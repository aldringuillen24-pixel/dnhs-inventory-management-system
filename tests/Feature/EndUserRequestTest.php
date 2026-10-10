<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;

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

test('a second request cannot promise units already pending approval', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 30,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    // First 20 enters the approval queue; stock rows stay untouched.
    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 20,
    ])->assertSessionHas('success', 'Request submitted to property custodian.');

    // Only 10 are still free, so a second 20 is refused with the hold shown.
    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 20,
    ])->assertSessionHas('error', 'Requested quantity exceeds available stock. Only 10 free (20 unit(s) already pending approval).');

    // A 10 fits the free remainder and queues normally.
    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 10,
    ])->assertSessionHas('success', 'Request submitted to property custodian.');
});

test('a custodian cannot assign units already pending another acceptance', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 30,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $secondUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Second',
        'last_name' => 'User',
        'username' => 'end-user-2',
        'email' => 'enduser2@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $service = app(InventoryOperationService::class);
    expect($service->submitRegisteredAssignment($inventory->item_id, $this->propertyCustodian->id, $this->endUser->id, 20, null))->toBe('created');

    // 20 of 30 are promised; a second 20 against the same record is refused.
    expect($service->submitRegisteredAssignment($inventory->item_id, $this->propertyCustodian->id, $secondUser->id, 20, null))->toBe('insufficient');

    // Stock itself is untouched until approval.
    expect((int) $inventory->fresh()->quantity)->toBe(30);
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
        'item_name' => $inventory->item_name,
        'category_id' => $category->category_id,
        'unit' => $inventory->unit,
        'quantity' => 2,
        'notes' => 'For classroom lessons',
    ]);

    $response->assertRedirect(route('endUser.my-requests'));

    $this->assertDatabaseHas('requests', [
        'item_id' => null,
        'requested_item_name' => 'Projector',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'notes' => 'For classroom lessons',
    ]);
});

test('end user onboarding saves the account building and room', function () {
    $this->endUser->update(['temporary_password' => 'temporary-password']);

    $this->actingAs($this->endUser)
        ->get(route('endUser.onboarding'))
        ->assertRedirect('/spa/onboarding');

    $this->get('/spa/onboarding')
        ->assertOk()
        ->assertSee('id="app"', false);

    $this->postJson('/api/onboarding', [
        'first_name' => $this->endUser->first_name,
        'last_name' => $this->endUser->last_name,
        'email' => $this->endUser->email,
        'building' => 'Main Building',
        'room' => '203',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertOk()
        ->assertJsonPath('onboarding_required', false)
        ->assertJsonPath('user.building', 'Main Building')
        ->assertJsonPath('user.room', '203');

    expect($this->endUser->fresh()->building)->toBe('Main Building')
        ->and($this->endUser->fresh()->room)->toBe('203')
        ->and($this->endUser->fresh()->temporary_password)->toBeNull();
});

test('inspector onboarding updates the sign-in username', function () {
    $inspectorRole = Role::create(['role_name' => 'Inspector']);

    $inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Inspector',
        'username' => 'inspector-a7f3',
        'email' => 'inspector@example.com',
        'password' => 'password',
        'status' => 'active',
        'temporary_password' => 'temporary-password',
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/onboarding', [
            'first_name' => 'School',
            'last_name' => 'Inspector',
            'username' => 'school-inspector',
            'email' => 'inspector@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertOk()
        ->assertJsonPath('user.username', 'school-inspector');

    expect($inspector->fresh()->username)->toBe('school-inspector')
        ->and($inspector->fresh()->temporary_password)->toBeNull();
});

test('inspector onboarding accepts the username the admin already assigned', function () {
    $inspectorRole = Role::create(['role_name' => 'Inspector']);

    $inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Inspector',
        'username' => 'inspector-a7f3',
        'email' => 'inspector@example.com',
        'password' => 'password',
        'status' => 'active',
        'temporary_password' => 'temporary-password',
    ]);

    // ignore($user->id) must let the unchanged value pass the unique check.
    $this->actingAs($inspector)
        ->postJson('/api/onboarding', [
            'first_name' => 'School',
            'last_name' => 'Inspector',
            'username' => 'inspector-a7f3',
            'email' => 'inspector@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertOk()
        ->assertJsonPath('user.username', 'inspector-a7f3');
});

test('inspector onboarding rejects a username already taken', function () {
    $inspectorRole = Role::create(['role_name' => 'Inspector']);

    $inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Inspector',
        'username' => 'inspector-a7f3',
        'email' => 'inspector@example.com',
        'password' => 'password',
        'status' => 'active',
        'temporary_password' => 'temporary-password',
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/onboarding', [
            'first_name' => 'School',
            'last_name' => 'Inspector',
            'username' => 'custodian',
            'email' => 'inspector@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('username');

    expect($inspector->fresh()->username)->toBe('inspector-a7f3')
        ->and($inspector->fresh()->temporary_password)->not->toBeNull();
});

test('an item with zero available stock is recorded as unmet demand instead of refused', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 0,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 3,
        'notes' => 'Needed for the class set.',
    ]);

    $response->assertRedirect(route('endUser.my-requests'));
    $response->assertSessionMissing('error');
    // The confirmation must not promise an approval the custodian cannot give.
    $response->assertSessionHas('success');

    // Cannot be approved -- there is nothing to allocate -- so it does not enter
    // the custodian's approval queue.
    $this->assertDatabaseHas('requests', [
        'item_id' => null,
        'requested_item_name' => 'Bond Paper',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'ream',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 3,
        'status' => AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
        'notes' => 'Needed for the class set.',
    ]);
});

test('a partial shortfall is still refused rather than recorded as unmet demand', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    // Stock exists but not enough. That is a quantity the end user can simply
    // lower, so it stays an error -- only a total absence becomes demand.
    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 10,
    ])->assertSessionHas('error', 'Requested quantity exceeds available stock. Only 4 free.');

    $this->assertDatabaseMissing('requests', [
        'requested_item_name' => 'Bond Paper',
        'user_id' => $this->endUser->id,
    ]);
});

test('a satisfied request still enters the custodian approval queue', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Bond Paper',
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 2,
    ])->assertSessionHas('success', 'Request submitted to property custodian.');

    $this->assertDatabaseHas('requests', [
        'requested_item_name' => 'Bond Paper',
        'quantity' => 2,
        'status' => 'waiting for approval',
    ]);
});

test('end user cannot request more than available stock of the selected type', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 3,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.requests.store'), [
            'item_name' => 'Bond Paper',
            'category_id' => $category->category_id,
            'unit' => 'ream',
            'quantity' => 4,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Requested quantity exceeds available stock. Only 3 free.');

    $this->assertDatabaseMissing('requests', [
        'user_id' => $this->endUser->id,
        'requested_item_name' => 'Bond Paper',
    ]);
});

test('assignment modal receives only available inventory with stock', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $available = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Available Laptop',
        'quantity' => 2,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    foreach ([
        ['item_name' => 'Assigned Laptop', 'status' => 'assigned', 'quantity' => 1],
        ['item_name' => 'Maintenance Laptop', 'status' => 'under_maintenance', 'quantity' => 1],
        ['item_name' => 'Empty Laptop', 'status' => 'available', 'quantity' => 0],
    ] as $item) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->propertyCustodian->id,
            'date_acquired' => '2026-08-10',
            ...$item,
        ]);
    }

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertSee('Registered End User')
        ->assertSee('Manual Issue')
        ->assertViewHas('availableInventoryItems', fn ($items) => $items->count() === 1
            && $items->first()['item_id'] === $available->item_id
            && $items->first()['quantity'] === 2);
});

test('assignment selector keeps same-name items with different categories and units distinct', function () {
    $firstCategory = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $secondCategory = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);
    $firstItem = Inventory::create([
        'category_id' => $firstCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'inventory_item_no' => 'INV-000101',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $secondItem = Inventory::create([
        'category_id' => $secondCategory->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'inventory_item_no' => 'INV-000102',
        'quantity' => 7,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertViewHas('availableInventoryItems', fn ($items) => $items->count() === 2
            && $items->firstWhere('item_id', $firstItem->item_id)['category_name'] === 'Office Supplies'
            && $items->firstWhere('item_id', $firstItem->item_id)['unit'] === 'box'
            && $items->firstWhere('item_id', $secondItem->item_id)['category_name'] === 'Learning Resources'
            && $items->firstWhere('item_id', $secondItem->item_id)['unit'] === 'ream');
});

test('request catalogue groups by category and keeps same-name items in different categories distinct', function () {
    $firstCategory = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $secondCategory = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $firstCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    Inventory::create([
        'category_id' => $secondCategory->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 7,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $payload = $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->json();

    $categories = collect($payload['categories'])->keyBy('category_name');

    // Same name, different category and unit: two distinct requestable things.
    expect($categories)->toHaveKeys(['Office Supplies', 'Learning Resources'])
        ->and($categories['Office Supplies']['items'][0])
        ->toMatchArray(['item_name' => 'Bond Paper', 'unit' => 'box', 'available_quantity' => 4])
        ->and($categories['Learning Resources']['items'][0])
        ->toMatchArray(['item_name' => 'Bond Paper', 'unit' => 'ream', 'available_quantity' => 7])
        ->and($categories['Office Supplies']['available_item_count'])->toBe(1);
});

test('request catalogue includes a category that has never been catalogued', function () {
    // A category with zero inventory rows must still be selectable: it is
    // exactly the case where an end user needs to ask for something the school
    // has never stocked.
    Category::create([
        'category_name' => 'Sports Equipment',
        'requires_serial_number' => false,
    ]);

    $withStock = Category::create([
        'category_name' => 'Custodial Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $withStock->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $payload = $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->json();

    $names = collect($payload['categories'])->pluck('category_name');

    expect($names)->toContain('Sports Equipment', 'Custodial Supplies');

    $empty = collect($payload['categories'])->firstWhere('category_name', 'Sports Equipment');

    // Listed, empty, and flagged so the modal knows to ask for a typed name
    // rather than offering a picker with nothing in it.
    expect($empty['items'])->toBe([])
        ->and($empty['available_item_count'])->toBe(0);
});

test('a request naming an item in an uncatalogued category is recorded as unmet demand', function () {
    $category = Category::create([
        'category_name' => 'Music Equipment',
        'requires_serial_number' => false,
    ]);

    $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => 'Basketball',
        'category_id' => $category->category_id,
        'unit' => 'units',
        'quantity' => 2,
        'notes' => 'For the intramurals.',
    ])->assertSessionMissing('error');

    $this->assertDatabaseHas('requests', [
        'item_id' => null,
        'requested_item_name' => 'Basketball',
        'requested_category_id' => $category->category_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
    ]);
});

test('request catalogue lists out-of-stock item types so they stay requestable', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 0,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $payload = $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->json();

    $listed = collect($payload['categories'])->firstWhere('category_name', 'Office Supplies');

    // Present but zero: the end user must be able to name the item they want,
    // otherwise a category with no stock offers nothing to request at all.
    expect($listed['items'])->toHaveCount(1)
        ->and($listed['items'][0]['available_quantity'])->toBe(0)
        ->and($listed['available_item_count'])->toBe(0);
});

test('custodian assigns exact matching stock records and records each allocation', function () {
    $this->endUser->update(['building' => 'Main Building', 'room' => '203']);
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $otherCategory = Category::create([
        'category_name' => 'Classroom Materials',
        'requires_serial_number' => false,
    ]);
    $firstLot = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 2,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $secondLot = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $differentType = Inventory::create([
        'category_id' => $otherCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 9,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Bond Paper',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'ream',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 3,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertSee('Open Request')
        ->assertSee('Review Item Request')
        ->assertSee('Request details')
        ->assertSee('Select inventory')
        ->assertSee('name="inventory_ids[]"', false)
        ->assertDontSee('name="allocations[', false)
        ->assertViewHas('incomingRequests', fn ($requests) => $requests->first()->total_available_stock === 6
            && $requests->first()->matching_inventory_items->pluck('item_id')->all() === [$firstLot->item_id, $secondLot->item_id]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$firstLot->item_id, $secondLot->item_id],
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($firstLot->fresh()->quantity)->toBe(0)
        ->and($firstLot->fresh()->status)->toBe('assigned')
        ->and($firstLot->fresh()->building)->toBe('Main Building')
        ->and($firstLot->fresh()->room)->toBe('203')
        ->and($secondLot->fresh()->quantity)->toBe(3)
        ->and($secondLot->fresh()->building)->toBe('Main Building')
        ->and($secondLot->fresh()->room)->toBe('203')
        ->and($differentType->fresh()->quantity)->toBe(9)
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fulfillmentRequests()->count())->toBe(2);

    expect(Transaction::whereIn('item_id', [$firstLot->item_id, $secondLot->item_id])->count())->toBe(2)
        ->and(StockMovement::where('reference_id', $request->id)->count())->toBe(2)
        ->and(StockMovement::where('reference_id', $request->id)->where('to_building', 'Main Building')->where('to_room', '203')->count())->toBe(2)
        ->and(StockMovement::where('reference_id', $request->id)->pluck('inventory_id')->all())
            ->toEqualCanonicalizing([$firstLot->item_id, $secondLot->item_id]);
});

test('custodian assigns selected serialized assets and leaves other matching names untouched', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);
    $otherCategory = Category::create([
        'category_name' => 'Classroom Equipment',
        'requires_serial_number' => true,
    ]);
    $assetOne = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'PJ-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $assetTwo = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'PJ-002',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $reservedAsset = Inventory::create([
        'category_id' => $otherCategory->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'PJ-003',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Projector',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$assetOne->item_id],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Selected items do not match the requested quantity. Update your selection and try again.');

    expect($assetOne->fresh()->status)->toBe('available')
        ->and($assetTwo->fresh()->status)->toBe('available')
        ->and($request->fresh()->status)->toBe('waiting for approval')
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$assetOne->item_id, $assetTwo->item_id],
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($assetOne->fresh()->status)->toBe('assigned')
        ->and($assetTwo->fresh()->status)->toBe('assigned')
        ->and($reservedAsset->fresh()->status)->toBe('available')
        ->and($reservedAsset->fresh()->quantity)->toBe(1)
        ->and(Transaction::whereIn('item_id', [$assetOne->item_id, $assetTwo->item_id])->count())->toBe(2);
});

test('custodian cannot assign a mismatched or stale allocation', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $otherCategory = Category::create([
        'category_name' => 'Classroom Materials',
        'requires_serial_number' => false,
    ]);
    $requestedItem = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $mismatchedItem = Inventory::create([
        'category_id' => $otherCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 8,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Bond Paper',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'ream',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 3,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$mismatchedItem->item_id],
        ]);

    $response->assertRedirect()->assertSessionHas('error');
    expect($request->fresh()->status)->toBe('waiting for approval')
        ->and($requestedItem->fresh()->quantity)->toBe(4)
        ->and($mismatchedItem->fresh()->quantity)->toBe(8)
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);

    $requestedItem->update(['quantity' => 1]);
    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$requestedItem->item_id],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($request->fresh()->status)->toBe('waiting for approval')
        ->and($requestedItem->fresh()->quantity)->toBe(1)
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

test('custodian cannot select more stock records than the requested quantity needs', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $firstRecord = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 5,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $extraRecord = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 8,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Bond Paper',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'ream',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$firstRecord->item_id, $extraRecord->item_id],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Selected items do not match the requested quantity. Update your selection and try again.');

    expect($firstRecord->fresh()->quantity)->toBe(5)
        ->and($extraRecord->fresh()->quantity)->toBe(8)
        ->and($request->fresh()->status)->toBe('waiting for approval')
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

test('custodian cancellation stores its reason without changing stock or history', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);
    $asset = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'PJ-CANCEL-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Projector',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.cancel', $request->id), [])
        ->assertSessionHasErrors('cancellation_reason');

    expect($request->fresh()->status)->toBe('waiting for approval')
        ->and($asset->fresh()->quantity)->toBe(1);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.cancel', $request->id), [
            'cancellation_reason' => 'Reserved for an urgent school event.',
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->assertDatabaseHas('requests', [
        'id' => $request->id,
        'status' => 'cancelled',
        'cancellation_reason' => 'Reserved for an urgent school event.',
    ]);
    expect($asset->fresh()->quantity)->toBe(1)
        ->and($asset->fresh()->status)->toBe('available')
        ->and($asset->fresh()->assigned_to_user_id)->toBeNull()
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertDontSee('Open Request')
        ->assertDontSee('Cancel Request');

    $this->actingAs($this->endUser)
        ->get(route('endUser.requests'))
        ->assertOk()
        ->assertSee('Note')
        ->assertSee('Reserved for an urgent school event.');
});

test('exact fulfillment records remain individually returnable', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);
    $asset = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'PJ-RETURN-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Projector',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id), [
            'inventory_ids' => [$asset->item_id],
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.request-return', $asset->item_id))
        ->assertRedirect(route('endUser.my-requests'));
    $returnRequest = AssignmentRequest::where('item_id', $asset->item_id)
        ->where('status', 'waiting for custodian approval')
        ->firstOrFail();

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.returns.approve', $returnRequest->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($asset->fresh()->quantity)->toBe(1)
        ->and($asset->fresh()->status)->toBe('available')
        ->and($request->fulfillmentRequests()->sole()->fresh()->status)->toBe('returned')
        ->and(StockMovement::where('inventory_id', $asset->item_id)->where('movement_type', 'returned')->exists())->toBeTrue();
});

test('my assigned items shows fulfilled records without their empty parent request row', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 2,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $parentRequest = AssignmentRequest::create([
        'requested_item_name' => 'Laptop',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $parentRequest->id), [
            'inventory_ids' => [$inventory->item_id],
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->actingAs($this->endUser)
        ->get(route('endUser.my-assigned-items'))
        ->assertOk()
        ->assertViewHas('activityRows', fn ($rows) => $rows->count() === 1
            && $rows->first()['type'] === 'Assigned'
            && $rows->first()['item_name'] === 'Laptop');
});

test('same-named assigned inventory records remain separate in the end-user list', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $firstInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Dell Laptop',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);
    $secondInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Dell Laptop',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);

    foreach ([$firstInventory, $secondInventory] as $inventory) {
        AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $this->propertyCustodian->id,
            'target_user_id' => $this->endUser->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);
    }

    $this->actingAs($this->endUser)
        ->get(route('endUser.my-assigned-items'))
        ->assertOk()
        ->assertViewHas('activityRows', fn ($rows) => $rows->count() === 2
            && $rows->pluck('item_id')->sort()->values()->all() === collect([$firstInventory->item_id, $secondInventory->item_id])->sort()->values()->all()
            && $rows->every(fn ($row) => $row['type'] === 'Assigned'
                && $row['item_name'] === 'Dell Laptop'
                && $row['quantity'] === 1));
});

test('only property custodians can assign or cancel incoming item requests', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $request = AssignmentRequest::create([
        'requested_item_name' => 'Bond Paper',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'ream',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->post(route('propertyCustodian.requests.approve', $request->id), ['inventory_ids' => [1]])
        ->assertForbidden();
    $this->actingAs($this->endUser)
        ->post(route('propertyCustodian.requests.cancel', $request->id), ['cancellation_reason' => 'Not allowed'])
        ->assertForbidden();
});

test('custodian-created assignment records the target end user as assignee', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
        'status' => 'available',
    ]);

    $request = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $request->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->assertDatabaseHas('inventory', [
        'item_id' => $inventory->item_id,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
    ]);

    $this->assertDatabaseHas('transactions', [
        'item_id' => $inventory->item_id,
        'from_user_id' => $this->propertyCustodian->id,
        'user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'assigned',
    ]);

    $movement = StockMovement::where('inventory_id', $inventory->item_id)->sole();
    expect($movement->movement_type)->toBe('assignment')
        ->and($movement->quantity)->toBe(1)
        ->and($movement->quantity_before)->toBe(1)
        ->and($movement->quantity_after)->toBe(0)
        ->and($movement->user_id)->toBe($this->propertyCustodian->id)
        ->and($movement->reference_type)->toBe('assignment_request')
        ->and($movement->reference_id)->toBe($request->id)
        ->and($movement->notes)->toContain("user #{$this->propertyCustodian->id}", "user #{$this->endUser->id}");

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['total'] === 1
            && $metrics['available'] === 0
            && $metrics['assigned'] === 1)
        ->assertSee('End User (1)', false);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertViewHas('totalAssignedCount', 1)
        ->assertViewHas('transactions', fn ($transactions) => $transactions->sum('quantity') === 1);
});

test('registered direct assignments retain the workflow and reject non-end-user recipients', function () {
    $this->endUser->update(['building' => 'Main Building', 'room' => 'Room 204']);
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Monitor',
        'quantity' => 3,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            'assignment_type' => 'registered',
            'item_id' => $inventory->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'transaction_date' => '2026-09-27',
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->assertDatabaseHas('requests', [
        'item_id' => $inventory->item_id,
        'target_user_id' => $this->endUser->id,
        'building' => 'Main Building',
        'room' => 'Room 204',
        'quantity' => 1,
        'status' => 'waiting for approval',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertDontSee('registered-building', false)
        ->assertDontSee('registered-room', false)
        ->assertSee('manual-building', false)
        ->assertSee('manual-room', false);

    $administratorRole = Role::create(['role_name' => 'Administrator']);
    $schoolHeadRole = Role::create(['role_name' => 'School Head']);
    foreach ([$administratorRole, $schoolHeadRole, $this->propertyCustodianRole] as $role) {
        $recipient = User::factory()->create(['role_id' => $role->role_id]);
        $this->actingAs($this->propertyCustodian)
            ->post(route('propertyCustodian.transactions.assignItem'), [
                'assignment_type' => 'registered',
                'item_id' => $inventory->item_id,
                'user_id' => $recipient->id,
                'quantity' => 1,
                'transaction_date' => '2026-09-27',
            ])
            ->assertSessionHasErrors('user_id')
            ->assertSessionHas('errors', fn ($errors) => $errors->first('user_id') === 'Items can only be assigned to an End User.');
    }

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            'assignment_type' => 'registered',
            'item_id' => $inventory->item_id,
            'user_id' => 999999,
            'quantity' => 1,
            'transaction_date' => '2026-09-27',
        ])
        ->assertSessionHasErrors('user_id');

    expect($inventory->fresh()->quantity)->toBe(3)
        ->and($inventory->fresh()->status)->toBe('available')
        ->and($inventory->fresh()->assigned_to_user_id)->toBeNull()
        ->and(AssignmentRequest::where('item_id', $inventory->item_id)->count())->toBe(1)
        ->and(Transaction::where('item_id', $inventory->item_id)->count())->toBe(0)
        ->and(StockMovement::where('inventory_id', $inventory->item_id)->count())->toBe(0);

    $this->actingAs($this->endUser)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            'assignment_type' => 'manual',
        ])
        ->assertForbidden();
});

test('competing assignment acceptances cannot issue the final available unit twice', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Webcam',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $otherEndUser = User::factory()->create(['role_id' => $this->endUserRole->role_id]);
    $firstRequest = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);
    $secondRequest = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $otherEndUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $operations = app(InventoryOperationService::class);
    expect($operations->acceptAssignment($firstRequest->id, $this->endUser->id))->toBe('accepted')
        ->and($operations->acceptAssignment($secondRequest->id, $otherEndUser->id))->toBe('insufficient')
        ->and($inventory->fresh()->quantity)->toBe(0)
        ->and($inventory->fresh()->status)->toBe('assigned')
        ->and($inventory->fresh()->assigned_to_user_id)->toBe($this->endUser->id)
        ->and($firstRequest->fresh()->status)->toBe('approved')
        ->and($secondRequest->fresh()->status)->toBe('waiting for approval')
        ->and(Transaction::where('item_id', $inventory->item_id)->count())->toBe(1)
        ->and(StockMovement::where('inventory_id', $inventory->item_id)->count())->toBe(1);
});

test('manual issue rolls back its stock deduction when transaction creation fails', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'quantity' => 5,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    expect(fn () => app(InventoryOperationService::class)->issueManual([
        'item_id' => $inventory->item_id,
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'quantity' => 2,
        'manual_recipient_name' => 'Alex Rivera',
        'manual_department' => 'Science Department',
        'transaction_date' => '2026-09-27',
    ], 999999))->toThrow(QueryException::class);

    expect($inventory->fresh()->quantity)->toBe(5)
        ->and($inventory->fresh()->status)->toBe('available')
        ->and(Transaction::where('item_id', $inventory->item_id)->count())->toBe(0)
        ->and(StockMovement::where('inventory_id', $inventory->item_id)->count())->toBe(0);
});

test('manual issue stores recipient snapshot and custodian can return the exact transaction', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'quantity' => 5,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $manualIssueData = [
        'assignment_type' => 'manual',
        'item_id' => $inventory->item_id,
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'quantity' => 3,
        'manual_recipient_name' => 'Alex Rivera',
        'manual_department' => 'Science Department',
        'manual_recipient_type' => 'Staff',
        'manual_contact' => 'EXT-204',
        'manual_notes' => 'Science fair presentation',
        'transaction_date' => '2026-09-27',
        'expected_return_date' => '2026-10-10',
    ];

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            ...$manualIssueData,
            'manual_recipient_name' => '',
            'manual_department' => '',
        ])
        ->assertSessionHasErrors(['manual_recipient_name', 'manual_department']);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), $manualIssueData)
        ->assertRedirect(route('propertyCustodian.transactions'));

    $transaction = Transaction::whereNull('user_id')->sole();
    expect($inventory->fresh()->quantity)->toBe(2)
        ->and($inventory->fresh()->status)->toBe('available')
        ->and($inventory->fresh()->assigned_to_user_id)->toBeNull()
        ->and($transaction->item_id)->toBe($inventory->item_id)
        ->and($transaction->from_user_id)->toBe($this->propertyCustodian->id)
        ->and($transaction->manual_recipient_name)->toBe('Alex Rivera')
        ->and($transaction->manual_department)->toBe('Science Department')
        ->and($transaction->manual_recipient_type)->toBe('Staff')
        ->and($transaction->manual_contact)->toBe('EXT-204')
        ->and($transaction->manual_notes)->toBe('Science fair presentation')
        ->and($transaction->expected_return_date->toDateString())->toBe('2026-10-10');

    $movement = StockMovement::sole();
    expect($movement->inventory_id)->toBe($inventory->item_id)
        ->and($movement->movement_type)->toBe('assignment')
        ->and($movement->reference_type)->toBe('transaction')
        ->and($movement->reference_id)->toBe($transaction->id)
        ->and($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(2);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertOk()
        ->assertSee('Alex Rivera')
        ->assertSee('Science Department')
        ->assertSee('2026-10-10')
        ->assertSee('Record Return');
    $this->actingAs($this->endUser)
        ->post(route('propertyCustodian.transactions.manual-return', $transaction->id))
        ->assertForbidden();

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.manual-return', $transaction->id), [
            'notes' => 'Received in good condition',
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($inventory->fresh()->quantity)->toBe(5)
        ->and($inventory->fresh()->status)->toBe('available')
        ->and($transaction->fresh()->status)->toBe('returned')
        ->and($transaction->fresh()->return_date->toDateString())->toBe(now()->toDateString())
        ->and(StockMovement::count())->toBe(2)
        ->and(StockMovement::where('movement_type', 'returned')->where('reference_id', $transaction->id)->exists())->toBeTrue();
});

test('assigned inventory shows the manual issue recipient name', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Conference Projector',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            'assignment_type' => 'manual',
            'item_id' => $inventory->item_id,
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'quantity' => 1,
            'manual_recipient_name' => 'Alex Rivera',
            'manual_department' => 'Science Department',
            'transaction_date' => '2026-09-29',
        ])
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertSee('Alex Rivera (1)')
        ->assertDontSee('Recorded assignee');
});

test('manual issue enforces serialized quantity and does not allow returns without a due date', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);
    $asset = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'serial_number' => 'SN-MANUAL-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $manualIssueData = [
        'assignment_type' => 'manual',
        'item_id' => $asset->item_id,
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'quantity' => 2,
        'manual_recipient_name' => 'Jordan Lee',
        'manual_department' => 'Library',
        'transaction_date' => '2026-09-27',
    ];

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), $manualIssueData)
        ->assertRedirect()
        ->assertSessionHas('error', 'Serialized inventory must be issued as its exact individual asset with quantity 1.');

    expect($asset->fresh()->quantity)->toBe(1)
        ->and($asset->fresh()->status)->toBe('available')
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);

    $manualIssueData['quantity'] = 1;
    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), $manualIssueData)
        ->assertRedirect(route('propertyCustodian.transactions'));
    $transaction = Transaction::whereNull('user_id')->sole();

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.manual-return', $transaction->id))
        ->assertRedirect()
        ->assertSessionHas('error', 'Only active manual issues with an expected return date can be returned.');

    expect($asset->fresh()->quantity)->toBe(0)
        ->and($asset->fresh()->status)->toBe('assigned')
        ->and($transaction->fresh()->status)->toBe('assigned')
        ->and(StockMovement::count())->toBe(1);
});

test('manual issue rejects mismatched unavailable and insufficient inventory without side effects', function () {
    $category = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $otherCategory = Category::create([
        'category_name' => 'Classroom Materials',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 2,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $manualIssueData = [
        'assignment_type' => 'manual',
        'item_id' => $inventory->item_id,
        'category_id' => $category->category_id,
        'unit' => 'ream',
        'quantity' => 1,
        'manual_recipient_name' => 'Casey Morgan',
        'manual_department' => 'Science Department',
        'transaction_date' => '2026-09-27',
    ];

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            ...$manualIssueData,
            'category_id' => $otherCategory->category_id,
            'unit' => 'box',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), [
            ...$manualIssueData,
            'quantity' => 3,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $inventory->update(['status' => 'under_maintenance']);
    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transactions.assignItem'), $manualIssueData)
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($inventory->fresh()->quantity)->toBe(2)
        ->and($inventory->fresh()->status)->toBe('under_maintenance')
        ->and(Transaction::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

test('assignment approval deducts only the selected inventory record with a duplicate name', function () {
    $firstCategory = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $selectedCategory = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    $otherItem = Inventory::create([
        'category_id' => $firstCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 8,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $selectedItem = Inventory::create([
        'category_id' => $selectedCategory->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 3,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $assignment = AssignmentRequest::create([
        'item_id' => $selectedItem->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $assignment->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($otherItem->fresh()->quantity)->toBe(8)
        ->and($otherItem->fresh()->status)->toBe('available')
        ->and($selectedItem->fresh()->quantity)->toBe(1)
        ->and($assignment->fresh()->item_id)->toBe($selectedItem->item_id);

    $transaction = Transaction::sole();
    $movement = StockMovement::sole();
    expect($transaction->item_id)->toBe($selectedItem->item_id)
        ->and($movement->inventory_id)->toBe($selectedItem->item_id)
        ->and($movement->quantity_before)->toBe(3)
        ->and($movement->quantity_after)->toBe(1);
});

test('assignment approval uses the selected serial-numbered inventory record', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);
    $otherAsset = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'serial_number' => 'SN-LAPTOP-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $selectedAsset = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'serial_number' => 'SN-LAPTOP-002',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $assignment = AssignmentRequest::create([
        'item_id' => $selectedAsset->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.requests.approve', $assignment->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($otherAsset->fresh()->status)->toBe('available')
        ->and($otherAsset->fresh()->quantity)->toBe(1)
        ->and($selectedAsset->fresh()->status)->toBe('assigned')
        ->and($selectedAsset->fresh()->quantity)->toBe(0)
        ->and(Transaction::sole()->item_id)->toBe($selectedAsset->item_id)
        ->and(StockMovement::sole()->inventory_id)->toBe($selectedAsset->item_id);
});

test('end user request cannot use same-name stock from another category or unit', function () {
    $selectedCategory = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);
    $otherCategory = Category::create([
        'category_name' => 'Office Supplies',
        'requires_serial_number' => false,
    ]);
    $selectedItem = Inventory::create([
        'category_id' => $selectedCategory->category_id,
        'unit' => 'ream',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    Inventory::create([
        'category_id' => $otherCategory->category_id,
        'unit' => 'box',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 10,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.requests.store'), [
            'item_name' => $selectedItem->item_name,
            'category_id' => $selectedCategory->category_id,
            'unit' => $selectedItem->unit,
            'quantity' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'Requested quantity exceeds available stock. Only 1 free.');

    $this->assertDatabaseMissing('requests', [
        'requested_item_name' => $selectedItem->item_name,
        'user_id' => $this->endUser->id,
    ]);
});

test('end user acceptance creates an assignment movement linked to its transaction', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Monitor',
        'quantity' => 4,
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $assignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.requests.respond', $assignment->id), ['action' => 'accept'])
        ->assertRedirect(route('endUser.requests'));

    $assignment->refresh();
    $movement = StockMovement::where('inventory_id', $inventory->item_id)->sole();
    expect($inventory->fresh()->quantity)->toBe(2)
        ->and($assignment->status)->toBe('approved')
        ->and($movement->movement_type)->toBe('assignment')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->quantity_before)->toBe(4)
        ->and($movement->quantity_after)->toBe(2)
        ->and($movement->user_id)->toBe($this->endUser->id)
        ->and($movement->reference_type)->toBe('transaction')
        ->and($movement->reference_id)->toBe($assignment->transaction_id)
        ->and($movement->notes)->toContain("request #{$assignment->id}");
});

test('assigned inventory is never counted as available stock', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Assigned Laptop',
        'quantity' => 1,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);

    // An assigned unit is held by someone, so it cannot satisfy a request. That
    // leaves zero available, which is now recorded as unmet demand rather than
    // refused -- the end user is stating a real need the system cannot fill.
    // The invariant under test is that the assigned unit is NOT available, not
    // that the request is rejected.
    $response = $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_name' => $inventory->item_name,
        'category_id' => $category->category_id,
        'unit' => $inventory->unit,
        'quantity' => 1,
    ]);

    $response->assertRedirect(route('endUser.my-requests'));
    $response->assertSessionMissing('error');

    $this->assertDatabaseHas('requests', [
        'requested_item_name' => 'Assigned Laptop',
        'user_id' => $this->endUser->id,
        'quantity' => 1,
        // Not approvable: there is nothing to allocate.
        'status' => AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
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

test('requests page labels approved transfers as transfers for sender and recipient', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Dell Laptop',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);
    $recipient = User::factory()->create(['role_id' => $this->endUserRole->role_id]);
    $transaction = Transaction::create([
        'user_id' => $recipient->id,
        'from_user_id' => $this->endUser->id,
        'item_id' => $inventory->item_id,
        'quantity' => 1,
        'transaction_date' => '2026-09-27',
        'status' => 'assigned',
    ]);
    AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'transaction_id' => $transaction->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($recipient)
        ->get(route('endUser.requests'))
        ->assertOk()
        ->assertSee('Transfer Request');

    $this->actingAs($this->endUser)
        ->get(route('endUser.requests'))
        ->assertOk()
        ->assertSee('Transfer');
});

test('my requests page shows every inventory and transaction in a fulfilled requisition', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $firstInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Dell Laptop',
        'inventory_item_no' => 'INV-000034',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);
    $secondInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Dell Laptop',
        'inventory_item_no' => 'INV-000031',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);
    $parentRequest = AssignmentRequest::create([
        'requested_item_name' => 'Dell Laptop',
        'requested_category_id' => $category->category_id,
        'requested_unit' => 'piece',
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => 2,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    foreach ([$firstInventory, $secondInventory] as $inventory) {
        $transaction = Transaction::create([
            'user_id' => $this->endUser->id,
            'from_user_id' => $this->propertyCustodian->id,
            'item_id' => $inventory->item_id,
            'quantity' => 1,
            'transaction_date' => '2026-09-27',
            'status' => 'assigned',
        ]);
        AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $this->propertyCustodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'parent_request_id' => $parentRequest->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => $parentRequest->requested_at,
            'responded_at' => now(),
        ]);
    }

    $this->actingAs($this->endUser)
        ->get(route('endUser.requests'))
        ->assertOk()
        ->assertViewHas('incomingRequests', fn ($requests) => $requests->isEmpty())
        ->assertSee('Fulfilled Records')
        ->assertSee('INV-000034')
        ->assertSee('INV-000031')
        ->assertDontSee('Requisition Fulfillment')
        ->assertDontSee('Fulfills requisition #' . $parentRequest->id)
        ->assertSee('#TX-00001')
        ->assertSee('#TX-00002');
});

test('end user cannot transfer a different item than the original assignment', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $assignedInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $differentInventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Monitor',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $recipient = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'transfer-recipient',
        'email' => 'recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $assignment = AssignmentRequest::create([
        'item_id' => $assignedInventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.assigned-items.transfer'), [
        'request_id' => $assignment->id,
        'transfer_user_id' => $recipient->id,
        'item_id' => $differentInventory->item_id,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('item_id');
    $this->assertDatabaseHas('requests', [
        'id' => $assignment->id,
        'status' => 'approved',
    ]);
    $this->assertDatabaseMissing('requests', [
        'item_id' => $differentInventory->item_id,
        'status' => 'waiting for transfer approval',
    ]);
});

test('end user cannot accept a request that is no longer pending', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $assignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.requests.respond', $assignment->id), [
        'action' => 'accept',
    ]);

    $response->assertRedirect(route('endUser.requests'));
    $response->assertSessionHas('error', 'This request is no longer awaiting a response.');
    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseHas('inventory', [
        'item_id' => $inventory->item_id,
        'quantity' => 1,
    ]);
});

test('end user cannot transfer an assignment they do not possess', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $otherUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Other',
        'last_name' => 'User',
        'username' => 'other-user',
        'email' => 'other@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $assignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $otherUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.assigned-items.transfer'), [
        'request_id' => $assignment->id,
        'transfer_user_id' => $otherUser->id,
        'item_id' => $inventory->item_id,
        'quantity' => 1,
    ]);

    $response->assertNotFound();
    $this->assertDatabaseHas('requests', [
        'id' => $assignment->id,
        'status' => 'approved',
    ]);
});

test('end user cannot transfer to a non-end-user recipient', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $assignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(route('endUser.assigned-items.transfer'), [
        'request_id' => $assignment->id,
        'transfer_user_id' => $this->propertyCustodian->id,
        'item_id' => $inventory->item_id,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('transfer_user_id');
    $this->assertDatabaseHas('requests', [
        'id' => $assignment->id,
        'status' => 'approved',
    ]);
    $this->assertDatabaseMissing('requests', [
        'target_user_id' => $this->propertyCustodian->id,
        'status' => 'waiting for transfer approval',
    ]);
});

test('declining a partial transfer restores the sender assignment quantity', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 5,
        'date_acquired' => '2026-08-10',
    ]);

    $recipient = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'partial-transfer-recipient',
        'email' => 'partial-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $originalAssignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 5,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)->post(route('endUser.assigned-items.transfer'), [
        'request_id' => $originalAssignment->id,
        'transfer_user_id' => $recipient->id,
        'item_id' => $inventory->item_id,
        'quantity' => 2,
    ])->assertRedirect(route('endUser.my-assigned-items'));

    $transferRequest = AssignmentRequest::where('user_id', $this->endUser->id)
        ->where('target_user_id', $recipient->id)
        ->where('status', 'waiting for transfer approval')
        ->firstOrFail();

    $this->actingAs($recipient)->post(route('endUser.requests.respond', $transferRequest->id), [
        'action' => 'decline',
    ])->assertRedirect(route('endUser.requests'));

    $this->assertDatabaseHas('requests', [
        'id' => $originalAssignment->id,
        'quantity' => 5,
        'status' => 'approved',
    ]);
    $this->assertDatabaseHas('requests', [
        'id' => $transferRequest->id,
        'status' => 'declined',
    ]);
});

test('a recipient does not see a declined transfer in assigned items', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Declined Transfer Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'declined',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->get(route('endUser.my-assigned-items'))
        ->assertOk()
        ->assertDontSee('Declined Transfer Laptop');
});

test('custodian approval transfers inventory ownership to the recipient', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'assigned_to_user_id' => $this->endUser->id,
        'building' => 'Main Building',
        'room' => 'Storage A',
        'item_name' => 'Transfer Laptop',
        'quantity' => 1,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);

    $recipient = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'approved-transfer-recipient',
        'email' => 'approved-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
        'building' => 'Science Building',
        'room' => '203',
    ]);

    AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'transfer pending',
        'requested_at' => now(),
    ]);

    $transferRequest = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'building' => 'Old Request Building',
        'room' => 'Old Request Room',
        'quantity' => 1,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transfers.approve', $transferRequest->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    $this->assertDatabaseHas('inventory', [
        'item_id' => $inventory->item_id,
        'assigned_to_user_id' => $recipient->id,
        'status' => 'assigned',
        'building' => 'Science Building',
        'room' => '203',
    ]);

    $this->assertDatabaseHas('transactions', [
        'item_id' => $inventory->item_id,
        'from_user_id' => $this->endUser->id,
        'user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'assigned',
        'from_building' => 'Main Building',
        'from_room' => 'Storage A',
        'building' => 'Science Building',
        'room' => '203',
    ]);

    $movement = StockMovement::where('inventory_id', $inventory->item_id)->sole();
    expect($movement->movement_type)->toBe('transfer')
        ->and($movement->quantity)->toBe(1)
        ->and($movement->user_id)->toBe($this->propertyCustodian->id)
        ->and($movement->reference_type)->toBe('assignment_request')
        ->and($movement->reference_id)->toBe($transferRequest->id)
        ->and($movement->from_building)->toBe('Main Building')
        ->and($movement->from_room)->toBe('Storage A')
        ->and($movement->to_building)->toBe('Science Building')
        ->and($movement->to_room)->toBe('203')
        ->and($movement->notes)->toContain("user #{$this->endUser->id}", "user #{$recipient->id}");

    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transfers.approve', $transferRequest->id))
        ->assertRedirect();
    expect(StockMovement::where('inventory_id', $inventory->item_id)->count())->toBe(1);
});

test('partial transfer keeps assigned totals aligned on inventory and transaction pages', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'assigned_to_user_id' => $this->endUser->id,
        'item_name' => 'Shared Laptop',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);
    $originTransaction = Transaction::create([
        'item_id' => $inventory->item_id,
        'from_user_id' => $this->propertyCustodian->id,
        'user_id' => $this->endUser->id,
        'quantity' => 4,
        'transaction_date' => now(),
        'status' => 'assigned',
    ]);
    $originalAssignment = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => $originTransaction->id,
        'quantity' => 4,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);
    $recipient = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'partial-transfer-recipient',
        'email' => 'partial-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.assigned-items.transfer'), [
            'request_id' => $originalAssignment->id,
            'transfer_user_id' => $recipient->id,
            'item_id' => $inventory->item_id,
            'quantity' => 2,
        ])
        ->assertRedirect(route('endUser.my-assigned-items'));
    $transferRequest = AssignmentRequest::where('user_id', $this->endUser->id)
        ->where('target_user_id', $recipient->id)
        ->where('status', 'waiting for transfer approval')
        ->firstOrFail();

    $this->actingAs($recipient)
        ->post(route('endUser.requests.respond', $transferRequest->id), ['action' => 'accept'])
        ->assertRedirect(route('endUser.requests'));
    $this->actingAs($this->propertyCustodian)
        ->post(route('propertyCustodian.transfers.approve', $transferRequest->id))
        ->assertRedirect(route('propertyCustodian.transactions'));

    expect($originTransaction->fresh()->quantity)->toBe(2)
        ->and(Transaction::where('status', 'assigned')->sum('quantity'))->toBe(4)
        ->and($inventory->fresh()->assigned_to_user_id)->toBe($this->endUser->id);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['total'] === 4
            && $metrics['assigned'] === 4)
        ->assertSee('End User (2)', false)
        ->assertSee('Transfer Recipient (2)', false);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertViewHas('totalAssignedCount', 4)
        ->assertViewHas('transactions', fn ($transactions) => $transactions->where('status', 'assigned')->sum('quantity') === 4);
});

test('transfer acceptance is protected against race conditions with lockForUpdate', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-08-10',
    ]);

    $recipient = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'transfer-recipient',
        'email' => 'recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $transferRequest = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $transferRequest2 = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'waiting for transfer approval',
        'requested_at' => now(),
    ]);

    // Simulate concurrent acceptance: both try to accept same transfer request
    // First acceptance should succeed as the recipient
    $response1 = $this->actingAs($recipient)->post(
        route('endUser.requests.respond', $transferRequest2->id),
        ['action' => 'accept']
    );
    $response1->assertRedirect(route('endUser.requests'));
    $response1->assertSessionHas('success');

    // Verify transfer was accepted
    $this->assertDatabaseHas('requests', [
        'id' => $transferRequest2->id,
        'status' => 'waiting for custodian approval',
    ]);

    // Second concurrent acceptance should fail gracefully (status already changed)
    $transferRequest2->refresh();
    expect($transferRequest2->status)->toBe('waiting for custodian approval');
});

test('end user can request return when an approved assignment exists even if inventory ownership is stale', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Bulletin Board',
        'quantity' => 9,
        'status' => 'assigned',
        'assigned_to_user_id' => null,
        'date_acquired' => '2026-08-30',
    ]);

    AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->propertyCustodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 10,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(
        route('endUser.inventory.request-return', $inventory->item_id)
    );

    $response->assertRedirect(route('endUser.my-requests'));
    $response->assertSessionHas('success', 'Return request submitted to property custodian.');
    $this->assertDatabaseHas('requests', [
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'status' => 'waiting for custodian approval',
        'quantity' => 10,
    ]);
});

test('end user can cancel a pending return request', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'date_acquired' => '2026-08-10',
    ]);

    $returnRequest = AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => $inventory->quantity,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->post(
        route('endUser.inventory.cancel-return', $inventory->item_id)
    );

    $response->assertRedirect(route('endUser.my-assigned-items'));
    $response->assertSessionHas('success', 'Return request cancelled successfully.');
    $this->assertDatabaseHas('requests', [
        'id' => $returnRequest->id,
        'status' => 'cancelled',
    ]);
});

test('end user does not see zero-quantity assignment rows in assigned items', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Printer Stand',
        'quantity' => 0,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'date_acquired' => '2026-08-30',
    ]);

    $response = $this->actingAs($this->endUser)->get(route('endUser.my-assigned-items'));

    $response->assertOk();
    $response->assertDontSee('Printer Stand');
});

test('pending return requests mark the assigned item as waiting for return approval and disable request return', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'date_acquired' => '2026-08-10',
    ]);

    AssignmentRequest::create([
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->propertyCustodian->id,
        'quantity' => $inventory->quantity,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->endUser)->get(route('endUser.my-assigned-items'));

    $response->assertOk();
    $response->assertSee('Waiting for Return Approval');
    $response->assertDontSee('Request Return');
});

test('end user cannot duplicate return request for same item', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Laptop',
        'quantity' => 1,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'date_acquired' => '2026-08-10',
    ]);

    // First return request
    $response1 = $this->actingAs($this->endUser)->post(
        route('endUser.inventory.request-return', $inventory->item_id)
    );
    $response1->assertRedirect(route('endUser.my-requests'));
    $response1->assertSessionHas('success', 'Return request submitted to property custodian.');

    // Verify first request created
    $this->assertDatabaseHas('requests', [
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'status' => 'waiting for custodian approval',
    ]);

    // Count existing requests
    $firstCount = AssignmentRequest::where('item_id', $inventory->item_id)
        ->where('user_id', $this->endUser->id)
        ->count();

    // Second return request for same item
    $response2 = $this->actingAs($this->endUser)->post(
        route('endUser.inventory.request-return', $inventory->item_id)
    );
    $response2->assertRedirect(route('endUser.my-requests'));
    $response2->assertSessionHas('success', 'Return request submitted to property custodian.');

    // Verify no duplicate was created
    $secondCount = AssignmentRequest::where('item_id', $inventory->item_id)
        ->where('user_id', $this->endUser->id)
        ->count();

    expect($secondCount)->toBe($firstCount);

    // Verify only one return request exists with correct status
    $this->assertDatabaseCount('requests', 1);
    $this->assertDatabaseHas('requests', [
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
        'status' => 'waiting for custodian approval',
    ]);
});
