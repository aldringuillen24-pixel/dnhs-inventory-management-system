<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\AssignmentReturn;
use App\Models\Category;
use App\Models\InspectionRecord;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryOperationService
{
    public function submitRegisteredAssignment(
        int $itemId,
        int $actorId,
        int $recipientId,
        int $quantity,
        ?string $transactionDate,
    ): string {
        return DB::transaction(function () use ($itemId, $actorId, $recipientId, $quantity, $transactionDate): string {
            $recipient = User::query()->whereKey($recipientId)->lockForUpdate()->firstOrFail();
            $inventoryItem = Inventory::whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventoryItem->status !== 'available') {
                return 'unavailable';
            }
            if ($quantity < 1 || $quantity > (int) $inventoryItem->quantity) {
                return 'insufficient';
            }

            AssignmentRequest::create([
                'item_id' => $inventoryItem->item_id,
                'user_id' => $actorId,
                'target_user_id' => $recipientId,
                'building' => $recipient->building,
                'room' => $recipient->room,
                'quantity' => $quantity,
                'status' => 'waiting for approval',
                'requested_at' => $transactionDate ? Carbon::parse($transactionDate) : now(),
            ]);

            return 'created';
        });
    }

    public function issueManual(array $attributes, int $actorId): Transaction|string
    {
        return DB::transaction(function () use ($attributes, $actorId): Transaction|string {
            $inventoryItem = Inventory::with('category')
                ->whereKey($attributes['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $inventoryItem->status !== 'available'
                || (int) $inventoryItem->quantity < 1
                || (int) $inventoryItem->category_id !== (int) $attributes['category_id']
                || $inventoryItem->unit !== $attributes['unit']
            ) {
                return 'The selected inventory record is no longer available or does not match its category and unit.';
            }

            $quantity = (int) $attributes['quantity'];
            if ($inventoryItem->category?->requires_serial_number) {
                if ($quantity !== 1 || (int) $inventoryItem->quantity !== 1 || ! $inventoryItem->serial_number) {
                    return 'Serialized inventory must be issued as its exact individual asset with quantity 1.';
                }
            } elseif ($quantity < 1 || $quantity > (int) $inventoryItem->quantity) {
                return 'The requested quantity exceeds the selected inventory record.';
            }

            $quantityBefore = (int) $inventoryItem->quantity;
            $quantityAfter = $quantityBefore - $quantity;
            $locationBefore = $inventoryItem->only(['building', 'room']);
            $inventoryItem->quantity = $quantityAfter;
            $inventoryItem->building = $attributes['building'] ?? $inventoryItem->building;
            $inventoryItem->room = $attributes['room'] ?? $inventoryItem->room;
            if ($quantityAfter === 0) {
                $inventoryItem->status = 'assigned';
                $inventoryItem->assigned_to_user_id = null;
            }
            $inventoryItem->save();

            $transaction = Transaction::create([
                'user_id' => null,
                'from_user_id' => $actorId,
                'item_id' => $inventoryItem->item_id,
                'quantity' => $quantity,
                'issued_quantity' => $quantity,
                'from_building' => $locationBefore['building'],
                'from_room' => $locationBefore['room'],
                'building' => $inventoryItem->building,
                'room' => $inventoryItem->room,
                'transaction_date' => $attributes['transaction_date'],
                'status' => 'assigned',
                'manual_recipient_name' => $attributes['manual_recipient_name'],
                'manual_department' => $attributes['manual_department'],
                'manual_recipient_type' => $attributes['manual_recipient_type'] ?? null,
                'manual_contact' => $attributes['manual_contact'] ?? null,
                'manual_notes' => $attributes['manual_notes'] ?? null,
                'expected_return_date' => $attributes['expected_return_date'] ?? null,
            ]);

            $this->recordMovement(
                $inventoryItem->item_id,
                $actorId,
                'assignment',
                $quantity,
                $quantityBefore,
                $quantityAfter,
                'transaction',
                $transaction->id,
                "Manual issue to {$transaction->manual_recipient_name} ({$transaction->manual_department}); transaction #{$transaction->id}",
                $locationBefore,
            );

            return $transaction;
        });
    }

    public function returnManualIssue(int $transactionId, int $actorId, ?string $notes): ?string
    {
        return DB::transaction(function () use ($transactionId, $actorId, $notes): ?string {
            $transaction = Transaction::whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $transaction->manual_recipient_name
                || ! $transaction->expected_return_date
                || $transaction->status !== 'assigned'
            ) {
                return 'Only active manual issues with an expected return date can be returned.';
            }

            $inventoryItem = Inventory::whereKey($transaction->item_id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($inventoryItem->status, ['available', 'assigned'], true)) {
                return 'The inventory record cannot be returned from its current status.';
            }

            $quantityBefore = (int) $inventoryItem->quantity;
            $quantityAfter = $quantityBefore + (int) $transaction->quantity;
            $locationBefore = $inventoryItem->only(['building', 'room']);
            $inventoryItem->update([
                'quantity' => $quantityAfter,
                'status' => 'available',
                'assigned_to_user_id' => null,
                'building' => $transaction->from_building ?? $inventoryItem->building,
                'room' => $transaction->from_room ?? $inventoryItem->room,
            ]);
            $transaction->update([
                'status' => 'returned',
                'return_date' => now()->toDateString(),
            ]);

            $this->recordMovement(
                $inventoryItem->item_id,
                $actorId,
                'returned',
                (int) $transaction->quantity,
                $quantityBefore,
                $quantityAfter,
                'transaction',
                $transaction->id,
                "Manual return from {$transaction->manual_recipient_name}; " . ($notes ?? 'Item received by property custodian'),
                $locationBefore,
            );

            return null;
        });
    }

    public function approveAssignment(int $assignmentRequestId, int $actorId, array $selectedInventoryIds = []): string
    {
        return DB::transaction(function () use ($assignmentRequestId, $actorId, $selectedInventoryIds): string {
            $assignmentRequest = AssignmentRequest::whereKey($assignmentRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignmentRequest->status !== 'waiting for approval') {
                return 'processed';
            }
            if ((int) $assignmentRequest->quantity < 1) {
                return 'insufficient';
            }

            if (! $assignmentRequest->item_id) {
                return $this->approveItemTypeRequest($assignmentRequest, $actorId, $selectedInventoryIds);
            }

            $inventoryItem = Inventory::with('category')->whereKey($assignmentRequest->item_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($inventoryItem->status !== 'available' || $assignmentRequest->quantity > $inventoryItem->quantity) {
                return 'insufficient';
            }
            if (
                $inventoryItem->category?->requires_serial_number
                && ((int) $assignmentRequest->quantity !== 1
                    || (int) $inventoryItem->quantity !== 1
                    || ! $inventoryItem->serial_number)
            ) {
                return 'invalid_allocation';
            }

            $assignmentRequest->load('targetUser.role');
            $assigneeId = strtolower((string) $assignmentRequest->targetUser?->role?->role_name) === 'end user'
                ? $assignmentRequest->target_user_id
                : $assignmentRequest->user_id;
            $assignee = User::query()->whereKey($assigneeId)->firstOrFail();
            $quantityBefore = (int) $inventoryItem->quantity;
            $quantityAfter = $quantityBefore - (int) $assignmentRequest->quantity;
            $locationBefore = $inventoryItem->only(['building', 'room']);
            $targetBuilding = $assignee->building ?? $assignmentRequest->building ?? $inventoryItem->building;
            $targetRoom = $assignee->room ?? $assignmentRequest->room ?? $inventoryItem->room;
            $inventoryItem->quantity = $quantityAfter;
            $inventoryItem->building = $targetBuilding;
            $inventoryItem->room = $targetRoom;
            if ($quantityAfter === 0) {
                $inventoryItem->status = 'assigned';
                $inventoryItem->assigned_to_user_id = $assigneeId;
            }
            $inventoryItem->save();

            $transaction = Transaction::create([
                'item_id' => $assignmentRequest->item_id,
                'from_user_id' => $assignmentRequest->user_id,
                'user_id' => $assigneeId,
                'quantity' => $assignmentRequest->quantity,
                'issued_quantity' => $assignmentRequest->quantity,
                'from_building' => $locationBefore['building'],
                'from_room' => $locationBefore['room'],
                'building' => $targetBuilding,
                'room' => $targetRoom,
                'transaction_date' => now(),
                'status' => 'assigned',
            ]);

            $assignmentRequest->update([
                'transaction_id' => $transaction->id,
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            $this->recordMovement(
                $inventoryItem->item_id,
                $actorId,
                'assignment',
                (int) $assignmentRequest->quantity,
                $quantityBefore,
                $quantityAfter,
                'assignment_request',
                $assignmentRequest->id,
                "Assigned from user #{$assignmentRequest->user_id} to user #{$assigneeId}; transaction #{$transaction->id}",
                $locationBefore,
            );

            return 'approved';
        });
    }

    private function approveItemTypeRequest(AssignmentRequest $assignmentRequest, int $actorId, array $selectedInventoryIds): string
    {
        if ((int) $assignmentRequest->quantity < 1) {
            return 'insufficient';
        }

        $selectedCount = count($selectedInventoryIds);
        $selectedInventoryIds = collect($selectedInventoryIds)
            ->map(fn ($inventoryId): int => (int) $inventoryId)
            ->unique()
            ->values();

        if ($selectedInventoryIds->isEmpty() || $selectedInventoryIds->count() !== $selectedCount) {
            return 'invalid_allocation';
        }

        $inventoryItems = Inventory::query()
            ->whereIn('item_id', $selectedInventoryIds)
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('item_id');

        if ($inventoryItems->count() !== $selectedInventoryIds->count()) {
            return 'invalid_allocation';
        }

        $category = Category::find($assignmentRequest->requested_category_id);
        if (! $category) {
            return 'invalid_allocation';
        }

        foreach ($inventoryItems as $item) {
            if (
                $item->item_name !== $assignmentRequest->requested_item_name
                || (int) $item->category_id !== (int) $assignmentRequest->requested_category_id
                || $item->unit !== $assignmentRequest->requested_unit
            ) {
                return 'invalid_allocation';
            }

            if (
                $item->status !== 'available'
                || (int) $item->quantity < 1
            ) {
                return 'insufficient';
            }

            if ($category->requires_serial_number && ((int) $item->quantity !== 1 || ! $item->serial_number)) {
                return 'invalid_allocation';
            }
        }

        if ($category->requires_serial_number && $selectedInventoryIds->count() !== (int) $assignmentRequest->quantity) {
            return 'invalid_allocation';
        }
        if ($inventoryItems->sum('quantity') < (int) $assignmentRequest->quantity) {
            return 'insufficient';
        }

        $assignee = User::query()->whereKey($assignmentRequest->user_id)->firstOrFail();
        $remainingQuantity = (int) $assignmentRequest->quantity;
        $allocationPlan = [];
        foreach ($inventoryItems as $item) {
            if ($remainingQuantity === 0) {
                break;
            }

            $quantity = $category->requires_serial_number
                ? 1
                : min($remainingQuantity, (int) $item->quantity);
            $allocationPlan[] = [$item, $quantity];
            $remainingQuantity -= $quantity;
        }

        if ($remainingQuantity > 0) {
            return 'insufficient';
        }
        if (count($allocationPlan) !== $inventoryItems->count()) {
            return 'invalid_allocation';
        }

        $firstTransactionId = null;
        $now = now();
        foreach ($allocationPlan as [$item, $quantity]) {
            $quantityBefore = (int) $item->quantity;
            $quantityAfter = $quantityBefore - $quantity;
            $locationBefore = $item->only(['building', 'room']);
            $targetBuilding = $assignee->building ?? $assignmentRequest->building ?? $item->building;
            $targetRoom = $assignee->room ?? $assignmentRequest->room ?? $item->room;
            $item->quantity = $quantityAfter;
            $item->building = $targetBuilding;
            $item->room = $targetRoom;
            if ($quantityAfter === 0) {
                $item->status = 'assigned';
                $item->assigned_to_user_id = $assignmentRequest->user_id;
            }
            $item->save();

            $transaction = Transaction::create([
                'item_id' => $item->item_id,
                'from_user_id' => $actorId,
                'user_id' => $assignmentRequest->user_id,
                'quantity' => $quantity,
                'issued_quantity' => $quantity,
                'from_building' => $locationBefore['building'],
                'from_room' => $locationBefore['room'],
                'building' => $targetBuilding,
                'room' => $targetRoom,
                'transaction_date' => $now,
                'status' => 'assigned',
            ]);
            $firstTransactionId ??= $transaction->id;

            AssignmentRequest::create([
                'item_id' => $item->item_id,
                'user_id' => $actorId,
                'target_user_id' => $assignmentRequest->user_id,
                'building' => $targetBuilding,
                'room' => $targetRoom,
                'transaction_id' => $transaction->id,
                'parent_request_id' => $assignmentRequest->id,
                'quantity' => $quantity,
                'status' => 'approved',
                'notes' => $assignmentRequest->notes,
                'requested_at' => $assignmentRequest->requested_at,
                'responded_at' => $now,
            ]);

            $this->recordMovement(
                $item->item_id,
                $actorId,
                'assignment',
                $quantity,
                $quantityBefore,
                $quantityAfter,
                'assignment_request',
                $assignmentRequest->id,
                "Request #{$assignmentRequest->id} assigned inventory #{$item->item_id} to user #{$assignmentRequest->user_id}; transaction #{$transaction->id}",
                $locationBefore,
            );
        }

        $assignmentRequest->update([
            'transaction_id' => $firstTransactionId,
            'status' => 'approved',
            'responded_at' => $now,
        ]);

        return 'approved';
    }

    public function acceptAssignment(int $assignmentRequestId, int $actorId): string
    {
        return DB::transaction(function () use ($assignmentRequestId, $actorId): string {
            $assignment = AssignmentRequest::whereKey($assignmentRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== 'waiting for approval') {
                return 'processed';
            }

            if ((int) $assignment->quantity < 1) {
                return 'insufficient';
            }

            $inventory = Inventory::with('category')->whereKey($assignment->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventory->status !== 'available' || $assignment->quantity > $inventory->quantity) {
                return 'insufficient';
            }
            if (
                $inventory->category?->requires_serial_number
                && ((int) $assignment->quantity !== 1
                    || (int) $inventory->quantity !== 1
                    || ! $inventory->serial_number)
            ) {
                return 'insufficient';
            }

            $locationBefore = $inventory->only(['building', 'room']);
            $recipient = User::query()->whereKey($assignment->target_user_id)->firstOrFail();
            $targetBuilding = $recipient->building ?? $assignment->building ?? $inventory->building;
            $targetRoom = $recipient->room ?? $assignment->room ?? $inventory->room;
            $transaction = Transaction::create([
                'item_id' => $assignment->item_id,
                'from_user_id' => $assignment->user_id,
                'user_id' => $assignment->target_user_id,
                'quantity' => $assignment->quantity,
                'issued_quantity' => $assignment->quantity,
                'from_building' => $locationBefore['building'],
                'from_room' => $locationBefore['room'],
                'building' => $targetBuilding,
                'room' => $targetRoom,
                'transaction_date' => $assignment->requested_at ?? now(),
                'status' => 'assigned',
            ]);

            $assignment->update([
                'transaction_id' => $transaction->id,
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            $quantityBefore = (int) $inventory->quantity;
            $quantityAfter = $quantityBefore - (int) $assignment->quantity;
            $inventory->building = $targetBuilding;
            $inventory->room = $targetRoom;
            $inventory->quantity = $quantityAfter;
            if ($quantityAfter === 0) {
                $inventory->status = 'assigned';
                $inventory->assigned_to_user_id = $assignment->target_user_id;
            }
            $inventory->save();

            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'assignment',
                (int) $assignment->quantity,
                $quantityBefore,
                $quantityAfter,
                'transaction',
                $transaction->id,
                "Assigned from user #{$assignment->user_id} to user #{$assignment->target_user_id}; request #{$assignment->id}",
                $locationBefore,
            );

            return 'accepted';
        });
    }

    public function approveTransfer(int $transferRequestId, int $actorId): bool
    {
        return DB::transaction(function () use ($transferRequestId, $actorId): bool {
            $transferRequest = AssignmentRequest::whereKey($transferRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transferRequest->status !== 'waiting for custodian approval' || (int) $transferRequest->quantity < 1) {
                return false;
            }

            $senderId = (int) $transferRequest->user_id;
            $recipientId = (int) $transferRequest->target_user_id;
            $recipient = User::query()->whereKey($recipientId)->firstOrFail();
            $inventory = Inventory::whereKey($transferRequest->item_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locationBefore = $inventory->only(['building', 'room']);
            $targetBuilding = $recipient->building ?? $transferRequest->building ?? $inventory->building;
            $targetRoom = $recipient->room ?? $transferRequest->room ?? $inventory->room;
            $originalAssignment = AssignmentRequest::query()
                ->where('item_id', $transferRequest->item_id)
                ->where(function ($query) use ($senderId) {
                    $query->where('user_id', $senderId)->orWhere('target_user_id', $senderId);
                })
                ->whereIn('status', ['approved', 'accepted', 'transfer pending'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $originalAssignment) {
                return false;
            }

            $originTransaction = $originalAssignment->transaction_id
                ? Transaction::whereKey($originalAssignment->transaction_id)->lockForUpdate()->first()
                : null;
            if ($originTransaction && (int) $originTransaction->quantity < (int) $transferRequest->quantity) {
                return false;
            }

            if ($originTransaction) {
                if ($originTransaction->quantity <= $transferRequest->quantity) {
                    $originTransaction->update([
                        'quantity' => 0,
                        'status' => 'transferred',
                    ]);
                } else {
                    $originTransaction->decrement('quantity', $transferRequest->quantity);
                }
            }

            if ($originalAssignment->status === 'transfer pending') {
                $originalAssignment->update([
                    'status' => 'transferred',
                    'responded_at' => now(),
                ]);
                $inventory->update([
                    'assigned_to_user_id' => $recipientId,
                    'status' => 'assigned',
                    'building' => $targetBuilding,
                    'room' => $targetRoom,
                ]);
            } else {
                $inventory->update(['building' => $targetBuilding, 'room' => $targetRoom]);
            }

            $transaction = Transaction::create([
                'item_id' => $transferRequest->item_id,
                'from_user_id' => $senderId,
                'user_id' => $recipientId,
                'quantity' => $transferRequest->quantity,
                'issued_quantity' => $transferRequest->quantity,
                'from_building' => $locationBefore['building'],
                'from_room' => $locationBefore['room'],
                'building' => $targetBuilding,
                'room' => $targetRoom,
                'transaction_date' => now(),
                'status' => 'assigned',
            ]);
            $transferRequest->update([
                'status' => 'approved',
                'transaction_id' => $transaction->id,
                'responded_at' => now(),
            ]);

            $quantity = (int) $inventory->quantity;
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'transfer',
                (int) $transferRequest->quantity,
                $quantity,
                $quantity,
                'assignment_request',
                $transferRequest->id,
                "Transferred from user #{$senderId} to user #{$recipientId}; transaction #{$transaction->id}",
                $locationBefore,
            );

            return true;
        });
    }

    public function requestTransfer(
        int $originalAssignmentId,
        int $itemId,
        int $senderId,
        int $recipientId,
        int $quantity,
        ?string $notes,
    ): string {
        if ($quantity < 1 || $senderId === $recipientId) {
            return 'invalid';
        }

        return DB::transaction(function () use ($originalAssignmentId, $itemId, $senderId, $recipientId, $quantity, $notes): string {
            $recipient = User::query()->whereKey($recipientId)->firstOrFail();
            $original = AssignmentRequest::whereKey($originalAssignmentId)
                ->with('targetUser.role')
                ->lockForUpdate()
                ->firstOrFail();
            $senderOwnsAssignment = (int) $original->target_user_id === $senderId
                || ((int) $original->user_id === $senderId
                    && $original->targetUser?->role?->role_name === 'Property Custodian');

            if (! $senderOwnsAssignment || (int) $original->item_id !== $itemId || ! in_array($original->status, ['approved', 'accepted'], true)) {
                return 'invalid';
            }
            if ($quantity > (int) $original->quantity) {
                return 'insufficient';
            }

            if ($quantity === (int) $original->quantity) {
                $original->update(['status' => 'transfer pending']);
            } else {
                $original->decrement('quantity', $quantity);
            }

            AssignmentRequest::create([
                'item_id' => $itemId,
                'user_id' => $senderId,
                'target_user_id' => $recipientId,
                'building' => $recipient->building,
                'room' => $recipient->room,
                'quantity' => $quantity,
                'status' => 'waiting for transfer approval',
                'notes' => $notes,
                'requested_at' => now(),
            ]);

            return 'created';
        });
    }

    public function acceptTransferRequest(int $transferRequestId): bool
    {
        return DB::transaction(function () use ($transferRequestId): bool {
            $transferRequest = AssignmentRequest::whereKey($transferRequestId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($transferRequest->status !== 'waiting for transfer approval') {
                return false;
            }

            $transferRequest->update([
                'status' => 'waiting for custodian approval',
                'responded_at' => now(),
            ]);

            return true;
        });
    }

    public function declineTransferRequest(int $transferRequestId, ?string $notes): bool
    {
        return DB::transaction(function () use ($transferRequestId, $notes): bool {
            $transferRequest = AssignmentRequest::whereKey($transferRequestId)
                ->lockForUpdate()
                ->firstOrFail();
            if (! in_array($transferRequest->status, ['waiting for transfer approval', 'waiting for custodian approval'], true)) {
                return false;
            }

            $senderId = (int) $transferRequest->user_id;
            $originalAssignment = AssignmentRequest::query()
                ->where('item_id', $transferRequest->item_id)
                ->where(function ($query) use ($senderId) {
                    $query->where('target_user_id', $senderId)
                        ->orWhere(function ($query) use ($senderId) {
                            $query->where('user_id', $senderId)
                                ->whereHas('targetUser.role', fn ($role) => $role->where('role_name', 'Property Custodian'));
                        });
                })
                ->whereIn('status', ['approved', 'accepted', 'transfer pending'])
                ->lockForUpdate()
                ->first();

            if ($originalAssignment?->status === 'transfer pending') {
                $originalAssignment->update(['status' => 'approved']);
            } elseif ($originalAssignment) {
                $originalAssignment->increment('quantity', $transferRequest->quantity);
            }

            $transferRequest->update([
                'status' => 'declined',
                'notes' => $notes ?? $transferRequest->notes,
                'responded_at' => now(),
            ]);

            return true;
        });
    }

    public function approveReturn(int $returnRequestId, int $actorId, array $storageLocation = []): bool
    {
        return DB::transaction(function () use ($returnRequestId, $actorId, $storageLocation): bool {
            $returnRequest = AssignmentRequest::whereKey($returnRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($returnRequest->status !== 'waiting for custodian approval' || (int) $returnRequest->quantity < 1) {
                return false;
            }

            $assignment = null;
            if ($returnRequest->transaction_id) {
                $assignment = AssignmentRequest::query()
                    ->where('transaction_id', $returnRequest->transaction_id)
                    ->where('item_id', $returnRequest->item_id)
                    ->where('target_user_id', $returnRequest->user_id)
                    ->whereIn('status', ['approved', 'accepted'])
                    ->lockForUpdate()
                    ->first();
            } else {
                $assignments = AssignmentRequest::query()
                    ->where('item_id', $returnRequest->item_id)
                    ->where('target_user_id', $returnRequest->user_id)
                    ->whereIn('status', ['approved', 'accepted'])
                    ->where('quantity', '>=', $returnRequest->quantity)
                    ->orderByDesc('responded_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                if ($assignments->count() > 1) {
                    return false;
                }

                $assignment = $assignments->first();
            }

            $transactionId = $returnRequest->transaction_id ?: $assignment?->transaction_id;
            if (! $transactionId && $assignment) {
                $transaction = Transaction::create([
                    'item_id' => $assignment->item_id,
                    'from_user_id' => $assignment->user_id,
                    'user_id' => $assignment->target_user_id,
                    'quantity' => $assignment->quantity,
                    'issued_quantity' => $assignment->quantity,
                    'transaction_date' => $assignment->responded_at ?? $assignment->requested_at ?? now(),
                    'status' => 'assigned',
                ]);
                $assignment->update(['transaction_id' => $transaction->id]);
                $transactionId = $transaction->id;
            }

            if (! $transactionId) {
                $transactions = Transaction::query()
                    ->where('item_id', $returnRequest->item_id)
                    ->where('user_id', $returnRequest->user_id)
                    ->where('status', 'assigned')
                    ->where('quantity', '>=', $returnRequest->quantity)
                    ->lockForUpdate()
                    ->get();

                if ($transactions->count() > 1) {
                    return false;
                }

                $transactionId = $transactions->first()?->id;
            }

            if (! $transactionId) {
                $inventory = Inventory::whereKey($returnRequest->item_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ((int) $inventory->assigned_to_user_id !== (int) $returnRequest->user_id) {
                    return false;
                }

                $transaction = Transaction::create([
                    'item_id' => $inventory->item_id,
                    'from_user_id' => $returnRequest->target_user_id,
                    'user_id' => $returnRequest->user_id,
                    'quantity' => $returnRequest->quantity,
                    'issued_quantity' => $returnRequest->quantity,
                    'transaction_date' => $returnRequest->requested_at ?? now(),
                    'status' => 'assigned',
                ]);
                $transactionId = $transaction->id;
            }

            $result = $this->applyAssignmentReturn(
                (int) $transactionId,
                (int) $returnRequest->quantity,
                $actorId,
                $returnRequest->notes,
                $returnRequest->id,
                $storageLocation ?: $returnRequest->only(['building', 'room']),
            );

            return $result instanceof AssignmentReturn;
        });
    }

    public function receiveAssignmentReturn(
        int $transactionId,
        int $quantity,
        int $actorId,
        ?string $notes,
        ?int $returnRequestId = null,
        array $storageLocation = [],
    ): AssignmentReturn|string
    {
        return DB::transaction(fn (): AssignmentReturn|string => $this->applyAssignmentReturn(
            $transactionId,
            $quantity,
            $actorId,
            $notes,
            $returnRequestId,
            $storageLocation,
        ));
    }

    public function receiveAssignmentReturns(array $transactionIds, int $actorId): array|string
    {
        $transactionIds = array_map('intval', $transactionIds);
        $uniqueTransactionIds = array_values(array_unique($transactionIds));
        sort($uniqueTransactionIds);

        if ($uniqueTransactionIds === [] || count($uniqueTransactionIds) !== count($transactionIds) || min($uniqueTransactionIds) < 1) {
            return 'Select one or more valid assignments to receive.';
        }

        try {
            return DB::transaction(function () use ($uniqueTransactionIds, $actorId): array {
                $transactionHeaders = Transaction::query()
                    ->whereIn('id', $uniqueTransactionIds)
                    ->get(['id', 'item_id']);
                if ($transactionHeaders->count() !== count($uniqueTransactionIds)) {
                    throw new \DomainException('One or more selected assignments no longer exist.');
                }

                $inventoryIds = $transactionHeaders->pluck('item_id')->unique()->sort()->values();
                $lockedInventoryIds = Inventory::query()
                    ->whereIn('item_id', $inventoryIds)
                    ->orderBy('item_id')
                    ->lockForUpdate()
                    ->pluck('item_id');
                if ($lockedInventoryIds->count() !== $inventoryIds->count()) {
                    throw new \DomainException('One or more selected inventory records no longer exist.');
                }

                $transactions = Transaction::query()
                    ->whereIn('id', $uniqueTransactionIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $results = [];
                foreach ($transactions as $transaction) {
                    $pendingReturn = AssignmentRequest::query()
                        ->where('transaction_id', $transaction->id)
                        ->where('item_id', $transaction->item_id)
                        ->where('user_id', $transaction->user_id)
                        ->where('target_user_id', $actorId)
                        ->where('status', 'waiting for custodian approval')
                        ->lockForUpdate()
                        ->first();
                    $quantity = (int) ($pendingReturn?->quantity ?? $transaction->quantity);

                    $result = $this->applyAssignmentReturn(
                        (int) $transaction->id,
                        $quantity,
                        $actorId,
                        null,
                        $pendingReturn?->id,
                    );
                    if (is_string($result)) {
                        throw new \DomainException($result);
                    }

                    $results[] = $result;
                }

                return $results;
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }
    }

    private function applyAssignmentReturn(
        int $transactionId,
        int $quantity,
        int $actorId,
        ?string $notes,
        ?int $returnRequestId = null,
        array $storageLocation = [],
    ): AssignmentReturn|string {
        $transaction = Transaction::whereKey($transactionId)
            ->lockForUpdate()
            ->firstOrFail();
        if ($transaction->status !== 'assigned' || ! $transaction->user_id || (int) $transaction->quantity < 1) {
            return 'This assignment is no longer active.';
        }
        if ($quantity < 1 || $quantity > (int) $transaction->quantity) {
            return 'Return quantity must be between 1 and the remaining assigned quantity.';
        }

        $inventory = Inventory::with('category')
            ->whereKey($transaction->item_id)
            ->lockForUpdate()
            ->firstOrFail();
        if (! in_array($inventory->status, ['available', 'assigned'], true) || (int) $inventory->quantity < 0) {
            return 'The inventory record cannot receive a return in its current state.';
        }

        $assignment = AssignmentRequest::query()
            ->where('transaction_id', $transaction->id)
            ->where('item_id', $inventory->item_id)
            ->where('target_user_id', $transaction->user_id)
            ->whereIn('status', ['approved', 'accepted'])
            ->lockForUpdate()
            ->first();
        if ($assignment && (int) $assignment->quantity !== (int) $transaction->quantity) {
            return 'The assignment and transaction quantities do not match. Review the assignment before receiving this return.';
        }
        if ($assignment && $quantity > (int) $assignment->quantity) {
            return 'Return quantity exceeds the remaining assigned quantity.';
        }

        if ($inventory->category?->requires_serial_number && (
            $quantity !== 1
            || (int) $transaction->quantity !== 1
            || ! $inventory->serial_number
            || $inventory->status !== 'assigned'
            || (int) $inventory->assigned_to_user_id !== (int) $transaction->user_id
        )) {
            return 'Serialized assets must be returned as their exact assigned asset with quantity 1.';
        }

        if ($returnRequestId) {
            $returnRequest = AssignmentRequest::whereKey($returnRequestId)
                ->lockForUpdate()
                ->firstOrFail();
            if (
                $returnRequest->status !== 'waiting for custodian approval'
                || (int) $returnRequest->item_id !== (int) $inventory->item_id
                || (int) $returnRequest->user_id !== (int) $transaction->user_id
                || (int) $returnRequest->quantity < $quantity
                || ($returnRequest->transaction_id && (int) $returnRequest->transaction_id !== (int) $transaction->id)
            ) {
                return 'This return request no longer matches the selected assignment.';
            }
        } else {
            $returnRequest = null;
        }

        $quantityBefore = (int) $inventory->quantity;
        $quantityAfter = $quantityBefore + $quantity;
        $locationBefore = $inventory->only(['building', 'room']);
        $storageBuilding = $storageLocation['building'] ?? $returnRequest?->building ?? $transaction->from_building ?? $inventory->building;
        $storageRoom = $storageLocation['room'] ?? $returnRequest?->room ?? $transaction->from_room ?? $inventory->room;
        $remainingAssigned = (int) $transaction->quantity - $quantity;
        $returnedAt = now();

        $transaction->update([
            'quantity' => $remainingAssigned,
            'status' => $remainingAssigned === 0 ? 'returned' : 'assigned',
            'return_date' => $remainingAssigned === 0 ? $returnedAt->toDateString() : null,
        ]);

        if ($assignment) {
            $assignment->update([
                'quantity' => $remainingAssigned,
                'status' => $remainingAssigned === 0 ? 'returned' : $assignment->status,
                'responded_at' => $remainingAssigned === 0 ? $returnedAt : $assignment->responded_at,
            ]);
        }

        $inventory->update([
            'quantity' => $quantityAfter,
            'status' => $quantityAfter > 0 ? 'available' : 'assigned',
            'assigned_to_user_id' => $quantityAfter > 0 ? null : $this->remainingInventoryAssignee($inventory->item_id),
            'building' => $storageBuilding,
            'room' => $storageRoom,
        ]);

        $assignmentReturn = AssignmentReturn::create([
            'transaction_id' => $transaction->id,
            'assignment_request_id' => $assignment?->id,
            'return_request_id' => $returnRequest?->id,
            'inventory_id' => $inventory->item_id,
            'building' => $storageBuilding,
            'room' => $storageRoom,
            'recipient_id' => $transaction->user_id,
            'received_by' => $actorId,
            'quantity' => $quantity,
            'notes' => $notes,
            'returned_at' => $returnedAt,
        ]);

        if ($returnRequest) {
            $requestedRemaining = (int) $returnRequest->quantity - $quantity;
            $returnRequest->update([
                'transaction_id' => $transaction->id,
                'quantity' => $requestedRemaining,
                'status' => $requestedRemaining === 0 ? 'approved' : 'waiting for custodian approval',
                'responded_at' => $requestedRemaining === 0 ? $returnedAt : $returnRequest->responded_at,
            ]);
        }

        $this->recordMovement(
            $inventory->item_id,
            $actorId,
            'returned',
            $quantity,
            $quantityBefore,
            $quantityAfter,
            'assignment_return',
            $assignmentReturn->id,
            "Returned {$quantity} {$inventory->unit} from user #{$transaction->user_id} to user #{$actorId}; assignment transaction #{$transaction->id}. " . ($notes ?? 'Item received by property custodian.'),
            $locationBefore,
        );

        return $assignmentReturn;
    }

    private function remainingInventoryAssignee(int $inventoryId): ?int
    {
        $recipientIds = Transaction::query()
            ->where('item_id', $inventoryId)
            ->where('status', 'assigned')
            ->where('quantity', '>', 0)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return $recipientIds->count() === 1 ? (int) $recipientIds->first() : null;
    }

    public function stockIn(array $attributes): Inventory
    {
        return DB::transaction(function () use ($attributes): Inventory {
            $inventory = Inventory::create($attributes);
            $quantity = (int) ($attributes['quantity'] ?? 0);

            if ($quantity > 0) {
                $this->recordMovement(
                    $inventory->item_id,
                    isset($attributes['user_id']) ? (int) $attributes['user_id'] : null,
                    'stock_in',
                    $quantity,
                    0,
                    $quantity,
                    'inventory',
                    $inventory->item_id,
                    $attributes['description'] ?? 'Stock-in',
                    ['building' => null, 'room' => null],
                );
            }

            $category = Category::find($attributes['category_id'] ?? null);
            $qrCode = null;
            if ($category?->requires_qr_code) {
                do {
                    $qrCode = 'dnhs_qr_' . Str::lower(Str::random(24));
                } while (Inventory::where('qr_code', $qrCode)->exists());
            }

            $inventory->update([
                'inventory_item_no' => sprintf('INV-%06d', $inventory->item_id),
                'qr_code' => $qrCode,
            ]);

            return $inventory;
        });
    }

    public function updateAvailableInventory(
        int $itemId,
        array $attributes,
        int $quantity,
        bool $isSerialized,
        array $groupItemIds,
        int $actorId,
        ?array $serialNumbers = null,
    ): ?string {
        return DB::transaction(function () use ($itemId, $attributes, $quantity, $isSerialized, $groupItemIds, $actorId, $serialNumbers): ?string {
            $items = Inventory::query()
                ->whereIn('item_id', $groupItemIds)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty() || $items->contains(fn (Inventory $item) => $item->status !== 'available')) {
                return 'Only available inventory can be updated.';
            }

            if ($isSerialized) {
                foreach ($items as $index => $item) {
                    $locationBefore = $item->only(['building', 'room']);
                    $locationChanged = (
                        array_key_exists('building', $attributes)
                        && $attributes['building'] !== $item->building
                    ) || (
                        array_key_exists('room', $attributes)
                        && $attributes['room'] !== $item->room
                    );
                    $itemAttributes = $attributes;
                    if ($serialNumbers !== null && array_key_exists($index, $serialNumbers)) {
                        $itemAttributes['serial_number'] = $serialNumbers[$index];
                    }
                    $item->update($itemAttributes);
                    if ($locationChanged) {
                        $quantity = (int) $item->quantity;
                        $this->recordMovement(
                            $item->item_id,
                            $actorId,
                            'relocated',
                            0,
                            $quantity,
                            $quantity,
                            'inventory',
                            $item->item_id,
                            'Inventory location updated',
                            $locationBefore,
                        );
                    }
                }
                return null;
            }

            $inventory = $items->firstWhere('item_id', $itemId);
            if (! $inventory || $quantity < 0) {
                return 'Inventory quantity is invalid.';
            }

            $quantityBefore = (int) $inventory->quantity;
            $locationBefore = $inventory->only(['building', 'room']);
            $locationChanged = (
                array_key_exists('building', $attributes)
                && $attributes['building'] !== $inventory->building
            ) || (
                array_key_exists('room', $attributes)
                && $attributes['room'] !== $inventory->room
            );
            $inventory->update([...$attributes, 'quantity' => $quantity]);
            $quantityDelta = $quantity - $quantityBefore;

            if ($quantityDelta !== 0 || $locationChanged) {
                $this->recordMovement(
                    $inventory->item_id,
                    $actorId,
                    $quantityDelta === 0 ? 'relocated' : ($quantityDelta > 0 ? 'stock_in' : 'stock_out'),
                    abs($quantityDelta),
                    $quantityBefore,
                    $quantity,
                    'inventory',
                    $inventory->item_id,
                    $quantityDelta === 0 ? 'Inventory location updated' : "Available quantity edited from {$quantityBefore} to {$quantity}",
                    $locationBefore,
                );
            }

            return null;
        });
    }

    public function markReturned(int $itemId, int $actorId, ?string $notes): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $notes): ?string {
            $inventory = Inventory::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'assigned' || ! $inventory->assigned_to_user_id) {
                return 'Only assigned items can be marked as returned.';
            }

            $sourceUserId = (int) $inventory->assigned_to_user_id;
            $transaction = Transaction::query()
                ->where('item_id', $inventory->item_id)
                ->where('user_id', $sourceUserId)
                ->where('status', 'assigned')
                ->latest('transaction_date')
                ->lockForUpdate()
                ->first();
            $quantityBefore = (int) $inventory->quantity;
            $returnedQuantity = (int) ($transaction?->quantity ?? $quantityBefore);
            $quantityAfter = $quantityBefore + $returnedQuantity;
            $locationBefore = $inventory->only(['building', 'room']);

            $inventory->update([
                'quantity' => $quantityAfter,
                'assigned_to_user_id' => null,
                'status' => 'available',
                'building' => $transaction?->from_building ?? $inventory->building,
                'room' => $transaction?->from_room ?? $inventory->room,
            ]);
            $transaction?->update([
                'status' => 'returned',
                'return_date' => now(),
            ]);

            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'returned',
                $returnedQuantity,
                $quantityBefore,
                $quantityAfter,
                'inventory',
                $inventory->item_id,
                "Returned from user #{$sourceUserId} to user #{$actorId}; " . ($notes ?? 'Item received in warehouse'),
                $locationBefore,
            );

            return null;
        });
    }

    public function sendToMaintenance(int $itemId, int $actorId, string $issue, ?string $notes): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $issue, $notes): ?string {
            $inventory = Inventory::with('category')->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'available') {
                return 'Only available items can be sent to maintenance.';
            }
            if ($inventory->category?->is_maintenance_eligible === false) {
                return 'Items in this category are not eligible for maintenance.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'under_maintenance']);
            $maintenanceRecord = MaintenanceRecord::create([
                'inventory_id' => $inventory->item_id,
                'reported_by' => $actorId,
                'status' => 'reported',
                'issue_description' => $issue,
                'started_at' => now(),
            ]);
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'maintenance',
                $quantity,
                $quantity,
                $quantity,
                'maintenance_record',
                $maintenanceRecord->id,
                $notes ?? $issue,
            );

            return null;
        });
    }

    public function sendItemsToMaintenance(array $itemIds, int $actorId, string $issue, ?string $notes): ?string
    {
        try {
            DB::transaction(function () use ($itemIds, $actorId, $issue, $notes): void {
                foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                    $error = $this->sendToMaintenance($itemId, $actorId, $issue, $notes);
                    if ($error !== null) {
                        throw new \DomainException($error);
                    }
                }
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function markRepaired(int $itemId, int $actorId, ?string $repairNotes, ?float $maintenanceCost): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $repairNotes, $maintenanceCost): ?string {
            $inventory = Inventory::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'under_maintenance') {
                return 'Item is not currently under maintenance.';
            }

            $maintenanceRecord = MaintenanceRecord::query()
                ->where('inventory_id', $inventory->item_id)
                ->whereIn('status', ['reported', 'in_progress'])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();
            if (! $maintenanceRecord) {
                return 'No active maintenance record was found.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'available']);
            $maintenanceRecord->update([
                'status' => 'completed',
                'repair_notes' => $repairNotes ?? $maintenanceRecord->repair_notes,
                'maintenance_cost' => $maintenanceCost ?? $maintenanceRecord->maintenance_cost,
                'completed_at' => now(),
            ]);
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'maintenance_completed',
                $quantity,
                $quantity,
                $quantity,
                'maintenance_record',
                $maintenanceRecord->id,
                $repairNotes ?? 'Item repaired and returned to inventory',
            );

            return null;
        });
    }

    public function markItemsRepaired(array $itemIds, int $actorId, ?string $repairNotes, ?float $maintenanceCost): ?string
    {
        try {
            DB::transaction(function () use ($itemIds, $actorId, $repairNotes, $maintenanceCost): void {
                foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                    $error = $this->markRepaired($itemId, $actorId, $repairNotes, $maintenanceCost);
                    if ($error !== null) {
                        throw new \DomainException($error);
                    }
                }
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function markReadyToDispose(int $itemId, int $actorId, string $notes): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $notes): ?string {
            $inventory = Inventory::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'under_maintenance') {
                return 'Only items under maintenance can be marked ready to dispose.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'ready_to_dispose']);
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'ready_to_dispose',
                $quantity,
                $quantity,
                $quantity,
                'inventory',
                $inventory->item_id,
                $notes,
            );

            return null;
        });
    }

    public function markItemsReadyToDispose(array $itemIds, int $actorId, string $notes): ?string
    {
        try {
            DB::transaction(function () use ($itemIds, $actorId, $notes): void {
                foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                    $error = $this->markReadyToDispose($itemId, $actorId, $notes);
                    if ($error !== null) {
                        throw new \DomainException($error);
                    }
                }
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function dispose(int $itemId, int $actorId, ?string $notes): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $notes): ?string {
            $inventory = Inventory::whereKey($itemId)->lockForUpdate()->firstOrFail();
            if (! in_array($inventory->status, ['available', 'ready_to_dispose'], true)) {
                return 'Only available or ready-to-dispose items can be disposed.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'disposed']);
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'disposed',
                $quantity,
                $quantity,
                0,
                'inventory',
                $inventory->item_id,
                $notes ?? 'Item disposed',
            );

            return null;
        });
    }

    public function disposeItems(array $itemIds, int $actorId, ?string $notes): ?string
    {
        try {
            DB::transaction(function () use ($itemIds, $actorId, $notes): void {
                foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                    $inventory = Inventory::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
                    if ($inventory->status !== 'ready_to_dispose') {
                        throw new \DomainException('Only items marked ready to dispose can be disposed.');
                    }

                    $error = $this->dispose($itemId, $actorId, $notes);
                    if ($error !== null) {
                        throw new \DomainException($error);
                    }
                }
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * Statuses a custodian may flag for inspection. Unlike maintenance this is not
     * gated on the category, because inspection is a verification step rather than
     * a repair.
     */
    public const INSPECTABLE_STATUSES = [
        'available',
        'assigned',
        'under_maintenance',
        'ready_to_dispose',
    ];

    public function sendToInspection(int $itemId, int $actorId, ?string $reason): ?string
    {
        return DB::transaction(function () use ($itemId, $actorId, $reason): ?string {
            $inventory = Inventory::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if (! in_array($inventory->status, self::INSPECTABLE_STATUSES, true)) {
                return 'Only available, assigned, under maintenance, or ready to dispose items can be sent to inspection.';
            }

            $statusBefore = $inventory->status;
            $quantity = $inventory->effectiveQuantity();

            $inspection = InspectionRecord::create([
                'inventory_id' => $inventory->item_id,
                'flagged_by' => $actorId,
                'status_before' => $statusBefore,
                'status' => 'flagged',
                'finding_notes' => $reason,
                'flagged_at' => now(),
            ]);

            $inventory->update(['status' => 'under_inspection']);

            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'inspection',
                $quantity,
                $quantity,
                $quantity,
                'inspection_record',
                $inspection->id,
                $reason,
            );

            return null;
        });
    }

    public function sendItemsToInspection(array $itemIds, int $actorId, ?string $reason): ?string
    {
        try {
            DB::transaction(function () use ($itemIds, $actorId, $reason): void {
                foreach (array_values(array_unique(array_map('intval', $itemIds))) as $itemId) {
                    $error = $this->sendToInspection($itemId, $actorId, $reason);
                    if ($error !== null) {
                        throw new \DomainException($error);
                    }
                }
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function markInspected(int $inspectionId, int $actorId, ?string $notes): ?string
    {
        return DB::transaction(function () use ($inspectionId, $actorId, $notes): ?string {
            $inspection = InspectionRecord::query()->whereKey($inspectionId)->lockForUpdate()->firstOrFail();
            if ($inspection->status !== 'flagged') {
                return 'This inspection record has already been completed.';
            }

            $inventory = Inventory::query()->whereKey($inspection->inventory_id)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'under_inspection') {
                return 'This item is no longer awaiting inspection.';
            }

            $statusBefore = in_array($inspection->status_before, self::INSPECTABLE_STATUSES, true)
                ? $inspection->status_before
                : 'available';

            $inventory->update(['status' => $statusBefore]);

            $inspection->update([
                'status' => 'inspected',
                'inspected_by' => $actorId,
                'finding_notes' => $notes ?? $inspection->finding_notes,
                'inspected_at' => now(),
            ]);

            $quantity = $inventory->effectiveQuantity();
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'inspection',
                $quantity,
                $quantity,
                $quantity,
                'inspection_record',
                $inspection->id,
                $notes,
            );

            return null;
        });
    }

    private function recordMovement(
        int $inventoryId,
        ?int $actorId,
        string $type,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes,
        ?array $locationBefore = null,
    ): StockMovement {
        $inventory = Inventory::findOrFail($inventoryId);
        $locationBefore ??= $inventory->only(['building', 'room']);

        return StockMovement::create([
            'inventory_id' => $inventoryId,
            'user_id' => $actorId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'from_building' => $locationBefore['building'] ?? null,
            'from_room' => $locationBefore['room'] ?? null,
            'to_building' => $inventory->building,
            'to_room' => $inventory->room,
            'notes' => $notes,
        ]);
    }
}