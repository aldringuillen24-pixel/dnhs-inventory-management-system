<?php

namespace App\Console\Commands;

use App\Models\ForecastPayload;
use App\Models\StockMovement;
use App\Services\StoredDemandForecastService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class TrainDemandForecast extends Command
{
    protected $signature = 'forecast:train {--sample : Train from the isolated ml/demo_stock_out_data.csv}';

    protected $description = 'Export eligible completed demand and train the production ML forecast.';

    private const LIVE_TRAINING_STATUS_PATH = 'forecast/training-status.json';

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
        $disk = Storage::disk('forecast');

        if ($this->option('sample')) {
            return $this->trainDemoForecast($disk);
        }

        $csvPath = $disk->path('forecast/real-stock-out-data.csv');
        $forecastPath = $disk->path('forecast/forecast.json');
        $modelPath = $disk->path('models/demand-model.joblib');

        try {
            $disk->makeDirectory('forecast');
            $disk->makeDirectory('models');
            $this->writeLiveTrainingStatus($disk, 'running');
            $this->exportApprovedStockOuts($csvPath);

            $process = new Process([
                config('forecast.python_binary'),
                base_path('ml/forecast_demand.py'),
                '--input', $csvPath,
                '--minimum-history', (string) max(1, (int) config('forecast.minimum_history', 3)),
                '--output', $forecastPath,
                '--model-output', $modelPath,
            ], base_path());
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Python training process failed.');
            }

            // The file is still written for local work and for the export used
            // by the training CSV, but the database copy is what any other
            // instance (a Render cron job, or the web service after a redeploy)
            // will actually read.
            $this->storedForecastService->persistTraining(
                ForecastPayload::SOURCE_LIVE,
                'success',
                $disk->get('forecast/forecast.json'),
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
            fputcsv($handle, [
                'inventory_id', 'item_name', 'category_id', 'category', 'unit', 'quantity_issued',
                'stock_out_date', 'history_start_month', 'history_end_month', 'history_complete',
                'movement_type', 'status',
            ]);

            $this->eligibleDemandMovements($completedMonth)
                ->with('inventory.category')
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
            ->whereHas('inventory.category', function ($category): void {
                $category->where('requires_serial_number', false)
                    ->where(function ($terms): void {
                        foreach (self::DEMAND_CATEGORY_TERMS as $term) {
                            $terms->orWhereRaw('LOWER(category_name) LIKE ?', ['%'.$term.'%']);
                        }
                    });
            });
    }
}
