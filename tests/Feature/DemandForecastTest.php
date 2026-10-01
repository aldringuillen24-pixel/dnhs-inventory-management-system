<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('forecast');
});

function forecastUser(string $roleName, string $username): User
{
    $role = Role::firstOrCreate(['role_name' => $roleName]);

    return User::factory()->create([
        'role_id' => $role->role_id,
        'username' => $username,
    ]);
}

function forecastInventory(User $owner, Category $category, string $name, int $available, string $status = 'available'): Inventory
{
    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'boxes',
        'user_id' => $owner->id,
        'item_name' => $name,
        'quantity' => $available,
        'unit_cost' => 10,
        'date_acquired' => '2025-01-01',
        'status' => $status,
    ]);
}

function forecastStockOut(Inventory $inventory, User $user, int $quantity, string $date, string $type = 'stock_out'): StockMovement
{
    $movement = StockMovement::create([
        'inventory_id' => $inventory->item_id,
        'user_id' => $user->id,
        'movement_type' => $type,
        'quantity' => $quantity,
        'quantity_before' => 100,
        'quantity_after' => 100 - $quantity,
        'notes' => 'Completed consumption event',
    ]);
    $movement->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

    return $movement->fresh();
}

function forecastRow(array $result, string $itemName): array
{
    return collect($result['rows'])->firstWhere('item_name', $itemName);
}

test('property custodians can run the forecast action', function () {
    $custodian = forecastUser('Property Custodian', 'forecast-custodian');

    $response = $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast'));

    $response->assertRedirect(route('propertyCustodian.reports'));
    expect(session('forecastResult'))->toHaveKeys(['status', 'rows', 'summary']);
});

test('live forecast service does not consume the isolated sample demo artifact', function () {
    $custodian = forecastUser('Property Custodian', 'demo-isolation-custodian');
    Storage::disk('forecast')->put('forecast/demo/forecast.json', json_encode([
        'schema_version' => 1,
        'source_type' => 'demo',
        'model_version' => 'demo-linear-regression-v1',
        'forecasts' => [[
            'inventory_id' => 1001,
            'item_name' => 'Sample Only Item',
            'category_id' => 10,
            'predicted_quantity' => 999,
        ]],
    ], JSON_THROW_ON_ERROR));

    $result = app(DemandForecastService::class)->forecast($custodian);

    expect($result['status'])->toBe('missing')
        ->and($result['rows'])->toBeEmpty()
        ->and(Storage::disk('forecast')->exists('forecast/forecast.json'))->toBeFalse();
});

test('only property custodians can access the forecast action', function (string $role) {
    $user = forecastUser($role, strtolower(str_replace(' ', '-', $role)) . '-forecast');

    $this->actingAs($user)->post(route('propertyCustodian.reports.forecast'))->assertForbidden();
})->with(['End User', 'School Head', 'Administrator']);

test('three completed movement months do not substitute for a stored ML forecast', function () {
    $custodian = forecastUser('Property Custodian', 'formula-custodian');
    $category = Category::create(['category_name' => 'Consumable Supplies', 'requires_serial_number' => false]);
    $inventory = forecastInventory($custodian, $category, 'Bond Paper', 20);

    forecastStockOut($inventory, $custodian, 50, '2026-06-15');
    forecastStockOut($inventory, $custodian, 60, '2026-07-15');
    forecastStockOut($inventory, $custodian, 55, '2026-08-15');

    $result = app(DemandForecastService::class)->forecast($custodian);
    expect($result['status'])->toBe('missing')
        ->and($result['rows'])->toBeEmpty();
});

test('sufficient available stock does not generate a forecast without stored ML output', function () {
    $custodian = forecastUser('Property Custodian', 'sufficient-custodian');
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $inventory = forecastInventory($custodian, $category, 'Whiteboard Marker', 35);

    forecastStockOut($inventory, $custodian, 20, '2026-06-15');
    forecastStockOut($inventory, $custodian, 19, '2026-07-15');
    forecastStockOut($inventory, $custodian, 21, '2026-08-15');

    $result = app(DemandForecastService::class)->forecast($custodian);

    expect($result['status'])->toBe('missing')->and($result['rows'])->toBeEmpty();
});

test('fewer than three completed movement months do not generate a substitute estimate', function () {
    $custodian = forecastUser('Property Custodian', 'insufficient-custodian');
    $category = Category::create(['category_name' => 'Consumables', 'requires_serial_number' => false]);
    $inventory = forecastInventory($custodian, $category, 'Printer Ink', 5);

    forecastStockOut($inventory, $custodian, 10, '2026-07-15');
    forecastStockOut($inventory, $custodian, 12, '2026-08-15');

    $result = app(DemandForecastService::class)->forecast($custodian);

    expect($result['status'])->toBe('missing')->and($result['rows'])->toBeEmpty();
});

test('transfers returns stock-ins disposals and durable assets do not affect demand', function () {
    $custodian = forecastUser('Property Custodian', 'filter-custodian');
    $endUser = forecastUser('End User', 'filter-end-user');
    $consumable = Category::create(['category_name' => 'Learning Resources', 'requires_serial_number' => false]);
    $durable = Category::create(['category_name' => 'ICT Equipment', 'requires_serial_number' => true]);
    $paper = forecastInventory($custodian, $consumable, 'Learning Paper', 10);
    $laptop = forecastInventory($custodian, $durable, 'Laptop', 0, 'disposed');

    forecastStockOut($paper, $custodian, 10, '2026-06-15');
    forecastStockOut($paper, $custodian, 20, '2026-07-15', 'transfer');
    forecastStockOut($paper, $custodian, 20, '2026-08-15', 'returned');
    forecastStockOut($paper, $custodian, 20, '2026-09-15', 'stock_in');
    forecastStockOut($laptop, $custodian, 99, '2026-06-15');

    $transaction = Transaction::create([
        'user_id' => $endUser->id,
        'item_id' => $paper->item_id,
        'quantity' => 7,
        'transaction_date' => '2026-06-15',
        'status' => 'assigned',
    ]);
    AssignmentRequest::create([
        'item_id' => $paper->item_id,
        'user_id' => $custodian->id,
        'target_user_id' => $endUser->id,
        'transaction_id' => $transaction->id,
        'quantity' => 7,
        'status' => 'approved',
        'requested_at' => '2026-06-14',
        'responded_at' => '2026-06-15',
    ]);

    $result = app(DemandForecastService::class)->forecast($custodian);

    expect($result['status'])->toBe('missing')->and($result['rows'])->toBeEmpty();
});

test('forecast execution does not change inventory or movement records', function () {
    $custodian = forecastUser('Property Custodian', 'readonly-custodian');
    $category = Category::create(['category_name' => 'Consumable Supplies', 'requires_serial_number' => false]);
    $inventory = forecastInventory($custodian, $category, 'Bond Paper', 20);
    forecastStockOut($inventory, $custodian, 50, '2026-06-15');
    forecastStockOut($inventory, $custodian, 60, '2026-07-15');
    forecastStockOut($inventory, $custodian, 55, '2026-08-15');
    $inventoryBefore = $inventory->fresh()->toArray();
    $movementCount = StockMovement::count();
    $transactionCount = Transaction::count();

    app(DemandForecastService::class)->forecast($custodian);

    expect($inventory->fresh()->toArray())->toEqual($inventoryBefore)
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(Transaction::count())->toBe($transactionCount);
});
