<?php

use App\Models\Category;
use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function storedDemoForecastPayload(): array
{
    $base = [
        'category' => 'Furniture and Fixtures',
        'unit' => 'pieces',
        'forecast_month' => '2026-10',
        'history_window' => [
            'start_month' => '2026-06',
            'end_month' => '2026-09',
            'completeness' => 'complete',
        ],
        'monthly_usage' => ['2026-06' => 2, '2026-07' => 3, '2026-08' => 0, '2026-09' => 4],
        'unknown_months' => [],
        'months_used' => ['2026-06', '2026-07', '2026-08', '2026-09'],
        'verified_months_used' => 4,
        'required_months' => 3,
        'model_version' => 'demo-linear-regression-v1',
        'confidence' => 'Low',
        'status' => 'success',
        'predicted_quantity' => 3,
    ];

    $firstDesk = [
        ...$base,
        'inventory_id' => 1001,
        'item_name' => 'Teacher Desk',
        'category_id' => 1,
    ];
    $secondDesk = [
        ...$base,
        'inventory_id' => 2001,
        'category' => 'Other Equipment',
        'item_name' => 'Teacher Desk',
        'category_id' => 8,
        'predicted_quantity' => 5,
    ];
    $incomplete = [
        ...$base,
        'inventory_id' => 4001,
        'item_name' => 'Limited Test Kits',
        'category_id' => 4,
        'category' => 'Laboratory Equipment',
        'history_window' => [
            'start_month' => '2026-06',
            'end_month' => '2026-09',
            'completeness' => 'incomplete',
        ],
        'monthly_usage' => ['2026-06' => 3, '2026-08' => 4],
        'unknown_months' => ['2026-07', '2026-09'],
        'months_used' => ['2026-06', '2026-08'],
        'verified_months_used' => 2,
        'status' => 'insufficient_history',
    ];
    unset($incomplete['predicted_quantity']);

    return [
        'schema_version' => 1,
        'source_type' => 'demo',
        'model_version' => 'demo-linear-regression-v1',
        'generated_at' => '2026-09-30T23:27:09Z',
        'forecasts' => [$firstDesk, $secondDesk],
        'insufficient_history' => [$incomplete],
    ];
}

function putStoredDemoForecast(array $payload): void
{
    Storage::disk('forecast')->put('forecast/demo/forecast.json', json_encode($payload, JSON_THROW_ON_ERROR));
}

function liveForecastRow(Inventory $inventory, int $demand): array
{
    $end = now()->subMonth()->startOfMonth();
    $start = $end->copy()->subMonths(3);
    $months = [];
    $usage = [];
    for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
        $key = $month->format('Y-m');
        $months[] = $key;
        $usage[$key] = 4;
    }

    return [
        'inventory_id' => (int) $inventory->item_id,
        'item_name' => $inventory->item_name,
        'category_id' => (int) $inventory->category_id,
        'category' => $inventory->category?->category_name ?? 'Uncategorized',
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
        'predicted_quantity' => $demand,
        'validation' => ['status' => 'time_ordered_holdout', 'months_tested' => 1, 'mae' => 1],
    ];
}

function putStoredLiveForecast(array $forecasts, array $insufficientHistory = []): void
{
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode([
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
        'forecasts' => $forecasts,
        'insufficient_history' => $insufficientHistory,
    ], JSON_THROW_ON_ERROR));
}

test('the demand forecast page data reads the generated forecast JSON', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $user->id,
        'item_name' => 'Bond Paper',
        'unit' => 'reams',
        'quantity' => 12,
        'unit_cost' => 10,
        'date_acquired' => '2026-01-01',
        'status' => 'available',
    ]);

    putStoredLiveForecast([liveForecastRow($inventory, 20)]);

    $result = app(StoredDemandForecastService::class)->read($user);

    expect($result['status'])->toBe('success')
        ->and($result['rows'][0]['forecast_demand'])->toBe(20)
        ->and($result['rows'][0]['available_stock'])->toBe(12)
        ->and($result['rows'][0]['unit'])->toBe('reams')
        ->and($result['rows'][0]['suggested_procurement'])->toBe(13)
        ->and($result['rows'][0]['calculation_basis'])->toContain('pending demand');

    $this->actingAs($user)
        ->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->assertJsonPath('title', 'Property Custodian Reports')
        ->assertJsonPath('liveForecastResult.rows.0.forecast_demand', 20)
        ->assertJsonPath('liveForecastResult.rows.0.available_stock', 12)
        ->assertJsonPath('liveForecastResult.rows.0.suggested_procurement', 13);
});

test('live reader rejects stale output and duplicate stable identities', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id, 'user_id' => $user->id, 'item_name' => 'Stale Paper',
        'unit' => 'reams', 'quantity' => 4, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    $row = liveForecastRow($inventory, 8);
    putStoredLiveForecast([$row]);
    $payload = json_decode(Storage::disk('forecast')->get('forecast/forecast.json'), true, 512, JSON_THROW_ON_ERROR);
    $payload['generated_at'] = now()->subDays(9)->utc()->format('Y-m-d\\TH:i:s\\Z');
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $service = app(StoredDemandForecastService::class);
    expect($service->read($user)['status'])->toBe('stale')
        ->and($service->read($user)['rows'])->toBeEmpty();

    putStoredLiveForecast([$row, $row]);
    expect($service->read($user)['status'])->toBe('error')
        ->and($service->read($user)['rows'])->toBeEmpty();
});

test('chat does not substitute pending requests or a PHP estimate when live output is missing', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id, 'user_id' => $user->id, 'item_name' => 'Untrained Paper',
        'unit' => 'reams', 'quantity' => 1, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    AssignmentRequest::create([
        'item_id' => $inventory->item_id, 'user_id' => $user->id, 'target_user_id' => $user->id,
        'quantity' => 9, 'status' => 'waiting for approval', 'requested_at' => now(),
    ]);
    StockMovement::create([
        'inventory_id' => $inventory->item_id, 'user_id' => $user->id, 'movement_type' => 'stock_out',
        'quantity' => 50, 'quantity_before' => 51, 'quantity_after' => 1,
    ]);
    $inventoryBefore = $inventory->fresh()->toArray();
    $movementCount = StockMovement::count();

    $response = $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'Show demand forecast for Untrained Paper'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('reply'))
        ->toBe('No stored ML forecast is available yet. Forecast training runs separately from chat.')
        ->not->toContain('9', '50', 'Untrained Paper');
    expect($inventory->fresh()->toArray())->toEqual($inventoryBefore)
        ->and(StockMovement::count())->toBe($movementCount);
});

test('live chat ranks Laravel recommendations and reports use the identical rows', function () {
    Storage::fake('forecast');
    Http::preventStrayRequests();
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $urgent = Inventory::create([
        'category_id' => $category->category_id, 'user_id' => $user->id, 'item_name' => 'Urgent Notebook',
        'unit' => 'boxes', 'quantity' => 0, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    $covered = Inventory::create([
        'category_id' => $category->category_id, 'user_id' => $user->id, 'item_name' => 'Reserve Binder',
        'unit' => 'boxes', 'quantity' => 100, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    AssignmentRequest::create([
        'item_id' => $urgent->item_id, 'user_id' => $user->id, 'target_user_id' => $user->id,
        'quantity' => 4, 'status' => 'waiting for approval', 'requested_at' => now(),
    ]);
    putStoredLiveForecast([
        liveForecastRow($urgent, 20),
        liveForecastRow($covered, 10),
    ]);
    $inventoryBefore = Inventory::query()->orderBy('item_id')->get()->toArray();
    $movementCount = StockMovement::count();
    $rowCount = count($forecast['forecasts'] ?? []) + count($forecast['insufficient_history'] ?? []);

    $chat = $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'What are more urgent?'])
        ->assertOk()
        ->assertJsonPath('success', true);
    // The chat renders a capped, decision-focused table. Available stock,
    // pending demand, confidence and advisory status moved to the Demand
    // Forecast page and AI Decision Support, so they are no longer asserted here.
    expect($chat->json('reply'))
        ->toContain('Advisory forecast priorities', 'Predicted demand', 'Safety stock', 'Suggested quantity', 'Urgent Notebook', '20 boxes', '5 boxes', '21 boxes', 'Urgent')
        ->toContain('Reserve Binder');

    // A long list must not flood the chat panel: only a capped summary is
    // rendered, and the complete list is pointed at the Demand Forecast page.
    if ($rowCount > 10) {
        $reply = (string) $chat->json('reply');

        expect($reply)->toContain('Showing 10 of '.$rowCount.' items')
            ->and($reply)->toContain('Demand Forecast')
            ->and(substr_count($reply, "\n| "))->toBe(10)
            ->and(strlen($reply))->toBeLessThan(4000);
    }

    $this->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->assertJsonPath('liveForecastResult.source_type', 'live')
        ->assertJsonFragment([
            'item_name' => 'Urgent Notebook',
            'forecast_demand' => 20,
            'suggested_procurement' => 21,
        ]);

    $this->getJson(route('api.custodian.reports.forecast.recommendations'))
        ->assertOk()
        ->assertJsonFragment(['item_name' => 'Urgent Notebook'])
        ->assertJsonFragment(['suggested_procurement' => 21]);

    $this->get('/spa/reports/forecast')->assertOk();

    expect(Inventory::query()->orderBy('item_id')->get()->toArray())->toEqual($inventoryBefore)
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(session('ai.demand_forecast_context.user.'.$user->id.'.context.source_type'))->toBe('live');
    Http::assertNothingSent();

    $this->postJson(route('ai.chat'), ['message' => 'why?'])
        ->assertOk()
        ->assertJsonPath('success', true);
    expect(session('ai.demand_forecast_context.user.'.$user->id.'.context.inventory_ids'))->toBe([$urgent->item_id]);

    $this->postJson(route('ai.chat.reset'))->assertOk();
    expect(session('ai.demand_forecast_context.user.'.$user->id))->toBeNull();
});

test('forecast chat asks for exact identity and period instead of guessing', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $firstCategory = Category::create(['category_name' => 'Furniture', 'requires_serial_number' => false]);
    $secondCategory = Category::create(['category_name' => 'Other Equipment', 'requires_serial_number' => false]);
    $first = Inventory::create([
        'category_id' => $firstCategory->category_id, 'user_id' => $user->id, 'item_name' => 'Teacher Desk',
        'unit' => 'pieces', 'quantity' => 0, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    $second = Inventory::create([
        'category_id' => $secondCategory->category_id, 'user_id' => $user->id, 'item_name' => 'Teacher Desk',
        'unit' => 'pieces', 'quantity' => 0, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    putStoredLiveForecast([liveForecastRow($first, 3), liveForecastRow($second, 5)]);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Explain demand forecast for Teacher Desk'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple inventory records') && str_contains($reply, 'ID '.$first->item_id) && str_contains($reply, 'ID '.$second->item_id));

    $this->postJson(route('ai.chat'), ['message' => 'Inventory ID '.$second->item_id])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Teacher Desk') && str_contains($reply, '5 pieces'));

    $period = now()->subMonth()->startOfMonth()->addMonth()->format('F Y');
    $otherPeriod = now()->subMonths(2)->startOfMonth()->format('F Y');
    $this->postJson(route('ai.chat'), ['message' => 'Show demand forecast for '.$otherPeriod])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'cannot estimate a different period') && str_contains($reply, $period));
});

test('forecast conversation context expires and clears on unrelated questions', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id, 'user_id' => $user->id, 'item_name' => 'Context Paper',
        'unit' => 'reams', 'quantity' => 10, 'unit_cost' => 10, 'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    putStoredLiveForecast([liveForecastRow($inventory, 8)]);
    $contextKey = 'ai.demand_forecast_context.user.'.$user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show demand forecast'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'full forecast list') && ! str_contains($reply, '| Item |'));
    expect(session($contextKey.'.context.source_type'))->toBe('live');

    $this->postJson(route('ai.chat'), ['message' => 'Give me all the list of forecast item'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Validated ML demand forecast') && str_contains($reply, 'Context Paper'));

    session()->put($contextKey, [
        'user_id' => $user->id,
        'expires_at' => now()->subMinute()->timestamp,
        'context' => ['source_type' => 'live', 'inventory_ids' => [$inventory->item_id], 'forecast_period' => now()->format('F Y')],
    ]);
    $this->postJson(route('ai.chat'), ['message' => 'why?'])->assertOk();
    expect(session($contextKey))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Show demand forecast'])->assertOk();
    expect(session($contextKey.'.context.source_type'))->toBe('live');
    $this->postJson(route('ai.chat'), ['message' => 'How many items are available?'])->assertOk();
    expect(session($contextKey))->toBeNull();
});

test('demo mode routes general forecast questions to sample output and unauthorized roles cannot read live results', function () {
    Storage::fake('forecast');
    config(['forecast.demo_mode' => true]);
    putStoredDemoForecast(storedDemoForecastPayload());
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::factory()->create(['role_id' => $custodianRole->role_id]);

    $this->actingAs($custodian)->postJson(route('ai.chat'), ['message' => 'Explain demand forecast'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'full Sample/Demo list') && ! str_contains($reply, 'Inventory ID 1001'));

    $this->postJson(route('ai.chat'), ['message' => 'Give me all the list of forecast item'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Sample/Demo stock-out demand forecast') && str_contains($reply, 'Inventory ID 1001') && str_contains($reply, 'Inventory ID 2001'));

    $endUserRole = Role::firstOrCreate(['role_name' => 'End User']);
    $endUser = User::factory()->create(['role_id' => $endUserRole->role_id]);
    expect(app(StoredDemandForecastService::class)->read($endUser)['status'])->toBe('forbidden');
    $this->actingAs($endUser)->postJson(route('ai.chat'), ['message' => 'Show demand forecast'])->assertForbidden();
});

test('demo forecast follow-ups select one item and refuse unsupported urgency or procurement claims', function () {
    Storage::fake('forecast');
    putStoredDemoForecast(storedDemoForecastPayload());
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Sample demo forecast'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'full Sample/Demo list'));

    $this->postJson(route('ai.chat'), ['message' => 'Show the November 2026 forecast'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'covers 2026-10 only') && ! str_contains($reply, 'Inventory ID 1001:'));

    $this->postJson(route('ai.chat'), ['message' => 'What are more urgent?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'cannot rank procurement urgency') && ! str_contains($reply, 'Inventory ID 1001:'));

    $this->postJson(route('ai.chat'), ['message' => 'Why do we need to procure this?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Which Sample/Demo inventory ID') && str_contains($reply, 'cannot establish a procurement need'));

    $this->postJson(route('ai.chat'), ['message' => 'Inventory ID 1001'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Inventory ID 1001: Teacher Desk') && ! str_contains($reply, 'Inventory ID 2001:'));

    $this->postJson(route('ai.chat'), ['message' => 'Why do we need to procure this?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Teacher Desk') && str_contains($reply, 'cannot establish whether procurement is needed') && ! str_contains($reply, 'Inventory ID 2001:'));
});

test('demo result validates stable identities and never joins or reads live inventory', function () {
    Storage::fake('forecast');
    $payload = storedDemoForecastPayload();
    putStoredDemoForecast($payload);
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode([
        'forecasts' => [['item_name' => 'Live only item', 'predicted_quantity' => 999]],
        'insufficient_history' => [],
    ]));
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (str_contains(strtolower($query->sql), 'inventory')) {
            $inventoryQueries++;
        }
    });

    $result = app(StoredDemandForecastService::class)->readDemo($user);
    $inventoryQueriesAfterRead = $inventoryQueries;
    $inventoryCount = DB::table('inventory')->count();

    expect($result['source_type'])->toBe('demo')
        ->and($result['model_version'])->toBe('demo-linear-regression-v1')
        ->and($result['summary'])->toMatchArray(['items_forecasted' => 2, 'items_insufficient_history' => 1])
        ->and(collect($result['rows'])->where('item_name', 'Teacher Desk')->pluck('inventory_id')->all())->toBe([1001, 2001])
        ->and(collect($result['rows'])->where('item_name', 'Teacher Desk')->pluck('category_id')->all())->toBe([1, 8])
        ->and($result['rows'][0]['forecast_demand'])->toBe(3)
        ->and($result['rows'][2]['unknown_months'])->toBe(['2026-07', '2026-09'])
        ->and($inventoryCount)->toBe(0)
        ->and($inventoryQueriesAfterRead)->toBe(0);
});

test('demo reader reports missing and rejects malformed or live-labeled output', function () {
    Storage::fake('forecast');
    $service = app(StoredDemandForecastService::class);
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    expect($service->readDemo($user)['status'])->toBe('missing');
    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'Show a sample forecast'])
        ->assertOk()
        ->assertJsonPath('reply', 'No Sample/Demo forecast has been generated yet. Sample results are separate from live inventory data.');

    $payload = storedDemoForecastPayload();
    $payload['source_type'] = 'live';
    putStoredDemoForecast($payload);
    expect($service->readDemo($user)['status'])->toBe('error')
        ->and($service->readDemo($user)['rows'])->toBe([]);
    $this->postJson(route('ai.chat'), ['message' => 'Show a sample forecast'])
        ->assertOk()
        ->assertJsonPath('reply', 'The Sample/Demo forecast output is invalid or unavailable. No forecast values were returned, and live data was not substituted.');

    $payload = storedDemoForecastPayload();
    $payload['forecasts'][0]['inventory_id'] = 0;
    putStoredDemoForecast($payload);
    expect($service->readDemo($user)['status'])->toBe('error');

    $payload = storedDemoForecastPayload();
    $payload['forecasts'][0]['history_window']['start_month'] = '2000-01';
    putStoredDemoForecast($payload);
    expect($service->readDemo($user)['status'])->toBe('error');
});

test('chat and reports show the same labeled demo result without live stock recommendations', function () {
    Storage::fake('forecast');
    putStoredDemoForecast(storedDemoForecastPayload());
    Storage::disk('forecast')->put('forecast/forecast.json', 'live-production-sentinel');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $inventoryCount = Inventory::count();
    $movementCount = StockMovement::count();

    $chat = $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'Give me the full sample demand forecast list'])
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($chat->json('reply'))
        ->toContain('Sample/Demo stock-out demand forecast', 'not live inventory', 'Inventory ID 1001', 'Category ID 1', '3 pieces', 'Inventory ID 2001', '5 pieces', 'insufficient verified history', 'Unknown months: July 2026, September 2026')
        ->not->toContain('"source_type"', '"forecasts"', 'Live only item');

    $this->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->assertJsonPath('demoForecastResult.source_type', 'demo')
        ->assertJsonFragment([
            'inventory_id' => 1001,
            'item_name' => 'Teacher Desk',
            'forecast_demand' => 3,
            'source_type' => 'demo',
        ])
        ->assertJsonFragment([
            'inventory_id' => 2001,
            'item_name' => 'Teacher Desk',
            'forecast_demand' => 5,
            'source_type' => 'demo',
        ])
        ->assertJsonFragment([
            'inventory_id' => 4001,
            'item_name' => 'Limited Test Kits',
            'unknown_months' => ['2026-07', '2026-09'],
            'source_type' => 'demo',
        ]);

    $demoResponse = $this->getJson(route('api.custodian.reports.forecast.recommendations', ['source' => 'demo']))
        ->assertOk()
        ->assertJsonFragment(['inventory_id' => 1001, 'item_name' => 'Teacher Desk'])
        ->assertJsonFragment(['inventory_id' => 2001, 'item_name' => 'Teacher Desk'])
        ->assertJsonFragment(['forecast_demand' => 5]);

    expect(collect($demoResponse->json('recommendations.data'))
        ->every(fn (array $row): bool => ! array_key_exists('suggested_procurement', $row)))->toBeTrue();

    $this->get('/spa/reports/forecast')->assertOk();

    expect(Inventory::count())->toBe($inventoryCount)
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(Storage::disk('forecast')->get('forecast/forecast.json'))->toBe('live-production-sentinel');
});

test('demo forecast details paginate the expanded identity list', function () {
    Storage::fake('forecast');
    $payload = storedDemoForecastPayload();
    $categoryNames = [
        1 => 'Furniture and Fixtures',
        2 => 'ICT Equipment',
        3 => 'Office Equipment',
        4 => 'Laboratory Equipment',
        5 => 'Learning Resources',
        6 => 'Sports Equipment',
        7 => 'Tools and Maintenance Equipment',
        8 => 'Other Equipment',
    ];
    $template = $payload['forecasts'][0];
    for ($index = 1; $index <= 16; $index++) {
        $categoryId = (($index - 1) % 8) + 1;
        $payload['forecasts'][] = [
            ...$template,
            'inventory_id' => 6000 + $index,
            'item_name' => 'Sample Item ' . $index,
            'category_id' => $categoryId,
            'category' => $categoryNames[$categoryId],
        ];
    }
    putStoredDemoForecast($payload);

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $apiUrl = route('api.custodian.reports.forecast.recommendations', ['source' => 'demo']);

    $pageOne = $this->actingAs($user)->getJson($apiUrl)->assertOk();
    expect($pageOne->json('recommendations.total'))->toBe(19)
        ->and($pageOne->json('recommendations.data'))->toHaveCount(15);
    expect(collect($pageOne->json('recommendations.data'))->pluck('inventory_id')->all())
        ->toContain(1001, 6012);

    $pageTwo = $this->getJson($apiUrl . '&page=2')->assertOk();
    expect(collect($pageTwo->json('recommendations.data'))->pluck('inventory_id')->all())
        ->toContain(6013, 6016);

    $this->get('/spa/reports/forecast')->assertOk();
});

test('detailed forecast recommendations support filters sorting and pagination', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Office Supplies', 'requires_serial_number' => false]);
    $forecasts = [];

    foreach (range(1, 18) as $number) {
        $itemName = sprintf('Notebook %02d', $number);
        $inventory = Inventory::create([
            'category_id' => $category->category_id,
            'user_id' => $user->id,
            'item_name' => $itemName,
            'unit' => 'boxes',
            'quantity' => 0,
            'unit_cost' => 10,
            'date_acquired' => '2026-01-01',
            'status' => 'available',
        ]);
        $forecasts[] = liveForecastRow($inventory, $number * 10);
    }

    putStoredLiveForecast($forecasts);

    $apiUrl = route('api.custodian.reports.forecast.recommendations');

    $filtered = $this->actingAs($user)
        ->getJson($apiUrl . '?search=Notebook%2012&category=Office%20Supplies&priority=Urgent&confidence=Low')
        ->assertOk();
    expect(collect($filtered->json('recommendations.data'))->pluck('item_name')->all())
        ->toContain('Notebook 12')
        ->not->toContain('Notebook 11');
    $filtered->assertJsonFragment(['priority' => 'Urgent']);

    $sorted = $this->getJson($apiUrl . '?sort=suggested')->assertOk();
    expect(array_slice(collect($sorted->json('recommendations.data'))->pluck('item_name')->all(), 0, 2))
        ->toBe(['Notebook 18', 'Notebook 17']);
    expect(collect($sorted->json('recommendations.data'))->pluck('item_name')->all())
        ->not->toContain('Notebook 03');

    $sortedPageTwo = $this->getJson($apiUrl . '?sort=suggested&page=2')->assertOk();
    expect(collect($sortedPageTwo->json('recommendations.data'))->pluck('item_name')->all())
        ->toContain('Notebook 03', 'Notebook 01')
        ->not->toContain('Notebook 18');

    $this->get('/spa/reports/forecast')->assertOk();

    $this->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->assertJsonPath('demoForecastResult.source_type', 'demo')
        ->assertJsonMissing(['item_name' => 'Live only item']);
});

test('detailed forecast page shows separate empty and error states', function () {
    Storage::fake('forecast');
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $apiUrl = route('api.custodian.reports.forecast.recommendations');

    $this->actingAs($user)
        ->getJson($apiUrl)
        ->assertOk()
        ->assertJsonPath('recommendations.data', [])
        ->assertJsonPath('forecastResult.rows', []);

    Storage::disk('forecast')->put('forecast/forecast.json', '{invalid json');

    $this->getJson($apiUrl)
        ->assertOk()
        ->assertJsonPath('forecastResult.status', 'error')
        ->assertJsonPath('recommendations.data', []);

    $this->get('/spa/reports/forecast')->assertOk();
});
