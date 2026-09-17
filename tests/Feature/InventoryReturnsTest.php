<?php

namespace Tests\Feature;

use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\Category;
use App\Models\User;
use App\Models\StockMovement;
use App\Models\Role;
use App\Models\Transaction;
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

    public function test_approving_a_partial_return_reduces_the_related_transaction_quantity()
    {
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 5,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        $assignment = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 5,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 2,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.returns.approve', $returnRequest->id))
            ->assertRedirect(route('propertyCustodian.transactions'));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'quantity' => 3,
            'status' => 'assigned',
            'return_date' => null,
        ]);
        $this->assertDatabaseHas('requests', [
            'id' => $assignment->id,
            'quantity' => 3,
            'status' => 'approved',
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

    public function test_approving_a_full_return_closes_the_related_transaction()
    {
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => $this->assignedItem->quantity,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => $this->assignedItem->quantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.returns.approve', $returnRequest->id))
            ->assertRedirect(route('propertyCustodian.transactions'));

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'returned',
        ]);
        $this->assertNotNull(Transaction::find($transaction->id)->return_date);
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
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => $this->assignedItem->quantity,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

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
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'returned',
        ]);
        $this->assertNotNull(Transaction::find($transaction->id)->return_date);
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

    public function test_custodian_can_send_an_available_item_to_maintenance_and_audit_it()
    {
        $this->assignedItem->update([
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.send-to-maintenance', $this->assignedItem->item_id), [
                'issue_description' => 'Needs repair',
                'notes' => 'Needs repair',
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'under_maintenance',
        ]);
        $this->assertDatabaseHas('maintenance_records', [
            'inventory_id' => $this->assignedItem->item_id,
            'reported_by' => $this->custodian->id,
            'status' => 'reported',
            'issue_description' => 'Needs repair',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $this->assignedItem->item_id,
            'movement_type' => 'maintenance',
            'notes' => 'Needs repair',
        ]);
    }

    public function test_ineligible_category_disables_send_to_maintenance_in_available_inventory()
    {
        $category = Category::create([
            'category_name' => 'Learning Resources',
            'requires_serial_number' => false,
            'is_maintenance_eligible' => false,
        ]);

        Inventory::create([
            'category_id' => $category->category_id,
            'item_name' => 'Workbook',
            'quantity' => 1,
            'unit' => 'copy',
            'unit_cost' => 100,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'user_id' => $this->custodian->id,
        ]);

        $response = $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'));

        $response->assertOk();
        $response->assertSee('disabled title="This category is not eligible for maintenance."', false);
    }

    public function test_furniture_category_is_not_eligible_for_maintenance()
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $category = Category::where('category_name', 'Furniture and Fixtures')->firstOrFail();

        $item = Inventory::create([
            'category_id' => $category->category_id,
            'item_name' => 'Teacher Desk',
            'quantity' => 1,
            'unit' => 'piece',
            'unit_cost' => 3500,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'user_id' => $this->custodian->id,
        ]);

        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertOk()
            ->assertSee('disabled title="This category is not eligible for maintenance."', false);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.send-to-maintenance', $item->item_id), [
                'issue_description' => 'Furniture should not enter maintenance.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Items in this category are not eligible for maintenance.');

        $this->assertDatabaseHas('inventory', [
            'item_id' => $item->item_id,
            'status' => 'available',
        ]);
    }

    public function test_ineligible_category_cannot_be_sent_to_maintenance_directly()
    {
        $category = Category::create([
            'category_name' => 'Learning Resources',
            'requires_serial_number' => false,
            'is_maintenance_eligible' => false,
        ]);

        $item = Inventory::create([
            'category_id' => $category->category_id,
            'item_name' => 'Workbook',
            'quantity' => 1,
            'unit' => 'copy',
            'unit_cost' => 100,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'user_id' => $this->custodian->id,
        ]);

        $response = $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.send-to-maintenance', $item->item_id), [
                'issue_description' => 'Should be rejected',
            ])
            ->assertRedirect();

        $response->assertSessionHas('error', 'Items in this category are not eligible for maintenance.');
        $this->assertDatabaseHas('inventory', [
            'item_id' => $item->item_id,
            'status' => 'available',
        ]);
        $this->assertDatabaseMissing('maintenance_records', [
            'inventory_id' => $item->item_id,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'inventory_id' => $item->item_id,
            'movement_type' => 'maintenance',
        ]);
    }

    public function test_custodian_can_mark_a_maintenance_item_as_repaired_and_audit_it()
    {
        $this->assignedItem->update([
            'status' => 'under_maintenance',
            'assigned_to_user_id' => null,
        ]);

        $maintenanceRecord = \App\Models\MaintenanceRecord::create([
            'inventory_id' => $this->assignedItem->item_id,
            'reported_by' => $this->custodian->id,
            'status' => 'reported',
            'issue_description' => 'Needs repair',
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.mark-repaired', $this->assignedItem->item_id), [
                'repair_notes' => 'Power cable replaced.',
                'maintenance_cost' => '250.00',
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('maintenance_records', [
            'id' => $maintenanceRecord->id,
            'status' => 'completed',
            'repair_notes' => 'Power cable replaced.',
            'maintenance_cost' => '250.00',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $this->assignedItem->item_id,
            'movement_type' => 'maintenance_completed',
            'notes' => 'Power cable replaced.',
        ]);
    }

    public function test_custodian_can_dispose_an_available_item_and_audit_it()
    {
        $this->assignedItem->update([
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.dispose', $this->assignedItem->item_id), [
                'notes' => 'Beyond repair',
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'status' => 'disposed',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $this->assignedItem->item_id,
            'movement_type' => 'disposed',
            'quantity_after' => 0,
            'notes' => 'Beyond repair',
        ]);
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
