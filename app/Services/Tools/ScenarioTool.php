<?php

namespace App\Services\Tools;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;
use Illuminate\Support\Str;

/**
 * Read-only "what if" projections over recorded inventory.
 *
 * Nothing here writes: every type recomputes owned baseline figures with
 * substituted assumed inputs and returns them under `scenario_projection`,
 * quarantined from recorded facts. Assumed inputs appear only in the
 * `assumptions` sentences and the computed `projection` block; the
 * `baseline` block carries recorded values untouched, and the reply always
 * leads with the assumptions plus an explicit not-a-fact line.
 *
 * Whitelisted types only: stock_level, bulk_assign, bulk_remove,
 * dispose_set, gap_to_target. Anything else (open-ended futures such as
 * enrollment shifts, disasters, or price changes) is refused with the
 * closest supported computation, naming its shape but never inventing the
 * parameters for it.
 */
class ScenarioTool implements ToolContract
{
    use InteractsWithInventory;

    public const TYPES = [
        'stock_level',
        'bulk_assign',
        'bulk_remove',
        'dispose_set',
        'gap_to_target',
        'open_ended',
    ];

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_SCENARIO;
    }

    public function fetch(User $user, array $request): array
    {
        $scenario = $request['scenario'] ?? null;
        if (! is_array($scenario) || ! in_array($scenario['type'] ?? null, self::TYPES, true)) {
            return $this->result('clarification', $request['intent'] ?? 'scenario', $request['capability'] ?? null, [
                'clarification_question' => 'What hypothetical change should I project? Try "what if Bond Paper stock was 50?" or "what if we assign 5 Chairs?"',
            ]);
        }

        if ($scenario['type'] === 'open_ended') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'scenario_refusal' => $this->refusal((string) ($scenario['topic'] ?? 'that')),
            ]);
        }

        $quantity = $scenario['quantity'] ?? null;
        if (! is_int($quantity) || $quantity < 1) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => 'How many units should I assume? Give a whole number of 1 or more.',
            ]);
        }

        $itemName = is_string($request['item_name'] ?? null) ? trim($request['item_name']) : '';
        if ($itemName === '') {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => 'Which item should this scenario apply to?',
            ]);
        }

        $records = $this->inventoryRecordsForRequest(['item_name' => $itemName], false);
        if ($records->isEmpty()) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }

        $groups = $records->groupBy(fn (Inventory $item): string => mb_strtolower(trim($item->item_name))
            .'|'.(int) $item->category_id
            .'|'.mb_strtolower(trim((string) $item->unit)));
        if ($groups->count() > 1) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_item' => $itemName,
                'clarification_candidates' => $this->scenarioGroupCandidates($groups),
            ]);
        }

        $group = $groups->first();
        $baseline = $this->baseline($scenario['type'], $group);

        return $this->result('success', $request['intent'], $request['capability'], [
            'scenario_projection' => [
                'scenario_type' => $scenario['type'],
                'header' => $this->header($scenario['type'], $baseline, $quantity),
                'baseline' => $baseline,
                'verdict' => $this->verdict($scenario['type'], $baseline, $quantity),
                'detail' => $this->detail($scenario['type'], $baseline, $quantity),
                'projection' => $this->project($scenario['type'], $baseline, $quantity),
            ],
        ]);
    }

    /**
     * Refuse an open-ended future with the closest supported computation.
     *
     * The alternative names its shape only; the caller supplies the numbers,
     * so no parameter is ever invented here.
     */
    private function refusal(string $topic): string
    {
        $alternative = match (true) {
            preg_match('/\b(enrollments?|enrolments?|demand)\b/iu', $topic) === 1
                => 'a stock-level substitution (try "what if <item> stock was <number>?")',
            preg_match('/\b(typhoon|storm|flood|earthquake|fire|disaster)\b/iu', $topic) === 1
                => 'a disposal-set projection (try "what if we dispose <number> <item>?")',
            preg_match('/\b(prices?|budget|cost)\b/iu', $topic) === 1
                => 'a gap-to-target projection, which prices the gap at today\'s recorded average unit cost (try "what if we need <number> <item>?")',
            default
                => 'a stock-level substitution (try "what if <item> stock was <number>?")',
        };

        return 'I can\'t project '.$topic.' — I don\'t model future events or price changes, and I won\'t guess at them. '
            .'This stays a what-if over today\'s recorded inventory; nothing was changed. '
            .'The closest supported projection is '.$alternative.'.';
    }

    /**
     * Recorded baseline for one item group: the math inputs only.
     *
     * No assumed value enters here. Each type reads only the inputs its
     * verdict needs: free stock (available minus holds) for assignment,
     * assigned holdings for returns, available stock for disposals, and
     * recorded average unit cost for gap pricing.
     *
     * @param  \Illuminate\Support\Collection<int, Inventory>  $group
     * @return array<string, mixed>
     */
    private function baseline(string $type, $group): array
    {
        $first = $group->first();
        $available = (int) $group->where('status', 'available')->sum('quantity');
        $assignedUnits = (int) $group->where('status', 'assigned')->sum('quantity');
        $pending = (int) $this->pendingDemand($group);
        $costs = $group->pluck('unit_cost')->filter(fn ($cost): bool => is_numeric($cost) && (float) $cost >= 0)->map(fn ($cost): float => (float) $cost);

        $baseline = [
            'item_name' => $first->item_name,
            'unit' => $first->unit ?: 'units',
            'current_available' => $available,
        ];

        if (in_array($type, ['stock_level', 'bulk_assign'], true)) {
            $baseline['pending_demand'] = $pending;
        }
        if ($type === 'bulk_assign') {
            $baseline['free_stock'] = $available - $pending;
        }
        if (in_array($type, ['bulk_remove', 'dispose_set'], true)) {
            $baseline['assigned_units'] = $assignedUnits;
        }
        if ($type === 'gap_to_target') {
            $baseline['average_unit_cost'] = $costs->isEmpty() ? null : round($costs->avg(), 2);
        }

        return $baseline;
    }

    /**
     * The single "Assuming…" header, built here and nowhere else.
     *
     * @param  array<string, mixed>  $baseline
     */
    private function header(string $type, array $baseline, int $quantity): string
    {
        $unit = Str::plural($baseline['unit'], $quantity);

        return match ($type) {
            'stock_level' => "Assuming available stock of {$baseline['item_name']} ({$baseline['unit']}) is {$quantity} {$unit}.",
            'bulk_assign' => "Assuming assignment of {$quantity} {$unit} of {$baseline['item_name']}.",
            'bulk_remove' => "Assuming return of {$quantity} assigned {$unit} of {$baseline['item_name']} to stock.",
            'dispose_set' => "Assuming disposal of {$quantity} {$unit} of {$baseline['item_name']}.",
            'gap_to_target' => "Assuming target stock of {$quantity} {$unit} of {$baseline['item_name']}.",
            default => 'Assuming the stated hypothetical change.',
        };
    }

    /**
     * Verdict-first headline per type, comparator-correct.
     *
     * Assigns consume free stock (available minus holds), returns free
     * holders back to stock, disposals remove available stock while custody
     * stays untouched, and targets produce gaps. Impossible outcomes lead
     * with the shortfall or excess.
     *
     * @param  array<string, mixed>  $baseline
     */
    private function verdict(string $type, array $baseline, int $quantity): string
    {
        $unit = $baseline['unit'];
        $computed = $this->project($type, $baseline, $quantity);

        return match ($type) {
            'stock_level' => sprintf(
                'Available would be %d %s (now %d).',
                $quantity,
                Str::plural($unit, $quantity),
                (int) $baseline['current_available']
            ),
            'bulk_assign' => $computed['shortfall'] > 0
                ? sprintf(
                    'Shortfall %d %s: only %d free for %d wanted.',
                    $computed['shortfall'],
                    Str::plural($unit, $computed['shortfall']),
                    (int) $baseline['free_stock'],
                    $quantity
                )
                : sprintf(
                    'All %d %s could be assigned; %d free left.',
                    $quantity,
                    Str::plural($unit, $quantity),
                    $computed['projected_remaining']
                ),
            'bulk_remove' => sprintf(
                '%d of %d assigned %s would return to stock.',
                $computed['returned_to_stock'],
                (int) $baseline['assigned_units'],
                Str::plural($unit, (int) $baseline['assigned_units'])
            ),
            'dispose_set' => sprintf(
                'Stock would drop to %d (%d removed); %d assigned custody untouched.',
                $computed['projected_available'],
                $computed['removed_from_stock'],
                (int) $baseline['assigned_units']
            ),
            'gap_to_target' => sprintf(
                'Gap %d %s to reach %d.',
                $computed['gap_units'],
                Str::plural($unit, $computed['gap_units']),
                $quantity
            ),
            default => 'No projection was computed for this scenario type.',
        };
    }

    /**
     * The comparator math behind the verdict. Empty when the verdict
     * already carries every figure, so zero filler is never emitted.
     *
     * @param  array<string, mixed>  $baseline
     */
    private function detail(string $type, array $baseline, int $quantity): string
    {
        $unit = $baseline['unit'];
        $computed = $this->project($type, $baseline, $quantity);

        return match ($type) {
            'stock_level' => sprintf(
                'Requested %d; shortfall %d (now %d).',
                (int) $baseline['pending_demand'],
                $computed['projected_shortfall'],
                $computed['current_shortfall']
            ),
            'bulk_remove' => sprintf(
                'Available %d to %d; %d still assigned%s.',
                (int) $baseline['current_available'],
                $computed['projected_available'],
                $computed['projected_assigned'],
                $computed['beyond_assigned'] > 0 ? '; '.$computed['beyond_assigned'].' beyond assigned holdings' : ''
            ),
            'dispose_set' => $computed['beyond_available'] > 0
                ? sprintf('%d beyond available stock.', $computed['beyond_available'])
                : '',
            'gap_to_target' => $computed['projected_cost'] === null
                ? sprintf(
                    'No unit cost is recorded, so the gap of %d %s cannot be priced.',
                    $computed['gap_units'],
                    Str::plural($unit, $computed['gap_units'])
                )
                : sprintf(
                    'At a recorded average of %s each: projected cost PHP %s.',
                    number_format((float) $computed['average_unit_cost'], 2),
                    number_format((float) $computed['projected_cost'], 2)
                ),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @return array<string, mixed>
     */
    private function project(string $type, array $baseline, int $quantity): array
    {
        // Baselines carry only the inputs each verdict needs; missing keys
        // default to zero so every type stays total over partial data.
        $available = (int) ($baseline['current_available'] ?? 0);
        $pending = (int) ($baseline['pending_demand'] ?? 0);
        $free = (int) ($baseline['free_stock'] ?? ($available - $pending));
        $assigned = (int) ($baseline['assigned_units'] ?? 0);

        return match ($type) {
            'stock_level' => [
                'assumed_available' => $quantity,
                'current_shortfall' => max(0, $pending - $available),
                'projected_shortfall' => max(0, $pending - $quantity),
                'availability_delta' => $quantity - $available,
            ],
            'bulk_assign' => [
                'assumed_assign' => $quantity,
                'free_stock' => $free,
                'projected_remaining' => $free - $quantity,
                'shortfall' => max(0, $quantity - $free),
            ],
            'bulk_remove' => [
                'assumed_remove' => $quantity,
                'returned_to_stock' => min($quantity, $assigned),
                'projected_available' => $available + min($quantity, $assigned),
                'projected_assigned' => $assigned - min($quantity, $assigned),
                'beyond_assigned' => max(0, $quantity - $assigned),
            ],
            'dispose_set' => [
                'assumed_dispose' => $quantity,
                'removed_from_stock' => min($quantity, $available),
                'projected_available' => $available - min($quantity, $available),
                'assigned_untouched' => $assigned,
                'beyond_available' => max(0, $quantity - $available),
            ],
            'gap_to_target' => [
                'target_stock' => $quantity,
                'gap_units' => max(0, $quantity - $available),
                'average_unit_cost' => $baseline['average_unit_cost'] ?? null,
                'projected_cost' => ($baseline['average_unit_cost'] ?? null) === null
                    ? null
                    : round(max(0, $quantity - $available) * (float) $baseline['average_unit_cost'], 2),
            ],
            default => [],
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Inventory>  $group
     */
    private function pendingDemand($group): int
    {
        $ids = $group->pluck('item_id')->all();
        $name = mb_strtolower(trim((string) $group->first()->item_name));
        $categoryId = (int) $group->first()->category_id;

        return (int) AssignmentRequest::query()
            ->where('status', 'waiting for approval')
            ->where(fn ($query) => $query
                ->whereIn('item_id', $ids)
                ->orWhere(fn ($query) => $query
                    ->whereRaw('LOWER(requested_item_name) LIKE ?', ['%'.$name.'%'])
                    ->where('requested_category_id', $categoryId)))
            ->sum('quantity');
    }

    /**
     * @param  \Illuminate\Support\Collection  $groups
     * @return array<int, string>
     */
    private function scenarioGroupCandidates($groups): array
    {
        $categoryNames = Category::query()
            ->whereIn('category_id', $groups->flatten()->pluck('category_id')->filter()->unique())
            ->pluck('category_name', 'category_id');

        return $groups->take(20)->values()->map(function ($group): string {
            $first = $group->first();

            return sprintf(
                '%s (%s, %s)',
                $first->item_name,
                $first->unit ?: 'units',
                $categoryNames[$first->category_id] ?? 'Uncategorized'
            );
        })->all();
    }
}
