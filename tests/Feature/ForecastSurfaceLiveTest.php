<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
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

    // Copy the artifacts produced by the real local `forecast:train` run so the
    // endpoints serve genuine data rather than a hand-written fixture.
    Storage::fake('forecast');

    foreach ([
        'forecast/forecast.json',
        'forecast/training-status.json',
    ] as $file) {
        $source = storage_path('app/'.$file);
        if (is_file($source)) {
            Storage::disk('forecast')->put($file, file_get_contents($source));
        }
    }

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

    // The stored forecast is validated against live inventory rows: every item
    // it names must exist with the same id, category and name. Recreate exactly
    // those rows, with low stock, so procurement maths has real inputs.
    $forecast = json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true);
    $rows = array_merge($forecast['forecasts'] ?? [], $forecast['insufficient_history'] ?? []);

    // StoredDemandForecastService requires each row's category_id to match the
    // inventory row exactly. Category ids differ between the production
    // database and this isolated test database, so remap them by name; the
    // predicted quantities, history and priorities stay exactly as trained.
    foreach ($rows as $index => $row) {
        $category = Category::firstOrCreate(
            ['category_name' => $row['category']],
            ['requires_serial_number' => false],
        );

        $rows[$index]['category_id'] = (int) $category->category_id;

        // Inventory::$fillable omits the item_id primary key, so the row is written
        // through forceFill; mass assignment would silently drop the id and
        // every forecast row would then fail identity validation.
        $item = Inventory::firstWhere('item_id', $row['inventory_id']) ?? new Inventory;

        $item->forceFill([
            'item_id' => $row['inventory_id'],
            'item_name' => $row['item_name'],
            'category_id' => $category->category_id,
            'unit' => $row['unit'] ?? 'piece',
            'user_id' => $this->custodian->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'status' => 'available',
            'date_acquired' => '2026-06-01',
        ])->save();
    }

    $forecast['forecasts'] = array_values(array_filter(
        $rows,
        fn (array $row): bool => ($row['status'] ?? '') === 'success',
    ));
    $forecast['insufficient_history'] = array_values(array_filter(
        $rows,
        fn (array $row): bool => ($row['status'] ?? '') !== 'success',
    ));

    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($forecast));
});

test('the stored forecast is served as live, not stale or missing', function () {
    $result = app(\App\Services\StoredDemandForecastService::class)->read($this->custodian);

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
    $rows = app(\App\Services\StoredDemandForecastService::class)
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

    $names = collect(app(\App\Services\StoredDemandForecastService::class)
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