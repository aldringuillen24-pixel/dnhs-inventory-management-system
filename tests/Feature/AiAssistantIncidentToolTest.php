<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\InventoryAnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function incidentToolUser(string $roleName): User
{
    $role = Role::firstOrCreate(['role_name' => $roleName]);

    return User::factory()->create(['role_id' => $role->role_id]);
}

function incidentToolSeed(User $reporter): array
{
    $category = Category::create(['category_name' => 'Incident Equipment']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $reporter->id,
        'unit' => 'unit',
        'item_name' => 'Incident Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-01-01',
        'status' => 'available',
        'inventory_item_no' => 'INC-001',
    ]);

    $report = MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'damaged',
        'incident_status' => 'reported',
        'issue_description' => 'Screen is cracked',
    ]);
    $investigation = MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'lost',
        'incident_status' => 'under_investigation',
        'issue_description' => 'Unit cannot be located',
    ]);
    MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'stolen',
        'incident_status' => 'resolved',
        'issue_description' => 'Resolved case',
    ]);

    return [$report, $investigation];
}

function incidentToolIntent(string $intent, string $responseType = 'list'): array
{
    return [
        'intent' => $intent,
        'topic_action' => 'new_topic',
        'item_name' => null,
        'inventory_id' => null,
        'serial_number' => null,
        'location_query' => null,
        'filters' => ['category_id' => null, 'unit' => null],
        'query' => null,
        'response_type' => $responseType,
        'explanation' => false,
        'explanation_topic' => null,
        'is_compound' => false,
        'sub_requests' => [],
    ];
}

function fakeIncidentToolQuestion(string $intent, string $responseType = 'list'): void
{
    Http::fake(function ($request) use ($intent, $responseType) {
        $system = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';
        if (str_contains($system, 'Classify the user question only')) {
            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(incidentToolIntent($intent, $responseType), JSON_THROW_ON_ERROR)],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([], 503);
    });
}

test('incident reports question lists open reports and leaves investigations out', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    $custodian = incidentToolUser('Property Custodian');
    incidentToolSeed($custodian);
    fakeIncidentToolQuestion('incident_reports');

    $reply = $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'Show incident reports'])
        ->assertOk()
        ->json('reply');

    expect($reply)
        ->toContain('Incident Laptop')
        ->toContain('Screen is cracked')
        ->not->toContain('Unit cannot be located')
        ->not->toContain('Resolved case');
});

test('investigation question lists only records under investigation', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    $custodian = incidentToolUser('Property Custodian');
    incidentToolSeed($custodian);
    fakeIncidentToolQuestion('investigations');

    $reply = $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'Show investigations'])
        ->assertOk()
        ->json('reply');

    expect($reply)
        ->toContain('Incident Laptop')
        ->toContain('Unit cannot be located')
        ->not->toContain('Screen is cracked')
        ->not->toContain('Resolved case');
});

test('incident report count only includes open reports', function () {
    config()->set([
        'services.gemini.api_key' => 'test-key',
        'services.gemini.model' => 'test/model',
    ]);
    $custodian = incidentToolUser('Property Custodian');
    incidentToolSeed($custodian);
    fakeIncidentToolQuestion('incident_reports', 'count');

    $reply = $this->actingAs($custodian)
        ->postJson(route('ai.chat'), ['message' => 'How many incident reports are open?'])
        ->assertOk()
        ->json('reply');

    expect($reply)->toBe('There is 1 open incident report.');
});

test('incident reports and investigations are restricted to property custodians', function () {
    $custodian = incidentToolUser('Property Custodian');
    $endUser = incidentToolUser('End User');
    incidentToolSeed($custodian);

    $request = [
        'capability' => AiCapabilityPolicy::VIEW_INCIDENT_REPORTS,
        'intent' => 'factual',
        'request_type' => 'incident_reports',
        'original_question' => 'Show incident reports',
    ];

    expect(app(InventoryAnswerService::class)->answer($custodian, $request)['status'])->toBe('success')
        ->and(app(InventoryAnswerService::class)->answer($endUser, $request)['status'])->toBe('forbidden');
});
