<?php

namespace App\Services\Tools;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;
use Illuminate\Support\Facades\DB;

/**
 * Stock, availability, location and reporting facts.
 *
 * Every method below is the original InventoryAnswerService body, relocated.
 * Queries, ordering, take(25) caps, the unit-cost / total-value metric split
 * and the per-capability disposed-scoping exceptions are unchanged.
 */
class InventoryTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return in_array($capability, [
            AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
            AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
            AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            AiCapabilityPolicy::VIEW_LOW_STOCK,
            AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS,
            AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY,
            AiCapabilityPolicy::VIEW_INVENTORY_VALUATION,
            AiCapabilityPolicy::VIEW_ITEM_STATUS,
        ], true);
    }

    public function fetch(User $user, array $request): array
    {
        return match ($request['capability'] ?? null) {
            AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY => $this->warehouseAvailability($request),
            AiCapabilityPolicy::VIEW_INVENTORY_STOCK => $this->inventoryStock($request),
            AiCapabilityPolicy::VIEW_INVENTORY_LOCATION => $this->location($request),
            AiCapabilityPolicy::VIEW_LOW_STOCK => $this->lowStock($request),
            AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS => $this->executiveSummary(),
            AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY => $this->systemSummary(),
            AiCapabilityPolicy::VIEW_INVENTORY_VALUATION => $this->inventoryValuation($request),
            AiCapabilityPolicy::VIEW_ITEM_STATUS => $this->itemStatus($request),
            default => $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null),
        };
    }

    protected function warehouseAvailability(array $request): array
    {
        if (($request['item_name'] ?? null) !== null) {
            return $this->specificAvailability($request);
        }

        $allItems = $this->groupInventoryRecords($this->matchingInventoryRecords(null))
            ->filter(fn (array $item): bool => $item['available_quantity'] > 0)
            ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE);
        $items = $allItems
            ->take(25)
            ->values()
            ->all();

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => (int) $allItems->sum('available_quantity'),
                'item_types' => $allItems->count(),
                'calculated_at' => now()->toIso8601String(),
            ]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function specificAvailability(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'], $request, [
            'item_id',
            'category_id',
            'item_name',
            'quantity',
            'unit',
            'status',
            'serial_number',
            'inventory_item_no',
        ]);

        if ($records->isEmpty()) {
            return $this->result('not_found', $request['intent'], $request['capability'], [
                'item_name' => $request['item_name'],
            ]);
        }

        if ($this->hasAmbiguousUnit($records)) {
            return $this->clarificationForMatches($request, $this->groupInventoryRecords($records));
        }

        $groups = $this->groupInventoryRecords($records);
        if ($groups->count() > 1) {
            return $this->clarificationForMatches($request, $groups);
        }

        $item = $groups->first();
        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => $item['available_quantity'],
                'item_types' => 1,
                'item_name' => $item['item_name'],
                'unit' => $item['unit'],
            ]);
        }

        $availabilityFacts = [
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'available_quantity' => $item['available_quantity'],
            'assigned_quantity' => $item['assigned_quantity'],
            'inventory_ids' => $item['inventory_ids'],
            'unit' => $item['unit'],
            'stock_status' => $item['stock_status'],
            'calculated_at' => $item['calculated_at'],
            'discrepancies' => $item['discrepancies'],
        ];

        if (($request['intent'] ?? null) === 'explanation' || ($request['response_type'] ?? null) === 'detail') {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $availabilityFacts]);
        }

        return $this->result('success', $request['intent'], $request['capability'], $availabilityFacts);
    }

    protected function inventoryStock(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'] ?? null, $request);

        if (in_array($request['query'] ?? null, ['inventory_identifiers', 'product_name'], true)) {
            if ($records->isEmpty()) {
                return $this->result('not_found', $request['intent'], $request['capability']);
            }

            if ($request['query'] === 'product_name') {
                return $this->result('success', $request['intent'], $request['capability'], [
                    'product_names' => $records->pluck('item_name')->unique(fn ($name) => mb_strtolower(trim($name)))->values()->all(),
                ]);
            }

            return $this->result('success', $request['intent'], $request['capability'], [
                'inventory_identifiers' => $records->map(fn (Inventory $item): array => [
                    'item_name' => $item->item_name,
                    'inventory_id' => (int) $item->item_id,
                    'inventory_no' => $item->inventory_item_no,
                    'serial_number' => $item->serial_number,
                ])->all(),
            ]);
        }

        $items = $this->groupInventoryRecords($records)->values()->all();

        if (($request['item_name'] ?? null) !== null && count($items) > 1) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['item_name'] ?? null) !== null && (
            $this->hasAmbiguousCategoryOrUnit($records)
            || (($request['response_type'] ?? null) === 'detail' && $records->count() > 1)
        )) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => (int) collect($items)->sum('quantity'),
                'item_types' => count($items),
            ]);
        }

        if (count($items) === 1 && (($request['response_type'] ?? null) === 'detail' || ($request['intent'] ?? null) === 'explanation')) {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $items[0]]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function location(array $request): array
    {
        $query = Inventory::query()->with(['assignedTo', 'category']);
        $itemName = trim((string) ($request['item_name'] ?? ''));
        $locationQuery = trim((string) ($request['location_query'] ?? ''));

        if (isset($request['inventory_id'])) {
            $query->whereKey((int) $request['inventory_id']);
        } elseif ($itemName !== '') {
            $query->whereRaw('LOWER(TRIM(item_name)) = ?', [mb_strtolower($itemName)]);
        } elseif ($locationQuery !== '') {
            $normalizedLocation = mb_strtolower($locationQuery);
            $locationVariants = array_values(array_unique([
                $normalizedLocation,
                preg_replace('/^room\s+/u', '', $normalizedLocation) ?? $normalizedLocation,
            ]));
            $query->where(function ($locationQueryBuilder) use ($locationVariants): void {
                $locationQueryBuilder
                    ->whereIn(DB::raw('LOWER(TRIM(building))'), $locationVariants)
                    ->orWhereIn(DB::raw('LOWER(TRIM(room))'), $locationVariants);
            });
        } else {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => 'Which item or building/room should I look up?',
            ]);
        }

        $this->filterInventoryIdentity($query, $request);

        $records = $query->orderBy('item_id')->get();
        if ($records->isEmpty()) {
            return $this->result('not_found', $request['intent'], $request['capability'], [
                'item_name' => $itemName ?: null,
                'location_query' => $locationQuery ?: null,
            ]);
        }

        if ($itemName !== '' && $records->count() > 1) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_candidates' => $records->map(fn (Inventory $item): string => sprintf(
                    '%s (Inventory ID %d%s)',
                    $item->item_name,
                    $item->item_id,
                    $item->serial_number ? ", Serial {$item->serial_number}" : ''
                ))->all(),
            ]);
        }

        $items = $records->map(function (Inventory $item): array {
            $activeTransactions = Transaction::query()
                ->with('user')
                ->where('item_id', $item->item_id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->orderBy('id')
                ->get();
            $holders = $activeTransactions->map(fn (Transaction $transaction): ?string =>
                $transaction->user?->full_name ?? $transaction->manual_recipient_name
            )->filter()->unique()->values();

            return [
                'inventory_id' => (int) $item->item_id,
                'item_name' => $item->item_name,
                'holder' => $holders->isNotEmpty()
                    ? $holders->implode(', ')
                    : $item->assignedTo?->full_name,
                'building' => $item->building,
                'room' => $item->room,
                'status' => $item->status,
            ];
        })->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'location_items' => $items,
            'location_query' => $locationQuery ?: null,
        ]);
    }

    protected function lowStock(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'] ?? null, $request);
        if (($request['item_name'] ?? null) !== null && $this->hasAmbiguousCategoryOrUnit($records)) {
            return $this->clarificationForMatches($request, $this->groupInventoryRecords($records));
        }
        $items = array_values(array_filter($this->groupInventoryRecords($records)->values()->all(), fn (array $item): bool => $item['available_quantity'] <= $this->lowStockThreshold()));

        if (($request['item_name'] ?? null) !== null && count($items) > 1) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], ['low_stock_count' => count($items)]);
        }

        if (($request['item_name'] ?? null) !== null && $items === []) {
            $records = $this->matchingInventoryRecords($request['item_name'], $request);
            $allMatches = $this->groupInventoryRecords($records);
            if ($allMatches->count() > 1) {
                return $this->clarificationForMatches($request, $allMatches);
            }
            if ($allMatches->isNotEmpty()) {
                return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $allMatches->first()]);
            }
            return $this->result('not_found', $request['intent'], $request['capability'], ['item_name' => $request['item_name']]);
        }

        if (count($items) === 1 && ($request['response_type'] ?? null) === 'detail') {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $items[0]]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function executiveSummary(): array
    {
        $items = $this->groupInventoryRecords($this->matchingInventoryRecords(null));

        return $this->result('success', 'factual', AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS, [
            'total_items' => (int) $items->sum('quantity'),
            'available_items' => (int) $items->sum('available_quantity'),
            'assigned_items' => (int) $items->sum('assigned_quantity'),
            'calculated_at' => now()->toIso8601String(),
            'discrepancies' => $items->pluck('discrepancies')->flatten()->values()->all(),
        ]);
    }

    protected function systemSummary(): array
    {
        return $this->result('success', 'factual', AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY, [
            'registered_users' => (int) User::count(),
            'inventory_records' => (int) Inventory::count(),
            'transactions_logged' => (int) Transaction::count(),
        ]);
    }

    protected function inventoryValuation(array $request): array
    {
        $query = Inventory::query()->where('status', '!=', 'disposed');
        $this->filterItem($query, $request['item_name'] ?? null);
        $unitCostQuestion = preg_match('/\bunit\s+cost\b|\bcost\s+per\s+unit\b|\bhow much\s+(?:is\s+)?(?:one|1|each)\b/u', strtolower((string) ($request['original_question'] ?? ''))) === 1;
        $items = $query->get(['item_name', 'quantity', 'unit', 'unit_cost'])->groupBy('item_name')->map(fn ($group, $name): array => [
            'item_name' => $name,
            'quantity' => (int) $group->sum('quantity'),
            'unit_cost' => (float) ($group->first()->unit_cost ?? 0),
            'total_value' => (float) $group->sum(fn (Inventory $item): float => (float) $item->unit_cost * (int) $item->quantity),
            'unit' => $group->first()->unit ?? 'units',
        ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'items' => $items,
            'valuation_metric' => $unitCostQuestion ? 'unit_cost' : 'total_value',
        ]);
    }

    protected function itemStatus(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
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
            'serial_number' => $item->serial_number,
        ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['status_items' => $items]);
    }
}