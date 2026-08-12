<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->propertyCustodianRole = Role::create([
        'role_name' => 'Property Custodian',
    ]);

    $this->endUserRole = Role::create([
        'role_name' => 'End User',
    ]);

    $this->propertyCustodian = User::create([
        'role_id' => $this->propertyCustodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'custodian',
        'email' => 'custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'end-user',
        'email' => 'enduser@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('end user request stores with property custodian target', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'description' => 'Portable projector',
        'quantity' => 5,
        'date_acquired' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_id' => $inventory->item_id,
        'quantity' => 2,
    ]);

    $response->assertRedirect(route('endUser.my-requests'));

    $this->assertDatabaseHas('requests', [
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
    ]);
});

test('end user sees both assigned and requested items in their activity table', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $assignedInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'description' => 'Assigned laptop',
        'quantity' => 3,
        'date_acquired' => '2026-08-10',
    ]);

    $requestedInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Monitor',
        'description' => 'Requested monitor',
        'quantity' => 4,
        'date_acquired' => '2026-08-10',
    ]);

    AssignmentRequest::create([
        'item_id' => $assignedInventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now()->subDay(),
        'responded_at' => now(),
    ]);

    AssignmentRequest::create([
        'item_id' => $requestedInventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->get(route('endUser.my-assigned-items'));

    $response->assertOk();
    $response->assertSee('Assigned');
    $response->assertSee('Requested');
    $response->assertSee('Laptop');
    $response->assertSee('Monitor');
});
