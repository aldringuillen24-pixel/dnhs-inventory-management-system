<?php

namespace App\Services\Tools;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Disposal facts: items already disposed and items awaiting disposal.
 *
 * Both capabilities keep their disposed-scoping exception, and the persisted
 * `disposed` / `ready_to_dispose` / `disposal_review` status strings and the
 * `disposed` movement type are compared exactly as written.
 */
class DisposalTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return in_array($capability, [
            AiCapabilityPolicy::VIEW_DISPOSAL,
            AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        ], true);
    }

    public function fetch(User $user, array $request): array
    {
        return match ($request['capability'] ?? null) {
            AiCapabilityPolicy::VIEW_DISPOSAL => $this->disposalRecords($request),
            AiCapabilityPolicy::VIEW_READY_TO_DISPOSE => $this->readyToDispose($request),
            default => $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null),
        };
    }

    protected function disposalRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $items = $records->where('status', 'disposed')->map(function (Inventory $item): array {
            $movement = StockMovement::query()->where('inventory_id', $item->item_id)
                ->where('movement_type', 'disposed')->latest()->first();

            return [
                'inventory_id' => (int) $item->item_id,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'quantity' => (int) ($movement?->quantity ?? $item->quantity),
                'unit' => $item->unit,
                'date' => $movement?->created_at?->toDateString(),
                'notes' => $movement?->notes,
            ];
        })->values()->all();

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'disposal_count' => count($items),
            ]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['disposal_records' => $items]);
    }

    protected function readyToDispose(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true)
            ->whereIn('status', ['ready_to_dispose', 'disposal_review']);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $items = $records->take(25)->map(fn (Inventory $item): array => [
            'inventory_id' => (int) $item->item_id,
            'item_name' => $item->item_name,
            'status' => $item->status,
            'quantity' => (int) $item->quantity,
            'unit' => $item->unit,
        ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], ['ready_to_dispose_items' => $items]);
    }
}