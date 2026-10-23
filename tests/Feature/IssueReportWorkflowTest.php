<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'issue-custodian',
        'email' => 'issue-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'issue-end-user',
        'email' => 'issue-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->category = Category::create([
        'category_name' => 'Issue Report Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
});

function makeIssuedItem($test, string $name, int $quantity = 1): Inventory
{
    $item = Inventory::create([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => 0,
        'unit_cost' => 1000,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
        'assigned_to_user_id' => $test->endUser->id,
        'serial_number' => 'SN-'.uniqid(),
    ]);

    Transaction::create([
        'user_id' => $test->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => $quantity,
        'issued_quantity' => $quantity,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);

    return $item;
}

test('end user can report a damaged assigned item without moving stock', function () {
    $item = makeIssuedItem($this, 'Reported Chair', 3);
    $movementsBefore = StockMovement::where('inventory_id', $item->item_id)->count();

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 2,
            'notes' => 'Leg cracked',
        ])
        ->assertRedirect(route('endUser.my-assigned-items'))
        ->assertSessionHas('success', 'Issue reported for custodian triage.');

    $this->assertDatabaseHas('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
        'status' => 'reported',
        'diagnosis' => 'damaged',
        'quantity' => 2,
    ]);

    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and(StockMovement::where('inventory_id', $item->item_id)->count())->toBe($movementsBefore);
});

test('issue report quantity above the assigned quantity is rejected', function () {
    $item = makeIssuedItem($this, 'Over Reported Chair', 2);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 5,
        ])
        ->assertSessionHas('error', 'Report quantity must be between 1 and the assigned quantity.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(0);
});

test('duplicate open issue reports are rejected', function () {
    $item = makeIssuedItem($this, 'Twice Reported Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'lost'])
        ->assertSessionHas('error', 'You already have an open report for this item.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(1);
});

test('non-holders cannot report issues on items', function () {
    $item = makeIssuedItem($this, 'Stranger Reported Chair', 1);

    $stranger = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Stranger',
        'last_name' => 'User',
        'username' => 'issue-stranger',
        'email' => 'issue-stranger@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($stranger)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged'])
        ->assertSessionHas('error', 'You can only report issues on items assigned to you.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(0);
});

test('custodian can decline an issue report', function () {
    $item = makeIssuedItem($this, 'Declined Report Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'lost'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $record = \App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $this->actingAs($this->custodian)
        ->post(route('propertyCustodian.maintenance.decline', $record->id))
        ->assertRedirect(route('propertyCustodian.inventory'))
        ->assertSessionHas('success', 'Issue report declined.');

    expect($record->fresh()->status)->toBe('cancelled');

    $this->actingAs($this->custodian)
        ->post(route('propertyCustodian.maintenance.decline', $record->id))
        ->assertSessionHas('error', 'This report has already been processed.');
});

test('dashboard condition data shows reported damage while assigned', function () {
    $item = makeIssuedItem($this, 'Condition Report Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $conditions = collect(
        $this->actingAs($this->endUser)
            ->getJson(route('api.end-user.dashboard'))
            ->assertOk()
            ->json('conditionData')
    );

    expect($conditions->firstWhere('label', 'Damaged')['value'] ?? 0)->toBe(1);
});
