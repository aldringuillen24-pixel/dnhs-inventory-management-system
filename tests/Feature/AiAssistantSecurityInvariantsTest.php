<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function invariantUser(string $role): User
{
    return User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => $role])->role_id]);
}

function invariantInventory(?User $owner): Inventory
{
    $category = Category::firstOrCreate(['category_name' => 'Confirm']);

    return Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => ($owner ?? User::query()->firstOrFail())->id,
        'unit' => 'pieces', 'item_name' => 'Projector', 'quantity' => 3,
        'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
}

it('CONFIRM 1: an end user asking for executive reports is told it is not available for their role', function () {
    Http::preventStrayRequests();
    Http::fake();
    $user = invariantUser('End User');
    invariantInventory($user);

    $response = $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Show me the executive inventory report',
    ]);

    // End User holds no assistant capability at all, so the turn is refused
    // before any classification, and never reaches the reports tool.
    $response->assertStatus(403);
    expect($response->json('message'))->toContain('not available for your role');

    // A role that can use the assistant but not executive reports is refused
    // by the router itself, with the same wording.
    $custodian = invariantUser('Property Custodian');
    expect(app(AiCapabilityPolicy::class)->allows($custodian, AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS))->toBeTrue();

    $endUserResult = app(\App\Services\Tools\ToolRouter::class)->dispatch($user, [
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS,
        'item_name' => null,
    ]);
    expect($endUserResult['status'])->toBe('forbidden')
        ->and(app(\App\Services\InventoryAnswerService::class)->localReply($endUserResult, $user))
        ->toBe('That information is not available for your role.');
});

it('CONFIRM 2: with no provider key every answerable question still answers', function () {
    config()->set('services.gemini.api_key', null);
    Http::preventStrayRequests();
    Http::fake();

    // Same deterministic classifier the local-facts suite uses, so the question
    // is routed without a provider exactly as it is in production's fallback.
    $router = app(\Tests\Support\LegacyAssistantTestRouter::class);
    app()->instance(\App\Services\AiIntentParserService::class, new class($router, app(AiCapabilityPolicy::class)) extends \App\Services\AiIntentParserService {
        public function __construct(private \Tests\Support\LegacyAssistantTestRouter $testRouter, AiCapabilityPolicy $policy)
        {
            parent::__construct($policy, app(\App\Services\GeminiApiService::class));
        }

        public function route(string $question, array $activeTopic = [], array $turns = []): array
        {
            return $this->testRouter->route($question, $activeTopic);
        }
    });

    $user = invariantUser('Property Custodian');
    $item = invariantInventory($user);

    $replies = [
        'How many projectors are available?' => 'Projector has 3 pieces available.',
        'Show details for Projector' => 'Projector: 3 pieces available out of 3 total; low stock.',
        'Which items are low in stock?' => '- Projector: 3 pieces (Low Stock)',
    ];

    foreach ($replies as $question => $expected) {
        $response = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => $question]);
        $response->assertOk();
        expect($response->json('reply'))->toBe($expected, "question: {$question}");
    }

    Http::assertNothingSent();
    expect($item->fresh()->quantity)->toBe(3);
});

it('CONFIRM 3: a malformed parse degrades to clarification, not a 500', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::preventStrayRequests();
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);
    $user = invariantUser('Property Custodian');

    $response = $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'How many projectors are available?',
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue()
        ->and($response->json('reply'))->toBeString()->not->toBeEmpty();
});

it('CONFIRM 4: a compound turn is authorised per sub-request, not per turn', function () {
    config()->set('services.gemini.api_key', null);
    Http::preventStrayRequests();
    Http::fake();
    $user = invariantUser('Property Custodian');
    invariantInventory($user);

    $executor = app(\App\Services\Tools\CompoundPlanExecutor::class);

    $make = fn (string $capability, array $extra = []): array => array_replace([
        'intent' => 'factual', 'capability' => $capability, 'item_name' => null,
        'inventory_id' => null, 'serial_number' => null, 'location_query' => null,
        'category_id' => null, 'unit' => null, 'query' => null,
        'filters' => ['category_id' => null, 'unit' => null], 'response_type' => 'count',
        'needs_external_explanation' => false, 'needs_clarification' => false, 'vague' => false,
    ], $extra);

    // Turn-level authorisation would pass (the custodian holds the first
    // capability); per-part authorisation refuses the second.
    $results = $executor->execute($user, [
        'kind' => 'compound',
        'requests' => [
            $make(AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY, ['item_name' => 'Projector']),
            $make(AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY, ['response_type' => 'detail']),
        ],
    ]);

    expect(app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY))->toBeTrue()
        ->and(app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY))->toBeFalse()
        ->and($results[0]['status'])->toBe('success')
        ->and($results[1]['status'])->toBe('forbidden');
});