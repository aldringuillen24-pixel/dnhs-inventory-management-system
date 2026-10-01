<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class ForecastExplanationService
{
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
        $localExplanation = $this->localExplanation($payload);

        if (($payload['forecast_status'] ?? null) !== 'success'
            || ! is_string(config('services.gemini.api_key'))
            || trim((string) config('services.gemini.api_key')) === '') {
            return [
                'status' => 'success',
                'source' => 'local',
                'payload' => $payload,
                'explanation' => $localExplanation,
            ];
        }

        $explanation = $this->geminiApi->generate(
            $this->systemPrompt($payload),
            'Explain why this calculated forecast may require procurement. Use only the supplied facts.',
            ['temperature' => 0.1, 'maxOutputTokens' => 300]
        );
        if (is_string($explanation) && $this->isGroundedProviderReply($explanation, $payload)) {
            return [
                'status' => 'success',
                'source' => 'provider',
                'payload' => $payload,
                'explanation' => trim($explanation),
            ];
        }

        return [
            'status' => 'success',
            'source' => 'local',
            'payload' => $payload,
            'explanation' => $localExplanation,
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
            'suggested_procurement' => $row['suggested_procurement'] ?? null,
            'needs_procurement' => $row['needs_procurement'] ?? false,
            'priority' => $row['priority'] ?? null,
            'confidence' => $row['confidence'] ?? null,
            'calculation_basis' => $row['calculation_basis'] ?? null,
            'advisory_status' => $row['advisory_status'] ?? null,
            'forecast_status' => $row['status'] ?? 'insufficient_data',
        ];
    }

    protected function isGroundedProviderReply(string $reply, array $payload): bool
    {
        $reply = trim($reply);
        if ($reply === ''
            || ! is_string($payload['item_name'] ?? null)
            || stripos($reply, $payload['item_name']) === false
            || preg_match('/\b(?:place|create|submit|approve|buy|purchase\s+order|update\s+(?:the\s+)?inventory|change\s+(?:the\s+)?stock)\b/iu', $reply) === 1) {
            return false;
        }

        $approvedNumbers = collect($payload)
            ->filter(fn ($value): bool => is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(?:-\d+)*$/', $value) === 1))
            ->flatMap(function ($value): array {
                preg_match_all('/\d+(?:[,.]\d+)*/u', (string) $value, $matches);

                return array_map(fn (string $number): string => str_replace(',', '', $number), $matches[0]);
            })
            ->unique();
        preg_match_all('/(?<![\pL])\d+(?:[,.]\d+)*(?![\pL])/u', $reply, $replyNumbers);
        foreach ($replyNumbers[0] as $number) {
            if (! $approvedNumbers->contains(str_replace(',', '', $number))) {
                return false;
            }
        }

        foreach (['priority', 'confidence'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_string($value)
                && preg_match('/\b'.$field.'\b[^.\n]{0,30}\b(?:Urgent|High|Medium|Normal|Low)\b/iu', $reply, $labelMatch) === 1
                && preg_match('/\b'.preg_quote($value, '/').'\b/iu', $labelMatch[0]) !== 1) {
                return false;
            }
        }

        foreach ([
            'forecast demand' => 'forecast_demand',
            'safety stock' => 'safety_stock',
            'available stock' => 'available_stock',
            'pending demand' => 'pending_demand',
            'suggested quantity' => 'suggested_procurement',
        ] as $label => $field) {
            if (! is_numeric($payload[$field] ?? null)) {
                continue;
            }
            $number = preg_quote((string) $payload[$field], '/');
            if (preg_match('/\b'.$label.'\b[^0-9\n]{0,30}'.$number.'(?!\d)/iu', $reply) !== 1) {
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

        if (! $payload['needs_procurement']) {
            return "{$payload['item_name']} has forecast demand of {$payload['forecast_demand']} {$payload['unit']}, safety stock of {$payload['safety_stock']} {$payload['unit']}, available stock of {$payload['available_stock']} {$payload['unit']}, and pending demand of {$payload['pending_demand']} {$payload['unit']}. The suggested quantity is {$payload['suggested_procurement']} {$payload['unit']}; {$payload['advisory_status']}. This is advisory only.";
        }

        return "{$payload['item_name']} has forecast demand of {$payload['forecast_demand']} {$payload['unit']}, safety stock of {$payload['safety_stock']} {$payload['unit']}, available stock of {$payload['available_stock']} {$payload['unit']}, and pending demand of {$payload['pending_demand']} {$payload['unit']}. The suggested quantity is {$payload['suggested_procurement']} {$payload['unit']}; {$payload['advisory_status']}. This is advisory only.";
    }
}
