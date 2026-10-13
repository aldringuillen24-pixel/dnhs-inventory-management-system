<?php

namespace App\Services\Tools;

use App\Models\StockMovement;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;
use Carbon\CarbonImmutable;

/**
 * Transaction history: stock-in movements only, plus a capped ledger summary.
 *
 * `movement_type` is a persisted value compared literally; the `stock_in` filter
 * and the limit(25) cap are unchanged. The ledger summary never dumps rows:
 * counts by movement type plus the latest five redacted movements.
 */
class HistoryTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return in_array($capability, [
            AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
            AiCapabilityPolicy::VIEW_LEDGER_SUMMARY,
        ], true);
    }

    public function fetch(User $user, array $request): array
    {
        if (($request['capability'] ?? null) === AiCapabilityPolicy::VIEW_LEDGER_SUMMARY) {
            return $this->ledgerSummary($request);
        }

        if (preg_match('/\b(?:stock[\s-]?out|movement)\s+(?:trend|trends|history)|\btrend(?:s)?\s+in\s+(?:stock[\s-]?out|inventory movement)/iu', (string) ($request['original_question'] ?? '')) === 1) {
            return $this->stockOutTrends($request);
        }

        return $this->purchaseHistory($request);
    }

    protected function ledgerSummary(array $request): array
    {
        $counts = StockMovement::query()->select('movement_type')->selectRaw('COUNT(*) as total')
            ->groupBy('movement_type')->orderBy('movement_type')
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'movement_type' => $movement->movement_type,
                'movement_count' => (int) $movement->total,
            ])->values()->all();

        $latest = StockMovement::query()->with(['inventory:item_id,item_name', 'user:id,first_name,last_name'])
            ->latest()->limit(5)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'item_name' => $movement->inventory?->item_name ?? 'Item',
                'movement_type' => $movement->movement_type,
                'quantity' => (int) $movement->quantity,
                'date' => $movement->created_at?->toDateString(),
                'actor' => $movement->user?->full_name ?? 'System',
            ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], ['ledger_summary' => [
            'movement_counts' => $counts,
            'latest_movements' => $latest,
        ]]);
    }

    protected function stockOutTrends(array $request): array
    {
        $range = $this->analyticsDateRange($request);
        if ($range['error'] !== null) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => $range['error'],
            ]);
        }

        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }

        $movements = StockMovement::query()->where('movement_type', 'stock_out')
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->get(['inventory_id', 'quantity', 'created_at']);
        $months = $movements->groupBy(fn (StockMovement $movement): string => CarbonImmutable::parse($movement->created_at)->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group, string $month): array => [
                'month' => $month,
                'movement_count' => $group->count(),
                'quantity' => (int) $group->sum('quantity'),
            ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'stock_out_analytics' => [
                'period' => [
                    'start' => $range['start']?->toDateString(),
                    'end' => $range['end']?->toDateString(),
                ],
                'movement_count' => $movements->count(),
                'quantity' => (int) $movements->sum('quantity'),
                'monthly' => $months,
                'evidence' => 'Counts and quantities include stock_movements rows with movement_type exactly equal to stock_out.',
                'uncertainty' => $movements->isEmpty()
                    ? 'No stock-out movement records were found in this period.'
                    : 'Only recorded stock_out movements are counted; other movement types are excluded.',
            ],
        ]);
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
                'inventory_no' => $movement->inventory?->inventory_item_no,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['purchase_history' => $history]);
    }
}