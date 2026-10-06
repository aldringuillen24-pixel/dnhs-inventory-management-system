<?php

use App\Models\Role;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\AiInventoryService;
use App\Services\GeminiApiService;
use App\Services\InventoryAnswerService;
use Tests\Support\LegacyAssistantTestRouter as InventoryQuestionRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    uses(RefreshDatabase::class);
}

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.gemini.api_key', null);
});

function roleScopeUser(?string $roleName = null): User
{
    $user = new User();
    if ($roleName !== null) {
        $user->setRelation('role', new Role(['role_name' => $roleName]));
    }

    return $user;
}

test('the AI capability policy defines the allowed capabilities for every role', function () {
    $policy = new AiCapabilityPolicy();
    $allCapabilities = (new ReflectionClass(AiCapabilityPolicy::class))
        ->getReflectionConstant('CAPABILITIES')?->getValue();

    expect($policy->capabilitiesForRole('Property Custodian'))->toEqual(array_values(array_diff(
        $allCapabilities,
        [AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY]
    )))
        ->and($policy->capabilitiesForRole('End User'))->toEqual([])
        ->and($policy->capabilitiesForRole('School Head'))->toEqual([])
        ->and($policy->capabilitiesForRole('Administrator'))->toEqual([]);
});
test('each supported parsed intent maps to one allowlisted application capability', function () {
    $policy = new AiCapabilityPolicy();
    $intentCapabilities = [
        'availability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'stock' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        'pending_requests' => AiCapabilityPolicy::VIEW_PENDING_REQUESTS,
        'low_stock' => AiCapabilityPolicy::VIEW_LOW_STOCK,
        'forecast' => AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
        'executive_reports' => AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS,
        'system_summary' => AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY,
        'inventory_valuation' => AiCapabilityPolicy::VIEW_INVENTORY_VALUATION,
        'own_assignments' => AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS,
        'own_requests' => AiCapabilityPolicy::VIEW_OWN_REQUESTS,
        'item_status' => AiCapabilityPolicy::VIEW_ITEM_STATUS,
        'assignments' => AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        'maintenance' => AiCapabilityPolicy::VIEW_MAINTENANCE,
        'disposal' => AiCapabilityPolicy::VIEW_DISPOSAL,
        'ready_to_dispose' => AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        'location' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
        'purchase_history' => AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
    ];

    foreach ($intentCapabilities as $intent => $capability) {
        expect($policy->capabilityForIntent($intent))->toBe($capability)
            ->and($policy->isKnownCapability($capability))->toBeTrue();
    }

    expect($policy->capabilityForIntent('unsupported'))->toBeNull()
        ->and($policy->capabilityForIntent('sql'))->toBeNull();

    // Procurement priorities moved to AI Decision Support on the Demand Forecast
    // page. The capability still exists, because that panel authorises against
    // it, but the generic assistant must no longer route the intent.
    expect($policy->capabilityForIntent('procurement_priorities'))->toBeNull()
        ->and($policy->isKnownCapability(AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES))->toBeTrue();
});

test('procurement questions are redirected to AI Decision Support', function () {
    $service = app(AiInventoryService::class);

    $redirected = [
        'What should we purchase first?',
        'what should we buy first',
        'which items should we order',
        'Should we restock Bond Paper?',
        'give me a procurement priority list',
        'what can wait?',
    ];

    foreach ($redirected as $question) {
        $reply = $service->ask(
            roleScopeUser('Property Custodian'),
            $question,
            ['status' => 'success', 'intent' => 'unsupported', 'capability' => null, 'answer' => []],
        );

        expect($reply)->toContain('AI Decision Support')
            ->and($reply)->toContain('Demand Forecast');
    }
});

test('the assistant still answers stock and forecast questions', function () {
    $service = app(AiInventoryService::class);

    // The redirect must not swallow ordinary questions this assistant owns.
    foreach (['How many bond papers are in stock?', 'What is the demand forecast?'] as $question) {
        $reply = $service->ask(
            roleScopeUser('Property Custodian'),
            $question,
            ['status' => 'success', 'intent' => 'stock', 'capability' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK, 'answer' => []],
        );

        expect($reply)->not->toContain('AI Decision Support');
    }
});

test('the assistant welcome message matches each role capability set', function () {    $policy = new AiCapabilityPolicy();

    expect($policy->assistantHelpText(roleScopeUser('End User')))->toBe(
        'I can only provide information available for your role.'
    )->and($policy->assistantHelpText(roleScopeUser('Property Custodian')))->toBe(
        'I can help with stock availability, inventory stock, inventory locations, pending requests, low-stock items, demand forecasts, reports, inventory valuation, assignment records, maintenance records, disposal records, items ready for disposal, purchase history, item status, requests, and assigned items.'
    )->and($policy->assistantHelpText(roleScopeUser('School Head')))->toBe(
        'I can only provide information available for your role.'
    )->and($policy->assistantHelpText(roleScopeUser('Administrator')))->toBe(
        'I can only provide information available for your role.'
    )->and($policy->assistantHelpText(roleScopeUser('Unknown Role')))->toBe(
        'I can only provide information available for your role.'
    )->and($policy->assistantHelpText(roleScopeUser()))->toBe(
        'I can only provide information available for your role.'
    );
});

test('unknown roles and capabilities are denied by default', function () {
    $policy = new AiCapabilityPolicy();

    expect($policy->allows(roleScopeUser(), AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY))->toBeFalse()
        ->and($policy->allows(roleScopeUser('Unknown Role'), AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY))->toBeFalse()
        ->and($policy->allows(roleScopeUser('Administrator'), 'unknown_capability'))->toBeFalse();
});

    test('clearly unrelated weather and user-count questions remain unsupported', function () {
        $parser = app(\App\Services\AiIntentParserService::class);

        expect($parser->route("What's the weather today?")['intent'])->toBe('unsupported')
        ->and($parser->route('How many users are registered?')['intent'])->toBe('unsupported');
    });

test('forbidden questions are denied before database retrieval or provider calls', function () {
    config()->set('services.gemini.api_key', 'test-key');
    Http::fake();
    $routed = [
        'intent' => 'forecast',
        'capability' => AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
        'item_name' => null,
        'needs_external_explanation' => true,
    ];

    $result = app(InventoryAnswerService::class)->answer(roleScopeUser('End User'), $routed);

    expect($result['status'])->toBe('forbidden');
    Http::assertNothingSent();
});

test('roles outside property custody cannot access the assistant', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $parsed = [
        'intent' => 'stock',
        'topic_action' => 'new_topic',
        'item_name' => 'Restricted Projector',
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'query' => null,
        'response_type' => 'detail',
        'explanation' => false,
        'explanation_topic' => null,
        'is_compound' => false,
        'sub_requests' => [],
    ];
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($parsed)]]]]],
    ])]);

    $role = Role::firstOrCreate(['role_name' => 'School Head']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create(['category_name' => 'Parser Role Scope']);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $user->id,
        'item_name' => 'Restricted Projector',
        'quantity' => 1,
        'date_acquired' => now()->toDateString(),
        'status' => 'available',
    ]);
    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (str_contains(strtolower($query->sql), 'from "inventory"')) {
            $inventoryQueries++;
        }
    });

    test()->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show stock for Restricted Projector'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    expect($inventoryQueries)->toBe(0);
    Http::assertNothingSent();
});

test('roles outside property custody cannot request or calculate comparisons before inventory queries', function () {
    $role = Role::firstOrCreate(['role_name' => 'School Head']);
    $user = User::factory()->create(['role_id' => $role->role_id]);
    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'from "inventory"') || str_contains($sql, 'from "stock_movements"')) {
            $inventoryQueries++;
        }
    });

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Compare stock-out between August 2026 and September 2026',
    ])->assertForbidden();
    $this->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => 'stock_out_quantity',
        'scope' => 'all',
        'month_one' => '2026-08',
        'month_two' => '2026-09',
    ])->assertForbidden();

    expect($inventoryQueries)->toBe(0);
    Http::assertNothingSent();
});

test('comparison context is isolated by authenticated user and removed after property custodian role loss', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $otherRole = Role::firstOrCreate(['role_name' => 'School Head']);
    $firstUser = User::factory()->create(['role_id' => $custodianRole->role_id]);
    $secondUser = User::factory()->create(['role_id' => $custodianRole->role_id]);
    $contextKey = 'ai.inventory_comparison_context.user.' . $firstUser->id;

    $this->actingAs($firstUser)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => 'request_count',
        'scope' => 'all',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();
    expect(session()->get($contextKey)['user_id'])->toBe($firstUser->id);

    $otherUserReply = $this->actingAs($secondUser)->postJson(route('ai.chat'), [
        'message' => 'Does that mean there was no activity in June and July?',
    ])->assertOk();
    expect($otherUserReply->json('reply'))->not->toContain('June 2026', 'July 2026', 'verified zero activity')
        ->and(session()->get($contextKey)['user_id'])->toBe($firstUser->id);

    $firstUser->update(['role_id' => $otherRole->role_id]);
    $this->actingAs($firstUser)->postJson(route('ai.chat'), [
        'message' => 'Does that mean there was no activity in June and July?',
    ])->assertForbidden();
    expect(session()->get($contextKey))->toBeNull();
    Http::assertNothingSent();
    $this->travelBack();
});

test('explanation service denies a role-scoped fact packet before provider calls', function () {
    config()->set('services.gemini.api_key', 'test-key');
    Http::fake();

    $reply = app(AiInventoryService::class)->ask(roleScopeUser('End User'), 'Why?', [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
        'answer' => ['items' => [['item_name' => 'Private Paper', 'available_quantity' => 8]]],
        'explanation_data' => ['item_name' => 'Private Paper', 'available_quantity' => 8],
    ]);

    expect($reply)->toBe('That information is not available for your role.');
    Http::assertNothingSent();
});

test('end users cannot access valuation, forecasts, or procurement priorities', function () {
    $service = app(InventoryAnswerService::class);
    $user = roleScopeUser('End User');

    foreach ([
        AiCapabilityPolicy::VIEW_INVENTORY_VALUATION,
        AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
        AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES,
        AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        AiCapabilityPolicy::VIEW_MAINTENANCE,
        AiCapabilityPolicy::VIEW_DISPOSAL,
        AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
        AiCapabilityPolicy::VIEW_ITEM_STATUS,
    ] as $capability) {
        $result = $service->answer($user, [
            'intent' => 'factual',
            'capability' => $capability,
            'item_name' => null,
        ]);
        expect($result['status'])->toBe('forbidden');
    }
});

test('property custodians can use stock, low stock, pending, forecast, and procurement capabilities', function () {
    $policy = new AiCapabilityPolicy();
    $user = roleScopeUser('Property Custodian');

    foreach ([
        AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
        AiCapabilityPolicy::VIEW_LOW_STOCK,
        AiCapabilityPolicy::VIEW_PENDING_REQUESTS,
        AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
        AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES,
    ] as $capability) {
        expect($policy->allows($user, $capability))->toBeTrue();
    }

    expect($policy->allows($user, AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS))->toBeTrue()
        ->and($policy->allows($user, AiCapabilityPolicy::VIEW_OWN_REQUESTS))->toBeTrue()
        ->and($policy->allows($user, AiCapabilityPolicy::VIEW_INVENTORY_VALUATION))->toBeTrue()
        ->and($policy->allows($user, AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY))->toBeFalse()
        ->and($policy->allows($user, AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS))->toBeTrue();
    foreach ([
        AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        AiCapabilityPolicy::VIEW_MAINTENANCE,
        AiCapabilityPolicy::VIEW_DISPOSAL,
        AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
        AiCapabilityPolicy::VIEW_ITEM_STATUS,
    ] as $capability) {
        expect($policy->allows($user, $capability))->toBeTrue();
    }
    });

    test('property custodians cannot request registered-user or system-wide summary counts', function () {
        config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
        $parsed = [
            'intent' => 'system_summary',
            'topic_action' => 'new_topic',
            'item_name' => null,
            'inventory_id' => null,
            'serial_number' => null,
            'location_query' => null,
            'filters' => ['category_id' => null, 'unit' => null],
            'query' => null,
            'response_type' => 'detail',
            'explanation' => false,
            'explanation_topic' => null,
            'is_compound' => false,
            'sub_requests' => [],
        ];
        Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($parsed)]]]]],
        ])]);
        $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
        $user = User::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show a system summary'])
            ->assertOk()
            ->assertJsonPath('reply', 'That information is not available for your role.');

        expect(app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY))->toBeFalse();
        Http::assertSentCount(1);
    });
