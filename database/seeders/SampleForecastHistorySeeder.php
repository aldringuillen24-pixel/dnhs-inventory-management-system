<?php

namespace Database\Seeders;

use App\Console\Commands\TrainDemandForecast;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\SampleForecastData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Generates sample stock history so the live demand-forecast model has
 * something to learn from on a fresh deployment.
 *
 * Deliberately standalone: it is NOT registered in DatabaseSeeder, so it never
 * runs from production boot or from any deployment. Run it on purpose:
 *
 *   php artisan db:seed --class=SampleForecastHistorySeeder
 *
 * The rows are invisible everywhere except the demand-forecast screen, which
 * is why this data exists at all. The model predicts for items in the
 * `inventory` table because the "how many are left" figure behind every
 * suggested procurement quantity lives there; a fresh installation has no such
 * items and therefore no forecast. See SampleForecastData.
 *
 * Every generated row satisfies the live exporter's eligibility rules in
 * TrainDemandForecast::eligibleDemandMovements():
 *   - movement_type "stock_out" (always accepted, no assignment linkage needed)
 *   - quantity > 0
 *   - created_at strictly before the start of the current month, so only
 *     completed months become demand history
 *   - categories are non-serialized and match DEMAND_CATEGORY_TERMS
 *
 * THE LEDGER IS REAL. Each item opens with a stock_in for enough to cover all
 * of its usage plus a chosen leftover, then each month's stock_out is chained
 * off the previous month's closing balance, and the item's quantity is set to
 * the final balance. The first version of this seeder wrote
 * quantity_before/quantity_after as flat per-row constants, so 300 of its 400
 * movements claimed to start at a level the previous month had not ended at,
 * and every item recorded more usage issued than it held. The forecast then
 * reported "buy 20" against a stock level that the history itself contradicted.
 *
 * The opening stock_in is not demand -- stock_in is not an eligible movement
 * type -- so it never reaches the model and cannot skew the prediction.
 *
 * Idempotent: keyed on item_name + category_id for inventory and on
 * inventory_id + notes for movements, and the chained values are rewritten on
 * every run, so re-running converges on a correct ledger rather than
 * accumulating duplicates.
 *
 * Remove with: php artisan forecast:purge-sample-history
 */
class SampleForecastHistorySeeder extends Seeder
{
    /** Completed months of history to generate. */
    private const MONTHS = 4;

    /** Items generated per eligible category. */
    private const ITEMS_PER_CATEGORY = 50;

    /** Fixed seed so repeated runs produce identical sample data. */
    private const RANDOM_SEED = 20261006;

    /** Item catalogue per category slug. */
    private const CATALOGUE = [
        'consumables' => [
            'Bond Paper A4 80gsm', 'Bond Paper Legal 80gsm', 'Bond Paper A3 80gsm',
            'Bond Paper Colored A4', 'Paper Bond Long 2 inch', 'Photopaper A4 Glossy',
            'Whiteboard Marker Blue', 'Whiteboard Marker Black', 'Whiteboard Marker Red',
            'Permanent Marker Black', 'Text Marker Set', 'Highlighter Yellow',
            'Ballpen Blue', 'Ballpen Black', 'Ballpen Red', 'Gel Pen Blue',
            'Pencil HB #2', 'Mechanical Pencil 0.5mm', 'Eraser White',
            'Crayon Set 24 Colors', 'Colored Pencil Set', 'Sharpener Metal',
            'Ruler 30cm', 'Scissors 8 inch', 'Paste-up Glue Stick', 'Liquid Glue 100ml',
            'Double Sided Tape 1 inch', 'Transparent Tape 2 inch', 'Masking Tape 1 inch',
            'Cartridge Pen Black', 'Cartridge Pen Blue', 'Ink Bottle Black 30ml',
            'Correction Pen', 'Notebook 80 lvs', 'Notebook 100 lvs',
            'Paper Notebook 40 lvs', 'Bond Envelope #10', 'Bond Envelope #12',
            'Document Folder Legal', 'Document Folder Letter', 'Expansion Folder',
            'Paper Clip 1 inch', 'Binder Clip 2 inch', 'Fastener Bar 1 inch',
            'Rubber Band 250g', 'Toilet Paper 2 ply', 'Alcohol 500ml',
            'Hand Soap 250ml', 'Detergent Powder 1kg', 'Wet Wipes Sachet',
        ],
        'supplies' => [
            'Bond Paper Short A4', 'Bond Paper Folio', 'Manila Paper A4',
            'Cartridge Ink Black 60ml', 'Cartridge Ink Cyan 60ml', 'Cartridge Ink Magenta 60ml',
            'Toner Cartridge HP 26A', 'Toner Cartridge Canon 057', 'Toner Cartridge Brother TN-2425',
            'Photo Drum Unit', 'Thermal Paper Roll 80mm', 'Thermal Paper Roll 58mm',
            'Laminating Pouch A4', 'Laminating Pouch A5', 'Laminating Pouch Photo',
            'Cleaning Cloth Microfiber', 'Cleanser Multi-Purpose 1L', 'Floor Cleaner 1L',
            'Glass Cleaner 750ml', 'Disinfectant Spray 500ml', 'Hand Sanitizer 500ml',
            'Trash Bag Large', 'Trash Bag Medium', 'Trash Bag Small',
            'Paper Towel Roll', 'Air Freshener 300ml', 'Mop Head Cotton',
            'Broom Stick', 'Dustpan Set', 'Mop Handle Aluminum',
            'Ballpen Refill Blue', 'Ballpen Refill Black', 'Gel Refill Blue',
            'Pencil Lead 2B', 'Whiteboard Eraser', 'Board Duster',
            'Stapler Heavy Duty', 'Stapler Pins No. 24', 'Stapler Pins No. 26',
            'Paper Fastener 1 inch', 'Ruler 15cm Wooden', 'Drawing Paper A4',
            'Art Paper A4', 'Carton Sheet', 'Rope Twine',
            'Match Box', 'Safety Pin Box', 'Button Cell Pack',
            'Eraser Dustless 2B', 'Pencil Sharpener Handheld',
        ],
    ];

    public function run(): void
    {
        $actor = $this->resolveActor();

        if ($actor === null) {
            $this->command?->error('No user account exists yet. Run RoleSeeder (or the admin bootstrap) first.');

            return;
        }

        $categories = $this->eligibleCategories();

        if ($categories->isEmpty()) {
            $this->command?->error('No demand-forecastable categories found. Check category names against DEMAND_CATEGORY_TERMS.');

            return;
        }

        mt_srand(self::RANDOM_SEED);

        $window = $this->historyWindow();
        $itemsCreated = 0;
        $movementsWritten = 0;

        foreach ($categories as $category) {
            foreach ($this->catalogueFor($category) as $index => $name) {
                $usage = $this->usageSeries($index);
                $leftover = $this->leftoverFor($usage);

                $item = $this->upsertInventory(
                    $category,
                    $name,
                    $index,
                    $window->first(),
                    // The leftover, not the opening balance. The opening figure
                    // funds the whole window and is already recorded as the
                    // stock_in; storing it here made every item look like it
                    // still held four months of stock, which understates the
                    // procurement gap the forecast is supposed to report.
                    $leftover,
                );

                if ($item->wasRecentlyCreated) {
                    $itemsCreated++;
                }

                $movementsWritten += $this->writeLedger($item, $actor, $window, $usage, $leftover);
            }
        }

        $this->command?->info(sprintf(
            'Sample forecast history ready: %d item(s), %d movement(s) across %d completed month(s).',
            $itemsCreated,
            $movementsWritten,
            $window->count(),
        ));
        $this->command?->info('These rows are hidden from inventory, reports, dashboards and the AI.');
        $this->command?->info('Visible only on the Demand Forecast screen. Now run: php artisan forecast:train');
    }

    /**
     * Categories the live exporter will actually read: non-serialized and
     * matching a demand term. Resolved from the command's own term list so the
     * two can never disagree.
     *
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function eligibleCategories()
    {
        return Category::query()
            ->where('requires_serial_number', false)
            ->get()
            ->filter(function (Category $category): bool {
                $name = strtolower((string) $category->category_name);

                foreach (TrainDemandForecast::DEMAND_CATEGORY_TERMS as $term) {
                    if (str_contains($name, $term)) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy('category_id')
            ->values();
    }

    /**
     * The completed months, oldest first.
     *
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    private function historyWindow()
    {
        $currentMonthStart = now()->startOfMonth();

        return collect(range(self::MONTHS, 1))
            ->map(fn (int $monthsAgo): Carbon => (clone $currentMonthStart)->subMonths($monthsAgo));
    }

    /**
     * @return array<int, string>
     */
    private function catalogueFor(Category $category): array
    {
        $name = strtolower((string) $category->category_name);

        foreach (self::CATALOGUE as $slug => $names) {
            if (str_contains($name, $slug === 'supplies' ? 'suppl' : 'consumable')) {
                return array_slice($names, 0, self::ITEMS_PER_CATEGORY);
            }
        }

        // An eligible category added later (e.g. widened terms) still gets
        // history instead of silently contributing nothing.
        return array_map(
            fn (int $index): string => sprintf('%s Sample Item %02d', $category->category_name, $index + 1),
            range(1, self::ITEMS_PER_CATEGORY)
        );
    }

    /**
     * Per-item monthly demand series with a gentle trend, so the regression
     * learns a slope instead of a flat average.
     *
     * @return array<int, int> Quantities oldest month first.
     */
    private function usageSeries(int $index): array
    {
        $base = 4 + (($index * 7) % 17);
        $trend = (($index % 5) - 2); // -2 .. +2 per month

        $series = [];
        for ($monthIndex = 0; $monthIndex < self::MONTHS; $monthIndex++) {
            $series[] = max(1, $base + ($trend * $monthIndex) + (($index + $monthIndex) % 3));
        }

        return $series;
    }

    /**
     * Units left on hand after the last month of usage.
     *
     * Deliberately tight and varied. When stock sits far above monthly demand the
     * procurement maths (forecast + safety stock - on hand - pending) yields zero
     * for every item, so the forecast panel and chat would have nothing to
     * recommend. Spanning roughly 0 to 1.3 months of cover produces a realistic
     * mix of urgent, soon, and comfortable items.
     *
     * @param  array<int, int>  $usage
     */
    private function leftoverFor(array $usage): int
    {
        $lastMonthUsage = (int) end($usage);
        $cover = mt_rand(0, 130) / 100; // 0.00 .. 1.30 months of cover

        return max(0, (int) round($lastMonthUsage * $cover));
    }

    /**
     * Opening balance that funds every month of usage and still ends on the
     * chosen leftover, so no month can ever close below zero.
     *
     * @param  array<int, int>  $usage
     */
    private function openingBalanceFor(array $usage, int $leftover): int
    {
        return array_sum($usage) + $leftover;
    }

    private function upsertInventory(
        Category $category,
        string $name,
        int $index,
        Carbon $acquired,
        int $quantity,
    ): Inventory {
        $unitCost = 15 + (($index * 37) % 180) + 0.5;

        // withoutGlobalScope so a re-run finds the rows it wrote previously
        // instead of creating a second copy of every item.
        $item = Inventory::query()
            ->withoutGlobalScope(SampleForecastData::SCOPE)
            ->firstOrNew([
                'item_name' => $name,
                'category_id' => $category->category_id,
            ]);

        if (! $item->exists) {
            $item->fill([
                'unit' => 'piece',
                'user_id' => $this->actor->id,
                'unit_cost' => $unitCost,
                'status' => 'available',
                'date_acquired' => $acquired->toDateString(),
                'lifespan_years' => null,
                'description' => SampleForecastData::INVENTORY_MARKER,
                'building' => 'Main Building',
            ]);
        }

        // Always restated so the ledger and the shelf cannot drift apart on a
        // re-run.
        $item->quantity = $quantity;

        $item->save();

        return $item;
    }

    /**
     * Writes the opening balance then one chained stock-out per month.
     *
     * @param  iterable<int, Carbon>  $months  Completed months, oldest first.
     * @param  array<int, int>  $usage  Quantities oldest first.
     * @return int Movements created.
     */
    private function writeLedger(Inventory $item, User $actor, iterable $months, array $usage, int $leftover): int
    {
        $created = 0;
        $months = collect($months);
        $first = $months->first();

        $opening = $this->openingBalanceFor($usage, $leftover);
        $openingAt = $first->copy()->subDay()->setTime(8, 0);

        if ($this->putMovement($item, $actor, SampleForecastData::OPENING_BALANCE_NOTES, 'stock_in', $opening, 0, $opening, $openingAt)) {
            $created++;
        }

        $balance = $opening;

        foreach ($months as $monthIndex => $month) {
            $quantity = (int) ($usage[$monthIndex] ?? end($usage));
            $before = $balance;
            $balance -= $quantity; // never negative: $opening covers all usage plus leftover

            $day = 5 + (($item->item_id * 3) % 20);
            $occurredAt = $month->copy()->addDays($day)->setTime(9, 0);

            if ($this->putMovement(
                $item,
                $actor,
                SampleForecastData::monthNotes($month->format('Y-m')),
                'stock_out',
                $quantity,
                $before,
                $balance,
                $occurredAt,
            )) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Inserts or corrects one ledger row, backdating created_at because the
     * exporter groups demand by that column.
     *
     * @return bool Whether the row was newly created.
     */
    private function putMovement(
        Inventory $item,
        User $actor,
        string $notes,
        string $type,
        int $quantity,
        int $before,
        int $after,
        Carbon $occurredAt,
    ): bool {
        $movement = StockMovement::query()
            ->withoutGlobalScope(SampleForecastData::SCOPE)
            ->firstOrNew(['inventory_id' => $item->item_id, 'notes' => $notes]);

        $movement->fill([
            'user_id' => $actor->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'notes' => $notes,
        ]);

        $isNew = ! $movement->exists;
        $movement->save();

        $movement->forceFill(['created_at' => $occurredAt, 'updated_at' => $occurredAt])->saveQuietly();

        return $isNew;
    }

    private ?User $actor = null;

    private function resolveActor(): ?User
    {
        if ($this->actor !== null) {
            return $this->actor;
        }

        $custodianRole = Role::where('role_name', 'Property Custodian')->first();

        $this->actor = ($custodianRole ? User::where('role_id', $custodianRole->role_id)->first() : null)
            ?? User::query()->orderBy('id')->first();

        return $this->actor;
    }
}
