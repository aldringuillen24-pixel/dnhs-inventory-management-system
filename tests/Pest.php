<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function geminiGenerateContentResponse(string $text): array
{
    return [
        'candidates' => [[
            'content' => [
                'role' => 'model',
                'parts' => [['text' => $text]],
            ],
            'finishReason' => 'STOP',
        ]],
    ];
}

/**
 * Creates a small live forecast fixture stored through the production database
 * persistence path, with matching inventory records for read-time validation.
 *
 * @return array<string, mixed>
 */
function seedLiveForecastTestData(\App\Models\User $user): array
{
    $category = \App\Models\Category::firstOrCreate(
        ['category_name' => 'Forecast Test Supplies'],
        ['requires_serial_number' => false],
    );
    $end = \Illuminate\Support\Carbon::now()->startOfMonth()->subMonth();
    $start = $end->copy()->subMonths(3);
    $monthKeys = [];
    $usage = [];

    for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
        $monthKey = $month->format('Y-m');
        $monthKeys[] = $monthKey;
        $usage[$monthKey] = 10;
    }

    $forecastSpecs = [];
    foreach (range(1, 26) as $index) {
        $forecastSpecs[] = [sprintf('Gap Item %02d', $index), 0, 60, 'Low', 'success'];
    }
    $forecastSpecs[] = ['Covered Item', 100, 8, 'Low', 'success'];
    $forecastSpecs[] = ['Insufficient History Item', 0, null, 'Low', 'insufficient_history'];
    $forecasts = [];
    $insufficientHistory = [];

    foreach ($forecastSpecs as [$name, $quantity, $demand, $confidence, $status]) {
        $inventory = \App\Models\Inventory::create([
            'category_id' => $category->category_id,
            'user_id' => $user->id,
            'item_name' => $name,
            'unit' => 'pieces',
            'quantity' => $quantity,
            'unit_cost' => 10,
            'date_acquired' => $start->toDateString(),
            'status' => 'available',
        ]);

        $rowUsage = $usage;
        $rowMonths = $monthKeys;
        $windowStart = $start->format('Y-m');
        $windowEnd = $end->format('Y-m');

        if ($status === 'insufficient_history') {
            $rowMonths = array_slice($monthKeys, 0, 2);
            $rowUsage = array_intersect_key($usage, array_flip($rowMonths));
            $windowEnd = $rowMonths[1];
        }

        $row = [
            'inventory_id' => (int) $inventory->item_id,
            'item_name' => $name,
            'category_id' => (int) $category->category_id,
            'category' => $category->category_name,
            'unit' => 'pieces',
            'forecast_month' => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $windowEnd)->addMonth()->format('Y-m'),
            'history_window' => [
                'start_month' => $windowStart,
                'end_month' => $windowEnd,
                'completeness' => 'complete',
            ],
            'monthly_usage' => $rowUsage,
            'unknown_months' => [],
            'months_used' => $rowMonths,
            'verified_months_used' => count($rowMonths),
            'required_months' => 3,
            'model_version' => 'demand-linear-regression-v2',
            'confidence' => $confidence,
            'status' => $status,
        ];

        if ($status === 'success') {
            $row['predicted_quantity'] = $demand;
            $row['validation'] = ['status' => 'time_ordered_holdout', 'months_tested' => 1, 'mae' => 1.0];
            $forecasts[] = $row;
        } else {
            $insufficientHistory[] = $row;
        }
    }

    $payload = [
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => now()->toIso8601String(),
        'forecasts' => $forecasts,
        'insufficient_history' => $insufficientHistory,
    ];
    app(\App\Services\Forecasting\StoredDemandForecastService::class)->persistTraining(
        \App\Models\ForecastPayload::SOURCE_LIVE,
        'success',
        json_encode($payload, JSON_THROW_ON_ERROR),
    );

    return $payload;
}

/**
 * Whether a live-provider test must be skipped, and why.
 *
 * Tests that reach Gemini for real spend the project's request quota, which is
 * a shared, finite resource. A full suite run must therefore never call the
 * provider by accident: `.env` supplies a real GEMINI_API_KEY, so key
 * presence alone is not a safe signal that someone meant to run these.
 *
 * Return null to proceed, or a reason string to skip with.
 */
function liveGeminiSkipReason(): ?string
{
    $flag = getenv('GEMINI_LIVE_CHECK');
    if ($flag === false || $flag === '') {
        $flag = $_ENV['GEMINI_LIVE_CHECK'] ?? $_SERVER['GEMINI_LIVE_CHECK'] ?? '';
    }

    if ((string) $flag !== '1') {
        return 'Live Gemini checks are opt-in. Re-run with GEMINI_LIVE_CHECK=1 to spend provider quota.';
    }

    if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
        return 'No Gemini key configured.';
    }

    return null;
}
