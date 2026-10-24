<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\AssignmentReturn;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryItemLog;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransferReleaseSlip;
use App\Models\User;
use App\Support\RequestableItemMatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryOperationService
{
    /**
     * Default deadline for registered assignments so due/overdue tracking
     * has data. Manual issues keep their optional custodian-set date.
     */
    public const DEFAULT_ASSIGNMENT_RETURN_DAYS = 365;

    private static function defaultExpectedReturnDate(Carbon|string $transactionDate): string
    {
        return Carbon::parse($transactionDate)->addDays(self::DEFAULT_ASSIGNMENT_RETURN_DAYS)->toDateString();
    }

    public function submitRegisteredAssignment(
        int $itemId,
        int $actorId,
        int $recipientId,
        int $quantity,
        ?string $transactionDate,
        ?string $expectedReturnDate = null,
    ): string {
        return DB::transaction(function () use ($itemId, $actorId, $recipientId, $quantity, $transactionDate, $expectedReturnDate): string {
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
            // Soft hold: earlier assignments awaiting acceptance do not deduct
            // stock, so a second assign of the same last units must be refused
            // here instead of silently queuing against nothing. Draft holds
            // promise units the same way and join the same pool.
            $pendingHold = (int) AssignmentRequest::query()
                ->whereIn('status', ['waiting for approval', 'draft'])
                ->where('item_id', $inventoryItem->item_id)
                ->sum('quantity');
            if ($quantity > max(0, (int) $inventoryItem->quantity - $pendingHold)) {
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
                'expected_return_date' => $expectedReturnDate,
            ]);

            return 'created';
        });
    }

    /**
     * Multi-line registered assignment: one recipient, N items, all-or-none.
     *
     * Every line is validated before anything is written (same guards as the
     * single-line path, plus same-item lines share one free-stock pool). On
     * any failure nothing is written and per-line errors are returned in
     * stockInImport shape: ['errors' => [lineIndex => [messages]]].
     *
     * @return array{requests?: AssignmentRequest[], errors?: array}
     */
    public function submitRegisteredAssignments(
        array $lines,
        int $actorId,
        int $recipientId,
        ?string $transactionDate,
        ?string $expectedReturnDate = null,
    ): array {
        return DB::transaction(function () use ($lines, $actorId, $recipientId, $transactionDate, $expectedReturnDate): array {
            $recipient = User::query()->whereKey($recipientId)->lockForUpdate()->firstOrFail();
            $ids = array_values(array_unique(array_map(
                fn ($line) => (int) ($line['item_id'] ?? 0),
                $lines,
            )));
            sort($ids);
            $items = Inventory::query()
                ->whereIn('item_id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            $errors = [];
            $promised = [];
            foreach (array_values($lines) as $index => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $quantity = $line['quantity'] ?? null;
                $item = $items->get($itemId);
                if (! $item) {
                    $errors[$index][] = 'The selected item is no longer available.';
                    continue;
                }
                if ($item->status !== 'available') {
                    $errors[$index][] = 'The selected item is not available for assignment.';
                    continue;
                }
                if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                    $errors[$index][] = 'Quantity must be a whole number of at least 1.';
                    continue;
                }
                $quantity = (int) $quantity;
                $pendingHold = (int) AssignmentRequest::query()
                    ->whereIn('status', ['waiting for approval', 'draft'])
                    ->where('item_id', $item->item_id)
                    ->sum('quantity');
                $free = max(0, (int) $item->quantity - $pendingHold);
                $already = $promised[$itemId] ?? 0;
                if ($quantity + $already > $free) {
                    $errors[$index][] = 'The requested quantity exceeds the free stock (available minus units already promised).';
                    continue;
                }
                $promised[$itemId] = $already + $quantity;
            }

            if ($errors !== []) {
                return ['errors' => $errors];
            }

            $created = [];
            foreach (array_values($lines) as $line) {
                $created[] = AssignmentRequest::create([
                    'item_id' => (int) $line['item_id'],
                    'user_id' => $actorId,
                    'target_user_id' => $recipientId,
                    'building' => $recipient->building,
                    'room' => $recipient->room,
                    'quantity' => (int) $line['quantity'],
                    'status' => 'waiting for approval',
                    'requested_at' => $transactionDate ? Carbon::parse($transactionDate) : now(),
                    'expected_return_date' => $expectedReturnDate,
                ]);
            }

            return ['requests' => $created];
        });
    }

    /**
     * Hold stock for a signed slip without moving custody.
     * Creates a `draft` parent (item-type summary, item_id null) plus one
     * `draft` child per inventory row. Nothing is deducted, no transaction or
     * movement is written — printable immediately, allocatable later. The
     * free-stock guards (available − waiting_for_approval − draft) treat a
     * second draft over the same units as insufficient, so double-drafting
     * the same stock fails here instead of double-promising it.
     *
     * @return AssignmentRequest|string the draft parent, or an error key.
     */
    public function createDraft(
        int $itemId,
        int $actorId,
        int $recipientId,
        int $quantity,
        ?string $transactionDate,
        ?string $expectedReturnDate = null,
    ): AssignmentRequest|string {
        return DB::transaction(function () use ($itemId, $actorId, $recipientId, $quantity, $transactionDate, $expectedReturnDate): AssignmentRequest|string {
            $recipient = User::query()->with('role')->whereKey($recipientId)->firstOrFail();
            if ($recipient->status !== 'active' || $recipient->role?->role_name !== 'End User') {
                return false;
            }
            $inventoryItem = Inventory::with('category')->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventoryItem->status !== 'available' || (int) $inventoryItem->quantity < 1) {
                return 'unavailable';
            }
            if ($quantity < 1) {
                return 'insufficient';
            }

            $held = (int) AssignmentRequest::query()
                ->whereIn('status', ['waiting for approval', 'draft'])
                ->where('item_id', $inventoryItem->item_id)
                ->sum('quantity');
            if ($quantity > max(0, (int) $inventoryItem->quantity - $held)) {
                return 'insufficient';
            }
            if ($inventoryItem->category?->requires_serial_number
                && ((int) $quantity !== 1 || (int) $inventoryItem->quantity !== 1 || ! $inventoryItem->serial_number)
            ) {
                return 'invalid_allocation';
            }

            $parent = AssignmentRequest::create([
                'user_id' => $actorId,
                'target_user_id' => $recipientId,
                'requested_item_name' => $inventoryItem->item_name,
                'requested_category_id' => $inventoryItem->category_id,
                'requested_unit' => $inventoryItem->unit,
                'building' => $recipient->building ?? $inventoryItem->building,
                'room' => $recipient->room ?? $inventoryItem->room,
                'quantity' => $quantity,
                'status' => 'draft',
                'requested_at' => $transactionDate ? Carbon::parse($transactionDate) : now(),
                'expected_return_date' => $expectedReturnDate,
            ]);
            AssignmentRequest::create([
                'user_id' => $actorId,
                'target_user_id' => $recipientId,
                'item_id' => $inventoryItem->item_id,
                'building' => $parent->building,
                'room' => $parent->room,
                'parent_request_id' => $parent->id,
                'quantity' => $quantity,
                'status' => 'draft',
                'requested_at' => $parent->requested_at,
                'expected_return_date' => $expectedReturnDate,
            ]);

            return $parent->fresh();
        });
    }

    /**
     * Multi-line draft hold: one slip, one parent, one `draft` child per line.
     *
     * Same guards as the single-line path per row (available, free stock
     * after holds, serial allocation), with same-item lines sharing one
     * free-stock pool. The parent carries a summary of the first line plus a
     * "+N more" suffix; custody math only ever reads the children.
     *
     * @return AssignmentRequest|array the draft parent, or ['errors' => [...]]
     */
    public function createDrafts(
        array $lines,
        int $actorId,
        int $recipientId,
        ?string $transactionDate,
        ?string $expectedReturnDate = null,
    ): AssignmentRequest|array {
        return DB::transaction(function () use ($lines, $actorId, $recipientId, $transactionDate, $expectedReturnDate): AssignmentRequest|array {
            $recipient = User::query()->whereKey($recipientId)->firstOrFail();
            $ids = array_values(array_unique(array_map(
                fn ($line) => (int) ($line['item_id'] ?? 0),
                $lines,
            )));
            sort($ids);
            $items = Inventory::with('category')
                ->whereIn('item_id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            $errors = [];
            $promised = [];
            $ordered = array_values($lines);
            foreach ($ordered as $index => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $quantity = $line['quantity'] ?? null;
                $item = $items->get($itemId);
                if (! $item) {
                    $errors[$index][] = 'The selected item is no longer available.';
                    continue;
                }
                if ($item->status !== 'available' || (int) $item->quantity < 1) {
                    $errors[$index][] = 'The selected item is not available for a draft hold.';
                    continue;
                }
                if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                    $errors[$index][] = 'Quantity must be a whole number of at least 1.';
                    continue;
                }
                $quantity = (int) $quantity;
                $held = (int) AssignmentRequest::query()
                    ->whereIn('status', ['waiting for approval', 'draft'])
                    ->where('item_id', $item->item_id)
                    ->sum('quantity');
                $already = $promised[$itemId] ?? 0;
                if ($quantity + $already > max(0, (int) $item->quantity - $held)) {
                    $errors[$index][] = 'The requested quantity exceeds the free stock (available minus units already promised).';
                    continue;
                }
                if ($item->category?->requires_serial_number
                    && ($quantity !== 1 || (int) $item->quantity !== 1 || ! $item->serial_number)
                ) {
                    $errors[$index][] = 'Serialized stock must be drafted as its exact individual asset with quantity 1.';
                    continue;
                }
                $promised[$itemId] = $already + $quantity;
            }

            if ($errors !== []) {
                return ['errors' => $errors];
            }

            $first = $items->get((int) $ordered[0]['item_id']);
            $totalQuantity = array_sum(array_map(fn ($line) => (int) $line['quantity'], $ordered));
            $parent = AssignmentRequest::create([
                'user_id' => $actorId,
                'target_user_id' => $recipientId,
                'requested_item_name' => count($ordered) > 1
                    ? $first->item_name . ' +' . (count($ordered) - 1) . ' more'
                    : $first->item_name,
                'requested_category_id' => $first->category_id,
                'requested_unit' => $first->unit,
                'building' => $recipient->building ?? $first->building,
                'room' => $recipient->room ?? $first->room,
                'quantity' => $totalQuantity,
                'status' => 'draft',
                'requested_at' => $transactionDate ? Carbon::parse($transactionDate) : now(),
                'expected_return_date' => $expectedReturnDate,
            ]);
            foreach ($ordered as $line) {
                AssignmentRequest::create([
                    'user_id' => $actorId,
                    'target_user_id' => $recipientId,
                    'item_id' => (int) $line['item_id'],
                    'building' => $parent->building,
                    'room' => $parent->room,
                    'parent_request_id' => $parent->id,
                    'quantity' => (int) $line['quantity'],
                    'status' => 'draft',
                    'requested_at' => $parent->requested_at,
                    'expected_return_date' => $expectedReturnDate,
                ]);
            }

            return $parent->fresh();
        });
    }

    /**
     * Allocate a draft: the single custody-writing step of the draft flow.
     *
     * Locks the parent, revalidates every child row against free stock
     * EXCLUDING this draft's own hold, then performs the same writes as the
     * direct approval path per row (deduct, assigned transaction, movement)
     * and flips parent and children to approved. Anything else holding the
     * units first makes this stale; an already-approved parent reports
     * processed so retries are safe.
     */
    public function allocateDraft(int $parentId, int $actorId): string
    {
        return DB::transaction(function () use ($parentId, $actorId): string {
            $parent = AssignmentRequest::whereKey($parentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($parent->status === 'approved') {
                return 'processed';
            }
            if ($parent->status !== 'draft') {
                return 'stale';
            }
            if ((int) $parent->quantity < 1) {
                return 'insufficient';
            }

            $children = AssignmentRequest::query()
                ->where('parent_request_id', $parent->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            if ($children->isEmpty()) {
                return 'stale';
            }

            $parent->load('targetUser.role');
            $assigneeId = strtolower((string) $parent->targetUser?->role?->role_name) === 'end user'
                ? $parent->target_user_id
                : $parent->user_id;
            $assignee = User::query()->whereKey($assigneeId)->firstOrFail();

            $now = now();
            $firstTransactionId = null;
            foreach ($children as $child) {
                $item = Inventory::with('category')->whereKey($child->item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($item->status !== 'available' || (int) $item->quantity < 1) {
                    return 'insufficient';
                }
                $totalHeld = (int) AssignmentRequest::query()
                    ->whereIn('status', ['waiting for approval', 'draft'])
                    ->where('item_id', $item->item_id)
                    ->sum('quantity');
                $ownHeld = (int) $children->where('item_id', $item->item_id)->sum('quantity');
                $free = max(0, (int) $item->quantity - ($totalHeld - $ownHeld));
                if ((int) $child->quantity < 1 || (int) $child->quantity > $free) {
                    return 'insufficient';
                }
                if ($item->category?->requires_serial_number
                    && ((int) $child->quantity !== 1 || (int) $item->quantity !== 1 || ! $item->serial_number)
                ) {
                    return 'invalid_allocation';
                }

                $quantityBefore = (int) $item->quantity;
                $quantityAfter = $quantityBefore - (int) $child->quantity;
                $locationBefore = $item->only(['building', 'room']);
                $targetBuilding = $assignee->building ?? $parent->building ?? $item->building;
                $targetRoom = $assignee->room ?? $parent->room ?? $item->room;
                $item->quantity = $quantityAfter;
                $item->building = $targetBuilding;
                $item->room = $targetRoom;
                if ($quantityAfter === 0) {
                    $item->status = 'assigned';
                    $item->assigned_to_user_id = $assigneeId;
                }
                $item->save();

                $transaction = Transaction::create([
                    'item_id' => $item->item_id,
                    'from_user_id' => $actorId,
                    'user_id' => $assigneeId,
                    'quantity' => (int) $child->quantity,
                    'issued_quantity' => (int) $child->quantity,
                    'from_building' => $locationBefore['building'],
                    'from_room' => $locationBefore['room'],
                    'building' => $targetBuilding,
                    'room' => $targetRoom,
                    'transaction_date' => $now,
                    'status' => 'assigned',
                    'expected_return_date' => $parent->expected_return_date
                        ?? self::defaultExpectedReturnDate($now),
                ]);
                $firstTransactionId ??= $transaction->id;

                $child->update([
                    'transaction_id' => $transaction->id,
                    'status' => 'approved',
                    'responded_at' => $now,
                ]);

                $this->recordMovement(
                    $item->item_id,
                    $actorId,
                    'assignment',
                    (int) $child->quantity,
                    $quantityBefore,
                    $quantityAfter,
                    'assignment_request',
                    $child->id,
                    "Draft #{$parent->id} allocated inventory #{$item->item_id} to user #{$assigneeId}; transaction #{$transaction->id}",
                    $locationBefore,
                );
            }

            $parent->update([
                'transaction_id' => $firstTransactionId,
                'status' => 'approved',
                'responded_at' => $now,
            ]);

            return 'allocated';
        });
    }

    public function issueManual(array $attributes, int $actorId): Transaction|string
    {
        $result = $this->issueManuals([$attributes], $actorId);

        return $result['transactions'][0] ?? $result['errors'][0][0] ?? 'The manual assignment could not be recorded.';
    }

    /**
     * Multi-line manual issue: one recipient, N transactions, all-or-none.
     *
     * Same guards as the single-line path per row (available, category/unit
     * match, serial allocation, quantity), with same-item lines sharing one
     * stock pool. Shared recipient fields come from $attributes; per-line
     * stock fields come from each $lines entry.
     *
     * @return array{transactions?: Transaction[], errors?: array}
     */
    public function issueManuals(array $lines, int $actorId): array
    {
        return DB::transaction(function () use ($lines, $actorId): array {
            $ordered = array_values($lines);
            $ids = array_values(array_unique(array_map(
                fn ($line) => (int) ($line['item_id'] ?? 0),
                $ordered,
            )));
            sort($ids);
            $items = Inventory::with('category')
                ->whereIn('item_id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            $errors = [];
            $promised = [];
            foreach ($ordered as $index => $attributes) {
                $itemId = (int) ($attributes['item_id'] ?? 0);
                $quantity = $attributes['quantity'] ?? null;
                $item = $items->get($itemId);
                if (! $item) {
                    $errors[$index][] = 'The selected inventory record is no longer available.';
                    continue;
                }
                if (
                    $item->status !== 'available'
                    || (int) $item->quantity < 1
                    || (int) $item->category_id !== (int) ($attributes['category_id'] ?? 0)
                    || $item->unit !== ($attributes['unit'] ?? null)
                ) {
                    $errors[$index][] = 'The selected inventory record is no longer available or does not match its category and unit.';
                    continue;
                }
                if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                    $errors[$index][] = 'Quantity must be a whole number of at least 1.';
                    continue;
                }
                $quantity = (int) $quantity;
                if ($item->category?->requires_serial_number) {
                    if ($quantity !== 1 || (int) $item->quantity !== 1 || ! $item->serial_number) {
                        $errors[$index][] = 'Serialized inventory must be issued as its exact individual asset with quantity 1.';
                        continue;
                    }
                } elseif ($quantity + ($promised[$itemId] ?? 0) > (int) $item->quantity) {
                    $errors[$index][] = 'The requested quantity exceeds the selected inventory record.';
                    continue;
                }
                $promised[$itemId] = ($promised[$itemId] ?? 0) + $quantity;
            }

            if ($errors !== []) {
                return ['errors' => $errors];
            }

            $created = [];
            foreach ($ordered as $attributes) {
                $inventoryItem = $items->get((int) $attributes['item_id']);
                $quantity = (int) $attributes['quantity'];
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
                    'manual_department' => $attributes['manual_department'] ?? null,
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
                    "Manual issue to {$transaction->manual_recipient_name}".($transaction->manual_department ? " ({$transaction->manual_department})" : '')."; transaction #{$transaction->id}",
                    $locationBefore,
                );

                $created[] = $transaction;
            }

            return ['transactions' => $created];
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

            // Unmet demand ('waiting for procurement') is approvable once stock exists:
            // approveItemTypeRequest still validates the selected records, so a
            // request that is still unfulfillable falls through to 'insufficient'
            // rather than being approved against nothing.
            if (! in_array($assignmentRequest->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT], true)) {
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
                'expected_return_date' => $assignmentRequest->expected_return_date
                    ?? self::defaultExpectedReturnDate(now()),
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
            // Same authority the custodian's stock-health column was computed
            // from, so a record the UI offered is always a record this accepts.
            if (! RequestableItemMatcher::matches(
                (string) $assignmentRequest->requested_item_name,
                (int) $assignmentRequest->requested_category_id,
                $assignmentRequest->requested_unit,
                $item,
            )) {
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
                'expected_return_date' => self::defaultExpectedReturnDate($now),
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
                'expected_return_date' => self::defaultExpectedReturnDate($assignment->requested_at ?? now()),
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

            if ($transferRequest->status !== 'transfer draft' || (int) $transferRequest->quantity < 1) {
                return false;
            }

            $releaseSlip = TransferReleaseSlip::query()
                ->where('request_id', $transferRequest->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();
            if (! $releaseSlip) {
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
            $originalAssignment = $transferRequest->source_assignment_id
                ? AssignmentRequest::whereKey($transferRequest->source_assignment_id)->lockForUpdate()->first()
                : AssignmentRequest::query()
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
            if (
                (int) $originalAssignment->item_id !== (int) $transferRequest->item_id
                || ! in_array($originalAssignment->status, ['approved', 'accepted', 'transfer pending'], true)
            ) {
                return false;
            }

            $originTransaction = $originalAssignment->transaction_id
                ? Transaction::whereKey($originalAssignment->transaction_id)->lockForUpdate()->first()
                : null;
            if ($originTransaction && (int) $originTransaction->quantity < (int) $transferRequest->quantity) {
                return false;
            }
            $hasPendingSiblingTransfer = $transferRequest->source_assignment_id
                && AssignmentRequest::query()
                    ->where('source_assignment_id', $originalAssignment->id)
                    ->where('id', '!=', $transferRequest->id)
                    ->whereIn('status', ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'])
                    ->exists();
            $fullAssignmentTransfer = ! $hasPendingSiblingTransfer && (
                ($originalAssignment->status === 'transfer pending'
                    && (int) ($originTransaction?->quantity ?? $originalAssignment->quantity) <= (int) $transferRequest->quantity)
                || (int) $originalAssignment->quantity < 1
            );

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

            if ($fullAssignmentTransfer) {
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
            $releaseSlip->update([
                'status' => 'completed',
                'completed_at' => now(),
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

            $original->decrement('quantity', $quantity);

            AssignmentRequest::create([
                'item_id' => $itemId,
                'source_assignment_id' => $original->id,
                'user_id' => $senderId,
                'target_user_id' => $recipientId,
                'building' => $recipient->building,
                'room' => $recipient->room,
                'quantity' => $quantity,
                'status' => 'waiting for custodian approval',
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

            // Legacy transfers still use this state transition; new requests
            // go directly to the custodian.
            $senderId = (int) $transferRequest->user_id;
            $originalAssignment = $transferRequest->source_assignment_id
                ? AssignmentRequest::whereKey($transferRequest->source_assignment_id)->lockForUpdate()->first()
                : AssignmentRequest::query()
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

            if (! $originalAssignment) {
                return false;
            }
            if (
                $originalAssignment->status === 'transfer pending'
                && (int) $originalAssignment->quantity < (int) $transferRequest->quantity
            ) {
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
            if (! in_array($transferRequest->status, ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'], true)) {
                return false;
            }

            $senderId = (int) $transferRequest->user_id;
            $originalAssignment = $transferRequest->source_assignment_id
                ? AssignmentRequest::whereKey($transferRequest->source_assignment_id)->lockForUpdate()->first()
                : AssignmentRequest::query()
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
            $transferRequest->releaseSlip()->update(['status' => 'cancelled']);

            return true;
        });
    }

    public function cancelTransferRequest(int $transferRequestId, ?string $reason): bool
    {
        return DB::transaction(function () use ($transferRequestId, $reason): bool {
            $transferRequest = AssignmentRequest::whereKey($transferRequestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($transferRequest->status, ['waiting for transfer approval', 'waiting for custodian approval'], true)) {
                return false;
            }

            $senderId = (int) $transferRequest->user_id;
            $originalAssignment = $transferRequest->source_assignment_id
                ? AssignmentRequest::whereKey($transferRequest->source_assignment_id)->lockForUpdate()->first()
                : AssignmentRequest::query()
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
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
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

    /**
     * Remove only the confirmed missing quantity from the reporter's active
     * assignment. Assigned units are represented by transactions, not by the
     * inventory row's on-hand quantity, so the stock ledger records the
     * combined on-hand and assigned total before and after the loss.
     *
     * @return array{assignment_changes: array<int, array{transaction_id: int, quantity_before: int, quantity_after: int}>, quantity_before: int, quantity_after: int}|string
     */
    public function recordIncidentLoss(
        int $inventoryId,
        int $reporterId,
        ?int $assignmentTransactionId,
        int $quantity,
        int $actorId,
        int $incidentId,
        string $incidentType,
        string $notes,
    ): array|string {
        try {
            return DB::transaction(function () use (
                $inventoryId,
                $reporterId,
                $assignmentTransactionId,
                $quantity,
                $actorId,
                $incidentId,
                $incidentType,
                $notes,
            ): array {
                $inventory = Inventory::query()->whereKey($inventoryId)->lockForUpdate()->firstOrFail();
                $activeAssignments = Transaction::query()
                    ->where('item_id', $inventoryId)
                    ->where('status', 'assigned')
                    ->where('quantity', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                $reporterAssignments = $activeAssignments
                    ->where('user_id', $reporterId)
                    ->when(
                        $assignmentTransactionId !== null,
                        fn ($assignments) => $assignments->where('id', $assignmentTransactionId),
                    )
                    ->values();

                if ($quantity < 1 || $reporterAssignments->sum('quantity') < $quantity) {
                    throw new \DomainException('The reporter no longer has an active assignment covering the affected quantity.');
                }

                $quantityBefore = (int) $inventory->quantity + (int) $activeAssignments->sum('quantity');
                $remainingToRemove = $quantity;
                $assignmentChanges = [];
                $now = now();

                foreach ($reporterAssignments as $transaction) {
                    if ($remainingToRemove === 0) {
                        break;
                    }

                    $transactionQuantity = (int) $transaction->quantity;
                    $removed = min($transactionQuantity, $remainingToRemove);
                    $quantityAfter = $transactionQuantity - $removed;
                    $assignment = AssignmentRequest::query()
                        ->where('transaction_id', $transaction->id)
                        ->where('item_id', $inventoryId)
                        ->where('target_user_id', $reporterId)
                        ->whereIn('status', ['approved', 'accepted'])
                        ->lockForUpdate()
                        ->first();

                    if ($assignment && (int) $assignment->quantity !== $transactionQuantity) {
                        throw new \DomainException('The assignment and transaction quantities do not match. Review the assignment before resolving this report.');
                    }

                    $transaction->update([
                        'quantity' => $quantityAfter,
                        'status' => $quantityAfter === 0 ? 'lost' : 'assigned',
                        'return_date' => $quantityAfter === 0 ? $now->toDateString() : null,
                    ]);
                    if ($assignment) {
                        $assignment->update([
                            'quantity' => $quantityAfter,
                            'status' => $quantityAfter === 0 ? 'lost' : $assignment->status,
                            'responded_at' => $quantityAfter === 0 ? $now : $assignment->responded_at,
                        ]);
                    }

                    $assignmentChanges[] = [
                        'transaction_id' => (int) $transaction->id,
                        'assignment_request_id' => $assignment?->id,
                        'quantity_before' => $transactionQuantity,
                        'quantity_after' => $quantityAfter,
                    ];
                    $remainingToRemove -= $removed;
                }

                if ($remainingToRemove !== 0) {
                    throw new \DomainException('The affected quantity could not be fully matched to the active assignment.');
                }

                $remainingAssigned = (int) Transaction::query()
                    ->where('item_id', $inventoryId)
                    ->where('status', 'assigned')
                    ->where('quantity', '>', 0)
                    ->sum('quantity');
                $inventory->update([
                    'status' => (int) $inventory->quantity > 0 || $remainingAssigned === 0
                        ? 'available'
                        : 'assigned',
                    'assigned_to_user_id' => (int) $inventory->quantity > 0 || $remainingAssigned === 0
                        ? null
                        : $this->remainingInventoryAssignee($inventoryId),
                ]);

                $quantityAfter = $quantityBefore - $quantity;
                $movement = $this->recordMovement(
                    $inventoryId,
                    $actorId,
                    'incident_loss',
                    $quantity,
                    $quantityBefore,
                    $quantityAfter,
                    'incident_report',
                    $incidentId,
                    "{$incidentType} outcome: {$notes}",
                );

                return [
                    'assignment_changes' => $assignmentChanges,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'stock_movement_id' => (int) $movement->id,
                ];
            });
        } catch (\DomainException $exception) {
            return $exception->getMessage();
        }
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
            $inventory->refresh();

            $this->recordItemLog([
                'item_id' => $inventory->item_id,
                'inventory_item_no' => $inventory->inventory_item_no,
                'item_name' => $inventory->item_name,
                'category_id' => $inventory->category_id,
                'category_name' => $category?->category_name,
                'status' => $inventory->status,
                'action' => InventoryItemLog::ACTION_CREATED,
                'changes' => [
                    'quantity' => $quantity,
                    'unit_cost' => $inventory->unit_cost,
                    'unit' => $inventory->unit,
                    'category' => $category?->category_name,
                    'ics_no' => $inventory->ics_no,
                ],
                'reason' => null,
                'user_id' => isset($attributes['user_id']) ? (int) $attributes['user_id'] : null,
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
        ?string $reason = null,
    ): ?string {
        return DB::transaction(function () use ($itemId, $attributes, $quantity, $isSerialized, $groupItemIds, $actorId, $serialNumbers, $reason): ?string {
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
                    $before = $this->itemLogState($item);
                    $itemAttributes = $attributes;
                    if ($serialNumbers !== null && array_key_exists($index, $serialNumbers)) {
                        $itemAttributes['serial_number'] = $serialNumbers[$index];
                    }
                    $item->update($itemAttributes);
                    $this->recordItemDiff($item->refresh(), $actorId, $before, $reason);
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
            $before = $this->itemLogState($inventory);
            $inventory->update([...$attributes, 'quantity' => $quantity]);
            $inventory->refresh();
            $this->recordItemDiff($inventory, $actorId, $before, $reason);
            $quantityDelta = $quantity - $quantityBefore;

            if ($quantityDelta !== 0 || $locationChanged) {
                $this->recordMovement(
                    $inventory->item_id,
                    $actorId,
                    $quantityDelta === 0 ? 'relocated' : 'correction',
                    abs($quantityDelta),
                    $quantityBefore,
                    $quantity,
                    'inventory',
                    $inventory->item_id,
                    $quantityDelta === 0 ? 'Inventory location updated' : "Available quantity corrected from {$quantityBefore} to {$quantity}",
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
                return 'Only available items can be sent for repair.';
            }
            if ($inventory->category?->is_maintenance_eligible === false) {
                return 'Items in this category are not eligible for repair.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'under_repair']);
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
            if ($inventory->status !== 'under_repair') {
                return 'Item is not currently under repair.';
            }

            // A row with nothing on the shelf can still complete when its units
            // are issued out: the holder never gave them up, so finishing returns
            // the row to them. Only a row with neither stock nor holder is refused.
            $hasActiveAssignments = Transaction::query()
                ->where('item_id', $inventory->item_id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->exists();
            if ((int) $inventory->quantity <= 0 && ! $hasActiveAssignments) {
                return 'Only item records with remaining stock can be marked as repaired. The units are still issued out.';
            }

            $maintenanceRecord = MaintenanceRecord::query()
                ->where('inventory_id', $inventory->item_id)
                ->whereIn('status', ['reported', 'in_progress'])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();
            if (! $maintenanceRecord) {
                return 'No active repair record was found.';
            }

            $quantity = (int) $inventory->quantity;
            // An item repaired while some of its units are still issued out
            // returns to its holder instead of reading as available stock.
            $inventory->update(['status' => $hasActiveAssignments ? 'assigned' : 'available']);
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
            if ($inventory->status !== 'under_repair') {
                return 'Only items under repair can be marked ready to dispose.';
            }
            if ((int) $inventory->quantity <= 0) {
                return 'Only item records with remaining stock can be marked ready to dispose. The units are still issued out.';
            }

            $quantity = (int) $inventory->quantity;
            $inventory->update(['status' => 'ready_to_dispose']);
            $inventory->refresh();
            $this->recordItemLog([
                'item_id' => $inventory->item_id,
                'inventory_item_no' => $inventory->inventory_item_no,
                'item_name' => $inventory->item_name,
                'category_id' => $inventory->category_id,
                'status' => $inventory->status,
                'action' => InventoryItemLog::ACTION_UPDATED,
                'changes' => ['status' => ['old' => 'under_repair', 'new' => 'ready_to_dispose']],
                'reason' => $notes,
                'user_id' => $actorId,
            ]);
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

    public function isolateIncidentQuantity(
        int $inventoryId,
        int $quantity,
        int $actorId,
        int $incidentId,
        string $notes,
    ): Inventory {
        return DB::transaction(function () use ($inventoryId, $quantity, $actorId, $incidentId, $notes): Inventory {
            $source = Inventory::query()->whereKey($inventoryId)->lockForUpdate()->firstOrFail();
            $stockBefore = (int) $source->quantity;
            if ($quantity < 1 || $quantity > $stockBefore) {
                throw new \DomainException('The received stock no longer covers the reported affected quantity.');
            }

            $sourceBefore = $this->itemLogState($source);
            $category = $source->category;
            if ($category?->requires_serial_number && ($quantity !== 1 || blank($source->serial_number))) {
                throw new \DomainException('Serialized assets must be isolated as their exact single reported unit.');
            }

            $serialNumber = $source->serial_number;
            if ($serialNumber !== null && $quantity !== $stockBefore) {
                throw new \DomainException('An individually serialized asset cannot be split from a larger inventory quantity.');
            }
            $source->update([
                'quantity' => $stockBefore - $quantity,
                'serial_number' => $quantity === $stockBefore ? null : $serialNumber,
            ]);

            $remainingAssigneeId = $this->remainingInventoryAssignee($source->item_id);
            $source->update([
                'status' => (int) $source->quantity > 0 || ! $remainingAssigneeId ? 'available' : 'assigned',
                'assigned_to_user_id' => (int) $source->quantity > 0 ? null : $remainingAssigneeId,
            ]);

            $isolated = Inventory::create([
                'category_id' => $source->category_id,
                'unit' => $source->unit,
                'user_id' => $source->user_id,
                'assigned_to_user_id' => null,
                'item_name' => $source->item_name,
                'description' => $source->description,
                'building' => $source->building,
                'room' => $source->room,
                'quantity' => $quantity,
                'unit_cost' => $source->unit_cost,
                'ics_no' => $source->ics_no,
                'serial_number' => $serialNumber,
                'status' => 'available',
                'date_acquired' => $source->date_acquired,
                'lifespan_years' => $source->lifespan_years,
                'expected_end_date' => $source->expected_end_date,
            ]);

            $qrCode = null;
            if ($category?->requires_qr_code) {
                do {
                    $qrCode = 'dnhs_qr_'.Str::lower(Str::random(24));
                } while (Inventory::query()->where('qr_code', $qrCode)->exists());
            }
            $isolated->update([
                'inventory_item_no' => sprintf('INV-%06d', $isolated->item_id),
                'qr_code' => $qrCode,
            ]);

            $this->recordItemDiff(
                $source->refresh(),
                $actorId,
                $sourceBefore,
                "Isolated incident #{$incidentId}: {$notes}",
            );
            $this->recordItemLog([
                'item_id' => $isolated->item_id,
                'inventory_item_no' => $isolated->inventory_item_no,
                'item_name' => $isolated->item_name,
                'category_id' => $isolated->category_id,
                'status' => $isolated->status,
                'action' => InventoryItemLog::ACTION_CREATED,
                'changes' => $this->itemLogState($isolated),
                'reason' => "Isolated affected units for incident #{$incidentId}: {$notes}",
                'user_id' => $actorId,
            ]);
            $this->recordMovement(
                $source->item_id,
                $actorId,
                'incident_split_out',
                $quantity,
                $stockBefore,
                (int) $source->quantity,
                'incident_report',
                $incidentId,
                $notes,
            );
            $this->recordMovement(
                $isolated->item_id,
                $actorId,
                'incident_split_in',
                $quantity,
                0,
                $quantity,
                'incident_report',
                $incidentId,
                $notes,
            );

            return $isolated->fresh();
        });
    }

    public function markIncidentReadyToDispose(
        int $itemId,
        int $quantity,
        int $actorId,
        int $incidentId,
        string $notes,
    ): ?string {
        return DB::transaction(function () use ($itemId, $quantity, $actorId, $incidentId, $notes): ?string {
            $inventory = Inventory::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            if ($inventory->status !== 'available' || (int) $inventory->quantity !== $quantity || $quantity < 1) {
                return 'The isolated incident quantity is no longer available for disposal review.';
            }

            if (Transaction::query()
                ->where('item_id', $itemId)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->exists()) {
                return 'Affected units must be fully handed over before disposal review.';
            }

            $inventory->update(['status' => 'ready_to_dispose']);
            $inventory->refresh();
            $this->recordItemLog([
                'item_id' => $inventory->item_id,
                'inventory_item_no' => $inventory->inventory_item_no,
                'item_name' => $inventory->item_name,
                'category_id' => $inventory->category_id,
                'status' => $inventory->status,
                'action' => InventoryItemLog::ACTION_UPDATED,
                'changes' => ['status' => ['old' => 'available', 'new' => 'ready_to_dispose']],
                'reason' => "Incident #{$incidentId}: {$notes}",
                'user_id' => $actorId,
            ]);
            $this->recordMovement(
                $inventory->item_id,
                $actorId,
                'ready_to_dispose',
                $quantity,
                $quantity,
                $quantity,
                'incident_report',
                $incidentId,
                $notes,
            );

            return null;
        });
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
            $inventory->refresh();
            $this->recordItemLog([
                'item_id' => $inventory->item_id,
                'inventory_item_no' => $inventory->inventory_item_no,
                'item_name' => $inventory->item_name,
                'category_id' => $inventory->category_id,
                'status' => $inventory->status,
                'action' => InventoryItemLog::ACTION_DISPOSED,
                'changes' => $this->itemLogState($inventory),
                'reason' => $notes,
                'user_id' => $actorId,
            ]);
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
     * Fields tracked for item-log diffs and snapshots.
     */
    private const ITEM_LOG_FIELDS = [
        'item_name',
        'category_id',
        'description',
        'ics_no',
        'unit',
        'unit_cost',
        'date_acquired',
        'lifespan_years',
        'building',
        'room',
        'serial_number',
        'quantity',
        'status',
    ];

    private function itemLogState(Inventory $item): array
    {
        $state = [];
        foreach (self::ITEM_LOG_FIELDS as $field) {
            $state[$field] = $item->getAttribute($field);
        }

        return $state;
    }

    private function recordItemDiff(Inventory $item, int $actorId, array $before, ?string $reason): void
    {
        $after = $this->itemLogState($item);
        $diff = [];
        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;
            if ($old != $new) {
                $diff[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($diff === []) {
            return;
        }

        $this->recordItemLog([
            'item_id' => $item->item_id,
            'inventory_item_no' => $item->inventory_item_no,
            'item_name' => $item->item_name,
            'category_id' => $item->category_id,
            'status' => $item->status,
            'action' => InventoryItemLog::ACTION_UPDATED,
            'changes' => $diff,
            'reason' => $reason,
            'user_id' => $actorId,
        ]);
    }

    private function recordItemLog(array $attributes): InventoryItemLog
    {
        return InventoryItemLog::create($attributes);
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