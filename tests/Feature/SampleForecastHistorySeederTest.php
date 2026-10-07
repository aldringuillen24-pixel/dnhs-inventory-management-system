<?php

use App\Console\Commands\TrainDemandForecast;
use App\Models\Category;
use App\Models\ForecastPayload;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StoredDemandForecastService;
use App\Support\SampleForecastData;
use Database\Seeders\SampleForecastHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Two contracts for the sample forecast history.
 *
 * 1. The ledger must be real. The first generator wrote quantity_before and
 *    quantity_after as flat per-row constants, so 300 of its 400 movements
 *    opened at a level the previous month had not closed at, and every item
 *    recorded more usage issued than it held. A forecast built on that history
 *    reports "buy 20" against a stock level the history itself contradicts.
 *
 * 2. The rows must be invisible outside the demand-forecast screen. The model
 *    predicts for items in the inventory table because the "how many are left"
 *    figure behind every procurement suggestion lives there, so sample data has
 *    to exist as real rows -- which is exactly why it is hidden behind a global
 *    scope rather than left in the lists.
 */

function seedForecastCategories(): void
{
    // The sync_school_categories migration already inserts these, so reuse the
    // existing rows instead of inserting duplicates (category_name is unique).
    foreach ([
        ['Consumables', false],
        ['School Supplies', false],
        ['Furniture', false],
        ['ICT / Computer Equipment', true],
    ] as [$name, $serialized]) {
        Category::updateOrCreate(
            ['category_name' => $name],
            ['requires_serial_number' => $serialized],
        );
    }

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    User::firstOrCreate(
        ['username' => 'sample-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Sample',
            'last_name' => 'Custodian',
            'email' => 'sample-custodian@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );
}

/** The sample items, bypassing the scope that hides them. */
function sampleInventory()
{
    return Inventory::withoutGlobalScope(SampleForecastData::SCOPE)
        ->where('description', SampleForecastData::INVENTORY_MARKER);
}

/** The sample ledger for one item, oldest first, bypassing the scope. */
function sampleLedgerFor(int $itemId)
{
    return StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)
        ->where('inventory_id', $itemId)
        ->orderBy('created_at');
}

/** Reads the CSV the live exporter writes, using the real command. */
function exportLiveForecastCsv(): array
{
    Storage::fake('forecast');

    // Fail the Python step immediately; the CSV export happens before it.
    config(['forecast.python_binary' => 'not-a-real-python-binary']);

    Artisan::call('forecast:train');

    $path = Storage::disk('forecast')->path('forecast/real-stock-out-data.csv');
    expect(is_readable($path))->toBeTrue("Expected export at {$path}");

    $handle = fopen($path, 'r');
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);

    $header = array_shift($rows);

    return array_map(fn (array $row): array => array_combine($header, $row), $rows);
}

test('the sample seeder only writes demand-forecastable categories', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $categories = sampleInventory()->pluck('category_id')->unique();

    expect($categories)->not->toBeEmpty();

    foreach (Category::whereIn('category_id', $categories)->get() as $category) {
        expect($category->requires_serial_number)->toBeFalse();

        $name = strtolower($category->category_name);
        $matchesTerm = collect(TrainDemandForecast::DEMAND_CATEGORY_TERMS)
            ->contains(fn (string $term): bool => str_contains($name, $term));

        expect($matchesTerm)->toBeTrue("Category '{$category->category_name}' is not exportable.");
    }
});

test('the sample seeder generates four completed months of stock-out history', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $items = sampleInventory()->get();
    expect($items)->toHaveCount(100);

    $currentMonthStart = now()->startOfMonth();
    $expectedMonths = collect(range(4, 1))
        ->map(fn (int $monthsAgo): string => (clone $currentMonthStart)->subMonths($monthsAgo)->format('Y-m'));

    foreach ($items as $item) {
        $movements = sampleLedgerFor($item->item_id)
            ->where('movement_type', 'stock_out')
            ->get();

        // Every item has demand in each completed month, and never in the
        // current one (the exporter ignores the live month).
        expect($movements)->toHaveCount(4);

        $months = $movements
            ->map(fn (StockMovement $movement): string => Carbon::parse($movement->created_at)->format('Y-m'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        expect($months)->toEqualCanonicalizing($expectedMonths->sort()->values()->all());

        foreach ($movements as $movement) {
            expect((int) $movement->quantity)->toBeGreaterThan(0)
                ->and(Carbon::parse($movement->created_at))->toBeLessThan($currentMonthStart);
        }
    }

    // Enough history for the model's minimum, otherwise every row would be
    // reported as insufficient_history.
    expect((int) config('forecast.minimum_history'))->toBeLessThanOrEqual(4);
});

test('monthly quantities vary so the model learns a trend instead of a flat average', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $itemsWithTrend = 0;

    foreach (sampleInventory()->get() as $item) {
        $quantities = sampleLedgerFor($item->item_id)
            ->where('movement_type', 'stock_out')
            ->orderBy('created_at')
            ->pluck('quantity')
            ->all();

        expect(count(array_unique($quantities)))->toBeGreaterThan(1);

        if ($quantities !== array_values(array_reverse($quantities))) {
            $itemsWithTrend++;
        }
    }

    // Upward and downward trends must both exist, otherwise the sample set
    // would bias every recommendation in one direction.
    expect($itemsWithTrend)->toBeGreaterThan(0);
});

test('each month opens where the previous month closed', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    foreach (sampleInventory()->get() as $item) {
        $ledger = sampleLedgerFor($item->item_id)->get();

        // An opening stock_in funds the whole window.
        expect($ledger->first()->movement_type)->toBe('stock_in')
            ->and((int) $ledger->first()->quantity_before)->toBe(0);

        $previousClosing = null;

        foreach ($ledger as $movement) {
            if ($previousClosing !== null) {
                expect((int) $movement->quantity_before)
                    ->toBe($previousClosing, "Ledger for item {$item->item_id} does not chain.");
            }

            // A stock_in adds to the balance and a stock_out subtracts from
            // it. Asserting one rule for both would fail the opening row.
            $before = (int) $movement->quantity_before;
            $quantity = (int) $movement->quantity;
            $after = (int) $movement->quantity_after;

            $expected = $movement->movement_type === 'stock_in'
                ? $before + $quantity
                : $before - $quantity;

            expect($expected)
                ->toBe($after, "Row arithmetic is wrong for item {$item->item_id}.");

            $previousClosing = (int) $movement->quantity_after;
        }
    }
});

test('the quantity on the shelf equals what the ledger says is left', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    foreach (sampleInventory()->get() as $item) {
        $ledger = sampleLedgerFor($item->item_id)->get();
        $finalBalance = (int) $ledger->last()->quantity_after;

        expect((int) $item->quantity)
            ->toBe($finalBalance, "Shelf and ledger disagree for {$item->item_name}.")
            // Nothing may close below zero, which would mean the opening
            // balance did not actually fund the usage.
            ->toBeGreaterThanOrEqual(0);
    }
});

test('running the sample seeder twice creates no duplicates', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $itemsAfterFirst = sampleInventory()->count();
    $movementsAfterFirst = StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)->count();

    // 100 items x (1 opening stock_in + 4 monthly stock_out)
    expect($itemsAfterFirst)->toBe(100)
        ->and($movementsAfterFirst)->toBe(500);

    $this->seed(SampleForecastHistorySeeder::class);

    expect(sampleInventory()->count())->toBe($itemsAfterFirst)
        ->and(StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)->count())->toBe($movementsAfterFirst);
});

test('the live exporter includes the generated history with four verified months', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $rows = exportLiveForecastCsv();

    expect($rows)->not->toBeEmpty();

    // Only the eligible categories are exported; Furniture and the serialized
    // ICT category must not appear.
    $categories = collect($rows)->pluck('category')->unique()->values();
    expect($categories->sort()->values()->all())->toBe(['Consumables', 'School Supplies']);

    $monthlyUsage = collect($rows)
        ->groupBy('inventory_id')
        ->map(fn ($group) => $group->pluck('stock_out_date')
            ->map(fn (string $date): string => Carbon::parse($date)->format('Y-m'))
            ->unique()
            ->count());

    expect($monthlyUsage->min())->toBe(4)
        ->and($monthlyUsage->max())->toBe(4);

    // history_start_month / history_end_month must bracket completed months.
    $historyEnd = collect($rows)->pluck('history_end_month')->unique();
    expect($historyEnd->all())->toEqualCanonicalizing([now()->subMonth()->format('Y-m')]);

    // The opening stock_in must not be exported as demand.
    expect(collect($rows)->pluck('movement_type')->unique()->all())->toBe(['stock_out']);
});

// --- Visibility -------------------------------------------------------------

test('sample items and movements are hidden from ordinary queries', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    // A genuine item, so the assertions are not passing on an empty table.
    $real = Inventory::create([
        'category_id' => Category::where('category_name', 'Furniture')->value('category_id'),
        'user_id' => User::where('username', 'sample-custodian')->value('id'),
        'item_name' => 'Genuine Wooden Desk',
        'unit' => 'piece',
        'quantity' => 7,
        'unit_cost' => 500,
        'status' => 'available',
        'date_acquired' => '2026-01-15',
    ]);

    expect(sampleInventory()->count())->toBe(100);
    expect(StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)->count())->toBe(500);

    // Nothing sample-shaped is reachable through the models.
    expect(Inventory::count())->toBe(1)
        ->and(Inventory::where('description', SampleForecastData::INVENTORY_MARKER)->count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(StockMovement::where('notes', 'like', SampleForecastData::MOVEMENT_PREFIX.'%')->count())->toBe(0);

    // The legacy marker format is hidden too. "sample-forecast-history:" does
    // not start with "sample-forecast:", so a scope built on the new prefix
    // alone would leak these.
    //
    // This goes on a second item rather than the genuine one, because the scope
    // defines an item as sample data when it carries a sample movement. Parking
    // a legacy marker on the genuine desk would, correctly, hide the desk.
    $other = Inventory::create([
        'category_id' => Category::where('category_name', 'Furniture')->value('category_id'),
        'user_id' => User::where('username', 'sample-custodian')->value('id'),
        'item_name' => 'Genuine Bookshelf',
        'unit' => 'piece',
        'quantity' => 2,
        'unit_cost' => 300,
        'status' => 'available',
        'date_acquired' => '2026-01-15',
    ]);

    $legacy = StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)->create([
        'inventory_id' => $other->item_id,
        'movement_type' => 'stock_in',
        'quantity' => 3,
        'notes' => SampleForecastData::LEGACY_MOVEMENT_PREFIX.'2026-06',
    ]);

    expect($legacy->exists)->toBeTrue();
    expect(StockMovement::where('notes', 'like', SampleForecastData::LEGACY_MOVEMENT_PREFIX.'%')->count())->toBe(0);

    // Only the seeder ever writes those markers, so "carries a sample movement"
    // and "is sample data" are the same set. The bookshelf now carries one and
    // is therefore hidden; the desk, which never did, is still listed.
    expect(Inventory::pluck('item_name')->all())->toBe(['Genuine Wooden Desk']);
});

test('sample stock never inflates the totals a dashboard reports', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    Inventory::create([
        'category_id' => Category::where('category_name', 'Furniture')->value('category_id'),
        'user_id' => User::where('username', 'sample-custodian')->value('id'),
        'item_name' => 'Genuine Wooden Desk',
        'unit' => 'piece',
        'quantity' => 7,
        'unit_cost' => 500,
        'status' => 'available',
        'date_acquired' => '2026-01-15',
    ]);

    // The metric shape the custodian dashboard and the AI assistant both use.
    expect(Inventory::where('status', '!=', 'disposed')->sum('quantity'))->toBe(7)
        ->and(Inventory::count())->toBe(1)
        ->and(Inventory::with('category')->count())->toBe(1)
        ->and(Inventory::with('assignedTo', 'category')->count())->toBe(1)
        ->and(Inventory::select('unit')->distinct()->pluck('unit')->all())->toBe(['piece']);

    // And the whole sample set is still there behind the scope.
    expect(Inventory::where('status', '!=', 'disposed')
        ->withoutGlobalScope(SampleForecastData::SCOPE)->sum('quantity'))->toBeGreaterThan(7);
});

test('the forecast screen still sees the sample rows it exists to display', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $custodian = User::where('username', 'sample-custodian')->first();
    $item = sampleInventory()->where('category_id', Category::where('category_name', 'Consumables')->value('category_id'))->first();

    app(StoredDemandForecastService::class)->persistTraining(
        ForecastPayload::SOURCE_LIVE,
        'success',
        json_encode([
            'schema_version' => 2,
            'source_type' => 'live',
            'model_version' => 'demand-linear-regression-v2',
            'generated_at' => now()->toIso8601String(),
            'forecasts' => [[
                'inventory_id' => $item->item_id,
                'item_name' => $item->item_name,
                'category_id' => $item->category_id,
                'category' => 'Consumables',
                'unit' => 'piece',
                'status' => 'success',
                'forecast_month' => now()->format('Y-m'),
                'predicted_quantity' => 12,
                'confidence' => 'Low',
                'model_version' => 'demand-linear-regression-v2',
                'history_window' => [
                    'start_month' => now()->subMonths(4)->format('Y-m'),
                    'end_month' => now()->subMonth()->format('Y-m'),
                    'completeness' => 'incomplete',
                ],
                'monthly_usage' => [
                    now()->subMonths(4)->format('Y-m') => 8,
                    now()->subMonths(3)->format('Y-m') => 9,
                    now()->subMonths(2)->format('Y-m') => 10,
                    now()->subMonth()->format('Y-m') => 11,
                ],
                'months_used' => [
                    now()->subMonths(4)->format('Y-m'),
                    now()->subMonths(3)->format('Y-m'),
                    now()->subMonths(2)->format('Y-m'),
                    now()->subMonth()->format('Y-m'),
                ],
                'verified_months_used' => 4,
                'required_months' => 3,
                'unknown_months' => [],
                'validation' => ['status' => 'time_ordered_holdout', 'months_tested' => 1, 'mae' => 1.5],
            ]],
            'insufficient_history' => [],
        ], JSON_THROW_ON_ERROR),
    );

    $result = app(StoredDemandForecastService::class)->read($custodian);

    // Without the bypass this is the empty-forecast failure: every row looks
    // like an orphaned item and the document is refused.
    expect($result['status'])->toBe('success')
        ->and($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['item_name'])->toBe($item->item_name)
        // available_stock is read from the item, which is the whole reason the
        // sample rows have to exist at all.
        ->and((int) $result['rows'][0]['available_stock'])->toBe((int) $item->quantity);
});

test('purging removes every sample row and leaves genuine stock alone', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $real = Inventory::create([
        'category_id' => Category::where('category_name', 'Furniture')->value('category_id'),
        'user_id' => User::where('username', 'sample-custodian')->value('id'),
        'item_name' => 'Genuine Wooden Desk',
        'unit' => 'piece',
        'quantity' => 7,
        'unit_cost' => 500,
        'status' => 'available',
        'date_acquired' => '2026-01-15',
    ]);

    StockMovement::create([
        'inventory_id' => $real->item_id,
        'movement_type' => 'stock_in',
        'quantity' => 7,
        'notes' => 'Genuine delivery',
    ]);

    expect(Artisan::call('forecast:purge-sample-history'))->toBe(0);

    // Nothing sample-shaped survives, seen without the scope so the check
    // cannot pass merely because the rows are hidden.
    expect(Inventory::withoutGlobalScope(SampleForecastData::SCOPE)
        ->where('description', SampleForecastData::INVENTORY_MARKER)->count())->toBe(0);
    expect(StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)
        ->where('notes', 'like', SampleForecastData::MOVEMENT_PREFIX.'%')->count())->toBe(0);

    // Genuine rows are untouched, movements included.
    expect(Inventory::withoutGlobalScope(SampleForecastData::SCOPE)->count())->toBe(1);
    expect(StockMovement::withoutGlobalScope(SampleForecastData::SCOPE)->count())->toBe(1);

    // And it is safe to run twice.
    expect(Artisan::call('forecast:purge-sample-history'))->toBe(0);
});
