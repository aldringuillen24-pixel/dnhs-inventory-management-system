<?php

namespace App\Services\Tools;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Assignment facts: what the user personally holds, and the wider assignment
 * record set. Bodies are the original InventoryAnswerService methods, relocated.
 */
class AssignmentTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return in_array($capability, [
            AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS,
            AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        ], true);
    }

    public function fetch(User $user, array $request): array
    {
        return match ($request['capability'] ?? null) {
            AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS => $this->assignedItems($user, $request),
            AiCapabilityPolicy::VIEW_ASSIGNMENTS => $this->assignmentRecords($request),
            default => $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null),
        };
    }

    protected function assignedItems(User $user, array $request): array
    {
        $items = Inventory::query()->where('assigned_to_user_id', $user->id)->where('status', 'assigned')
            ->get(['item_id', 'item_name', 'quantity', 'unit', 'inventory_item_no', 'status'])
            ->loadMissing('transactions.assignmentReturns')
            ->map(function (Inventory $item): array {
                $snapshot = $item->quantitySnapshot();

                return [
                    'item_name' => $item->item_name,
                    'quantity' => $snapshot['assigned_quantity'],
                    'unit' => $item->unit,
                    'inventory_id' => (int) $item->item_id,
                    'inventory_no' => $item->inventory_item_no,
                    'status' => $item->status,
                    'calculated_at' => $snapshot['calculated_at'],
                    'discrepancies' => $snapshot['discrepancies'],
                ];
            })->all();

        return $this->result('success', $request['intent'], $request['capability'], ['assigned_items' => $items]);
    }

    protected function assignmentRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $assignments = Transaction::query()->with(['user', 'item'])
            ->where('status', 'assigned')
            ->whereIn('item_id', $records->pluck('item_id'))
            ->orderByDesc('transaction_date')
            ->limit(25)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'inventory_id' => (int) $transaction->item_id,
                'item_name' => $transaction->item?->item_name ?? 'Item',
                'assigned_to' => $transaction->manual_recipient_name ?? $transaction->user?->full_name ?? 'Not recorded',
                'quantity' => (int) $transaction->quantity,
                'unit' => $transaction->item?->unit ?? 'units',
                'status' => $transaction->status,
                'date' => $transaction->transaction_date?->toDateString(),
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['assignment_records' => $assignments]);
    }
}