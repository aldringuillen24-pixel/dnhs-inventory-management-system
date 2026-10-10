<?php

namespace App\Services;

use App\Models\ForecastAiRecommendation;
use App\Models\User;
use App\Services\Concerns\GroundsForecastReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * AI recommendations covering every row in the stored demand forecast.
 *
 * The chat asks a question and answers about at most ten rows. This service
 * answers a different question — "what should I do about everything?" — so it
 * covers the whole forecast rather than a ranked slice.
 *
 * The same discipline applies as everywhere else in this layer: the server
 * computes every number and every grouping, the provider only words them, and
 * each reply is validated against the facts before a custodian reads it. What
 * is new here is granularity. Work is split into a brief plus per-item chunks,
 * each validated on its own, so a rejected chunk costs twenty items rather than
 * a hundred, and an item the provider skipped or mangled falls back to a
 * templated sentence without discarding the rest of its chunk.
 *
 * The result is stored against the forecast's `generated_at` stamp, so a cycle
 * is written once and reused by every custodian until the model retrains.
 */
class ForecastAiRecommendationService
{
    use GroundsForecastReply;

    /** What to do with this item. Exhaustive and mutually exclusive. */
    public const QUEUE_BUY_NOW = 'buy_now';

    public const QUEUE_VERIFY_FIRST = 'verify_first';

    public const QUEUE_NO_ACTION = 'no_action';

    /**
     * Rows per provider call.
     *
     * Not a token limit — a hundred rows fit comfortably in one prompt. It is
     * the smallest slice whose loss to a grounding rejection is tolerable, and
     * small enough that one bad sentence is an obvious outlier rather than a
     * twelfth of the list.
     */
    public const MAX_CHUNK_ITEMS = 20;

    /**
     * Ceiling on provider calls per cycle.
     *
     * A safety valve, not an expected limit: at the configured chunk size this
     * allows 240 actionable rows. Anything beyond is rendered from the local
     * template so a pathological forecast cannot fan out into an unbounded
     * number of billable requests.
     */
    public const MAX_CHUNKS = 12;

    public function __construct(
        protected AiCapabilityPolicy $policy,
        protected GeminiApiService $geminiApi,
        protected StoredDemandForecastService $storedForecastService,
    )
    {
    }

    /**
     * Recommendations for the current forecast cycle.
     *
     * @return array<string, mixed>
     */
    public function recommendations(User $user, bool $refresh = false): array
    {
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES)) {
            return ['status' => 'forbidden', 'message' => 'That information is not available for your role.'];
        }

        $forecast = $this->storedForecastService->read($user);

        if (($forecast['status'] ?? null) === 'forbidden') {
            return ['status' => 'forbidden', 'message' => 'That information is not available for your role.'];
        }

        // No trained forecast means no recommendations. Answering from a
        // different source would quietly change what the tab means.
        if (($forecast['status'] ?? null) !== 'success') {
            return [
                'status' => 'error',
                'message' => 'No trained production ML forecast is available. Model training runs separately from chat and reports.',
            ];
        }

        $rows = collect($forecast['rows'] ?? []);
        $queues = $this->partition($rows);
        $cycleKey = $this->cycleKey($forecast);

        if (! $refresh) {
            $cached = $this->storedCycle($forecast, $cycleKey, $queues);

            if ($cached !== null) {
                return $cached;
            }
        }

        return $this->generateAndStore($forecast, $rows, $queues, $cycleKey, $user);
    }

    /**
     * The stored advice for this cycle, rebuilt against the live forecast rows.
     *
     * Only the provider's text is read back. Queue membership and every quantity
     * are recomputed, so a row saved before an item was re-categorised or
     * deleted cannot make the tab contradict the table beside it.
     *
     * @param  array<string, Collection>  $queues
     * @return array<string, mixed>|null
     */
    private function storedCycle(array $forecast, string $cycleKey, array $queues): ?array
    {
        $record = ForecastAiRecommendation::query()
            ->where('source_type', ForecastAiRecommendation::SOURCE_LIVE)
            ->where('forecast_generated_at', $cycleKey)
            ->first();

        if (! $record || ! is_array($record->queues)) {
            return null;
        }

        $actions = $this->savedActions($record->queues);

        if ($actions === []) {
            return null;
        }

        return $this->payload($forecast, $cycleKey, $queues, $actions, (string) $record->brief, 'provider', 'cached', $record->generated_at?->toIso8601String());
    }

    /**
     * Flattens the stored per-queue items into inventory_id => action.
     *
     * @return array<int, string>
     */
    private function savedActions(array $queues): array
    {
        $actions = [];

        foreach ($queues as $queue) {
            foreach ((array) ($queue['items'] ?? []) as $item) {
                if (! is_array($item) || ! isset($item['inventory_id'])) {
                    continue;
                }

                $actions[(int) $item['inventory_id']] = (string) ($item['action'] ?? '');
            }
        }

        return array_filter($actions, fn (string $action): bool => trim($action) !== '');
    }

    /**
     * The stamp that identifies this cycle.
     *
     * Normalised through the model on purpose. Assigning an ISO string and then
     * looking the row up with that same string does not match: the datetime cast
     * rewrites the value on write but leaves the where clause alone, so the
     * second read missed its own row and tried to insert a duplicate.
     */
    private function cycleKey(array $forecast): string
    {
        $generated = $forecast['generated_at'] ?? null;

        if (! is_string($generated) || $generated === '') {
            return 'unknown-'.$this->fingerprint($forecast);
        }

        $probe = new ForecastAiRecommendation;
        $probe->forecast_generated_at = $generated;

        return $probe->forecast_generated_at?->format('Y-m-d H:i:s')
            ?? 'unknown-'.$this->fingerprint($forecast);
    }

    /**
     * A stable fallback identity for a forecast that carries no timestamp.
     *
     * Derived from the row count and the largest suggested quantity, so it
     * changes when the numbers change and stays put when they do not.
     */
    private function fingerprint(array $forecast): string
    {
        $rows = collect($forecast['rows'] ?? []);

        return $rows->count().'-'.(int) $rows->max(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0));
    }

    /**
     * Whether the item's most recent verified month is far out of line with the
     * verified months before it.
     *
     * Returns null when the question cannot be answered — too few verified
     * months, no usable history, or a baseline of zero — so "cannot tell" is a
     * first-class outcome rather than something forced into a verdict.
     *
     * @return array{spiked: bool, ratio: float, baseline: float, latest_month: string, latest_quantity: float, months_compared: int}|null
     */
    private function usageSpike(array $row): ?array
    {
        $usage = $row['monthly_usage_values'] ?? null;

        if (! is_array($usage) || $usage === []) {
            return null;
        }

        // Months with no recorded events are unknown, not zero. Reading one as
        // zero would make every item look like it had a spike in the month its
        // data stopped arriving.
        $unknown = array_flip((array) ($row['unknown_months'] ?? []));
        $points = [];

        foreach ($usage as $month => $quantity) {
            $month = (string) $month;

            if (isset($unknown[$month]) || ! is_numeric($quantity)) {
                continue;
            }

            $points[$month] = (float) $quantity;
        }

        if (count($points) < $this->spikeMinimumMonths()) {
            return null;
        }

        // Calendar months are zero-padded ISO strings, so a lexical sort is a
        // chronological one.
        ksort($points);

        $latest = array_key_last($points);
        $baseline = array_values($points);
        $latestQuantity = (float) array_pop($baseline);

        // A zero median means the item was not being drawn down at all before
        // now, so there is no established rate to have risen above. A ratio
        // against zero would be an invented figure.
        if ($baseline === []) {
            return null;
        }

        $baselineValue = $this->median($baseline);

        if ($baselineValue <= 0.0) {
            return null;
        }

        return [
            'spiked' => ($latestQuantity / $baselineValue) >= $this->spikeRatio(),
            // Floats, deliberately: JSON has one number type, so PHP receives 6 rather than
            // 6.0 and strict callers would see a type flip either way. Rounded to
            // one decimal so a sentence can quote the figure the reader sees.
            'ratio' => (float) round($latestQuantity / $baselineValue, 1),
            'baseline' => (float) round($baselineValue, 1),
            'latest_month' => (string) $latest,
            'latest_quantity' => (float) round($latestQuantity, 1),
            'months_compared' => count($points),
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    private function hasUsageSpike(array $row): bool
    {
        return ($this->usageSpike($row)['spiked'] ?? false) === true;
    }

    /**
     * How many rows in the forecast show unusual consumption.
     *
     * Computed once and passed down rather than recomputed per caller, because
     * usageSpike() re-reads and re-sorts a month's usage map every time and the
     * brief asks for this figure more than once.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function spikeCount(Collection $rows): int
    {
        return $rows->filter(fn (array $row): bool => $this->hasUsageSpike($row))->count();
    }

    private function spikeMinimumMonths(): int
    {
        return max(3, (int) config('forecast.consumption_anomaly.minimum_verified_months', 6));
    }

    private function spikeRatio(): float
    {
        return max(1.5, (float) config('forecast.consumption_anomaly.spike_ratio', 3.0));
    }

    /**
     * Splits the forecast into the three work queues.
     *
     * Exhaustive and mutually exclusive: every row lands in exactly one queue,
     * so the three badges always sum to the row total and nothing is both
     * "order this" and "nothing to do".
     *
     * An unusual-consumption row is demoted out of Buy now even at High
     * confidence. High confidence only describes how deep the history is, not
     * whether the newest point in it is trustworthy, and the model's linear
     * trend will otherwise have read a one-off spike as a permanent rise and
     * told the custodian to order against it.
     *
     * @return array<string, Collection>
     */
    private function partition(Collection $rows): array
    {
        // Stated once and reused by both filters. Expressing the Buy now rule as a
        // named predicate is what keeps the two filters exact complements, and
        // therefore keeps every row in exactly one queue.
        $isBuyNow = fn (array $row): bool => ($row['needs_procurement'] ?? false) === true
            && ($row['status'] ?? null) === 'success'
            && ($row['confidence'] ?? null) === 'High'
            && ! $this->hasUsageSpike($row);

        $buyNow = $rows->filter($isBuyNow);

        $verifyFirst = $rows->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true
            && ! $isBuyNow($row));

        $noAction = $rows->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) !== true);

        // Within a queue, most urgent and largest first, matching how the chat
        // and the table already rank, so the tab never reorders a row the
        // custodian has seen elsewhere.
        $rank = fn (Collection $queue): Collection => $queue
            ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                + (int) ($row['suggested_procurement'] ?? 0))
            ->values();

        return [
            self::QUEUE_BUY_NOW => $rank($buyNow),
            self::QUEUE_VERIFY_FIRST => $rank($verifyFirst),
            self::QUEUE_NO_ACTION => $rank($noAction),
        ];
    }

    /**
     * Generates the cycle once and saves it.
     *
     * @param  array<string, Collection>  $queues
     * @return array<string, mixed>
     */
    private function generateAndStore(array $forecast, Collection $rows, array $queues, string $cycleKey, User $user): array
    {
        $hasKey = is_string(config('services.gemini.api_key')) && trim((string) config('services.gemini.api_key')) !== '';

        [$brief, $briefSource, $briefStatus] = $hasKey
            ? $this->generateBrief($forecast, $rows, $queues)
            : [$this->localBrief($forecast, $rows, $queues), 'local', 'no_key'];

        $actions = [];
        $chunkBudget = self::MAX_CHUNKS;

        // Only the actionable queues are worth a provider call. The No action
        // queue is the same sentence for every row in it, so asking a model to
        // write twenty identical lines buys nothing.
        foreach ([self::QUEUE_BUY_NOW, self::QUEUE_VERIFY_FIRST] as $queueKey) {
            $queue = $queues[$queueKey];

            if ($queue->isEmpty()) {
                continue;
            }

            foreach (array_chunk($queue->all(), self::MAX_CHUNK_ITEMS) as $chunk) {
                if ($hasKey && $chunkBudget > 0) {
                    $chunkBudget--;
                    $actions += $this->generateChunkActions($chunk, $queueKey, $forecast, $user);
                } else {
                    // Out of budget, or no key: the template is still a complete
                    // answer, just not a written one.
                    $actions += $this->localChunkActions($chunk);
                }
            }
        }

        $storedQueues = $this->storeShape($queues, $actions);
        $generatedAt = now();

        ForecastAiRecommendation::query()->updateOrCreate(
            ['source_type' => ForecastAiRecommendation::SOURCE_LIVE, 'forecast_generated_at' => $cycleKey],
            [
                'forecast_period' => $forecast['forecast_period'] ?? null,
                'brief' => $brief,
                'queues' => $storedQueues,
                'generated_at' => $generatedAt,
            ]
        );

        return $this->payload($forecast, $cycleKey, $queues, $actions, $brief, $briefSource, $briefStatus, $generatedAt->toIso8601String());
    }

    /**
     * Assembles the response.
     *
     * @param  array<string, Collection>  $queues
     * @param  array<int, string>  $actions
     * @return array<string, mixed>
     */
    private function payload(array $forecast, string $cycleKey, array $queues, array $actions, string $brief, string $source, string $providerStatus, ?string $generatedAt): array
    {
        $labels = [
            self::QUEUE_BUY_NOW => 'Buy now',
            self::QUEUE_VERIFY_FIRST => 'Verify first',
            self::QUEUE_NO_ACTION => 'No action',
        ];

        $shaped = [];
        $total = 0;

        foreach ($labels as $key => $label) {
            $queue = $queues[$key] ?? collect();
            $items = $queue->map(fn (array $row): array => $this->presentItem($row, $actions[(int) ($row['inventory_id'] ?? 0)] ?? null))->values()->all();
            $total += count($items);

            $shaped[] = [
                'key' => $key,
                'label' => $label,
                'item_count' => count($items),
                'items' => $items,
            ];
        }

        return [
            'status' => 'success',
            'cached' => $providerStatus === 'cached',
            'source' => $source,
            'provider_status' => $providerStatus,
            'forecast_period' => $forecast['forecast_period'] ?? null,
            'forecast_generated_at' => $cycleKey,
            'generated_at' => $generatedAt,
            'item_count' => $total,
            'brief' => $brief,
            'queues' => $shaped,
        ];
    }

    /**
     * The subset of a row this tab needs.
     *
     * `action` is the provider's sentence when one was accepted, and the local
     * template otherwise. `action_hint` is what makes that sentence safe to ask
     * for: it is derived here from the row's own facts, so the provider has a
     * licensed claim to work from rather than being asked to infer one.
     *
     * @return array<string, mixed>
     */
    private function presentItem(array $row, ?string $action): array
    {
        $spike = $this->usageSpike($row);

        return [
            'inventory_id' => (int) ($row['inventory_id'] ?? 0),
            'item_name' => $row['item_name'] ?? null,
            'category' => $row['category'] ?? null,
            'unit' => $row['unit'] ?? null,
            'forecast_demand' => $row['forecast_demand'] ?? null,
            'safety_stock' => $row['safety_stock'] ?? null,
            'available_stock' => $row['available_stock'] ?? null,
            'pending_demand' => $row['pending_demand'] ?? null,
            'unmet_demand' => (int) ($row['unmet_demand'] ?? 0),
            'unmet_requesters' => (int) ($row['unmet_requesters'] ?? 0),
            'suggested_procurement' => $row['suggested_procurement'] ?? null,
            'priority' => $row['priority'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'status' => $row['status'] ?? null,
            'history_depth' => $this->historyDepth($row),
            'usage_spike' => $spike['spiked'] ?? false,
            'usage_ratio' => $spike['ratio'] ?? null,
            'usage_baseline' => $spike['baseline'] ?? null,
            'usage_latest_month' => $spike['latest_month'] ?? null,
            'usage_months_compared' => $spike['months_compared'] ?? null,
            'action' => $action ?? $this->localAction($row),
        ];
    }

    /**
     * How much verified history backs this row, in words.
     *
     * This is what lets a written sentence say *why* a number is soft instead
     * of only flagging that it is. Null when the row has no usable history.
     */
    private function historyDepth(array $row): ?string
    {
        $used = $row['historical_months_used'] ?? null;
        $required = $row['required_months'] ?? null;

        if (! is_int($used) || ! is_int($required) || $used <= 0 || $required <= 0) {
            return null;
        }

        return $used.' of '.$required.' required months';
    }

    /**
     * The row shape sent to the provider, including the licensed claim.
     *
     * @return array<string, mixed>
     */
    private function promptItem(array $row): array
    {
        $spike = $this->usageSpike($row);

        return [
            'inventory_id' => (int) ($row['inventory_id'] ?? 0),
            'item_name' => $row['item_name'] ?? null,
            'unit' => $row['unit'] ?? null,
            'forecast_demand' => $row['forecast_demand'] ?? null,
            'safety_stock' => $row['safety_stock'] ?? null,
            'available_stock' => $row['available_stock'] ?? null,
            'pending_demand' => $row['pending_demand'] ?? null,
            'unmet_demand' => (int) ($row['unmet_demand'] ?? 0),
            'unmet_requesters' => (int) ($row['unmet_requesters'] ?? 0),
            'suggested_procurement' => $row['suggested_procurement'] ?? null,
            'priority' => $row['priority'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'status' => $row['status'] ?? null,
            'historical_months_used' => $row['historical_months_used'] ?? null,
            'required_months' => $row['required_months'] ?? null,
            // The spike figures are sent as approved facts so a written sentence
            // can quote "three times its usual rate" without inventing the ratio.
            'usage_spike' => $spike['spiked'] ?? false,
            'usage_ratio' => $spike['ratio'] ?? null,
            'usage_baseline' => $spike['baseline'] ?? null,
            'usage_latest_month' => $spike['latest_month'] ?? null,
            'usage_latest_quantity' => $spike['latest_quantity'] ?? null,
            'usage_months_compared' => $spike['months_compared'] ?? null,
            'action_hint' => $this->actionHint($row),
        ];
    }

    /**
     * The one claim this row is allowed to make.
     *
     * Derived from the row's own facts, never from prose. A provider that
     * stays inside its hint cannot claim something the forecast does not know,
     * and the grounding check still catches a reply that tries.
     */
    private function actionHint(array $row): string
    {
        if (($row['status'] ?? null) !== 'success') {
            return 'no_estimate';
        }

        $thin = ($row['confidence'] ?? null) !== 'High';
        $waiting = (int) ($row['unmet_demand'] ?? 0) > 0;
        $covered = ($row['needs_procurement'] ?? false) !== true;
        // Ahead of $thin, because a spike is the more specific finding: it says
        // something about THIS number, where thin history only says how much to
        // trust any number on this row.
        $spiked = $this->hasUsageSpike($row);

        if ($covered) {
            return $waiting ? 'already_covered_but_requested' : 'already_covered';
        }

        return match (true) {
            $spiked && $waiting => 'usage_spike_staff_waiting',
            $spiked => 'usage_spike',
            $thin && $waiting => 'thin_history_staff_waiting',
            $thin => 'thin_history',
            $waiting => 'staff_waiting',
            default => 'partial_cover',
        };
    }

    /**
     * The deterministic sentence, and the answer whenever a reply is unusable.
     *
     * Written to read as advice rather than as a rejected API call, because it
     * is also the normal answer when no provider is configured.
     */
    private function localAction(array $row): string
    {
        $name = (string) ($row['item_name'] ?? 'this item');
        $suggested = (int) ($row['suggested_procurement'] ?? 0);

        return match ($this->actionHint($row)) {
            'no_estimate' => 'Too little verified history to estimate. Confirm recent usage before ordering anything.',
            'usage_spike_staff_waiting' => 'Recent usage is far above this item’s own history and staff are waiting. Confirm the real rate before ordering.',
            'usage_spike' => 'Recent usage is far above this item’s own history. Confirm the real rate before ordering this quantity.',
            'thin_history_staff_waiting' => 'This figure rests on thin history and staff are waiting. Confirm usage before committing.',
            'thin_history' => 'Confirm actual usage before committing to this quantity.',
            'staff_waiting' => 'Staff requested this while it was unavailable. Order for them this cycle.',
            'already_covered_but_requested' => 'Stock already covers next month, but staff requested this while it was unavailable.',
            'already_covered' => 'Stock already covers next month. No action needed this cycle.',
            default => $suggested > 0
                ? 'Stock covers only part of next month’s demand. Order '.$suggested.' this cycle.'
                : 'No procurement gap this cycle for '.$name.'.',
        };
    }

    /**
     * Local sentences for a whole chunk, keyed by inventory id.
     *
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, string>
     */
    private function localChunkActions(array $chunk): array
    {
        $actions = [];

        foreach ($chunk as $row) {
            $actions[(int) ($row['inventory_id'] ?? 0)] = $this->localAction($row);
        }

        return $actions;
    }

    /**
     * Asks the provider for one plain sentence per item in this chunk.
     *
     * Each returned sentence is validated on its own row. A rejected sentence
     * falls back to the local template for that item alone, so one bad line
     * cannot discard nineteen good ones.
     *
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, string>
     */
    private function generateChunkActions(array $chunk, string $queueKey, array $forecast, User $user): array
    {
        $items = array_map(fn (array $row): array => $this->promptItem($row), $chunk);

        $facts = [
            'prompt_type' => 'item_actions',
            'queue' => $queueKey,
            'selected_count' => count($items),
            'max_items_shown' => self::MAX_CHUNK_ITEMS,
            'forecast_period' => $forecast['forecast_period'] ?? null,
            'generated_at' => $forecast['generated_at'] ?? null,
            'items' => $items,
        ];

        $reply = $this->geminiApi->generate(
            $this->itemActionsPrompt($queueKey),
            json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0.2, 'maxOutputTokens' => 4000, 'responseMimeType' => 'application/json']
        );

        $local = $this->localChunkActions($chunk);

        if (! is_string($reply) || trim($reply) === '') {
            return $local;
        }

        $parsed = json_decode(trim($reply), true);

        if (! is_array($parsed)) {
            Log::warning('Forecast recommendations chunk was not usable JSON.', [
                'queue' => $queueKey,
                'user_id' => $user->id,
                'reply' => mb_substr(trim($reply), 0, 400),
            ]);

            return $local;
        }

        // Keyed by the exact name supplied, so a renamed or invented item cannot
        // overwrite a real row's sentence.
        $byName = [];

        foreach ($parsed as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = is_string($entry['item_name'] ?? null) ? trim($entry['item_name']) : '';
            $action = is_string($entry['action'] ?? null) ? trim($entry['action']) : '';

            if ($name === '' || $action === '') {
                continue;
            }

            $byName[$name] = $action;
        }

        $actions = [];
        $rejected = [];

        foreach ($items as $item) {
            $id = (int) $item['inventory_id'];
            $candidate = $byName[(string) $item['item_name']] ?? null;

            if ($candidate === null || ! $this->isUsableAction($candidate, $item, $facts)) {
                $actions[$id] = $local[$id] ?? $this->localAction($item);
                $rejected[$id] = $item['item_name'];

                continue;
            }

            $actions[$id] = $candidate;
        }

        if ($rejected !== []) {
            Log::warning('Forecast recommendations rejected some provider sentences.', [
                'queue' => $queueKey,
                'user_id' => $user->id,
                'fallback_to_template' => array_values($rejected),
            ]);
        }

        return $actions;
    }

    /**
     * Whether one written sentence may be shown.
     *
     * Three gates: no claim of acting or costing, no figure the forecast did not
     * calculate for this item, and short enough to read as a line rather than a
     * paragraph. Length matters here in a way it does not in the chat, because
     * twenty of these render in a list.
     */
    private function isUsableAction(string $action, array $item, array $facts): bool
    {
        if (mb_strlen($action) > 240) {
            return false;
        }

        if ($this->forbiddenClaim($action) !== null) {
            return false;
        }

        // Scoped to this single row, so a sentence may quote that item's figures
        // and nothing else.
        $scoped = array_merge($facts, ['items' => [$item]]);

        return $this->unapprovedNumbers($action, $scoped) === [];
    }

    /**
     * Asks the provider to summarise the whole cycle in plain English.
     *
     * Aggregates are computed here rather than dumped as raw rows: the useful
     * observation ("the pressure is concentrated in School Supplies") only
     * exists once rows are grouped, and a model handed a hundred rows has to be
     * trusted to find it. Grouped first, it is a fact it can only restate.
     *
     * @param  array<string, Collection>  $queues
     * @return array{0: string, 1: string, 2: string}
     */
    private function generateBrief(array $forecast, Collection $rows, array $queues): array
    {
        $facts = [
            'prompt_type' => 'cycle_brief',
            // A brief is about the shape of the cycle, so counts alone are
            // legitimate and requiring an item name would reject a correct reply.
            'requires_item_name' => false,
            'forecast_period' => $forecast['forecast_period'] ?? null,
            'forecast_summary' => [],
            // cycleState() is shared with the chat and knows nothing about spikes,
            // so `gaps_needing_verification` there undercounts what actually landed
            // in Verify first. Merging the real queue length keeps the number the
            // brief states consistent with the badges above it, and stays an
            // approved fact so the grounding check will not reject it.
            'cycle_state' => [
                ...$this->cycleState($rows),
                'gaps_needing_verification' => $queues[self::QUEUE_VERIFY_FIRST]->count(),
                'usage_spikes' => $this->spikeCount($rows),
            ],
            'by_category' => $this->categoryTotals($rows),
            'queue_item_count' => $queues[self::QUEUE_BUY_NOW]->count() + $queues[self::QUEUE_VERIFY_FIRST]->count(),
            'selected_count' => $rows->count(),
            // Non-empty, because the shared grounding check refuses to judge a
            // reply with no facts to check it against, and because these are the
            // per-item figures a brief may quote.
            //
            // Drawn from every row with a gap, NOT from the Buy now queue. That
            // queue is empty whenever no gap row is High confidence, which is
            // common on real forecasts, and an empty fact list silently rejected
            // every brief and downgraded the whole tab to local text.
            'items' => $this->briefSpotlight($rows)->map(fn (array $row): array => $this->promptItem($row))->all(),
        ];

        $reply = $this->geminiApi->generate(
            $this->briefPrompt(),
            json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0.2, 'maxOutputTokens' => 2000]
        );

        if (! is_string($reply) || trim($reply) === '') {
            return [$this->localBrief($forecast, $rows, $queues), 'local', 'request_failed'];
        }

        if (! $this->isGroundedReply($reply, $facts)) {
            Log::warning('Forecast cycle brief rejected a provider reply.', [
                'failed_check' => $this->groundingFailure($reply, $facts),
                'forbidden_claim' => $this->forbiddenClaim($reply),
                'unapproved_numbers' => $this->unapprovedNumbers($reply, $facts),
                'reply' => mb_substr(trim($reply), 0, 400),
            ]);

            return [$this->localBrief($forecast, $rows, $queues), 'local', 'reply_rejected'];
        }

        return [trim($reply), 'provider', 'ok'];
    }

    /**
     * The rows a brief is allowed to name figures from.
     *
     * The largest gaps first, falling back to any row at all so the fact list is
     * never empty on a forecast that has rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function briefSpotlight(Collection $rows): Collection
    {
        $gaps = $rows->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)
            ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0) * 1000000
                + (int) ($row['suggested_procurement'] ?? 0));

        return $gaps->isEmpty() ? $rows->take(10) : $gaps->take(10);
    }

    /**
     * Group counts per category.
     *
     * The single most useful thing a brief can say that the table cannot: which
     * part of the catalogue is driving the whole month's pressure.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categoryTotals(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) ($row['category'] ?? 'Uncategorised'))
            ->map(fn (Collection $group, string $category): array => [
                'category' => $category,
                'items' => $group->count(),
                'items_with_gap' => $group->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true)->count(),
                'suggested_units' => (int) $group->sum(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0)),
            ])
            ->sortByDesc(fn (array $entry): int => (int) $entry['suggested_units'])
            ->values()
            ->all();
    }

    /**
     * The brief written without a provider.
     *
     * Same shape and same vocabulary the prompt asks for, so a fallback reads
     * like an answer rather than like a failure.
     *
     * @param  array<string, Collection>  $queues
     */
    private function localBrief(array $forecast, Collection $rows, array $queues): string
    {
        $state = $this->cycleState($rows);
        $gap = (int) $state['items_with_gap'];
        $forecasted = (int) $state['items_forecasted'];
        $verify = $queues[self::QUEUE_VERIFY_FIRST]->count();
        $covered = $queues[self::QUEUE_NO_ACTION]->count();
        $units = (int) $state['suggested_units'];
        $period = is_string($forecast['forecast_period'] ?? null) && $forecast['forecast_period'] !== ''
            ? (string) $forecast['forecast_period']
            : 'the coming cycle';

        $lines = ['**'.$forecasted.' items forecast for '.$period.'.**'];

        if ($gap === 0) {
            $lines[] = 'Nothing needs ordering: stock already covers next month for every item with an estimate.';

            return implode("\n\n", $lines)."\n\nThis is advisory only and does not create an order.";
        }

        $lines[] = $gap.' of them will run short next month, totalling '.$units.' units to order.';

        $top = $this->categoryTotals($rows)[0] ?? null;

        if ($top !== null && ($top['suggested_units'] ?? 0) > 0) {
            $lines[] = 'Most of that sits in '.$top['category'].', with '
                .$top['items_with_gap'].' of its '.$top['items'].' items short.';
        }

        $spikes = $this->spikeCount($rows);

        // Split the verify line, because the two reasons are different in kind.
        // "Thin history" means we do not know how much to trust the number;
        // "unusual consumption" means we looked and the number itself is
        // doubtful. Collapsing them into one sentence would understate the
        // second and the reader could not tell which items are which.
        if ($verify > 0) {
            $lines[] = $verify.' of the suggestions rest on thin history, so confirm recent usage before committing to them.';
        }

        if ($spikes > 0) {
            $lines[] = $spikes.' item'.($spikes === 1 ? '' : 's')
                .' show recent usage far above ' . ($spikes === 1 ? 'its' : 'their')
                .' own history, so confirm the real rate before ordering against '
                . ($spikes === 1 ? 'it' : 'them') . '.';
        }

        if ($covered > 0) {
            $lines[] = 'The remaining '.$covered.' are already covered and need nothing this cycle.';
        }

        return implode("\n\n", $lines)."\n\nThis is advisory only and does not create an order.";
    }

    private function briefPrompt(): string
    {
        return "You are summarising a school's inventory demand forecast for the property custodian.\n\n"
            ."You are given counts the server already calculated: a cycle_state block, per-category totals, and a "
            ."short list of the largest gaps. Those are the only data you have. You cannot query anything, and you "
            ."must not recalculate, round, or estimate any figure.\n\n"
            ."Write a short summary of this procurement cycle in plain English for a school administrator.\n\n"
            ."Rules:\n"
            ."1. Use only numbers that appear in the approved facts. Every figure you write must already be there.\n"
            ."2. Write 3 to 5 short sentences. Plain prose, no headings, no bullet points, no markdown table.\n"
            ."3. Say how many items are short and roughly how much is needed, then where the pressure is concentrated.\n"
            ."4. Say plainly whether any suggestions rest on thin history and should be confirmed first. If any\n"
            ."   item shows usage far above its own history, say that too and say the rate should be confirmed.\n"
            ."5. Say plainly which items need nothing this cycle, so the reader knows the list is not everything.\n"
            ."6. Never state or estimate any price, peso amount, cost, budget, supplier, or brand. None were supplied.\n"
            ."7. Never claim an order was placed or approved, or that stock was changed. You only advise.\n"
            ."8. Never name the data fields you were given. Write \"62 items\", never \"62 items in cycle_state\".\n"
            ."9. Do not use the words urgent, critical, emergency or shortage.\n"
            ."10. Close with one short sentence noting this is advisory only and does not create an order.\n";
    }

    private function itemActionsPrompt(string $queueKey): string
    {
        $queueName = $queueKey === self::QUEUE_BUY_NOW
            ? 'items the custodian should order this cycle'
            : 'items whose figures are too soft to order on without checking first';

        return "You are writing one short recommendation sentence per inventory item for a school property custodian.\n\n"
            ."These are {$queueName}. Each has an 'action_hint' describing the one claim that item's data supports. "
            ."Write the sentence for that hint and no more.\n\n"
            ."What each hint means:\n"
            ."  partial_cover - stock covers only part of next month's demand, so order the suggested quantity now.\n"
            ."  staff_waiting - staff requested this while none was available, so order it for them this cycle.\n"
            ."  thin_history - the figure rests on few verified months, so confirm actual usage before committing.\n"
            ."  thin_history_staff_waiting - both of the above apply.\n"
            ."  usage_spike - the latest verified month is far above this item's own history, so the rate itself\n"
            ."    is doubtful; tell the reader to confirm the real rate before committing to a quantity.\n"
            ."  usage_spike_staff_waiting - both of the above apply.\n"
            ."  no_estimate - there is not enough history to estimate at all; tell the reader to verify usage first.\n\n"
            ."Return ONLY a JSON array, one object per item, in the order given:\n"
            .'[{"item_name": "<copied exactly>", "action": "<one sentence>"}]\n\n'
            ."Rules:\n"
            ."1. Copy item_name character for character from the facts. Never invent or rename an item.\n"
            ."2. Include every item given, exactly once.\n"
            ."3. action is ONE sentence of at most 200 characters. Plain English. No markdown, no lists, no bullet "
            ."characters, no quotation marks around it.\n"
            ."4. Only use numbers that appear in that item's own facts. You may not do arithmetic and may not quote a "
            ."figure from a different item.\n"
            ."5. Say what to do, not what the arithmetic is. Do not write a formula and do not recite field values.\n"
            ."6. Never state or estimate any price, peso amount, cost, budget, supplier, or brand.\n"
            ."7. Never claim an order was placed or approved, or that stock was changed. You only advise.\n"
            ."8. Never name the data fields you were given.\n";
    }

    /**
     * The stored shape: only what the provider contributed.
     *
     * Quantities are deliberately not persisted. Saving them would let a stale
     * row print numbers the current forecast no longer produces.
     *
     * @param  array<string, Collection>  $queues
     * @param  array<int, string>  $actions
     * @return array<int, array<string, mixed>>
     */
    private function storeShape(array $queues, array $actions): array
    {
        $stored = [];

        foreach ($queues as $key => $queue) {
            $stored[] = [
                'key' => $key,
                'items' => $queue
                    ->map(fn (array $row): array => [
                        'inventory_id' => (int) ($row['inventory_id'] ?? 0),
                        'action' => $actions[(int) ($row['inventory_id'] ?? 0)] ?? $this->localAction($row),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $stored;
    }
}
