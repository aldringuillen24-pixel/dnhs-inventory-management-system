<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
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
});

function localFactsUser(string $roleName): User
{
    $role = Role::firstOrCreate(['role_name' => $roleName]);

    return User::factory()->create(['role_id' => $role->role_id]);
}

function localFactsInventory(Category $category, array $attributes): Inventory
{
    return Inventory::create(array_merge([
        'category_id' => $category->category_id,
        'user_id' => User::query()->firstOrFail()->id,
        'unit' => 'boxes',
        'item_name' => 'Bond Paper',
        'quantity' => 1,
        'date_acquired' => '2026-01-01',
        'status' => 'available',
    ], $attributes));
}

function localFactsAnswer(User $user, string $question): array
{
    $router = app(InventoryQuestionRouter::class);

    return app(InventoryAnswerService::class)->answer($user, $router->route($question));
}

test('how many item available returns the exact case-insensitive total and unit', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Bond Paper', 'quantity' => 7]);
    localFactsInventory($category, ['item_name' => 'bond paper', 'quantity' => 5]);
    localFactsInventory($category, ['item_name' => 'Bond Paper', 'quantity' => 9, 'status' => 'assigned']);
    localFactsInventory($category, ['item_name' => 'Bond Paper', 'quantity' => 20, 'status' => 'disposed']);

    $routed = app(InventoryQuestionRouter::class)->route('How many BOND PAPER boxes are available?');
    $result = app(InventoryAnswerService::class)->answer($user, $routed);

    expect($routed['capability'])->toBe(\App\Services\AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY)
        ->and($result['status'])->toBe('success')
        ->and($result['answer'])->toMatchArray([
            'item_name' => 'Bond Paper',
                'available_count' => 12,
                'item_types' => 1,
            'unit' => 'boxes',
        ]);
        expect(app(InventoryAnswerService::class)->localReply($result, $user))->toBe('Bond Paper has 12 boxes available.');
    Http::assertNothingSent();
});

test('factual chat returns exact database facts after structured parsing without answer generation', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'intent' => 'availability',
            'topic_action' => 'new_topic',
            'item_name' => 'Bond Paper',
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
        ])]]]]],
    ])]);
    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Bond Paper', 'quantity' => 6]);

    $this->actingAs($user)->postJson(route('ai.chat'), [
        'message' => 'How many Bond Paper are available?',
    ])->assertOk()->assertJsonPath('reply', 'Bond Paper has 6 boxes available.');

    Http::assertSentCount(1);
});

test('warehouse availability denies end users before querying inventory', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    $user = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Private Paper', 'quantity' => 12]);
    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (str_contains(strtolower($query->sql), 'from "inventory"')) {
            $inventoryQueries++;
        }
    });

    $routed = app(InventoryQuestionRouter::class)->route('How many Private Paper are available?');
    $result = app(InventoryAnswerService::class)->answer($user, $routed);

    expect($result['status'])->toBe('forbidden')
        ->and(app(InventoryAnswerService::class)->localReply($result, $user))->toBe('That information is not available for your role.')
        ->and($inventoryQueries)->toBe(0);
});

test('warehouse availability reflects database changes when the same request is answered again', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, ['item_name' => 'Projector', 'quantity' => 4]);
    $routed = app(InventoryQuestionRouter::class)->route('How many Projector are available?');
    $service = app(InventoryAnswerService::class);
    $initial = $service->answer($user, $routed);

    $item->update(['quantity' => 9]);
    $updated = $service->answer($user, $routed);

    expect($initial['answer']['available_count'])->toBe(4)
        ->and($updated['answer']['available_count'])->toBe(9)
        ->and($service->localReply($updated, $user))->toBe('Projector has 9 boxes available.');
});

test('specific warehouse availability returns only minimal current database facts', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    }

    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, [
        'item_name' => 'Projector',
        'quantity' => 4,
        'description' => 'Internal receiving note not needed for availability.',
    ]);
    $inventoryQueries = [];
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (str_contains(strtolower($query->sql), 'from "inventory"')) {
            $inventoryQueries[] = strtolower($query->sql);
        }
    });
    $routed = app(InventoryQuestionRouter::class)->route('Do we still have a projector?');
    $result = app(InventoryAnswerService::class)->answer($user, $routed);

    expect($routed['capability'])->toBe(\App\Services\AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY)
        ->and($result['status'])->toBe('success')
        ->and($result['answer']['item_details'])->toEqual([
            'item_name' => 'Projector',
            'quantity' => 4,
            'available_quantity' => 4,
            'assigned_quantity' => 0,
            'inventory_ids' => [$item->item_id],
            'unit' => 'boxes',
            'stock_status' => 'Low Stock',
            'calculated_at' => $result['answer']['item_details']['calculated_at'],
            'discrepancies' => [],
        ])
        ->and($inventoryQueries)->not->toBeEmpty()
        ->and(collect($inventoryQueries)->every(fn (string $sql): bool => ! str_contains($sql, 'description')))->toBeTrue();
});

test('missing and zero-stock items return safe exact factual results', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Empty Folder', 'quantity' => 4, 'status' => 'assigned']);

    $zero = localFactsAnswer($user, 'How many Empty Folder are available?');
    $missing = localFactsAnswer($user, 'How many Staplers are available?');

    expect($zero['status'])->toBe('success')
            ->and($zero['answer']['available_count'])->toBe(0)
        ->and($missing['status'])->toBe('not_found');
    expect(app(InventoryAnswerService::class)->localReply($missing))->toBe('That item was not found in inventory.');
    Http::assertNothingSent();
});

test('inventory details calculate assigned stock from active transactions', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, [
        'item_name' => 'Assigned Projector',
        'quantity' => 0,
        'status' => 'assigned',
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'item_id' => $item->item_id,
        'quantity' => 3,
        'issued_quantity' => 3,
        'status' => 'assigned',
        'transaction_date' => now(),
    ]);

    $result = app(InventoryAnswerService::class)->answer($user, [
        'intent' => 'factual',
        'capability' => \App\Services\AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
        'item_name' => 'Assigned Projector',
        'response_type' => 'detail',
    ]);

    expect($result['answer']['item_details'])->toMatchArray([
        'quantity' => 3,
        'available_quantity' => 0,
        'assigned_quantity' => 3,
        'inventory_ids' => [$item->item_id],
    ]);
});

test('inventory details report conflicting stored and transaction quantities', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, [
        'item_name' => 'Conflicting Projector',
        'quantity' => 2,
        'status' => 'assigned',
    ]);
    Transaction::create([
        'user_id' => $user->id,
        'item_id' => $item->item_id,
        'quantity' => 5,
        'issued_quantity' => 5,
        'status' => 'assigned',
        'transaction_date' => now(),
    ]);

    $result = localFactsAnswer($user, 'Do we still have a Conflicting Projector?');

    expect($result['answer']['item_details']['discrepancies'])
        ->toContain('stored assigned quantity is 2 but active transactions calculate 5')
        ->and(app(InventoryAnswerService::class)->localReply($result))
        ->toContain('Discrepancy: stored assigned quantity is 2 but active transactions calculate 5.');
});

test('what items are available groups items and returns deterministic stock statuses', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Zebra Paper', 'quantity' => 2]);
    localFactsInventory($category, ['item_name' => 'bond paper', 'quantity' => 4]);
    localFactsInventory($category, ['item_name' => 'Bond Paper', 'quantity' => 3]);
    localFactsInventory($category, ['item_name' => 'Zero Stock', 'quantity' => 0]);
    localFactsInventory($category, ['item_name' => 'Disposed Item', 'quantity' => 10, 'status' => 'disposed']);

    $result = localFactsAnswer($user, 'What items are available?');

    expect($result['answer']['items'])->toHaveCount(2)
        ->and($result['answer']['items'][0])->toMatchArray([
            'item_name' => 'bond paper',
            'available_quantity' => 7,
            'stock_status' => 'In Stock',
        ])
        ->and($result['answer']['items'][1])->toMatchArray([
            'item_name' => 'Zebra Paper',
            'available_quantity' => 2,
            'stock_status' => 'Low Stock',
        ]);
    Http::assertNothingSent();
});

test('assigned items only include the authenticated end user items', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = localFactsUser('Administrator');
    $other = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Equipment']);
    localFactsInventory($category, ['item_name' => 'My Laptop', 'quantity' => 1, 'status' => 'assigned', 'assigned_to_user_id' => $user->id, 'inventory_item_no' => 'MY-001']);
    localFactsInventory($category, ['item_name' => 'Other Laptop', 'quantity' => 1, 'status' => 'assigned', 'assigned_to_user_id' => $other->id, 'inventory_item_no' => 'OTHER-001']);

    $result = localFactsAnswer($user, 'What is assigned to me?');

    expect($result['answer']['assigned_items'])->toHaveCount(1)
        ->and($result['answer']['assigned_items'][0])->toMatchArray([
            'item_name' => 'My Laptop',
            'inventory_no' => 'MY-001',
            'status' => 'assigned',
        ]);
    expect(json_encode($result))->not->toContain('Other Laptop');
    Http::assertNothingSent();
});

test('my pending requests count only the authenticated user pending statuses', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $user = localFactsUser('Administrator');
    $other = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Supplies']);
    $item = localFactsInventory($category, ['item_name' => 'Bond Paper']);
    $createRequest = fn (User $requester, string $status): AssignmentRequest => AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $requester->id,
        'target_user_id' => $requester->id,
        'quantity' => 1,
        'status' => $status,
    ]);
    $createRequest($user, 'waiting for approval');
    $createRequest($user, 'waiting for custodian approval');
    $createRequest($user, 'approved');
    $createRequest($other, 'waiting for approval');

    $result = localFactsAnswer($user, 'How many of my requests are pending?');

    expect($result['answer']['pending_count'])->toBe(2)
        ->and(app(InventoryAnswerService::class)->localReply($result))->toBe('There are 2 pending item requests.');
    Http::assertNothingSent();
});

test('low stock is allowed for property custodians but denied for end users', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $custodian = localFactsUser('Property Custodian');
    $endUser = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Supplies']);
    localFactsInventory($category, ['item_name' => 'Low Paper', 'quantity' => 3]);
    localFactsInventory($category, ['item_name' => 'Healthy Paper', 'quantity' => 6]);

    $allowed = localFactsAnswer($custodian, 'Which items are low in stock?');
    $denied = localFactsAnswer($endUser, 'Which items are low in stock?');

    $lowPaper = collect($allowed['answer']['items'] ?? [])->firstWhere('item_name', 'Low Paper');

    expect($allowed['status'])->toBe('success')
        ->and($lowPaper)->toMatchArray([
            'item_name' => 'Low Paper',
            'available_quantity' => 3,
            'unit' => 'boxes',
            'stock_status' => 'Low Stock',
        ])
        ->and($denied['status'])->toBe('forbidden')
        ->and(app(InventoryAnswerService::class)->localReply($denied))->toBe('That information is not available for your role.');
    Http::assertNothingSent();
});

test('location questions return exact inventory, holder, location, and status from the database', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $custodian = localFactsUser('Property Custodian');
    $holder = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, [
        'item_name' => 'Laptop',
        'building' => 'Main Building',
        'room' => 'Room 203',
        'status' => 'assigned',
        'assigned_to_user_id' => $holder->id,
    ]);
    localFactsInventory($category, [
        'item_name' => 'Projector',
        'building' => 'Library',
        'room' => 'Media Desk',
    ]);

    $result = localFactsAnswer($custodian, 'Where is the laptop?');
    $roomResult = localFactsAnswer($custodian, 'What is assigned to Room 203?');
    $libraryResult = localFactsAnswer($custodian, 'Which items are in the library?');

    expect($result['status'])->toBe('success')
        ->and($result['answer']['location_items'][0])->toMatchArray([
            'inventory_id' => $item->item_id,
            'item_name' => 'Laptop',
            'holder' => $holder->full_name,
            'building' => 'Main Building',
            'room' => 'Room 203',
            'status' => 'assigned',
        ])
        ->and($roomResult['answer']['location_items'])->toHaveCount(1)
        ->and($libraryResult['answer']['location_items'][0]['item_name'])->toBe('Projector');
    Http::assertNothingSent();
});

test('location questions disclose missing fields and clarify duplicate exact item names', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $custodian = localFactsUser('Property Custodian');
    $category = Category::create(['category_name' => 'Equipment']);
    localFactsInventory($category, ['item_name' => 'Projector']);
    localFactsInventory($category, ['item_name' => 'Laptop', 'room' => '203']);
    localFactsInventory($category, ['item_name' => 'Laptop', 'room' => '204']);

    $missing = localFactsAnswer($custodian, 'Where is the projector?');
    $ambiguous = localFactsAnswer($custodian, 'Where is the laptop?');

    expect(app(InventoryAnswerService::class)->localReply($missing))
        ->toContain('Building: not recorded', 'Room: not recorded')
        ->and($ambiguous['status'])->toBe('clarification')
        ->and($ambiguous['answer']['clarification_candidates'])->toHaveCount(2);
    Http::assertNothingSent();
});

test('location answers list all active holders for pooled inventory records', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $custodian = localFactsUser('Property Custodian');
    $firstHolder = localFactsUser('End User');
    $secondHolder = localFactsUser('End User');
    $category = Category::create(['category_name' => 'Equipment']);
    $item = localFactsInventory($category, [
        'item_name' => 'Projector',
        'quantity' => 2,
        'status' => 'assigned',
        'assigned_to_user_id' => $firstHolder->id,
    ]);
    foreach ([$firstHolder, $secondHolder] as $holder) {
        Transaction::create([
            'item_id' => $item->item_id,
            'user_id' => $holder->id,
            'quantity' => 1,
            'status' => 'assigned',
            'transaction_date' => now(),
        ]);
    }

    $result = localFactsAnswer($custodian, 'Who has the projector?');

    expect($result['answer']['location_items'][0]['holder'])
        ->toBe($firstHolder->full_name . ', ' . $secondHolder->full_name);
    Http::assertNothingSent();
});

test('end users cannot query other inventory locations or holders', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    Http::fake();
    $endUser = localFactsUser('End User');

    $result = localFactsAnswer($endUser, 'Who has the projector?');

    expect($result['status'])->toBe('forbidden');
    Http::assertNothingSent();
});
