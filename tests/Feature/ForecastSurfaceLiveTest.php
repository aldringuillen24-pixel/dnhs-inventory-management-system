<?php
namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Forecasting\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Phase 6: the served forecast must be live — no stale, missing, or invalid
 * fallbacks — and the two surfaces custodians actually use (the Reports API and
 * the AI chat) must both return real item names from the stored forecast.
 */

beforeEach(function () {
    // Opt-in only: the AI chat assertions below reach the real provider and
    // spend quota. A plain `php artisan test` must not bill the account.
    if ($reason = liveGeminiSkipReason()) {
        $this->markTestSkipped($reason);
    }

    Storage::fake('forecast');

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->custodian = User::firstOrCreate(
        ['username' => 'phase6-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Phase',
            'last_name' => 'Six',
            'email' => 'phase6@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    seedLiveForecastTestData($this->custodian);
});

test('the stored forecast is served as live, not stale or missing', function () {
    $result = app(StoredDemandForecastService::class)->read($this->custodian);

    expect($result['status'])->toBe('success')
        ->and($result['rows'])->not->toBeEmpty()
        ->and($result['forecast_period'])->not->toBeEmpty();

    foreach ($result['rows'] as $row) {
        expect($row)->toHaveKeys([
            'suggested_procurement',
            'priority',
            'confidence',
            'forecast_demand',
            'safety_stock',
        ]);
        expect($row['confidence'])->toBeIn(['Low', 'Medium', 'High']);
        expect($row['priority'])->toBeIn(['Urgent', 'High', 'Medium', 'Normal']);
    }
});

test('the reports API serves live forecast rows to a custodian', function () {
    $payload = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->json();

    expect($payload)->toHaveKey('liveForecastResult');

    $forecast = $payload['liveForecastResult'];
    expect($forecast['status'])->toBe('success')
        ->and($forecast['rows'])->not->toBeEmpty();

    // The panel must not receive a stale/missing/error status.
    expect($forecast['status'])->not->toBeIn(['stale', 'missing', 'error']);
});

test('the reports forecast explanation endpoint answers for a real forecast item', function () {
    $rows = app(\App\Services\Forecasting\StoredDemandForecastService::class)
        ->read($this->custodian)['rows'];
    $item = $rows[array_key_first($rows)];

    $response = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.explanation'), [
            'inventory_id' => $item['inventory_id'],
        ])
        ->assertOk();

    expect($response->json('explanation'))->not->toBeEmpty();
});

test('chat answers what to purchase first using live forecast items', function () {
    // "top priority" is one of the urgency phrasings the chat recognises as a
    // ranked-list question. ("What should we purchase first?" is intentionally
    // treated as a procurement follow-up and asks which item to explain.)
    $response = $this->actingAs($this->custodian)
        ->postJson(route('ai.chat'), ['message' => 'Which items are top priority?'])
        ->assertOk();

    $reply = (string) $response->json('reply');

    // The answer must be grounded in real rows, not a missing/stale notice.
    expect($reply)->not->toContain('No stored ML forecast is available')
        ->and($reply)->not->toContain('stored ML forecast is stale')
        ->and($reply)->not->toContain('invalid or unavailable');

    $names = collect(app(\App\Services\Forecasting\StoredDemandForecastService::class)
        ->read($this->custodian)['rows'])->pluck('item_name');

    expect($names->contains(fn (string $name): bool => str_contains($reply, $name)))
        ->toBeTrue('Reply did not name a forecast item. Reply was: '.$reply);
});

test('chat is refused for a role without forecast capability', function () {
    $headRole = Role::firstOrCreate(['role_name' => 'School Head']);
    $head = User::firstOrCreate(
        ['username' => 'phase6-head'],
        [
            'role_id' => $headRole->role_id,
            'first_name' => 'Phase',
            'last_name' => 'Six',
            'email' => 'phase6head@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $this->actingAs($head)
        ->postJson(route('ai.chat'), ['message' => 'What should we purchase first?'])
        ->assertStatus(403);
});