<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'perf-custodian',
        'email' => 'perf-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'perf-end-user',
        'email' => 'perf-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->general = Category::firstOrCreate(
        ['category_name' => 'General Supplies'],
        ['requires_serial_number' => false, 'is_maintenance_eligible' => true],
    );

    $this->furniture = Category::firstOrCreate(
        ['category_name' => 'Furniture'],
        ['requires_serial_number' => false, 'is_maintenance_eligible' => false],
    );
    // The school category sync seeds Furniture as maintenance-eligible;
    // this performance fixture needs an ineligible furniture row.
    $this->furniture->update(['requires_serial_number' => false, 'is_maintenance_eligible' => false]);

    $this->ict = Category::firstOrCreate(
        ['category_name' => 'ICT Equipment'],
        ['requires_serial_number' => true, 'is_maintenance_eligible' => true],
    );

    $row = function (string $name, Category $category, string $unit, string $status, int $quantity, array $extra = []) {
        static $counter = 0;
        $counter++;

        return Inventory::create(array_merge([
            'category_id' => $category->category_id,
            'unit' => $unit,
            'user_id' => $this->custodian->id,
            'item_name' => $name,
            'quantity' => $quantity,
            'status' => $status,
            'date_acquired' => '2026-01-10',
        ], $extra));
    };

    // Bulk groups across every workspace tab.
    for ($i = 0; $i < 40; $i++) {
        $row('Paper', $this->general, 'piece', 'available', 2);
    }
    for ($i = 0; $i < 10; $i++) {
        $row('Paper', $this->general, 'box', 'available', 5);
    }
    for ($i = 0; $i < 8; $i++) {
        $row('Wooden Chair', $this->general, 'piece', 'under_maintenance', 1);
    }
    for ($i = 0; $i < 6; $i++) {
        $row('Stapler', $this->general, 'piece', 'under_inspection', 2);
    }
    for ($i = 0; $i < 5; $i++) {
        $row('Old Monitor', $this->general, 'piece', 'ready_to_dispose', 1);
    }
    for ($i = 0; $i < 7; $i++) {
        $row('Broken Fan', $this->general, 'piece', 'disposed', 1);
    }
    // An 'assigned' record always stores 0 in inventory.quantity: issueManual zeroes the
// column and moves the units onto the assignment transaction. The fixture has to
// mirror that or it asserts on a state the application cannot produce.
    $assignedItemIds = [];
    for ($i = 0; $i < 4; $i++) {
        $assignedItemIds[] = $row('Projector', $this->general, 'piece', 'assigned', 0)->item_id;
    }
    foreach ($assignedItemIds as $assignedItemId) {
        Transaction::create([
            'user_id' => $this->endUser->id,
            'item_id' => $assignedItemId,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => '2026-02-01',
            'status' => 'assigned',
        ]);
    }
    for ($i = 0; $i < 12; $i++) {
        $row('Office Desk', $this->furniture, 'piece', 'available', 3);
    }

    $this->laptopIds = collect();
    for ($i = 0; $i < 6; $i++) {
        $item = $row('Laptop', $this->ict, 'piece', 'available', 1, ['serial_number' => 'SN-PERF-' . $i]);
        $this->laptopIds->push($item->item_id);
    }

    // Assigned transactions backing the receive-return details.
    $paperRow = Inventory::where('item_name', 'Paper')->where('unit', 'piece')->firstOrFail();
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $paperRow->item_id,
        'quantity' => 2,
        'issued_quantity' => 2,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);
});

test('inventory aggregates match independent recomputation at volume', function () {
    $response = $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory'))->assertOk();

    // Independent recomputation of the intended semantics: custody-based statuses
    // (assigned, and items flagged for inspection while still with a user) read the
    // active assignment transaction, everything else reads the stored column.
    $activeAssignments = Transaction::query()
        ->where('status', 'assigned')
        ->groupBy('item_id')
        ->select('item_id')
        ->selectRaw('SUM(quantity) as active_quantity')
        ->pluck('active_quantity', 'item_id');

    $effective = fn (Inventory $item): int => in_array($item->status, ['assigned', 'under_inspection'], true)
        ? ((int) ($activeAssignments[$item->item_id] ?? 0) ?: (int) $item->quantity)
        : (int) $item->quantity;

    $liveItems = Inventory::query()->where('status', '!=', 'disposed')->get();

    $expectedTotal = (int) $liveItems->sum($effective);

    $response->assertJsonPath('inventoryMetrics.total', $expectedTotal)
        ->assertJsonPath('inventoryMetrics.available', (int) $liveItems->where('status', 'available')->sum($effective))
        ->assertJsonPath('inventoryMetrics.assigned', (int) $liveItems->where('status', 'assigned')->sum($effective))
        ->assertJsonPath(
            'inventoryMetrics.attention',
            (int) $liveItems->whereIn('status', ['under_inspection', 'under_maintenance'])->sum($effective)
        );

    // No status may be double counted: every live unit lands in exactly one bucket.
    expect($expectedTotal)->toBe(
        (int) $liveItems->groupBy('status')->sum(fn ($items) => (int) $items->sum($effective))
    );

    foreach (['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose'] as $status) {
        $response->assertJsonPath(
            "inventoryStatusCounts.{$status}",
            (int) $liveItems->where('status', $status)->sum($effective)
        );
    }
    $response->assertJsonPath(
        'inventoryStatusCounts.disposed',
        (int) Inventory::where('status', 'disposed')->sum('quantity')
    );
});

test('maintenance picker still lists every eligible available row', function () {
    $response = $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory'))->assertOk();

    $expected = Inventory::where('status', 'available')
        ->whereHas('category', fn ($query) => $query->where('is_maintenance_eligible', '!=', false))
        ->count();
    // Categories without a record also count as eligible, mirroring the controller filter.
    $expected += Inventory::where('status', 'available')->whereDoesntHave('category')->count();

    $listed = collect($response->json('maintenanceItems'))->flatten(1)->count();

    expect($listed)->toBe($expected)->and($expected)->toBeGreaterThan(0);
});

test('edit details carry serial numbers for displayed serialized rows', function () {
    $response = $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory'))->assertOk();

    $details = $response->json('editItemDetails');

    foreach ($this->laptopIds as $index => $itemId) {
        expect($details[(string) $itemId]['serialNumbers'] ?? null)
            ->toContain('SN-PERF-' . $index);
    }
});

test('inventory page performs a bounded number of queries', function () {
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory'))->assertOk();

    // Tripwire against N+1 regressions (e.g. per-group fallback lookups):
    // a bounded page must never scale its query count with total rows.
    expect($queries)->toBeLessThan(60);
});

test('inventory API returns inventory groups at volume', function () {
    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory'))
        ->assertOk()
        ->assertJsonFragment(['item_name' => 'Paper'])
        ->assertJsonFragment(['item_name' => 'Laptop'])
        ->assertJsonFragment(['item_name' => 'Office Desk']);
});
