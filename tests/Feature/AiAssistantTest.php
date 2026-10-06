<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\AssignmentRequest;
use App\Models\Transaction;
use App\Services\AiCapabilityPolicy;
use App\Services\AiInventoryService;
use App\Services\AiIntentParserService;
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
    $router = app(InventoryQuestionRouter::class);
    app()->instance(AiIntentParserService::class, new class($router, app(AiCapabilityPolicy::class)) extends AiIntentParserService {
        public function __construct(private InventoryQuestionRouter $testRouter, AiCapabilityPolicy $policy)
        {
            parent::__construct($policy, app(\App\Services\GeminiApiService::class));
        }

        public function route(string $question, array $activeTopic = [], array $turns = []): array
        {
            return $this->testRouter->route($question, $activeTopic);
        }
    });
});

function useRealAssistantIntentParser(): void
{
    app()->instance(AiIntentParserService::class, new AiIntentParserService(
        app(AiCapabilityPolicy::class),
        app(\App\Services\GeminiApiService::class),
    ));
}

function explanationTestUser(string $roleName = 'Property Custodian'): User
{
    $user = new User(['first_name' => 'Test', 'last_name' => 'User']);
    $user->setRelation('role', new Role(['role_name' => $roleName]));

    return $user;
}

function persistedAssistantUser(string $roleName = 'Property Custodian'): User
{
    $role = Role::firstOrCreate(['role_name' => $roleName]);
    $id = uniqid('assistant-', false);

    return User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Assistant',
        'last_name' => 'Test',
        'username' => $id,
        'email' => $id . '@example.test',
        'password' => 'password',
        'status' => 'active',
    ]);
}

function createAssistantInventory(User $user, string $name, int $quantity, string $status = 'available'): Inventory
{
    $category = Category::firstOrCreate(['category_name' => 'Assistant Test Items']);

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $user->id,
        'item_name' => $name,
        'description' => $name . ' test record',
        'quantity' => $quantity,
        'unit_cost' => 1,
        'date_acquired' => now()->toDateString(),
        'status' => $status,
    ]);
}

function createAssistantRequest(User $user, Inventory $item, string $status = 'waiting for approval'): AssignmentRequest
{
    return AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $user->id,
        'target_user_id' => $user->id,
        'quantity' => 1,
        'status' => $status,
        'requested_at' => now(),
    ]);
}

function assistantInventoryContext(Inventory $item, string $capability = AiCapabilityPolicy::VIEW_INVENTORY_STOCK): array
{
    return [
        'prior_intent' => 'factual',
        'capability' => $capability,
        'inventory_id' => (int) $item->item_id,
        'category_id' => (int) $item->category_id,
        'unit' => $item->unit,
        'serial_number' => $item->serial_number,
        'item_name' => $item->item_name,
        'reference_type' => 'inventory',
        'reference_id' => (int) $item->item_id,
    ];
}

function assistantStoredConversationContext(
    Inventory $item,
    string $capability = AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
    string $intent = 'factual',
    string $responseType = 'detail',
): array {
    return [
        'version' => 1,
        'intent' => $intent,
        'capability' => $capability,
        'response_type' => $responseType,
        'reference_type' => 'inventory',
        'reference_id' => (int) $item->item_id,
        'item_name' => $item->item_name,
        'filters' => [],
    ];
}

function assistantContextSessionKey(User $user): string
{
    return 'ai.inventory_context.user.' . $user->id;
}

function assistantComparisonContextSessionKey(User $user): string
{
    return 'ai.inventory_comparison_context.user.' . $user->id;
}

function structuredAssistantParse(array $overrides = []): array
{
    return array_replace([
        'intent' => 'availability',
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
    ], $overrides);
}

test('local inventory counts use singular wording for one unit and one item type', function () {
    $service = app(InventoryAnswerService::class);

    expect($service->localReply([
        'status' => 'success',
        'answer' => ['available_count' => 1, 'item_types' => 1],
    ]))->toBe('There is 1 available unit across 1 item type.');
});

test('unit cost questions return the per-unit cost instead of total value', function () {
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Book', 5);
    $item->update(['unit_cost' => 200]);
    $service = app(InventoryAnswerService::class);
    $result = $service->answer($user, [
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_INVENTORY_VALUATION,
        'item_name' => 'Book',
        'original_question' => 'How much the unit cost of each Book?',
    ]);

    expect($service->localReply($result, $user))
        ->toBe('- Book: 200 per piece.')
        ->not->toContain('total value');
});

test('disposed inventory count returns a count instead of an empty-record message', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Disposed Projector', 1, 'disposed');
    createAssistantInventory($user, 'Disposed Printer', 1, 'disposed');
    $service = app(InventoryAnswerService::class);
    $routed = app(InventoryQuestionRouter::class)->route('How many disposed items?');
    $result = $service->answer($user, $routed);

    expect($result['status'])->toBe('success')
        ->and($result['answer']['disposal_count'])->toBe(2)
        ->and($service->localReply($result, $user))->toBe('There are 2 disposed items.');
});

test('unresolved reference after a generic availability count asks which item to check', function () {
    Http::fake();
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Available Projector', 1);

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'How many items are available?',
    ])->assertOk()->assertJsonPath('reply', 'There is 1 available unit across 1 item type.');

    $this->postJson(route('ai.chat'), ['message' => 'what is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which available item would you like to check?');

    Http::assertNothingSent();
});

test('count, list, item detail, and typo answers use database inventory', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Dell Laptop', 2);
    createAssistantInventory($user, 'Dell Laptop', 3);
    createAssistantInventory($user, 'Projector', 1);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);

    $count = $service->answer($user, $router->route('How many laptops are available?'));
    $typoCount = $service->answer($user, $router->route('How many laptops are avaiable?'));
    $list = $service->answer($user, $router->route('Which items are available?'));
    $details = $service->answer($user, $router->route('Show details for Projector.'));
    $generic = $service->answer($user, $router->route('Is the item available?'));

    expect($service->localReply($count, $user))->toBe('Dell Laptop has 5 pieces available.')
        ->and($service->localReply($typoCount, $user))->toBe('Dell Laptop has 5 pieces available.')
        ->and($service->localReply($list, $user))->toContain('Dell Laptop', 'Projector')
        ->and($service->localReply($details, $user))->toContain('Projector', 'Projector test record')
        ->and($service->localReply($generic, $user))->toBe('Which item would you like to check?');
});

test('ambiguous partial item names ask for clarification', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 1);
    createAssistantInventory($user, 'Projector Screen', 2);
    $service = app(InventoryAnswerService::class);
    $request = app(InventoryQuestionRouter::class)->route('Show details for Project.');

    expect($service->localReply($service->answer($user, $request), $user))
        ->toContain('Projector', 'Projector Screen', 'category, serial number, or inventory ID');
});

    test('duplicate same-name detail records ask for an identifying detail', function () {
        $user = persistedAssistantUser();
        createAssistantInventory($user, 'Projector', 1);
        createAssistantInventory($user, 'Projector', 1);
        $router = app(InventoryQuestionRouter::class);
        $service = app(InventoryAnswerService::class);

        $result = $service->answer($user, $router->route('Show the projector.'));

        expect($result['status'])->toBe('clarification')
        ->and($service->localReply($result, $user))
        ->toContain('Projector', 'category, serial number, or inventory ID');
    });

test('clarification asks only for the missing item or request detail', function () {
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);
    $user = explanationTestUser();

    $missingItem = $router->route('How many are left?');
    $ambiguousRequests = $router->route('Show requests.');

    expect($service->localReply($service->answer($user, $missingItem), $user))
        ->toBe('Which item would you like to check?')
        ->and($router->route('How much stock is left?')['needs_clarification'])->toBeTrue()
        ->and($service->localReply($service->answer($user, $ambiguousRequests), $user))
        ->toBe('Do you mean your requests or all pending inventory requests?');
});

test('a missing item clarification uses the next message to resume the database query', function () {
    $user = persistedAssistantUser();
    $ictCategory = Category::create(['category_name' => 'ICT Equipment']);
    $paperworkCategory = Category::create(['category_name' => 'Learning Resources']);
    $firstLaptop = createAssistantInventory($user, 'Dell Laptop', 4);
    $firstLaptop->update(['category_id' => $ictCategory->category_id]);
    $secondLaptop = createAssistantInventory($user, 'Dell Laptop', 3);
    $secondLaptop->update(['category_id' => $paperworkCategory->category_id]);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'available item'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    expect(session()->get('ai.inventory_clarification.user.' . $user->id . '.context'))
        ->toMatchArray(['requires_item_name' => true, 'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY]);

    $this->postJson(route('ai.chat'), ['message' => 'laptop'])
        ->assertOk()
        ->assertJsonPath('reply', 'Dell Laptop: 7 pieces available.');

    expect(session()->get('ai.inventory_clarification.user.' . $user->id))->toBeNull();
});

test('a fresh generic count question replaces a stale item clarification', function () {
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Dell Laptop', 2);
    $item->update(['unit' => 'piece']);
    $other = createAssistantInventory($user, 'Dell Laptop', 3);
    $other->update(['unit' => 'case']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many laptops are available?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));

    $response = $this->postJson(route('ai.chat'), ['message' => 'How many items are available?']);
    $response->assertOk();
    expect($response->json('reply'))->toContain('5 available units');

    expect(session()->get('ai.inventory_clarification.user.' . $user->id))->toBeNull();
});

test('a bare item follow-up filters the previous generic availability count', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Dell Laptop', 2);
    createAssistantInventory($user, 'Dell Laptop', 3);
    createAssistantInventory($user, 'Projector', 4);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many items are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'There are 9 available units across 2 item types.');

    expect(session()->get(assistantContextSessionKey($user) . '.context.reference_type'))->toBe('inventory_search');

    $this->postJson(route('ai.chat'), ['message' => 'laptop'])
        ->assertOk()
        ->assertJsonPath('reply', 'Dell Laptop has 5 pieces available.');
});

test('availability and product identifier follow-ups return matching database facts', function () {
    $user = persistedAssistantUser();
    $first = createAssistantInventory($user, 'Dell Laptop', 2);
    $first->update(['serial_number' => 'DELL-001', 'inventory_item_no' => 'INV-000001']);
    $second = createAssistantInventory($user, 'Dell Laptop', 3);
    $second->update(['serial_number' => 'DELL-002', 'inventory_item_no' => 'INV-000002']);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);

    $availability = $router->route('laptop available');
    $identifiers = $service->answer($user, $router->route('What is the inventory number of the laptop?'));
    $productName = $service->answer($user, $router->route(
        'What is the name the product name?',
        assistantInventoryContext($first, AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY)
    ));

    expect($availability)->toMatchArray([
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'item_name' => 'laptop',
    ])->and($service->localReply($service->answer($user, $availability), $user))
        ->toBe('Dell Laptop has 5 pieces available.')
        ->and($service->localReply($identifiers, $user))->toContain('INV-000001', 'INV-000002', 'DELL-001', 'DELL-002')
        ->and($service->localReply($productName, $user))->toBe('Product name: Dell Laptop.');
});

test('grouped availability answers retain product context for identifier and name follow-ups', function () {
    $user = persistedAssistantUser();
    $first = createAssistantInventory($user, 'Dell Laptop', 2);
    $first->update(['serial_number' => 'DELL-001', 'inventory_item_no' => 'INV-000001']);
    $second = createAssistantInventory($user, 'Dell Laptop', 3);
    $second->update(['serial_number' => 'DELL-002', 'inventory_item_no' => 'INV-000002']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'laptop available'])
        ->assertOk()
        ->assertJsonPath('reply', 'Dell Laptop has 5 pieces available.');

    expect(session()->get(assistantContextSessionKey($user) . '.context.reference_type'))->toBe('inventory_group');

    $this->postJson(route('ai.chat'), ['message' => 'What is the inventory number of the laptop?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'INV-000001') && str_contains($reply, 'INV-000002'));

    $this->postJson(route('ai.chat'), ['message' => 'What is the name the product name?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Product name: Dell Laptop.');
});

test('plural and hyphenated item names still resolve against database inventory', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Dell-Laptop', 4);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer($user, $router->route('How much Dell laptops stock is left?'));

    expect($service->localReply($result, $user))->toBe('Dell-Laptop has 4 pieces available.');
});

test('same-name inventory with different categories or units asks for clarification', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Laptop', 4);
    $otherCategory = Category::create(['category_name' => 'Assistant Test Accessories']);
    Inventory::create([
        'category_id' => $otherCategory->category_id,
        'unit' => 'case',
        'user_id' => $user->id,
        'item_name' => 'Laptop',
        'description' => 'Second laptop stock record',
        'quantity' => 2,
        'unit_cost' => 1,
        'date_acquired' => now()->toDateString(),
        'status' => 'available',
    ]);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer($user, $router->route('How many laptops are available?'));

    expect($result['status'])->toBe('clarification')
        ->and($service->localReply($result, $user))
        ->toContain('Laptop', 'category, serial number, or inventory ID');
});

test('short follow-ups use the previous item and explain low stock from database facts', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Dell Laptop', 2);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);
    $context = assistantInventoryContext($inventory, AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY);

    $routed = $router->route('Why?', $context);
    $result = $service->answer($user, $routed);

    expect($routed)->toMatchArray([
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'item_name' => 'Dell Laptop',
    ])->and($service->localReply($result, $user))->toContain(
        'Dell Laptop is available',
        '2 piece remain',
        'low-stock limit of 5'
    );
});

test('availability and count follow-ups keep the item from the prior turn', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 2);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);
    $context = assistantInventoryContext($inventory);

    $availability = $router->route('Is it available?', $context);
    $count = $router->route('How many are left?', $context);
    $availableCount = $router->route('How many are available?', $context);
    $whatAbout = $router->route('What about this one?', $context);

    expect($availability)->toMatchArray([
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'item_name' => 'Projector',
        'response_type' => 'detail',
    ])->and($service->localReply($service->answer($user, $count), $user))
        ->toBe('Projector has 2 pieces available.')
        ->and($availableCount)->toMatchArray([
            'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
            'item_name' => 'Projector',
            'response_type' => 'count',
        ])
        ->and($service->localReply($service->answer($user, $whatAbout), $user))->toContain('Projector');
});

test('what about a named item carries the previous inventory action to that item', function () {
    $router = app(InventoryQuestionRouter::class);
    $context = assistantInventoryContext(createAssistantInventory(persistedAssistantUser(), 'Projector', 2), AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY);
    $context['response_type'] = 'count';

    expect($router->route('What about paper?', $context))->toMatchArray([
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'item_name' => 'paper',
        'response_type' => 'count',
        'starts_new_topic' => true,
    ])->and($router->route('What about paper?'))->toMatchArray([
        'intent' => 'clarification',
        'needs_clarification' => true,
        'clarification_question' => 'What would you like to know about paper?',
    ]);
});

test('the first clarification choice resolves the original question in displayed order', function () {
    $user = persistedAssistantUser();
    $firstCategory = Category::create(['category_name' => 'First Laptop Category']);
    $secondCategory = Category::create(['category_name' => 'Second Laptop Category']);
    $first = createAssistantInventory($user, 'Laptop', 4);
    $first->update(['category_id' => $firstCategory->category_id, 'serial_number' => 'LAPTOP-FIRST']);
    $second = createAssistantInventory($user, 'Laptop', 7);
    $second->update(['category_id' => $secondCategory->category_id, 'serial_number' => 'LAPTOP-SECOND', 'unit' => 'case']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many laptops are available?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Laptop') && str_contains($reply, '2) Laptop'));

    $this->postJson(route('ai.chat'), ['message' => 'the first one'])
        ->assertOk()
        ->assertJsonPath('reply', 'Laptop has 4 pieces available.');
});

test('an invalid clarification choice keeps the original request pending for retry', function () {
    $user = persistedAssistantUser();
    $firstCategory = Category::create(['category_name' => 'First Laptop Category']);
    $secondCategory = Category::create(['category_name' => 'Second Laptop Category']);
    $first = createAssistantInventory($user, 'Laptop', 4);
    $first->update(['category_id' => $firstCategory->category_id]);
    $second = createAssistantInventory($user, 'Laptop', 7);
    $second->update(['category_id' => $secondCategory->category_id, 'unit' => 'case']);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many laptops are available?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Laptop') && str_contains($reply, '2) Laptop'));

    $originalPending = session()->get($clarificationKey);
    expect($originalPending)->not->toBeNull();
    expect(app(InventoryAnswerService::class)->refreshClarificationContext($user, $originalPending['context']))->not->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'the third one'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Laptop') && str_contains($reply, '2) Laptop'));

    expect(session()->get($clarificationKey . '.context'))->toEqual($originalPending['context']);

    $this->postJson(route('ai.chat'), ['message' => 'the second one'])
        ->assertOk()
        ->assertJsonPath('reply', 'Laptop has 7 cases available.');

    expect(session()->get($clarificationKey))->toBeNull();
});

test('a clarification is discarded when its candidate set changes in the database', function () {
    $user = persistedAssistantUser();
    $first = createAssistantInventory($user, 'Laptop', 4);
    createAssistantInventory($user, 'Laptop', 7);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Laptop.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Laptop') && str_contains($reply, '2) Laptop'));

    expect(session()->get($clarificationKey))->not->toBeNull();
    $first->delete();

    $this->postJson(route('ai.chat'), ['message' => 'the second one'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    expect(session()->get($clarificationKey))->toBeNull();
});

test('malformed pending clarification data is discarded before candidate selection', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Laptop', 4);
    createAssistantInventory($user, 'Laptop', 7);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Laptop.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));

    $pending = session()->get($clarificationKey);
    $pending['context']['cached_answer'] = 'must not be reused';
    session()->put($clarificationKey, $pending);

    $this->postJson(route('ai.chat'), ['message' => 'the first one'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?')
        ->assertDontSee('must not be reused');

    expect(session()->get($clarificationKey))->toBeNull();
});

test('a clearly unrelated question clears pending clarification state', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Laptop', 4);
    createAssistantInventory($user, 'Laptop', 7);
    config()->set('services.gemini.api_key', null);
    Http::fake();
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Laptop.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));

    $this->postJson(route('ai.chat'), ['message' => 'What is the weather today?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => ! str_contains($reply, 'multiple matches'));

    expect(session()->get($clarificationKey))->toBeNull();
    Http::assertNothingSent();
});

test('serial number and inventory ID resolve only their matching clarification candidate', function () {
    $user = persistedAssistantUser();
    $first = createAssistantInventory($user, 'Laptop', 4);
    $first->update(['serial_number' => 'LAPTOP-FIRST', 'inventory_item_no' => 'ASSET-LAPTOP-1']);
    $second = createAssistantInventory($user, 'Laptop', 7);
    $second->update(['serial_number' => 'LAPTOP-SECOND']);
    $pending = [
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        'item_name' => 'Laptop',
        'response_type' => 'detail',
        'candidate_ids' => [$first->item_id, $second->item_id],
    ];
    $service = app(InventoryAnswerService::class);

    expect($service->clarificationReply($user, $pending))->toContain('Asset Tag: ASSET-LAPTOP-1')
        ->and($service->resolveClarification($user, $pending, 'LAPTOP-SECOND')['inventory_id'])->toBe($second->item_id)
        ->and($service->resolveClarification($user, $pending, 'inventory ID ' . $first->item_id)['inventory_id'])->toBe($first->item_id)
        ->and($service->resolveClarification($user, $pending, 'asset tag ASSET-LAPTOP-1')['inventory_id'])->toBe($first->item_id)
        ->and($service->resolveClarification($user, $pending, 'Assistant Test Items'))->toBeNull()
        ->and($service->resolveClarification($user, $pending, 'UNKNOWN-SERIAL'))->toBeNull()
        ->and($service->resolveClarification($user, $pending, 'inventory ID 999999'))->toBeNull();
});

test('what about a named item rechecks current stock and clarifies duplicate matches', function () {
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 2);
    $paperCategory = Category::create(['category_name' => 'Paper Supplies']);
    $otherPaperCategory = Category::create(['category_name' => 'Paper Equipment']);
    $paper = createAssistantInventory($user, 'Paper', 5);
    $paper->update(['category_id' => $paperCategory->category_id]);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 2 pieces available.');

    $projector->update(['quantity' => 6]);
    $this->postJson(route('ai.chat'), ['message' => 'How many are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 6 pieces available.');

    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Paper has 5 pieces available.');

    $otherPaper = createAssistantInventory($user, 'Paper', 8);
    $otherPaper->update(['category_id' => $otherPaperCategory->category_id]);
    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Paper has 13 pieces available.');
});

test('a named new item replaces a pending clarification and carries forward the clear action', function () {
    $user = persistedAssistantUser();
    $firstCategory = Category::create(['category_name' => 'First Laptop Category']);
    $secondCategory = Category::create(['category_name' => 'Second Laptop Category']);
    $firstLaptop = createAssistantInventory($user, 'Laptop', 4);
    $firstLaptop->update(['category_id' => $firstCategory->category_id]);
    $secondLaptop = createAssistantInventory($user, 'Laptop', 7);
    $secondLaptop->update(['category_id' => $secondCategory->category_id, 'unit' => 'case']);
    $paper = createAssistantInventory($user, 'Paper', 5);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many laptops are available?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Laptop') && str_contains($reply, '2) Laptop'));

    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Paper has 5 pieces available.');

    expect(session()->get(assistantContextSessionKey($user))['context'])
        ->toEqual(assistantStoredConversationContext($paper, AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY, 'factual', 'count'))
        ->and(session()->get('ai.inventory_clarification.user.' . $user->id))->toBeNull();
});

test('a complete new inventory request replaces a pending clarification', function () {
    $user = persistedAssistantUser();
    $firstCategory = Category::create(['category_name' => 'First Laptop Category']);
    $secondCategory = Category::create(['category_name' => 'Second Laptop Category']);
    $firstLaptop = createAssistantInventory($user, 'Laptop', 4);
    $firstLaptop->update(['category_id' => $firstCategory->category_id]);
    $secondLaptop = createAssistantInventory($user, 'Laptop', 7);
    $secondLaptop->update(['category_id' => $secondCategory->category_id]);
    $projector = createAssistantInventory($user, 'Projector', 1);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many laptops are available?'])
        ->assertOk();

    $this->postJson(route('ai.chat'), ['message' => 'Show details for Projector'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, 'Projector test record'));

    expect(session()->get(assistantContextSessionKey($user))['context'])
        ->toEqual(assistantStoredConversationContext($projector))
        ->and(session()->get('ai.inventory_clarification.user.' . $user->id))->toBeNull();
});

test('pending request answers are readable for zero and nonzero results', function () {
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Laptop', 2);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);
    $request = $router->route('pending requests');

    expect($service->localReply($service->answer($user, $request), $user))
        ->toBe('There are no pending item requests.');

    createAssistantRequest($user, $item);
    createAssistantRequest($user, $item);
    createAssistantRequest($user, $item, 'waiting for transfer approval');
    $countRequest = $router->route('How many pending requests?');

    expect($service->localReply($service->answer($user, $countRequest), $user))
        ->toBe('There are 3 pending item requests.');
});

test('follow-up context from the chat endpoint stays database-grounded', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    Http::fake();

    $firstResponse = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Projector.']);
    $firstResponse->assertOk();
    $inventory->update(['building' => 'Main Building', 'room' => '203']);

    $response = $this->postJson(route('ai.chat'), [
        'message' => 'Where is it?',
        'history' => [['sender' => 'user', 'text' => 'Where is a different item?']],
    ]);

    $response->assertOk()->assertJsonPath('reply', "- Projector (Inventory ID {$inventory->item_id}): Holder: not recorded; Building: Main Building; Room: 203; Status: available");
    Http::assertNothingSent();
});

test('stored conversation context contains only the purpose and validated inventory reference', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    $router = app(InventoryQuestionRouter::class);
    $service = app(InventoryAnswerService::class);
    $question = $router->route('How many projectors are available?');
    $result = $service->answer($user, $question);

    expect($service->conversationContext($user, $question, $result))->toEqual([
        'version' => 1,
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'response_type' => 'count',
        'reference_type' => 'inventory',
        'reference_id' => (int) $inventory->item_id,
        'item_name' => 'Projector',
        'filters' => [],
    ]);
});

test('follow-up context reloads the current item identity from its validated reference', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);

    $this->actingAs($user)->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($inventory, AiCapabilityPolicy::VIEW_INVENTORY_STOCK),
        ],
    ]);
    $inventory->update(['item_name' => 'Portable Projector', 'building' => 'Main Building', 'room' => '203']);

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', "- Portable Projector (Inventory ID {$inventory->item_id}): Holder: not recorded; Building: Main Building; Room: 203; Status: available");
});

test('deleted or disposed inventory references are discarded before follow-up answers', function () {
    $user = persistedAssistantUser();
    $deleted = createAssistantInventory($user, 'Deleted Projector', 1);
    $disposed = createAssistantInventory($user, 'Disposed Projector', 1);

    $this->actingAs($user)->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($deleted),
        ],
    ]);
    $deleted->delete();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');

    $this->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($disposed),
        ],
    ]);
    $disposed->update(['status' => 'disposed']);

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');
});

test('context with cached or unknown fields is discarded', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    $invalidContext = assistantStoredConversationContext($inventory);
    $invalidContext['available_quantity'] = 999;

    $this->actingAs($user)->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => $invalidContext,
        ],
    ])->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');

    expect(session()->get(assistantContextSessionKey($user)))->toBeNull();
});

test('holder and low-stock follow-ups use the saved item and a new item replaces context', function () {
    $custodian = persistedAssistantUser();
    $holder = persistedAssistantUser('End User');
    $projector = createAssistantInventory($custodian, 'Projector', 1, 'assigned');
    $projector->update(['assigned_to_user_id' => $holder->id, 'building' => 'Main Building', 'room' => '203']);
    Transaction::create([
        'item_id' => $projector->item_id,
        'user_id' => $holder->id,
        'quantity' => 1,
        'status' => 'assigned',
        'transaction_date' => now(),
    ]);
    $laptop = createAssistantInventory($custodian, 'Laptop', 3);
    $laptop->update(['building' => 'Science Building', 'room' => '210']);

    $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'Who has it?'])
        ->assertOk()
        ->assertJsonPath('reply', "- Projector (Inventory ID {$projector->item_id}): Holder: {$holder->full_name}; Building: Main Building; Room: 203; Status: assigned");
    $this->postJson(route('ai.chat'), ['message' => 'Is it low stock?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector: 0 piece available out of 1 total; out of stock. Projector test record');

    $this->postJson(route('ai.chat'), ['message' => 'Show details for Laptop.'])->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', "- Laptop (Inventory ID {$laptop->item_id}): Holder: not recorded; Building: Science Building; Room: 210; Status: available");
});

test('count follow-ups requery the latest database quantity', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 2);
    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])->assertOk();

    $inventory->update(['quantity' => 7]);

    $this->postJson(route('ai.chat'), ['message' => 'How many are left?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 7 pieces available.');
});

test('repeated questions query fresh inventory data without answer caching', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 2);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many Projector are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 2 pieces available.');

    $inventory->update(['quantity' => 9]);

    $this->postJson(route('ai.chat'), ['message' => 'How many Projector are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 9 pieces available.');
});

test('follow-ups without context ask for clarification', function () {
    $user = persistedAssistantUser();

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');

    $this->postJson(route('ai.chat'), ['message' => 'How many are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    $this->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    $this->postJson(route('ai.chat'), ['message' => 'Why?'])
        ->assertOk()
        ->assertJsonPath('reply', 'What would you like me to explain?');

    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'What would you like to know about paper?');

    $this->postJson(route('ai.chat'), ['message' => 'the first one'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');
});

test('ambiguous item answers do not seed follow-up context', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 1);
    createAssistantInventory($user, 'Projector', 1);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));

    expect(session()->get(assistantContextSessionKey($user)))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, '1) Projector')
            && str_contains($reply, '2) Projector')
            && str_contains($reply, 'Which one do you mean?'));
});

test('a category clarification resolves the original item and enables its follow-ups', function () {
    $user = persistedAssistantUser();
    $learningResources = Category::create(['category_name' => 'Learning Resources']);
    $ictEquipment = Category::create(['category_name' => 'ICT Equipment']);
    $matchingItem = Inventory::create([
        'category_id' => $learningResources->category_id,
        'unit' => 'piece',
        'user_id' => $user->id,
        'item_name' => 'Dell Laptop',
        'description' => 'Learning resources laptop',
        'quantity' => 4,
        'building' => 'Main Building',
        'room' => '203',
        'unit_cost' => 1,
        'date_acquired' => now()->toDateString(),
        'status' => 'available',
    ]);
    Inventory::create([
        'category_id' => $ictEquipment->category_id,
        'unit' => 'piece',
        'user_id' => $user->id,
        'item_name' => 'Dell Laptop',
        'description' => 'ICT equipment laptop',
        'quantity' => 2,
        'unit_cost' => 1,
        'date_acquired' => now()->toDateString(),
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'Show details for Dell Laptop.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Category: Learning Resources')
            && str_contains($reply, 'Category: ICT Equipment'));
    $pending = session()->get('ai.inventory_clarification.user.' . $user->id);
    expect($pending)->not->toBeNull()
        ->and($pending['context']['candidate_ids'])->toEqual([$matchingItem->item_id, $matchingItem->item_id + 1]);
    expect(app(InventoryAnswerService::class)->resolveClarification($user, $pending['context'], 'Learning resources category'))
        ->not->toBeNull();

    $categoryReply = $this->postJson(route('ai.chat'), ['message' => 'Learning resources category'])
        ->assertOk()
        ->json('reply');
    expect($categoryReply)->toContain('Learning resources laptop')
        ->not->toContain('ICT equipment laptop');

    expect(session()->get(assistantContextSessionKey($user))['context'])
        ->toEqual(assistantStoredConversationContext($matchingItem));

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', "- Dell Laptop (Inventory ID {$matchingItem->item_id}): Holder: not recorded; Building: Main Building; Room: 203; Status: available");
});

test('pending clarification candidates are isolated to the authenticated user', function () {
    $owner = persistedAssistantUser();
    $otherUser = persistedAssistantUser();
    $learningResources = Category::create(['category_name' => 'Private Learning Resources']);
    $ictEquipment = Category::create(['category_name' => 'Private ICT Equipment']);

    foreach ([$learningResources, $ictEquipment] as $category) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $owner->id,
            'item_name' => 'Private Dell Laptop',
            'description' => $category->category_name,
            'quantity' => 1,
            'unit_cost' => 1,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
        ]);
    }

    $this->actingAs($owner)
        ->postJson(route('ai.chat'), ['message' => 'Show details for Private Dell Laptop.'])
        ->assertOk();
    expect(session()->get('ai.inventory_clarification.user.' . $owner->id))->not->toBeNull();

    $this->actingAs($otherUser)
        ->postJson(route('ai.chat'), ['message' => 'Private Learning Resources category'])
        ->assertOk()
        ->assertJsonPath('reply', (new AiCapabilityPolicy())->assistantHelpText($otherUser))
        ->assertDontSee('Private Dell Laptop');

    expect(session()->get('ai.inventory_clarification.user.' . $otherUser->id))->toBeNull();
});

test('pending clarification is discarded when the user loses assistant access', function () {
    $user = persistedAssistantUser('Property Custodian');
    createAssistantInventory($user, 'Private Laptop', 4);
    createAssistantInventory($user, 'Private Laptop', 7);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Private Laptop.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));
    expect(session()->get($clarificationKey))->not->toBeNull();

    $schoolHead = Role::firstOrCreate(['role_name' => 'School Head']);
    $user->update(['role_id' => $schoolHead->role_id]);

    $this->postJson(route('ai.chat'), ['message' => 'the first one'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    expect(session()->get($clarificationKey))->toBeNull();
});

test('expired context is not used for follow-ups', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 1);
    config()->set('inventory.ai_context_ttl_minutes', 1);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-29 10:00:00'));

    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk();

    expect(session()->get(assistantContextSessionKey($user))['expires_at'])
        ->toBe(now()->copy()->addMinute()->timestamp);

    $this->travel(2)->minutes();
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');
    $this->travelBack();
});

test('follow-ups recheck role access before inventory data is read', function () {
    $user = persistedAssistantUser('End User');
    $inventory = createAssistantInventory($user, 'Restricted Projector', 1);
    $context = assistantStoredConversationContext($inventory);
    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (str_contains(strtolower($query->sql), 'from "inventory"')) {
            $inventoryQueries++;
        }
    });

    $this->actingAs($user)->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => $context,
        ],
    ])->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.')
        ->assertDontSee('Restricted Projector');

    expect($inventoryQueries)->toBe(0);
});

test('one user cannot use another user inventory conversation context', function () {
    $firstUser = persistedAssistantUser();
    $secondUser = persistedAssistantUser();
    $inventory = createAssistantInventory($firstUser, 'Private Projector', 1);

    $this->actingAs($firstUser)->postJson(route('ai.chat'), ['message' => 'Show details for Private Projector.'])
        ->assertOk();
    expect(session()->get(assistantContextSessionKey($firstUser)))->not->toBeNull();

    $this->actingAs($secondUser)->postJson(route('ai.chat'), [
        'message' => 'Where is it?',
        'history' => [['sender' => 'user', 'text' => 'Show details for Private Projector.']],
    ])->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?')
        ->assertDontSee('Private Projector');

    expect(session()->get(assistantContextSessionKey($secondUser)))->toBeNull();
});

test('unsupported questions clear inventory context and prevent stale follow-ups', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    $key = assistantContextSessionKey($user);
    $stored = [
        'user_id' => $user->id,
        'expires_at' => now()->addMinutes(15)->timestamp,
        'context' => assistantStoredConversationContext($inventory),
    ];

    $this->actingAs($user)->withSession([$key => $stored])
        ->postJson(route('ai.chat'), ['message' => 'Tell me a joke.'])
        ->assertOk();

    expect(session()->get($key))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?')
        ->assertDontSee('Projector');
});

test('unsupported questions discard pending clarification context', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 1);
    createAssistantInventory($user, 'Projector', 2);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches'));
    expect(session()->get($clarificationKey))->not->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Tell me a joke.'])
        ->assertOk();

    expect(session()->get($clarificationKey))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?');
});

test('losing assistant access clears the active topic', function () {
    $user = persistedAssistantUser('School Head');
    $inventory = createAssistantInventory($user, 'Restricted Projector', 1);
    $contextKey = assistantContextSessionKey($user);

    $this->actingAs($user)->withSession([
        $contextKey => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($inventory),
        ],
    ])->postJson(route('ai.chat'), ['message' => 'How many Restricted Projector are available?'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    expect(session()->get($contextKey))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');
});

test('assistant access denial clears the users active topic and pending clarification', function () {
    $user = persistedAssistantUser('End User');
    $inventory = createAssistantInventory($user, 'Restricted Projector', 1);
    $contextKey = assistantContextSessionKey($user);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->withSession([
        $contextKey => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($inventory),
        ],
        $clarificationKey => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => ['intent' => 'factual'],
        ],
    ])->postJson(route('ai.chat'), ['message' => 'How many items are available?'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    expect(session()->get($contextKey))->toBeNull()
        ->and(session()->get($clarificationKey))->toBeNull();
});

test('starting a new chat clears active topic and pending clarification', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    $contextKey = assistantContextSessionKey($user);
    $clarificationKey = 'ai.inventory_clarification.user.' . $user->id;

    $this->actingAs($user)->withSession([
        $contextKey => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => assistantStoredConversationContext($inventory),
        ],
        $clarificationKey => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => ['intent' => 'factual'],
        ],
    ])->postJson(route('ai.chat.reset'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(session()->get($contextKey))->toBeNull()
        ->and(session()->get($clarificationKey))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like me to locate?')
        ->assertDontSee('Projector');
});

test('pronouns it and those resolve against validated context and refresh latest database facts', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 5);
    $inventory->update(['building' => 'Building A', 'room' => '101']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 5 pieces available.');

    // 'it' carries forward the count action
    $this->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 5 pieces available.');

    // 'those' carries forward the count action
    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 5 pieces available.');

    // Stock changes in database
    $inventory->update(['quantity' => 2]);

    // 'those' queries fresh database quantity
    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 2 pieces available.');

    // Now asking for details switches response type
    $this->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, 'Projector test record'));

    // 'it' carries forward the detail action
    $this->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, 'Projector test record'));

    // 'Where is it?' returns location
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Building A') && str_contains($reply, '101'));

    // 'those' continues the location topic
    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Building A') && str_contains($reply, '101'));
});

test('why and how many are available follow-ups resolve against validated context', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 2);
    createAssistantRequest($user, $inventory, 'waiting for approval');

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Why is projector low in stock?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, '2 piece remain'));

    // 'Why?' follow-up carries explanation
    $this->postJson(route('ai.chat'), ['message' => 'Why?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, '2 piece remain'));

    // 'How many are available?' queries fresh count
    $this->postJson(route('ai.chat'), ['message' => 'How many are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 2 pieces available.');
});

test('clear short follow-ups use application rules without a second Gemini parse', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse(json_encode(structuredAssistantParse(['response_type' => 'detail'])))
    )]);
    useRealAssistantIntentParser();
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 2);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'Why?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Projector') && str_contains($reply, '2 piece remain'));

    Http::assertSentCount(1);
});

test('pending-request wording starts a new topic and uses validated Gemini parsing', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $pendingParse = structuredAssistantParse([
        'intent' => 'pending_requests',
        'item_name' => null,
        'query' => 'pending_count',
        'response_type' => 'count',
    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode(structuredAssistantParse(['response_type' => 'count']))))
        ->push(geminiGenerateContentResponse(json_encode($pendingParse)))]);
    useRealAssistantIntentParser();
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 2);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many Projector are available?'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'How many requests are waiting for approval?'])
        ->assertOk()
        ->assertJsonPath('reply', 'There are no pending item requests.');

    Http::assertSentCount(2);
});

test('a named inventory request replaces the active topic through Gemini parsing', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $paperParse = structuredAssistantParse(['item_name' => 'Bond Paper']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode(structuredAssistantParse())))
        ->push(geminiGenerateContentResponse(json_encode($paperParse)))]);
    useRealAssistantIntentParser();
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 2);
    createAssistantInventory($user, 'Bond Paper', 5);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many Projector are available?'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'How many Bond Paper are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Bond Paper has 5 pieces available.');

    expect(session()->get(assistantContextSessionKey($user))['context']['item_name'])->toBe('Bond Paper');
    Http::assertSentCount(2);
});

test('ambiguous Gemini parsing clears the active topic and asks for clarification', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode(structuredAssistantParse())))
        ->push(geminiGenerateContentResponse('{malformed'))]);
    useRealAssistantIntentParser();
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 2);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many Projector are available?'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'What about that?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which inventory item or question would you like me to check?');

    expect(session()->get(assistantContextSessionKey($user)))->toBeNull();
    Http::assertSentCount(2);
});

test('what about a named item carries forward action, queries fresh data without old item facts, and replaces context', function () {
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 3);
    $paperCategory = Category::create(['category_name' => 'Supplies']);
    $paper = Inventory::create([
        'category_id' => $paperCategory->category_id,
        'unit' => 'box',
        'user_id' => $user->id,
        'item_name' => 'Bond Paper',
        'description' => 'A4 Bond Paper',
        'quantity' => 12,
        'building' => 'Admin Wing',
        'room' => 'Supply Room',
        'unit_cost' => 200,
        'date_acquired' => now()->toDateString(),
        'status' => 'available',
    ]);

    // Initial question establishes count action on Projector
    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 3 pieces available.');

    // 'What about bond paper?' queries bond paper fresh and carries forward the count action
    $this->postJson(route('ai.chat'), ['message' => 'What about bond paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Bond Paper has 12 boxes available.')
        ->assertDontSee('piece')
        ->assertDontSee('Projector');

    // Context is now Bond Paper; 'Where is it?' queries Bond Paper's location
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Admin Wing') && str_contains($reply, 'Supply Room'));

    // Now test when the target of 'what about' has duplicate matches
    createAssistantInventory($user, 'White Chalk', 5);
    createAssistantInventory($user, 'White Chalk', 8);

    $this->postJson(route('ai.chat'), ['message' => 'What about white chalk?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'multiple matches') && str_contains($reply, 'White Chalk'));

    // Bond Paper context was replaced/cleared; pending clarification is for White Chalk
    expect(session()->get(assistantContextSessionKey($user)))->toBeNull();
});

test('a clearly named new request starts a new topic and replaces old context after a successful answer', function () {
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 1);
    $projector->update(['building' => 'Building 1', 'room' => '101']);

    $whiteboard = createAssistantInventory($user, 'Whiteboard', 4);
    $whiteboard->update(['building' => 'Building 2', 'room' => '202']);

    // Start with Projector location
    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'Where is the Projector?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Building 1') && str_contains($reply, '101'));

    // Clearly named new request for Whiteboard starts a new topic
    $this->postJson(route('ai.chat'), ['message' => 'Where is the Whiteboard?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Building 2') && str_contains($reply, '202'));

    // Stored context is now Whiteboard
    expect(session()->get(assistantContextSessionKey($user))['context']['reference_id'])
        ->toBe((int) $whiteboard->item_id);

    // Follow-ups now refer to Whiteboard, not Projector
    $this->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Building 2') && str_contains($reply, '202'));

    $this->postJson(route('ai.chat'), ['message' => 'How many are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Whiteboard has 4 pieces available.');
});

test('missing, expired, invalid, and unauthorized contexts prompt clarification and do not guess', function () {
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 3);

    // 1. Missing context
    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'What would you like to know about paper?');

    // 2. Expired context
    config()->set('inventory.ai_context_ttl_minutes', 1);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-29 12:00:00'));
    $this->postJson(route('ai.chat'), ['message' => 'Show details for Projector.'])->assertOk();

    $this->travel(5)->minutes();
    $this->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');
    $this->postJson(route('ai.chat'), ['message' => 'those'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');
    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'What would you like to know about paper?');
    $this->travelBack();

    // 3. Invalid context (tampered unknown fields)
    $tamperedContext = assistantStoredConversationContext($inventory);
    $tamperedContext['tampered_key'] = 'malicious';
    $this->withSession([
        assistantContextSessionKey($user) => [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => $tamperedContext,
        ],
    ])->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which item would you like to check?');

    // 4. Unauthorized context (End User attempting follow-up on custodian-only capability)
    $endUser = persistedAssistantUser('End User');
    $custodianContext = assistantStoredConversationContext($inventory, AiCapabilityPolicy::VIEW_LOW_STOCK);
    $this->actingAs($endUser)->withSession([
        assistantContextSessionKey($endUser) => [
            'user_id' => $endUser->id,
            'expires_at' => now()->addMinutes(15)->timestamp,
            'context' => $custodianContext,
        ],
    ])->postJson(route('ai.chat'), ['message' => 'it'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    $this->actingAs($user);
    $this->postJson(route('ai.chat'), ['message' => 'What about paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'What would you like to know about paper?');
});

test('end users cannot access detailed inventory facts outside their role', function () {
    $service = app(InventoryAnswerService::class);
    $result = $service->answer(explanationTestUser('End User'), app(InventoryQuestionRouter::class)->route('Show details for Projector.'));

    expect($result['status'])->toBe('forbidden')
        ->and($service->localReply($result, explanationTestUser('End User')))
        ->toBe('That information is not available for your role.');
});

test('assistant question suggestions are limited to the signed-in role', function () {
    $endUser = persistedAssistantUser('End User');
    $this->actingAs($endUser);
    $endUserMarkup = view('components.header.ai-inventory-assistant')->render();

    expect($endUserMarkup)->not->toContain('AI Inventory Assistant', 'What items are assigned to me?', 'Show my requests');

    $this->postJson(route('ai.chat'), ['message' => 'How many items are available?'])
        ->assertForbidden()
        ->assertJsonPath('message', 'The AI assistant is not available for your role.');

    $custodian = persistedAssistantUser('Property Custodian');
    $this->actingAs($custodian);
    $custodianMarkup = view('components.header.ai-inventory-assistant')->render();

    expect($custodianMarkup)->toContain('How many pending requests?', 'Which items are low in stock?')
        ->not->toContain('What items are assigned to me?', 'Show my requests');
});

test('factual answers are calculated locally without a Gemini request', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = explanationTestUser();
    $routed = app(InventoryQuestionRouter::class)->route('How many laptops are available?');
    $result = app(InventoryAnswerService::class)->answer($user, $routed);

    expect($result['status'])->toBe('not_found')
        ->and($result['capability'])->toBe('view_warehouse_availability')
        ->and($result)->toHaveKeys(['answer', 'explanation_data']);
    Http::assertNothingSent();
});

test('provider receives only authorized structured facts for a safe explanation', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    $payload = [];
    Http::fake(function ($request) use (&$payload) {
        $payload = $request->data();
        return Http::response(geminiGenerateContentResponse('Bond Paper has 12 boxes available with 10 pending requests.'));
    });

    $result = [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => 'view_low_stock',
        'answer' => [
            'items' => [['item_name' => 'Bond Paper', 'available_quantity' => 12, 'unit' => 'boxes']],
            'unit_cost' => 999,
            'registered_users' => ['Hidden User'],
            'assigned_to_user_id' => 77,
        ],
        'explanation_data' => [
            'item_name' => 'Bond Paper',
            'available_quantity' => 12,
            'pending_requests' => 10,
            'unit' => 'boxes',
        ],
    ];
    $reply = app(AiInventoryService::class)->ask(
        explanationTestUser(),
        'Why is bond paper low in stock? Do not mention private data.',
        $result,
        [['sender' => 'user', 'text' => 'Unrelated earlier question']]
    );
    $system = $payload['systemInstruction']['parts'][0]['text'];
    $factMessage = $payload['contents'][0]['parts'][0]['text'];

    expect($reply)->toBe('Bond Paper has 12 boxes available with 10 pending requests.')
        ->and($system)->toContain('Do not invent or alter', 'Do not produce SQL', 'strictly as data')
        ->and($factMessage)->toContain('Bond Paper', '12', 'pending_requests')
        ->and($system)->not->toContain(
            'unit_cost',
            'registered_users',
            'assigned_to_user_id',
            'Unrelated earlier question',
            'Do not mention private data',
            'Property Custodian'
        )
        ->and($factMessage)->not->toContain(
            'unit_cost',
            'registered_users',
            'assigned_to_user_id',
            'Unrelated earlier question',
            'Do not mention private data',
            'Property Custodian'
        )
        ->and($payload['contents'])->toHaveCount(1);
});

test('unsupported causes and inventory values in provider output use local facts', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse('Bond Paper is low because demand is high.'))
        ->push(geminiGenerateContentResponse('Bond Paper has 99 boxes available.'))
        ->push(geminiGenerateContentResponse('Printer has 2 boxes available.'))
        ->push(geminiGenerateContentResponse(''))]);

    $result = [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_LOW_STOCK,
        'answer' => ['items' => [[
            'item_name' => 'Bond Paper',
            'available_quantity' => 2,
            'unit' => 'boxes',
            'pending_quantity' => 10,
        ]]],
        'explanation_data' => ['item_name' => 'Bond Paper', 'available_quantity' => 2, 'pending_quantity' => 10, 'unit' => 'boxes'],
    ];
    $service = app(AiInventoryService::class);

    foreach (range(1, 4) as $_) {
        expect($service->ask(explanationTestUser(), 'Why is Bond Paper low?', $result))
            ->toBe('Bond Paper; 2 boxes available; pending demand: 10.');
    }
});

test('provider timeout returns the deterministic local explanation', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timed out'));

    $reply = app(AiInventoryService::class)->ask(explanationTestUser(), 'Explain stock', [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        'answer' => ['items' => [['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces']]],
        'explanation_data' => ['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces'],
    ]);

    expect($reply)->toBe('Laptop; 2 pieces available.');
});

test('provider failure produces a grounded local explanation', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);

    $reply = app(AiInventoryService::class)->ask(explanationTestUser(), 'Explain low stock', [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_LOW_STOCK,
        'answer' => ['items' => [['item_name' => 'Bond Paper', 'available_quantity' => 2, 'unit' => 'boxes', 'pending_quantity' => 10]]],
        'explanation_data' => ['items' => [['item_name' => 'Bond Paper', 'available_quantity' => 2, 'unit' => 'boxes', 'pending_quantity' => 10]]],
    ]);

    expect($reply)->toContain('Bond Paper', '2 boxes', 'pending demand: 10');
});

test('a missing provider key returns local grounded facts without a request', function () {
    config()->set('services.gemini.api_key', null);
    Http::fake();

    $reply = app(AiInventoryService::class)->ask(explanationTestUser(), 'Explain low stock', [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_LOW_STOCK,
        'answer' => ['items' => [['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces']]],
        'explanation_data' => ['items' => [['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces']]],
    ]);

    expect($reply)->toContain('Laptop', '2 pieces available');
    Http::assertNothingSent();
});

test('factual intent does not call the explanation provider', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    $payload = [];
    Http::fake(function ($request) use (&$payload) {
        $payload = $request->data();
        return Http::response(geminiGenerateContentResponse('Explained.'));
    });

    $reply = app(AiInventoryService::class)->ask(explanationTestUser(), 'How many?', [
        'status' => 'success',
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'answer' => ['items' => [['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces']]],
        'explanation_data' => ['item_name' => 'Laptop', 'available_quantity' => 2, 'unit' => 'pieces'],
    ]);

    expect($reply)->toBe('Laptop; 2 pieces available.');
    Http::assertNothingSent();
});

test('intent parser accepts only the validated structured response', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    $valid = structuredAssistantParse([
        'intent' => 'maintenance',
        'response_type' => 'detail',
    ]);
    $validExplanation = [...$valid, 'intent' => 'explanation', 'explanation' => true, 'explanation_topic' => 'maintenance'];
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode($valid)))
        ->push(geminiGenerateContentResponse(json_encode($validExplanation)))
        ->push(geminiGenerateContentResponse(json_encode([...$valid, 'sql' => 'SELECT *'])))
        ->push(geminiGenerateContentResponse(json_encode([...$valid, 'intent' => 'unknown'])))
        ->push(geminiGenerateContentResponse('{malformed'))
        ->push([], 503)]);

    $parser = new AiIntentParserService(app(AiCapabilityPolicy::class), app(\App\Services\GeminiApiService::class));

    expect($parser->parse('Is the projector in repair?'))->toBe($valid)
        ->and($parser->parse('Why is the projector in repair?'))->toBe($validExplanation)
        ->and($parser->parse('Show the projector maintenance history'))->toBeNull()
        ->and($parser->parse('Show the projector maintenance history'))->toBeNull()
        ->and($parser->parse('Show the projector maintenance history'))->toBeNull()
        ->and($parser->parse('Show the projector maintenance history'))->toBeNull();
});

test('structured parser routes supported intent to current database facts', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(structuredAssistantParse())]]]]],
    ])]);
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 3);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 3 pieces available.');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'https://generativelanguage.googleapis.com/v1beta/models/')
        && ($request->data()['generationConfig']['responseFormat']['text']['mimeType'] ?? null) === 'APPLICATION_JSON'
        && ($request->data()['generationConfig']['responseFormat']['text']['schema']['additionalProperties'] ?? true) === false
        && ! str_contains(json_encode($request->data()), 'SELECT'));
});

test('invalid parser output asks for clarification without using keyword routing', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([...structuredAssistantParse(), 'sql' => 'SELECT * FROM inventory'])]]]]],
    ])]);
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 3);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which inventory item or question would you like me to check?');
});

test('parser cannot select an item name absent from the users question', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(structuredAssistantParse(['item_name' => 'Private Printer']))]]]]],
    ])]);
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Private Printer', 3);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which inventory item or question would you like me to check?');
});

test('parser provider failure asks for clarification instead of falling back to keyword routing', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);
    $user = persistedAssistantUser();

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Which inventory item or question would you like me to check?');
});

test('structured topic continuation carries only the active item to the new capability', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $locationParse = structuredAssistantParse([
        'intent' => 'location',
        'topic_action' => 'continue_topic',
        'item_name' => null,
        'response_type' => 'detail',
    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode(structuredAssistantParse())))
        ->push(geminiGenerateContentResponse(json_encode($locationParse)))]);
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 3);
    $inventory->update(['building' => 'Library', 'room' => 'Media Desk']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has 3 pieces available.');
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Library') && str_contains($reply, 'Media Desk'));
});

test('structured new topic replaces the previous item before its follow-up', function () {
    useRealAssistantIntentParser();
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $bondPaperParse = structuredAssistantParse(['item_name' => 'Bond Paper']);
    $locationParse = structuredAssistantParse([
        'intent' => 'location',
        'topic_action' => 'continue_topic',
        'item_name' => null,
        'response_type' => 'detail',
    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse(json_encode(structuredAssistantParse())))
        ->push(geminiGenerateContentResponse(json_encode($bondPaperParse)))
        ->push(geminiGenerateContentResponse(json_encode($locationParse)))]);
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 3);
    $projector->update(['building' => 'Old Wing', 'room' => '101']);
    $paper = createAssistantInventory($user, 'Bond Paper', 4);
    $paper->update(['building' => 'Supply Room', 'room' => '202']);

    $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'How many projectors are available?'])
        ->assertOk();
    $this->postJson(route('ai.chat'), ['message' => 'What about Bond Paper?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Bond Paper has 4 pieces available.');
    $this->postJson(route('ai.chat'), ['message' => 'Where is it?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'Supply Room') && str_contains($reply, '202'))
        ->assertDontSee('Old Wing');
});

test('deterministic location questions use validated room targets and database inventory', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    $inventory->update(['building' => 'Library', 'room' => 'Media Desk']);
    Http::fake();

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Which items are in the Media Desk?',
    ])->assertOk()->assertJsonPath('reply', "- Projector (Inventory ID {$inventory->item_id}): Holder: not recorded; Building: Library; Room: Media Desk; Status: available");

    Http::assertNothingSent();
});

test('unsupported questions use local role guidance without calling the provider', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 2);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);
    expect(app(InventoryQuestionRouter::class)->route('What equipment is stored in the north wing?'))->toMatchArray([
        'capability' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
        'item_name' => null,
        'location_query' => 'north wing',
    ]);

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'What equipment is stored in the north wing?',
    ])->assertOk()->assertJsonPath('reply', 'No inventory records were found at that location.');

    expect($inventory->fresh()->quantity)->toBe(2);
    Http::assertNothingSent();
});

test('lifecycle item matching asks for clarification when names are ambiguous', function () {
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 1);
    createAssistantInventory($user, 'Projector Screen', 1);
    $service = app(InventoryAnswerService::class);
    $result = $service->answer($user, [
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_ITEM_STATUS,
        'item_name' => 'Projector',
        'response_type' => 'detail',
    ]);

    expect($result['status'])->toBe('clarification')
        ->and($service->localReply($result, $user))->toContain('Projector', 'Projector Screen', 'Inventory ID');
});

test('provider failure falls back to verified maintenance facts', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([], 503)]);

    $reply = app(AiInventoryService::class)->ask(explanationTestUser(), 'Why is the projector in maintenance?', [
        'status' => 'success',
        'intent' => 'explanation',
        'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
        'answer' => ['maintenance_records' => [[
            'item_name' => 'Projector',
            'status' => 'reported',
            'issue' => 'Lens is loose',
        ]]],
        'explanation_data' => ['maintenance_records' => [['item_name' => 'Projector', 'status' => 'reported', 'issue' => 'Lens is loose']]],
    ]);

    expect($reply)->toContain('Projector', 'reported', 'Lens is loose');
});

test('maintenance facts stay local and only the explanation follow-up uses the provider', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',

    ]);
    $user = persistedAssistantUser();
    $inventory = createAssistantInventory($user, 'Projector', 1);
    MaintenanceRecord::create([
        'inventory_id' => $inventory->item_id,
        'reported_by' => $user->id,
        'status' => 'reported',
        'issue_description' => 'Lens is loose',
    ]);
    $payloads = [];
    Http::fake(function ($request) use (&$payloads) {
        $payloads[] = $request->data();

        return Http::response(geminiGenerateContentResponse('Projector has recorded maintenance status reported.'));
    });

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Is the projector under maintenance?',
    ])->assertOk()->assertJsonPath('reply', "- Maintenance: Projector; Inventory ID {$inventory->item_id}; Status: reported; Issue: Lens is loose");
    $this->postJson(route('ai.chat'), ['message' => 'Why?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Projector has recorded maintenance status reported.');

    expect($inventory->fresh()->status)->toBe('available')
        ->and($payloads)->toHaveCount(1)
        ->and($payloads[0]['contents'])->toHaveCount(1)
        ->and($payloads[0]['contents'][0]['parts'][0]['text'])->toContain('Lens is loose', 'reported')
        ->and($payloads[0]['contents'][0]['parts'][0]['text'])->not->toContain('SELECT', 'unit_cost', 'repair queue');
});

test('assignment, maintenance, disposal, ready-to-dispose, and purchase answers use database records', function () {
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 1, 'assigned');
    $ready = createAssistantInventory($user, 'Old Printer', 2, 'ready_to_dispose');
    $disposed = createAssistantInventory($user, 'Broken Scanner', 1, 'disposed');
    $purchased = createAssistantInventory($user, 'Bond Paper', 12);
    MaintenanceRecord::create([
        'inventory_id' => $projector->item_id,
        'reported_by' => $user->id,
        'status' => 'in_progress',
        'issue_description' => 'Lamp replacement',
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'item_id' => $projector->item_id,
        'quantity' => 1,
        'transaction_date' => now()->toDateString(),
        'status' => 'assigned',
        'manual_recipient_name' => 'Jordan Lee',
    ]);
    StockMovement::create([
        'inventory_id' => $disposed->item_id,
        'user_id' => $user->id,
        'movement_type' => 'disposed',
        'quantity' => 1,
        'quantity_before' => 1,
        'quantity_after' => 0,
        'notes' => 'Beyond repair',
    ]);
    StockMovement::create([
        'inventory_id' => $purchased->item_id,
        'user_id' => $user->id,
        'movement_type' => 'stock_in',
        'quantity' => 12,
        'quantity_before' => 0,
        'quantity_after' => 12,
        'notes' => 'Purchase order PO-42',
    ]);
    $service = app(InventoryAnswerService::class);

    $assignment = $service->answer($user, [
        'intent' => 'factual', 'capability' => AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        'item_name' => 'Projector', 'response_type' => 'detail',
    ]);
    $maintenance = $service->answer($user, [
        'intent' => 'factual', 'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
        'item_name' => 'Projector', 'response_type' => 'detail',
    ]);
    $disposal = $service->answer($user, [
        'intent' => 'factual', 'capability' => AiCapabilityPolicy::VIEW_DISPOSAL,
        'item_name' => 'Broken Scanner', 'response_type' => 'detail',
    ]);
    $readyToDispose = $service->answer($user, [
        'intent' => 'factual', 'capability' => AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        'item_name' => null, 'response_type' => 'list',
    ]);
    $purchaseHistory = $service->answer($user, [
        'intent' => 'factual', 'capability' => AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
        'item_name' => 'Bond Paper', 'response_type' => 'detail',
    ]);

    expect($service->localReply($assignment, $user))->toContain('Jordan Lee')
        ->and($service->localReply($maintenance, $user))->toContain('Lamp replacement')
        ->and($service->localReply($disposal, $user))->toContain('Beyond repair')
        ->and($service->localReply($readyToDispose, $user))->toContain('Old Printer', 'ready_to_dispose')
        ->and($service->localReply($purchaseHistory, $user))->toContain('PO-42');
});

function createComparisonMovement(User $user, Inventory $item, string $type, int $quantity, string $date): StockMovement
{
    $movement = StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $user->id,
        'movement_type' => $type,
        'quantity' => $quantity,
        'quantity_before' => 0,
        'quantity_after' => $quantity,
    ]);
    $movement->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

    return $movement;
}

test('comparison requests return an inline proposal with only unambiguous resolved values', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-15 12:00:00'));
    $user = persistedAssistantUser();
    $projector = createAssistantInventory($user, 'Projector', 3);

    $response = $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Compare stock-out for item "Projector" between this month and last month',
    ])->assertOk()
        ->assertJsonPath('comparison_form.metric', 'stock_out_quantity')
        ->assertJsonPath('comparison_form.scope', 'item')
        ->assertJsonPath('comparison_form.item_id', $projector->item_id)
        ->assertJsonPath('comparison_form.item_name', 'Projector')
        ->assertJsonPath('comparison_form.item_unit', 'piece')
        ->assertJsonPath('comparison_form.month_one', '2026-09')
        ->assertJsonPath('comparison_form.month_one_label', 'September 2026')
        ->assertJsonPath('comparison_form.month_two', '2026-08')
        ->assertJsonPath('comparison_form.month_two_label', 'August 2026');

    expect($response->json('comparison_form.metrics'))->toContain(['value' => 'transfer_count', 'label' => 'Transfer count']);

    $this->postJson(route('ai.chat'), ['message' => 'Compare activity between March 2025 and April 2025'])
        ->assertOk()
        ->assertJsonPath('comparison_form.metric', null)
        ->assertJsonPath('comparison_form.scope', null)
        ->assertJsonPath('comparison_form.month_one', '2025-03')
        ->assertJsonPath('comparison_form.month_two', '2025-04');
    $this->postJson(route('ai.chat'), ['message' => 'Compare requests between March 2025 and April 2025'])
        ->assertOk()
        ->assertJsonPath('comparison_form.metric', null);

    Http::assertNothingSent();
    $this->travelBack();

    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-03-31 12:00:00'));
    $this->withSession([assistantContextSessionKey($user) => ['stale' => true]])
        ->postJson(route('ai.chat'), ['message' => 'Compare stock-out between this month and last month'])
        ->assertOk()
        ->assertJsonPath('comparison_form.month_one', '2026-03')
        ->assertJsonPath('comparison_form.month_two', '2026-02');
    expect(session()->get(assistantContextSessionKey($user)))->toBeNull();
    $this->travelBack();
});

test('ambiguous exact item names require selecting a server-labeled inventory record', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $firstCategory = Category::create(['category_name' => 'Projector Equipment']);
    $secondCategory = Category::create(['category_name' => 'Presentation Equipment']);
    $first = Inventory::create([
        'category_id' => $firstCategory->category_id, 'unit' => 'piece', 'user_id' => $user->id,
        'item_name' => 'Projector', 'quantity' => 1, 'unit_cost' => 1,
        'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);
    Inventory::create([
        'category_id' => $secondCategory->category_id, 'unit' => 'box', 'user_id' => $user->id,
        'item_name' => 'Projector', 'quantity' => 1, 'unit_cost' => 1,
        'date_acquired' => '2026-01-01', 'status' => 'available',
    ]);

    $draft = $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'Compare stock-out for item "Projector" between August 2026 and September 2026',
    ])->assertOk()
        ->assertJsonPath('comparison_form.scope', 'item')
        ->assertJsonPath('comparison_form.item_name', null)
        ->assertJsonPath('comparison_form.item_id', null)
        ->json('comparison_form');

    expect($draft['item_options'])->toHaveCount(2)
        ->and($draft['item_options'][0]['label'] . $draft['item_options'][1]['label'])
        ->toContain('Projector Equipment', 'Presentation Equipment', 'Inventory ID');

    $this->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_out_quantity', 'scope' => 'item',
        'item_id' => $first->item_id, 'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->assertJsonPath('result.scope_label', 'Projector');
    $this->travelBack();
});

test('confirmed stock-out comparison uses exact movement types and returns server calculated chart data', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Projector', 3);
    createComparisonMovement($user, $item, 'stock_out', 4, '2026-08-31 23:59:59');
    createComparisonMovement($user, $item, 'assignment', 80, '2026-08-20 12:00:00');
    createComparisonMovement($user, $item, 'stock_out', 6, '2026-09-01 00:00:00');
    createComparisonMovement($user, $item, 'stock_in', 30, '2026-09-10 12:00:00');

    $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => 'stock_out_quantity',
        'scope' => 'item',
        'item_name' => 'Projector',
        'month_one' => '2026-08',
        'month_two' => '2026-09',
    ])->assertOk()
        ->assertJsonPath('result.metric_label', 'Stock-out quantity')
        ->assertJsonPath('result.scope_label', 'Projector')
        ->assertJsonPath('result.months.0.label', 'August 2026')
        ->assertJsonPath('result.months.1.label', 'September 2026')
        ->assertJsonPath('result.series.0.period_one', 4)
        ->assertJsonPath('result.series.0.period_two', 6)
        ->assertJsonPath('result.series.0.difference', 2)
        ->assertJsonPath('result.series.0.percentage_change', 50)
        ->assertJsonPath('result.chart.categories.0', 'August 2026')
        ->assertJsonPath('result.chart.statuses.0', 'activity recorded')
        ->assertJsonPath('result.chart.statuses.1', 'activity recorded')
        ->assertJsonPath('result.chart.series.0.data.0', 4)
        ->assertJsonPath('result.chart.series.0.data.1', 6);
    $this->travelBack();
});

test('confirmed comparison follow-ups explain verified zero and unavailable history from saved aggregates', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Projector', 3);
    createComparisonMovement($user, $item, 'stock_out', 1, '2026-05-10 12:00:00');

    $compare = fn (string $metric) => $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => $metric,
        'scope' => 'item',
        'item_name' => 'Projector',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    $compare('stock_out_quantity')->assertJsonPath('result.series.0.period_one_status', 'verified_zero');
    $stored = session()->get(assistantComparisonContextSessionKey($user));
    expect($stored['context'])->toHaveKeys(['metric', 'metric_label', 'scope_label', 'months', 'series'])
        ->and($stored['context'])->not->toHaveKey('chart')
        ->and($stored['context'])->not->toHaveKey('inventory_id')
        ->and($stored['context']['series'][0]['period_one'])->toBe(0);

    $this->postJson(route('ai.chat'), ['message' => 'Does that mean there was no activity in June and July?'])
        ->assertOk()
        ->assertJsonPath('reply', 'Yes. The comparison confirms no Stock-out quantity activity for Projector in June 2026 or July 2026. Both periods have verified zero activity.');

    $compare('maintenance_count')->assertJsonPath('result.series.0.period_one_status', 'unavailable');
    $this->postJson(route('ai.chat'), ['message' => 'Does that mean there was no activity in June and July?'])
        ->assertOk()
        ->assertJsonPath('reply', 'I cannot confirm that there was no activity in both periods for Completed maintenance count on Projector. June 2026: history unavailable, so activity cannot be confirmed; July 2026: history unavailable, so activity cannot be confirmed.');

    $this->postJson(route('ai.chat'), ['message' => 'Does that mean there was no activity in May and July?'])
        ->assertOk()
        ->assertJsonPath('reply', 'That question mentions different months from the latest confirmed comparison. Please name a new comparison to check those periods.');
    expect(session()->get(assistantComparisonContextSessionKey($user)))->toBeNull();

    $this->travelBack();
});

test('system summary is denied to custodians and clears comparison context', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => 'request_count',
        'scope' => 'all',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    $this->postJson(route('ai.chat'), ['message' => 'Show a system summary'])
        ->assertOk()
        ->assertJsonPath('reply', 'That information is not available for your role.')
        ->assertJsonMissingPath('comparison_form');
    expect(session()->get(assistantComparisonContextSessionKey($user)))->toBeNull();

    $this->postJson(route('ai.chat'), ['message' => 'Does that mean there was no activity in June and July?'])
        ->assertOk()
        ->assertJsonMissing(['reply' => 'Yes. The comparison confirms no Request count activity for All inventory in June 2026 or July 2026. Both periods have verified zero activity.']);
    $this->travelBack();
});

test('system summary formatter returns readable text without raw JSON', function () {
    $reply = app(InventoryAnswerService::class)->localReply([
        'status' => 'success',
        'answer' => ['registered_users' => 3, 'inventory_records' => 8, 'transactions_logged' => 12],
    ]);

    expect($reply)->toBe('System summary: 3 registered users, 8 inventory records, and 12 logged transactions.')
        ->not->toStartWith('{');
});

test('comparison context is cleared by reset and expiry', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $payload = [
        'confirmed' => true,
        'metric' => 'request_count',
        'scope' => 'all',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ];

    $this->actingAs($user)->postJson(route('ai.comparison'), $payload)->assertOk();
    expect(session()->get(assistantComparisonContextSessionKey($user))['expires_at'])->toBeGreaterThan(now()->timestamp);
    $this->postJson(route('ai.chat.reset'))->assertOk();
    expect(session()->get(assistantComparisonContextSessionKey($user)))->toBeNull();

    $this->postJson(route('ai.comparison'), $payload)->assertOk();
    $this->travel(16)->minutes();
    $this->postJson(route('ai.chat'), ['message' => 'Does that mean there was no activity in June and July?'])
        ->assertOk()
        ->assertJsonMissing(['reply' => 'Yes. The comparison confirms no Request count activity for All inventory in June 2026 or July 2026. Both periods have verified zero activity.']);
    expect(session()->get(assistantComparisonContextSessionKey($user)))->toBeNull();
    $this->travelBack();
});

test('Gemini explains only the server-calculated comparison trend without receiving totals', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    $providerInput = '';
    Http::fake(function ($request) use (&$providerInput) {
        $providerInput = $request->data()['contents'][0]['parts'][0]['text'];

        return Http::response(geminiGenerateContentResponse("Stock-out quantity for Projector\nAugust 2026: activity recorded\nSeptember 2026: activity recorded\n\nPercentage change is shown in the table."));
    });
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Projector', 3);
    createComparisonMovement($user, $item, 'stock_out', 4, '2026-08-10 12:00:00');
    createComparisonMovement($user, $item, 'stock_out', 6, '2026-09-10 12:00:00');

    $result = $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_out_quantity', 'scope' => 'item', 'item_name' => 'Projector',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->json('result');
    $providerInputWithoutDates = str_replace(['August 2026', 'September 2026'], '', $providerInput);

    expect($result['explanation'])->toBe("Stock-out quantity for Projector\nAugust 2026: activity recorded\nSeptember 2026: activity recorded\n\nPercentage change is shown in the table.")
        ->and($result['series'][0]['period_one'])->toBe(4)
        ->and($result['series'][0]['period_two'])->toBe(6)
        ->and($result['chart']['series'][0]['data'])->toBe([4, 6])
        ->and($providerInput)->toContain('"period_one_status":"available"', '"period_one_label":"August 2026"', '"period_two_label":"September 2026"')
        ->and($providerInput)->not->toContain('Projector', '"period_one":4', '"period_two":6', 'chart')
        ->and(preg_match('/\d/', $providerInputWithoutDates))->toBe(0);
    Http::assertSentCount(1);
    $this->travelBack();
});

test('Gemini explains unavailable comparison history and unsafe numeric output falls back locally', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
        ->push(geminiGenerateContentResponse("Stock-out quantity for Projector\nAugust 2026: history unavailable\nSeptember 2026: history unavailable\n\nPercentage change is unavailable because one or both period totals are unavailable."))
        ->push(geminiGenerateContentResponse('Stock-out activity increased by 99 units.'))]);
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 3);
    $payload = [
        'confirmed' => true, 'metric' => 'stock_out_quantity', 'scope' => 'item', 'item_name' => 'Projector',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ];

    $first = $this->actingAs($user)->postJson(route('ai.comparison'), $payload)
        ->assertOk()
        ->assertJsonPath('result.series.0.period_one_status', 'unavailable')
        ->json('result.explanation');
    $second = $this->postJson(route('ai.comparison'), $payload)->assertOk()->json('result');

    expect($first)->toBe("Stock-out quantity for Projector\nAugust 2026: history unavailable\nSeptember 2026: history unavailable\n\nPercentage change is unavailable because one or both period totals are unavailable.")
        ->and($second['explanation'])->toContain('August 2026: history unavailable', 'September 2026: history unavailable')
        ->and($second['series'][0]['period_one'])->toBeNull()
        ->and($second['series'][0]['period_two'])->toBeNull();
    Http::assertSentCount(2);
    $this->travelBack();
});

test('Gemini explains a zero denominator without inventing a percentage', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response(
        geminiGenerateContentResponse("Stock-in quantity for Projector\nAugust 2026: verified zero activity\nSeptember 2026: activity recorded\n\nPercentage change is unavailable because the August 2026 total is zero.")
    )]);
    $user = persistedAssistantUser();
    $item = createAssistantInventory($user, 'Projector', 3);
    createComparisonMovement($user, $item, 'stock_in', 5, '2026-07-10 12:00:00');
    createComparisonMovement($user, $item, 'stock_in', 2, '2026-09-10 12:00:00');

    $result = $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_in_quantity', 'scope' => 'item', 'item_name' => 'Projector',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->json('result');

    expect($result['explanation'])->toBe("Stock-in quantity for Projector\nAugust 2026: verified zero activity\nSeptember 2026: activity recorded\n\nPercentage change is unavailable because the August 2026 total is zero.")
        ->and($result['series'][0]['period_one'])->toBe(0)
        ->and($result['series'][0]['period_two'])->toBe(2)
        ->and($result['series'][0]['percentage_change'])->toBeNull();
    Http::assertSentCount(1);
    $this->travelBack();
});

test('comparison confirmation and input constraints are validated before aggregation', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    createAssistantInventory($user, 'Projector', 3);
    $base = [
        'metric' => 'stock_in_quantity',
        'scope' => 'all',
        'unit' => 'piece',
        'month_one' => '2026-08',
        'month_two' => '2026-09',
    ];

    $this->actingAs($user)->postJson(route('ai.comparison'), ['confirmed' => false, ...$base])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirmed');
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$base, 'metric' => 'sql'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('metric');
    $withoutUnit = $base;
    unset($withoutUnit['unit']);
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$withoutUnit])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit');
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$base, 'unit' => 'not-a-unit'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit');
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$base, 'month_two' => '2026-08'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('month_two');
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$base, 'scope' => 'item'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_name');
    $this->postJson(route('ai.comparison'), ['confirmed' => true, ...$base, 'scope' => 'item', 'item_name' => 'missing'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_name');
    $this->postJson(route('ai.comparison'), [
        'confirmed' => true, ...$base, 'month_two' => now()->addYear()->format('Y-m'),
    ])->assertUnprocessable()->assertJsonValidationErrors('month_two');
    $this->postJson(route('ai.comparison'), [
        'confirmed' => true, ...$base, 'month_two' => now()->format('Y-m'),
    ])->assertUnprocessable()->assertJsonValidationErrors('month_two');
    $monitor = createAssistantInventory($user, 'Monitor', 2);
    $this->postJson(route('ai.comparison'), [
        'confirmed' => true, ...$base, 'scope' => 'item', 'item_name' => $monitor->item_name, 'unit' => 'box',
    ])->assertUnprocessable()->assertJsonValidationErrors('unit');
    createAssistantInventory($user, 'Projector', 3);
    $this->postJson(route('ai.comparison'), [
        'confirmed' => true, ...$base, 'scope' => 'item', 'item_name' => 'Projector',
    ])->assertUnprocessable()->assertJsonValidationErrors('item_name');
    $this->travelBack();
});

test('request, assignment, transfer, maintenance, and disposal comparisons use separate canonical sources', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $recipient = persistedAssistantUser('End User');
    $item = createAssistantInventory($user, 'Projector', 3);
    $request = fn (string $date, int $quantity) => AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $user->id,
        'target_user_id' => $recipient->id,
        'quantity' => $quantity,
        'status' => 'waiting for approval',
        'requested_at' => $date,
        'created_at' => $date,
        'updated_at' => $date,
    ]);
    $augustRequest = $request('2026-08-04 10:00:00', 2);
    $request('2026-09-04 10:00:00', 5);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $user->id,
        'target_user_id' => $recipient->id,
        'parent_request_id' => $augustRequest->id,
        'quantity' => 99,
        'status' => 'waiting for approval',
        'requested_at' => '2026-08-05 10:00:00',
    ]);

    Transaction::create([
        'user_id' => $recipient->id, 'item_id' => $item->item_id, 'quantity' => 2,
        'issued_quantity' => 30, 'transaction_date' => '2026-08-08', 'status' => 'assigned',
    ]);
    Transaction::create([
        'user_id' => $recipient->id, 'from_user_id' => $user->id, 'item_id' => $item->item_id,
        'quantity' => 4, 'issued_quantity' => 40, 'transaction_date' => '2026-09-08', 'status' => 'assigned',
    ]);
    createComparisonMovement($user, $item, 'assignment', 3, '2026-08-08 12:00:00');
    createComparisonMovement($user, $item, 'transfer', 4, '2026-09-08 12:00:00');
    MaintenanceRecord::create([
        'inventory_id' => $item->item_id, 'reported_by' => $user->id, 'status' => 'completed',
        'completed_at' => '2026-09-10 10:00:00',
    ]);
    createComparisonMovement($user, $item, 'disposed', 7, '2026-09-12 10:00:00');

    $compare = function (string $metric) use ($user) {
        return test()->actingAs($user)->postJson(route('ai.comparison'), [
            'confirmed' => true,
            'metric' => $metric,
            'scope' => 'all',
            'unit' => str_ends_with($metric, '_quantity') ? 'piece' : null,
            'month_one' => '2026-08',
            'month_two' => '2026-09',
        ])->assertOk()->json('result.series.0');
    };

    expect($compare('request_count'))
        ->toMatchArray(['period_one' => 1, 'period_two' => 1, 'unit' => 'Records'])
        ->and($compare('request_quantity'))
        ->toMatchArray(['period_one' => 2, 'period_two' => 5, 'unit' => 'piece'])
        ->and($compare('assignment_count'))
        ->toMatchArray(['period_one' => 1, 'period_two' => 0, 'unit' => 'Records'])
        ->and($compare('assignment_quantity'))
        ->toMatchArray(['period_one' => 3, 'period_two' => 0, 'unit' => 'piece'])
        ->and($compare('transfer_count'))
        ->toMatchArray(['period_one' => 0, 'period_two' => 1, 'unit' => 'Records'])
        ->and($compare('transfer_quantity'))
        ->toMatchArray(['period_one' => 0, 'period_two' => 4, 'unit' => 'piece'])
        ->and($compare('maintenance_count'))
        ->toMatchArray(['period_one' => 0, 'period_two' => 1, 'unit' => 'Records'])
        ->and($compare('disposal_quantity'))
        ->toMatchArray(['period_one' => 0, 'period_two' => 7, 'unit' => 'piece']);
    $this->travelBack();
});

test('incompatible units stay separate and zero or unavailable history is explicit', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $piece = createAssistantInventory($user, 'Projector', 3);
    $boxCategory = Category::create(['category_name' => 'Comparison Boxes']);
    $box = Inventory::create([
        'category_id' => $boxCategory->category_id, 'unit' => 'box', 'user_id' => $user->id,
        'item_name' => 'Paper', 'quantity' => 1, 'unit_cost' => 1,
        'date_acquired' => now()->toDateString(), 'status' => 'available',
    ]);
    createComparisonMovement($user, $piece, 'stock_in', 5, '2026-07-10 10:00:00');
    createComparisonMovement($user, $piece, 'stock_in', 2, '2026-09-10 10:00:00');
    createComparisonMovement($user, $box, 'stock_in', 9, '2026-08-10 10:00:00');

    $compareUnit = fn (string $unit) => $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_in_quantity', 'scope' => 'all', 'unit' => $unit,
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->json('result');
    $boxResult = $compareUnit('box');
    $pieceResult = $compareUnit('piece');

    expect($boxResult['series'])->toHaveCount(1)
        ->and($boxResult['series'][0])
        ->toMatchArray(['unit' => 'box', 'period_one' => 9, 'period_two' => 0, 'period_two_status' => 'verified_zero'])
        ->and($boxResult['chart']['statuses'])->toBe(['activity recorded', 'verified zero activity'])
        ->and($boxResult['explanation'])->toContain("Stock-in quantity for All inventory\nAugust 2026: activity recorded\nSeptember 2026: verified zero activity")
        ->and($pieceResult['series'])->toHaveCount(1)
        ->and($pieceResult['series'][0])
        ->toMatchArray(['unit' => 'piece', 'period_one' => 0, 'period_two' => 2, 'period_one_status' => 'verified_zero', 'percentage_change' => null, 'percentage_change_status' => 'zero_denominator'])
        ->and($pieceResult['explanation'])->toContain("August 2026: verified zero activity\nSeptember 2026: activity recorded\n\nPercentage change is unavailable because the August 2026 total is zero.");

    $unavailable = $this->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_out_quantity', 'scope' => 'item', 'item_name' => 'Projector',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->json('result.series.0');
    expect($unavailable)->toMatchArray([
        'period_one' => null,
        'period_two' => null,
        'period_one_status' => 'unavailable',
        'period_two_status' => 'unavailable',
        'difference' => null,
        'percentage_change' => null,
    ]);
    expect($this->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_out_quantity', 'scope' => 'item', 'item_name' => 'Projector',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->json('result.explanation'))->toContain("August 2026: history unavailable\nSeptember 2026: history unavailable\n\nPercentage change is unavailable because one or both period totals are unavailable.");

    $markup = view('components.header.ai-inventory-assistant')->render();
    expect($markup)->toContain('Confirm and calculate', 'aria-hidden="true"', 'Comparison totals and changes for each unit', 'AI insight', 'message.comparisonResult.explanation', '<caption class="sr-only">');
    $tableEnd = strpos($markup, '</table>', strpos($markup, 'Comparison totals and changes for each unit'));
    $explanation = strpos($markup, 'message.comparisonResult.explanation');
    expect($explanation)->toBeGreaterThan($tableEnd);
    $this->travelBack();
});

test('broad quantity comparisons return one validated unit series', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    $user = persistedAssistantUser();
    $category = Category::create(['category_name' => 'Comparison Unit Limits']);
    for ($index = 0; $index < 13; $index++) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'unit-' . $index,
            'user_id' => $user->id,
            'item_name' => 'Unit Item ' . $index,
            'quantity' => 1,
            'unit_cost' => 1,
            'date_acquired' => now()->toDateString(),
            'status' => 'available',
        ]);
    }

    $this->actingAs($user)->postJson(route('ai.comparison'), [
        'confirmed' => true, 'metric' => 'stock_in_quantity', 'scope' => 'all', 'unit' => 'unit-12',
        'month_one' => '2026-08', 'month_two' => '2026-09',
    ])->assertOk()->assertJsonCount(1, 'result.series');
    $this->travelBack();
});
