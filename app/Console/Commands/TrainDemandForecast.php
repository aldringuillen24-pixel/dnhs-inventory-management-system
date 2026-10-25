<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ForecastPayload;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Forecasting\StoredDemandForecastService;
use App\Support\RequestableItemMatcher;
use App\Support\SampleForecastData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class TrainDemandForecast extends Command
{
    protected $signature = 'forecast:train
        {--sample : Train from the isolated ml/demo_stock_out_data.csv}
        {--test-dataset : Train the live forecast from the local dummy dataset (local/testing only)}
        {--local-model : Run the production Python model locally instead of calling the forecast API}';

    protected $description = 'Export eligible completed demand and train the production ML forecast.';

    private const LIVE_TRAINING_STATUS_PATH = 'forecast/training-status.json';

    private const CSV_COLUMNS = [
        'inventory_id', 'item_name', 'category_id', 'category', 'unit', 'quantity_issued',
        'stock_out_date', 'history_start_month', 'history_end_month', 'history_complete',
        'movement_type', 'status',
    ];

    public function __construct(
        protected StoredDemandForecastService $storedForecastService,
    )
    {
        parent::__construct();
    }

    /**
     * Category-name substrings that identify demand-forecastable stock.
     *
     * Public so sample-data tooling (SampleForecastHistorySeeder) resolves
     * eligible categories from this exact list instead of duplicating the
     * terms, which would let the two drift apart and silently train on
     * categories the exporter later drops.
     */
    public const DEMAND_CATEGORY_TERMS = [
        'consumable', 'supply', 'supplies', 'learning resource', 'office material', 'stationery', 'paper', 'ink', 'marker',
    ];

    public function handle(): int
    {
        $trainingModes = collect(['sample', 'test-dataset', 'local-model'])
            ->filter(fn (string $option): bool => (bool) $this->option($option));
        if ($trainingModes->count() > 1) {
            $this->error('The sample, test-dataset, and local-model options cannot be used together.');

            return self::FAILURE;
        }
        $disk = Storage::disk('forecast');
        if ($this->option('sample')) {
            return $this->trainDemoForecast($disk);
        }
        if ($this->option('test-dataset') && ! app()->environment(['local', 'testing'])) {
            $this->error('The dummy dataset option is available only in local and testing environments.');

            return self::FAILURE;
        }
        if ($this->option('test-dataset')) {
            return $this->trainTestDataset($disk);
        }

        $csvPath = $disk->path('datasets/real-stock-out-data.csv');

        try {
            $disk->makeDirectory('forecast');
            $disk->makeDirectory('datasets');
            $this->writeLiveTrainingStatus($disk, 'running');
            $this->exportApprovedStockOuts($csvPath);

            $forecast = $this->option('local-model')
                ? $this->trainExportLocally($csvPath)
                : $this->trainExportViaApi($csvPath);
            if (! is_array($forecast)
                || ($forecast['schema_version'] ?? null) !== 2
                || ($forecast['source_type'] ?? null) !== ForecastPayload::SOURCE_LIVE
                || ($forecast['model_version'] ?? null) !== 'demand-linear-regression-v2'
                || ! is_string($forecast['generated_at'] ?? null)
                || ! is_array($forecast['forecasts'] ?? null)
                || ! array_is_list($forecast['forecasts'])
                || ! is_array($forecast['insufficient_history'] ?? null)
                || ! array_is_list($forecast['insufficient_history'])) {
                throw new \UnexpectedValueException('Forecast trainer returned an invalid forecast document.');
            }

            $this->storedForecastService->persistTraining(
                ForecastPayload::SOURCE_LIVE,
                'success',
                json_encode($forecast, JSON_THROW_ON_ERROR),
            );

            $this->writeLiveTrainingStatus($disk, 'success');
            $this->info('Demand forecast trained and saved.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->writeLiveTrainingStatus($disk, 'failed');
            $this->storedForecastService->persistTraining(ForecastPayload::SOURCE_LIVE, 'failed');
            Log::error('Demand forecast training failed.', [
                'exception' => $exception,
                'csv_path' => $csvPath,
            ]);
            $this->error('Demand forecast training failed. Check the Laravel log for details.');

            return self::FAILURE;
        }
    }

    private function trainExportViaApi(string $csvPath): mixed
    {
        $apiUrl = rtrim((string) config('forecast.api_url'), '/');
        $apiToken = (string) config('forecast.api_token');
        if ($apiUrl === '' || $apiToken === '') {
            throw new \RuntimeException('Forecast API URL and token must be configured.');
        }
        if (parse_url($apiUrl, PHP_URL_SCHEME) !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Forecast API URL must use HTTPS outside local and testing environments.');
        }

        $csv = fopen($csvPath, 'rb');
        if ($csv === false) {
            throw new \RuntimeException("Unable to read forecast export: {$csvPath}");
        }

        try {
            $response = Http::connectTimeout(10)
                ->timeout(120)
                ->withToken($apiToken)
                ->attach('file', $csv, basename($csvPath))
                ->post($apiUrl.'/forecast', [
                    'minimum_history' => max(1, (int) config('forecast.minimum_history', 3)),
                ]);
        } finally {
            fclose($csv);
        }

        $response->throw();

        return $response->json();
    }

    private function trainExportLocally(string $csvPath): array
    {
        $trainerPath = base_path('ml/forecast_demand.py');
        $outputPath = tempnam(sys_get_temp_dir(), 'dnhs-forecast-json-');
        $modelPath = tempnam(sys_get_temp_dir(), 'dnhs-forecast-model-');

        try {
            if ($outputPath === false || $modelPath === false) {
                throw new \RuntimeException('Unable to create temporary forecast training outputs.');
            }
            if (! is_readable($csvPath) || ! is_readable($trainerPath)) {
                throw new \RuntimeException('Production forecast export or trainer is missing or unreadable.');
            }

            $process = new Process([
                config('forecast.python_binary'),
                $trainerPath,
                '--input', $csvPath,
                '--output', $outputPath,
                '--model-output', $modelPath,
                '--minimum-history', (string) max(1, (int) config('forecast.minimum_history', 3)),
            ], base_path());
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Production forecast training process failed.');
            }

            return json_decode(file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            if (is_string($outputPath) && is_file($outputPath)) {
                unlink($outputPath);
            }
            if (is_string($modelPath) && is_file($modelPath)) {
                unlink($modelPath);
            }
        }
    }

    private function trainTestDataset($disk): int
    {
        $csvPath = (string) config('forecast.test_dataset_path');
        $trainerPath = base_path('ml/forecast_demand.py');
        $outputPath = tempnam(sys_get_temp_dir(), 'dnhs-forecast-json-');
        $modelPath = tempnam(sys_get_temp_dir(), 'dnhs-forecast-model-');

        try {
            if ($outputPath === false || $modelPath === false) {
                throw new \RuntimeException('Unable to create temporary forecast training outputs.');
            }
            if (! is_readable($csvPath) || ! is_readable($trainerPath)) {
                throw new \RuntimeException('Test forecast dataset or trainer is missing or unreadable.');
            }

            $process = new Process([
                config('forecast.python_binary'),
                $trainerPath,
                '--input', $csvPath,
                '--output', $outputPath,
                '--model-output', $modelPath,
                '--minimum-history', (string) max(1, (int) config('forecast.minimum_history', 3)),
            ], base_path());
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Dummy dataset training process failed.');
            }

            $forecast = json_decode(file_get_contents($outputPath), true, 512, JSON_THROW_ON_ERROR);
            $forecast = $this->mapTestForecastInventory($forecast);
            $this->storedForecastService->persistTraining(
                ForecastPayload::SOURCE_LIVE,
                'success',
                json_encode($forecast, JSON_THROW_ON_ERROR),
            );
            $this->writeLiveTrainingStatus($disk, 'success');

            $this->info('Test dataset forecast trained and saved as the live forecast.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error('Test dataset demand forecast training failed.', [
                'exception' => $exception,
                'csv_path' => $csvPath,
            ]);
            $this->error('Test dataset demand forecast training failed. The existing live forecast was left unchanged.');

            return self::FAILURE;
        } finally {
            if (is_string($outputPath) && is_file($outputPath)) {
                unlink($outputPath);
            }
            if (is_string($modelPath) && is_file($modelPath)) {
                unlink($modelPath);
            }
        }
    }

    private function mapTestForecastInventory(mixed $forecast): array
    {
        if (! is_array($forecast)
            || ($forecast['schema_version'] ?? null) !== 2
            || ($forecast['source_type'] ?? null) !== ForecastPayload::SOURCE_LIVE
            || ($forecast['model_version'] ?? null) !== 'demand-linear-regression-v2'
            || ! is_string($forecast['generated_at'] ?? null)
            || ! is_array($forecast['forecasts'] ?? null)
            || ! array_is_list($forecast['forecasts'])
            || ! is_array($forecast['insufficient_history'] ?? null)
            || ! array_is_list($forecast['insufficient_history'])) {
            throw new \UnexpectedValueException('Dummy dataset trainer returned an invalid forecast document.');
        }

        $inventory = Inventory::query()
            ->withoutGlobalScope(SampleForecastData::SCOPE)
            ->with('category')
            ->get();
        $categoryIdsByName = Category::query()
            ->get()
            ->keyBy(fn (Category $category): string => mb_strtolower(trim($category->category_name)));
        $nextSyntheticId = ((int) Inventory::withoutGlobalScope(SampleForecastData::SCOPE)->max('item_id')) + 1;
        $seen = [];

        foreach (['forecasts', 'insufficient_history'] as $group) {
            foreach ($forecast[$group] as &$item) {
                if (! is_array($item)
                    || ! is_string($item['item_name'] ?? null)
                    || ! is_string($item['category'] ?? null)
                    || ! is_string($item['unit'] ?? null)) {
                    throw new \UnexpectedValueException('Dummy dataset trainer returned an invalid item identity.');
                }

                $matches = $inventory->filter(fn (Inventory $candidate): bool =>
                    $candidate->category
                    && mb_strtolower(trim((string) $candidate->category->category_name)) === mb_strtolower(trim($item['category']))
                    && RequestableItemMatcher::nameMatches($item['item_name'], (string) $candidate->item_name)
                    && RequestableItemMatcher::unitMatches($item['unit'], $candidate->unit)
                )->values();

                if ($matches->isNotEmpty()) {
                    $primary = $matches->first();
                    $item['inventory_id'] = (int) $primary->item_id;
                    $item['category_id'] = (int) $primary->category_id;
                    $item['matched_inventory_ids'] = $matches->pluck('item_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                } else {
                    $item['inventory_id'] = $nextSyntheticId++;
                    $category = $categoryIdsByName->get(mb_strtolower(trim($item['category'])));
                    if ($category) {
                        $item['category_id'] = (int) $category->category_id;
                    }
                }

                $identity = $item['inventory_id'].':'.$item['category_id'];
                if (isset($seen[$identity])) {
                    throw new \UnexpectedValueException('Dummy dataset contains multiple forecasts that map to the same inventory item.');
                }
                $seen[$identity] = true;
            }
            unset($item);
        }

        return $forecast;
    }

    private function trainDemoForecast($disk): int
    {
        $csvPath = config('forecast.demo.input_path');
        $outputPath = $disk->path(config('forecast.demo.output_path'));
        $modelPath = $disk->path(config('forecast.demo.model_path'));
        $trainerPath = config('forecast.demo.trainer_path');

        try {
            if (! is_readable($csvPath) || ! is_readable($trainerPath)) {
                throw new \RuntimeException('Demo forecast source data or trainer is missing or unreadable.');
            }

            $disk->makeDirectory(dirname(config('forecast.demo.output_path')));
            $disk->makeDirectory(dirname(config('forecast.demo.model_path')));
            $process = new Process([
                config('forecast.python_binary'),
                $trainerPath,
                '--input', $csvPath,
                '--output', $outputPath,
                '--model-output', $modelPath,
                '--minimum-history', (string) max(1, (int) config('forecast.demo.minimum_history', 3)),
            ], base_path());
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Demo Python training process failed.');
            }

            $this->info('Sample/demo demand forecast trained and saved separately from live forecast data.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error('Demo demand forecast training failed.', [
                'exception' => $exception,
                'csv_path' => $csvPath,
                'output_path' => $outputPath,
            ]);
            $this->error('Demo demand forecast training failed. Check the Laravel log for details.');

            return self::FAILURE;
        }
    }

    private function writeLiveTrainingStatus($disk, string $status): void
    {
        $disk->put(self::LIVE_TRAINING_STATUS_PATH, json_encode([
            'status' => $status,
            'updated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }

    private function exportApprovedStockOuts(string $path): void
    {
        $completedMonth = now()->startOfMonth();
        $historyEndMonth = $completedMonth->copy()->subMonth()->format('Y-m');
        $historyStarts = $this->eligibleDemandMovements($completedMonth)
            ->selectRaw('inventory_id, MIN(created_at) as first_demand_at')
            ->groupBy('inventory_id')
            ->pluck('first_demand_at', 'inventory_id');

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Unable to create forecast export: {$path}");
        }

        try {
            fputcsv($handle, self::CSV_COLUMNS);

            $this->eligibleDemandMovements($completedMonth)
                // The scope has to be dropped here too, not only in the
                // whereHas above. withoutGlobalScope in a whereHas only affects
                // filtering; eager loading rebuilds the relation with the model's
                // own scopes, so $movement->inventory came back null for every
                // sample row and the guard below skipped all of them, producing
                // a header-only CSV and a training run with nothing to learn from.
                //
                // The constraint is attached to 'inventory' rather than to
                // 'inventory.category', because on a nested eager load Laravel
                // hands the closure to the deepest relation -- constraining
                // 'inventory.category' alone leaves inventory still scoped.
                ->with([
                    'inventory' => fn ($inventory) => $inventory->withoutGlobalScope(SampleForecastData::SCOPE),
                    'inventory.category',
                ])
                ->orderBy('created_at')
                ->each(function (StockMovement $movement) use ($handle, $historyStarts, $historyEndMonth): void {
                    if (! $movement->inventory) {
                        return;
                    }

                    fputcsv($handle, [
                        $movement->inventory->item_id,
                        $movement->inventory->item_name,
                        $movement->inventory->category_id,
                        $movement->inventory->category?->category_name ?? 'Uncategorized',
                        $movement->inventory->unit ?: 'units',
                        $movement->quantity,
                        $movement->created_at->toDateString(),
                        Carbon::parse($historyStarts[$movement->inventory->item_id])->format('Y-m'),
                        $historyEndMonth,
                        'false',
                        $movement->movement_type === 'assignment' ? 'issuance' : $movement->movement_type,
                        'approved',
                    ]);
                });
        } finally {
            fclose($handle);
        }
    }

    private function eligibleDemandMovements(Carbon $completedMonth)
    {
        $hasIssuanceTables = Schema::hasTable('assignment_requests') && Schema::hasTable('transactions');

        return StockMovement::query()
            // The training data may legitimately be the sample history on a
            // fresh installation, which the global scope hides everywhere else.
            ->withoutGlobalScope(SampleForecastData::SCOPE)
            ->where('quantity', '>', 0)
            ->where('created_at', '<', $completedMonth)
            ->where(function ($query) use ($hasIssuanceTables): void {
                $query->whereIn('movement_type', ['stock_out', 'issuance', 'issue'])
                    ->when($hasIssuanceTables, fn ($demand) => $demand->orWhere(function ($assignment): void {
                        $assignment->where('movement_type', 'assignment')
                            ->where(function ($reference): void {
                                $reference->where(function ($request): void {
                                    $request->where('reference_type', 'assignment_request')
                                        ->whereExists(function ($approvedRequest): void {
                                            $approvedRequest->selectRaw('1')
                                                ->from('assignment_requests')
                                                ->join('transactions', 'transactions.id', '=', 'assignment_requests.transaction_id')
                                                ->whereColumn('assignment_requests.id', 'stock_movements.reference_id')
                                                ->where('assignment_requests.status', 'approved')
                                                ->whereNotNull('assignment_requests.responded_at')
                                                ->where('transactions.status', 'assigned')
                                                ->whereNull('transactions.expected_return_date');
                                        });
                                })->orWhere(function ($transaction): void {
                                    $transaction->where('reference_type', 'transaction')
                                        ->whereExists(function ($completedTransaction): void {
                                            $completedTransaction->selectRaw('1')
                                                ->from('transactions')
                                                ->whereColumn('transactions.id', 'stock_movements.reference_id')
                                                ->where('transactions.status', 'assigned')
                                                ->whereNull('transactions.expected_return_date');
                                        });
                                });
                            });
                    }));
            })
            // Split into two steps so the inventory half can drop the scope that hides
            // sample rows. Written as whereHas('inventory.category', ...) the
            // scope is applied by the nested relation and the sample items
            // would silently stop contributing their history.
            ->whereHas('inventory', function ($inventory): void {
                $inventory->withoutGlobalScope(SampleForecastData::SCOPE)
                    ->whereHas('category', function ($category): void {
                        $category->where('requires_serial_number', false)
                            ->where(function ($terms): void {
                                foreach (self::DEMAND_CATEGORY_TERMS as $term) {
                                    $terms->orWhereRaw('LOWER(category_name) LIKE ?', ['%'.$term.'%']);
                                }
                            });
                    });
            });
    }
}
