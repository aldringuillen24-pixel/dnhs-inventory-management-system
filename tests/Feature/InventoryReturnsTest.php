<?php

namespace Tests\Feature;

use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\Category;
use App\Models\User;
use App\Models\StockMovement;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $custodian;
    private User $endUser;
    private Category $category;
    private Inventory $assignedItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $custodianRole = Role::create(['role_name' => 'Property Custodian']);
        $endUserRole = Role::create(['role_name' => 'End User']);

        // Create users
        $this->custodian = User::factory()->create(['role_id' => $custodianRole->role_id]);
        $this->endUser = User::factory()->create(['role_id' => $endUserRole->role_id]);

        // Create category
        $this->category = Category::create([
            'category_name' => 'Test Category',
            'requires_serial_number' => false,
        ]);

        // Create and assign an item
        $this->assignedItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Test Item',
            'description' => 'Test Description',
            'quantity' => 1,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);
    }

    /**
     * Test: End user can request return on their assigned item
     */
    public function test_end_user_can_request_return_on_assigned_item()
    {
        $this->actingAs($this->endUser);

        $response = $this->post(route('endUser.inventory.request-return', $this->assignedItem->item_id));

        $response->assertRedirect(route('endUser.my-requests'));
        $response->assertSessionHas('success', 'Return request submitted to property custodian.');

        // Verify request was created
        $this->assertDatabaseHas('requests', [
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'status' => 'waiting for custodian approval',
        ]);
    }

    public function test_custodian_sees_returns_and_peer_transfers_in_separate_queues()
    {
        $recipient = User::factory()->create(['role_id' => $this->endUser->role_id]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 1,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $transferRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $recipient->id,
            'quantity' => 1,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->custodian)->get(route('propertyCustodian.transactions'));

        $response->assertOk();
        $response->assertViewHas('pendingTransfers', fn ($requests) => $requests->pluck('id')->all() === [$transferRequest->id]);
        $response->assertViewHas('pendingReturns', fn ($requests) => $requests->pluck('id')->all() === [$returnRequest->id]);
    }

    public function test_custodian_can_approve_return_for_a_full_transfer_with_stale_inventory_ownership()
    {
        $recipient = User::factory()->create(['role_id' => $this->endUser->role_id]);

        AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $recipient->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $recipient->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 1,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.returns.approve', $returnRequest->id))
            ->assertRedirect(route('propertyCustodian.transactions'));

        $this->assertDatabaseHas('requests', [
            'id' => $returnRequest->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);
    }

    public function test_recipient_can_return_their_partial_transfer_quantity_without_affecting_sender_assignment()
    {
        $recipient = User::factory()->create(['role_id' => $this->endUser->role_id]);

        $inventory = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Shared Computer Table',
            'quantity' => 0,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);

        $senderAssignment = AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'quantity' => 5,
            'status' => 'approved',
            'requested_at' => now(),
        ]);

        $recipientAssignment = AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $recipient->id,
            'quantity' => 5,
            'status' => 'approved',
            'requested_at' => now(),
        ]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $recipient->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 5,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.returns.approve', $returnRequest->id))
            ->assertRedirect(route('propertyCustodian.transactions'));

        $this->assertDatabaseHas('requests', [
            'id' => $senderAssignment->id,
            'quantity' => 5,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('requests', [
            'id' => $recipientAssignment->id,
            'quantity' => 0,
            'status' => 'returned',
        ]);
        $this->assertDatabaseHas('inventory', [
            'item_id' => $inventory->item_id,
            'quantity' => 5,
            'status' => 'available',
        ]);
    }

    /**
     * Test: End user cannot request return on items not assigned to them
     */
    public function test_end_user_cannot_request_return_on_unassigned_item()
    {
        $otherUser = User::factory()->create(['role_id' => $this->endUser->role_id]);

        $this->actingAs($otherUser);

        $response = $this->post(route('endUser.inventory.request-return', $this->assignedItem->item_id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify request was NOT created
        $this->assertDatabaseMissing('requests', [
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $otherUser->id,
            'status' => 'waiting for custodian approval',
        ]);
    }

    /**
     * Test: End user cannot request return on available items
     */
    public function test_end_user_cannot_request_return_on_available_item()
    {
        $availableItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Available Item',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'assigned_to_user_id' => null,
            'user_id' => $this->custodian->id,
        ]);

        $this->actingAs($this->endUser);

        $response = $this->post(route('endUser.inventory.request-return', $availableItem->item_id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /**
     * Test: Custodian can approve return request
     */
    public function test_custodian_can_approve_return_request()
    {
        // Create return request
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);

        $response = $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        $response->assertRedirect(route('propertyCustodian.transactions'));
        $response->assertSessionHas('success', 'Return request approved. Item is now available.');

        // Verify item status changed
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);

        // Verify request status changed
        $this->assertDatabaseHas('requests', [
            'id' => $returnRequest->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Test: Item becomes available after return approval
     */
    public function test_item_becomes_available_after_return_approval()
    {
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);
        $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        $updatedItem = Inventory::find($this->assignedItem->item_id);
        $this->assertEquals('available', $updatedItem->status);
        $this->assertNull($updatedItem->assigned_to_user_id);
    }

    /**
     * Test: Audit row created with movement_type='returned' on approval
     */
    public function test_audit_row_created_on_return_approval()
    {
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);
        $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        // Verify audit row
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('movement_type', 'returned')
            ->where('reference_type', 'assignment_request')
            ->where('reference_id', $returnRequest->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals($this->custodian->id, $movement->user_id);
        $this->assertEquals($this->assignedItem->quantity, $movement->quantity);
        $this->assertStringContainsString('returned', strtolower($movement->notes));
    }

    /**
     * Test: Custodian can mark assigned item as returned directly
     */
    public function test_custodian_can_mark_assigned_item_as_returned_directly()
    {
        $this->actingAs($this->custodian);

        $response = $this->post(route('propertyCustodian.inventory.mark-returned', $this->assignedItem->item_id), [
            'notes' => 'Item received in warehouse',
        ]);

        $response->assertRedirect(route('propertyCustodian.inventory'));
        $response->assertSessionHas('success', 'Item marked as returned and now available.');

        // Verify item status changed
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);
    }

    /**
     * Test: Cannot mark available items as returned
     */
    public function test_cannot_mark_available_item_as_returned()
    {
        $availableItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Available Test Item',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'assigned_to_user_id' => null,
            'user_id' => $this->custodian->id,
        ]);

        $this->actingAs($this->custodian);

        $response = $this->post(route('propertyCustodian.inventory.mark-returned', $availableItem->item_id), [
            'notes' => 'Test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /**
     * Test: Audit row created for direct return (mark-returned)
     */
    public function test_audit_row_created_on_direct_return()
    {
        $this->actingAs($this->custodian);

        $this->post(route('propertyCustodian.inventory.mark-returned', $this->assignedItem->item_id), [
            'notes' => 'Item received from warehouse',
        ]);

        // Verify audit row
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('movement_type', 'returned')
            ->where('reference_type', 'inventory')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals($this->custodian->id, $movement->user_id);
        $this->assertEquals($this->assignedItem->quantity, $movement->quantity);
    }

    /**
     * Test: Custodian can decline return request
     */
    public function test_custodian_can_decline_return_request()
    {
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);

        $response = $this->post(route('propertyCustodian.returns.decline', $returnRequest->id), [
            'notes' => 'Item still in use',
        ]);

        $response->assertRedirect(route('propertyCustodian.transactions'));
        $response->assertSessionHas('success', 'Return request declined.');

        // Verify request status changed
        $this->assertDatabaseHas('requests', [
            'id' => $returnRequest->id,
            'status' => 'declined',
        ]);

        // Verify item status did NOT change
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
        ]);
    }

    /**
     * Test: Both return flows show in audit ledger
     */
    public function test_both_return_flows_appear_in_audit_ledger()
    {
        // Approve a return request (Flow A)
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);
        $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        // Assign another item for direct return (Flow B)
        $secondItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Second Item',
            'description' => 'Test',
            'quantity' => 1,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);

        $this->post(route('propertyCustodian.inventory.mark-returned', $secondItem->item_id), [
            'notes' => 'Direct return',
        ]);

        // Verify both movements are recorded
        $movements = StockMovement::where('movement_type', 'returned')->get();

        $this->assertGreaterThanOrEqual(2, $movements->count());

        // Verify Flow A (request-based)
        $requestBased = $movements->where('reference_type', 'assignment_request')->first();
        $this->assertNotNull($requestBased);
        $this->assertEquals($this->assignedItem->item_id, $requestBased->inventory_id);

        // Verify Flow B (direct)
        $directBased = $movements->where('reference_type', 'inventory')->first();
        $this->assertNotNull($directBased);
        $this->assertEquals($secondItem->item_id, $directBased->inventory_id);
    }

    /**
     * Test: Only authenticated custodian can approve returns
     */
    public function test_unauthenticated_user_cannot_approve_return()
    {
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $response = $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: End user cannot approve returns
     */
    public function test_end_user_cannot_approve_return()
    {
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->endUser);

        $response = $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        $response->assertStatus(403);
    }

    /**
     * Test: Return request quantity matches inventory quantity
     */
    public function test_return_request_records_correct_quantity()
    {
        $multiItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Multi-Qty Item',
            'description' => 'Test',
            'quantity' => 5,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);

        $this->actingAs($this->endUser);
        $this->post(route('endUser.inventory.request-return', $multiItem->item_id));

        $returnRequest = AssignmentRequest::where('item_id', $multiItem->item_id)->first();

        $this->assertEquals(5, $returnRequest->quantity);
    }
}
