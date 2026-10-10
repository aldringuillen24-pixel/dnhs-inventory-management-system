<?php

namespace App\Services\Tools\Concerns;

use App\Models\Category;
use App\Models\Inventory;

/**
 * Inventory query and grouping helpers shared by every tool.
 *
 * These are the ambiguity, grouping and threshold rules the assistant has
 * always applied. They were protected methods on InventoryAnswerService and are
 * reproduced here byte for byte: same columns, same LIKE/= choice, same
 * case-folding, same `<=` low-stock comparison, same ordering. Moving them
 * must not change a single query semantic.
 */
trait InteractsWithInventory
{
    protected function result(string $status, ?string $intent, ?string $capability, array $answer = []): array
    {
        return [
            'status' => $status,
            'intent' => $intent,
            'capability' => $capability,
            'answer' => $answer,
            'explanation_data' => $answer,
        ];
    }

    protected function inventoryRecordsForRequest(array $request, bool $includeDisposed = false)
    {
        $query = Inventory::query()->orderBy('item_id');
        if (! $includeDisposed) {
            $query->where('status', '!=', 'disposed');
        }
        if (isset($request['inventory_id'])) {
            $query->where('item_id', (int) $request['inventory_id']);
        }
        if (is_string($request['serial_number'] ?? null) && trim($request['serial_number']) !== '') {
            $query->where('serial_number', trim($request['serial_number']));
        }
        if (is_string($request['item_name'] ?? null) && trim($request['item_name']) !== '') {
            $name = mb_strtolower(str_replace('-', ' ', trim($request['item_name'])));
            $query->whereRaw("LOWER(REPLACE(item_name, '-', ' ')) LIKE ?", ['%' . $name . '%']);
        }

        return $query->get();
    }

    protected function hasNoMatchingItem(array $request, $records): bool
    {
        return ($request['item_name'] ?? null) !== null
            || isset($request['inventory_id'])
            || isset($request['serial_number'])
            ? $records->isEmpty()
            : false;
    }

    protected function needsRecordClarification(array $request, $records): bool
    {
        $hasSelector = (is_string($request['item_name'] ?? null) && trim($request['item_name']) !== '')
            || (is_string($request['serial_number'] ?? null) && trim($request['serial_number']) !== '');

        return ! isset($request['inventory_id']) && $hasSelector && $records->count() > 1;
    }

    protected function recordClarification(array $request, $records): array
    {
        return $this->result('clarification', $request['intent'], $request['capability'], [
            'clarification_item' => $request['item_name'] ?? null,
            'clarification_candidates' => $this->formatClarificationCandidates($records),
        ]);
    }

    protected function matchingInventoryRecords(?string $itemName, array $identity = [], ?array $columns = null)
    {
        $query = Inventory::query()->where('status', '!=', 'disposed');
        $this->filterInventoryIdentity($query, $identity);
        $query->orderBy('item_id');
        $columns ??= ['item_id', 'category_id', 'item_name', 'description', 'quantity', 'unit', 'status', 'serial_number', 'inventory_item_no'];
        if ($itemName === null) {
            return $query->get($columns);
        }

        $normalizedName = strtolower(trim(str_replace('-', ' ', $itemName)));
        $normalizedName = preg_replace('/\s+/', ' ', $normalizedName) ?? $normalizedName;
        $terms = [$normalizedName];
        if (preg_match('/([a-z]+)$/', $normalizedName, $matches) === 1) {
            $lastWord = $matches[1];
            if (preg_match('/ies$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -3) . 'y';
            } elseif (preg_match('/(?:ches|shes|sses|xes|zes)$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -2);
            } elseif (preg_match('/[^s]s$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -1);
            }
        }

        $terms = array_values(array_unique($terms));
        foreach ($terms as $term) {
            $exact = (clone $query)->whereRaw("LOWER(REPLACE(TRIM(item_name), '-', ' ')) = ?", [$term])
                ->get($columns);
            if ($exact->isNotEmpty()) {
                return $exact;
            }
        }

        foreach ($terms as $term) {
            $matches = (clone $query)->whereRaw("LOWER(REPLACE(item_name, '-', ' ')) LIKE ?", ['%' . $term . '%'])
                ->get($columns);
            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return collect();
    }

    protected function filterInventoryIdentity($query, array $identity): void
    {
        if (isset($identity['inventory_id'])) {
            $query->where('item_id', (int) $identity['inventory_id']);
        }
        if (isset($identity['category_id'])) {
            $query->where('category_id', (int) $identity['category_id']);
        }
        if (is_string($identity['unit'] ?? null) && $identity['unit'] !== '') {
            $query->whereRaw('LOWER(TRIM(unit)) = ?', [mb_strtolower($identity['unit'])]);
        }
        if (is_string($identity['serial_number'] ?? null) && $identity['serial_number'] !== '') {
            $query->where('serial_number', $identity['serial_number']);
        }
    }

    protected function hasAmbiguousCategoryOrUnit($records): bool
    {
        $categories = $records->pluck('category_id')->filter()->unique()->count();
        return $categories > 1 || $this->hasAmbiguousUnit($records);
    }

    protected function hasAmbiguousUnit($records): bool
    {
        $units = $records->pluck('unit')->filter()->map(fn ($unit): string => strtolower(trim($unit)))->unique()->count();

        return $units > 1;
    }

    protected function clarificationForMatches(array $request, $matches): array
    {
        $records = isset($request['item_name'])
            ? $this->matchingInventoryRecords($request['item_name'])
            : collect();
        $candidates = $this->formatClarificationCandidates($records);
        if ($candidates === []) {
            $candidates = collect($matches)->pluck('item_name')->filter()->unique()->values()->all();
        }

        return $this->result('clarification', $request['intent'] ?? null, $request['capability'] ?? null, [
            'clarification_item' => $request['item_name'] ?? null,
            'clarification_candidates' => $candidates,
        ]);
    }

    protected function formatClarificationCandidates($records): array
    {
        $records = collect($records)->take(20);
        $categoryNames = Category::query()
            ->whereIn('category_id', $records->pluck('category_id')->filter()->unique())
            ->pluck('category_name', 'category_id');

        return $records->values()->map(fn (Inventory $item, int $index): string => sprintf(
            '%d) %s (Category: %s, Unit: %s, Inventory ID: %d%s%s)',
            $index + 1,
            $item->item_name,
            $categoryNames[$item->category_id] ?? 'Uncategorized',
            $item->unit,
            $item->item_id,
            $item->serial_number ? ", Serial: {$item->serial_number}" : '',
            $item->inventory_item_no ? ", Asset Tag: {$item->inventory_item_no}" : ''
        ))->all();
    }

    protected function normalizeClarificationSelection(string $selection): string
    {
        $selection = mb_strtolower(trim($selection));
        $selection = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $selection) ?? $selection;

        return trim(preg_replace('/\s+/u', ' ', $selection) ?? $selection);
    }

    protected function groupInventoryRecords($records)
    {
        // `matchingInventoryRecords()` returns a plain collection when nothing
        // matched, and a plain collection has no eager loading. Calling
        // loadMissing() on one raised a 500 for any question naming an item that
        // does not exist, such as "What about paper?". Grouping an empty set is
        // still correct, so the eager load is simply skipped when unsupported.
        if (method_exists($records, 'loadMissing')) {
            $records->loadMissing('transactions.assignmentReturns');
        }

        return $records->groupBy(fn (Inventory $item): string => strtolower(trim($item->item_name)))
            ->map(function ($group): array {
                $snapshots = $group->map(fn (Inventory $item): array => $item->quantitySnapshot());
                $available = (int) $snapshots->sum('available_quantity');
                return [
                    'item_name' => $group->first()->item_name,
                    'description' => $group->first()->description,
                    'quantity' => (int) $snapshots->sum('total_quantity'),
                    'available_quantity' => $available,
                    'assigned_quantity' => (int) $snapshots->sum('assigned_quantity'),
                    'inventory_ids' => $snapshots->pluck('inventory_id')->values()->all(),
                    'unit' => $group->first()->unit ?? 'units',
                    'stock_status' => $this->stockStatus($available),
                    'statuses' => $group->pluck('status')->unique()->values()->all(),
                    'calculated_at' => $snapshots->max('calculated_at'),
                    'discrepancies' => $snapshots->pluck('discrepancies')->flatten()->values()->all(),
                ];
            })->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE);
    }

    protected function filterItem($query, ?string $itemName): void
    {
        if ($itemName !== null) {
            $query->whereRaw('LOWER(item_name) LIKE ?', ['%' . strtolower($itemName) . '%']);
        }
    }

    protected function stockStatus(int $availableQuantity): string
    {
        return $availableQuantity === 0
            ? 'Out of Stock'
            : ($availableQuantity <= $this->lowStockThreshold() ? 'Low Stock' : 'In Stock');
    }

    /**
     * The low-stock cut-off.
     *
     * `<=` here is a business decision, not an implementation detail: an item
     * sitting exactly on the threshold is reported as low. Do not move the
     * comparison or re-derive the value from configuration at the call site.
     */
    protected function lowStockThreshold(): int
    {
        return max(1, (int) config('inventory.low_stock_threshold', 5));
    }
}