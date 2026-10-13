<?php

namespace App\Services\Tools;

use App\Models\AssignmentRequest;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Completed custody transfers, read-only.
 *
 * A completed transfer is an approved end-user to end-user request carrying
 * the resulting transaction: the approval is what moved custody, so rows
 * still awaiting any approval stage never appear here. Capped at the latest
 * 25 like every other record list. Display names only — no notes, contacts,
 * or user ids cross into answers.
 */
class TransferTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_TRANSFER_HISTORY;
    }

    public function fetch(User $user, array $request): array
    {
        if (($request['capability'] ?? null) !== AiCapabilityPolicy::VIEW_TRANSFER_HISTORY) {
            return $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null);
        }

        return $this->transferRecords($request);
    }

    protected function transferRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $transfers = AssignmentRequest::query()->with(['user', 'targetUser', 'item'])
            ->where('status', 'approved')
            ->whereNotNull('transaction_id')
            ->whereHas('user.role', fn ($query) => $query->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($query) => $query->where('role_name', 'End User'))
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('item_id', $records->pluck('item_id')))
            ->orderByDesc('responded_at')
            ->limit(25)
            ->get()
            ->map(fn (AssignmentRequest $transfer): array => [
                'inventory_id' => (int) $transfer->item_id,
                'item_name' => $transfer->item?->item_name ?? 'Item',
                'quantity' => (int) $transfer->quantity,
                'unit' => $transfer->item?->unit ?? 'units',
                'date' => $transfer->responded_at?->toDateString(),
                'from' => $transfer->user?->full_name ?? 'Unknown',
                'to' => $transfer->targetUser?->full_name ?? 'Unknown',
                'status' => 'transferred',
                'inventory_no' => $transfer->item?->inventory_item_no,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['transfer_records' => $transfers]);
    }
}
