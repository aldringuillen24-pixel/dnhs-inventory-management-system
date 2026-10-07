<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Support\SampleForecastData;
use Illuminate\Console\Command;

/**
 * Removes exactly the rows created by SampleForecastHistorySeeder, and nothing
 * else.
 *
 * Both models hide sample rows behind a global scope, so every query here opts
 * back out -- otherwise the command would report "nothing to remove" while the
 * rows it was written to delete were still sitting there.
 *
 * Movements are removed before inventory because stock_movements.inventory_id
 * is a foreign key; deleting in the other order would fail.
 *
 * The trained forecast is left in place. Re-run `php artisan forecast:train`
 * afterwards so the prediction no longer covers rows that are gone.
 */
class PurgeSampleForecastHistory extends Command
{
    protected $signature = 'forecast:purge-sample-history
                            {--dry-run : Report what would be removed without deleting it}';

    protected $description = 'Delete the sample inventory and stock history created for forecast training';

    public function handle(): int
    {
        $movements = $this->movements()->count();
        $itemIds = $this->sampleItemIds();
        $items = $itemIds->count();

        if ($movements === 0 && $items === 0) {
            $this->components->info('No sample forecast history found. Nothing to remove.');

            return self::SUCCESS;
        }

        $this->table(
            ['table', 'rows'],
            [
                ['stock_movements', $movements],
                ['inventory', $items],
            ]
        );

        if ($this->option('dry-run')) {
            $this->components->info('Dry run only. Re-run without --dry-run to delete these rows.');

            return self::SUCCESS;
        }

        $this->components->warn('Deleting sample forecast history.');

        // The item ids are collected before the movements go, because the
        // sample items are identified by having sample movements -- deleting the
        // movements first would erase the only way to find them again.
        $deletedMovements = $this->movements()->delete();

        $deletedItems = $itemIds->isEmpty()
            ? 0
            : Inventory::query()
                ->withoutGlobalScope(SampleForecastData::SCOPE)
                ->whereKey($itemIds->all())
                ->delete();

        $this->components->info(sprintf(
            'Removed %d movement(s) and %d inventory item(s).',
            $deletedMovements,
            $deletedItems,
        ));

        $this->components->info('Run `php artisan forecast:train` to rebuild the forecast without them.');

        return self::SUCCESS;
    }

    /**
     * Ids of the items the global scope hides, found the same way it finds
     * them: by carrying sample movements. Deriving this from a different
     * signal than the scope uses is how the two would drift apart and leave
     * rows behind.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function sampleItemIds()
    {
        return $this->movements()->distinct()->pluck('inventory_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<StockMovement>
     */
    private function movements()
    {
        return StockMovement::query()
            ->withoutGlobalScope(SampleForecastData::SCOPE)
            ->where(fn ($inner) => $inner
                ->where('notes', 'like', SampleForecastData::MOVEMENT_PREFIX.'%')
                ->orWhere('notes', 'like', SampleForecastData::LEGACY_MOVEMENT_PREFIX.'%'));
    }


}
