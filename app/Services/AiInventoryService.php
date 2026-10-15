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
    public function explainComparison(User $user, array $result): array
    {
        $fallback = is_string($result['explanation'] ?? null)
            ? $result['explanation']
            : 'The comparison result is available in the table.';
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_INVENTORY_STOCK)) {
            return ['text' => 'That information is not available for your role.', 'source' => 'local'];
        }

        $series = $result['series'][0] ?? null;
        if (! is_array($series)
            || ! in_array($series['period_one_status'] ?? null, ['available', 'verified_zero', 'unavailable'], true)
            || ! in_array($series['period_two_status'] ?? null, ['available', 'verified_zero', 'unavailable'], true)) {
            return ['text' => $fallback, 'source' => 'local'];
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

        $reply = $this->geminiApi->generate(
            "Format the explanation as exactly five lines: line 1 is '{metric} for {scope}'; line 2 is '{period_one_label}: {status}'; line 3 is '{period_two_label}: {status}'; line 4 is blank; line 5 is the percentage-change note. For each status use exactly 'verified zero activity', 'history unavailable', or 'activity recorded' to match its JSON status. For zero_denominator, line 5 must be 'Percentage change is unavailable because the {period_one_label} total is zero.' For unavailable percentage status, state it is unavailable because one or both period totals are unavailable. Otherwise say 'Percentage change is shown in the table.' Use only the JSON facts. Do not invent or alter labels or statuses, calculate or include totals, differences, or percentages, add causes or recommendations, or use markdown.",
            json_encode($facts, JSON_UNESCAPED_SLASHES) ?: '',
            ['temperature' => 0, 'maxOutputTokens' => 160]
        );

        $accepted = is_string($reply) && $this->isSafeComparisonExplanation($reply, $facts);

        return [
            'text' => $accepted ? trim($reply) : $fallback,
            'source' => $accepted ? 'ai' : 'local',
        ];
    }

    /**
     * Writes a grounded plain-language explanation of historical usage.
     *
     * @return array{text: string, source: 'ai'|'local'}
     */
    public function explainHistoricalComparison(User $user, array $result): array
    {
        $fallback = $this->historicalComparisonFallback($result);
        if (! $this->policy->allows($user, AiCapabilityPolicy::VIEW_INVENTORY_STOCK)) {
            return ['text' => 'That information is not available for your role.', 'source' => 'local'];
        }

        $row = $result['series'][0] ?? null;
        $months = $result['months'] ?? null;
        if (! is_array($row) || ! is_array($months) || count($months) !== 2) {
            return ['text' => $fallback, 'source' => 'local'];
        }

        $facts = [
            'metric' => $result['metric_label'] ?? 'Monthly usage',
            'scope' => $result['scope_label'] ?? 'Inventory',
            'unit' => $row['unit'] ?? '',
            'period_one_label' => $months[0]['label'] ?? '',
            'period_two_label' => $months[1]['label'] ?? '',
            'period_one_total' => $row['period_one'] ?? null,
            'period_two_total' => $row['period_two'] ?? null,
            'difference' => $row['difference'] ?? null,
            'percentage_change' => $row['percentage_change'] ?? null,
            'percentage_change_status' => $row['percentage_change_status'] ?? 'unavailable',
            'trend' => match (true) {
                ($row['difference'] ?? null) > 0 => 'increased',
                ($row['difference'] ?? null) < 0 => 'decreased',
                ($row['difference'] ?? null) === 0 => 'unchanged',
                default => 'cannot be determined',
            },
            'data_source' => $result['source_label'] ?? 'Historical usage data',
        ];

        $reply = $this->geminiApi->generate(
            'Explain this comparison to a school property custodian in 3-5 clear, useful sentences. '
                .'Explain what changed between the two months and what the measured difference means for recorded monthly usage. '
                .'Include both totals, the absolute difference, and the percentage when available. '
                .'Use the exact trend word provided in the facts (increased, decreased, unchanged, or cannot be determined). '
                .'For zero_denominator, say percentage change cannot be calculated because the first month total is zero. '
                .'Clarify that this describes historical recorded usage only; do not call it current stock or a forecast. '
                .'Do not invent reasons for the change, recommendations, or any facts/numbers not in the JSON. '
                .'Use the exact item/unit/month labels and numeric values from the JSON. Do not use markdown headings or bullets.',
            json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ['temperature' => 0.2, 'maxOutputTokens' => 350]
        );

        $accepted = is_string($reply) && $this->isSafeHistoricalComparisonExplanation($reply, $facts);

        return [
            'text' => $accepted ? trim($reply) : $fallback,
            'source' => $accepted ? 'ai' : 'local',
        ];
    }

    private function historicalComparisonFallback(array $result): string
    {
        $row = $result['series'][0] ?? [];
        $months = $result['months'] ?? [];
        if (! is_array($row) || count($months) !== 2) {
            return 'The historical usage comparison is available in the table.';
        }

        $one = $row['period_one'] ?? null;
        $two = $row['period_two'] ?? null;
        $unit = (string) ($row['unit'] ?? 'units');
        $scope = (string) ($result['scope_label'] ?? 'the selected inventory');
        $firstMonth = (string) ($months[0]['label'] ?? 'the first month');
        $secondMonth = (string) ($months[1]['label'] ?? 'the second month');

        if (! is_numeric($one) || ! is_numeric($two)) {
            return 'The historical usage comparison cannot be fully interpreted because one or both monthly totals are unavailable. See the table for the verified values.';
        }

        $difference = (int) ($row['difference'] ?? 0);
        $trend = match (true) {
            $difference > 0 => 'increased by '.number_format(abs($difference)).' '.$unit,
            $difference < 0 => 'decreased by '.number_format(abs($difference)).' '.$unit,
            default => 'did not change',
        };
        $percentage = match ($row['percentage_change_status'] ?? null) {
            'available' => ' That is a '.number_format(abs((float) $row['percentage_change']), 2).'% '
                .($difference > 0 ? 'increase' : ($difference < 0 ? 'decrease' : 'change of zero percent')).'.',
            'zero_denominator' => ' A percentage change cannot be calculated because the first month total is zero.',
            default => '',
        };

        return 'For '.$scope.', recorded monthly usage was '.number_format((int) $one).' '.$unit
            .' in '.$firstMonth.' and '.number_format((int) $two).' '.$unit.' in '.$secondMonth.'. '
            .'Usage '.$trend.'.'.$percentage
            .' This compares historical recorded usage; it does not explain why usage changed or predict future demand.';
    }

    private function isSafeHistoricalComparisonExplanation(string $reply, array $facts): bool
    {
        $reply = trim($reply);
        if (mb_strlen($reply) < 80 || mb_strlen($reply) > 900) {
            return false;
        }
        foreach (['scope', 'unit', 'period_one_label', 'period_two_label'] as $key) {
            if (! is_string($facts[$key]) || $facts[$key] === '' || mb_stripos($reply, $facts[$key]) === false) {
                return false;
            }
        }

        $requiredNumbers = [
            $facts['period_one_total'],
            $facts['period_two_total'],
            $facts['difference'] === null ? null : abs((int) $facts['difference']),
        ];
        $numberTokens = [];
        preg_match_all('/\b\d[\d,]*(?:\.\d+)?%?\b/u', $reply, $matches);
        foreach ($matches[0] as $token) {
            $numberTokens[] = [
                'value' => (float) rtrim(str_replace(',', '', $token), '%'),
                'percentage' => str_ends_with($token, '%'),
            ];
        }

        $allowedNumbers = [
            (float) substr((string) $facts['period_one_label'], -4),
            (float) substr((string) $facts['period_two_label'], -4),
            ...array_map(
                fn (mixed $number): float => abs((float) $number),
                array_filter($requiredNumbers, fn (mixed $number): bool => $number !== null)
            ),
        ];
        $expectedPercentage = ($facts['percentage_change_status'] ?? null) === 'available'
            ? abs((float) $facts['percentage_change'])
            : null;

        foreach ($numberTokens as $token) {
            $allowed = collect($allowedNumbers)->contains(
                fn (float $number): bool => abs($number - $token['value']) < 0.001
            );
            if ($expectedPercentage !== null && abs($expectedPercentage - $token['value']) < 0.051) {
                $allowed = true;
            }
            if (! $allowed) {
                return false;
            }
        }
        foreach ($requiredNumbers as $number) {
            if ($number !== null && ! collect($numberTokens)->contains(
                fn (array $token): bool => abs(abs((float) $number) - $token['value']) < 0.001
            )) {
                return false;
            }
        }
        if ($expectedPercentage !== null && ! collect($numberTokens)->contains(
            fn (array $token): bool => abs($expectedPercentage - $token['value']) < 0.051
        )) {
            return false;
        }

        $trend = $facts['trend'];
        if (in_array($trend, ['increased', 'decreased', 'unchanged'], true)) {
            if (preg_match('/\b(increased|decreased|unchanged)\b/iu', $reply, $trendMatch) !== 1
                || mb_strtolower($trendMatch[1]) !== $trend) {
                return false;
            }
        }
        if (($facts['percentage_change_status'] ?? null) === 'zero_denominator'
            && stripos($reply, 'cannot be calculated') === false) {
            return false;
        }

        return true;
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
