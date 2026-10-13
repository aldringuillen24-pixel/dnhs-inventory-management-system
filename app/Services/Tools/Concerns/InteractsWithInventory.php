<?php

namespace App\Services\Tools\Concerns;

use App\Models\Category;
use App\Models\Inventory;
use Carbon\CarbonImmutable;

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
    /**
     * Resolve only explicit ISO date ranges or bounded "last N months" phrases.
     *
     * @return array{start: ?CarbonImmutable, end: ?CarbonImmutable, error: ?string}
     */
    protected function analyticsDateRange(array $request, int $defaultMonths = 3): array
    {
        $question = (string) ($request['original_question'] ?? '');
        $now = CarbonImmutable::now();
        $start = null;
        $end = $now->endOfDay();

        if (preg_match('/\bfrom\s+(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})\b/i', $question, $matches) === 1) {
            try {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $matches[1]);
                $rangeEnd = CarbonImmutable::createFromFormat('!Y-m-d', $matches[2]);
                if ($start->format('Y-m-d') !== $matches[1] || $rangeEnd->format('Y-m-d') !== $matches[2]) {
                    throw new \InvalidArgumentException;
                }
                $end = $rangeEnd->endOfDay();
            } catch (\Throwable) {
                return ['start' => null, 'end' => null, 'error' => 'Use valid dates in YYYY-MM-DD format.'];
            }

            if ($start->greaterThan($end) || $start->diffInDays($end) > 3660) {
                return ['start' => null, 'end' => null, 'error' => 'The date range must be ordered and no longer than ten years.'];
            }

            return ['start' => $start->startOfDay(), 'end' => $end, 'error' => null];
        }

        if (preg_match('/\blast\s+(one|two|three|four|five|six|seven|eight|nine|ten|\d{1,2})\s+months?\b/i', $question, $matches) === 1) {
            $months = [
                'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
                'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
            ][$matches[1]] ?? (int) $matches[1];
            if ($months < 1 || $months > 120) {
                return ['start' => null, 'end' => null, 'error' => 'Choose a period from one to 120 months.'];
            }
            $start = $now->subMonthsNoOverflow($months)->startOfDay();
        } elseif ($defaultMonths > 0) {
            $start = $now->subMonthsNoOverflow($defaultMonths)->startOfDay();
        }

        return ['start' => $start, 'end' => $end, 'error' => null];
    }

    protected function filterMaintenanceDateRange($query, array $range): void
    {
        if ($range['start'] === null || $range['end'] === null) {
            return;
        }

        $query->where(function ($dateQuery) use ($range): void {
            $dateQuery->whereBetween('started_at', [$range['start'], $range['end']])
                ->orWhere(function ($createdQuery) use ($range): void {
                    $createdQuery->whereNull('started_at')
                        ->whereBetween('created_at', [$range['start'], $range['end']]);
                });
        });
    }

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

    /**
     * A holder mention in the raw question, if any.
     *
     * Only assignment phrasing counts ("assigned/issued to X", "held by X",
     * "X's …", "my assignments"): a bare name elsewhere in the sentence is
     * not a holder filter. Returns the raw mention, or 'me' handling is left
     * to the resolver.
     */
    protected function holderMention(array $request): ?string
    {
        $question = trim((string) ($request['original_question'] ?? ''));
        if ($question === '') {
            return null;
        }

        $name = '[^\s?.,;!]+(?:\s+[^\s?.,;!]+){0,2}';
        if (preg_match('/\bassigned\s+to\s+(me|myself)\b/i', $question) === 1
            || preg_match('/\bmy\s+assignments?\b/i', $question) === 1) {
            return 'me';
        }
        foreach ([
            '/\bassigned\s+to\s+(' . $name . ')/iu',
            '/\bissued\s+to\s+(' . $name . ')/iu',
            '/\bheld\s+by\s+(' . $name . ')/iu',
        ] as $pattern) {
            if (preg_match($pattern, $question, $matches) === 1 && trim($matches[1]) !== '') {
                return trim($matches[1]);
            }
        }

        if (preg_match('/\b([A-Z][\p{L}\'.-]*(?:\s+[A-Z][\p{L}\'.-]*){0,2})\'s\b/u', $question, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Resolve a holder mention to a user, if it names exactly one.
     *
     * Matching is by full name or username, exact first then substring, all
     * in PHP so no raw SQL is involved. Zero matches is 'unmatched', more
     * than one is 'ambiguous'; both use the existing clarification shape.
     * Display names only cross into replies — never emails or contacts.
     *
     * @return array{mention: ?string, status: string, user: ?\App\Models\User, candidates: array<int, string>}
     */
    protected function resolveAssignmentHolder(array $request, ?object $user = null): array
    {
        $none = ['mention' => null, 'status' => 'none', 'user' => null, 'candidates' => []];
        $mention = $this->holderMention($request);
        if ($mention === null || $mention === '') {
            return $none;
        }

        if ($user !== null && preg_match('/^(me|myself)$/i', $mention) === 1) {
            return ['mention' => $mention, 'status' => 'self', 'user' => $user, 'candidates' => []];
        }

        $needle = mb_strtolower($mention);
        $users = \App\Models\User::query()->get(['id', 'first_name', 'last_name', 'username']);
        $displayOf = fn ($holder): string => trim($holder->full_name) !== ''
            ? trim($holder->full_name)
            : (string) $holder->username;

        $exact = $users->filter(fn ($holder): bool =>
            mb_strtolower($displayOf($holder)) === $needle
            || mb_strtolower((string) $holder->username) === $needle)->values();
        $matches = $exact->isNotEmpty() ? $exact : $users->filter(fn ($holder): bool =>
            str_contains(mb_strtolower($displayOf($holder)), $needle)
            || str_contains(mb_strtolower((string) $holder->username), $needle))->values();

        if ($matches->isEmpty()) {
            return ['mention' => $mention, 'status' => 'unmatched', 'user' => null, 'candidates' => []];
        }

        if ($matches->count() > 1) {
            $names = $matches->map($displayOf);
            $candidates = $matches->values()->map(function ($holder) use ($displayOf, $names): string {
                $label = $displayOf($holder);
                return $names->filter(fn ($other): bool => $other === $label)->count() > 1
                    ? "{$label} ({$holder->username})"
                    : $label;
            })->values()->all();

            return ['mention' => $mention, 'status' => 'ambiguous', 'user' => null, 'candidates' => $candidates];
        }

        return ['mention' => $mention, 'status' => 'resolved', 'user' => $matches->first(), 'candidates' => []];
    }

    /**
     * Escape a value for use inside a LIKE pattern.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
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