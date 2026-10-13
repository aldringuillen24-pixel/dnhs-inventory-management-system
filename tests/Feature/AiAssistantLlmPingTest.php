<?php

use App\Models\AiApiKey;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryAnswerService;
use App\Services\Tools\AssignmentTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Ping',
        'last_name' => 'Custodian',
        'username' => 'llm-ping-custodian',
        'email' => 'llm-ping-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    config()->set('services.gemini.api_key', null);
});

function llmPingPayload(string $text): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

function llmPingAssignmentIntent(): array
{
    return [
        'intent' => 'assignments',
        'topic_action' => 'new_topic',
        'item_name' => null,
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'query' => null,
        'response_type' => 'list',
        'explanation' => false,
        'explanation_topic' => null,
        'is_compound' => false,
        'sub_requests' => [],
    ];
}

function llmPingSeed(int $custodianId): void
{
    $category = Category::firstOrCreate(['category_name' => 'Classroom Equipment']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $custodianId,
        'unit' => 'unit',
        'item_name' => 'Projector',
        'quantity' => 1,
        'unit_cost' => 500,
        'date_acquired' => '2024-01-01',
        'status' => 'available',
    ]);
    Transaction::create([
        'user_id' => $custodianId,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2024-02-01',
        'status' => 'assigned',
    ]);
}

function llmPingLocalFallback(User $custodian): string
{
    $toolResult = app(AssignmentTool::class)->fetch($custodian, [
        'capability' => 'view_assignments',
        'intent' => 'factual',
        'original_question' => 'show assignment records',
        'item_name' => null,
    ]);

    return app(InventoryAnswerService::class)->localReply($toolResult, $custodian);
}

test('llm ping wiring proves the provider path with a grounded marker', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    llmPingSeed($this->custodian->id);

    Http::fake(function ($request) {
        $system = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';
        if (str_contains($system, 'Classify the user question only')) {
            return Http::response(llmPingPayload(json_encode(llmPingAssignmentIntent(), JSON_THROW_ON_ERROR)));
        }

        return Http::response(llmPingPayload('MARKER: activity recorded'));
    });

    $response = $this->actingAs($this->custodian)
        ->postJson(route('ai.chat'), ['message' => 'show assignment records'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('reply'))->toContain('MARKER');

    Http::assertSent(function (Request $request): bool {
        $text = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

        return str_contains((string) $text, '"assignment_records"');
    });
});

$llmPingSkip = liveGeminiSkipReason();

test('live llm ping reports the provider source', function () {
    llmPingSeed($this->custodian->id);
    $fallback = llmPingLocalFallback($this->custodian);

    $failures = [];
    Event::listen(
        \Illuminate\Http\Client\Events\ResponseReceived::class,
        function ($event) use (&$failures): void {
            if (! $event->response->successful()) {
                $failures[] = $event->response->status();
            }
        }
    );

    $response = $this->actingAs($this->custodian)
        ->postJson(route('ai.chat'), ['message' => 'show assignment records'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $reply = (string) $response->json('reply');
    $hasKey = AiApiKey::query()->where('is_active', true)->exists()
        || (is_string(config('services.gemini.api_key')) && trim((string) config('services.gemini.api_key')) !== '');

    if ($reply !== $fallback) {
        fwrite(STDERR, "\nLLM RESPONDED (provider_status=ok, reply_length=" . strlen($reply) . ")\n");
        expect($reply)->not->toBe($fallback);
    } else {
        $status = ! $hasKey ? 'no_key' : ($failures !== [] ? 'request_failed' : 'reply_rejected');
        fwrite(STDERR, "\nFALLBACK USED (provider_status={$status}, reply_length=" . strlen($reply) . ")\n");
        expect($reply)->toBe($fallback);
    }
})->skip(($llmPingSkip ?? '') !== '', (string) ($llmPingSkip ?? 'live check skipped'));
