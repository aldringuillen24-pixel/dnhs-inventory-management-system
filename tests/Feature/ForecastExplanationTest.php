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
