<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\ForecastPayload;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * Phase 8: the trained forecast must live in the database, not on a container's
 * local disk.
 *
 * On Render the cron job that trains and the web service that serves are
 * separate containers with separate ephemeral filesystems, so a forecast.json
 * written by one is invisible to the other and destroyed by the next deploy.
 * These tests pin the behaviour that makes scheduled retraining possible, and
 * keep the local-file path working as a fallback so existing installs and the
 * wider suite are unaffected.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->custodian = User::firstOrCreate(
        ['username' => 'phase8-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Phase',
            'last_name' => 'Eight',
            'email' => 'phase8@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $this->category = Category::firstOrCreate(
        ['category_name' => 'School Supplies'],
        ['requires_serial_number' => false],
    );

    $this->makeItem = function (int $inventoryId) {
        $item = new Inventory;
        // Inventory::$fillable omits the item_id primary key, so mass assignment
        // would silently drop it and the forecast row would fail validation.
        $item->forceFill([
            'item_id' => $inventoryId,
            'item_name' => 'Phase8 Bond Paper',
            'category_id' => $this->category->category_id,
            'unit' => 'ream',
            'user_id' => $this->custodian->id,
            'quantity' => 2,
            'unit_cost' => 100,
            'status' => 'available',
            'date_acquired' => '2026-06-01',
        ])->save();

        return $item;
    };
});

/**
 * Builds a minimal document that satisfies validateLivePayload() and
 * validateLiveItem(). The tests here are about storage and lifecycle, not model
 * quality, so the forecasts are hand-built rather than produced by Python.
 */
function phase8Document(string $generatedAt, int $inventoryId, int $categoryId): string
{
    return json_encode([
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => $generatedAt,
        'forecasts' => [[
            'inventory_id' => $inventoryId,
            'item_name' => 'Phase8 Bond Paper',
            'category_id' => $categoryId,
            'category' => 'School Supplies',
            'unit' => 'ream',
            'status' => 'success',
            'forecast_month' => '2026-10',
            'predicted_quantity' => 40,
            'confidence' => 'High',
            // Keys must match validateLiveItem() exactly; extra or missing keys
            // are rejected, so this mirrors what ml/forecast_demand.py emits.
            'model_version' => 'demand-linear-regression-v2',
            'history_window' => [
                'start_month' => '2026-06',
                'end_month' => '2026-09',
                'completeness' => 'complete',
            ],
            'monthly_usage' => [
                '2026-06' => 30,
                '2026-07' => 34,
                '2026-08' => 33,
                '2026-09' => 35,
            ],
            'months_used' => ['2026-06', '2026-07', '2026-08', '2026-09'],
            'verified_months_used' => 4,
            'required_months' => 3,
            'unknown_months' => [],
            'validation' => ['status' => 'time_ordered_holdout', 'months_tested' => 1, 'mae' => 2.5],
        ]],
        'insufficient_history' => [],
    ], JSON_THROW_ON_ERROR);
}

test('a trained forecast stored in the database is served with no file on disk', function () {
    Storage::fake('forecast');

    // Nothing on disk at all: this is the cross-container case, where the cron
    // job's container wrote to the database and the web container has a fresh
    // empty filesystem.
    expect(Storage::disk('forecast')->exists('forecast/forecast.json'))->toBeFalse();

    ($this->makeItem)(9001);

    app(StoredDemandForecastService::class)->persistTraining(
        ForecastPayload::SOURCE_LIVE,
        'success',
        phase8Document(now()->toIso8601String(), 9001, (int) $this->category->category_id),
    );

    $result = app(StoredDemandForecastService::class)->read($this->custodian);

    expect($result['status'])->toBe('success')
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['item_name'])->toBe('Phase8 Bond Paper');
});

test('the local file still works when the database has no forecast', function () {
    Storage::fake('forecast');

    ($this->makeItem)(9001);

    // The fallback must keep the existing file-based installs and fixtures
    // working, so this path cannot simply be dropped.
    Storage::disk('forecast')->put(
        'forecast/forecast.json',
        phase8Document(now()->toIso8601String(), 9001, (int) $this->category->category_id),
    );
    Storage::disk('forecast')->put('forecast/training-status.json', json_encode([
        'status' => 'success',
        'updated_at' => now()->toIso8601String(),
    ]));

    expect(ForecastPayload::query()->count())->toBe(0);

    $result = app(StoredDemandForecastService::class)->read($this->custodian);

    expect($result['status'])->toBe('success')
        ->and($result['rows'][0]['item_name'])->toBe('Phase8 Bond Paper');
});

test('a missing forecast reports missing rather than an empty success', function () {
    Storage::fake('forecast');

    expect(app(StoredDemandForecastService::class)->read($this->custodian)['status'])->toBe('missing');
});

test('a running or failed training run is never served as a result', function () {
    Storage::fake('forecast');

    ($this->makeItem)(9001);

    foreach (['running', 'failed'] as $status) {
        app(StoredDemandForecastService::class)->persistTraining(
            ForecastPayload::SOURCE_LIVE,
            $status,
            phase8Document(now()->toIso8601String(), 9001, (int) $this->category->category_id),
        );

        // A previous good document exists, but serving it as if it were current
        // would misrepresent stale figures as today's recommendation.
        expect(app(StoredDemandForecastService::class)->read($this->custodian)['status'])->toBe('failed');
    }
});

test('a stale database forecast is refused rather than displayed', function () {
    Storage::fake('forecast');

    ($this->makeItem)(9001);

    app(StoredDemandForecastService::class)->persistTraining(
        ForecastPayload::SOURCE_LIVE,
        'success',
        // Older than forecast.maximum_age_hours (168h by default).
        phase8Document(now()->subDays(30)->toIso8601String(), 9001, (int) $this->category->category_id),
    );

    expect(app(StoredDemandForecastService::class)->read($this->custodian)['status'])->toBe('stale');
});

test('retraining replaces the previous forecast instead of accumulating rows', function () {
    Storage::fake('forecast');

    foreach ([9001, 9002, 9003] as $inventoryId) {
        ($this->makeItem)($inventoryId);

        app(StoredDemandForecastService::class)->persistTraining(
            ForecastPayload::SOURCE_LIVE,
            'success',
            phase8Document(now()->toIso8601String(), $inventoryId, (int) $this->category->category_id),
        );
    }

    expect(ForecastPayload::query()->where('source_type', ForecastPayload::SOURCE_LIVE)->count())->toBe(1);

    $result = app(StoredDemandForecastService::class)->read($this->custodian);

    expect($result['rows'][0]['inventory_id'])->toBe(9003);
});

test('a corrupt stored document is refused instead of crashing the page', function () {
    Storage::fake('forecast');

    app(StoredDemandForecastService::class)->persistTraining(
        ForecastPayload::SOURCE_LIVE,
        'success',
        json_encode(['schema_version' => 2, 'forecasts' => []], JSON_THROW_ON_ERROR),
    );

    expect(app(StoredDemandForecastService::class)->read($this->custodian)['status'])->toBe('error');
});

test('decision support reads the same database forecast', function () {
    Storage::fake('forecast');

    ($this->makeItem)(9001);

    app(StoredDemandForecastService::class)->persistTraining(
        ForecastPayload::SOURCE_LIVE,
        'success',
        phase8Document(now()->toIso8601String(), 9001, (int) $this->category->category_id),
    );

    $payload = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.decision-support'), [
            'prompt_type' => 'purchase_first',
        ])
        ->assertOk()
        ->json();

    expect($payload['items'])->not->toBeEmpty()
        ->and($payload['items'][0]['item_name'])->toBe('Phase8 Bond Paper');
});
