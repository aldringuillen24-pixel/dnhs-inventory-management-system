<?php

use App\Models\AiApiKey;
use App\Models\AssignmentRequest;
use App\Models\AssignmentReturn;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\GeminiApiService;
use App\Services\InventoryAnswerService;
use App\Services\Response\AnswerComposer;
use App\Services\Tools\AssignmentTool;
use App\Services\Tools\CompoundPlanExecutor;
use App\Services\Tools\DisposalTool;
use App\Services\Tools\HistoryTool;
use App\Services\Tools\InventoryTool;
use App\Services\Tools\MaintenanceTool;
use App\Services\Tools\ReturnTool;
use App\Services\Tools\TransferTool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $adminRole = Role::firstOrCreate(['role_name' => 'Administrator']);
    $endUserRole = Role::firstOrCreate(['role_name' => 'End User']);

    $this->admin = User::create([
        'role_id' => $adminRole->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'keypool-admin',
        'email' => 'keypool-admin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'keypool-enduser',
        'email' => 'keypool-enduser@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    config()->set('services.gemini.api_key', null);
});

function keypoolKey(int $n, array $overrides = []): AiApiKey
{
    return AiApiKey::create([
        'label' => "key-{$n}",
        'key_encrypted' => AiApiKey::encryptKey("test-gemini-key-material-{$n}-padding"),
        'is_active' => true,
        'priority' => $n,
        ...$overrides,
    ]);
}

function keypoolSuccessPayload(string $text = 'hello'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

test('privacy: Gemini receives allowed inventory facts but not private fields', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            keypoolSuccessPayload('Desk Lamp has 3 units available.')
        ),
    ]);

    $facts = [
        'items' => [[
            'item_name' => 'Desk Lamp',
            'quantity' => 4,
            'available_quantity' => 3,
            'assigned_quantity' => 1,
            'unit' => 'units',
            'stock_status' => 'In Stock',
            'description' => 'PRIVATE_DESCRIPTION_482',
            'serial_number' => 'PRIVATE_SERIAL_913',
            'recipient_name' => 'PRIVATE_PERSON_275',
        ]],
    ];

    $reply = app(AnswerComposer::class)->compose(
        $this->admin,
        'How many Desk Lamps are available?',
        $facts,
        'Desk Lamp has 3 units available.',
        AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        AnswerComposer::MODE_BOUNDED,
    );

    expect($reply)->toBe('Desk Lamp has 3 units available.');

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($input, '"available_quantity": 3')
            && str_contains($input, '"stock_status": "In Stock"')
            && ! str_contains($input, 'PRIVATE_DESCRIPTION_482')
            && ! str_contains($input, 'PRIVATE_SERIAL_913')
            && ! str_contains($input, 'PRIVATE_PERSON_275');
    });
});

test('privacy: local fallback is used without sending a Gemini request', function () {
    config()->set('services.gemini.api_key', null);
    Http::fake();

    $fallback = 'Desk Lamp has 3 units available.';
    $reply = app(AnswerComposer::class)->compose(
        $this->admin,
        'How many Desk Lamps are available?',
        ['available_quantity' => 3, 'item_name' => 'Desk Lamp'],
        $fallback,
        AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
        AnswerComposer::MODE_BOUNDED,
    );

    expect($reply)->toBe($fallback);
    Http::assertNothingSent();
});

test('keypool admin can save five keys but the sixth is rejected', function () {
    foreach (range(1, 5) as $n) {
        $this->actingAs($this->admin)
            ->postJson(route('api.admin.settings.keys.store'), [
                'label' => "key-{$n}",
                'key' => "test-gemini-key-material-{$n}-padding",
            ])
            ->assertCreated()
            ->assertJsonPath('key.label', "key-{$n}")
            ->assertJsonPath('key.masked_key', '…ding');
    }

    $this->actingAs($this->admin)
        ->postJson(route('api.admin.settings.keys.store'), [
            'label' => 'key-6',
            'key' => 'test-gemini-key-material-6-padding',
        ])
        ->assertUnprocessable();

    expect(AiApiKey::count())->toBe(5);
});

test('keypool key responses never expose full key material', function () {
    $full = 'test-gemini-key-material-1-padding';

    $store = $this->actingAs($this->admin)
        ->postJson(route('api.admin.settings.keys.store'), ['label' => 'key-1', 'key' => $full])
        ->assertCreated();

    $store->assertDontSee($full);
    expect($store->json('key.masked_key'))->toBe('…ding');

    $this->actingAs($this->admin)
        ->getJson(route('api.admin.settings.keys.index'))
        ->assertOk()
        ->assertDontSee($full);

    // Encrypted at rest: stored value decrypts back but is not plaintext.
    $stored = AiApiKey::sole()->key_encrypted;
    expect($stored)->not->toBe($full)
        ->and((new AiApiKey(['key_encrypted' => $stored]))->decryptedKey())->toBe($full);
});

test('keypool generate fails over to the next key after a 429', function () {
    keypoolKey(1, ['priority' => 0]);
    keypoolKey(2, ['priority' => 1]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushStatus(429)
            ->push(keypoolSuccessPayload('second-key-answer')),
    ]);

    $text = app(GeminiApiService::class)->generate('sys', 'input');

    expect($text)->toBe('second-key-answer')
        ->and(AiApiKey::where('label', 'key-1')->sole()->last_error)->toBe('HTTP 429')
        ->and(AiApiKey::where('label', 'key-2')->sole()->last_error)->toBeNull()
        ->and(AiApiKey::where('label', 'key-2')->sole()->last_used_at)->not->toBeNull();
});

test('keypool generate returns null for local fallback when all keys fail', function () {
    keypoolKey(1);
    keypoolKey(2);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

    expect(app(GeminiApiService::class)->generate('sys', 'input'))->toBeNull();
});

test('keypool zero keys keeps env single-key behavior', function () {
    config()->set('services.gemini.api_key', 'env-seed-key-material-padding');

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('env-answer'))]);

    expect(app(GeminiApiService::class)->generate('sys', 'input'))->toBe('env-answer');

    config()->set('services.gemini.api_key', null);

    expect(app(GeminiApiService::class)->generate('sys', 'input'))->toBeNull();
});

test('keypool non-administrator cannot manage keys', function () {
    $key = keypoolKey(1);

    $this->actingAs($this->endUser)
        ->getJson(route('api.admin.settings.keys.index'))
        ->assertForbidden();

    $this->actingAs($this->endUser)
        ->postJson(route('api.admin.settings.keys.store'), ['label' => 'x', 'key' => 'test-gemini-key-material-x-padding'])
        ->assertForbidden();

    $this->actingAs($this->endUser)
        ->patchJson(route('api.admin.settings.keys.update', $key), ['label' => 'y'])
        ->assertForbidden();

    $this->actingAs($this->endUser)
        ->deleteJson(route('api.admin.settings.keys.destroy', $key))
        ->assertForbidden();
});

test('multistep retrieval grounds recurring classroom replacement assessment in tool evidence', function () {
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'multistep-custodian',
        'email' => 'multistep-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $custodian->id,
        'unit' => 'pieces',
        'item_name' => 'Projector',
        'quantity' => 1,
        'unit_cost' => 500,
        'date_acquired' => '2018-01-01',
        'lifespan_years' => 5,
        'status' => 'under_inspection',
        'building' => 'Main Building',
        'room' => '204',
    ]);
    foreach ([150, 150] as $cost) {
        MaintenanceRecord::create([
            'inventory_id' => $item->item_id,
            'reported_by' => $custodian->id,
            'status' => 'completed',
            'issue_description' => 'Repeated projector power issue',
            'maintenance_cost' => $cost,
            'started_at' => now()->subMonths(2),
        ]);
    }

    $result = app(\App\Services\InventoryAnswerService::class)->answer($custodian, [
        'intent' => 'factual',
        'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
        'original_question' => 'Which classrooms have recurring equipment problems, and which items may need replacement?',
    ]);

    expect($result['status'])->toBe('success')
        ->and($result['retrieval_trace'])->toHaveCount(4)
        ->and(collect($result['retrieval_trace'])->pluck('authorized')->unique()->all())->toBe([true])
        ->and(collect($result['retrieval_trace'])->pluck('tool')->all())->toBe([
            AiCapabilityPolicy::VIEW_MAINTENANCE,
            AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            AiCapabilityPolicy::VIEW_ITEM_STATUS,
            AiCapabilityPolicy::VIEW_MAINTENANCE,
        ]);

    $reply = app(\App\Services\InventoryAnswerService::class)->localReply($result, $custodian);
    expect($reply)->toContain('Projector')
        ->and($reply)->toContain('Main Building, 204')
        ->and($reply)->toContain('2 recorded maintenance entries')
        ->and($reply)->toContain('consider replacement')
        ->and($reply)->toContain('60%')
        ->and($reply)->toContain('current replacement quote');
});

test('multistep retrieval stops when the first tool is not authorized', function () {
    $executor = app(CompoundPlanExecutor::class);

    $results = $executor->execute($this->endUser, [
        'kind' => 'single',
        'requests' => [[
            'intent' => 'factual',
            'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
            'original_question' => 'Which classrooms have recurring equipment problems, and which items may need replacement?',
        ]],
    ]);

    expect($results)->toHaveCount(2)
        ->and($results[0]['status'])->toBe('forbidden')
        ->and($results[0]['retrieval_trace'][0]['authorized'])->toBeFalse()
        ->and($results[0]['retrieval_trace'])->toHaveCount(2)
        ->and($results[0]['retrieval_trace'][1]['tool'])->toBeNull()
        ->and($results[1]['status'])->toBe('insufficient_data')
        ->and($results[1]['answer']['missing_evidence'][0])->toContain('Maintenance records were unavailable');
});

test('context memory stops exposing earlier topics after a new or unsuccessful turn', function () {
    $turnLog = app(\App\Services\Conversation\ConversationTurnLog::class);
    $sessionId = 'context-boundary-session';

    $turnLog->record(
        $this->admin,
        $sessionId,
        'How many Projectors are available?',
        'Projectors: 2 available.',
        [
            'intent' => 'factual',
            'topic_action' => 'new_topic',
            'capability' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
            'response_type' => 'count',
            'item_name' => 'Projector',
            'inventory_id' => 41,
            'original_question' => 'How many Projectors are available?',
        ],
        ['status' => 'success'],
        [],
        [41],
    );
    $turnLog->record(
        $this->admin,
        $sessionId,
        'Where is the Printer?',
        'The Printer is in room 12.',
        [
            'intent' => 'factual',
            'topic_action' => 'new_topic',
            'capability' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            'response_type' => 'detail',
            'item_name' => 'Printer',
            'inventory_id' => 42,
            'original_question' => 'Where is the Printer?',
        ],
        ['status' => 'success'],
        [],
        [42],
    );

    expect($turnLog->entityRegistry($this->admin, $sessionId))->toBe([42])
        ->and($turnLog->recentTurns($this->admin, $sessionId))->toHaveCount(1)
        ->and($turnLog->lastResolved($this->admin, $sessionId)['item_name'])->toBe('Printer')
        ->and($turnLog->rebuildTopic($this->admin, $sessionId)['item_name'])->toBe('Printer');

    $turnLog->record(
        $this->admin,
        $sessionId,
        'Can I see restricted data?',
        'That information is not available.',
        [
            'intent' => 'unsupported',
            'topic_action' => 'unclear',
            'capability' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            'response_type' => 'detail',
            'original_question' => 'Can I see restricted data?',
        ],
        ['status' => 'forbidden'],
    );

    expect($turnLog->entityRegistry($this->admin, $sessionId))->toBe([])
        ->and($turnLog->recentTurns($this->admin, $sessionId))->toBe([])
        ->and($turnLog->lastResolved($this->admin, $sessionId))->toBeNull()
        ->and($turnLog->rebuildTopic($this->admin, $sessionId))->toBeNull();
});

test('context single chat request returns a grounded LLM answer and records the turn', function () {
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'context-chat-custodian',
        'email' => 'context-chat-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $custodian->id,
        'unit' => 'units',
        'item_name' => 'Projector',
        'quantity' => 4,
        'unit_cost' => 500,
        'date_acquired' => '2024-01-01',
        'lifespan_years' => 5,
        'status' => 'available',
        'building' => 'Main Building',
        'room' => '204',
    ]);

    config()->set([
        'services.gemini.api_key' => 'test-gemini-key',
        'services.gemini.model' => 'test/model',
    ]);
    $parsedIntent = [
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
    ];
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(keypoolSuccessPayload(json_encode($parsedIntent, JSON_THROW_ON_ERROR)))
            ->push(keypoolSuccessPayload('There are 4 Projectors in stock.')),
    ]);

    $response = $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'How many Projectors are in stock?'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('reply', 'There are 4 Projectors in stock.');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool =>
        str_contains($request->data()['contents'][0]['parts'][0]['text'] ?? '', '"available_count": 4')
    );

    $turn = \App\Models\AiConversationTurn::query()->sole();
    expect($turn->status)->toBe(\App\Models\AiConversationTurn::STATUS_SUCCESS)
        ->and($turn->question)->toBe('How many Projectors are in stock?')
        ->and($turn->resolved['item_name'])->toBe('Projector')
        ->and($response->json('reply'))->not->toContain('fallback');
});

function assignmentHolderUser(string $first, string $last, string $username): User
{
    $role = Role::firstOrCreate(['role_name' => 'End User']);

    return User::create([
        'role_id' => $role->role_id,
        'first_name' => $first,
        'last_name' => $last,
        'username' => $username,
        'email' => "{$username}@example.com",
        'password' => 'password',
        'status' => 'active',
    ]);
}

function assignmentHolderItem(string $name, int $categoryId, int $userId): Inventory
{
    return Inventory::create([
        'category_id' => $categoryId,
        'user_id' => $userId,
        'unit' => 'unit',
        'item_name' => $name,
        'quantity' => 1,
        'unit_cost' => 100,
        'date_acquired' => '2024-01-01',
        'status' => 'available',
    ]);
}

function assignmentHolderTransaction(Inventory $item, int $holderId): Transaction
{
    return Transaction::create([
        'user_id' => $holderId,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2024-02-01',
        'status' => 'assigned',
    ]);
}

function assignmentHolderRequest(string $question, ?string $itemName = null): array
{
    return [
        'capability' => AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        'intent' => 'factual',
        'original_question' => $question,
        'item_name' => $itemName,
    ];
}

test('assignment holder question returns only that holder rows', function () {
    $holderA = assignmentHolderUser('Enda', 'Holder', 'enda-holder');
    $holderB = assignmentHolderUser('Second', 'Holder', 'second-holder');
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $laptop = assignmentHolderItem('Laptop', $category->category_id, $this->admin->id);
    assignmentHolderTransaction($laptop, $holderA->id);
    assignmentHolderTransaction($laptop, $holderB->id);

    $result = app(AssignmentTool::class)->fetch(
        $this->admin,
        assignmentHolderRequest('Which items are assigned to Enda Holder?')
    );

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['assignment_records'];
    expect($rows)->not->toBeEmpty();
    foreach ($rows as $row) {
        expect($row['assigned_to'])->toBe('Enda Holder');
    }
});

test('assignment holder and item filters combine', function () {
    $holderA = assignmentHolderUser('Enda', 'Holder', 'enda-holder');
    $holderB = assignmentHolderUser('Second', 'Holder', 'second-holder');
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $laptop = assignmentHolderItem('Laptop', $category->category_id, $this->admin->id);
    $projector = assignmentHolderItem('Projector', $category->category_id, $this->admin->id);
    assignmentHolderTransaction($laptop, $holderA->id);
    assignmentHolderTransaction($projector, $holderA->id);
    assignmentHolderTransaction($projector, $holderB->id);

    $result = app(AssignmentTool::class)->fetch(
        $this->admin,
        assignmentHolderRequest('Show laptops assigned to Enda Holder', 'Laptop')
    );

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['assignment_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item_name'])->toBe('Laptop')
        ->and($rows[0]['assigned_to'])->toBe('Enda Holder');
});

test('assignment unknown holder asks for clarification', function () {
    $result = app(AssignmentTool::class)->fetch(
        $this->admin,
        assignmentHolderRequest('What is assigned to Nobody Here?')
    );

    expect($result['status'])->toBe('clarification')
        ->and($result['answer']['clarification_question'])->toContain('Nobody Here');
});

test('assignment ambiguous holder asks for clarification', function () {
    assignmentHolderUser('Sam', 'Cruz', 'sam-cruz');
    assignmentHolderUser('Sam', 'Cruz', 'sam-cruz-2');

    $result = app(AssignmentTool::class)->fetch(
        $this->admin,
        assignmentHolderRequest('What is assigned to Sam Cruz?')
    );

    expect($result['status'])->toBe('clarification')
        ->and($result['answer']['clarification_candidates'])->toHaveCount(2);
});

test('assignment gemini packet carries holder names without other personal data', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'assignment_records' => [[
            'inventory_id' => 7,
            'item_name' => 'Laptop',
            'assigned_to' => 'Enda Holder',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => 'assigned',
            'date' => '2026-10-01',
            'email' => 'PRIVATE_EMAIL_611',
            'contact_number' => 'PRIVATE_PHONE_722',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What is assigned to Enda Holder?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($input, '"assigned_to": "Enda Holder"')
            && ! str_contains($input, 'PRIVATE_EMAIL_611')
            && ! str_contains($input, 'PRIVATE_PHONE_722');
    });
});

test('assignment local list enumerates records and discloses the 25-record cap', function () {
    $row = [
        'item_name' => 'Laptop',
        'inventory_id' => 7,
        'status' => 'assigned',
        'assigned_to' => 'Enda Holder',
        'quantity' => 1,
        'unit' => 'unit',
        'date' => '2026-10-01',
    ];

    $capped = app(InventoryAnswerService::class)->localReply([
        'status' => 'success',
        'answer' => ['assignment_records' => array_fill(0, 25, $row)],
    ]);

    expect(substr_count($capped, '- Assignment:'))->toBe(25)
        ->and($capped)->toContain('latest 25')
        ->and($capped)->toContain('Assigned to: Enda Holder');

    $short = app(InventoryAnswerService::class)->localReply([
        'status' => 'success',
        'answer' => ['assignment_records' => array_fill(0, 3, $row)],
    ]);

    expect(substr_count($short, '- Assignment:'))->toBe(3)
        ->and($short)->not->toContain('latest 25');
});

test('assignment packet carries identity fields without personal data', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'assignment_records' => [[
            'inventory_id' => 7,
            'item_name' => 'Laptop',
            'assigned_to' => 'Enda Holder',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => 'assigned',
            'date' => '2026-10-01',
            'inventory_no' => 'INV-000007',
            'serial_number' => 'SN-007',
            'building' => 'Science Building',
            'room' => 'Lab 1',
            'email' => 'PRIVATE_EMAIL_813',
            'username' => 'PRIVATE_USERNAME_914',
            'user_id' => 4242,
            'contact_number' => 'PRIVATE_PHONE_015',
            'notes' => 'PRIVATE_NOTES_116',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What is assigned to Enda Holder?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($input, '"assigned_to": "Enda Holder"')
            && str_contains($input, '"inventory_id": 7')
            && str_contains($input, '"inventory_no": "INV-000007"')
            && str_contains($input, '"serial_number": "SN-007"')
            && str_contains($input, '"building": "Science Building"')
            && str_contains($input, '"room": "Lab 1"')
            && ! str_contains($input, 'PRIVATE_EMAIL_813')
            && ! str_contains($input, 'PRIVATE_USERNAME_914')
            && ! str_contains($input, '4242')
            && ! str_contains($input, 'PRIVATE_PHONE_015')
            && ! str_contains($input, 'PRIVATE_NOTES_116');
    });
});

test('assignment own-items packet includes inventory numbers', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'assigned_items' => [[
            'item_name' => 'Laptop',
            'quantity' => 1,
            'unit' => 'unit',
            'status' => 'assigned',
            'inventory_id' => 9,
            'inventory_no' => 'INV-000009',
            'email' => 'PRIVATE_EMAIL_511',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What items do I hold?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains($input, '"inventory_id": 9')
            && str_contains($input, '"inventory_no": "INV-000009"')
            && ! str_contains($input, 'PRIVATE_EMAIL_511');
    });
});

test('assignment serial disambiguates which bond paper', function () {
    $holder = assignmentHolderUser('Enda', 'Holder', 'enda-holder');
    $category = Category::create([
        'category_name' => 'Serialized Assignment Category',
        'requires_serial_number' => true,
    ]);
    $first = assignmentHolderItem('Bond Paper', $category->category_id, $this->admin->id);
    $first->update(['serial_number' => 'SN-T1-A1', 'inventory_item_no' => 'INV-T1-001']);
    $second = assignmentHolderItem('Bond Paper', $category->category_id, $this->admin->id);
    $second->update(['serial_number' => 'SN-T1-A2', 'inventory_item_no' => 'INV-T1-002']);
    assignmentHolderTransaction($first, $holder->id);
    assignmentHolderTransaction($second, $holder->id);

    $result = app(AssignmentTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        'intent' => 'factual',
        'original_question' => 'Which Bond Paper with serial SN-T1-A2 is assigned?',
        'item_name' => 'Bond Paper',
        'serial_number' => 'SN-T1-A2',
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['assignment_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['serial_number'] ?? null)->toBe('SN-T1-A2')
        ->and($rows[0]['inventory_no'] ?? null)->toBe('INV-T1-002');
});

function transferHistoryUsers(): array
{
    $sender = assignmentHolderUser('Tomas', 'Sender', 'tomas-sender');
    $recipient = assignmentHolderUser('Rosa', 'Recipient', 'rosa-recipient');

    return [$sender, $recipient];
}

function transferHistoryItem(int $categoryId, int $userId): Inventory
{
    return Inventory::create([
        'category_id' => $categoryId,
        'user_id' => $userId,
        'unit' => 'unit',
        'item_name' => 'Projector',
        'quantity' => 1,
        'unit_cost' => 500,
        'date_acquired' => '2024-01-01',
        'status' => 'available',
        'inventory_item_no' => 'INV-TH-001',
    ]);
}

test('transfer_history packet carries parties without notes or contacts', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'transfer_records' => [[
            'inventory_id' => 21,
            'item_name' => 'Projector',
            'quantity' => 1,
            'unit' => 'unit',
            'date' => '2026-10-01',
            'from' => 'Tomas Sender',
            'to' => 'Rosa Recipient',
            'status' => 'transferred',
            'inventory_no' => 'INV-000021',
            'notes' => 'PRIVATE_TRANSFER_NOTES_711',
            'contact_number' => 'PRIVATE_PHONE_712',
            'user_id' => 4242,
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'Show completed transfers.',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_TRANSFER_HISTORY,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"from": "Tomas Sender"')
            && str_contains((string) $input, '"to": "Rosa Recipient"')
            && str_contains((string) $input, '"inventory_no": "INV-000021"')
            && ! str_contains((string) $input, 'PRIVATE_TRANSFER_NOTES_711')
            && ! str_contains((string) $input, 'PRIVATE_PHONE_712')
            && ! str_contains((string) $input, '4242');
    });
});

test('transfer_history tool returns completed transfers with parties and dates', function () {
    [$sender, $recipient] = transferHistoryUsers();
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $item = transferHistoryItem($category->category_id, $this->admin->id);
    $transaction = assignmentHolderTransaction($item, $recipient->id);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $sender->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'approved',
        'transaction_id' => $transaction->id,
        'requested_at' => now(),
        'responded_at' => now(),
    ]);
    // Decoys: a custodian assignment and a still-pending transfer.
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->admin->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $sender->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'waiting for transfer approval',
        'requested_at' => now(),
    ]);

    $result = app(TransferTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_TRANSFER_HISTORY,
        'intent' => 'factual',
        'original_question' => 'Show completed transfers.',
        'item_name' => null,
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['transfer_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['from'] ?? null)->toBe('Tomas Sender')
        ->and($rows[0]['to'] ?? null)->toBe('Rosa Recipient')
        ->and($rows[0]['date'] ?? null)->not->toBeNull()
        ->and($rows[0]['inventory_no'] ?? null)->toBe('INV-TH-001');

    $reply = app(InventoryAnswerService::class)->localReply($result, $this->admin);
    expect($reply)->toContain('From: Tomas Sender')->and($reply)->toContain('To: Rosa Recipient');
});

test('return_history packet carries parties without notes or contacts', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'return_records' => [[
            'inventory_id' => 23,
            'item_name' => 'Projector',
            'quantity' => 1,
            'unit' => 'unit',
            'date' => '2026-10-02',
            'from' => 'Rosa Recipient',
            'to' => 'Property Custodian',
            'status' => 'returned',
            'inventory_no' => 'INV-000023',
            'notes' => 'PRIVATE_RETURN_NOTES_811',
            'user_id' => 4242,
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'Show completed returns.',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_RETURN_HISTORY,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"from": "Rosa Recipient"')
            && str_contains((string) $input, '"inventory_no": "INV-000023"')
            && ! str_contains((string) $input, 'PRIVATE_RETURN_NOTES_811')
            && ! str_contains((string) $input, '4242');
    });
});

test('return_history tool returns completed returns from assignment_returns', function () {
    [$sender, $recipient] = transferHistoryUsers();
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $item = transferHistoryItem($category->category_id, $this->admin->id);
    $transaction = assignmentHolderTransaction($item, $recipient->id);
    AssignmentReturn::create([
        'transaction_id' => $transaction->id,
        'inventory_id' => $item->item_id,
        'recipient_id' => $recipient->id,
        'received_by' => $this->admin->id,
        'quantity' => 1,
        'returned_at' => now(),
    ]);

    $result = app(ReturnTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_RETURN_HISTORY,
        'intent' => 'factual',
        'original_question' => 'Show completed returns.',
        'item_name' => null,
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['return_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['from'] ?? null)->toBe('Rosa Recipient')
        ->and($rows[0]['to'] ?? null)->toBe('Admin User')
        ->and($rows[0]['date'] ?? null)->not->toBeNull();

    $reply = app(InventoryAnswerService::class)->localReply($result, $this->admin);
    expect($reply)->toContain('From: Rosa Recipient')->and($reply)->toContain('To: Admin User');
});

test('request pending packet carries status and requester without emails', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'pending_requests' => [[
            'item_name' => 'Bond Paper',
            'quantity' => 5,
            'unit' => 'ream',
            'status' => 'waiting for approval',
            'requester' => 'End User',
            'requested_at' => '2026-10-01',
            'email' => 'PRIVATE_EMAIL_911',
            'notes' => 'PRIVATE_NOTES_912',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What requests are pending?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_PENDING_REQUESTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"status": "waiting for approval"')
            && str_contains((string) $input, '"requester": "End User"')
            && ! str_contains((string) $input, 'PRIVATE_EMAIL_911')
            && ! str_contains((string) $input, 'PRIVATE_NOTES_912');
    });
});

test('request own packet carries unit without ids', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'requests' => [[
            'item_name' => 'Bond Paper',
            'quantity' => 5,
            'unit' => 'ream',
            'status' => 'waiting for approval',
            'requested_at' => '2026-10-01',
            'id' => 4242,
            'item_id' => 4243,
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What did I request?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_OWN_REQUESTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"unit": "ream"')
            && ! str_contains((string) $input, '4242')
            && ! str_contains((string) $input, '4243');
    });
});

test('history purchase packet carries inventory numbers without notes', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'purchase_history' => [[
            'inventory_id' => 27,
            'item_name' => 'Bond Paper',
            'quantity' => 10,
            'unit' => 'ream',
            'date' => '2026-10-01',
            'inventory_no' => 'INV-000027',
            'notes' => 'PRIVATE_STOCK_NOTES_011',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'Show purchase history.',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"inventory_id": 27')
            && str_contains((string) $input, '"inventory_no": "INV-000027"')
            && ! str_contains((string) $input, 'PRIVATE_STOCK_NOTES_011');
    });
});

test('history ledger summary caps counts and latest movements', function () {
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $item = transferHistoryItem($category->category_id, $this->admin->id);
    foreach (range(1, 6) as $n) {
        StockMovement::create([
            'inventory_id' => $item->item_id,
            'user_id' => $this->admin->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
        ]);
    }
    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $this->admin->id,
        'movement_type' => 'transfer',
        'quantity' => 1,
    ]);

    $result = app(HistoryTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_LEDGER_SUMMARY,
        'intent' => 'factual',
        'original_question' => 'Summarize recent stock movements.',
        'item_name' => null,
    ]);

    expect($result['status'])->toBe('success');
    $summary = $result['answer']['ledger_summary'];
    expect($summary['movement_counts'] ?? [])->toContainEqual(['movement_type' => 'stock_in', 'movement_count' => 6])
        ->and($summary['movement_counts'] ?? [])->toContainEqual(['movement_type' => 'transfer', 'movement_count' => 1])
        ->and($summary['latest_movements'] ?? [])->toHaveCount(5);

    foreach ($summary['latest_movements'] as $movement) {
        expect($movement)->not->toHaveKey('notes')
            ->and($movement)->toHaveKeys(['item_name', 'movement_type', 'quantity', 'date', 'actor']);
    }

    $reply = app(InventoryAnswerService::class)->localReply($result, $this->admin);
    expect($reply)->toContain('Latest 5 movements');
});

function statusTestItem(string $name, string $status, int $categoryId, int $userId, ?string $itemNo = null): Inventory
{
    return Inventory::create([
        'category_id' => $categoryId,
        'user_id' => $userId,
        'unit' => 'unit',
        'item_name' => $name,
        'quantity' => 1,
        'unit_cost' => 100,
        'date_acquired' => '2024-01-01',
        'status' => $status,
        'building' => 'Science Building',
        'room' => 'Lab 1',
        'inventory_item_no' => $itemNo ?? ('INV-9' . random_int(10001, 99999)),
    ]);
}

test('status maintenance packet carries identity and issue without repair notes', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'maintenance_records' => [[
            'inventory_id' => 11,
            'item_name' => 'Microscope',
            'status' => 'reported',
            'issue' => 'Cracked lens',
            'date' => '2026-10-01',
            'inventory_no' => 'INV-000011',
            'notes' => 'PRIVATE_REPAIR_NOTES_411',
            'email' => 'PRIVATE_EMAIL_412',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What is wrong with the Microscope?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_MAINTENANCE,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"inventory_id": 11')
            && str_contains((string) $input, '"inventory_no": "INV-000011"')
            && str_contains((string) $input, '"issue": "Cracked lens"')
            && ! str_contains((string) $input, 'PRIVATE_REPAIR_NOTES_411')
            && ! str_contains((string) $input, 'PRIVATE_EMAIL_412');
    });
});

test('status under-repair trace returns identity rows from the tool', function () {
    $category = Category::firstOrCreate(['category_name' => 'Laboratory Equipment']);
    $item = statusTestItem('Microscope', 'under_repair', $category->category_id, $this->admin->id);
    MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'reported_by' => $this->admin->id,
        'status' => 'reported',
        'issue_description' => 'Cracked lens',
        'repair_notes' => 'Internal technician remarks',
        'started_at' => now(),
    ]);

    $result = app(MaintenanceTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
        'intent' => 'factual',
        'original_question' => 'What is wrong with the Microscope?',
        'item_name' => 'Microscope',
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['maintenance_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['inventory_id'] ?? null)->toBe($item->item_id)
        ->and($rows[0]['inventory_no'] ?? null)->toBe($item->inventory_item_no)
        ->and($rows[0]['issue'] ?? null)->toBe('Cracked lens');
});

test('status disposal packet excludes free-form notes with documented reason', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'disposal_records' => [[
            'inventory_id' => 13,
            'item_name' => 'Old Chair',
            'status' => 'disposed',
            'quantity' => 2,
            'unit' => 'piece',
            'date' => '2026-09-01',
            'inventory_no' => 'INV-000013',
            'notes' => 'PRIVATE_DISPOSAL_NOTES_511',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'Why was the Old Chair disposed?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_DISPOSAL,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"inventory_id": 13')
            && str_contains((string) $input, '"inventory_no": "INV-000013"')
            && ! str_contains((string) $input, 'PRIVATE_DISPOSAL_NOTES_511');
    });
});

test('status disposed-why trace returns dated rows from the tool', function () {
    $category = Category::firstOrCreate(['category_name' => 'Furniture']);
    $item = statusTestItem('Old Chair', 'disposed', $category->category_id, $this->admin->id);
    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $this->admin->id,
        'movement_type' => 'disposed',
        'quantity' => 1,
        'notes' => 'Beyond economical repair',
    ]);

    $result = app(DisposalTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_DISPOSAL,
        'intent' => 'factual',
        'original_question' => 'Why was the Old Chair disposed?',
        'item_name' => 'Old Chair',
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['disposal_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['inventory_no'] ?? null)->toBe($item->inventory_item_no)
        ->and($rows[0]['date'] ?? null)->not->toBeNull();
});

test('status awaiting-disposal rows carry inventory numbers', function () {
    $category = Category::firstOrCreate(['category_name' => 'Furniture']);
    $item = statusTestItem('Wobbly Desk', 'ready_to_dispose', $category->category_id, $this->admin->id);

    $result = app(DisposalTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        'intent' => 'factual',
        'original_question' => 'What is awaiting disposal?',
        'item_name' => 'Wobbly Desk',
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['ready_to_dispose_items'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['inventory_id'] ?? null)->toBe($item->item_id)
        ->and($rows[0]['inventory_no'] ?? null)->toBe($item->inventory_item_no);
});

test('status ready-to-dispose and item-status packets carry identity', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What is awaiting disposal?',
        ['ready_to_dispose_items' => [[
            'item_name' => 'Wobbly Desk',
            'status' => 'ready_to_dispose',
            'quantity' => 1,
            'unit' => 'piece',
            'inventory_id' => 17,
            'inventory_no' => 'INV-000017',
        ]]],
        'fallback reply',
        AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
        AnswerComposer::MODE_BOUNDED,
    );

    app(AnswerComposer::class)->compose(
        $this->admin,
        'What is the status of the Microscope?',
        ['status_items' => [[
            'item_name' => 'Microscope',
            'status' => 'under_repair',
            'quantity' => 1,
            'unit' => 'unit',
            'inventory_id' => 19,
            'inventory_no' => 'INV-000019',
            'building' => 'Science Building',
            'room' => 'Lab 1',
            'email' => 'PRIVATE_EMAIL_611',
        ]]],
        'fallback reply',
        AiCapabilityPolicy::VIEW_ITEM_STATUS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"inventory_no": "INV-000017"');
    });

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"building": "Science Building"')
            && str_contains((string) $input, '"room": "Lab 1"')
            && ! str_contains((string) $input, 'PRIVATE_EMAIL_611');
    });
});

test('status inspection stays unsupported and is never faked', function () {
    expect(app(MaintenanceTool::class)->handles('view_inspection'))->toBeFalse()
        ->and(app(DisposalTool::class)->handles('view_inspection'))->toBeFalse()
        ->and(app(InventoryTool::class)->handles('view_inspection'))->toBeFalse()
        ->and(app(AiCapabilityPolicy::class)->isKnownCapability('view_inspection'))->toBeFalse();
});

test('assignment where question returns building and room labels', function () {
    $holder = assignmentHolderUser('Enda', 'Holder', 'enda-holder');
    $category = Category::firstOrCreate(['category_name' => 'Office Supplies']);
    $item = assignmentHolderItem('Bond Paper', $category->category_id, $this->admin->id);
    Transaction::create([
        'user_id' => $holder->id,
        'item_id' => $item->item_id,
        'quantity' => 5,
        'issued_quantity' => 5,
        'building' => 'Science Building',
        'room' => 'Lab 1',
        'transaction_date' => '2024-02-01',
        'status' => 'assigned',
    ]);

    $result = app(AssignmentTool::class)->fetch($this->admin, [
        'capability' => AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        'intent' => 'factual',
        'original_question' => 'Where is the Bond Paper assigned?',
        'item_name' => 'Bond Paper',
    ]);

    expect($result['status'])->toBe('success');
    $rows = $result['answer']['assignment_records'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['building'] ?? null)->toBe('Science Building')
        ->and($rows[0]['room'] ?? null)->toBe('Lab 1');

    $reply = app(InventoryAnswerService::class)->localReply($result, $this->admin);
    expect($reply)->toContain('Building: Science Building')
        ->and($reply)->toContain('Room: Lab 1');
});

test('assignment packet carries building and room without extra detail', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(keypoolSuccessPayload('ok')),
    ]);

    $facts = [
        'assignment_records' => [[
            'inventory_id' => 7,
            'item_name' => 'Bond Paper',
            'assigned_to' => 'Enda Holder',
            'quantity' => 5,
            'unit' => 'ream',
            'status' => 'assigned',
            'date' => '2026-10-01',
            'building' => 'Science Building',
            'room' => 'Lab 1',
            'email' => 'PRIVATE_EMAIL_921',
            'notes' => 'PRIVATE_NOTES_922',
        ]],
    ];

    app(AnswerComposer::class)->compose(
        $this->admin,
        'Where is the Bond Paper assigned?',
        $facts,
        'fallback reply',
        AiCapabilityPolicy::VIEW_ASSIGNMENTS,
        AnswerComposer::MODE_BOUNDED,
    );

    Http::assertSent(function (Request $request): bool {
        $input = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $input, '"building": "Science Building"')
            && str_contains((string) $input, '"room": "Lab 1"')
            && ! str_contains((string) $input, 'PRIVATE_EMAIL_921')
            && ! str_contains((string) $input, 'PRIVATE_NOTES_922');
    });
});

function scenarioCustodian(): User
{
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);

    return User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Scenario',
        'last_name' => 'Custodian',
        'username' => 'scenario-custodian-' . uniqid(),
        'email' => 'scenario-custodian-' . uniqid() . '@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
}

function scenarioSeed(object $test): array
{
    $custodian = scenarioCustodian();
    $category = Category::firstOrCreate(['category_name' => 'Scenario Supplies']);

    $make = function (string $name, string $status, int $quantity, ?int $holderId = null, float $unitCost = 100.0) use ($category, $custodian): Inventory {
        return Inventory::create([
            'category_id' => $category->category_id,
            'user_id' => $custodian->id,
            'unit' => 'piece',
            'item_name' => $name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'date_acquired' => '2024-01-01',
            'status' => $status,
            'assigned_to_user_id' => $holderId,
        ]);
    };

    // Group under test: 12 available, 2 assigned to the end user, avg cost 150.
    $first = $make('Scenario Widget', 'available', 8, null, 100.0);
    $make('Scenario Widget', 'available', 4, null, 200.0);
    $make('Scenario Widget', 'assigned', 1, $test->endUser->id, 150.0);
    $make('Scenario Widget', 'assigned', 1, $test->endUser->id, 150.0);
    // Ambiguity partner: shares the word "Widget" but is its own group.
    $make('Widget Pro', 'available', 6, null, 50.0);

    AssignmentRequest::create([
        'item_id' => $first->item_id,
        'user_id' => $test->endUser->id,
        'target_user_id' => $custodian->id,
        'quantity' => 3,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    return [$custodian, $category];
}

function scenarioRequest(string $question, ?string $itemName, ?string $type, $quantity = null, ?string $topic = null): array
{
    return [
        'intent' => 'scenario',
        'capability' => AiCapabilityPolicy::VIEW_SCENARIO,
        'item_name' => $itemName,
        'response_type' => 'detail',
        'original_question' => $question,
        'needs_clarification' => false,
        'vague' => false,
        'query' => null,
        'filters' => [],
        'scenario' => ['type' => $type, 'quantity' => $quantity, 'topic' => $topic],
    ];
}

test('scenario stock-level substitution recomputes shortfall with assumptions first', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if Scenario Widget stock was 2?', 'Scenario Widget', 'stock_level', 2)
    );

    expect($result['status'])->toBe('success');
    $packet = $result['answer']['scenario_projection'];
    expect($packet['baseline']['current_available'])->toBe(12)
        ->and($packet['projection']['assumed_available'])->toBe(2)
        ->and($packet['projection']['current_shortfall'])->toBe(0)
        ->and($packet['projection']['projected_shortfall'])->toBe(1);

    $reply = $service->localReply($result, $custodian);
    $lines = explode("\n", $reply);
    expect($lines[0])->toBe('Assuming available stock of Scenario Widget (piece) is 2 pieces.')
        ->and($lines[1])->toBe('Available would be 2 pieces (now 12).')
        ->and($lines[2])->toBe('Requested 3; shortfall 1 (now 0).')
        ->and(end($lines))->toBe('Projection only — no stock was changed.');
});

test('scenario bulk assign consumes free stock and leads with shortfall', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if we assign 20 Scenario Widget?', 'Scenario Widget', 'bulk_assign', 20)
    );

    expect($result['status'])->toBe('success');
    $packet = $result['answer']['scenario_projection'];
    expect($packet['baseline']['free_stock'])->toBe(9)
        ->and($packet['projection']['projected_remaining'])->toBe(-11)
        ->and($packet['projection']['shortfall'])->toBe(11);

    $lines = explode("\n", $service->localReply($result, $custodian));
    expect($lines[1])->toBe('Shortfall 11 pieces: only 9 free for 20 wanted.')
        ->and(end($lines))->toBe('Projection only — no stock was changed.');

    $feasible = $service->answer(
        $custodian,
        scenarioRequest('what if we assign 5 Scenario Widget?', 'Scenario Widget', 'bulk_assign', 5)
    );
    expect($service->localReply($feasible, $custodian))->toContain('All 5 pieces could be assigned; 4 free left.');
});

test('scenario bulk remove frees holders against assigned holdings', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if we remove 1 Scenario Widget?', 'Scenario Widget', 'bulk_remove', 1)
    );

    expect($result['status'])->toBe('success');
    $projection = $result['answer']['scenario_projection']['projection'];
    expect($projection['projected_available'])->toBe(13)
        ->and($projection['projected_assigned'])->toBe(1)
        ->and($projection['beyond_assigned'])->toBe(0);

    $lines = explode("\n", $service->localReply($result, $custodian));
    expect($lines[1])->toBe('1 of 2 assigned pieces would return to stock.')
        ->and($lines[2])->toBe('Available 12 to 13; 1 still assigned.')
        ->and(end($lines))->toBe('Projection only — no stock was changed.');
});

test('scenario dispose set removes stock with custody untouched', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if we dispose 2 Scenario Widget?', 'Scenario Widget', 'dispose_set', 2)
    );

    expect($result['status'])->toBe('success');
    $packet = $result['answer']['scenario_projection'];
    expect($packet['projection']['removed_from_stock'])->toBe(2)
        ->and($packet['projection']['projected_available'])->toBe(10)
        ->and($packet['projection']['assigned_untouched'])->toBe(2)
        ->and($packet['projection']['beyond_available'])->toBe(0)
        ->and(array_keys($packet['baseline']))->toBe(['item_name', 'unit', 'current_available', 'assigned_units']);

    $reply = $service->localReply($result, $custodian);
    expect($reply)->toContain('Stock would drop to 10 (2 removed); 2 assigned custody untouched.')
        ->and($reply)->not->toContain('freed assigned')
        ->and($reply)->not->toContain('@');

    $excess = $service->answer(
        $custodian,
        scenarioRequest('what if we dispose 20 Scenario Widget?', 'Scenario Widget', 'dispose_set', 20)
    );
    expect($service->localReply($excess, $custodian))->toContain('8 beyond available stock.');
});

test('scenario gap to target prices the gap at recorded unit cost', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if we need 20 Scenario Widget?', 'Scenario Widget', 'gap_to_target', 20)
    );

    expect($result['status'])->toBe('success');
    $projection = $result['answer']['scenario_projection']['projection'];
    expect($projection['gap_units'])->toBe(8)
        ->and($projection['projected_cost'])->toBe(1200.0);

    $lines = explode("\n", $service->localReply($result, $custodian));
    expect($lines[1])->toBe('Gap 8 pieces to reach 20.')
        ->and($lines[2])->toBe('At a recorded average of 150.00 each: projected cost PHP 1,200.00.')
        ->and(end($lines))->toBe('Projection only — no stock was changed.');
});

test('scenario open-ended future is refused with the closest computation', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest(
            'what if enrollment doubles, do we have enough Scenario Widget?',
            'Scenario Widget',
            'open_ended',
            null,
            'enrollment'
        )
    );

    expect($result['status'])->toBe('success');
    $refusal = $result['answer']['scenario_refusal'];
    expect($refusal)->toContain("can't project enrollment")
        ->and($refusal)->toContain('stock-level substitution')
        ->and(preg_match('/\d/', $refusal))->toBe(0);

    expect($service->localReply($result, $custodian))->toBe($refusal);
});

test('scenario ambiguous scope and bad quantity ask for clarification', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $noItem = $service->answer(
        $custodian,
        scenarioRequest('what if stock was 50?', null, 'stock_level', 50)
    );
    expect($noItem['status'])->toBe('clarification')
        ->and($service->localReply($noItem, $custodian))->toContain('Which item');

    $ambiguous = $service->answer(
        $custodian,
        scenarioRequest('what if Widget stock was 50?', 'Widget', 'stock_level', 50)
    );
    expect($ambiguous['status'])->toBe('clarification')
        ->and($ambiguous['answer']['clarification_candidates'])->toHaveCount(2);

    $badQty = $service->answer(
        $custodian,
        scenarioRequest('what if Scenario Widget stock was 0?', 'Scenario Widget', 'stock_level', 0)
    );
    expect($badQty['status'])->toBe('clarification')
        ->and($service->localReply($badQty, $custodian))->toContain('1 or more');
});

test('scenario is forbidden for non-custodians', function () {
    scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $this->endUser,
        scenarioRequest('what if Scenario Widget stock was 2?', 'Scenario Widget', 'stock_level', 2)
    );

    expect($result['status'])->toBe('forbidden')
        ->and($service->localReply($result, $this->endUser))->toBe('That information is not available for your role.');
});

test('scenario quarantine keeps assumed inputs out of recorded facts', function () {
    [$custodian] = scenarioSeed($this);
    $service = app(InventoryAnswerService::class);

    $result = $service->answer(
        $custodian,
        scenarioRequest('what if Scenario Widget stock was 2?', 'Scenario Widget', 'stock_level', 2)
    );

    $packet = $result['answer']['scenario_projection'];
    expect($packet['baseline'])->toBe([
        'item_name' => 'Scenario Widget',
        'unit' => 'piece',
        'current_available' => 12,
        'pending_demand' => 3,
    ])
        ->and($packet['header'])->toContain('is 2 pieces')
        ->and(array_keys($result['answer']))->toBe(['scenario_projection']);

    $reply = $service->localReply($result, $custodian);
    expect($reply)->toStartWith('Assuming')
        ->and($reply)->toEndWith('Projection only — no stock was changed.');
});

test('scenario end-to-end chat projects through the assistant', function () {
    [$custodian] = scenarioSeed($this);

    $response = $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'what if Scenario Widget stock was 2?'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('reply'))->toStartWith('Assuming');
});
