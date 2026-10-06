<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Forecast-only decision support for the property custodian.
 *
 * The ML model supplies the numbers; this service only decides *which* rows a
 * question is about and in what order. The ranked selection is computed locally
 * from the stored forecast so it can never disagree with the table or the
 * exported PDF. The provider (Gemini) is used for the prose only, and every
 * reply is validated against the approved facts before it is shown. If the key
 * is missing, the provider fails, or the reply is not grounded, the local
 * deterministic answer is used instead.
 */
class ForecastDecisionSupportService
{
    public const PROMPT_PURCHASE_FIRST = 'purchase_first';
    public const PROMPT_DEFERRABLE = 'deferrable';
    public const PROMPT_VERIFY_FIRST = 'verify_first';
    public const PROMPT_NEXT_STEPS = 'next_steps';

    public const PROMPT_TYPES = [
        self::PROMPT_PURCHASE_FIRST,
        self::PROMPT_DEFERRABLE,
        self::PROMPT_VERIFY_FIRST,
        self::PROMPT_NEXT_STEPS,
    ];

    /** Maximum rows returned to the caller or listed on the exported PDF. */
    public const MAX_SELECTED_ITEMS = 10;

    /** Confidence ordering used when ranking rows that need verification. */
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

    public function __construct(
        protected AiCapabilityPolicy $policy,
        protected GeminiApiService $geminiApi,
        protected StoredDemandForecastService $storedForecastService,
    )
    {
    }

    public static function questionLabel(string $promptType): string
    {
        return match ($promptType) {
            self::PROMPT_DEFERRABLE => 'What can wait?',
            self::PROMPT_VERIFY_FIRST => 'Which rows need verification first?',
            self::PROMPT_NEXT_STEPS => 'What step should I do next?',
            default => 'What should we purchase first?',
        };
    }

    /**
     * Answers a decision question, optionally narrowed by the previous answer.
     *
     * $context carries the prior exchange as ids only — never free prose — so a
     * follow-up can only ever narrow or re-explain items the forecast already
     * supplied. It cannot introduce an outside item.
     *
     * @param  array{inventory_ids?:array<int>,prompt_type?:string}  $context
     * @return array{status:string,message?:string,answer?:string,source?:string,provider_status?:string,prompt_type?:string,question?:string,inventory_ids?:array<int>,items?:array<int,array<string,mixed>>,forecast_period?:?string,generated_at?:?string,summary?:array<string,mixed>,scope?:string}
     */
    public function answer(User $user, string $promptType, array $context = []): array
    {
        if (! in_array($promptType, self::PROMPT_TYPES, true)) {
            return ['status' => 'error', 'message' => 'That decision question is not supported.'];
        }

        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES)) {
            return ['status' => 'forbidden', 'message' => 'That information is not available for your role.'];
        }

        $forecast = $this->storedForecastService->read($user);
        if (($forecast['status'] ?? null) === 'forbidden') {
            return ['status' => 'forbidden', 'message' => 'That information is not available for your role.'];
        }
        if (($forecast['status'] ?? null) !== 'success') {
            return [
                'status' => 'error',
                'message' => 'No trained production ML forecast is available. Model training runs separately from chat and reports.',
            ];
        }

        $rows = collect($forecast['rows'] ?? []);
        $selection = $this->resolveSelection($rows, $promptType, $context);

        $selected = $selection['rows'];
        $facts = $this->buildFacts($forecast, $selected, $promptType, $selection['scope']);
        $localAnswer = $this->localAnswer($facts);

        $answer = $localAnswer;
        $source = 'local';
        $providerStatus = 'not_attempted';

        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            $providerStatus = 'no_key';
        } else {
            $reply = $this->geminiApi->generate(
                $this->systemPrompt($promptType),
                json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '',
                ['temperature' => 0.2, 'maxOutputTokens' => 500]
            );

            if (! is_string($reply) || trim($reply) === '') {
                $providerStatus = 'request_failed';
            } elseif ($this->isGroundedReply($reply, $facts)) {
                $answer = trim($reply);
                $source = 'provider';
                $providerStatus = 'ok';
            } else {
                // A reply arrived but failed grounding. Keep the local summary,
                // but record which check failed so "why did it fall back?" is
                // answerable from the logs instead of guesswork.
                $providerStatus = 'reply_rejected';
                Log::warning('Forecast decision support rejected a provider reply.', [
                    'prompt_type' => $promptType,
                    'user_id' => $user->id,
                    'failed_check' => $this->groundingFailure($reply, $facts),
                    'forbidden_claim' => $this->forbiddenClaim($reply),
                    'unapproved_numbers' => $this->unapprovedNumbers(trim($reply), $facts),
                    'reply' => mb_substr(trim($reply), 0, 800),
                ]);
            }
        }

        return [
            'status' => 'success',
            'source' => $source,
            'provider_status' => $providerStatus,
            'answer' => $answer,
            'prompt_type' => $promptType,
            'question' => self::questionLabel($promptType),
            'inventory_ids' => $selected->pluck('inventory_id')->map(fn (mixed $id): int => (int) $id)->all(),
            'items' => $selected->map(fn (array $row): array => $this->presentRow($row))->all(),
            'forecast_period' => $forecast['forecast_period'] ?? null,
            'generated_at' => $forecast['generated_at'] ?? null,
            'summary' => $forecast['summary'] ?? [],
            'scope' => $selection['scope'],
        ];
    }

    /**
     * Decides which rows a question is about.
     *
     * A follow-up may only NARROW the previous answer. When the client sends the
     * prior answer's inventory ids, the selection is restricted to those rows,
     * so "why is the second one urgent?" is answered about that item and cannot
     * be used to pull an outside item into the thread. Ids that are no longer in
     * the forecast are dropped, never trusted.
     *
     * @param  array{inventory_ids?:array<int>,prompt_type?:string}  $context
     * @return array{rows: \Illuminate\Support\Collection, scope: string}
     */
    private function resolveSelection(Collection $rows, string $promptType, array $context): array
    {
        $priorIds = array_values(array_filter(array_map(
            'intval',
            (array) ($context['inventory_ids'] ?? [])
        ), fn (int $id): bool => $id > 0));

        if ($priorIds === []) {
            return ['rows' => $this->selectRows($rows, $promptType), 'scope' => 'full'];
        }

        $known = $rows->whereIn('inventory_id', $priorIds)->values();

        if ($known->isEmpty()) {
            // Every prior id is gone (items deleted, model retrained). Fall back
            // to the full ranking rather than answering about nothing.
            return ['rows' => $this->selectRows($rows, $promptType), 'scope' => 'full'];
        }

        // Preserve the order the previous answer established, so "the second
        // one" still means the same item.
        $ordered = collect($priorIds)
            ->map(fn (int $id) => $known->firstWhere('inventory_id', $id))
            ->filter()
            ->take(self::MAX_SELECTED_ITEMS)
            ->values();

        // A follow-up asking a *different* kind of question re-filters within the
        // prior set, so "what can wait?" after a purchase-first answer only
        // considers items that were already on the list.
        return [
            'rows' => $this->refilter($ordered, $promptType),
            'scope' => 'follow_up',
        ];
    }

    /**
     * Applies the prompt's own filter to an already-restricted set.
     *
     * @return \Illuminate\Support\Collection
     */
    private function refilter(Collection $rows, string $promptType): Collection
    {
        return match ($promptType) {
            self::PROMPT_DEFERRABLE => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) === 'success'
                    && ($row['needs_procurement'] ?? false) === false)
                ->values(),
            self::PROMPT_VERIFY_FIRST => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                    || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))
                ->values(),
            // purchase_first keeps the prior order: every one of these items
            // already has a gap, which is why they were on the list.
            default => $rows,
        };
    }

    /**
     * Ranks the forecast rows for a question. Ordering is deterministic and
     * derived only from fields the ML forecast already calculated.
     */
    public function selectRows(Collection $rows, string $promptType): Collection
    {
        return match ($promptType) {
            // The actionable items: everything with a gap, most urgent first.
            // Identical to purchase_first on purpose — the next-steps answer
            // reasons about these rows and exports them to the PDF.
            self::PROMPT_NEXT_STEPS => $rows
                ->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
                ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                    + (int) ($row['suggested_procurement'] ?? 0))
                ->values()
                ->take(self::MAX_SELECTED_ITEMS),
            self::PROMPT_DEFERRABLE => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) === 'success'
                    && ($row['needs_procurement'] ?? false) === false
                    && is_int($row['suggested_procurement'] ?? null))
                ->sortByDesc(fn (array $row): int => (int) ($row['available_stock'] ?? 0))
                ->values()
                ->take(self::MAX_SELECTED_ITEMS),
            self::PROMPT_VERIFY_FIRST => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                    || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))
                ->sortBy(fn (array $row): int => $this->confidenceScore($row) * 1000 + (int) ($row['priority_rank'] ?? 0))
                ->values()
                ->take(self::MAX_SELECTED_ITEMS),
            default => $rows
                ->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
                ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                    + (int) ($row['suggested_procurement'] ?? 0))
                ->values()
                ->take(self::MAX_SELECTED_ITEMS),
        };
    }

    private function confidenceScore(array $row): int
    {
        if (($row['status'] ?? null) !== 'success') {
            return self::CONFIDENCE_SCORE['Low'];
        }

        return self::CONFIDENCE_SCORE[$row['confidence'] ?? ''] ?? self::CONFIDENCE_SCORE['High'];
    }

    private function presentRow(array $row): array
    {
        return [
            'inventory_id' => (int) ($row['inventory_id'] ?? 0),
            'item_name' => $row['item_name'] ?? null,
            'category' => $row['category'] ?? null,
            'unit' => $row['unit'] ?? null,
            'forecast_demand' => $row['forecast_demand'] ?? null,
            'safety_stock' => $row['safety_stock'] ?? null,
            'available_stock' => $row['available_stock'] ?? null,
            'pending_demand' => $row['pending_demand'] ?? null,
            'suggested_procurement' => $row['suggested_procurement'] ?? null,
            'priority' => $row['priority'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'advisory_status' => $row['advisory_status'] ?? null,
            'status' => $row['status'] ?? null,
        ];
    }

    private function buildFacts(array $forecast, Collection $selected, string $promptType, string $scope = 'full'): array
    {
        $facts = [
            'prompt_type' => $promptType,
            'question' => self::questionLabel($promptType),
            // Tells the provider this is a narrowing follow-up, so it explains the
            // listed items instead of re-ranking the whole forecast.
            'scope' => $scope,
            'forecast_period' => $forecast['forecast_period'] ?? null,
            'generated_at' => $forecast['generated_at'] ?? null,
            'forecast_summary' => $forecast['summary'] ?? [],
            'selected_count' => $selected->count(),
            'max_items_shown' => self::MAX_SELECTED_ITEMS,
            'items' => $selected->map(fn (array $row): array => $this->presentRow($row))->all(),
        ];

        if ($scope === 'follow_up') {
            $facts['instruction'] = 'This is a follow-up about the specific items listed below. '
                .'Answer only about them, in the order given. Do not introduce items that are not listed.';
        }

        $facts['cycle_state'] = $this->cycleState(collect($forecast['rows'] ?? []));

        return $facts;
    }

    /**
     * Aggregate counts the next-steps answer is built from.
     *
     * These are computed here rather than narrated by the provider, so every
     * step and every number in a "what do I do next" answer traces to the model
     * output instead of being improvised.
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
            // not just this selection. Without it the provider states this total
            // itself, which the grounding check then rejects as an invented
            // figure, so a correct reply was silently downgraded.
            'verify_first_total' => $rows->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
            // Rows that would be acted on but rest on weak history. These are the
            // ones worth a verification step; counting every Low/Medium row,
            // including items nobody is buying, produced a warning about all 99.
            'gaps_needing_verification' => $gaps->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
            'suggested_units' => (int) $gaps->sum(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0)),
        ];
    }

    private function systemPrompt(string $promptType): string
    {
        $label = self::questionLabel($promptType);

        if ($promptType === self::PROMPT_NEXT_STEPS) {
            return "You are the decision support layer on top of a locally calculated inventory demand forecast.\n\n"
                ."The approved facts include a 'cycle_state' block of counts the server already computed, and an 'items' list "
                ."of the actionable rows with their exact quantities.\n\n"
                ."Answer the custodian's question: \"What step should I do next?\"\n\n"
                ."Rules:\n"
                ."1. Give a short numbered list of concrete next steps, most urgent first.\n"
                ."2. Every count and quantity you write must already appear in cycle_state or the items. Do not compute or estimate.\n"
                ."3. Order the work: Urgent items, then High, then verification of weak-history suggestions, then deferring items with no gap.\n"
                ."4. State that the final step is approval by the Property Custodian and the School Head, and that this does not create an order.\n"
                ."5. Do not invent a price, budget, supplier, delivery date, or any step outside this workflow.\n"
                ."6. Keep it under 160 words.\n";
        }

        return "You are the decision support layer on top of a locally calculated inventory demand forecast.\n\n"
            ."The approved facts below were computed by the server from verified completed stock-out history. "
            ."They are the only data you have. You cannot query a database, and you must not recalculate, adjust, "
            ."round up, or invent any quantity.\n\n"
            ."Question to answer: \"{$label}\"\n\n"
            ."Rules:\n"
            ."1. Use only the numbers present in the approved facts. Every figure you write must already appear there.\n"
            ."2. Refer to items by their item_name, exactly as written.\n"
            ."3. Do not state or estimate any price, peso amount, cost, budget, supplier, or brand. None were supplied.\n"
            ."4. You may recommend what to procure and what to defer. That is your role.\n"
            ."5. Never claim an order was placed or approved, or that inventory or stock was changed. "
            ."You only advise; a custodian approves separately.\n"
            ."6. If status is not \"success\", say the item lacks enough verified history for an estimate and should be "
            ."verified before use. Do not produce a suggested quantity for it.\n"
            ."7. Prefer a short ranked list. State the reasoning for the first few items: the arithmetic "
            ."(forecast demand + safety stock - available stock - pending demand) and the priority and confidence behind it.\n"
            ."8. Format each item as ONE line, with no blank line between items: "
            ."**Item name** — buy N unit, Priority, Confidence confidence; basis: demand X + buffer Y - stock Z - pending W. "
            ."Extra blank lines turn the chat panel into an unreadable wall of text.\n"
            ."9. Close with one line noting the forecast is advisory only.\n";
    }

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

        $names = collect($items)->pluck('item_name')->filter(fn (mixed $name): bool => is_string($name) && $name !== '');
        if ($names->isEmpty() || $names->first(fn (string $name): bool => stripos($reply, $name) !== false) === null) {
            return 'no_item_named';
        }

        return $this->unapprovedNumbers($reply, $facts) === [] ? null : 'unapproved_number';
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
     * generated-at stamp, the summary totals, and the list sizes this layer
     * itself chose. That last group matters: a reply saying "here are the top
     * 10" states the size of the list we handed it, which is a fact, not an
     * invented quantity. Rejecting it silently sent every well-written ranked
     * answer back to the local template.
     *
     * @return array<int, string>
     */
    private function unapprovedNumbers(string $reply, array $facts): array
    {
        $items = $facts['items'] ?? [];

        $approved = collect(is_array($items) ? $items : [])
            ->flatMap(fn (array $item): array => [
                $item['forecast_demand'] ?? null,
                $item['safety_stock'] ?? null,
                $item['available_stock'] ?? null,
                $item['pending_demand'] ?? null,
                $item['suggested_procurement'] ?? null,
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
        // suggested_units, …) are approved facts too. A next-steps reply that
        // quotes "the 62 High priority items" is restating a count this class
        // calculated, and rejecting it pushed every well-formed guidance answer
        // back to the local template.
        foreach ((array) ($facts['cycle_state'] ?? []) as $count) {
            if (is_int($count) || is_float($count)) {
                $approved->push((string) $count);
            }
        }

        // The size of the list this layer selected, so a reply may count its own
        // items without that count being read as fabricated data.
        foreach (['selected_count', 'max_items_shown'] as $size) {
            if (is_int($facts[$size] ?? null)) {
                $approved->push((string) $facts[$size]);
            }
        }

        // Digits inside supplied item and category names are approved facts too.
        // "Crayon Set 24 Colors" contains a standalone 24, and a reply quoting
        // that name correctly must not be rejected for it. Only digit runs
        // glued to a letter (300ml) are excluded, by QUANTITY_PATTERN.
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
     * Removes "1." / "2)" markers at the start of a line before the number check.
     * A list position is not a claim about a quantity, and both the local answer
     * and a provider reply may legitimately number their items.
     */
    private function stripListOrdinals(string $text): string
    {
        return (string) preg_replace('/^\s*\d{1,3}[.)]\s+/m', '', $text);
    }

    private function localAnswer(array $facts): string
    {
        $items = $facts['items'] ?? [];
        if (($facts['prompt_type'] ?? null) === self::PROMPT_NEXT_STEPS) {
            return $this->localNextSteps($facts);
        }

        if (! is_array($items) || $items === []) {
            return match ($facts['prompt_type'] ?? self::PROMPT_PURCHASE_FIRST) {
                self::PROMPT_DEFERRABLE => 'No item currently has stock and pending demand covering its forecast demand and safety stock, so nothing can be deferred from this forecast cycle.',
                self::PROMPT_VERIFY_FIRST => 'Every forecast row rests on enough verified completed history to be usable. No row needs verification before ordering this cycle.',
                default => 'No item in this forecast has a procurement gap, so there is nothing to purchase first this cycle.',
            };
        }

        $period = is_string($facts['forecast_period'] ?? null) ? $facts['forecast_period'] : 'this cycle';
        $lines = [$facts['question'].' ('.$period.')'];

        foreach ($items as $index => $item) {
            $lines[] = ($index + 1).'. '.$this->localItemLine($item);
        }

        $lines[] = 'This is advisory only and does not create orders. Refresh model training before acting on a new result.';

        return implode("\n", $lines);
    }

    /**
     * The "what do I do next" answer.
     *
     * Every step and every count comes from cycleState(), which is derived from
     * the forecast rows, so the guidance cannot invent a workflow step or
     * miscount. The final step describes the approval path that actually exists
     * in this application (custodian prepares, School Head approves) and states
     * plainly that nothing here creates an order.
     */
    private function localNextSteps(array $facts): string
    {
        $state = $facts['cycle_state'] ?? [];
        $items = $facts['items'] ?? [];
        $period = is_string($facts['forecast_period'] ?? null) ? $facts['forecast_period'] : 'this cycle';

        $withGap = (int) ($state['items_with_gap'] ?? 0);
        $urgent = (int) ($state['urgent'] ?? 0);
        $high = (int) ($state['high'] ?? 0);
        $medium = (int) ($state['medium'] ?? 0);
        $deferrable = (int) ($state['deferrable'] ?? 0);
        $verify = (int) ($state['gaps_needing_verification'] ?? 0);
        $insufficient = (int) ($state['insufficient_history'] ?? 0);
        $units = (int) ($state['suggested_units'] ?? 0);

        if ($withGap === 0) {
            return "Nothing needs purchasing for {$period}: no item has a procurement gap this cycle.\n"
                ."Next step: leave purchasing as is, and let the model retrain next cycle. "
                .'Model training runs on a schedule; this panel does not create orders.';
        }

        $lines = ["Next steps for {$period} ({$withGap} item".($withGap === 1 ? '' : 's')." with a gap, {$units} units):"];
        $step = 0;

        if ($urgent > 0) {
            $step++;
            $names = collect(is_array($items) ? $items : [])
                ->filter(fn (array $item): bool => ($item['priority'] ?? null) === 'Urgent')
                ->take(3)
                ->pluck('item_name')
                ->filter()
                ->implode(', ');
            $lines[] = $step.'. Purchase the '.$urgent.' Urgent item'.($urgent === 1 ? '' : 's').' first'
                .($names !== '' ? ': '.$names : '')
                .'. These have no stock left against forecast demand.';
        }

        if ($high > 0) {
            $step++;
            $lines[] = $step.'. Then work through the '.$high.' High priority item'.($high === 1 ? '' : 's')
                .'. These have a gap but still have some stock, so they are not as time-critical.'
                .($medium > 0 ? ' The remaining '.$medium.' Medium item'.($medium === 1 ? '' : 's').' can follow last.' : '');
        }

        if ($verify > 0) {
            $step++;
            $lines[] = $step.'. Before ordering, verify the '.$verify.' suggestion'.($verify === 1 ? '' : 's')
                .' that rest on fewer verified months than the others. Check current usage and stock first.';
        }

        if ($insufficient > 0) {
            $step++;
            $lines[] = $step.'. Do not procure the '.$insufficient.' item'.($insufficient === 1 ? '' : 's')
                .' with insufficient history. They show no estimate; wait for more completed months.';
        }

        if ($deferrable > 0) {
            $step++;
            $lines[] = $step.'. Defer the '.$deferrable.' item'.($deferrable === 1 ? '' : 's')
                .' with no procurement gap. Stock already covers forecast demand, so they can wait for next cycle.';
        }

        $step++;
        $lines[] = $step.'. Send the resulting list for approval: the Property Custodian prepares it and the School Head approves it. '
            .'Export the list as PDF for the signature page. This panel is advisory only and does not create orders.';

        return implode("\n", $lines);
    }

    private function localItemLine(array $item): string
    {
        $name = $item['item_name'] ?? 'Item';
        $unit = $item['unit'] ?? 'units';

        if (($item['status'] ?? null) !== 'success') {
            return "{$name} ({$item['category']}): insufficient verified history, so no ML estimate is available. Verify stock and usage before procuring.";
        }

        $suggested = (int) ($item['suggested_procurement'] ?? 0);
        $basis = sprintf(
            'demand %s + buffer %s - stock %s - pending %s',
            $item['forecast_demand'].' '.$unit,
            $item['safety_stock'].' '.$unit,
            $item['available_stock'].' '.$unit,
            $item['pending_demand'].' '.$unit
        );

        if ($suggested <= 0) {
            return "{$name} ({$item['category']}): no procurement gap; {$basis}. Stock covers the forecast, so this can be deferred.";
        }

        return "{$name} ({$item['category']}): suggested {$suggested} {$unit} on {$item['priority']} priority with {$item['confidence']} confidence. Basis: {$basis}.";
    }
}
