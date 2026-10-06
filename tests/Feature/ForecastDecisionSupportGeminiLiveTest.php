<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * Manual verification that AI Decision Support actually reaches Gemini with the
 * configured key, rather than silently degrading to the local summary. Skipped
 * unless GEMINI_LIVE_CHECK=1 so the suite never depends on the network.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Opt-in only: these tests call the real provider and spend quota. The
    // guard is GEMINI_LIVE_CHECK=1, not merely "a key happens to be present",
    // because .env supplies a real key to every test run.
    if ($reason = liveGeminiSkipReason()) {
        $this->markTestSkipped($reason);
    }

    Storage::fake('forecast');

    foreach (['forecast/forecast.json', 'forecast/training-status.json'] as $file) {
        $source = storage_path('app/'.$file);
        if (is_file($source)) {
            Storage::disk('forecast')->put($file, file_get_contents($source));
        }
    }

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->custodian = User::firstOrCreate(
        ['username' => 'gemini-live-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Gemini',
            'last_name' => 'Live',
            'email' => 'geminilive@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $forecast = json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true);
    $rows = array_merge($forecast['forecasts'] ?? [], $forecast['insufficient_history'] ?? []);

    foreach ($rows as $index => $row) {
        $category = Category::firstOrCreate(
            ['category_name' => $row['category']],
            ['requires_serial_number' => false],
        );
        $rows[$index]['category_id'] = (int) $category->category_id;

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

    $forecast['forecasts'] = array_values(array_filter($rows, fn (array $r): bool => ($r['status'] ?? '') === 'success'));
    $forecast['insufficient_history'] = array_values(array_filter($rows, fn (array $r): bool => ($r['status'] ?? '') !== 'success'));

    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($forecast));
});

test('a live Gemini follow-up stays on the items it was given', function () {
    $first = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.decision-support'), [
            'prompt_type' => 'purchase_first',
        ])->assertOk()->json();

    if (($first['provider_status'] ?? null) !== 'ok') {
        $this->markTestSkipped('Provider unavailable for the first turn.');
    }

    $subset = array_slice($first['inventory_ids'], 0, 2);

    $follow = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.decision-support'), [
            'prompt_type' => 'purchase_first',
            'inventory_ids' => $subset,
        ])->assertOk()->json();

    fwrite(STDERR, "\n--- FOLLOW-UP scope=".$follow['scope'].' source='.$follow['source'].' status='.$follow['provider_status']." ---\n");
    fwrite(STDERR, mb_substr((string) $follow['answer'], 0, 600)."\n");

    expect($follow['scope'])->toBe('follow_up')
        ->and($follow['inventory_ids'])->toBe($subset);

    if (($follow['provider_status'] ?? null) !== 'ok') {
        $this->markTestSkipped("Follow-up degraded: {$follow['provider_status']}.");
    }

    // The reply must discuss the narrowed items and not drag in the rest.
    $names = collect($follow['items'])->pluck('item_name')->filter()->all();
    $outside = collect($first['items'])
        ->reject(fn (array $item): bool => in_array($item['inventory_id'], $subset, true))
        ->pluck('item_name')
        ->filter(fn (string $name): bool => strlen($name) > 12);

    foreach ($outside as $name) {
        expect($follow['answer'])->not->toContain($name);
    }
});

test('a live Gemini run answers every quick prompt', function () {
    foreach (['purchase_first', 'deferrable', 'verify_first'] as $promptType) {
        $payload = $this->actingAs($this->custodian)
            ->postJson(route('api.custodian.reports.forecast.decision-support'), [
                'prompt_type' => $promptType,
            ])
            ->assertOk()
            ->json();

        fwrite(STDERR, "\n--- [{$promptType}] source=".$payload['source'].' provider_status='.$payload['provider_status']." ---\n");
        fwrite(STDERR, mb_substr((string) $payload['answer'], 0, 500)."\n");

        expect($payload['status'])->toBe('success')
            ->and($payload['answer'])->not->toBeEmpty();

        // This live test reports whether Gemini was reached; it does not assert
        // that every live reply passes grounding. A provider reply that cites an
        // unapproved number is a safe, expected degradation (the deterministic
        // suite covers those rules), so it skips rather than fails.
        if (($payload['provider_status'] ?? null) !== 'ok') {
            $this->markTestSkipped("Degraded for {$promptType}: {$payload['provider_status']}.");
        }
    }
});
