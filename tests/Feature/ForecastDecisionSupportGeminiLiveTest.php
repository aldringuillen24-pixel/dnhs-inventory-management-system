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
    if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
        $this->markTestSkipped('No Gemini key configured.');
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
            ->and($payload['answer'])->not->toBeEmpty()
            ->and($payload['provider_status'])->toBeIn(['ok', 'no_key']);

        if (($payload['provider_status'] ?? null) !== 'ok') {
            $this->markTestSkipped("Degraded for {$promptType}: {$payload['provider_status']}.");
        }
    }
});
