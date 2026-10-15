<?php

namespace App\Services;

use App\Models\User;
use App\Services\Forecasting\StoredDemandForecastService;

class DemandForecastService
{
    public function __construct(protected StoredDemandForecastService $storedForecastService)
    {
    }

    public function forecast(User $user): array
    {
        return $this->storedForecastService->read($user);
    }
}
