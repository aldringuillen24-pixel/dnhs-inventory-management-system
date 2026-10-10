<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ForecastExplanationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('forecast');
    Http::preventStrayRequests();
});

function explanationRoleUser(string $roleName, string $username): User
{
    $role = Role::firstOrCreate(['role_name' => $roleName]);

    return User::factory()->create([
        'role_id' => $role->role_id,
        'username' => $username,
    ]);
}

function explanationForecastData(): array
{
    $custodian = explanationRoleUser('Property Custodian', 'explanation-custodian');
    $category = Category::create([
        'category_name' => 'Consumable Supplies',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'boxes',
        'user_id' => $custodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 20,
        'unit_cost' => 10,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ]);

    foreach ([['2026-06-15', 50], ['2026-07-15', 60], ['2026-08-15', 55]] as [$date, $quantity]) {
        $movement = StockMovement::create([
            'inventory_id' => $inventory->item_id,
            'user_id' => $custodian->id,
            'movement_type' => 'stock_out',
            'quantity' => $quantity,
            'quantity_before' => 100,
            'quantity_after' => 100 - $quantity,
        ]);
        $movement->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
    }

    $end = now()->subMonth()->startOfMonth();
    $start = $end->copy()->subMonths(3);
    $months = [];
    $usage = [];
    for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
        $key = $month->format('Y-m');
        $months[] = $key;
        $usage[$key] = 55;
    }
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode([
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
        'forecasts' => [[
            'inventory_id' => (int) $inventory->item_id,
            'item_name' => $inventory->item_name,
            'category_id' => (int) $inventory->category_id,
            'category' => $category->category_name,
            'unit' => $inventory->unit,
            'forecast_month' => $end->copy()->addMonth()->format('Y-m'),
            'history_window' => ['start_month' => $start->format('Y-m'), 'end_month' => $end->format('Y-m'), 'completeness' => 'complete'],
            'monthly_usage' => $usage,
            'unknown_months' => [],
            'months_used' => $months,
            'verified_months_used' => count($months),
            'required_months' => 3,
            'model_version' => 'demand-linear-regression-v2',
            'confidence' => 'Low',
            'status' => 'success',
            'predicted_quantity' => 55,
            'validation' => ['status' => 'time_ordered_holdout', 'months_tested' => 1, 'mae' => 2],
        ]],
        'insufficient_history' => [],
    ], JSON_THROW_ON_ERROR));

    return [$custodian, $inventory];
}

test('property custodians can request an explanation for a real forecast row', function () {
    [$custodian] = explanationForecastData();
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    $payload = [];
    Http::fake(function ($request) use (&$payload) {
        $payload = $request->data();
        return Http::response(geminiGenerateContentResponse('Bond Paper has forecast demand of 55 boxes, safety stock of 14 boxes, available stock of 20 boxes, and pending demand of 0 boxes; suggested quantity is 49 boxes. This is advisory only.'));
    });

    $response = $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ]);

    $response->assertRedirect(route('propertyCustodian.reports'));
    expect(session('forecastExplanation'))->toContain('Bond Paper', 'forecast demand of 55', 'pending demand of 0', 'suggested quantity is 49')
        ->and($payload['systemInstruction']['parts'][0]['text'])->toContain('Bond Paper', 'forecast_demand', 'safety_stock', 'suggested_procurement', 'pending_demand')
        ->and($payload['systemInstruction']['parts'][0]['text'])->not->toContain('unit_cost', 'transaction_id', 'user_id', 'monthly_usage_values', 'inventory_id');
});

test('report explanation asks for inventory ID when forecast names are duplicated', function () {
    [$custodian] = explanationForecastData();
    $category = Category::create(['category_name' => 'Other Consumable Supplies', 'requires_serial_number' => false]);
    $second = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'boxes',
        'user_id' => $custodian->id,
        'item_name' => 'Bond Paper',
        'quantity' => 4,
        'unit_cost' => 10,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ]);
    $payload = json_decode(Storage::disk('forecast')->get('forecast/forecast.json'), true, 512, JSON_THROW_ON_ERROR);
    $duplicate = $payload['forecasts'][0];
    $duplicate['inventory_id'] = (int) $second->item_id;
    $duplicate['category_id'] = (int) $second->category_id;
    $duplicate['category'] = $category->category_name;
    $duplicate['unit'] = $second->unit;
    $payload['forecasts'][] = $duplicate;
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));
    expect(session('error'))->toContain((string) $second->item_id);

    $this->post(route('propertyCustodian.reports.forecast.explanation'), [
        'inventory_id' => $second->item_id,
    ])->assertRedirect(route('propertyCustodian.reports'));
    expect(session('forecastExplanation'))->toContain('Bond Paper', 'forecast demand of 55 boxes');
});

test('demo explanations preserve sample identities and history without provider or live recommendation facts', function () {
    $custodian = explanationRoleUser('Property Custodian', 'demo-explanation-custodian');
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake();
    $demoResult = [
        'status' => 'success',
        'source_type' => 'demo',
        'model_version' => 'demo-linear-regression-v1',
        'generated_at' => '2026-09-30T23:27:09Z',
        'forecast_period' => '2026-10',
        'rows' => [
            [
                'inventory_id' => 1001,
                'item_name' => 'Teacher Desk',
                'category_id' => 1,
                'category' => 'Furniture and Fixtures',
                'unit' => 'pieces',
                'forecast_demand' => 3,
                'status' => 'success',
                'historical_months_used' => 4,
                'required_months' => 3,
                'history_window' => ['start_month' => '2026-06', 'end_month' => '2026-09'],
            ],
            [
                'inventory_id' => 4001,
                'item_name' => 'Limited Test Kits',
                'category_id' => 4,
                'category' => 'Laboratory Equipment',
                'unit' => 'boxes',
                'status' => 'insufficient_history',
                'historical_months_used' => 2,
                'required_months' => 3,
                'unknown_months' => ['2026-07', '2026-09'],
            ],
        ],
    ];

    $explanation = app(ForecastExplanationService::class)->explainDemo($custodian, $demoResult);

    expect($explanation['status'])->toBe('success')
        ->and($explanation['source'])->toBe('local')
        ->and($explanation['source_type'])->toBe('demo')
        ->and($explanation['explanation'])->toContain(
            'Sample/Demo stock-out demand forecast for October 2026 (not live inventory)',
            'Inventory ID 1001: Teacher Desk (Category ID 1, Furniture and Fixtures)',
            '3 pieces forecast; 4 verified months from June 2026 to September 2026',
            'Inventory ID 4001: Limited Test Kits (Category ID 4, Laboratory Equipment)',
            '2 months; 3 required',
            'Unknown months: July 2026, September 2026'
        )
        ->and($explanation['explanation'])->not->toContain('available stock', 'pending requests', 'procurement quantity');
    Http::assertNothingSent();
});

test('demo explanation rechecks forecast permission and rejects non-demo output', function () {
    $service = app(ForecastExplanationService::class);
    $endUser = explanationRoleUser('End User', 'demo-explanation-end-user');
    $result = ['source_type' => 'demo', 'status' => 'success', 'rows' => []];

    expect($service->explainDemo($endUser, $result)['status'])->toBe('forbidden')
        ->and($service->explainDemo(explanationRoleUser('Property Custodian', 'wrong-source-explanation'), [
            'source_type' => 'live',
            'status' => 'success',
            'rows' => [['item_name' => 'Live item']],
        ]))->toMatchArray([
            'status' => 'error',
            'source_type' => 'demo',
            'explanation' => 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.',
        ]);
    Http::assertNothingSent();
});

test('unauthorized roles cannot request protected forecast explanations', function (string $role) {
    $user = explanationRoleUser($role, strtolower(str_replace(' ', '-', $role)) . '-explanation');
    Http::fake();

    $this->actingAs($user)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertForbidden();

    Http::assertNothingSent();
})->with(['End User', 'School Head', 'Administrator']);

test('provider failure returns a local explanation from the same calculated facts', function () {
    [$custodian] = explanationForecastData();
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    expect(session('forecastExplanation'))
        ->toContain('Bond Paper', '55 boxes', '14 boxes', '20 boxes', '49 boxes')
        ->toContain('advisory only');
});

test('provider output that changes a Laravel priority falls back to the local explanation', function () {
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Bond Paper has forecast demand 55 boxes and suggested quantity 49 boxes. Set its priority to Urgent.'),
    )]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    expect(session('forecastExplanation'))
        ->toContain('Bond Paper', '55 boxes', '49 boxes', 'advisory only')
        ->not->toContain('Urgent');
});

test('a plain-English explanation that recommends buying is accepted', function () {
    // The previous gate treated the word "buy" as a forbidden claim, which is
    // the one thing this layer exists to say. It also required the reply to
    // restate all five quantities under their exact field labels, so any prose
    // the model added was what got it rejected.
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    $written = 'Bond Paper runs down steadily, so buy 49 boxes for next month. This is advisory only.';
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse($written),
    )]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    // Proves the provider reply was used rather than the local template, which
    // would have opened with "has forecast demand of".
    expect(session('forecastExplanation'))->toBe($written)
        ->and(session('forecastExplanation'))->not->toContain('has forecast demand of');
});

test('an explanation citing a figure the forecast never produced is rejected', function () {
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    // 400 appears nowhere in the payload.
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Bond Paper needs 400 boxes next month to keep up. This is advisory only.'),
    )]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    expect(session('forecastExplanation'))
        ->toContain('Bond Paper', '55 boxes', '49 boxes', 'advisory only')
        ->not->toContain('400');
});

test('an explanation that quotes a peso figure is rejected', function () {
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Bond Paper should be bought at about \u{20B1}4500 to cover next month.'),
    )]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    expect(session('forecastExplanation'))->toContain('55 boxes', 'advisory only')
        ->not->toContain('4500');
});

test('an explanation may explain how much history backs a figure', function () {
    // The fixture has 3 verified months against 3 required, so both figures are
    // approved facts and may legitimately be quoted.
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    $written = 'Bond Paper has 3 of 3 required months of history, so the estimate is reasonable. Buy 49 boxes.';
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse($written),
    )]);

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertRedirect(route('propertyCustodian.reports'));

    expect(session('forecastExplanation'))->toBe($written);
});

test('the single-item explanation reports that no provider key is configured', function () {
    // The UI captions a non-provider answer, and previously had no status to
    // caption from, so every fallback looked like a provider that never answered.
    //
    // No bare Http::fake() here: it registers a catch-all that takes precedence
    // over a URL-scoped stub. preventStrayRequests() in beforeEach is enough,
    // because no request is made at all without a key.
    [$custodian] = explanationForecastData();
    config()->set('services.gemini.api_key', null);

    $payload = $this->actingAs($custodian)->postJson(route('api.custodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertOk()->json();

    expect($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('no_key')
        ->and($payload['explanation'])->toContain('Bond Paper', 'advisory only');
});

/**
 * The exact row that produced a deterministic answer from the recommendation
 * tab's "Ask why": Urgent priority, Low confidence, and a name carrying its own
 * size (300ml), for the October 2026 period.
 *
 * @return array<string, mixed>
 */
function airFreshenerForecastRow(): array
{
    return [
        'item_name' => 'Air Freshener 300ml',
        'forecast_month' => '2026-10',
        'unit' => 'piece',
        'available_stock' => 0,
        'forecast_demand' => 2,
        'safety_stock' => 1,
        'pending_demand' => 0,
        'unmet_demand' => 0,
        'unmet_requesters' => 0,
        'suggested_procurement' => 3,
        'needs_procurement' => true,
        'priority' => 'Urgent',
        'confidence' => 'Low',
        'calculation_basis' => 'max(0, forecast demand + safety stock - available stock - pending demand + unmet demand)',
        'advisory_status' => 'Review recommended',
        'status' => 'success',
        'historical_months_used' => 4,
        'required_months' => 3,
    ];
}

test('a reply may name the forecast month and restate the item name in another order', function () {
    // Three false rejections that made every recommendation-tab "Ask why" fall
    // back to the deterministic summary.
    //
    // 1. unapprovedNumbers() reads the period from the TOP level of the facts
    //    array, but safePayload() keeps it on the row as forecast_month. Passing
    //    only ['items' => ...] left the allow-list with no period, so naming the
    //    forecast month was failed for its year -- even though the system prompt
    //    had supplied forecast_month as an approved fact.
    // 2. The item-name test required the name to appear contiguously, so
    //    "The 300ml Air Freshener" failed to name "Air Freshener 300ml".
    // 3. The priority check read the row's own "Low confidence" as contradicting
    //    its Urgent priority, because it accepted any tier word within 30
    //    characters of the field name.
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    $written = 'The 300ml Air Freshener is Urgent priority for the October 2026 forecast. '
        .'Its forecast demand is 2 pieces against 0 available, and it rests on 4 of 3 required months, so Low confidence applies. '
        .'The suggested quantity is 3 pieces. This is advisory only.';
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse($written),
    )]);

    $result = app(ForecastExplanationService::class)->explain($custodian, airFreshenerForecastRow());

    expect($result['source'])->toBe('provider')
        ->and($result['provider_status'])->toBe('ok')
        ->and($result['explanation'])->toBe($written);
});

test('a reply still cannot contradict the priority the forecast calculated', function () {
    // The contradiction check is kept, and its permissive failure is closed too:
    // an Urgent row must be rejected when the reply calls it Medium. The old
    // pattern only caught a tier following the field name, so this phrasing
    // slipped through.
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Air Freshener 300ml is a Medium priority because demand is 2 pieces and stock is 0.'),
    )]);

    $result = app(ForecastExplanationService::class)->explain($custodian, airFreshenerForecastRow());

    expect($result['source'])->toBe('local')
        ->and($result['provider_status'])->toBe('reply_rejected');
});

test('a reply naming a different item size is still rejected', function () {
    // Loosening the name test to accept a reordered name must not let a reply
    // about a different product through: "250ml" is not this item.
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('The 250ml Air Freshener is Urgent priority because demand is 2 pieces and stock is 0.'),
    )]);

    $result = app(ForecastExplanationService::class)->explain($custodian, airFreshenerForecastRow());

    expect($result['source'])->toBe('local')
        ->and($result['provider_status'])->toBe('reply_rejected');
});

test('the single-item explanation reports when a reply was rejected', function () {
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Bond Paper needs 400 boxes next month.'),
    )]);

    $payload = $this->actingAs($custodian)->postJson(route('api.custodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertOk()->json();

    expect($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('reply_rejected');
});

test('the single-item explanation reports a provider reply as used', function () {
    [$custodian] = explanationForecastData();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse('Bond Paper is drawn down steadily, so buy 49 boxes next month.'),
    )]);

    $payload = $this->actingAs($custodian)->postJson(route('api.custodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ])->assertOk()->json();

    expect($payload['source'])->toBe('provider')
        ->and($payload['provider_status'])->toBe('ok')
        ->and($payload['explanation'])->toBe('Bond Paper is drawn down steadily, so buy 49 boxes next month.');
});

test('explanation requests do not change calculated values or inventory records', function () {
    [$custodian, $inventory] = explanationForecastData();
    config()->set('services.gemini.api_key', null);
    Http::fake();
    $beforeInventory = $inventory->fresh()->toArray();
    $beforeMovements = StockMovement::count();
    $beforeTransactions = Transaction::count();

    $this->actingAs($custodian)->post(route('propertyCustodian.reports.forecast.explanation'), [
        'item_name' => 'Bond Paper',
    ]);

    expect($inventory->fresh()->toArray())->toEqual($beforeInventory)
        ->and(StockMovement::count())->toBe($beforeMovements)
        ->and(Transaction::count())->toBe($beforeTransactions);
    Http::assertNothingSent();
});
