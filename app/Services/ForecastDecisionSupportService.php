<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\GroundsForecastReply;
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
    // The grounding rules live in a shared trait because the Recommendations tab
    // validates provider replies against the same forecast facts and must not
    // drift into a weaker copy of the safety gate.
    use GroundsForecastReply;

    public const PROMPT_PURCHASE_FIRST = 'purchase_first';
    public const PROMPT_DEFERRABLE = 'deferrable';
    public const PROMPT_VERIFY_FIRST = 'verify_first';
    public const PROMPT_NEXT_STEPS = 'next_steps';
    public const PROMPT_WHY_THESE = 'why_these';

    public const PROMPT_TYPES = [
        self::PROMPT_PURCHASE_FIRST,
        self::PROMPT_DEFERRABLE,
        self::PROMPT_VERIFY_FIRST,
        self::PROMPT_NEXT_STEPS,
        self::PROMPT_WHY_THESE,
    ];

    /** Maximum rows returned to the caller or listed on the exported PDF. */
    public const MAX_SELECTED_ITEMS = 10;

    /**
     * Maximum rows a single question may ask for.
     *
     * The default answer stays at MAX_SELECTED_ITEMS: the reply layouts are
     * written around ten rows. A custodian who names a larger number gets up
     * to this many server-ranked rows instead, with the cap disclosed rather
     * than applied silently.
     */
    public const MAX_REQUESTED_ITEMS = 25;

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
            self::PROMPT_WHY_THESE => 'Why are these items on the list?',
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
        [$maxItems, $clampedFrom] = $this->requestedMaxItems($context['max_items'] ?? null);
        $selection = $this->resolveSelection($rows, $promptType, $context, $maxItems);

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
            } elseif ($this->isGroundedReply($reply, $facts)
                && $this->listsEveryItem($reply, $facts, $promptType)) {
                $answer = trim($reply);
                $source = 'provider';
                $providerStatus = 'ok';
            } else {
                // A reply arrived but failed grounding. Keep the local summary,
                // but record which check failed so "why did it fall back?" is
                // answerable from the logs instead of guesswork.
                $failedCheck = $this->groundingFailure($reply, $facts)
                    ?? ($this->listsEveryItem($reply, $facts, $promptType) ? null : 'incomplete_list');
                $providerStatus = 'reply_rejected';
                Log::warning('Forecast decision support rejected a provider reply.', [
                    'prompt_type' => $promptType,
                    'user_id' => $user->id,
                    'failed_check' => $failedCheck,
                    'forbidden_claim' => $this->forbiddenClaim($reply),
                    'unapproved_numbers' => $this->unapprovedNumbers(trim($reply), $facts),
                    'reply' => mb_substr(trim($reply), 0, 800),
                ]);
            }
        }

        $result = [
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

        // Set only when the requested count exceeded the cap, so the chat can
        // disclose the cap instead of silently returning fewer rows. Absent
        // otherwise, keeping the response shape unchanged for existing callers.
        if ($clampedFrom !== null) {
            $result['notice'] = "Showing the top {$maxItems} of the {$clampedFrom} requested. "
                .'The chat lists at most '.self::MAX_REQUESTED_ITEMS.' items per answer — '
                .'open Demand Forecast for the full ranking and PDF export.';
        }

        return $result;
    }

    /**
     * The row cap for one answer: the requested count when sane, clamped to
     * the hard ceiling otherwise.
     *
     * @return array{0:int,1:?int} The applied cap and the requested count when
     *                             it was clamped, null when nothing was cut.
     */
    private function requestedMaxItems(mixed $value): array
    {
        if (! is_int($value) || $value < 1) {
            return [self::MAX_SELECTED_ITEMS, null];
        }

        if ($value > self::MAX_REQUESTED_ITEMS) {
            return [self::MAX_REQUESTED_ITEMS, $value];
        }

        return [$value, null];
    }

    /**
     * Parses a free-text procurement question the pattern matcher could not
     * classify, without answering it.
     *
     * This is the fallback behind the frontend's pattern list: patterns stay
     * free and instant, and only an unrecognised phrasing spends one small
     * provider call. The provider returns a strict form only — never prose,
     * never data — and every field is validated against the question and the
     * prior answer before anything acts on it. An unusable parse returns null
     * so the caller falls back to its guidance message.
     *
     * @param  array<int>  $priorIds Inventory ids from the previous answer.
     * @param  array<string>  $priorNames Item names from the previous answer.
     * @return array{prompt_type:string,item_name:?string,inventory_id:?int,count:?int}|null
     */
    public function parseQuestion(User $user, string $question, array $priorIds = [], array $priorNames = []): ?array
    {
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES)) {
            return null;
        }

        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            return null;
        }

        try {
            $content = $this->geminiApi->generate(
                $this->parseSystemPrompt(),
                json_encode([
                    'question' => mb_substr(trim($question), 0, 500),
                    'prior_items' => array_values(array_filter(array_map(
                        fn (mixed $name): ?string => is_string($name) && trim($name) !== '' ? mb_substr(trim($name), 0, 255) : null,
                        $priorNames,
                    ))),
                ], JSON_UNESCAPED_SLASHES) ?: '',
                [
                    'responseFormat' => [
                        'text' => [
                            'mimeType' => 'APPLICATION_JSON',
                            'schema' => $this->parseResponseSchema(),
                        ],
                    ],
                    'temperature' => 0,
                    'maxOutputTokens' => 256,
                ]
            );

            if (! is_string($content)) {
                return null;
            }

            $decoded = json_decode($content, true);

            return is_array($decoded)
                ? $this->validateParse($decoded, $question, $priorIds, $priorNames)
                : null;
        } catch (\Throwable $exception) {
            Log::warning('Forecast question parsing failed: '.$exception->getMessage());

            return null;
        }
    }

    private function parseSystemPrompt(): string
    {
        return 'Classify one procurement question about an inventory demand forecast. Do not answer it, access data, or propose actions. '
            .'Return one JSON object with exactly these keys: prompt_type, item_name, inventory_id, count. '
            .'prompt_type must be purchase_first, deferrable, verify_first, next_steps, or why_these. '
            .'purchase_first ranks what to buy first; deferrable lists what can wait; verify_first lists rows needing verification; '
            .'next_steps gives the ordered next actions; why_these explains why listed items were selected. '
            .'item_name must be the exact product name from the question, or null when none is named. '
            .'inventory_id must be the integer inventory id from the question, or null when none is given. '
            .'count must be the number of items asked for (11 in "give me 11 items"), or null when none is asked for. '
            .'Never return SQL, permissions, database instructions, answers, or additional keys.';
    }

    private function parseResponseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'prompt_type' => ['type' => 'STRING', 'enum' => self::PROMPT_TYPES],
                'item_name' => ['type' => 'STRING', 'nullable' => true, 'maxLength' => 255],
                'inventory_id' => ['type' => 'INTEGER', 'nullable' => true, 'minimum' => 1],
                'count' => ['type' => 'INTEGER', 'nullable' => true, 'minimum' => 1],
            ],
            'required' => ['prompt_type', 'item_name', 'inventory_id', 'count'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Validates a parsed question, then holds it to the grounding rule.
     *
     * A name or id the provider invents is rejected whole: names must appear
     * in the question or name an item from the prior answer, and ids must
     * appear in the question or belong to the prior answer. Without this the
     * parser could talk the chat into answering about an item nobody named.
     *
     * @param  array<string, mixed>  $decoded
     * @param  array<int>  $priorIds
     * @param  array<string>  $priorNames
     * @return array{prompt_type:string,item_name:?string,inventory_id:?int,count:?int}|null
     */
    private function validateParse(array $decoded, string $question, array $priorIds, array $priorNames = []): ?array
    {
        $expected = ['prompt_type', 'item_name', 'inventory_id', 'count'];

        if (array_diff(array_keys($decoded), $expected) !== []
            || array_diff($expected, array_keys($decoded)) !== []) {
            return null;
        }

        $promptType = $decoded['prompt_type'];
        $itemName = $decoded['item_name'];
        $inventoryId = $decoded['inventory_id'];
        $count = $decoded['count'];

        if (! is_string($promptType) || ! in_array($promptType, self::PROMPT_TYPES, true)
            || ! (is_null($itemName) || (is_string($itemName) && trim($itemName) !== '' && mb_strlen(trim($itemName)) <= 255))
            || ! (is_null($inventoryId) || (is_int($inventoryId) && $inventoryId > 0))
            || ! (is_null($count) || (is_int($count) && $count >= 1 && $count <= 1000))) {
            return null;
        }

        $cleanIds = [];
        foreach ((array) $priorIds as $id) {
            if (is_int($id) && $id > 0) {
                $cleanIds[] = $id;
            } elseif (is_string($id) && ctype_digit($id) && (int) $id > 0) {
                $cleanIds[] = (int) $id;
            }
        }
        $cleanIds = array_values(array_unique($cleanIds));

        if (is_string($itemName) && ! $this->parseNameIsGrounded($itemName, $question, $priorNames)) {
            return null;
        }

        if (is_int($inventoryId)
            && preg_match('/(?<!\d)'.preg_quote((string) $inventoryId, '/').'(?!\d)/u', $question) !== 1
            && ! in_array($inventoryId, $cleanIds, true)) {
            return null;
        }

        return [
            'prompt_type' => $promptType,
            'item_name' => is_string($itemName) ? trim($itemName) : null,
            'inventory_id' => $inventoryId,
            'count' => $count,
        ];
    }

    /**
     * Whether a parsed name comes from the user's own words or the list they
     * were just shown.
     *
     * The name must appear in the question itself, or name an item from the
     * prior answer — so "the toner" may resolve to the listed Toner Cartridge,
     * but a name from neither is rejected. Names from the prior answer are
     * still resolved by the caller against its own list, never trusted from
     * the parse alone.
     *
     * @param  array<string>  $priorNames
     */
    private function parseNameIsGrounded(string $itemName, string $question, array $priorNames = []): bool
    {
        if (mb_stripos($question, $itemName) !== false) {
            return true;
        }

        $needle = mb_strtolower(trim($itemName));

        foreach ((array) $priorNames as $prior) {
            if (! is_string($prior) || trim($prior) === '') {
                continue;
            }

            $candidate = mb_strtolower(trim($prior));

            if ($candidate === $needle || str_contains($candidate, $needle)) {
                return true;
            }
        }

        return false;
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
    private function resolveSelection(Collection $rows, string $promptType, array $context, int $maxItems = self::MAX_SELECTED_ITEMS): array
    {
        $priorIds = array_values(array_filter(array_map(
            'intval',
            (array) ($context['inventory_ids'] ?? [])
        ), fn (int $id): bool => $id > 0));

        if ($priorIds === []) {
            return ['rows' => $this->selectRows($rows, $promptType, $maxItems), 'scope' => 'full'];
        }

        $known = $rows->whereIn('inventory_id', $priorIds)->values();

        if ($known->isEmpty()) {
            // Every prior id is gone (items deleted, model retrained). Fall back
            // to the full ranking rather than answering about nothing.
            return ['rows' => $this->selectRows($rows, $promptType, $maxItems), 'scope' => 'full'];
        }

        // Preserve the order the previous answer established, so "the second
        // one" still means the same item.
        $ordered = collect($priorIds)
            ->map(fn (int $id) => $known->firstWhere('inventory_id', $id))
            ->filter()
            ->take($maxItems)
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
            // already has a gap, which is why they were on the list. why_these
            // explains that same list, so it keeps it untouched as well.
            default => $rows,
        };
    }

    /**
     * Ranks the forecast rows for a question. Ordering is deterministic and
     * derived only from fields the ML forecast already calculated.
     */
    public function selectRows(Collection $rows, string $promptType, int $maxItems = self::MAX_SELECTED_ITEMS): Collection
    {
        // A named count ("give me 5 items") narrows the same ranking rather
        // than redefining it. Out-of-range values fall back to the default so
        // a bad count can never empty or explode an answer.
        $take = $maxItems >= 1 && $maxItems <= self::MAX_REQUESTED_ITEMS
            ? $maxItems
            : self::MAX_SELECTED_ITEMS;

        return match ($promptType) {
            // The actionable items: everything with a gap, most urgent first.
            // Identical to purchase_first on purpose — the next-steps answer
            // reasons about these rows and exports them to the PDF.
            self::PROMPT_NEXT_STEPS => $rows
                ->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
                ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                    + (int) ($row['suggested_procurement'] ?? 0))
                ->values()
                ->take($take),
            self::PROMPT_DEFERRABLE => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) === 'success'
                    && ($row['needs_procurement'] ?? false) === false
                    && is_int($row['suggested_procurement'] ?? null))
                ->sortByDesc(fn (array $row): int => (int) ($row['available_stock'] ?? 0))
                ->values()
                ->take($take),
            self::PROMPT_VERIFY_FIRST => $rows
                ->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                    || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))
                ->sortBy(fn (array $row): int => $this->confidenceScore($row) * 1000 + (int) ($row['priority_rank'] ?? 0))
                ->values()
                ->take($take),
            // The explanation prompt reasons about the same ranking the
            // custodian was just shown: everything with a gap, most urgent
            // first. Identical to purchase_first on purpose — the question is
            // about that list, so the selection must be that list.
            self::PROMPT_WHY_THESE => $rows
                ->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
                ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                    + (int) ($row['suggested_procurement'] ?? 0))
                ->values()
                ->take($take),
            default => $rows
                ->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
                ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                    + (int) ($row['suggested_procurement'] ?? 0))
                ->values()
                ->take($take),
        };
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
            // History depth, so the explanation prompt may honestly say how
            // many verified months a figure rests on. The grounding check
            // already approves these two fields wherever they appear.
            'historical_months_used' => $row['historical_months_used'] ?? null,
            'required_months' => $row['required_months'] ?? null,
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

        // Selection-scoped aggregates for the explanation prompt only. The
        // other prompts never read this key, and the grounding check approves
        // numbers solely from keys it knows, so their gates are unchanged.
        if ($promptType === self::PROMPT_WHY_THESE) {
            $facts['selection_state'] = $this->selectionState($selected);
        }

        $facts['cycle_state'] = $this->cycleState(collect($forecast['rows'] ?? []));

        return $facts;
    }

    /**
     * Whether a ranking reply names every selected item.
     *
     * The grounding gate approves every number a reply states, but it cannot
     * tell that an item is missing: a reply covering 10 of 11 selected rows
     * says nothing false, it is only incomplete — and the chat then disagrees
     * with the PDF, which renders all 11 server rows. The ranking prompts
     * (purchase_first, deferrable, verify_first) lay every row out, so each
     * one must name each selected item; anything less falls back to the
     * deterministic list, which loops all rows and is always complete.
     * Prose prompts (why_these, next_steps) intentionally name only examples
     * and are exempt.
     */
    private function listsEveryItem(string $reply, array $facts, string $promptType): bool
    {
        if (! in_array($promptType, [self::PROMPT_PURCHASE_FIRST, self::PROMPT_DEFERRABLE, self::PROMPT_VERIFY_FIRST], true)) {
            return true;
        }

        foreach ((array) ($facts['items'] ?? []) as $item) {
            $name = is_array($item) ? ($item['item_name'] ?? null) : null;

            if (! is_string($name) || $name === '' || mb_stripos($reply, $name) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aggregate counts over the selected list alone.
     *
     * cycleState() describes the whole forecast, which is the wrong scope for
     * an explanation of one list: quoting whole-forecast counts would answer a
     * different question. These are computed from the same rows the reply is
     * about, so every count the provider states traces to the selection.
     *
     * @return array<string, int>
     */
    private function selectionState(Collection $selected): array
    {
        return [
            'total' => $selected->count(),
            'urgent' => $selected->where('priority', 'Urgent')->count(),
            'high' => $selected->where('priority', 'High')->count(),
            'medium' => $selected->where('priority', 'Medium')->count(),
            // Listed items resting on limited verified history: the ones worth
            // a verification step before ordering.
            'weak_history' => $selected->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
            'suggested_units' => (int) $selected->sum(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0)),
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
                ."6. Never name the data fields you were given. Write plain English, so say \"62 High priority items\", "
                ."not \"62 items in cycle state\" and never mention gaps_needing_verification or selected_count.\n"
                ."7. Keep it under 160 words.\n";
        }

        if ($promptType === self::PROMPT_WHY_THESE) {
            return "You are the decision support layer on top of a locally calculated inventory demand forecast.\n\n"
                ."The approved facts below were computed by the server from verified completed stock-out history. "
                ."They are the only data you have. You cannot query a database, and you must not recalculate, adjust, "
                ."round up, or invent any quantity.\n\n"
                ."The custodian was just shown a ranked list of items and asks: \"Why are these items on the list?\"\n\n"
                ."The 'items' list is that ranking, in order. The 'selection_state' block counts what those items share.\n\n"
                ."Rules:\n"
                ."1. Explain in prose why these items were selected: every one of them has a procurement gap — forecast demand plus safety stock exceeds available stock plus pending demand — ordered most urgent first.\n"
                ."2. Every figure you write must already appear in selection_state or the items. Counts about THESE items come from selection_state; cycle_state describes the whole forecast and must not be used for claims about this list.\n"
                ."3. Name the first-listed (highest-priority) item as the lead example, using its exact item_name.\n"
                ."4. If the weak-history count in selection_state is not zero, say that many of the listed items rest on limited verified history and that usage should be confirmed before ordering. If it is zero, say the estimates rest on enough verified history instead.\n"
                ."5. Do not state or estimate any price, peso amount, cost, budget, supplier, or brand. None were supplied.\n"
                ."6. You may recommend what to procure. Never claim an order was placed or approved, or that inventory or stock was changed. "
                ."You only advise; a custodian approves separately.\n"
                ."7. Never name the data fields you were given. Write plain English, so say \"9 High priority items\", "
                ."not \"9 items in selection state\" and never mention selection_state, cycle_state, gaps_needing_verification or selected_count.\n"
                ."8. If an item's status is not \"success\", say it lacks enough verified history for an estimate and should be "
                ."verified before use. Do not produce a suggested quantity for it.\n"
                ."9. No list layout and no formula line: this answer is prose, not a ranking. Keep it under 160 words.\n"
                ."10. Close with one line noting the forecast is advisory only.\n";
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
            ."6. Never name the data fields you were given. Write plain English, so say \"62 High priority items\", "
            ."not \"62 items in cycle state\" and never mention cycle_state, gaps_needing_verification or selected_count.\n"
            ."7. If status is not \"success\", say the item lacks enough verified history for an estimate and should be "
            ."verified before use. Do not produce a suggested quantity for it.\n"
            ."8. List the highest priority items first.\n"
            ."9. Layout exactly, with no markdown table (the chat column is narrow):\n"
            ."   - First line, once: \"Buy = demand + buffer - stock - pending\"\n"
            ."   - Then two lines per item, no blank line between items:\n"
            ."     **Item name** — N piece\n"
            ."     Priority · Confidence confidence · X + Y - Z - W\n"
            ."   The second line repeats the four numbers in the formula order. Do not spell out "
            ."'demand', 'buffer', 'stock' or 'pending' again, and do not repeat the formula per item.\n"
            ."   List every item in the approved facts, in the order given, omitting none. A shorter list is a wrong answer.\n"
            ."10. Close with one line noting the forecast is advisory only.\n";
    }

    private function localAnswer(array $facts): string
    {
        $items = $facts['items'] ?? [];
        if (($facts['prompt_type'] ?? null) === self::PROMPT_NEXT_STEPS) {
            return $this->localNextSteps($facts);
        }
        if (($facts['prompt_type'] ?? null) === self::PROMPT_WHY_THESE) {
            return $this->localWhyThese($facts);
        }

        if (! is_array($items) || $items === []) {
            return match ($facts['prompt_type'] ?? self::PROMPT_PURCHASE_FIRST) {
                self::PROMPT_DEFERRABLE => 'No item currently has stock and pending demand covering its forecast demand and safety stock, so nothing can be deferred from this forecast cycle.',
                self::PROMPT_VERIFY_FIRST => 'Every forecast row rests on enough verified completed history to be usable. No row needs verification before ordering this cycle.',
                self::PROMPT_WHY_THESE => 'There is no procurement gap in this forecast, so there is no list to explain.',
                default => 'No item in this forecast has a procurement gap, so there is nothing to purchase first this cycle.',
            };
        }

        $period = is_string($facts['forecast_period'] ?? null) ? $facts['forecast_period'] : 'this cycle';
        $lines = [
            $facts['question'].' ('.$period.')',
            'Buy = demand + buffer − stock − pending',
        ];

        foreach ($items as $index => $item) {
            $lines[] = ($index + 1).'. '.$this->localItemLine($item);
        }

        $lines[] = 'This is advisory only and does not create orders. Refresh model training before acting on a new result.';

        return implode("\n", $lines);
    }

    /**
     * The deterministic "why are these on the list" answer.
     *
     * Prose, not a ranking: it states the shared basis the selection was made
     * on, the priority mix, and the history caveat. Every count comes from
     * selectionState(), every name from the items, so the fallback cannot
     * miscount or misname even when the provider is unreachable.
     */
    private function localWhyThese(array $facts): string
    {
        $items = is_array($facts['items'] ?? null) ? $facts['items'] : [];
        $state = is_array($facts['selection_state'] ?? null) ? $facts['selection_state'] : [];
        $period = is_string($facts['forecast_period'] ?? null) ? $facts['forecast_period'] : 'this cycle';
        $total = (int) ($state['total'] ?? count($items));
        $urgent = (int) ($state['urgent'] ?? 0);
        $high = (int) ($state['high'] ?? 0);
        $medium = (int) ($state['medium'] ?? 0);
        $weak = (int) ($state['weak_history'] ?? 0);

        $lead = collect($items)
            ->pluck('item_name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->take(3)
            ->implode(', ');

        $lines = [
            "These {$total} items are on the list because each has a procurement gap for {$period}: "
            .'forecast demand plus safety stock exceeds available stock plus pending demand.',
        ];

        $mix = [];
        if ($urgent > 0) {
            $mix[] = $urgent.' Urgent';
        }
        if ($high > 0) {
            $mix[] = $high.' High';
        }
        if ($medium > 0) {
            $mix[] = $medium.' Medium';
        }
        if ($mix !== []) {
            $lines[] = 'The mix is '.implode(', ', $mix).($lead !== '' ? ', led by '.$lead.'.' : '.');
        } elseif ($lead !== '') {
            $lines[] = 'Led by '.$lead.'.';
        }

        if ($weak > 0) {
            $lines[] = $weak.' of the '.$total.' rest'.($weak === 1 ? 's' : '')
                .' on limited verified history — confirm actual usage before ordering.';
        } else {
            $lines[] = 'The estimates rest on enough verified history to be usable.';
        }

        $lines[] = 'This is advisory only and does not create orders.';

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

}
