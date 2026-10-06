<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\AiIntentParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function compoundUser(string $roleName): User
{
    return User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => $roleName])->role_id]);
}

function compoundParser(): AiIntentParserService
{
    return new AiIntentParserService(
        app(AiCapabilityPolicy::class),
        app(\App\Services\GeminiApiService::class),
    );
}

function compoundParse(array $overrides = []): array
{
    return array_replace([
        'intent' => 'stock',
        'topic_action' => 'new_topic',
        'item_name' => 'Projector',
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'query' => null,
        'response_type' => 'count',
        'explanation' => false,
        'explanation_topic' => null,
        'is_compound' => false,
        'sub_requests' => [],
    ], $overrides);
}

function compoundSub(array $overrides = []): array
{
    return array_replace([
        'intent' => 'stock',
        'item_name' => null,
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'query' => null,
        'response_type' => 'count',
    ], $overrides);
}

function compoundRequest(string $capability, array $overrides = []): array
{
    return array_replace([
        'intent' => 'factual',
        'capability' => $capability,
        'item_name' => null,
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'category_id' => null,
        'unit' => null,
        'query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'response_type' => 'count',
        'needs_external_explanation' => false,
        'needs_clarification' => false,
        'vague' => false,
    ], $overrides);
}

/** Reply the parser with one structured classification. */
function compoundFakeParse(array $parse): void
{
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode($parse)]]]]],
    ])]);
}

function compoundSeedInventory(): void
{
    $category = Category::create(['category_name' => 'Compound']);
    Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => User::query()->firstOrFail()->id,
        'unit' => 'pieces', 'item_name' => 'Projector', 'quantity' => 3,
        'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
}

it('returns a single plan for an ordinary question', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse());

    $route = compoundParser()->route('How many projectors are available?');

    expect($route['plan']['kind'])->toBe('single')
        ->and($route['plan']['requests'])->toHaveCount(1)
        ->and($route['plan']['requests'][0]['capability'])->toBe(AiCapabilityPolicy::VIEW_INVENTORY_STOCK);
});

it('decomposes a compound question into one routed request per part', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse([
        'is_compound' => true,
        'sub_requests' => [
            compoundSub(['intent' => 'stock', 'item_name' => 'Projector']),
            compoundSub(['intent' => 'low_stock', 'response_type' => 'list']),
        ],
    ]));

    $route = compoundParser()->route('How many projectors are available and which items are low in stock?');

    expect($route['plan']['kind'])->toBe('compound')
        ->and($route['plan']['requests'])->toHaveCount(2)
        ->and($route['plan']['requests'][0]['capability'])->toBe(AiCapabilityPolicy::VIEW_INVENTORY_STOCK)
        ->and($route['plan']['requests'][0]['item_name'])->toBe('Projector')
        ->and($route['plan']['requests'][1]['capability'])->toBe(AiCapabilityPolicy::VIEW_LOW_STOCK);
});

it('authorises each sub-request separately so a denied part neither leaks nor voids the rest', function () {
    $user = compoundUser('Property Custodian');
    compoundSeedInventory();

    $results = app(\App\Services\Tools\CompoundPlanExecutor::class)->execute($user, [
        'kind' => 'compound',
        'requests' => [
            compoundRequest(AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY, [
                'item_name' => 'Projector',
            ]),
            // A Property Custodian holds availability but not the system-wide summary.
            compoundRequest(AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY, ['response_type' => 'detail']),
        ],
    ]);

    expect(app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY))->toBeTrue()
        ->and(app(AiCapabilityPolicy::class)->allows($user, AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY))->toBeFalse();

    expect($results)->toHaveCount(2)
        ->and($results[0]['status'])->toBe('success')
        ->and($results[0]['answer']['available_count'])->toBe(3)
        ->and($results[1]['status'])->toBe('forbidden');

    $reply = app(\App\Services\InventoryAnswerService::class)->mergeReplies($results, $user);

    expect($reply)->toContain('Projector has 3 pieces available.')
        ->and($reply)->toContain('That information is not available for your role.');
});

it('answers a compound turn end to end through the chat endpoint', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $user = compoundUser('Property Custodian');
    compoundSeedInventory();

    compoundFakeParse(compoundParse([
        'intent' => 'stock',
        'item_name' => 'Projector',
        'is_compound' => true,
        'sub_requests' => [
            compoundSub(['intent' => 'stock', 'item_name' => 'Projector']),
            compoundSub(['intent' => 'low_stock', 'response_type' => 'list']),
        ],
    ]));

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'How many projectors are available and which items are low in stock?',
    ])->assertOk()->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '3 available units')
        && str_contains($reply, 'Projector: 3 pieces (Low Stock)'));
});

it('rejects a decomposition that tries to fan out into a forecast sub-request', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse([
        'is_compound' => true,
        'sub_requests' => [
            compoundSub(['intent' => 'stock', 'item_name' => 'Projector']),
            compoundSub(['intent' => 'forecast']),
        ],
    ]));

    expect(compoundParser()->parse('how many projectors and the forecast'))->toBeNull();
});

it('rejects a decomposition whose selector does not appear in the question', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse([
        'is_compound' => true,
        'sub_requests' => [
            compoundSub(['intent' => 'stock', 'item_name' => 'Projector']),
            compoundSub(['intent' => 'stock', 'item_name' => 'NotMentioned Item']),
        ],
    ]));

    expect(compoundParser()->parse('how many projectors and chairs'))->toBeNull();
});

it('rejects is_compound true with fewer than two sub-requests', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse([
        'is_compound' => true,
        'sub_requests' => [compoundSub(['intent' => 'stock', 'item_name' => 'Projector'])],
    ]));

    expect(compoundParser()->parse('how many projectors'))->toBeNull();
});

it('rejects a sub-request that smuggles in an extra key', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    compoundFakeParse(compoundParse([
        'is_compound' => true,
        'sub_requests' => [
            compoundSub(['intent' => 'stock', 'item_name' => 'Projector']),
            [...compoundSub(['intent' => 'low_stock']), 'sql' => 'SELECT * FROM inventory'],
        ],
    ]));

    expect(compoundParser()->parse('how many projectors and low stock'))->toBeNull();
});