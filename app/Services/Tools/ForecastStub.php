<?php

namespace App\Services\Tools;

use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Forecast through the chat path is a redirect, not an answer.
 *
 * Demand forecasting and AI Decision Support are answered by the Demand
 * Forecast page. This stub exists only so the capability resolves to something
 * that refuses to answer, keeping the capability declared for the policy while
 * returning no forecast data here. Do not extend it: real forecasting belongs
 * on the Forecast page, not in the assistant.
 */
class ForecastStub implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_DEMAND_FORECAST;
    }

    public function fetch(User $user, array $request): array
    {
        return $this->forecast($request);
    }

    protected function forecast(array $request): array
    {
        return $this->result('insufficient_data', 'forecast', AiCapabilityPolicy::VIEW_DEMAND_FORECAST, [
            'message' => 'No validated stored ML forecast is available through this question path.',
        ]);
    }
}