<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->roles = collect([
        'Administrator',
        'Property Custodian',
        'School Head',
        'Inspector',
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

    $this->admin = $makeUser('Administrator', 'spa-admin');
    $this->custodian = $makeUser('Property Custodian', 'spa-custodian');
    $this->schoolHead = $makeUser('School Head', 'spa-school-head');
    $this->inspector = $makeUser('Inspector', 'spa-inspector');
    $this->endUser = $makeUser('End User', 'spa-end-user');
});

test('guests receive a JSON 401 from the SPA api', function () {
    $this->getJson(route('api.me'))->assertUnauthorized();
});

test('api me returns the user, role, and onboarding flag without secrets', function () {
    $response = $this->actingAs($this->custodian)->getJson(route('api.me'))->assertOk();

    $response->assertJsonPath('role', 'Property Custodian')
        ->assertJsonPath('onboarding_required', false);

    expect($response->json('user'))->not->toHaveKey('password')
        ->and($response->json('user'))->not->toHaveKey('temporary_password')
        ->and($response->json('user.role.role_name'))->toBe('Property Custodian');
});

test('api me flags pending onboarding', function () {
    $this->endUser->update(['temporary_password' => 'temp-secret']);

    $this->actingAs($this->endUser)->getJson(route('api.me'))
        ->assertOk()
        ->assertJsonPath('onboarding_required', true);
});

test('role middleware returns JSON 403 outside the allowed role', function () {
    $this->actingAs($this->endUser)->getJson(route('api.custodian.dashboard'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Unauthorized. Insufficient permissions.');
});

test('custodian dashboard api returns view data as JSON', function () {
    $this->actingAs($this->custodian)->getJson(route('api.custodian.dashboard'))
        ->assertOk()
        ->assertJsonStructure([
            'title',
            'metrics' => ['inventory', 'available', 'lowStock', 'pendingRequests'],
            'categoryData',
            'requestStatusData',
            'recentActivity',
        ]);
});

test('custodian inventory api returns paginated workspace data as JSON', function () {
    $this->actingAs($this->custodian)->getJson(route('api.custodian.inventory'))
        ->assertOk()
        ->assertJsonStructure([
            'allInventoryPage' => ['data', 'current_page', 'total'],
            'inventoryMetrics' => ['total', 'available', 'assigned'],
            'activeWorkspace',
            'categories',
        ]);
});

test('stock-in through the api creates inventory and returns JSON', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    $response = $this->actingAs($this->custodian)->postJson(route('api.custodian.inventory.stock-in'), [
        'item_name' => 'Workbook',
        'category_id' => $category->category_id,
        'unit' => 'copy',
        'unit_cost' => 120,
        'date_acquired' => '2026-08-10',
        'quantity' => 25,
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('inventory', [
        'item_name' => 'Workbook',
        'quantity' => 25,
    ]);
});

test('stock-in validation failures return JSON 422', function () {
    $this->actingAs($this->custodian)->postJson(route('api.custodian.inventory.stock-in'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_name', 'category_id', 'quantity']);
});

test('end user request through the api stores a request and returns JSON', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Projector',
        'quantity' => 5,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->endUser)->postJson(route('api.end-user.requests.store'), [
        'item_name' => 'Projector',
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'quantity' => 2,
    ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'requested_item_name' => 'Projector',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
    ]);
});

test('custodian decline through the api updates the request and returns JSON', function () {
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Projector',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.decline', $request->id), ['notes' => 'Out of stock']);

    $response->assertOk()->assertJsonPath('status', 'success');

    expect($request->fresh()->status)->toBe('declined');
});

test('assignment to a non end user returns JSON 422 with field errors', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Laptop',
        'quantity' => 3,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->custodian)->postJson(route('api.custodian.transactions.assign'), [
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'quantity' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
});

test('admin users api is role scoped and paginated', function () {
    $this->actingAs($this->admin)->getJson(route('api.admin.users.index'))
        ->assertOk()
        ->assertJsonStructure([
            'users' => ['data', 'current_page'],
            'roles',
            'metrics' => ['total', 'active'],
        ]);

    $this->actingAs($this->endUser)->getJson(route('api.admin.users.index'))->assertForbidden();
});

test('school head audit logs api returns paginated JSON', function () {
    $this->actingAs($this->schoolHead)->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->assertJsonStructure(['title', 'logs' => ['data']]);
});

test('inspector inventory and reports api are read only and role scoped', function () {
    $this->actingAs($this->inspector)->getJson(route('api.inspector.inventory.overview'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics', 'categories']);

    $this->actingAs($this->inspector)->getJson(route('api.inspector.reports'))
        ->assertOk()
        ->assertJsonStructure(['title', 'metrics', 'categoryData', 'statusData', 'recentTransactions']);

    $this->actingAs($this->inspector)->getJson(route('api.school-head.inventory.overview'))
        ->assertForbidden();
    $this->actingAs($this->inspector)->postJson(route('api.custodian.transactions.assign'))
        ->assertForbidden();
});

test('end user dashboard api returns metrics as JSON', function () {
    $this->actingAs($this->endUser)->getJson(route('api.end-user.dashboard'))
        ->assertOk()
        ->assertJsonStructure([
            'title',
            'metrics' => ['assignedItems', 'pendingRequests', 'pendingReturns'],
            'categoryData',
            'recentActivity',
        ]);
});
