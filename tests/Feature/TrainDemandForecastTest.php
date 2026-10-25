<?php

use App\Console\Commands\TrainDemandForecast;
use App\Models\Category;
use App\Models\ForecastPayload;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Forecasting\StoredDemandForecastService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('production local-model training does not call the forecast API', function () {
    Storage::fake('forecast');
    config(['forecast.python_binary' => base_path('missing-forecast-python')]);
    Http::fake();
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Demand forecast training failed.'
            && isset($context['exception'])
        );

    $this->artisan('forecast:train', ['--local-model' => true])
        ->expectsOutput('Demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    Http::assertNothingSent();
    expect(json_decode(Storage::disk('forecast')->get('forecast/training-status.json'), true)['status'])
        ->toBe('failed');
});

test('forecast training modes cannot be combined', function () {
    Storage::fake('forecast');

    $this->artisan('forecast:train', [
        '--local-model' => true,
        '--test-dataset' => true,
    ])
        ->expectsOutput('The sample, test-dataset, and local-model options cannot be used together.')
        ->assertExitCode(Command::FAILURE);

    expect(Storage::disk('forecast')->exists('datasets/real-stock-out-data.csv'))->toBeFalse();
});

test('local-model training marks unmatched forecast items for demand-only display', function () {
    $command = app(TrainDemandForecast::class);
    $method = new ReflectionMethod(TrainDemandForecast::class, 'annotateForecastInventoryMatches');
    $forecast = [
        'forecasts' => [['inventory_id' => 901, 'item_name' => 'Bond Paper']],
        'insufficient_history' => [['inventory_id' => 902, 'item_name' => 'Chalk']],
    ];

    $result = $method->invoke($command, $forecast, [
        901 => [],
        902 => [12],
    ]);

    expect($result['forecasts'][0]['matched_inventory_ids'])->toBe([])
        ->and($result['insufficient_history'][0]['matched_inventory_ids'])->toBe([12]);
});

test('local-model training maps historical items and combines their usage with database exports', function () {
    Storage::fake('forecast');

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $user->id,
        'item_name' => 'Bond Paper',
        'unit' => 'reams',
        'quantity' => 10,
        'unit_cost' => 10,
        'date_acquired' => now()->subYear()->toDateString(),
        'status' => 'available',
    ]);
    $usageDate = now()->startOfMonth()->subMonth()->endOfMonth();
    $historyMonth = $usageDate->format('Y-m');
    $historicalStartMonth = $usageDate->copy()->subMonths(2)->format('Y-m');
    $databaseUsageDate = $usageDate->copy()->subMonths(4)->endOfMonth();
    $databaseMovement = StockMovement::create([
        'inventory_id' => $inventory->item_id,
        'user_id' => $user->id,
        'movement_type' => 'stock_out',
        'quantity' => 3,
        'quantity_before' => 10,
        'quantity_after' => 7,
    ]);
    $databaseMovement->forceFill([
        'created_at' => $databaseUsageDate,
        'updated_at' => $databaseUsageDate,
    ])->save();
    $historicalPath = Storage::disk('forecast')->path('datasets/historical-usage.csv');
    Storage::disk('forecast')->put('datasets/historical-usage.csv', implode(PHP_EOL, [
        'inventory_id,item_name,category_id,category,unit,quantity_issued,stock_out_date,history_start_month,history_end_month,history_complete,movement_type,status',
        "9001,Bond Paper,900,Office Supplies,reams,12,{$usageDate->toDateString()},{$historicalStartMonth},{$historyMonth},true,stock_out,approved",
    ]));
    config([
        'forecast.historical_dataset_path' => $historicalPath,
        'forecast.python_binary' => base_path('missing-forecast-python'),
    ]);
    Http::fake();
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Demand forecast training failed.'
            && isset($context['exception'])
        );

    $this->artisan('forecast:train', ['--local-model' => true])
        ->expectsOutput('Demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    Http::assertNothingSent();
    $rows = array_map(
        fn (string $line): array => str_getcsv($line, escape: ''),
        preg_split('/\r\n|\r|\n/', trim(Storage::disk('forecast')->get('datasets/real-stock-out-data.csv'))),
    );
    $usageRows = array_slice($rows, 1);
    expect(count($usageRows))->toBe(4)
        ->and(array_unique(array_column($usageRows, 0)))->toBe([(string) $inventory->item_id])
        ->and(array_unique(array_column($usageRows, 2)))->toBe([(string) $category->category_id])
        ->and(array_unique(array_column($usageRows, 8)))->toBe([$historyMonth])
        ->and(array_unique(array_column($usageRows, 9)))->toBe(['false'])
        ->and(array_column($usageRows, 5))->toBe(['3', '12', '0', '0'])
        ->and(json_decode(Storage::disk('forecast')->get('forecast/training-status.json'), true)['status'])
        ->toBe('failed');
});

test('unmatched historical items are exported with forecast-only identities', function () {
    Storage::fake('forecast');

    $historicalPath = Storage::disk('forecast')->path('datasets/historical-usage.csv');
    Storage::disk('forecast')->put('datasets/historical-usage.csv', implode(PHP_EOL, [
        'inventory_id,item_name,category_id,category,unit,quantity_issued,stock_out_date,history_start_month,history_end_month,history_complete,movement_type,status',
        '9001,Bond Paper (A4),900,Consumables,reams,120,2026-06-30,2026-06,2026-09,true,stock_out,approved',
    ]));
    config([
        'forecast.historical_dataset_path' => $historicalPath,
        'forecast.python_binary' => base_path('missing-forecast-python'),
    ]);
    Http::fake();
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Demand forecast training failed.'
            && isset($context['exception'])
        );

    $this->artisan('forecast:train', ['--local-model' => true])
        ->expectsOutput('Demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    $rows = array_map(
        fn (string $line): array => str_getcsv($line, escape: ''),
        preg_split('/\r\n|\r|\n/', trim(Storage::disk('forecast')->get('datasets/real-stock-out-data.csv'))),
    );
    expect(count($rows))->toBe(5)
        ->and($rows[1][0])->toBe('1')
        ->and((int) $rows[1][2])->toBeGreaterThan(0)
        ->and($rows[1][2])->not->toBe('900')
        ->and($rows[1][1])->toBe('Bond Paper (A4)')
        ->and($rows[1][3])->toBe('Consumables')
        ->and($rows[1][5])->toBe('120');
});

test('training exports stock-outs and logs forecast API failures', function () {
    Storage::fake('forecast');

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $user->id,
        'item_name' => 'Bond Paper',
        'unit' => 'reams',
        'quantity' => 10,
        'unit_cost' => 10,
        'date_acquired' => now()->subYear()->toDateString(),
        'status' => 'available',
    ]);
    $stockOutDate = now()->subMonths(2)->startOfMonth();
    Storage::disk('forecast')->put('forecast/forecast.json', '{"previous":"forecast"}');

    $movement = StockMovement::create([
        'inventory_id' => $inventory->item_id,
        'user_id' => $user->id,
        'movement_type' => 'stock_out',
        'quantity' => 3,
        'quantity_before' => 10,
        'quantity_after' => 7,
        'notes' => 'Approved stock-out',
    ]);
    $movement->forceFill([
        'created_at' => $stockOutDate,
        'updated_at' => $stockOutDate,
    ])->save();
    $issuance = StockMovement::create([
        'inventory_id' => $inventory->item_id,
        'user_id' => $user->id,
        'movement_type' => 'issuance',
        'quantity' => 2,
        'quantity_before' => 7,
        'quantity_after' => 5,
        'reference_type' => 'transaction',
        'reference_id' => 999,
    ]);
    $issuance->forceFill(['created_at' => $stockOutDate->copy()->addDays(2), 'updated_at' => $stockOutDate])->save();
    $transfer = StockMovement::create([
        'inventory_id' => $inventory->item_id,
        'user_id' => $user->id,
        'movement_type' => 'transfer',
        'quantity' => 4,
        'quantity_before' => 7,
        'quantity_after' => 3,
    ]);
    $transfer->forceFill(['created_at' => $stockOutDate->copy()->addDays(3), 'updated_at' => $stockOutDate])->save();
    $durableCategory = Category::create(['category_name' => 'ICT Equipment', 'requires_serial_number' => true]);
    $laptop = Inventory::create([
        'category_id' => $durableCategory->category_id,
        'user_id' => $user->id,
        'item_name' => 'Laptop',
        'unit' => 'pieces',
        'quantity' => 1,
        'unit_cost' => 10,
        'date_acquired' => now()->subYear()->toDateString(),
        'status' => 'available',
        'serial_number' => 'LAPTOP-FORECAST-1',
    ]);
    $durableMovement = StockMovement::create([
        'inventory_id' => $laptop->item_id,
        'user_id' => $user->id,
        'movement_type' => 'stock_out',
        'quantity' => 1,
        'quantity_before' => 1,
        'quantity_after' => 0,
    ]);
    $durableMovement->forceFill(['created_at' => $stockOutDate, 'updated_at' => $stockOutDate])->save();

    config([
        'forecast.api_url' => 'https://forecast.example.test',
        'forecast.api_token' => 'forecast-test-token',
    ]);
    Http::fake(['*' => Http::response(['detail' => 'Service unavailable'], 503)]);
    $expectedCsvPath = Storage::disk('forecast')->path('datasets/real-stock-out-data.csv');
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Demand forecast training failed.'
            && $context['csv_path'] === $expectedCsvPath
            && isset($context['exception'])
        );

    $this->artisan('forecast:train')
        ->expectsOutput('Demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://forecast.example.test/forecast'
        && $request->hasHeader('Authorization', 'Bearer '.config('forecast.api_token'))
        && $request->method() === 'POST'
    );

    $csv = Storage::disk('forecast')->get('datasets/real-stock-out-data.csv');
    $rows = array_map(
        fn (string $line): array => str_getcsv($line, escape: ''),
        preg_split('/\r\n|\r|\n/', trim($csv)),
    );

    expect($rows[0])->toBe([
        'inventory_id', 'item_name', 'category_id', 'category', 'unit', 'quantity_issued',
        'stock_out_date', 'history_start_month', 'history_end_month', 'history_complete',
        'movement_type', 'status',
    ])
        ->and($rows[1])->toBe([
            (string) $inventory->item_id, 'Bond Paper', (string) $category->category_id,
            'Office Supplies', 'reams', '3', $stockOutDate->toDateString(),
            $stockOutDate->format('Y-m'), now()->subMonth()->format('Y-m'), 'false', 'stock_out', 'approved',
        ])
        ->and($rows[2][0])->toBe((string) $inventory->item_id)
        ->and($rows[2][10])->toBe('issuance')
        ->and(count($rows))->toBe(3)
        ->and(json_decode(Storage::disk('forecast')->get('forecast/training-status.json'), true)['status'])->toBe('failed')
        ->and(app(StoredDemandForecastService::class)->read($user)['status'])->toBe('failed');
});

test('training persists the forecast JSON returned by the API', function () {
    Storage::fake('forecast');
    config([
        'forecast.api_url' => 'https://forecast.example.test',
        'forecast.api_token' => 'forecast-test-token',
    ]);
    Http::fake(['*' => Http::response([
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => now()->toIso8601String(),
        'forecasts' => [],
        'insufficient_history' => [],
    ])]);

    $this->artisan('forecast:train')
        ->expectsOutput('Demand forecast trained and saved.')
        ->assertExitCode(Command::SUCCESS);

    $this->assertDatabaseHas('forecast_payloads', [
        'source_type' => 'live',
        'training_status' => 'success',
    ]);
    expect(json_decode(Storage::disk('forecast')->get('forecast/training-status.json'), true)['status'])
        ->toBe('success');
});

test('sample training uses isolated demo paths and never overwrites live artifacts', function () {
    Storage::fake('forecast');
    Storage::disk('forecast')->put('forecast/forecast.json', '{"source":"live"}');
    Storage::disk('forecast')->put('models/demand-model.joblib', 'live-model');
    config(['forecast.python_binary' => base_path('missing-forecast-python')]);

    $expectedCsvPath = config('forecast.demo.input_path');
    $expectedOutputPath = Storage::disk('forecast')->path(config('forecast.demo.output_path'));

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Demo demand forecast training failed.'
            && $context['csv_path'] === $expectedCsvPath
            && $context['output_path'] === $expectedOutputPath
            && isset($context['exception'])
        );

    $this->artisan('forecast:train', ['--sample' => true])
        ->expectsOutput('Demo demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    expect(Storage::disk('forecast')->get('forecast/forecast.json'))->toBe('{"source":"live"}')
        ->and(Storage::disk('forecast')->get('models/demand-model.joblib'))->toBe('live-model')
        ->and(Storage::disk('forecast')->exists('datasets/real-stock-out-data.csv'))->toBeFalse()
        ->and(Storage::disk('forecast')->exists(config('forecast.demo.output_path')))->toBeFalse();
});

test('test dataset training failure does not replace the current live forecast', function () {
    Storage::fake('forecast');
    $testDatasetPath = Storage::disk('forecast')->path('datasets/dummay-datasets.csv');
    Storage::disk('forecast')->put('datasets/dummay-datasets.csv', 'inventory_id,item_name');
    config([
        'forecast.test_dataset_path' => $testDatasetPath,
        'forecast.python_binary' => base_path('missing-forecast-python'),
    ]);
    Http::fake();
    $existingPayload = '{"existing":"forecast"}';
    app(StoredDemandForecastService::class)->persistTraining('live', 'success', $existingPayload);

    $this->artisan('forecast:train', ['--test-dataset' => true])
        ->expectsOutput('Test dataset demand forecast training failed. The existing live forecast was left unchanged.')
        ->assertExitCode(Command::FAILURE);

    Http::assertNothingSent();
    expect(Inventory::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(Storage::disk('forecast')->exists(config('forecast.demo.output_path')))->toBeFalse()
        ->and(Storage::disk('forecast')->exists('forecast/training-status.json'))->toBeFalse()
        ->and(ForecastPayload::query()->where('source_type', 'live')->value('payload'))->toBe($existingPayload)
        ->and(ForecastPayload::query()->where('source_type', 'live')->value('training_status'))->toBe('success');
});

test('dummy dataset training is rejected outside local and testing environments', function () {
    Storage::fake('forecast');
    $this->app->detectEnvironment(fn () => 'production');

    $this->artisan('forecast:train', ['--test-dataset' => true])
        ->expectsOutput('The dummy dataset option is available only in local and testing environments.')
        ->assertExitCode(Command::FAILURE);

    expect(Storage::disk('forecast')->exists('datasets/real-stock-out-data.csv'))->toBeFalse()
        ->and(Storage::disk('forecast')->exists(config('forecast.demo.output_path')))->toBeFalse();
});
