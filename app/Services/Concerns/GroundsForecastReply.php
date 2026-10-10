<?php

namespace App\Services\Concerns;

use App\Services\ForecastDecisionSupportService;
use Illuminate\Support\Collection;

/**
 * Grounding rules shared by every forecast-grounded AI surface.
 *
 * Two surfaces read the stored forecast and let a provider write the prose:
 * the AI Decision Support chat and the Recommendations tab. The checks that
 * decide whether a provider reply may be shown live here rather than being
 * copied into each service, because duplicating a safety gate is how one of
 * them eventually stops being applied.
 *
 * The contract is the same for both: the server computes the numbers, the
 * provider only words them, and every reply is validated against the approved
 * facts before a user sees it. A rejected reply falls back to a deterministic
 * answer built from the same facts.
 */
trait GroundsForecastReply
{
    /**
     * Confidence ordering used when ranking rows that need verification.
     *
     * A trait constant, so both surfaces rank identically. PHP 8.2 supports
     * this and the project requires ^8.2.
     */
    private const CONFIDENCE_SCORE = [
        'Low' => 0,
        'Medium' => 1,
        'High' => 2,
    ];

    /**
     * A whole, standalone quantity. `(?!\d)` matters: without it the regex can
     * backtrack to a shorter prefix, so "300ml" in an item name would be read as
     * the quantity 30. A digit run glued to a letter is part of a product name,
     * not a claim about stock.
     */
    private const QUANTITY_PATTERN = '/(?<![\pL\d])\d+(?:[,.]\d+)*(?!\d)(?![\pL])/u';

    /**
     * A provider reply is accepted only when it mentions a selected item, does
     * not claim to have acted, and contains no number the forecast did not
     * calculate. On rejection the failing check is returned so the caller can
     * log a real cause instead of a blanket "provider unavailable".
     */
    private function isGroundedReply(string $reply, array $facts): bool
    {
        return $this->groundingFailure($reply, $facts) === null;
    }

    /**
     * @return string|null The name of the first failing check, or null when the
     *                     reply is acceptable.
     */
    private function groundingFailure(string $reply, array $facts): ?string
    {
        $reply = trim($reply);
        $items = $facts['items'] ?? [];

        if ($reply === '' || ! is_array($items) || $items === []) {
            return 'no_items';
        }

        // Recommending what to procure is this layer's whole job, so "buy" and
        // "purchase" are allowed. What is forbidden is claiming the assistant
        // acted: no order placed or approved, no inventory or stock changed, and
        // no price or supplier, because the forecast carries no cost data and a
        // fabricated peso figure would be the most damaging possible error here.
        if ($this->forbiddenClaim($reply) !== null) {
            return 'forbidden_claim';
        }

        // A whole-cycle brief is about the shape of the forecast, not specific
        // items, so it legitimately references counts alone. Such a surface sets
        // `requires_item_name => false`. Everything else defaults to the
        // historical rule, which is to name a real selected item.
        if ($this->requiresItemName($facts)) {
            $names = collect($items)->pluck('item_name')->filter(fn (mixed $name): bool => is_string($name) && $name !== '');
            if ($names->isEmpty() || $names->first(fn (string $name): bool => stripos($reply, $name) !== false) === null) {
                return 'no_item_named';
            }
        }

        return $this->unapprovedNumbers($reply, $facts) === [] ? null : 'unapproved_number';
    }

    /**
     * Whether this reply has to name at least one supplied item.
     *
     * Defaults to exempting next-steps guidance, which is about the workflow,
     * and to requiring a name everywhere else. That was the rule before any
     * other surface needed the exemption, so leaving it as the default keeps
     * ForecastDecisionSupportService behaving exactly as its tests assert.
     */
    private function requiresItemName(array $facts): bool
    {
        if (array_key_exists('requires_item_name', $facts)) {
            return (bool) $facts['requires_item_name'];
        }

        return ($facts['prompt_type'] ?? null) !== ForecastDecisionSupportService::PROMPT_NEXT_STEPS;
    }

    /**
     * Detects a reply claiming the system acted, or quoting a cost the forecast
     * never supplied.
     */
    private function forbiddenClaim(string $reply): ?string
    {
        $patterns = [
            'acted_on_order' => '/\b(?:i\s+(?:have\s+)?(?:placed|created|submitted|approved)\s+(?:the\s+|a\s+|your\s+)?(?:order|purchase)|order\s+(?:has\s+been|was)\s+(?:placed|approved)|(?:placed|approved)\s+(?:the\s+|a\s+)?(?:purchase\s+)?order)\b/iu',
            'changed_inventory' => '/\b(?:update[ds]?\s+(?:the\s+)?inventory|changed?\s+(?:the\s+)?stock)\b/iu',
            'invented_cost' => '/(?:₱|\bphp\b|\bphp\s+\d)/iu',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $reply) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The numbers a reply states that the forecast never calculated.
     *
     * Approved values are the per-item quantities, the forecast period and its
     * generated-at stamp, the summary totals, the server-computed cycle counts,
     * the list sizes this layer chose, and digits inside supplied item and
     * category names. That last group matters: "Crayon Set 24 Colors" contains a
     * standalone 24, and a reply quoting that name correctly must not be
     * rejected for it. Only digit runs glued to a letter (300ml) are excluded,
     * by QUANTITY_PATTERN.
     *
     * @return array<int, string>
     */
    private function unapprovedNumbers(string $reply, array $facts): array
    {
        // The unmet-requests prompt supplies its list under a different key
        // because its rows have a different shape (no forecast_demand, no
        // suggested_procurement). Both are approved fact sources, so a reply
        // quoting either set of computed figures is grounded.
        $items = array_merge(
            (array) ($facts['items'] ?? []),
            (array) ($facts['unmet_demand'] ?? []),
        );

        $approved = collect(is_array($items) ? $items : [])
            ->flatMap(fn (array $item): array => [
                $item['forecast_demand'] ?? null,
                $item['safety_stock'] ?? null,
                $item['available_stock'] ?? null,
                $item['pending_demand'] ?? null,
                $item['unmet_demand'] ?? null,
                $item['unmet_requesters'] ?? null,
                $item['suggested_procurement'] ?? null,
                $item['total_quantity'] ?? null,
                $item['requester_count'] ?? null,
                // History depth. Without these a reply that honestly says "this
                // figure comes from only 3 verified months" was rejected, so the
                // tab could count weak rows but never explain one.
                $item['historical_months_used'] ?? null,
                $item['required_months'] ?? null,
                $item['unknown_months'] ?? null,
                // Unusual-consumption figures. A sentence that says "recent usage
                // is three times its usual rate" is restating a ratio the server
                // calculated, so the ratio, the baseline it was measured against
                // and the depth of history behind it are all approved facts.
                $item['usage_ratio'] ?? null,
                $item['usage_baseline'] ?? null,
                $item['usage_latest_quantity'] ?? null,
                $item['usage_months_compared'] ?? null,
                // Category totals, so a brief may say "School Supplies carries
                // the most pressure" with the aggregate beside it.
                $item['items_with_gap'] ?? null,
                $item['suggested_units'] ?? null,
            ])
            ->filter(fn (mixed $value): bool => is_int($value) || is_float($value))
            ->map(fn (int|float $value): string => (string) $value);

        foreach ([$facts['forecast_period'] ?? null, $facts['generated_at'] ?? null] as $stamp) {
            if (! is_string($stamp)) {
                continue;
            }

            preg_match_all('/\d+/', $stamp, $stampNumbers);
            $approved = $approved->merge($stampNumbers[0]);
        }

        foreach ((array) ($facts['forecast_summary'] ?? []) as $total) {
            if (is_int($total) || is_float($total)) {
                $approved->push((string) $total);
            }
        }

        // The server-computed cycle counts (urgent, high, items_with_gap,
        // suggested_units, …) are approved facts too. A reply that quotes "the
        // 62 High priority items" is restating a count this layer calculated,
        // and rejecting it pushed every well-formed answer back to a template.
        foreach ((array) ($facts['cycle_state'] ?? []) as $count) {
            if (is_int($count) || is_float($count)) {
                $approved->push((string) $count);
            }
        }

        // Selection-scoped aggregates for the explanation prompt (why_these).
        // Only that surface supplies the key, so every other surface's
        // allow-list is unchanged by this.
        foreach ((array) ($facts['selection_state'] ?? []) as $count) {
            if (is_int($count) || is_float($count)) {
                $approved->push((string) $count);
            }
        }

        foreach ((array) ($facts['by_category'] ?? []) as $category) {
            if (! is_array($category)) {
                continue;
            }

            foreach (['items', 'items_with_gap', 'suggested_units'] as $field) {
                if (is_int($category[$field] ?? null) || is_float($category[$field] ?? null)) {
                    $approved->push((string) $category[$field]);
                }
            }
        }

        // The size of the list this layer selected, so a reply may count its own
        // items without that count being read as fabricated data.
        foreach (['selected_count', 'max_items_shown', 'queue_item_count'] as $size) {
            if (is_int($facts[$size] ?? null)) {
                $approved->push((string) $facts[$size]);
            }
        }

        // Digits inside supplied item and category names are approved facts too.
        foreach (collect(is_array($items) ? $items : []) as $item) {
            foreach (['item_name', 'category'] as $field) {
                if (! is_string($item[$field] ?? null)) {
                    continue;
                }

                preg_match_all('/\d+/', $item[$field], $nameNumbers);
                $approved = $approved->merge($nameNumbers[0]);
            }
        }

        $approved = $approved->unique()->values();

        preg_match_all(self::QUANTITY_PATTERN, $this->stripListOrdinals($reply), $matches);

        return collect($matches[0])
            ->map(fn (string $number): string => str_replace(',', '', $number))
            ->reject(fn (string $number): bool => $approved->contains($number))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Removes list position markers before the number check.
     *
     * A position ("1.", "2)") is not a claim about a quantity, and both the
     * local answer and a provider reply may legitimately number their items.
     * Handles markers at the start of a line and after a sentence break, since
     * a numbered list is not always written one item per line.
     */
    private function stripListOrdinals(string $text): string
    {
        return (string) preg_replace('/(?:(?<=\.)|^)\s*\d{1,3}[.)]\s+/m', '', $text);
    }

    /**
     * Confidence as a sortable score, with a row that has no estimate ranked
     * lowest because nothing about it can be relied on yet.
     */
    private function confidenceScore(array $row): int
    {
        if (($row['status'] ?? null) !== 'success') {
            return self::CONFIDENCE_SCORE['Low'];
        }

        return self::CONFIDENCE_SCORE[$row['confidence'] ?? ''] ?? self::CONFIDENCE_SCORE['High'];
    }

    /**
     * Aggregate counts over the whole forecast.
     *
     * Computed here rather than narrated by the provider, so every count a
     * surface states traces to the model output instead of being improvised.
     *
     * @return array<string, int>
     */
    private function cycleState(Collection $rows): array
    {
        $gaps = $rows->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true);

        return [
            'items_forecasted' => $rows->where('status', 'success')->count(),
            'items_with_gap' => $gaps->count(),
            'urgent' => $gaps->where('priority', 'Urgent')->count(),
            'high' => $gaps->where('priority', 'High')->count(),
            'medium' => $gaps->where('priority', 'Medium')->count(),
            'deferrable' => $rows->filter(fn (array $row): bool => ($row['status'] ?? null) === 'success'
                && ($row['needs_procurement'] ?? false) === false)->count(),
            'insufficient_history' => $rows->where('status', 'insufficient_history')->count(),
            // Rows matching the verify-first filter across the WHOLE forecast,
            // not just a selection. Without it the provider states this total
            // itself, which the grounding check then rejects as an invented
            // figure, so a correct reply was silently downgraded.
            'verify_first_total' => $rows->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
            // Rows that would be acted on but rest on weak history. These are the
            // ones worth a verification step; counting every Low/Medium row,
            // including items nobody is buying, produced a warning about all of
            // them.
            'gaps_needing_verification' => $gaps->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
            'suggested_units' => (int) $gaps->sum(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0)),
        ];
    }

    /**
     * One entry in the deterministic ranked list.
     *
     * Uses the same two-line shape the provider is instructed to produce, so the
     * fallback reads identically rather than switching to a wall of prose. The
     * formula is stated once by the caller, not repeated per item.
     */
    private function localItemLine(array $item): string
    {
        $name = $item['item_name'] ?? 'Item';
        $unit = $item['unit'] ?? 'units';

        if (($item['status'] ?? null) !== 'success') {
            return "**{$name}** — no estimate\nInsufficient verified history · verify usage before procuring.";
        }

        $suggested = (int) ($item['suggested_procurement'] ?? 0);
        // Unmet demand is shown in the figure line even at zero, so the formula
        // the custodian reads matches the formula that was actually applied.
        $figures = sprintf(
            '%s + %s - %s - %s + %s',
            $item['forecast_demand'],
            $item['safety_stock'],
            $item['available_stock'],
            $item['pending_demand'],
            $item['unmet_demand'] ?? 0
        );

        if ($suggested <= 0) {
            return "**{$name}** — 0 {$unit}\n{$item['priority']} · {$item['confidence']} confidence · {$figures}";
        }

        return "**{$name}** — {$suggested} {$unit}\n{$item['priority']} · {$item['confidence']} confidence · {$figures}";
    }
}
