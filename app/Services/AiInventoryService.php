<?php

namespace App\Services;

use App\Models\User;
use App\Services\Response\AnswerComposer;
use App\Services\Response\LocalAnswerComposer;

class AiInventoryService
{
    protected ProcurementQuestionMatcher $procurementMatcher;

    public function __construct(
        protected AiCapabilityPolicy $policy,
        protected GeminiApiService $geminiApi,
        protected AnswerComposer $answerComposer,
        protected InventoryAnswerService $answerService,
        protected LocalAnswerComposer $localComposer,
        ?ProcurementQuestionMatcher $procurementMatcher = null,
    )
    {
        // Nullable so existing direct construction (tests, and any future
        // caller) keeps working; the container always supplies the singleton.
        $this->procurementMatcher = $procurementMatcher ?? new ProcurementQuestionMatcher();
    }

    /**
     * Generate or fall back to a reply for an answered turn.
     *
     * The facts come from $result, never from the conversation: the provider is
     * given the authorised fact packet and nothing else, so it cannot be
     * steered by earlier chat text.
     */
public function ask(User $user, string $question, array $result): string
    {
        // Procurement and restock questions are answered by AI Decision Support
        // on the Demand Forecast page. This assistant no longer routes them, so
        // it points users there instead of returning an unrelated or empty
        // answer. Checked first so it does not depend on how the question was
        // classified.
        if ($this->procurementMatcher->matches($question)) {
            return 'Procurement questions are handled by AI Decision Support on the Demand Forecast page. '
                .'Open Demand Forecast in the sidebar and ask "What should we purchase first?" there. '
                .'This assistant covers stock, assignments, requests, locations, low-stock items, maintenance, disposal, and reports.';
        }

        if (($result['status'] ?? null) !== 'success') {
            return ($result['status'] ?? null) === 'forbidden'
                ? 'That information is not available for your role.'
                : 'I could not safely answer that question.';
        }

        $intent = $result['intent'] ?? null;
        $capability = $result['capability'] ?? null;
        $localPacket = ['answer' => $result['answer'] ?? []];
        if (! is_string($capability) || ! $this->policy->allows($user, $capability)) {
            return 'That information is not available for your role.';
        }

        $facts = $result['explanation_data'] ?? null;
        if (! is_array($facts) || $facts === []) {
            return $this->fallbackFor($user, $result, $intent, $localPacket);
        }

        // Factual turns may now be narrated too, so they reach the provider.
        // AnswerComposer still gates on the capability allowlist and on the
        // grounding validator, and the deterministic reply remains the
        // fallback in every other case.
        return $this->answerComposer->compose(
            $user,
            $question,
            $facts,
            $this->fallbackFor($user, $result, $intent, $localPacket),
            $capability,
            $this->providerModeFor($intent),
        );
    }

    /**
     * The reply to show when the provider does not phrase this turn.
     *
     * Explanation turns keep the explanation formatter they have always used.
     * Factual turns use localReply() — the same reply the controller returned
     * for them before generation was widened — so every question that answered
     * before still answers identically.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $localPacket
     */
    private function fallbackFor(User $user, array $result, mixed $intent, array $localPacket): string
    {
        return in_array($intent, ['explanation', 'forecast', 'recommendation'], true)
            ? $this->localComposer->compose($localPacket)
            : $this->answerService->localReply($result, $user);
    }

    /**
     * Whether a turn is narrated under the strict or the bounded grounding
     * rule.
     *
     * Explanation turns are about meaning, so they keep the strict rule: every
     * item name in the facts must appear in the reply. Factual turns list data,
     * where requiring containment is unachievable for a 25-row answer, so they
     * use the bounded rule: numbers must still come from the facts, and no name
     * outside the fact set may appear.
     */
    private function providerModeFor(mixed $intent): string
    {
        return in_array($intent, ['explanation', 'forecast', 'recommendation'], true)
            ? AnswerComposer::MODE_STRICT
            : AnswerComposer::MODE_BOUNDED;
    }

    /**
     * Recognises questions about what to buy or reorder.
     *
     * Procurement questions are delegated to AI Decision Support, so this is
     * now the shared ProcurementQuestionMatcher rather than a second private
     * copy of a narrower pattern list.
     *
     * @see ProcurementQuestionMatcher
     */
    public function explainComparison(User $user, array $result): string
    {
        $fallback = is_string($result['explanation'] ?? null)
            ? $result['explanation']
            : 'The comparison result is available in the table.';
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_INVENTORY_STOCK)) {
            return 'That information is not available for your role.';
        }

        $series = $result['series'][0] ?? null;
        if (! is_array($series)
            || ! in_array($series['period_one_status'] ?? null, ['available', 'verified_zero', 'unavailable'], true)
            || ! in_array($series['period_two_status'] ?? null, ['available', 'verified_zero', 'unavailable'], true)) {
            return $fallback;
        }

        $trend = match (true) {
            $series['period_one_status'] === 'unavailable' || $series['period_two_status'] === 'unavailable' => 'unavailable',
            ($series['difference'] ?? null) > 0 => 'increased',
            ($series['difference'] ?? null) < 0 => 'decreased',
            default => 'unchanged',
        };
        $facts = [
            'metric' => $result['metric_label'] ?? 'Inventory activity',
            'scope' => $result['scope_label'] ?? 'Inventory',
            'period_one_label' => $result['months'][0]['label'] ?? 'First period',
            'period_two_label' => $result['months'][1]['label'] ?? 'Second period',
            'period_one_status' => $series['period_one_status'],
            'period_two_status' => $series['period_two_status'],
            'trend' => $trend,
            'percentage_change_status' => $series['percentage_change_status'] ?? 'unavailable',
        ];

        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            return $fallback;
        }

        $reply = $this->geminiApi->generate(
            "Format the explanation as exactly five lines: line 1 is '{metric} for {scope}'; line 2 is '{period_one_label}: {status}'; line 3 is '{period_two_label}: {status}'; line 4 is blank; line 5 is the percentage-change note. For each status use exactly 'verified zero activity', 'history unavailable', or 'activity recorded' to match its JSON status. For zero_denominator, line 5 must be 'Percentage change is unavailable because the {period_one_label} total is zero.' For unavailable percentage status, state it is unavailable because one or both period totals are unavailable. Otherwise say 'Percentage change is shown in the table.' Use only the JSON facts. Do not invent or alter labels or statuses, calculate or include totals, differences, or percentages, add causes or recommendations, or use markdown.",
            json_encode($facts, JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0, 'maxOutputTokens' => 160]
        );

        return is_string($reply) && $this->isSafeComparisonExplanation($reply, $facts)
            ? trim($reply)
            : $fallback;
    }

    public function explainComparisonFollowUp(User $user, array $summary): string
    {
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_INVENTORY_STOCK)) {
            return 'That information is not available for your role.';
        }

        $row = $summary['series'][0];
        if ($row['period_one_status'] === 'verified_zero' && $row['period_two_status'] === 'verified_zero') {
            return 'Yes. The comparison confirms no '.$summary['metric_label'].' activity for '.$summary['scope_label'].' in '
                .$summary['months'][0]['label'].' or '.$summary['months'][1]['label'].'. Both periods have verified zero activity.';
        }

        $periodDetails = [];
        foreach ([0, 1] as $index) {
            $status = $row['period_' . ($index === 0 ? 'one' : 'two') . '_status'];
            $periodDetails[] = match ($status) {
                'verified_zero' => $summary['months'][$index]['label'].': verified zero activity',
                'unavailable' => $summary['months'][$index]['label'].': history unavailable, so activity cannot be confirmed',
                default => $summary['months'][$index]['label'].': activity was recorded',
            };
        }

        if ($row['period_one_status'] === 'unavailable' || $row['period_two_status'] === 'unavailable') {
            return 'I cannot confirm that there was no activity in both periods for '.$summary['metric_label'].' on '
                .$summary['scope_label'].'. '.$periodDetails[0].'; '.$periodDetails[1].'.';
        }

        return 'No. The comparison shows that activity was not zero in both periods for '.$summary['metric_label'].' on '
            .$summary['scope_label'].'. '.$periodDetails[0].'; '.$periodDetails[1].'.';
    }

    private function isSafeComparisonExplanation(string $reply, array $facts): bool
    {
        $reply = trim($reply);
        if ($reply === '') {
            return false;
        }
        $lines = preg_split('/\R/u', $reply);
        if (! is_array($lines) || count($lines) !== 5 || $lines[3] !== '') {
            return false;
        }
        $expectedTitle = $facts['metric'].' for '.$facts['scope'];
        $expectedPeriodOne = $facts['period_one_label'].': '.$this->comparisonStatusLabel($facts['period_one_status']);
        $expectedPeriodTwo = $facts['period_two_label'].': '.$this->comparisonStatusLabel($facts['period_two_status']);
        $expectedNote = match ($facts['percentage_change_status']) {
            'zero_denominator' => 'Percentage change is unavailable because the '.$facts['period_one_label'].' total is zero.',
            'unavailable' => 'Percentage change is unavailable because one or both period totals are unavailable.',
            default => 'Percentage change is shown in the table.',
        };
        if (strcasecmp($lines[0], $expectedTitle) !== 0
            || strcasecmp($lines[1], $expectedPeriodOne) !== 0
            || strcasecmp($lines[2], $expectedPeriodTwo) !== 0
            || strcasecmp($lines[4], $expectedNote) !== 0) {
            return false;
        }

        $nonDateText = str_replace([$expectedTitle, $facts['period_one_label'], $facts['period_two_label']], '', $reply);
        if (preg_match('/\d/u', $nonDateText) === 1) {
            return false;
        }

        return true;
    }

    private function comparisonStatusLabel(string $status): string
    {
        return match ($status) {
            'verified_zero' => 'verified zero activity',
            'unavailable' => 'history unavailable',
            default => 'activity recorded',
        };
    }

}
