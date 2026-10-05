<?php

namespace Tests\Feature;

use App\Models\AssignmentRequest;
use App\Models\AssignmentReturn;
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
        $this->assignedItem->update(['building' => 'Classroom Building', 'room' => '203']);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'from_building' => 'Main Building',
            'from_room' => 'Storage A',
            'building' => 'Classroom Building',
            'room' => '203',
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
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('reference_type', 'assignment_return')
            ->sole();
        $returnRecord = AssignmentReturn::where('return_request_id', $returnRequest->id)->sole();
        $this->assertSame($returnRecord->id, $movement->reference_id);
        $this->assertSame(2, $movement->quantity);
        $this->assertSame(1, $movement->quantity_before);
        $this->assertSame(3, $movement->quantity_after);
        expect($this->assignedItem->fresh()->building)->toBe('Main Building')
            ->and($this->assignedItem->fresh()->room)->toBe('Storage A')
            ->and($movement->from_building)->toBe('Classroom Building')
            ->and($movement->from_room)->toBe('203')
            ->and($movement->to_building)->toBe('Main Building')
            ->and($movement->to_room)->toBe('Storage A')
            ->and($returnRecord->building)->toBe('Main Building')
            ->and($returnRecord->room)->toBe('Storage A');
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
        $this->assignedItem->update(['quantity' => 0]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 1,
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
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['total'] === 1
                && $metrics['available'] === 1
                && $metrics['assigned'] === 0);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.transactions'))
            ->assertViewHas('totalAssignedCount', 0)
            ->assertViewHas('availableInventoryItems', fn ($items) => $items->first()['quantity'] === 1)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->first()->status === 'returned');
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('movement_type', 'returned')
            ->sole();
        $this->assertStringContainsString("user #{$this->endUser->id}", $movement->notes);
        $this->assertStringContainsString("user #{$this->custodian->id}", $movement->notes);
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
        $this->assignedItem->update(['quantity' => 0]);
        $returnRequest = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'target_user_id' => $this->custodian->id,
            'quantity' => 1,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian);
        $this->post(route('propertyCustodian.returns.approve', $returnRequest->id));

        // Verify audit row
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('movement_type', 'returned')
            ->where('reference_type', 'assignment_return')
            ->first();

        $this->assertNotNull($movement);
        $this->assertDatabaseHas('assignment_returns', [
            'id' => $movement->reference_id,
            'return_request_id' => $returnRequest->id,
        ]);
        $this->assertEquals($this->custodian->id, $movement->user_id);
        $this->assertSame(1, $movement->quantity);
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'quantity' => 1,
            'status' => 'available',
        ]);
        $this->assertStringContainsString('returned', strtolower($movement->notes));
    }

    public function test_receive_return_modal_lists_the_exact_active_assignment()
    {
        $this->assignedItem->update([
            'quantity' => 0,
            'inventory_item_no' => 'INV-RETURN-001',
        ]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 2,
            'issued_quantity' => 2,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);
        AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 2,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertOk()
            ->assertSee('Receive Return')
            ->assertSee('INV-RETURN-001')
            ->assertSee('Item Assigned')
            ->assertSee($this->endUser->full_name)
            ->assertSee('type="checkbox"', false);
    }

    public function test_custodian_can_receive_multiple_partial_returns_for_one_assignment()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 5,
            'issued_quantity' => 5,
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
            'transaction_id' => $transaction->id,
            'quantity' => 5,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_request_id' => $returnRequest->id,
                'return_quantity' => 2,
                'notes' => 'Two units received in good condition',
            ])
            ->assertRedirect(route('propertyCustodian.inventory'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'quantity' => 2,
            'status' => 'available',
            'assigned_to_user_id' => null,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'quantity' => 3,
            'issued_quantity' => 5,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('requests', [
            'id' => $assignment->id,
            'quantity' => 3,
            'status' => 'approved',
        ]);
        expect($returnRequest->fresh()->quantity)->toBe(3)
            ->and($returnRequest->fresh()->status)->toBe('waiting for custodian approval');
        $this->assertDatabaseHas('assignment_returns', [
            'transaction_id' => $transaction->id,
            'assignment_request_id' => $assignment->id,
            'recipient_id' => $this->endUser->id,
            'received_by' => $this->custodian->id,
            'quantity' => 2,
            'notes' => 'Two units received in good condition',
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_request_id' => $returnRequest->id,
                'return_quantity' => 1,
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        expect($transaction->fresh()->quantity)->toBe(2)
            ->and($assignment->fresh()->quantity)->toBe(2)
            ->and(AssignmentReturn::where('transaction_id', $transaction->id)->sum('quantity'))->toBe(3)
            ->and($this->assignedItem->fresh()->quantity)->toBe(3)
            ->and(StockMovement::where('reference_type', 'assignment_return')->where('inventory_id', $this->assignedItem->item_id)->count())->toBe(2);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_request_id' => $returnRequest->id,
                'return_quantity' => 2,
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        expect($transaction->fresh()->quantity)->toBe(0)
            ->and($transaction->fresh()->status)->toBe('returned')
            ->and($assignment->fresh()->quantity)->toBe(0)
            ->and($assignment->fresh()->status)->toBe('returned')
            ->and($returnRequest->fresh()->quantity)->toBe(0)
            ->and($returnRequest->fresh()->status)->toBe('approved')
            ->and(AssignmentReturn::where('transaction_id', $transaction->id)->sum('quantity'))->toBe(5)
            ->and($this->assignedItem->fresh()->quantity)->toBe(5)
            ->and(StockMovement::where('reference_type', 'assignment_return')->where('inventory_id', $this->assignedItem->item_id)->count())->toBe(3);
    }

    public function test_partial_return_changes_only_the_selected_assignment_for_shared_inventory()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $otherUser = User::factory()->create(['role_id' => $this->endUser->role_id]);
        $firstTransaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 2,
            'issued_quantity' => 2,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);
        $secondTransaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $otherUser->id,
            'quantity' => 3,
            'issued_quantity' => 3,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);
        $firstAssignment = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $firstTransaction->id,
            'quantity' => 2,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);
        $secondAssignment = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $otherUser->id,
            'transaction_id' => $secondTransaction->id,
            'quantity' => 3,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $firstTransaction->id,
                'return_quantity' => 1,
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        expect($firstTransaction->fresh()->quantity)->toBe(1)
            ->and($firstAssignment->fresh()->quantity)->toBe(1)
            ->and($secondTransaction->fresh()->quantity)->toBe(3)
            ->and($secondAssignment->fresh()->quantity)->toBe(3)
            ->and($this->assignedItem->fresh()->quantity)->toBe(1)
            ->and($this->assignedItem->fresh()->assigned_to_user_id)->toBeNull()
            ->and(AssignmentReturn::where('transaction_id', $firstTransaction->id)->sum('quantity'))->toBe(1)
            ->and(AssignmentReturn::where('transaction_id', $secondTransaction->id)->count())->toBe(0);
    }

    public function test_custodian_can_select_multiple_assignments_and_only_selected_rows_are_returned()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $recipientTwo = User::factory()->create(['role_id' => $this->endUser->role_id]);
        $recipientThree = User::factory()->create(['role_id' => $this->endUser->role_id]);
        $recipients = [$this->endUser, $recipientTwo, $recipientThree];
        $transactions = [];
        $assignments = [];

        foreach ($recipients as $recipient) {
            $transaction = Transaction::create([
                'item_id' => $this->assignedItem->item_id,
                'user_id' => $recipient->id,
                'quantity' => 1,
                'issued_quantity' => 1,
                'transaction_date' => now(),
                'status' => 'assigned',
            ]);
            $transactions[] = $transaction;
            $assignments[] = AssignmentRequest::create([
                'item_id' => $this->assignedItem->item_id,
                'user_id' => $this->custodian->id,
                'target_user_id' => $recipient->id,
                'transaction_id' => $transaction->id,
                'quantity' => 1,
                'status' => 'approved',
                'requested_at' => now(),
                'responded_at' => now(),
            ]);
        }

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_ids' => [$transactions[0]->id, $transactions[1]->id],
            ])
            ->assertRedirect(route('propertyCustodian.inventory'))
            ->assertSessionHas('success', '2 assignment return(s) received and recorded.');

        expect($this->assignedItem->fresh()->quantity)->toBe(2)
            ->and($this->assignedItem->fresh()->status)->toBe('available')
            ->and($transactions[0]->fresh()->status)->toBe('returned')
            ->and($transactions[1]->fresh()->status)->toBe('returned')
            ->and($assignments[0]->fresh()->status)->toBe('returned')
            ->and($assignments[1]->fresh()->status)->toBe('returned')
            ->and($transactions[2]->fresh()->status)->toBe('assigned')
            ->and($assignments[2]->fresh()->status)->toBe('approved')
            ->and(AssignmentReturn::count())->toBe(2)
            ->and(StockMovement::where('movement_type', 'returned')->count())->toBe(2);
    }

    public function test_returns_greater_than_remaining_and_inactive_assignments_do_not_change_records()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);
        $assignment = AssignmentRequest::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_quantity' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('return_quantity');

        expect($this->assignedItem->fresh()->quantity)->toBe(0)
            ->and($transaction->fresh()->quantity)->toBe(1)
            ->and($transaction->fresh()->status)->toBe('assigned')
            ->and($assignment->fresh()->quantity)->toBe(1)
            ->and(AssignmentReturn::count())->toBe(0)
            ->and(StockMovement::where('movement_type', 'returned')->count())->toBe(0);

        $transaction->update(['quantity' => 0, 'status' => 'returned']);
        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_quantity' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('return_quantity');

        expect($this->assignedItem->fresh()->quantity)->toBe(0)
            ->and($assignment->fresh()->quantity)->toBe(1)
            ->and(AssignmentReturn::count())->toBe(0)
            ->and(StockMovement::where('movement_type', 'returned')->count())->toBe(0);
    }

    public function test_serialized_return_requires_the_exact_assigned_asset_and_quantity_one()
    {
        $serializedCategory = Category::create([
            'category_name' => 'Serialized Equipment',
            'requires_serial_number' => true,
        ]);
        $asset = Inventory::create([
            'category_id' => $serializedCategory->category_id,
            'item_name' => 'Laptop',
            'serial_number' => 'SN-RETURN-100',
            'inventory_item_no' => 'INV-RETURN-100',
            'description' => 'Serialized asset',
            'quantity' => 0,
            'unit' => 'piece',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);
        $transaction = Transaction::create([
            'item_id' => $asset->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);
        $assignment = AssignmentRequest::create([
            'item_id' => $asset->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_quantity' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('return_quantity');

        expect($asset->fresh()->quantity)->toBe(0)
            ->and($asset->fresh()->status)->toBe('assigned')
            ->and($asset->fresh()->assigned_to_user_id)->toBe($this->endUser->id)
            ->and($transaction->fresh()->quantity)->toBe(1)
            ->and($assignment->fresh()->quantity)->toBe(1)
            ->and(AssignmentReturn::where('transaction_id', $transaction->id)->count())->toBe(0);

        $this->actingAs($this->custodian)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_quantity' => 1,
            ])
            ->assertRedirect(route('propertyCustodian.inventory'));

        expect($asset->fresh()->quantity)->toBe(1)
            ->and($asset->fresh()->status)->toBe('available')
            ->and($transaction->fresh()->status)->toBe('returned')
            ->and($assignment->fresh()->status)->toBe('returned')
            ->and(AssignmentReturn::where('transaction_id', $transaction->id)->sum('quantity'))->toBe(1);
    }

    public function test_only_property_custodians_can_receive_an_assignment_return()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        $this->actingAs($this->endUser)
            ->post(route('propertyCustodian.inventory.receive-return'), [
                'transaction_id' => $transaction->id,
                'return_quantity' => 1,
            ])
            ->assertForbidden();

        expect($this->assignedItem->fresh()->quantity)->toBe(0)
            ->and($transaction->fresh()->status)->toBe('assigned')
            ->and(AssignmentReturn::count())->toBe(0)
            ->and(StockMovement::where('movement_type', 'returned')->count())->toBe(0);
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
            'reference_type' => 'maintenance_record',
            'notes' => 'Needs repair',
        ]);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertViewHas('inventoryStatusCounts', fn ($counts) => $counts['under_maintenance'] === 1)
            ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['attention'] === 1);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.transactions'))
            ->assertViewHas('availableInventoryItems', fn ($items) => $items->isEmpty());
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

    public function test_furniture_category_is_eligible_for_maintenance()
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $category = Category::where('category_name', 'Furniture')->firstOrFail();
        $this->assertTrue((bool) $category->is_maintenance_eligible);

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
            ->post(route('propertyCustodian.inventory.send-to-maintenance', $item->item_id), [
                'issue_description' => 'Furniture repair request.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory', [
            'item_id' => $item->item_id,
            'status' => 'under_maintenance',
        ]);
    }

    public function test_consumables_category_is_not_eligible_for_maintenance()
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $category = Category::where('category_name', 'Consumables')->firstOrFail();

        $item = Inventory::create([
            'category_id' => $category->category_id,
            'item_name' => 'Bond Paper',
            'quantity' => 1,
            'unit' => 'ream',
            'unit_cost' => 320,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
            'user_id' => $this->custodian->id,
        ]);

        $this->actingAs($this->custodian)
            ->postJson(route('api.custodian.inventory.send-to-maintenance', $item->item_id), [
                'issue_description' => 'Consumables should not enter maintenance.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Items in this category are not eligible for maintenance.');

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
            'started_at' => now()->subHour(),
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
        $maintenanceRecord->refresh();
        $this->assertNotNull($maintenanceRecord->started_at);
        $this->assertNotNull($maintenanceRecord->completed_at);
        $this->assertLessThanOrEqual($maintenanceRecord->completed_at, $maintenanceRecord->started_at);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $this->assignedItem->item_id,
            'movement_type' => 'maintenance_completed',
            'notes' => 'Power cable replaced.',
        ]);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['available'] === 1)
            ->assertViewHas('inventoryStatusCounts', fn ($counts) => $counts['available'] === 1);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.transactions'))
            ->assertViewHas('availableInventoryItems', fn ($items) => $items->first()['quantity'] === 1);
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
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.inventory'))
            ->assertViewHas('inventoryStatusCounts', fn ($counts) => $counts['disposed'] === 1)
            ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['total'] === 0);
        $this->actingAs($this->custodian)
            ->get(route('propertyCustodian.transactions'))
            ->assertViewHas('availableInventoryItems', fn ($items) => $items->isEmpty());
    }

    /**
     * Test: Audit row created for direct return (mark-returned)
     */
    public function test_audit_row_created_on_direct_return()
    {
        $this->assignedItem->update(['quantity' => 0]);
        $transaction = Transaction::create([
            'item_id' => $this->assignedItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        $this->actingAs($this->custodian);

        $this->post(route('propertyCustodian.inventory.receive-return'), [
            'transaction_id' => $transaction->id,
            'return_quantity' => 1,
            'notes' => 'Item received from warehouse',
        ]);

        // Verify audit row
        $movement = StockMovement::where('inventory_id', $this->assignedItem->item_id)
            ->where('movement_type', 'returned')
            ->where('reference_type', 'assignment_return')
            ->first();

        $this->assertNotNull($movement);
        $this->assertDatabaseHas('assignment_returns', [
            'id' => $movement->reference_id,
            'transaction_id' => $transaction->id,
            'inventory_id' => $this->assignedItem->item_id,
        ]);
        $this->assertEquals($this->custodian->id, $movement->user_id);
        $this->assertSame(1, $movement->quantity);
        $this->assertDatabaseHas('inventory', [
            'item_id' => $this->assignedItem->item_id,
            'quantity' => 1,
            'status' => 'available',
        ]);
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

        // Assign another item for direct receipt (Flow B)
        $secondItem = Inventory::create([
            'category_id' => $this->category->category_id,
            'item_name' => 'Second Item',
            'description' => 'Test',
            'quantity' => 0,
            'unit' => 'pcs',
            'date_acquired' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_to_user_id' => $this->endUser->id,
            'user_id' => $this->custodian->id,
        ]);
        $secondTransaction = Transaction::create([
            'item_id' => $secondItem->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'issued_quantity' => 1,
            'transaction_date' => now(),
            'status' => 'assigned',
        ]);

        $this->post(route('propertyCustodian.inventory.receive-return'), [
            'transaction_id' => $secondTransaction->id,
            'return_quantity' => 1,
            'notes' => 'Direct return',
        ]);

        // Verify both movements are recorded
        $movements = StockMovement::where('movement_type', 'returned')->get();

        $this->assertGreaterThanOrEqual(2, $movements->count());

        // Verify Flow A (request-based)
        $requestBased = $movements->where('reference_type', 'assignment_return')->first();
        $this->assertNotNull($requestBased);
        $this->assertEquals($this->assignedItem->item_id, $requestBased->inventory_id);

        // Verify Flow B (direct)
        $directBased = $movements->where('reference_type', 'assignment_return')->firstWhere('inventory_id', $secondItem->item_id);
        $this->assertNotNull($directBased);
        $this->assertSame('assignment_return', $directBased->reference_type);
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
