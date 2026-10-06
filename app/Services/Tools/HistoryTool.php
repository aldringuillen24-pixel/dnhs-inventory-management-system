<?php

namespace App\Services\Tools;

use App\Models\StockMovement;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Transaction history: stock-in movements only.
 *
 * `movement_type` is a persisted value compared literally; the `stock_in` filter
 * and the limit(25) cap are unchanged.
 */
class HistoryTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_PURCHASE_HISTORY;
    }

    public function fetch(User $user, array $request): array
    {
        return $this->purchaseHistory($request);
    }

    protected function purchaseHistory(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $history = StockMovement::query()->with('inventory')
            ->where('movement_type', 'stock_in')
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'inventory_id' => (int) $movement->inventory_id,
                'item_name' => $movement->inventory?->item_name ?? 'Item',
                'quantity' => (int) $movement->quantity,
                'unit' => $movement->inventory?->unit ?? 'units',
                'date' => $movement->created_at?->toDateString(),
                'notes' => $movement->notes,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['purchase_history' => $history]);
    }
}