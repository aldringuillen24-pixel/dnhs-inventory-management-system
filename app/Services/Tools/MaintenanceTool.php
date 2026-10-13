<?php

namespace App\Services\Tools;

use App\Models\MaintenanceRecord;
use App\Models\Inventory;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;
use Illuminate\Support\Carbon;

/**
 * Maintenance records. Body relocated from InventoryAnswerService unchanged,
 * including the `inventoryRecordsForRequest($request, true)` disposed-scoping
 * exception and the limit(25) cap.
 *
 * Inspection has no capability and no tool source: inspection-flavoured
 * questions must resolve unsupported upstream. Never synthesize
 * inspection_records facts here to fill that gap.
 */
class MaintenanceTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_MAINTENANCE;
    }

    public function fetch(User $user, array $request): array
    {
        return $this->maintenanceRecords($request);
    }

    protected function maintenanceRecords(array $request): array
    {
        $analysis = $this->maintenanceAnalysisType((string) ($request['original_question'] ?? ''));
        if ($analysis !== null) {
            return $this->maintenanceAnalytics($request, $analysis);
        }

        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $maintenance = MaintenanceRecord::query()->with('inventory')
            ->when(is_array($request['inventory_ids'] ?? null), fn ($query) => $query->whereIn('inventory_id', $request['inventory_ids']))
            ->when(! is_array($request['inventory_ids'] ?? null) && $records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->latest('started_at')
            ->limit(25)
            ->get()
            ->map(fn (MaintenanceRecord $record): array => [
                'inventory_id' => (int) $record->inventory_id,
                'item_name' => $record->inventory?->item_name ?? 'Item',
                'status' => $record->status,
                'issue' => $record->issue_description,
                'notes' => $record->repair_notes,
                'date' => $record->started_at?->toDateString(),
                'inventory_no' => $record->inventory?->inventory_item_no,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['maintenance_records' => $maintenance]);
    }

    private function maintenanceAnalysisType(string $question): ?string
    {
        if (preg_match('/\b(?:repair\s+or\s+replace|replace\s+or\s+repair|should\s+i\s+(?:repair|replace)|should\s+we\s+(?:repair|replace))\b/iu', $question) === 1) {
            return 'repair_decision';
        }
        if (preg_match('/\bmaintenance\s+costs?\b/iu', $question) === 1) {
            return 'cost_summary';
        }
        if (preg_match('/\b(?:which\s+rooms?|buildings?|locations?)\b.*\b(?:most|maintenance|issues?|repairs?)\b|\b(?:maintenance|repair)\s+issues?\s+by\s+(?:room|building)\b/iu', $question) === 1) {
            return 'location_frequency';
        }
        if (preg_match('/\b(?:frequently|recurring|repeat(?:ed|ing)?|often)\b.*\b(?:repair|maintenance|problem|issue|break)\b|\b(?:repair|maintenance)\s+frequency\b/iu', $question) === 1) {
            return 'recurring_issues';
        }

        return null;
    }

    /**
     * @param  'repair_decision'|'location_frequency'|'recurring_issues'  $analysis
     * @return array<string, mixed>
     */
    private function maintenanceAnalytics(array $request, string $analysis): array
    {
        $range = $this->analyticsDateRange($request, $analysis === 'repair_decision' ? 0 : 3);
        if ($range['error'] !== null) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => $range['error'],
            ]);
        }

        if ($analysis === 'repair_decision') {
            return $this->repairDecision($request);
        }
        if ($analysis === 'cost_summary') {
            return $this->costSummary($request, $range);
        }

        $query = MaintenanceRecord::query()->with('inventory:item_id,item_name,building,room')
            ->whereHas('inventory');
        $this->filterMaintenanceDateRange($query, $range);
        $records = $query->get([
            'id', 'inventory_id', 'status', 'issue_description', 'started_at', 'created_at',
        ]);

        $period = [
            'start' => $range['start']?->toDateString(),
            'end' => $range['end']?->toDateString(),
        ];
        if ($analysis === 'location_frequency') {
            $locations = $records->groupBy(function (MaintenanceRecord $record): string {
                $building = trim((string) $record->inventory?->building) ?: 'Building not recorded';
                $room = trim((string) $record->inventory?->room) ?: 'Room not recorded';

                return $building."\0".$room;
            })->map(function ($group, string $key): array {
                [$building, $room] = explode("\0", $key, 2);

                return [
                    'building' => $building,
                    'room' => $room,
                    'issue_count' => $group->count(),
                ];
            })->sortByDesc('issue_count')->values()->all();

            return $this->result('success', $request['intent'], $request['capability'], [
                'maintenance_analytics' => [
                    'type' => $analysis,
                    'period' => $period,
                    'record_count' => $records->count(),
                    'locations' => $locations,
                    'uncertainty' => $records->isEmpty()
                        ? 'No maintenance records were found for this period.'
                        : 'Counts include recorded maintenance records only; missing room or building values are grouped as not recorded.',
                ],
            ]);
        }

        $items = $records->groupBy('inventory_id')->map(function ($group): array {
            $item = $group->first()->inventory;

            return [
                'item_name' => $item?->item_name ?? 'Item',
                'issue_count' => $group->count(),
                'last_issue_date' => $group->map(fn (MaintenanceRecord $record) => $record->started_at ?? $record->created_at)
                    ->filter()->max()?->toDateString(),
            ];
        })->sortByDesc('issue_count')->values()->all();
        $problems = $this->recurringProblemCounts($records);

        return $this->result('success', $request['intent'], $request['capability'], [
            'maintenance_analytics' => [
                'type' => $analysis,
                'period' => $period,
                'record_count' => $records->count(),
                'items' => $items,
                'recurring_problems' => $problems,
                'uncertainty' => 'Recurring problem labels are counted only when a recorded issue description contains a recognized term; descriptions without a recognized term are not classified.',
            ],
        ]);
    }

    private function repairDecision(array $request): array
    {
        $inventory = is_array($request['inventory_ids'] ?? null)
            ? Inventory::query()->whereIn('item_id', $request['inventory_ids'])->orderBy('item_id')->get()
            : $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $inventory)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $inventory)) {
            return $this->recordClarification($request, $inventory);
        }

        $ids = $inventory->pluck('item_id');
        $history = MaintenanceRecord::query()->whereIn('inventory_id', $ids)->get([
            'inventory_id', 'maintenance_cost',
        ])->groupBy('inventory_id');
        $today = Carbon::today();
        $items = $inventory->map(function (Inventory $item) use ($history, $today): array {
            $records = $history->get($item->item_id, collect());
            $knownCosts = $records->pluck('maintenance_cost')->filter(fn ($cost): bool => $cost !== null);
            $repairCost = $knownCosts->sum(fn ($cost): float => (float) $cost);
            $replacementProxy = is_numeric($item->unit_cost) && (float) $item->unit_cost > 0
                ? (float) $item->unit_cost
                : null;
            $ageYears = $item->date_acquired?->diffInYears($today);
            $lifespan = is_numeric($item->lifespan_years) ? (int) $item->lifespan_years : null;
            $nearOrPastLife = $lifespan !== null && $ageYears !== null && $ageYears >= $lifespan;
            $costRatio = $replacementProxy !== null && $repairCost > 0
                ? round($repairCost / $replacementProxy, 2)
                : null;

            $decision = 'insufficient_evidence';
            if (in_array($item->status, ['ready_to_dispose', 'disposal_review'], true)) {
                $decision = 'replacement_assessment';
            } elseif ($nearOrPastLife && $costRatio !== null && $costRatio >= 0.5) {
                $decision = 'consider_replacement';
            } elseif (! $nearOrPastLife && $replacementProxy !== null
                && $costRatio !== null && $costRatio < 0.5) {
                $decision = 'repair_may_be_economical';
            }

            $caveats = ['Recorded unit cost is a comparison proxy, not a current replacement quote.'];
            if ($records->isEmpty()) {
                $caveats[] = 'No repair history is recorded.';
            }
            if ($knownCosts->count() < $records->count()) {
                $caveats[] = 'Some repair records have no recorded cost.';
            }
            if ($lifespan === null || $ageYears === null) {
                $caveats[] = 'Acquisition age or expected lifespan is missing.';
            }
            if ($decision === 'insufficient_evidence' || $decision === 'replacement_assessment') {
                $caveats[] = 'No repairability assessment or current replacement quote is available; this is not a definitive recommendation.';
            }

            return [
                'item_name' => $item->item_name,
                'status' => $item->status,
                'acquired_on' => $item->date_acquired?->toDateString(),
                'age_years' => $ageYears,
                'lifespan_years' => $lifespan,
                'maintenance_count' => $records->count(),
                'known_repair_cost_total' => round($repairCost, 2),
                'missing_repair_cost_count' => $records->count() - $knownCosts->count(),
                'recorded_unit_cost_proxy' => $replacementProxy,
                'repair_to_unit_cost_ratio' => $costRatio,
                'decision' => $decision,
                'caveats' => $caveats,
            ];
        })->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'maintenance_analytics' => [
                'type' => 'repair_decision',
                'items' => $items,
                'criteria' => 'Replacement is considered only for an end-of-life item when known cumulative repair cost is at least 50% of recorded unit cost. Repair may be economical below that threshold before end of life. Otherwise evidence is insufficient.',
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MaintenanceRecord>  $records
     * @return array<int, array{problem: string, occurrences: int}>
     */
    private function costSummary(array $request, array $range): array
    {
        $inventory = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $inventory)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }

        $query = MaintenanceRecord::query()->with('inventory:item_id,item_name')
            ->when($inventory->isNotEmpty(), fn ($builder) => $builder->whereIn('inventory_id', $inventory->pluck('item_id')))
            ->whereHas('inventory');
        $this->filterMaintenanceDateRange($query, $range);
        $records = $query->get(['id', 'inventory_id', 'maintenance_cost']);
        $items = $records->groupBy('inventory_id')->map(function ($group): array {
            $knownCosts = $group->pluck('maintenance_cost')->filter(fn ($cost): bool => $cost !== null);

            return [
                'item_name' => $group->first()->inventory?->item_name ?? 'Item',
                'record_count' => $group->count(),
                'known_cost_total' => round($knownCosts->sum(fn ($cost): float => (float) $cost), 2),
                'missing_cost_count' => $group->count() - $knownCosts->count(),
            ];
        })->sortByDesc('known_cost_total')->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'maintenance_analytics' => [
                'type' => 'cost_summary',
                'period' => [
                    'start' => $range['start']?->toDateString(),
                    'end' => $range['end']?->toDateString(),
                ],
                'record_count' => $records->count(),
                'items' => $items,
                'uncertainty' => 'Totals sum only recorded maintenance_cost values; records without cost are counted separately and are not treated as zero-cost repairs.',
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MaintenanceRecord>  $records
     * @return array<int, array{problem: string, occurrences: int}>
     */
    private function recurringProblemCounts($records): array
    {
        $terms = [
            'paper jam' => ['paper jam', 'paper feed'],
            'power issue' => ['power', 'won’t turn on', "won't turn on", 'not turning on'],
            'battery issue' => ['battery', 'charging'],
            'overheating' => ['overheat', 'overheating'],
            'connection issue' => ['connection', 'connectivity', 'network'],
            'physical damage' => ['damage', 'broken', 'cracked'],
        ];
        $counts = [];
        $unclassified = 0;
        foreach ($records as $record) {
            $description = mb_strtolower((string) $record->issue_description);
            $matched = false;
            foreach ($terms as $label => $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($description, $needle)) {
                        $counts[$label] = ($counts[$label] ?? 0) + 1;
                        $matched = true;
                        break;
                    }
                }
            }
            if (! $matched) {
                $unclassified++;
            }
        }

        arsort($counts);

        return [
            ...collect($counts)->map(fn (int $count, string $problem): array => [
                'problem' => $problem,
                'occurrences' => $count,
            ])->values()->all(),
            ['problem' => 'unclassified', 'occurrences' => $unclassified],
        ];
    }
}