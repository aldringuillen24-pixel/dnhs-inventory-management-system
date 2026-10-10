<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\ForecastPayload;
use App\Models\Inventory;
use App\Models\User;
use App\Support\RequestableItemMatcher;
use App\Support\SampleForecastData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoredDemandForecastService
{
    private const FORECAST_PATH = 'forecast/forecast.json';

    private const TRAINING_STATUS_PATH = 'forecast/training-status.json';

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

        try {
            $resolved = $this->resolveLiveDocument();

            if ($resolved === null) {
                return $this->resultWithStatus('missing');
            }

            // A run that is still in progress, or that failed, must not be
            // presented as a current result. This is deliberately not a
            // fallback to the previous good forecast: serving an old result as
            // if it were fresh would be worse than showing nothing.
            if ($resolved['training_status'] !== 'success') {
                return $this->resultWithStatus('failed');
            }

            $payload = json_decode($resolved['payload'], true, 512, JSON_THROW_ON_ERROR);
            $generatedAt = $this->validateLivePayload($payload);
        } catch (Throwable $exception) {
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
        // Sample forecast rows are hidden from every other part of the app, but
        // this is the one screen they exist for. Without this opt-out every row
        // would look like an orphaned item and the whole forecast would be
        // refused as empty -- the same failure mode as the deleted-item bug.
        $inventory = Inventory::query()
            ->withoutGlobalScope(SampleForecastData::SCOPE)
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

        $unmetDemand = $this->unmetDemandByType();

        // A forecast row whose inventory item has since been deleted, or moved
        // to a different category, no longer describes anything real: its
        // "buy N" figure was derived against that item and that category. So
        // the row is dropped rather than shown.
        //
        // It used to reject the whole document on the first miss, which meant
        // deleting one routine inventory item emptied the entire forecast page
        // for every user. Dropping only the orphaned rows keeps the remaining
        // predictions usable; a genuinely unreadable document is still refused
        // above, by validateLivePayload(), and a forecast that has lost every
        // row is still refused below.
        $rows = collect();
        $orphaned = [];
        $orderedForecastItems = collect($forecastItems)
            ->sortBy(fn (array $item): int => (int) $item['inventory_id'])
            ->values();

        foreach ($orderedForecastItems as $item) {
            $record = $inventory->get($item['inventory_id']);
            if (! $record || (int) $record->category_id !== $item['category_id']) {
                $orphaned[] = (int) $item['inventory_id'];

                continue;
            }

            // Unmet demand is keyed by the inventory id it resolved to, so this
            // lookup can only ever match one row. The previous group-key guard
            // had to walk rows in id order to avoid double-counting serialized
            // item types; resolving first makes that unnecessary.
            $unmet = $unmetDemand->get((int) $record->item_id);

            $rows->push($this->validatedForecastRow(
                $item,
                $record,
                (int) ($pendingDemand->get($record->item_id) ?? 0),
                $unmet,
            ));
        }

        if ($orphaned !== []) {
            Log::info('Stored demand forecast skipped rows with no matching inventory item.', [
                'orphaned_inventory_ids' => $orphaned,
                'kept_rows' => $rows->count(),
                'forecast_month' => $payload['forecasts'][0]['forecast_month'] ?? null,
            ]);
        }

        // Every row was orphaned, so there is nothing left to present. This is
        // distinct from a corrupt document: the file is fine, the inventory it
        // was trained against is simply gone.
        if ($rows->isEmpty()) {
            return $this->errorResult();
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

    /**
     * Locates the trained forecast document and its training state.
     *
     * The database is preferred because a Render Cron Job that trains runs in a
     * different container from the web service, and container filesystems are
     * ephemeral. The local file is still consulted as a fallback so existing
     * installs and the test suite keep working unchanged.
     *
     * @return array{payload: string, training_status: string, source: string}|null
     */
    private function resolveLiveDocument(): ?array
    {
        $record = ForecastPayload::query()->where('source_type', ForecastPayload::SOURCE_LIVE)->first();

        if ($record !== null) {
            // A row exists but may hold no document yet: a run that is in
            // progress or failed before writing output. The training status is
            // what matters there, and read() refuses to serve it.
            if (! is_string($record->payload) || trim($record->payload) === '') {
                return [
                    'payload' => '{}',
                    'training_status' => (string) $record->training_status,
                    'source' => 'database',
                ];
            }

            return [
                'payload' => (string) $record->payload,
                'training_status' => (string) $record->training_status,
                'source' => 'database',
            ];
        }

        $disk = Storage::disk('forecast');
        $status = 'success';

        if ($disk->exists(self::TRAINING_STATUS_PATH)) {
            $decoded = json_decode($disk->get(self::TRAINING_STATUS_PATH), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded) || ! in_array($decoded['status'] ?? null, ['running', 'success', 'failed'], true)) {
                throw new \UnexpectedValueException('Live forecast training state is invalid.');
            }

            $status = (string) $decoded['status'];
        }

        if (! $disk->exists(self::FORECAST_PATH)) {
            // No stored document at all. If a run is mid-flight or failed, that
            // is the state to report, not "missing".
            return $status === 'success' ? null : [
                'payload' => '{}',
                'training_status' => $status,
                'source' => 'filesystem',
            ];
        }

        return [
            'payload' => $disk->get(self::FORECAST_PATH),
            'training_status' => $status,
            'source' => 'filesystem',
        ];
    }

    /**
     * Stores a freshly trained forecast and its outcome so any instance can
     * read it back, including after a redeploy.
     */
    public function persistTraining(string $sourceType, string $status, ?string $payload = null): void
    {
        $attributes = [
            'training_status' => $status,
            'training_status_updated_at' => now(),
        ];

        if ($payload !== null) {
            $attributes['payload'] = $payload;
            // Generated-at is authoritative from the document itself, so it is
            // read the same way the read path validates it.
            $decoded = json_decode($payload, true);

            if (is_array($decoded) && is_string($decoded['generated_at'] ?? null)) {
                $attributes['generated_at'] = Carbon::parse($decoded['generated_at']);
            }
        }

        ForecastPayload::query()->updateOrCreate(
            ['source_type' => $sourceType],
            $attributes,
        );
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

    /**
     * Unmet demand resolved to the inventory row it should boost.
     *
     * These are end-user requests raised when stock was zero, so they carry
     * `item_id = NULL` and cannot be joined on that. Each request is instead
     * resolved through the shared RequestableItemMatcher to a concrete inventory
     * row, and the result is keyed by that row's id.
     *
     * Keying by the resolved inventory id — rather than by the requested
     * name/category/unit triple — is what keeps the attribution safe for
     * serialized categories, where several rows share one item type. Exactly one
     * row holds a given id, so the quantity cannot be counted twice.
     *
     * @return \Illuminate\Support\Collection<int, array{total_quantity:int,requester_count:int}>
     */
    private function unmetDemandByType(): Collection
    {
        return AssignmentRequest::query()
            ->where('status', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT)
            ->whereNotNull('requested_item_name')
            ->whereNotNull('requested_category_id')
            ->get(['requested_item_name', 'requested_category_id', 'requested_unit', 'quantity', 'user_id'])
            // Grouped on the NORMALISED name so "chair" and "Chairs" form one
            // demand group instead of two competing ones.
            ->groupBy(fn (AssignmentRequest $request): string => RequestableItemMatcher::normaliseName(
                (string) $request->requested_item_name
            ).'|'.(int) $request->requested_category_id.'|'.mb_strtolower(trim((string) $request->requested_unit)))
            ->map(function ($requests): ?array {
                $first = $requests->first();

                $inventoryId = RequestableItemMatcher::candidates(
                    (string) $first->requested_item_name,
                    (int) $first->requested_category_id,
                    $first->requested_unit,
                )->first()?->item_id;

                if (! $inventoryId) {
                    // No catalogue row matches, so there is no forecast row to
                    // add to. The demand is still real and still reaches the
                    // dedicated unmet-requests prompt; it simply cannot raise a
                    // suggestion for an item the model has never seen.
                    return null;
                }

                // requester_count is "how many people are affected", not a
                // purchase quantity. It is supplied so the AI can say so
                // accurately, and must never be added to a suggested figure.
                return [
                    'inventory_id' => (int) $inventoryId,
                    'total_quantity' => (int) $requests->sum('quantity'),
                    'requester_count' => $requests->pluck('user_id')->filter()->unique()->count(),
                ];
            })
            ->filter()
            // Several request groups can resolve to the same inventory row (a
            // generic placeholder unit matches any unit). Fold them, or a later
            // group would silently overwrite an earlier one.
            ->groupBy('inventory_id')
            ->map(fn ($entries): array => [
                'total_quantity' => (int) $entries->sum('total_quantity'),
                'requester_count' => (int) $entries->sum('requester_count'),
            ]);
    }

    /**
     * @param  array<string, int>|null  $unmet  Unmet quantity this specific row may
     *                                            claim, or null when another row in
     *                                            the same name+category+unit group
     *                                            already claimed it.
     */
    private function validatedForecastRow(array $item, Inventory $inventory, int $pendingDemand, ?array $unmet = null): array
    {
        $isSuccessful = $item['status'] === 'success';
        $forecastDemand = $isSuccessful ? $item['predicted_quantity'] : null;
        $availableStock = $inventory->status === 'available' ? max(0, (int) $inventory->quantity) : 0;
        $safetyStock = $isSuccessful ? (int) ceil($forecastDemand * (float) config('forecast.safety_stock_rate', 0.25)) : null;
        // Unmet demand is a POSITIVE term. It could not be folded into
        // pendingDemand, which subtracts: these are requests that could NOT be
        // satisfied, so they increase what should be bought rather than reduce
        // it. Raw quantity, deliberately uncapped — it is what staff asked for.
        $unmetDemand = $unmet['total_quantity'] ?? 0;
        $unmetRequesters = $unmet['requester_count'] ?? 0;
        $suggested = $isSuccessful
            ? max(0, $forecastDemand + $safetyStock - $availableStock - $pendingDemand + $unmetDemand)
            : null;
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
            'unmet_demand' => $unmetDemand,
            'unmet_requesters' => $unmetRequesters,
            'safety_stock' => $safetyStock,
            'suggested_procurement' => $suggested,
            'needs_procurement' => $isSuccessful && $suggested > 0,
            'priority' => $priority,
            'priority_rank' => $this->priorityRank($priority),
            'confidence' => $item['confidence'],
            // "pending demand" is kept in this string deliberately: tests assert on it.
            'calculation_basis' => $isSuccessful
                ? 'max(0, forecast demand + safety stock - available stock - pending demand + unmet demand)'
                : 'No procurement calculation: verified history is below the configured minimum.',
            'advisory_status' => ! $isSuccessful ? 'Insufficient history; no estimate' : ($suggested > 0 ? 'Review recommended' : 'No procurement gap identified'),
            'explanation' => ! $isSuccessful
                ? 'More verified completed demand history is required before an ML forecast can be used.'
                : ($suggested > 0
                    ? ($unmetDemand > 0
                        ? 'Forecast demand and safety stock exceed stock plus pending demand, and staff requested this item while it was unavailable.'
                        : 'Forecast demand and safety stock exceed stock plus pending demand.')
                    : 'Available stock and pending demand cover forecast demand and safety stock.'),
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
