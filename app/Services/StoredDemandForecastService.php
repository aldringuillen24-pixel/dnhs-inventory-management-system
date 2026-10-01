<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class StoredDemandForecastService
{
    private const FORECAST_PATH = 'forecast/forecast.json';

    public function readDemo(User $user): array
    {
        if (! app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            return $this->demoResult('forbidden');
        }

        $disk = Storage::disk('forecast');
        $path = (string) config('forecast.demo.output_path', 'forecast/demo/forecast.json');

        try {
            if (! $disk->exists($path)) {
                return $this->demoResult('missing');
            }

            $payload = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
            $this->validateDemoPayload($payload);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->demoResult('error');
        }

        $rows = collect([...$payload['forecasts'], ...$payload['insufficient_history']])
            ->map(fn (array $item): array => [
                'inventory_id' => $item['inventory_id'],
                'item_name' => $item['item_name'],
                'category_id' => $item['category_id'],
                'category' => $item['category'],
                'unit' => $item['unit'],
                'forecast_month' => $item['forecast_month'],
                'history_window' => $item['history_window'],
                'monthly_usage' => $item['monthly_usage'],
                'unknown_months' => $item['unknown_months'],
                'months_used' => $item['months_used'],
                'historical_months_used' => $item['verified_months_used'],
                'required_months' => $item['required_months'],
                'model_version' => $item['model_version'],
                'confidence' => $item['confidence'],
                'forecast_demand' => $item['predicted_quantity'] ?? null,
                'status' => $item['status'],
                'source_type' => 'demo',
            ])
            ->sortBy([['inventory_id', 'asc'], ['category_id', 'asc']])
            ->values();

        return [
            'status' => $payload['forecasts'] === [] ? 'insufficient_data' : 'success',
            'source_type' => 'demo',
            'model_version' => $payload['model_version'],
            'generated_at' => $payload['generated_at'],
            'forecast_period' => $rows->pluck('forecast_month')->filter()->unique()->count() === 1
                ? $rows->first()['forecast_month']
                : null,
            'rows' => $rows->all(),
            'summary' => [
                'items_forecasted' => count($payload['forecasts']),
                'items_insufficient_history' => count($payload['insufficient_history']),
            ],
        ];
    }

    private function validateDemoPayload(mixed $payload): void
    {
        $rootKeys = ['schema_version', 'source_type', 'model_version', 'generated_at', 'forecasts', 'insufficient_history'];
        if (! is_array($payload)
            || array_diff(array_keys($payload), $rootKeys) !== []
            || array_diff($rootKeys, array_keys($payload)) !== []
            || ($payload['schema_version'] ?? null) !== 1
            || ($payload['source_type'] ?? null) !== 'demo'
            || ($payload['model_version'] ?? null) !== 'demo-linear-regression-v1'
            || ! is_string($payload['generated_at'] ?? null)
            || ! is_array($payload['forecasts'] ?? null)
            || ! array_is_list($payload['forecasts'])
            || ! is_array($payload['insufficient_history'] ?? null)
            || ! array_is_list($payload['insufficient_history'])) {
            throw new \UnexpectedValueException('Demo forecast JSON has an invalid root structure or source.');
        }

        Carbon::parse($payload['generated_at']);
        $seen = [];
        foreach ([...$payload['forecasts'], ...$payload['insufficient_history']] as $item) {
            $this->validateDemoItem($item, $seen);
        }
    }

    private function validateDemoItem(mixed $item, array &$seen): void
    {
        $allowedKeys = [
            'inventory_id', 'item_name', 'category_id', 'category', 'unit', 'forecast_month',
            'history_window', 'monthly_usage', 'unknown_months', 'months_used', 'verified_months_used',
            'required_months', 'model_version', 'confidence', 'status', 'predicted_quantity',
        ];
        $requiredKeys = array_diff($allowedKeys, ['predicted_quantity']);
        if (! is_array($item)
            || array_diff(array_keys($item), $allowedKeys) !== []
            || array_diff($requiredKeys, array_keys($item)) !== []
            || ! is_int($item['inventory_id'] ?? null) || $item['inventory_id'] < 1
            || ! is_int($item['category_id'] ?? null) || $item['category_id'] < 1
            || ! is_string($item['item_name'] ?? null) || trim($item['item_name']) === '' || mb_strlen($item['item_name']) > 255
            || ! is_string($item['category'] ?? null) || trim($item['category']) === '' || mb_strlen($item['category']) > 255
            || ! is_string($item['unit'] ?? null) || trim($item['unit']) === '' || mb_strlen($item['unit']) > 64
            || ($item['model_version'] ?? null) !== 'demo-linear-regression-v1'
            || ! in_array($item['confidence'] ?? null, ['Low', 'Medium', 'High'], true)) {
            throw new \UnexpectedValueException('Demo forecast contains an invalid item identity or metadata.');
        }

        $identityKey = $item['inventory_id'].':'.$item['category_id'];
        if (isset($seen[$identityKey])) {
            throw new \UnexpectedValueException('Demo forecast contains duplicate inventory/category identities.');
        }
        $seen[$identityKey] = true;

        $window = $item['history_window'] ?? null;
        if (! is_array($window)
            || array_diff(array_keys($window), ['start_month', 'end_month', 'completeness']) !== []
            || array_diff(['start_month', 'end_month', 'completeness'], array_keys($window)) !== []
            || ! $this->isValidMonth($window['start_month'])
            || ! $this->isValidMonth($window['end_month'])
            || $window['start_month'] > $window['end_month']
            || $this->monthCount($window['start_month'], $window['end_month']) > 120
            || ! in_array($window['completeness'], ['complete', 'incomplete'], true)
            || ! $this->isValidMonth($item['forecast_month'] ?? null)
            || $item['forecast_month'] !== $this->nextMonth($window['end_month'])) {
            throw new \UnexpectedValueException('Demo forecast contains an invalid history window.');
        }

        $windowMonths = $this->monthsBetween($window['start_month'], $window['end_month']);
        $usage = $item['monthly_usage'] ?? null;
        $monthsUsed = $item['months_used'] ?? null;
        $unknownMonths = $item['unknown_months'] ?? null;
        if (! is_array($usage) || ! is_array($monthsUsed) || ! array_is_list($monthsUsed)
            || ! is_array($unknownMonths) || ! array_is_list($unknownMonths)
            || $monthsUsed !== array_keys($usage)
            || count(array_unique($monthsUsed)) !== count($monthsUsed)
            || count(array_unique($unknownMonths)) !== count($unknownMonths)
            || array_intersect($monthsUsed, $unknownMonths) !== []) {
            throw new \UnexpectedValueException('Demo forecast contains invalid verified or unknown months.');
        }
        foreach ($usage as $month => $quantity) {
            if (! in_array($month, $windowMonths, true)
                || (! is_int($quantity) && ! is_float($quantity))
                || ! is_finite((float) $quantity)
                || $quantity < 0) {
                throw new \UnexpectedValueException('Demo forecast contains invalid monthly usage.');
            }
        }
        foreach ($unknownMonths as $month) {
            if (! in_array($month, $windowMonths, true)) {
                throw new \UnexpectedValueException('Demo forecast marks a month outside the history window as unknown.');
            }
        }
        if (($window['completeness'] === 'complete' && ($unknownMonths !== [] || $monthsUsed !== $windowMonths))
            || ($window['completeness'] === 'incomplete' && array_diff($windowMonths, [...$monthsUsed, ...$unknownMonths]) !== [])) {
            throw new \UnexpectedValueException('Demo forecast completeness does not match its verified and unknown months.');
        }

        $verifiedCount = count($monthsUsed);
        $requiredCount = $item['required_months'] ?? null;
        $status = $item['status'] ?? null;
        if (! is_int($requiredCount) || $requiredCount < 1
            || ($item['verified_months_used'] ?? null) !== $verifiedCount
            || ($status === 'success'
                ? ($verifiedCount < $requiredCount || ! is_int($item['predicted_quantity'] ?? null) || $item['predicted_quantity'] < 0)
                : ($status !== 'insufficient_history' || $verifiedCount >= $requiredCount || array_key_exists('predicted_quantity', $item)))) {
            throw new \UnexpectedValueException('Demo forecast history threshold or prediction status is inconsistent.');
        }
    }

    private function isValidMonth(mixed $month): bool
    {
        if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return false;
        }

        return Carbon::createFromFormat('!Y-m', $month)->format('Y-m') === $month;
    }

    private function monthsBetween(string $startMonth, string $endMonth): array
    {
        $months = [];
        $month = Carbon::createFromFormat('!Y-m', $startMonth);
        $end = Carbon::createFromFormat('!Y-m', $endMonth);
        while ($month->lte($end)) {
            $months[] = $month->format('Y-m');
            $month->addMonth();
        }

        return $months;
    }

    private function monthCount(string $startMonth, string $endMonth): int
    {
        [$startYear, $startNumber] = array_map('intval', explode('-', $startMonth));
        [$endYear, $endNumber] = array_map('intval', explode('-', $endMonth));

        return ($endYear - $startYear) * 12 + $endNumber - $startNumber + 1;
    }

    private function nextMonth(string $month): string
    {
        return Carbon::createFromFormat('!Y-m', $month)->addMonth()->format('Y-m');
    }

    private function demoResult(string $status): array
    {
        return [
            'status' => $status,
            'source_type' => 'demo',
            'model_version' => null,
            'generated_at' => null,
            'forecast_period' => null,
            'rows' => [],
            'summary' => ['items_forecasted' => 0, 'items_insufficient_history' => 0],
        ];
    }

    /**
     * Return the most recently generated Python forecast in the shape used by
     * the Demand Forecast page, or null when training has not run yet.
     */
    public function read(User $user): array
    {
        if (! app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            return $this->resultWithStatus('forbidden');
        }

        $disk = Storage::disk('forecast');

        try {
            $trainingStatusPath = 'forecast/training-status.json';
            if ($disk->exists($trainingStatusPath)) {
                $trainingStatus = json_decode($disk->get($trainingStatusPath), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($trainingStatus) || ! in_array($trainingStatus['status'] ?? null, ['running', 'success', 'failed'], true)) {
                    throw new \UnexpectedValueException('Live forecast training state is invalid.');
                }
                if ($trainingStatus['status'] !== 'success') {
                    return $this->resultWithStatus('failed');
                }
            }
            if (! $disk->exists(self::FORECAST_PATH)) {
                return $this->resultWithStatus('missing');
            }

            $payload = json_decode($disk->get(self::FORECAST_PATH), true, 512, JSON_THROW_ON_ERROR);
            $generatedAt = $this->validateLivePayload($payload);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->errorResult();
        }

        if ($generatedAt->gt(now()->addMinutes(5))) {
            return $this->resultWithStatus('error');
        }
        if ($generatedAt->lt(now()->subHours(max(1, (int) config('forecast.maximum_age_hours', 168))))) {
            return $this->resultWithStatus('stale', $generatedAt->toIso8601String());
        }

        $forecastItems = [...$payload['forecasts'], ...$payload['insufficient_history']];
        $inventory = Inventory::query()
            ->with('category:category_id,category_name')
            ->whereIn('item_id', collect($forecastItems)->pluck('inventory_id')->all())
            ->get()
            ->keyBy('item_id');
        $pendingDemand = AssignmentRequest::query()
            ->whereIn('item_id', $inventory->keys())
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval'])
            ->get(['item_id', 'quantity'])
            ->groupBy('item_id')
            ->map(fn ($requests): int => (int) $requests->sum('quantity'));

        $rows = collect();
        foreach ($forecastItems as $item) {
            $record = $inventory->get($item['inventory_id']);
            if (! $record || (int) $record->category_id !== $item['category_id']) {
                return $this->errorResult();
            }

            $rows->push($this->validatedForecastRow(
                $item,
                $record,
                (int) ($pendingDemand->get($record->item_id) ?? 0),
            ));
        }

        $successfulRows = $rows->where('status', 'success');
        $forecastMonth = collect($payload['forecasts'])->pluck('forecast_month')->filter()->first();

        return [
            'status' => $successfulRows->isNotEmpty() ? 'success' : 'insufficient_data',
            'rows' => $rows->all(),
            'forecast_period' => $forecastMonth ? Carbon::parse($forecastMonth . '-01')->format('F Y') : null,
            'generated_at' => $generatedAt->toIso8601String(),
            'source_type' => 'live',
            'model_version' => $payload['model_version'],
            'summary' => [
                'items_forecasted' => $successfulRows->count(),
                'items_needing_procurement' => $successfulRows->where('needs_procurement', true)->count(),
                'suggested_units_to_procure' => (int) $successfulRows->sum('suggested_procurement'),
                'confidence' => $this->summaryConfidence($successfulRows->pluck('confidence')->all()),
            ],
        ];
    }

    private function validateLivePayload(mixed $payload): Carbon
    {
        $rootKeys = ['schema_version', 'source_type', 'model_version', 'generated_at', 'forecasts', 'insufficient_history'];
        if (! is_array($payload)
            || array_diff(array_keys($payload), $rootKeys) !== []
            || array_diff($rootKeys, array_keys($payload)) !== []
            || ($payload['schema_version'] ?? null) !== 2
            || ($payload['source_type'] ?? null) !== 'live'
            || ($payload['model_version'] ?? null) !== 'demand-linear-regression-v2'
            || ! is_string($payload['generated_at'] ?? null)
            || ! is_array($payload['forecasts'] ?? null)
            || ! array_is_list($payload['forecasts'])
            || ! is_array($payload['insufficient_history'] ?? null)
            || ! array_is_list($payload['insufficient_history'])) {
            throw new \UnexpectedValueException('Live forecast JSON has an invalid root structure or source.');
        }

        $generatedAt = Carbon::parse($payload['generated_at']);
        $seen = [];
        foreach ([...$payload['forecasts'], ...$payload['insufficient_history']] as $item) {
            $this->validateLiveItem($item, $seen);
        }

        return $generatedAt;
    }

    private function validateLiveItem(mixed $item, array &$seen): void
    {
        $commonKeys = [
            'inventory_id', 'item_name', 'category_id', 'category', 'unit', 'forecast_month',
            'history_window', 'monthly_usage', 'unknown_months', 'months_used', 'verified_months_used',
            'required_months', 'model_version', 'confidence', 'status',
        ];
        if (! is_array($item)
            || array_diff($commonKeys, array_keys($item)) !== []
            || array_diff(array_keys($item), [...$commonKeys, 'predicted_quantity', 'validation']) !== []
            || ! is_int($item['inventory_id']) || $item['inventory_id'] < 1
            || ! is_int($item['category_id']) || $item['category_id'] < 1
            || ! is_string($item['item_name']) || trim($item['item_name']) === ''
            || ! is_string($item['category']) || trim($item['category']) === ''
            || ! is_string($item['unit']) || trim($item['unit']) === ''
            || $item['model_version'] !== 'demand-linear-regression-v2'
            || ! in_array($item['confidence'], ['Low', 'Medium', 'High'], true)) {
            throw new \UnexpectedValueException('Live forecast contains invalid item identity or metadata.');
        }

        $identity = $item['inventory_id'].':'.$item['category_id'];
        if (isset($seen[$identity])) {
            throw new \UnexpectedValueException('Live forecast contains duplicate inventory/category identities.');
        }
        $seen[$identity] = true;

        $window = $item['history_window'];
        if (! is_array($window)
            || array_diff(['start_month', 'end_month', 'completeness'], array_keys($window)) !== []
            || ! $this->isValidMonth($window['start_month'] ?? null)
            || ! $this->isValidMonth($window['end_month'] ?? null)
            || $window['start_month'] > $window['end_month']
            || $this->monthCount($window['start_month'], $window['end_month']) > 120
            || ! in_array($window['completeness'], ['complete', 'incomplete'], true)
            || ! $this->isValidMonth($item['forecast_month'])
            || $item['forecast_month'] !== $this->nextMonth($window['end_month'])) {
            throw new \UnexpectedValueException('Live forecast contains an invalid history window.');
        }

        $windowMonths = $this->monthsBetween($window['start_month'], $window['end_month']);
        $usage = $item['monthly_usage'];
        $monthsUsed = $item['months_used'];
        $unknownMonths = $item['unknown_months'];
        if (! is_array($usage) || array_is_list($usage) || ! is_array($monthsUsed) || ! array_is_list($monthsUsed)
            || ! is_array($unknownMonths) || ! array_is_list($unknownMonths)
            || $monthsUsed !== array_keys($usage)
            || count(array_unique($monthsUsed)) !== count($monthsUsed)
            || count(array_unique($unknownMonths)) !== count($unknownMonths)
            || array_intersect($monthsUsed, $unknownMonths) !== []) {
            throw new \UnexpectedValueException('Live forecast contains invalid verified or unknown months.');
        }
        foreach ($usage as $month => $quantity) {
            if (! in_array($month, $windowMonths, true)
                || (! is_int($quantity) && ! is_float($quantity))
                || ! is_finite((float) $quantity) || $quantity < 0) {
                throw new \UnexpectedValueException('Live forecast contains invalid monthly usage.');
            }
        }
        foreach ($unknownMonths as $month) {
            if (! in_array($month, $windowMonths, true)) {
                throw new \UnexpectedValueException('Live forecast marks an out-of-window month unknown.');
            }
        }
        if (($window['completeness'] === 'complete' && ($unknownMonths !== [] || $monthsUsed !== $windowMonths))
            || ($window['completeness'] === 'incomplete' && array_diff($windowMonths, [...$monthsUsed, ...$unknownMonths]) !== [])) {
            throw new \UnexpectedValueException('Live forecast completeness does not match its history months.');
        }

        $historyCount = count($monthsUsed);
        $required = $item['required_months'];
        if (! is_int($required) || $required < 1 || $item['verified_months_used'] !== $historyCount
            || ($item['status'] === 'success'
                ? (! is_int($item['predicted_quantity'] ?? null) || $item['predicted_quantity'] < 0 || $historyCount < $required || ! $this->validModelValidation($item['validation'] ?? null))
                : ($item['status'] !== 'insufficient_history' || array_key_exists('predicted_quantity', $item) || array_key_exists('validation', $item) || $historyCount >= $required))) {
            throw new \UnexpectedValueException('Live forecast history threshold or prediction status is inconsistent.');
        }
    }

    private function validModelValidation(mixed $validation): bool
    {
        return is_array($validation)
            && in_array($validation['status'] ?? null, ['unavailable', 'time_ordered_holdout'], true)
            && is_int($validation['months_tested'] ?? null)
            && $validation['months_tested'] >= 0
            && (($validation['status'] === 'unavailable' && $validation['months_tested'] === 0 && ($validation['mae'] ?? null) === null)
                || ($validation['status'] === 'time_ordered_holdout'
                    && $validation['months_tested'] > 0
                    && (is_int($validation['mae'] ?? null) || is_float($validation['mae'] ?? null))
                    && is_finite((float) $validation['mae'])
                    && $validation['mae'] >= 0));
    }

    private function validatedForecastRow(array $item, Inventory $inventory, int $pendingDemand): array
    {
        $isSuccessful = $item['status'] === 'success';
        $forecastDemand = $isSuccessful ? $item['predicted_quantity'] : null;
        $availableStock = $inventory->status === 'available' ? max(0, (int) $inventory->quantity) : 0;
        $safetyStock = $isSuccessful ? (int) ceil($forecastDemand * (float) config('forecast.safety_stock_rate', 0.25)) : null;
        $suggested = $isSuccessful ? max(0, $forecastDemand + $safetyStock - $availableStock - $pendingDemand) : null;
        $priority = $isSuccessful ? $this->priority($availableStock, $pendingDemand, $forecastDemand, $suggested) : 'Normal';

        return [
            'inventory_id' => (int) $inventory->item_id,
            'item_name' => $inventory->item_name,
            'category_id' => (int) $inventory->category_id,
            'category' => $inventory->category?->category_name ?? 'Uncategorized',
            'unit' => $inventory->unit ?: 'units',
            'forecast_month' => $item['forecast_month'],
            'history_window' => $item['history_window'],
            'monthly_usage_values' => $item['monthly_usage'],
            'unknown_months' => $item['unknown_months'],
            'historical_months_used' => $item['verified_months_used'],
            'required_months' => $item['required_months'],
            'model_validation' => $item['validation'] ?? null,
            'forecast_demand' => $forecastDemand,
            'available_stock' => $availableStock,
            'pending_demand' => $pendingDemand,
            'pending_requests' => $pendingDemand,
            'safety_stock' => $safetyStock,
            'suggested_procurement' => $suggested,
            'needs_procurement' => $isSuccessful && $suggested > 0,
            'priority' => $priority,
            'priority_rank' => $this->priorityRank($priority),
            'confidence' => $item['confidence'],
            'calculation_basis' => $isSuccessful
                ? 'max(0, forecast demand + safety stock - available stock - pending demand)'
                : 'No procurement calculation: verified history is below the configured minimum.',
            'advisory_status' => ! $isSuccessful ? 'Insufficient history; no estimate' : ($suggested > 0 ? 'Review recommended' : 'No procurement gap identified'),
            'explanation' => ! $isSuccessful
                ? 'More verified completed demand history is required before an ML forecast can be used.'
                : ($suggested > 0 ? 'Forecast demand and safety stock exceed stock plus pending demand.' : 'Available stock and pending demand cover forecast demand and safety stock.'),
            'status' => $item['status'],
            'source_type' => 'live',
        ];
    }

    private function priority(int $availableStock, int $pendingDemand, int $forecastDemand, int $suggested): string
    {
        if ($suggested <= 0) {
            return 'Normal';
        }
        if ($availableStock <= 0 && $pendingDemand < $forecastDemand) {
            return 'Urgent';
        }
        if ($availableStock + $pendingDemand < $forecastDemand) {
            return 'High';
        }

        return 'Medium';
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'Urgent' => 4,
            'High' => 3,
            'Medium' => 2,
            default => 1,
        };
    }

    private function errorResult(): array
    {
        return $this->resultWithStatus('error');
    }

    private function resultWithStatus(string $status, ?string $generatedAt = null): array
    {
        return [
            'status' => $status,
            'rows' => [],
            'forecast_period' => null,
            'generated_at' => $generatedAt,
            'source_type' => 'live',
            'model_version' => null,
            'summary' => [
                'items_forecasted' => 0,
                'items_needing_procurement' => 0,
                'suggested_units_to_procure' => 0,
                'confidence' => 'Insufficient data',
            ],
        ];
    }

    private function summaryConfidence(array $confidences): string
    {
        if ($confidences === []) {
            return 'Insufficient data';
        }

        return in_array('Low', $confidences, true) ? 'Low' : (in_array('Medium', $confidences, true) ? 'Medium' : 'High');
    }
}
