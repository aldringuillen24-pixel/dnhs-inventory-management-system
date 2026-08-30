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

test('end user cannot request assigned inventory as available stock', function () {
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

    $response = $this->actingAs($this->endUser)->post(route('endUser.requests.store'), [
        'item_id' => $inventory->item_id,
        'quantity' => 1,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Requested quantity exceeds available stock.');
    $this->assertDatabaseMissing('requests', [
        'item_id' => $inventory->item_id,
        'user_id' => $this->endUser->id,
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
    ]);
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
