<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\GroundsForecastReply;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ForecastExplanationService
{
    // The same grounding rules the decision-support chat and the Recommendations
    // tab use. Reusing them is the point: a second copy of this gate is how one
    // of them quietly stops being applied.
    use GroundsForecastReply;

    /**
     * The forecast fields a reply may label with a priority tier.
     *
     * Both are read together so a tier can be attributed to the field it
     * belongs to. "Urgent priority with Low confidence" describes an Urgent row
     * and a Low-confidence one at the same time, which is why the checks cannot
     * be done one field at a time.
     */
    private const LABELLED_TIER_FIELDS = ['priority', 'confidence'];

    public function __construct(
        protected AiCapabilityPolicy $policy,
        protected GeminiApiService $geminiApi,
    )
    {
    }

    public function explain(User $user, array $forecastRow): array
    {
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            return [
                'status' => 'forbidden',
                'message' => 'That information is not available for your role.',
            ];
        }

        $payload = $this->safePayload($forecastRow);
        $local = fn (string $providerStatus): array => [
            'status' => 'success',
            'source' => 'local',
            'provider_status' => $providerStatus,
            'payload' => $payload,
            'explanation' => $this->localExplanation($payload),
        ];

        // A row with no estimate has nothing to explain, so no request is made
        // and the reason is reported rather than implied.
        if (($payload['forecast_status'] ?? null) !== 'success') {
            return $local('not_attempted');
        }

        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            return $local('no_key');
        }

        $reply = $this->geminiApi->generate(
            $this->systemPrompt($payload),
            'Explain why this calculated forecast may require procurement. Use only the supplied facts.',
            ['temperature' => 0.1, 'maxOutputTokens' => 300]
        );

        if (! is_string($reply) || trim($reply) === '') {
            return $local('request_failed');
        }

        $failure = $this->explanationGroundingFailure($reply, $payload);

        if ($failure !== null) {
            // Recorded so "why did it fall back?" is answerable from the log
            // instead of guesswork. This service previously fell back silently
            // on every single call, which is why the failure went unnoticed.
            Log::warning('Forecast explanation rejected a provider reply.', [
                'user_id' => $user->id,
                'item_name' => $payload['item_name'] ?? null,
                'failed_check' => $failure['check'],
                'forbidden_claim' => $failure['forbidden_claim'],
                'unapproved_numbers' => $failure['unapproved_numbers'],
                'reply' => mb_substr(trim($reply), 0, 400),
            ]);

            return $local('reply_rejected');
        }

        return [
            'status' => 'success',
            'source' => 'provider',
            'provider_status' => 'ok',
            'payload' => $payload,
            'explanation' => trim($reply),
        ];
    }

    public function explainDemo(User $user, array $forecast): array
    {
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            return [
                'status' => 'forbidden',
                'source_type' => 'demo',
                'explanation' => 'That information is not available for your role.',
            ];
        }

        if (($forecast['source_type'] ?? null) !== 'demo' || ! is_array($forecast['rows'] ?? null)) {
            return [
                'status' => 'error',
                'source_type' => 'demo',
                'explanation' => 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.',
            ];
        }
        if (($forecast['status'] ?? null) === 'missing') {
            return [
                'status' => 'missing',
                'source_type' => 'demo',
                'explanation' => 'No Sample/Demo forecast has been generated yet. Sample results are separate from live inventory data.',
            ];
        }
        if (($forecast['status'] ?? null) === 'error') {
            return [
                'status' => 'error',
                'source_type' => 'demo',
                'explanation' => 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.',
            ];
        }

        $rows = collect($forecast['rows']);
        if ($rows->isEmpty()) {
            return [
                'status' => 'insufficient_data',
                'source_type' => 'demo',
                'explanation' => 'The Sample/Demo forecast contains no eligible sample records. No live inventory data was used.',
            ];
        }

        $period = $forecast['forecast_period'] ?? 'the next sample forecast period';
        if (is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1) {
            $period = Carbon::createFromFormat('!Y-m', $period)->format('F Y');
        }
        $lines = [
            "Sample/Demo stock-out demand forecast for {$period} (not live inventory). Model: "
                .($forecast['model_version'] ?? 'unknown').'; Generated: '.($forecast['generated_at'] ?? 'unknown').'.',
        ];

        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_int($row['inventory_id'] ?? null)
                || ! is_int($row['category_id'] ?? null)
                || ! is_string($row['item_name'] ?? null)
                || ! is_string($row['category'] ?? null)
                || ! in_array($row['status'] ?? null, ['success', 'insufficient_history'], true)
                || ! is_int($row['historical_months_used'] ?? null)
                || ! is_int($row['required_months'] ?? null)) {
                return [
                    'status' => 'error',
                    'source_type' => 'demo',
                    'explanation' => 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.',
                ];
            }

            $identity = "Inventory ID {$row['inventory_id']}: {$row['item_name']} (Category ID {$row['category_id']}, {$row['category']})";
            if ($row['status'] === 'success'
                && is_int($row['forecast_demand'] ?? null)
                && is_string($row['unit'] ?? null)) {
                $window = $row['history_window'];
                $start = Carbon::createFromFormat('!Y-m', $window['start_month'])->format('F Y');
                $end = Carbon::createFromFormat('!Y-m', $window['end_month'])->format('F Y');
                $lines[] = "- {$identity} - {$row['forecast_demand']} {$row['unit']} forecast; {$row['historical_months_used']} verified months from {$start} to {$end}.";
                continue;
            }

            if ($row['status'] !== 'insufficient_history') {
                return [
                    'status' => 'error',
                    'source_type' => 'demo',
                    'explanation' => 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.',
                ];
            }

            $lines[] = "- {$identity} - insufficient verified history ({$row['historical_months_used']} months; {$row['required_months']} required).";
            if (($row['unknown_months'] ?? []) !== []) {
                $unknown = collect($row['unknown_months'])
                    ->map(fn (string $month): string => Carbon::createFromFormat('!Y-m', $month)->format('F Y'))
                    ->implode(', ');
                $lines[array_key_last($lines)] .= " Unknown months: {$unknown}.";
            }
        }

        return [
            'status' => 'success',
            'source' => 'local',
            'source_type' => 'demo',
            'explanation' => implode("\n", $lines),
        ];
    }

    protected function safePayload(array $row): array
    {
        return [
            'item_name' => $row['item_name'] ?? null,
            'forecast_month' => $row['forecast_month'] ?? null,
            'unit' => $row['unit'] ?? null,
            'available_stock' => $row['available_stock'] ?? null,
            'forecast_demand' => $row['forecast_demand'] ?? null,
            'safety_stock' => $row['safety_stock'] ?? null,
            'pending_demand' => $row['pending_demand'] ?? $row['pending_requests'] ?? null,
            'unmet_demand' => $row['unmet_demand'] ?? 0,
            'unmet_requesters' => $row['unmet_requesters'] ?? 0,
            'suggested_procurement' => $row['suggested_procurement'] ?? null,
            'needs_procurement' => $row['needs_procurement'] ?? false,
            'priority' => $row['priority'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'calculation_basis' => $row['calculation_basis'] ?? null,
            'advisory_status' => $row['advisory_status'] ?? null,
            'forecast_status' => $row['status'] ?? 'insufficient_data',
            // History depth. Without these the provider could report that a
            // figure was uncertain but never say *how* uncertain, which is the
            // one thing the custodian most needs from an explanation.
            'historical_months_used' => $row['historical_months_used'] ?? null,
            'required_months' => $row['required_months'] ?? null,
        ];
    }

    protected function explanationGroundingFailure(string $reply, array $payload): ?array
    {
        $reply = trim($reply);

        if ($reply === '' || ! is_string($payload['item_name'] ?? null) || ! $this->namesItem($reply, $payload['item_name'])) {
            return ['check' => 'no_item_named', 'forbidden_claim' => null, 'unapproved_numbers' => []];
        }

        $forbidden = $this->forbiddenClaim($reply);

        if ($forbidden !== null) {
            return ['check' => 'forbidden_claim', 'forbidden_claim' => $forbidden, 'unapproved_numbers' => []];
        }

        // Scoped to this one row, so the reply may quote this item's figures only.
        $unapproved = $this->unapprovedNumbers($reply, $this->numberFacts($payload));

        if ($unapproved !== []) {
            return ['check' => 'unapproved_number', 'forbidden_claim' => null, 'unapproved_numbers' => $unapproved];
        }

        // A reply may describe the item's priority, but may not contradict it.
        // Without this, "set its priority to Urgent" would pass every other check.
        //
        // The tier must be the one labelled for THIS field. The old pattern
        // accepted any tier word within 30 characters of the field name, so a
        // row whose own confidence is "Low" failed the moment a correct reply
        // said "Urgent priority with Low confidence": the Low belonging to
        // confidence was read as a contradiction of priority. Both fields now
        // resolve to the tier word nearest to their own label.
        foreach (['priority', 'confidence'] as $field) {
            $value = $payload[$field] ?? null;
            $stated = $this->statedTierFor($reply, $field);

            if (is_string($value) && $stated !== null && strcasecmp($stated, $value) !== 0) {
                return ['check' => 'contradicts_'.$field, 'forbidden_claim' => null, 'unapproved_numbers' => []];
            }
        }

        return null;
    }

    /**
     * The priority tier a reply attributes to one labelled field.
     *
     * Reads both natural orders — "High priority" and "priority: High" — and
     * accepts the tier only when it sits directly beside that field's own label.
     * A tier belonging to the other field is never returned, which is what the
     * old wide pattern got wrong.
     */
    private function statedTierFor(string $reply, string $field): ?string
    {
        // Every tier word and every field label, with byte offsets, so each tier
        // can be attributed to the label it actually belongs to.
        if (preg_match_all('/\b(?:Urgent|High|Medium|Normal|Low)\b/iu', $reply, $tiers, PREG_OFFSET_CAPTURE) === 0) {
            return null;
        }

        $labels = [];
        foreach (self::LABELLED_TIER_FIELDS as $name) {
            if (preg_match_all('/\b'.$name.'\b/iu', $reply, $found, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($found[0] as [$word, $offset]) {
                    $labels[] = ['name' => $name, 'offset' => (int) $offset];
                }
            }
        }

        if ($labels === []) {
            return null;
        }

        foreach ($tiers[0] as [$tier, $offset]) {
            $offset = (int) $offset;

            // The label this tier is closest to, in either order. "Urgent
            // priority" and "priority to Urgent" both resolve to the same pair,
            // and a tier belonging to the other field is never claimed here.
            $nearest = null;
            foreach ($labels as $label) {
                $distance = abs($label['offset'] - $offset);
                if ($nearest === null || $distance < $nearest['distance']) {
                    $nearest = ['name' => $label['name'], 'distance' => $distance];
                }
            }

            // Bounded so a tier in a later, unrelated sentence is not attributed
            // to a label far away. The filler word in "priority to Urgent" is a
            // few characters, so this is generous for real phrasings.
            if ($nearest['name'] !== $field || $nearest['distance'] > 40) {
                continue;
            }

            return $tier;
        }

        return null;
    }

    /**
     * The facts the number check is allowed to draw its allow-list from.
     *
     * unapprovedNumbers() reads the period and timestamp from the TOP level of
     * the facts array, while safePayload() keeps them on the row under
     * forecast_month. Passing only ['items' => [$payload]] therefore left the
     * allow-list with no period at all, so a reply naming the forecast month was
     * failed for its year — even though the system prompt had supplied
     * forecast_month as an approved fact. Both are projected here, under the
     * names the shared check already reads.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function numberFacts(array $payload): array
    {
        return [
            'items' => [$payload],
            'forecast_period' => $payload['forecast_month'] ?? null,
            'generated_at' => $payload['generated_at'] ?? null,
        ];
    }

    /**
     * Whether a reply names this item.
     *
     * A literal substring test also failed a reply that reordered the name —
     * "The 300ml Air Freshener is Urgent" for an item called
     * "Air Freshener 300ml" — which names the item perfectly well. Word order is
     * not a factual claim, so each word of the name is required to be present
     * instead. The distinctive parts (the numeric size in "300ml") are still
     * required, so a reply about a different size or product is not accepted.
     */
    private function namesItem(string $reply, string $itemName): bool
    {
        if (stripos($reply, $itemName) !== false) {
            return true;
        }

        $lowered = mb_strtolower($reply);
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $itemName, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) > 1 && ! str_contains($lowered, mb_strtolower($word))) {
                return false;
            }
        }

        return true;
    }

    protected function systemPrompt(array $payload): string
    {
        return "You are explaining a locally calculated inventory forecast.\n"
            . "Use only the supplied facts. Do not access a database, calculate, recalculate, change, or invent any value. Do not decide permissions or create orders or inventory changes.\n"
            . "The forecast is advisory only.\n"
            . "Approved facts:\n"
            . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function localExplanation(array $payload): string
    {
        if ($payload['forecast_status'] !== 'success') {
            return "There is not enough verified completed demand history to explain a forecast for {$payload['item_name']}. No ML estimate is available; this is advisory only.";
        }

        // Unmet demand is stated only when it is non-zero, so the common case
        // reads exactly as it did before this term existed.
        $unmet = (int) ($payload['unmet_demand'] ?? 0);
        $unmetClause = $unmet > 0
            ? ", and unmet staff demand of {$unmet} {$payload['unit']}"
            : '';

        return "{$payload['item_name']} has forecast demand of {$payload['forecast_demand']} {$payload['unit']}, safety stock of {$payload['safety_stock']} {$payload['unit']}, available stock of {$payload['available_stock']} {$payload['unit']}, pending demand of {$payload['pending_demand']} {$payload['unit']}{$unmetClause}. The suggested quantity is {$payload['suggested_procurement']} {$payload['unit']}; {$payload['advisory_status']}. This is advisory only.";
    }
}
