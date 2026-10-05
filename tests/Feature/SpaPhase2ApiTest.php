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
    $this->roles = collect([
        'Administrator',
        'Property Custodian',
        'School Head',
        'End User',
    ])->mapWithKeys(fn ($name) => [$name => Role::create(['role_name' => $name])]);

    $makeUser = fn ($role, $username) => User::create([
        'role_id' => $this->roles[$role]->role_id,
        'first_name' => $role,
        'last_name' => 'User',
        'username' => $username,
        'email' => "{$username}@example.com",
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->admin = $makeUser('Administrator', 'phase2-admin');
    $this->custodian = $makeUser('Property Custodian', 'phase2-custodian');
    $this->schoolHead = $makeUser('School Head', 'phase2-school-head');
    $this->endUser = $makeUser('End User', 'phase2-end-user');
});

test('admin dashboard, reports, and settings apis are role scoped', function () {
    $this->actingAs($this->admin)->getJson(route('api.admin.dashboard'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics' => ['activeUsers'], 'roleData', 'accountStatusData']);

    $this->actingAs($this->admin)->getJson(route('api.admin.reports'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics' => ['activeUsers'], 'roleData']);

    $this->actingAs($this->admin)->getJson(route('api.admin.settings'))
        ->assertOk()
        ->assertJsonStructure(['systemDiagnostics', 'inventoryPolicy', 'securityPolicy', 'aiConfig']);

    $this->actingAs($this->endUser)->getJson(route('api.admin.dashboard'))->assertForbidden();
    $this->actingAs($this->custodian)->getJson(route('api.admin.users.index'))->assertForbidden();
});

test('admin user create, update, and delete flow through the api', function () {
    $custodianRole = $this->roles['Property Custodian'];

    $this->actingAs($this->admin)->postJson(route('api.admin.users.store'), [
        'username' => 'phase2-new-user',
        'role_id' => $custodianRole->role_id,
    ])->assertOk()->assertJsonPath('status', 'success');

    $created = User::where('username', 'phase2-new-user')->firstOrFail();

    $this->actingAs($this->admin)->patchJson(route('api.admin.users.update', $created), [
        'role_id' => $this->roles['End User']->role_id,
        'status' => 'inactive',
    ])->assertOk()->assertJsonPath('status', 'success');

    expect($created->fresh()->status)->toBe('inactive');

    $this->actingAs($this->admin)->deleteJson(route('api.admin.users.destroy', $created))
        ->assertOk()->assertJsonPath('status', 'success');

    expect(User::where('username', 'phase2-new-user')->exists())->toBeFalse();
});

test('school head workspace reads return JSON shapes and stay role scoped', function () {
    $this->actingAs($this->schoolHead)->getJson(route('api.school-head.dashboard'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics' => ['totalUnits'], 'categoryData', 'recentActivity']);

    $this->actingAs($this->schoolHead)->getJson(route('api.school-head.inventory.overview'))
        ->assertOk()
        ->assertJsonStructure(['title', 'categories', 'metrics' => ['units']]);

    $this->actingAs($this->schoolHead)->getJson(route('api.school-head.reports'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics' => ['totalUnits'], 'recentTransactions']);

    $this->actingAs($this->schoolHead)->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->assertJsonStructure(['title', 'logs' => ['data']]);

    $this->actingAs($this->endUser)->getJson(route('api.school-head.dashboard'))->assertForbidden();
});

test('custodian reports and inventory edit flow through the api', function () {
    $category = Category::create(['category_name' => 'Phase2 Supplies', 'requires_serial_number' => false]);

    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Phase2 Widget',
        'quantity' => 4,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics' => ['totalUnits'], 'categoryData', 'recentTransactions']);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory.edit', $item))
        ->assertOk()
        ->assertJsonPath('inventoryItem.item_id', $item->item_id);

    $this->actingAs($this->custodian)->patchJson(route('api.custodian.inventory.update', $item), [
        'item_name' => 'Phase2 Widget',
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'unit_cost' => 55,
        'date_acquired' => '2026-08-10',
        'quantity' => 4,
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->actingAs($this->endUser)->getJson(route('api.custodian.reports'))->assertForbidden();
});

test('assigned inventory aggregates quantity and cost from active assignment transactions', function () {
    $category = Category::create(['category_name' => 'Assigned Item Test']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'assigned_to_user_id' => $this->endUser->id,
        'item_name' => 'Assigned Laptop',
        'quantity' => 0,
        'unit_cost' => 16000,
        'status' => 'assigned',
        'date_acquired' => '2026-09-24',
    ]);
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-24',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.quantity', 1)
        ->assertJsonPath('inventoryPages.assigned.data.0.unit_cost', '16000.00')
        ->assertJsonPath('inventoryPages.assigned.data.0.total_cost', 16000);
});

test('disposed inventory is excluded from all inventory but remains in disposed workspace', function () {
    $category = Category::create(['category_name' => 'Disposed Workspace Test']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Disposed Test Item',
        'inventory_item_no' => 'DISPOSED-001',
        'quantity' => 1,
        'unit_cost' => 100,
        'status' => 'disposed',
        'serial_number' => 'DISPOSED-SN-001',
        'date_acquired' => '2026-09-24',
    ]);
    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'movement_type' => 'disposed',
        'quantity' => 1,
        'quantity_before' => 1,
        'quantity_after' => 0,
        'notes' => 'Beyond economical repair',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'all']))
        ->assertOk()
        ->assertJsonPath('allInventoryPage.total', 0);

    $this->getJson(route('api.custodian.inventory', ['workspace' => 'disposed']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.disposed.data.0.item_name', 'Disposed Test Item')
        ->assertJsonPath('inventoryPages.disposed.data.0.sourceItems.0.inventory_item_no', 'DISPOSED-001')
        ->assertJsonPath('inventoryPages.disposed.data.0.sourceItems.0.serial_number', 'DISPOSED-SN-001')
        ->assertJsonPath('inventoryPages.disposed.data.0.sourceItems.0.latest_disposal_movement.notes', 'Beyond economical repair');
});

test('inventory edit api includes serial numbers for every item in a serialized group', function () {
    $category = Category::create([
        'category_name' => 'Serialized Edit Items',
        'requires_serial_number' => true,
    ]);
    $first = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Serialized Edit Item',
        'serial_number' => 'SERIAL-EDIT-001',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Serialized Edit Item',
        'serial_number' => 'SERIAL-EDIT-002',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory.edit', $first))
        ->assertOk()
        ->assertJsonPath('serialNumbers.0', 'SERIAL-EDIT-001')
        ->assertJsonPath('serialNumbers.1', 'SERIAL-EDIT-002');
});

test('serialized inventory edit updates each serial number in the group', function () {
    $category = Category::create([
        'category_name' => 'Serialized Update Items',
        'requires_serial_number' => true,
    ]);
    $first = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Serialized Update Item',
        'serial_number' => 'SERIAL-OLD-001',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $second = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Serialized Update Item',
        'serial_number' => 'SERIAL-OLD-002',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->custodian)
        ->patchJson(route('api.custodian.inventory.update', $first), [
            'item_name' => 'Serialized Update Item',
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'quantity' => 2,
            'unit_cost' => 50,
            'date_acquired' => '2026-08-10',
            'serial_numbers' => ['SERIAL-CORRECTED-001', 'SERIAL-CORRECTED-002'],
        ]);

    expect($first->fresh()->serial_number)->toBe('SERIAL-CORRECTED-001')
        ->and($second->fresh()->serial_number)->toBe('SERIAL-CORRECTED-002');
});

test('serialized inventory edit rejects a serial number used by another item', function () {
    $category = Category::create([
        'category_name' => 'Serialized Unique Edit Items',
        'requires_serial_number' => true,
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Serialized Unique Edit Item',
        'serial_number' => 'SERIAL-UNIQUE-001',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Different Serialized Item',
        'serial_number' => 'SERIAL-TAKEN-001',
        'quantity' => 1,
        'unit_cost' => 50,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->custodian)
        ->patchJson(route('api.custodian.inventory.update', $item), [
            'item_name' => 'Serialized Unique Edit Item',
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'quantity' => 1,
            'unit_cost' => 50,
            'date_acquired' => '2026-08-10',
            'serial_numbers' => ['SERIAL-TAKEN-001'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serial_numbers.0']);

    expect($item->fresh()->serial_number)->toBe('SERIAL-UNIQUE-001');
});

test('profile update through the api respects role validation', function () {
    $this->actingAs($this->custodian)->patchJson(route('api.custodian.profile.update'), [
        'first_name' => 'Updated',
        'username' => 'phase2-custodian',
        'email' => 'phase2-custodian@example.com',
    ])->assertOk()->assertJsonPath('status', 'success');

    expect($this->custodian->fresh()->first_name)->toBe('Updated');

    $this->actingAs($this->endUser)->patchJson(route('api.end-user.profile.update'), [
        'username' => 'phase2-end-user',
        'email' => 'not-an-email',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

test('ai assistant endpoints require auth and stay role scoped', function () {
    $this->postJson('/api/ai/chat', ['message' => 'How many items are available?'])->assertUnauthorized();

    $this->actingAs($this->custodian)->postJson('/api/ai/chat', ['message' => 'How many items are available?'])
        ->assertOk()
        ->assertJsonStructure(['success', 'reply', 'role']);

    // Only Property Custodian holds assistant capabilities; other roles are denied.
    $this->actingAs($this->endUser)->postJson('/api/ai/chat', ['message' => 'How many items are available?'])
        ->assertForbidden();
});
