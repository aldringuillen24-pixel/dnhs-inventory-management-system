<?php

use App\Console\Commands\TrainDemandForecast;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\SampleForecastHistorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Phase 5 contract: the sample history seeder must produce data the live
 * exporter will actually read, so `php artisan forecast:train` yields real
 * forecast rows instead of an empty or insufficient_history result.
 *
 * The export is exercised through the real command (with a bogus Python binary
 * so the subprocess fails fast after the CSV is written) rather than by
 * re-implementing the filter here.
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

    $categories = Inventory::pluck('category_id')->unique();

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

    $items = Inventory::all();
    expect($items)->toHaveCount(100);

    $currentMonthStart = now()->startOfMonth();
    $expectedMonths = collect(range(4, 1))
        ->map(fn (int $monthsAgo): string => (clone $currentMonthStart)->subMonths($monthsAgo)->format('Y-m'));

    foreach ($items as $item) {
        $movements = StockMovement::where('inventory_id', $item->item_id)
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

    foreach (Inventory::all() as $item) {
        $quantities = StockMovement::where('inventory_id', $item->item_id)
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

test('running the sample seeder twice creates no duplicates', function () {
    seedForecastCategories();

    $this->seed(SampleForecastHistorySeeder::class);

    $itemsAfterFirst = Inventory::count();
    $movementsAfterFirst = StockMovement::count();

    expect($itemsAfterFirst)->toBe(100)
        ->and($movementsAfterFirst)->toBe(400);

    $this->seed(SampleForecastHistorySeeder::class);

    expect(Inventory::count())->toBe($itemsAfterFirst)
        ->and(StockMovement::count())->toBe($movementsAfterFirst);
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
});