<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
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
        'username' => 'eu-custodian',
        'email' => 'eu-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $makeEndUser = fn ($username) => User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => $username,
        'email' => "{$username}@example.com",
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = $makeEndUser('eu-end-user');
    $this->colleague = $makeEndUser('eu-colleague');

    $this->category = Category::create([
        'category_name' => 'General Supplies',
        'requires_serial_number' => false,
    ]);
});

function makeStock($test, string $name, int $quantity, string $status = 'available', array $extra = []): Inventory
{
    return Inventory::create(array_merge([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'status' => $status,
        'date_acquired' => '2026-01-10',
    ], $extra));
}

test('end user workspace reads return JSON shapes', function () {
    $this->actingAs($this->endUser)->getJson(route('api.end-user.dashboard'))
        ->assertOk()
        ->assertJsonStructure([
            'metrics' => ['assignedItems', 'pendingRequests', 'pendingReturns', 'dueSoon'],
            'categoryData',
            'statusData',
            'pendingRequestData',
            'recentActivity',
        ]);

    $this->actingAs($this->endUser)->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->assertJsonStructure([
            'myRequests',
            'incomingRequests',
            // Catalogue is category-grouped and includes out-of-stock item types
            // so they remain requestable.
            'categories' => ['*' => ['category_id', 'category_name', 'available_item_count', 'items']],
            'pendingIncomingCount',
            'myPendingCount',
        ]);

    $this->actingAs($this->endUser)->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonStructure(['activityRows', 'endUsers']);
});

test('accepting an assignment through the api assigns stock', function () {
    $item = makeStock($this, 'Stapler', 5);

    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.requests.respond', $request->id), ['action' => 'accept'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($request->fresh()->status)->not->toBe('waiting for approval');

    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'assigned',
    ]);
});

test('declining an assignment through the api marks it declined', function () {
    $item = makeStock($this, 'Tape Dispenser', 3);

    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.requests.respond', $request->id), ['action' => 'decline'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($request->fresh()->status)->toBe('declined');
});

test('transferring an assignment through the api creates a transfer request', function () {
    $item = makeStock($this, 'Scissors', 4);

    $original = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 3,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)->postJson(route('api.end-user.transfer'), [
        'request_id' => $original->id,
        'transfer_user_id' => $this->colleague->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->colleague->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'status' => 'waiting for transfer approval',
    ]);
});

test('return request and cancellation flow through the api', function () {
    $item = makeStock($this, 'Whiteboard Marker', 1, 'assigned', ['assigned_to_user_id' => $this->endUser->id]);

    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.inventory.request-return', $item->item_id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'status' => 'waiting for custodian approval',
    ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.inventory.cancel-return', $item->item_id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'status' => 'cancelled',
    ]);
});
