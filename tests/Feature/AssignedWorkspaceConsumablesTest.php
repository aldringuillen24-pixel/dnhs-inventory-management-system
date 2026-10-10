<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'custodian',
        'email' => 'custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'Mark',
        'last_name' => 'Rign',
        'username' => 'end-user',
        'email' => 'enduser@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

function makeConsumable($test, string $name, int $quantity): Inventory
{
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'unit_cost' => 10,
        'status' => 'available',
        'date_acquired' => '2026-06-01',
    ]);
}

function assignAndAccept($test, Inventory $inventory, int $quantity): void
{
    $service = app(InventoryOperationService::class);

    expect($service->submitRegisteredAssignment(
        $inventory->item_id,
        $test->custodian->id,
        $test->endUser->id,
        $quantity,
        null,
    ))->toBe('created');

    $requestId = \App\Models\AssignmentRequest::query()
        ->where('item_id', $inventory->item_id)
        ->where('target_user_id', $test->endUser->id)
        ->value('id');

    expect($service->acceptAssignment($requestId, $test->endUser->id))->toBe('accepted');
}

test('fully issued consumables appear in the assigned workspace', function () {
    $inventory = makeConsumable($this, 'Bond Paper', 30);

    assignAndAccept($this, $inventory, 30);

    expect($inventory->fresh()->status)->toBe('assigned');

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory'))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.item_name', 'Bond Paper')
        ->assertJsonPath('inventoryPages.assigned.data.0.status', 'assigned')
        ->assertJsonPath('inventoryPages.assigned.data.0.pending_hold', 0);
});

test('partially issued consumables stay available with the remainder', function () {
    $inventory = makeConsumable($this, 'Bond Paper', 50);

    assignAndAccept($this, $inventory, 30);

    expect($inventory->fresh()->status)->toBe('available')
        ->and((int) $inventory->fresh()->quantity)->toBe(20);

    $response = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory'))
        ->assertOk();

    $assignedNames = collect($response->json('inventoryPages.assigned.data'))->pluck('item_name')->all();
    expect($assignedNames)->not->toContain('Bond Paper');

    $available = collect($response->json('inventoryPages.available.data'));
    expect($available->firstWhere('item_name', 'Bond Paper')['quantity'])->toBe(20);
});
