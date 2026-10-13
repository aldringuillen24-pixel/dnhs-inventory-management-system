<?php

namespace App\Services\Tools;

use App\Models\AssignmentReturn;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Completed item returns, read-only.
 *
 * Sourced from the persisted assignment_returns rows the custodian's return
 * approval writes — one row per accepted return, never the pending request
 * queue. Capped at the latest 25 like every other record list. Display names
 * only — no notes, contacts, or user ids cross into answers.
 */
class ReturnTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_RETURN_HISTORY;
    }

    public function fetch(User $user, array $request): array
    {
        if (($request['capability'] ?? null) !== AiCapabilityPolicy::VIEW_RETURN_HISTORY) {
            return $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null);
        }

        return $this->returnRecords($request);
    }

    protected function returnRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $returns = AssignmentReturn::query()->with(['recipient', 'receivedBy', 'inventory'])
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->latest('returned_at')
            ->limit(25)
            ->get()
            ->map(fn (AssignmentReturn $return): array => [
                'inventory_id' => (int) $return->inventory_id,
                'item_name' => $return->inventory?->item_name ?? 'Item',
                'quantity' => (int) $return->quantity,
                'unit' => $return->inventory?->unit ?? 'units',
                'date' => $return->returned_at?->toDateString(),
                'from' => $return->recipient?->full_name ?? 'Unknown',
                'to' => $return->receivedBy?->full_name ?? 'Unknown',
                'status' => 'returned',
                'inventory_no' => $return->inventory?->inventory_item_no,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['return_records' => $returns]);
    }
}
