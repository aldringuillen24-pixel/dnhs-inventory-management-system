<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
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
        'username' => 'tx-custodian',
        'email' => 'tx-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'tx-end-user',
        'email' => 'tx-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->category = Category::create([
        'category_name' => 'General Supplies',
        'requires_serial_number' => false,
    ]);
});

function makeAvailableItem($test, string $name, int $quantity): Inventory
{
    return Inventory::create([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);
}

test('transactions api returns every workspace list as JSON', function () {
    $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonStructure([
            'availableInventoryItems',
            'endUsers',
            'incomingRequests',
            'assignmentRequests',
            'pendingTransfers',
            'pendingReturns',
            'transactions',
            'auditLedger',
            'totalAssignedCount',
            'pendingRequestsCount',
            'totalTransactionsCount',
            'overdueReturnsCount',
        ]);
});

test('approve specific item request through the api assigns stock', function () {
    $item = makeAvailableItem($this, 'Stapler', 5);

    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.approve', $request->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($request->fresh()->status)->toBe('approved')
        ->and($item->fresh()->quantity)->toBe(3);

    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'assigned',
    ]);
});

test('registered assign through the api creates an approval request', function () {
    $item = makeAvailableItem($this, 'Notebook', 10);

    $this->actingAs($this->custodian)->postJson(route('api.custodian.transactions.assign'), [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'quantity' => 3,
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 3,
        'status' => 'waiting for approval',
    ]);
});

test('manual assign through the api records a transaction', function () {
    $item = makeAvailableItem($this, 'Extension Cord', 4);

    $this->actingAs($this->custodian)->postJson(route('api.custodian.transactions.assign'), [
        'assignment_type' => 'manual',
        'item_id' => $item->item_id,
        'category_id' => $this->category->category_id,
        'unit' => 'piece',
        'quantity' => 1,
        'manual_recipient_name' => 'Visiting Lecturer',
        'manual_department' => 'Science',
        'transaction_date' => '2026-03-01',
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'manual_recipient_name' => 'Visiting Lecturer',
        'quantity' => 1,
    ]);
});

test('manual return through the api restores inventory', function () {
    $item = makeAvailableItem($this, 'Projector Screen', 2);

    $transaction = Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-03-01',
        'expected_return_date' => '2026-04-01',
        'manual_recipient_name' => 'Event Crew',
        'manual_department' => 'Admin',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transactions.manual-return', $transaction->id), ['notes' => 'Returned after event'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($transaction->fresh()->status)->not->toBe('assigned');
});

test('assigned inventory exposes return details and records a full return through the api', function () {
    $item = makeAvailableItem($this, 'Assigned Return Laptop', 1);
    $item->update([
        'quantity' => 0,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'serial_number' => 'RETURN-LAPTOP-001',
        'inventory_item_no' => 'INV-RETURN-001',
    ]);
    $transaction = Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-24',
        'building' => 'Science Building',
        'room' => '12',
        'from_building' => 'Main Building',
        'from_room' => 'Property Office',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_id', $transaction->id)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.inventory_item_no', 'INV-RETURN-001')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.serial_number', 'RETURN-LAPTOP-001')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.remaining_quantity', 1)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_date', '2026-09-24')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.building', 'Science Building')
        // A fully issued record keeps 0 in inventory.quantity; the issued units live on the
        // assignment transaction. The unit list must read assigned_quantity so it agrees
        // with the group total instead of rendering "0 piece".
        ->assertJsonPath('inventoryPages.assigned.data.0.quantity', 1)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.quantity', 0)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.assigned_quantity', 1);

    $this->postJson(route('api.custodian.inventory.receive-return'), [
        'transaction_id' => $transaction->id,
        'return_quantity' => 1,
        'notes' => 'Returned in good condition',
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonPath('auditLedger.0.display_details', 'Returned 1 piece from End User (End User) to stockroom. Received by Property Custodian (Property Custodian). Note: Returned in good condition');

    expect($transaction->fresh()->status)->toBe('returned')
        ->and($item->fresh()->status)->toBe('available')
        ->and($item->fresh()->quantity)->toBe(1);
});

test('assigned inventory exposes eligible manual issues as returnable records', function () {
    $item = makeAvailableItem($this, 'Manual Return Laptop', 1);
    $item->update(['quantity' => 0, 'status' => 'assigned']);
    $transaction = Transaction::create([
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-24',
        'expected_return_date' => '2026-10-10',
        'manual_recipient_name' => 'Visiting Lecturer',
        'manual_department' => 'Science',
        'from_building' => 'Main Building',
        'from_room' => 'Property Office',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_id', $transaction->id)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.recipient', 'Visiting Lecturer')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.is_manual_return', true)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.expected_return_date', '2026-10-10');
});

test('custodian can send selected available inventory records to maintenance together', function () {
    $this->category->update(['is_maintenance_eligible' => true]);
    $first = makeAvailableItem($this, 'Printer Group', 1);
    $second = makeAvailableItem($this, 'Printer Group', 1);
    $ineligibleCategory = Category::create([
        'category_name' => 'Ineligible Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
    $ineligible = Inventory::create([
        'category_id' => $ineligibleCategory->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Ineligible Item',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-maintenance', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'issue_description' => 'Does not power on',
            'notes' => 'Observed during inspection',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('under_maintenance')
        ->and($second->fresh()->status)->toBe('under_maintenance')
        ->and($ineligible->fresh()->status)->toBe('available')
        ->and(\App\Models\MaintenanceRecord::whereIn('inventory_id', [$first->item_id, $second->item_id])->count())->toBe(2);

    $third = makeAvailableItem($this, 'Another Printer', 1);
    $this->postJson(route('api.custodian.inventory.send-to-maintenance', $third->item_id), [
        'inventory_ids' => [$third->item_id, $ineligible->item_id],
        'issue_description' => 'Needs repair',
    ])->assertUnprocessable();

    expect($third->fresh()->status)->toBe('available');
});

test('custodian can confirm repair or ready-to-dispose actions for selected maintenance records', function () {
    $first = makeAvailableItem($this, 'Maintenance Printer', 1);
    $second = makeAvailableItem($this, 'Maintenance Printer', 1);
    $operations = app(\App\Services\InventoryOperationService::class);
    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Paper jam', null))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Paper jam', null))->toBeNull();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.mark-repaired', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'repair_notes' => 'Feed rollers replaced',
            'maintenance_cost' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('available')
        ->and($second->fresh()->status)->toBe('available')
        ->and(\App\Models\MaintenanceRecord::whereIn('inventory_id', [$first->item_id, $second->item_id])
            ->where('status', 'completed')->count())->toBe(2);

    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull();

    $this->postJson(route('api.custodian.inventory.mark-ready-to-dispose', $first->item_id), [
        'inventory_ids' => [$first->item_id, $second->item_id],
        'notes' => 'Repair is not economical',
    ])->assertOk()->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('ready_to_dispose')
        ->and($second->fresh()->status)->toBe('ready_to_dispose');

    $this->postJson(route('api.custodian.inventory.mark-repaired', $first->item_id), [
        'inventory_ids' => [$first->item_id, $second->item_id],
    ])->assertUnprocessable();

    expect($first->fresh()->status)->toBe('ready_to_dispose')
        ->and($second->fresh()->status)->toBe('ready_to_dispose');
});

test('custodian can dispose selected ready-to-dispose records together', function () {
    $first = makeAvailableItem($this, 'Old Projector', 1);
    $second = makeAvailableItem($this, 'Old Projector', 1);
    $available = makeAvailableItem($this, 'Available Projector', 1);
    $operations = app(\App\Services\InventoryOperationService::class);
    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->markReadyToDispose($first->item_id, $this->custodian->id, 'Beyond repair'))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->markReadyToDispose($second->item_id, $this->custodian->id, 'Beyond repair'))->toBeNull();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.dispose', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'notes' => 'Approved for disposal',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('disposed')
        ->and($second->fresh()->status)->toBe('disposed')
        ->and($available->fresh()->status)->toBe('available');

    $third = makeAvailableItem($this, 'Another Old Projector', 1);
    $this->postJson(route('api.custodian.inventory.dispose', $third->item_id), [
        'inventory_ids' => [$third->item_id, $available->item_id],
    ])->assertUnprocessable();

    expect($third->fresh()->status)->toBe('available')
        ->and($available->fresh()->status)->toBe('available');
});
