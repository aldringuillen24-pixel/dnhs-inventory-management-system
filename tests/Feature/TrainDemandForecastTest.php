<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StoredDemandForecastService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('training exports stock-outs to forecast storage and logs Python failures', function () {
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

    config(['forecast.python_binary' => base_path('missing-forecast-python')]);
    $expectedCsvPath = Storage::disk('forecast')->path('forecast/real-stock-out-data.csv');
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool =>
            $message === 'Demand forecast training failed.'
            && $context['csv_path'] === $expectedCsvPath
            && isset($context['exception'])
        );

    $this->artisan('forecast:train')
        ->expectsOutput('Demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    $csv = Storage::disk('forecast')->get('forecast/real-stock-out-data.csv');
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

test('sample training uses isolated demo paths and never overwrites live artifacts', function () {
    Storage::fake('forecast');
    Storage::disk('forecast')->put('forecast/forecast.json', '{"source":"live"}');
    Storage::disk('forecast')->put('models/demand-model.joblib', 'live-model');
    config(['forecast.python_binary' => base_path('missing-forecast-python')]);

    $expectedCsvPath = config('forecast.demo.input_path');
    $expectedOutputPath = Storage::disk('forecast')->path(config('forecast.demo.output_path'));

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool =>
            $message === 'Demo demand forecast training failed.'
            && $context['csv_path'] === $expectedCsvPath
            && $context['output_path'] === $expectedOutputPath
            && isset($context['exception'])
        );

    $this->artisan('forecast:train', ['--sample' => true])
        ->expectsOutput('Demo demand forecast training failed. Check the Laravel log for details.')
        ->assertExitCode(Command::FAILURE);

    expect(Storage::disk('forecast')->get('forecast/forecast.json'))->toBe('{"source":"live"}')
        ->and(Storage::disk('forecast')->get('models/demand-model.joblib'))->toBe('live-model')
        ->and(Storage::disk('forecast')->exists('forecast/real-stock-out-data.csv'))->toBeFalse()
        ->and(Storage::disk('forecast')->exists(config('forecast.demo.output_path')))->toBeFalse();
});