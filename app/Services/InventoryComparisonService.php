<?php

namespace App\Services;

use App\DTOs\InventoryComparisonData;
use App\Models\Inventory;
use App\Support\SampleForecastData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryComparisonService
{
    private const MAX_SERIES = 1;
    private const MAX_UNIT_OPTIONS = 50;

    private const METRICS = [
        'stock_in_quantity' => ['label' => 'Stock-in quantity', 'quantity' => true],
        'stock_out_quantity' => ['label' => 'Stock-out quantity', 'quantity' => true],
        'request_count' => ['label' => 'Request count', 'quantity' => false],
        'request_quantity' => ['label' => 'Requested quantity', 'quantity' => true],
        'assignment_count' => ['label' => 'Assignment count', 'quantity' => false],
        'assignment_quantity' => ['label' => 'Assignment quantity', 'quantity' => true],
        'transfer_count' => ['label' => 'Transfer count', 'quantity' => false],
        'transfer_quantity' => ['label' => 'Transfer quantity', 'quantity' => true],
        'maintenance_count' => ['label' => 'Completed maintenance count', 'quantity' => false],
        'disposal_quantity' => ['label' => 'Completed disposal quantity', 'quantity' => true],
    ];

    public function metrics(): array
    {
        return collect(self::METRICS)
            ->map(fn (array $metric, string $key): array => ['value' => $key, 'label' => $metric['label']])
            ->values()
            ->all();
    }

    public function draft(string $message): array
    {
        $metric = $this->recognizedMetric($message);
        $periods = $this->recognizedMonths($message);
        $scope = null;
        $itemName = null;
        $itemId = null;
        $itemUnit = null;
        $itemOptions = [];

        if (preg_match('/\b(?:all inventory|all items|overall inventory)\b/i', $message) === 1) {
            $scope = 'all';
        } elseif (preg_match('/\bitem\s+["\']([^"\']{1,255})["\']/i', $message, $matches) === 1) {
            $matchesForName = Inventory::query()->with('category')
                ->whereRaw('LOWER(item_name) = ?', [mb_strtolower(trim($matches[1]))])
                ->limit(50)
                ->get(['item_id', 'item_name', 'unit', 'category_id']);
            if ($matchesForName->count() === 1) {
                $scope = 'item';
                $itemName = $matchesForName->first()->item_name;
                $itemId = (int) $matchesForName->first()->item_id;
                $itemUnit = $matchesForName->first()->unit;
            } elseif ($matchesForName->count() > 1) {
                $scope = 'item';
                $itemOptions = $matchesForName->map(fn (Inventory $item): array => [
                    'value' => (int) $item->item_id,
                    'label' => implode(' | ', array_filter([
                        $item->item_name,
                        $item->category?->category_name,
                        $item->unit,
                        'Inventory ID '.$item->item_id,
                    ])),
                ])->all();
            }
        }

        return [
            'metrics' => $this->metrics(),
            'quantity_metrics' => array_keys(array_filter(self::METRICS, fn (array $metric): bool => $metric['quantity'])),
            'units' => $this->unitOptions(),
            'metric' => $metric,
            'scope' => $scope,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'item_options' => $itemOptions,
            'item_unit' => $itemUnit,
            'unit' => null,
            'month_one' => $periods[0]['value'] ?? null,
            'month_one_label' => $periods[0]['label'] ?? null,
            'month_two' => $periods[1]['value'] ?? null,
            'month_two_label' => $periods[1]['label'] ?? null,
        ];
    }

    public function isComparisonRequest(string $message): bool
    {
        return preg_match('/\b(?:compare|comparison|versus|vs\.?|difference between)\b/i', $message) === 1;
    }

    public function comparisonContextSummary(array $result): ?array
    {
        $metric = $result['metric'] ?? null;
        $series = $result['series'][0] ?? null;
        $months = $result['months'] ?? null;
        if (! is_string($metric) || ! isset(self::METRICS[$metric])
            || ! is_array($series) || ! is_array($months) || count($months) !== 2) {
            return null;
        }

        $summary = [
            'version' => 1,
            'metric' => $metric,
            'metric_label' => self::METRICS[$metric]['label'],
            'scope_label' => $result['scope_label'] ?? null,
            'months' => array_map(fn (array $month): array => [
                'value' => $month['value'] ?? null,
                'label' => $month['label'] ?? null,
            ], $months),
            'series' => [[
                'unit' => $series['unit'] ?? null,
                'period_one' => $series['period_one'] ?? null,
                'period_two' => $series['period_two'] ?? null,
                'period_one_status' => $series['period_one_status'] ?? null,
                'period_two_status' => $series['period_two_status'] ?? null,
                'difference' => $series['difference'] ?? null,
                'percentage_change' => $series['percentage_change'] ?? null,
                'percentage_change_status' => $series['percentage_change_status'] ?? null,
            ]],
        ];

        return $this->validateComparisonContextSummary($summary);
    }

    public function validateComparisonContextSummary(array $summary): ?array
    {
        $expectedKeys = ['version', 'metric', 'metric_label', 'scope_label', 'months', 'series'];
        if (array_diff(array_keys($summary), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($summary)) !== []
            || ($summary['version'] ?? null) !== 1
            || ! is_string($summary['metric'] ?? null)
            || ! isset(self::METRICS[$summary['metric']])
            || ($summary['metric_label'] ?? null) !== self::METRICS[$summary['metric']]['label']
            || ! is_string($summary['scope_label'] ?? null)
            || trim($summary['scope_label']) === ''
            || mb_strlen($summary['scope_label']) > 255
            || ! is_array($summary['months'] ?? null) || count($summary['months']) !== 2
            || ! is_array($summary['series'] ?? null) || count($summary['series']) !== 1) {
            return null;
        }

        foreach ($summary['months'] as $month) {
            if (! is_array($month)
                || array_diff(array_keys($month), ['value', 'label']) !== []
                || array_diff(['value', 'label'], array_keys($month)) !== []
                || ! is_string($month['value'] ?? null)
                || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month['value']) !== 1
                || ! is_string($month['label'] ?? null)
                || trim($month['label']) === ''
                || mb_strlen($month['label']) > 64
                || \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month['value'])->format('F Y') !== $month['label']) {
                return null;
            }
        }

        $row = $summary['series'][0];
        $expectedSeriesKeys = [
            'unit', 'period_one', 'period_two', 'period_one_status', 'period_two_status',
            'difference', 'percentage_change', 'percentage_change_status',
        ];
        if (! is_array($row)
            || array_diff(array_keys($row), $expectedSeriesKeys) !== []
            || array_diff($expectedSeriesKeys, array_keys($row)) !== []
            || ! is_string($row['unit'] ?? null) || trim($row['unit']) === '' || mb_strlen($row['unit']) > 64
            || ! $this->validContextTotal($row['period_one'] ?? null, $row['period_one_status'] ?? null)
            || ! $this->validContextTotal($row['period_two'] ?? null, $row['period_two_status'] ?? null)
            || ! in_array($row['percentage_change_status'] ?? null, ['available', 'unavailable', 'zero_denominator'], true)
            || ! (is_int($row['difference'] ?? null) || ($row['difference'] ?? null) === null)
            || ! (is_int($row['percentage_change'] ?? null) || is_float($row['percentage_change'] ?? null) || ($row['percentage_change'] ?? null) === null)) {
            return null;
        }

        if (($row['period_one_status'] === 'unavailable' || $row['period_two_status'] === 'unavailable')
            ? ($row['difference'] !== null || $row['percentage_change'] !== null || $row['percentage_change_status'] !== 'unavailable')
            : ($row['difference'] !== $row['period_two'] - $row['period_one'])) {
            return null;
        }
        if ($row['period_one_status'] !== 'unavailable' && $row['period_two_status'] !== 'unavailable') {
            $expectedPercentageStatus = $row['period_one'] === 0 ? 'zero_denominator' : 'available';
            if ($row['percentage_change_status'] !== $expectedPercentageStatus
                || ($expectedPercentageStatus === 'zero_denominator' && $row['percentage_change'] !== null)) {
                return null;
            }
        }

        if ($summary['months'][0]['value'] === $summary['months'][1]['value']) {
            return null;
        }

        return $summary;
    }

    public function isComparisonFollowUp(string $question, array $summary): bool
    {
        $normalized = mb_strtolower(trim($question));
        if ($this->isComparisonRequest($normalized)
            || preg_match('/\b(?:system|inventory)\s+summary\b/u', $normalized) === 1) {
            return false;
        }

        $hasReference = preg_match('/\b(?:that|this|those|it|there|same|comparison|compared|result|period|month|months)\b/u', $normalized) === 1;
        $hasComparisonCue = preg_match('/\b(?:activity|active|occurred|happened|zero|none|no|unavailable|history|difference|changed|increase|decrease|mean|explain|why|total|totals)\b/u', $normalized) === 1;

        return $hasReference && $hasComparisonCue;
    }

    public function comparisonFollowUpMentionsOtherPeriods(string $question, array $summary): bool
    {
        $monthNames = [
            'january', 'february', 'march', 'april', 'may', 'june',
            'july', 'august', 'september', 'october', 'november', 'december',
        ];
        $normalized = mb_strtolower($question);
        preg_match_all('/\b(' . implode('|', $monthNames) . ')\b/u', $normalized, $mentionedMonths);
        preg_match_all('/\b(?:19|20)\d{2}\b/u', $normalized, $mentionedYears);

        $storedMonths = array_map(fn (array $month): string => mb_strtolower(\Carbon\CarbonImmutable::createFromFormat('!Y-m', $month['value'])->format('F')), $summary['months']);
        $storedYears = array_map(fn (array $month): string => substr($month['value'], 0, 4), $summary['months']);

        return array_diff($mentionedMonths[1] ?? [], $storedMonths) !== []
            || array_diff($mentionedYears[0] ?? [], $storedYears) !== [];
    }

    private function validContextTotal(mixed $value, mixed $status): bool
    {
        return match ($status) {
            'available' => is_int($value) && $value > 0,
            'verified_zero' => $value === 0,
            'unavailable' => $value === null,
            default => false,
        };
    }

    public function compare(InventoryComparisonData $data): array
    {
        $itemId = null;
        $resolvedItemName = null;
        $itemUnit = null;
        if ($data->scope === 'item') {
            if ($data->itemId !== null) {
                $item = Inventory::query()->whereKey($data->itemId)->first(['item_id', 'item_name', 'unit']);
            } else {
                $matches = Inventory::query()
                    ->whereRaw('LOWER(item_name) = ?', [mb_strtolower(trim((string) $data->itemName))])
                    ->limit(2)
                    ->get(['item_id', 'item_name', 'unit']);
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['item_name' => 'That item name is ambiguous. Select a matching inventory record.']);
                }
                $item = $matches->first();
            }
            if (! $item instanceof Inventory) {
                throw ValidationException::withMessages(['item_name' => 'No inventory item exactly matches that name.']);
            }
            if ($data->itemName !== null && mb_strtolower($data->itemName) !== mb_strtolower($item->item_name)) {
                throw ValidationException::withMessages(['item_name' => 'The selected inventory record does not match the item name.']);
            }
            $resolvedItemName = $item->item_name;
            $itemId = (int) $item->item_id;
            $itemUnit = $item->unit;
        }

        $metric = self::METRICS[$data->metric] ?? null;
        if ($metric === null) {
            throw ValidationException::withMessages(['metric' => 'Choose a supported comparison metric.']);
        }

        $unit = 'Records';
        if ($metric['quantity']) {
            $unit = $itemUnit ?? (string) $data->unit;
            if ($unit === '' || mb_strlen($unit) > 64) {
                throw ValidationException::withMessages(['unit' => 'Choose one supported inventory unit for this quantity comparison.']);
            }
            if ($itemUnit !== null && $data->unit !== null && $data->unit !== $itemUnit) {
                throw ValidationException::withMessages(['unit' => 'The selected unit does not match the inventory item.']);
            }
            if ($itemId === null) {
                $canonicalUnit = Inventory::query()->where('unit', $unit)->value('unit');
                if (! is_string($canonicalUnit) || $canonicalUnit !== $unit) {
                    throw ValidationException::withMessages(['unit' => 'Choose a unit that exactly matches an inventory unit.']);
                }
            }
        } elseif ($data->unit !== null) {
            throw ValidationException::withMessages(['unit' => 'A unit can only be selected for a quantity comparison.']);
        }

        $historyUnits = [$unit];
        if (count($historyUnits) > self::MAX_SERIES) {
            throw ValidationException::withMessages(['scope' => 'This comparison would exceed the supported result limit. Narrow the scope.']);
        }

        $history = [];
        foreach ($historyUnits as $unit) {
            $history[$unit] = $this->baseQuery($data->metric, $itemId, $unit)->exists();
        }

        $monthOneStart = $data->monthOne->startOfMonth();
        $monthTwoStart = $data->monthTwo->startOfMonth();
        $first = $this->aggregate($data->metric, $itemId, $unit, $monthOneStart);
        $second = $this->aggregate($data->metric, $itemId, $unit, $monthTwoStart);
        $units = array_values(array_unique([...$historyUnits, ...array_keys($first), ...array_keys($second)]));
        if (count($units) > self::MAX_SERIES) {
            throw ValidationException::withMessages(['scope' => 'This comparison would exceed the supported result limit. Narrow the scope.']);
        }

        $series = [];
        foreach ($units as $unit) {
            $hasHistory = $history[$unit] ?? false;
            $one = $hasHistory ? ($first[$unit] ?? 0) : null;
            $two = $hasHistory ? ($second[$unit] ?? 0) : null;
            $series[] = [
                'unit' => $unit,
                'period_one' => $one,
                'period_two' => $two,
                'period_one_status' => $one === null ? 'unavailable' : ($one === 0 ? 'verified_zero' : 'available'),
                'period_two_status' => $two === null ? 'unavailable' : ($two === 0 ? 'verified_zero' : 'available'),
                'difference' => $one === null || $two === null ? null : $two - $one,
                'percentage_change' => $one === null || $two === null || $one === 0
                    ? null
                    : round((($two - $one) / $one) * 100, 2),
                'percentage_change_status' => $one === null || $two === null
                    ? 'unavailable'
                    : ($one === 0 ? 'zero_denominator' : 'available'),
            ];
        }

        $monthOneLabel = $monthOneStart->format('F Y');
        $monthTwoLabel = $monthTwoStart->format('F Y');
        $scopeLabel = $resolvedItemName ?? 'All inventory';
        $explanation = $this->resultExplanation($metric['label'], $scopeLabel, $monthOneLabel, $monthTwoLabel, $series[0]);

        return [
            'metric' => $data->metric,
            'metric_label' => $metric['label'],
            'scope' => $data->scope,
            'scope_label' => $scopeLabel,
            'explanation' => $explanation,
            'months' => [
                ['value' => $monthOneStart->format('Y-m'), 'label' => $monthOneLabel],
                ['value' => $monthTwoStart->format('Y-m'), 'label' => $monthTwoLabel],
            ],
            'series' => $series,
            'chart' => [
                'categories' => [$monthOneLabel, $monthTwoLabel],
                'unit' => $unit,
                'statuses' => [
                    $this->activityStatusLabel($series[0]['period_one_status']),
                    $this->activityStatusLabel($series[0]['period_two_status']),
                ],
                'series' => array_map(fn (array $row): array => [
                    'name' => $row['unit'],
                    'data' => [$row['period_one'], $row['period_two']],
                ], $series),
            ],
        ];
    }

    private function resultExplanation(
        string $metricLabel,
        string $scopeLabel,
        string $monthOneLabel,
        string $monthTwoLabel,
        array $series,
    ): string {
        $note = match ($series['percentage_change_status']) {
            'zero_denominator' => "Percentage change is unavailable because the {$monthOneLabel} total is zero.",
            'unavailable' => 'Percentage change is unavailable because one or both period totals are unavailable.',
            default => 'Percentage change is shown in the table.',
        };

        return $metricLabel.' for '.$scopeLabel."\n"
            .$monthOneLabel.': '.$this->activityStatusLabel($series['period_one_status'])."\n"
            .$monthTwoLabel.': '.$this->activityStatusLabel($series['period_two_status'])."\n\n"
            .$note;
    }

    private function activityStatusLabel(string $status): string
    {
        return match ($status) {
            'unavailable' => 'history unavailable',
            'verified_zero' => 'verified zero activity',
            default => 'activity recorded',
        };
    }

    private function aggregate(string $metric, ?int $itemId, string $unit, \Carbon\CarbonImmutable $month): array
    {
        $query = $this->baseQuery($metric, $itemId, $unit);
        $dateColumn = $this->dateColumn($metric);
        $query->where($dateColumn, '>=', $month->startOfMonth()->toDateString())
            ->where($dateColumn, '<', $month->addMonth()->startOfMonth()->toDateString());

        $quantity = self::METRICS[$metric]['quantity'];
        $query->selectRaw($quantity
            ? 'inventory.unit as unit, SUM('.$this->quantityExpression($metric).') as total'
            : "'Records' as unit, COUNT(*) as total");
        if ($quantity) {
            $query->groupBy('inventory.unit')->orderBy('inventory.unit')->limit(self::MAX_SERIES + 1);
        }

        return $query->get()->mapWithKeys(fn ($row): array => [(string) $row->unit => (int) $row->total])->all();
    }

    private function baseQuery(string $metric, ?int $itemId, ?string $unit = null): Builder
    {
        $query = match ($metric) {
            'stock_in_quantity', 'stock_out_quantity', 'assignment_count', 'assignment_quantity',
            'transfer_count', 'transfer_quantity', 'disposal_quantity' => DB::table('stock_movements')
                ->join('inventory', 'inventory.item_id', '=', 'stock_movements.inventory_id')
                ->whereIn('stock_movements.movement_type', match ($metric) {
                    'stock_in_quantity' => ['stock_in'],
                    'stock_out_quantity' => ['stock_out'],
                    'assignment_count', 'assignment_quantity' => ['assignment'],
                    'transfer_count', 'transfer_quantity' => ['transfer'],
                    default => ['disposed'],
                }),
            'request_count' => DB::table('requests')->whereNull('parent_request_id'),
            'request_quantity' => DB::table('requests')
                ->join('inventory', 'inventory.item_id', '=', 'requests.item_id')
                ->whereNull('requests.parent_request_id')
                ->whereNotNull('requests.item_id'),
            'maintenance_count' => DB::table('maintenance_records')
                ->join('inventory', 'inventory.item_id', '=', 'maintenance_records.inventory_id')
                ->where('maintenance_records.status', 'completed')
                ->whereNotNull('maintenance_records.completed_at'),
            default => throw ValidationException::withMessages(['metric' => 'Choose a supported comparison metric.']),
        };

        // These branches are raw query-builder joins, which never see the
        // Inventory and StockMovement global scopes, so sample forecast rows
        // would otherwise be counted here and reported by the assistant as
        // though they described real stock. The join is an inner join, so
        // excluding the item is enough to exclude its movements and requests.
        //
        // Mirrors Inventory's own scope rather than filtering on
        // inventory.description: the assistant must not answer from that
        // column, so it must not appear in the query either.
        if (collect($query->joins ?? [])->contains(fn ($join) => ($join->table ?? '') === 'inventory')) {
            $query->whereNotExists(function ($movements) {
                $movements->selectRaw('1')
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.inventory_id', 'inventory.item_id')
                    ->where(fn ($notes) => $notes
                        ->where('stock_movements.notes', 'like', SampleForecastData::MOVEMENT_PREFIX.'%')
                        ->orWhere('stock_movements.notes', 'like', SampleForecastData::LEGACY_MOVEMENT_PREFIX.'%'));
            });
        }

        if ($itemId !== null) {
            $query->where($metric === 'request_count' ? 'requests.item_id' : 'inventory.item_id', $itemId);
        }
        if ($unit !== null && self::METRICS[$metric]['quantity'] && $query->joins !== null) {
            $query->where('inventory.unit', $unit);
        }

        return $query;
    }

    private function unitOptions(): array
    {
        return Inventory::query()->select('unit')->distinct()->whereNotNull('unit')->where('unit', '<>', '')
            ->orderBy('unit')->limit(self::MAX_UNIT_OPTIONS)->pluck('unit')
            ->map(fn ($unit): string => (string) $unit)->all();
    }

    private function quantityExpression(string $metric): string
    {
        return match ($metric) {
            'stock_in_quantity', 'stock_out_quantity', 'assignment_quantity', 'transfer_quantity', 'disposal_quantity' => 'stock_movements.quantity',
            'request_quantity' => 'requests.quantity',
            default => '0',
        };
    }

    private function dateColumn(string $metric): string
    {
        return match ($metric) {
            'stock_in_quantity', 'stock_out_quantity', 'assignment_count', 'assignment_quantity',
            'transfer_count', 'transfer_quantity', 'disposal_quantity' => 'stock_movements.created_at',
            'request_count', 'request_quantity' => 'requests.requested_at',
            'maintenance_count' => 'maintenance_records.completed_at',
            default => throw ValidationException::withMessages(['metric' => 'Choose a supported comparison metric.']),
        };
    }

    private function recognizedMetric(string $message): ?string
    {
        $message = mb_strtolower($message);
        $entities = array_values(array_filter([
            preg_match('/\b(?:stock[_ -]?in|received)\b/i', $message) === 1 ? 'stock_in_quantity' : null,
            preg_match('/\bstock[_ -]?out\b/i', $message) === 1 ? 'stock_out_quantity' : null,
            preg_match('/\brequests?\b/i', $message) === 1 ? 'request' : null,
            preg_match('/\bassignments?\b/i', $message) === 1 ? 'assignment' : null,
            preg_match('/\btransfers?\b/i', $message) === 1 ? 'transfer' : null,
            preg_match('/\bmaintenance\b/i', $message) === 1 ? 'maintenance_count' : null,
            preg_match('/\bdisposals?\b/i', $message) === 1 ? 'disposal_quantity' : null,
        ]));

        if (count($entities) !== 1) {
            return null;
        }

        $entity = $entities[0];
        if (in_array($entity, ['stock_in_quantity', 'stock_out_quantity'], true)) {
            return $entity;
        }

        $quantityRequested = preg_match('/\b(?:quantit(?:y|ies)|units?)\b/i', $message) === 1;
        $countRequested = preg_match('/\b(?:counts?|number of|frequency|records?|cases?)\b/i', $message) === 1;
        if ($quantityRequested === $countRequested) {
            return null;
        }

        return match ($entity) {
            'request' => $quantityRequested ? 'request_quantity' : 'request_count',
            'assignment' => $quantityRequested ? 'assignment_quantity' : 'assignment_count',
            'transfer' => $quantityRequested ? 'transfer_quantity' : 'transfer_count',
            'maintenance_count' => $countRequested ? 'maintenance_count' : null,
            'disposal_quantity' => $quantityRequested ? 'disposal_quantity' : null,
            default => null,
        };
    }

    private function recognizedMonths(string $message): array
    {
        preg_match_all('/\b(?:this month|(?:last|previous) month|20\d{2}-(?:0[1-9]|1[0-2])|[A-Za-z]+\s+20\d{2})\b/i', $message, $matches, PREG_OFFSET_CAPTURE);
        $periods = [];
        foreach ($matches[0] as [$phrase]) {
            try {
                $month = match (mb_strtolower($phrase)) {
                    'this month' => now()->toImmutable()->startOfMonth(),
                    'last month', 'previous month' => now()->toImmutable()->subMonthNoOverflow()->startOfMonth(),
                    default => str_contains($phrase, '-')
                        ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $phrase)
                        : \Carbon\CarbonImmutable::createFromFormat('!F Y', trim($phrase)),
                };
            } catch (\Throwable) {
                continue;
            }
            if ($month === false) {
                continue;
            }
            $periods[] = ['value' => $month->format('Y-m'), 'label' => $month->format('F Y')];
        }

        return count($periods) === 2 ? $periods : [];
    }
}