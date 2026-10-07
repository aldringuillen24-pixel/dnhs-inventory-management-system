<?php

namespace App\Support;

/**
 * Single definition of the markers that identify sample forecast data.
 *
 * The demand-forecast model can only predict for items that exist in the
 * `inventory` table, because the "how many are left" figure that drives the
 * suggested procurement quantity lives on the item itself. A fresh
 * installation therefore has no forecast at all: there is no history to learn
 * from and no item to attach the prediction to.
 *
 * SampleForecastHistorySeeder fills that gap by writing genuinely consistent
 * sample stock and stock-out history. Those rows are real rows in real tables,
 * so without something to separate them they would appear in the inventory
 * list, inflate stock counts, show up in the custodian and school-head
 * dashboards, populate the audit ledger, and be reported by the AI assistant
 * as though they described real property.
 *
 * Both models carry a global scope built from the constants here, which is why
 * the markers live in one place: the seeder that writes them, the scopes that
 * hide them, and the purge command that removes them can never disagree about
 * what counts as sample data.
 *
 * An inventory row is treated as sample data when it carries at least one
 * sample movement. Only the seeder ever writes those markers, so "carries a
 * sample movement" and "is sample data" are the same set, and no schema change
 * is needed to add a marker column -- which matters because a scope naming a
 * column that does not exist would break every inventory query on an
 * installation that has not run the migration.
 *
 * The one place sample data is allowed to be seen is the demand-forecast
 * screen itself, and the training that produces it. Both opt out explicitly
 * with withoutGlobalScope() using SCOPE below.
 */
final class SampleForecastData
{
    /**
     * Name of the Eloquent global scope added to Inventory and StockMovement.
     *
     * Call sites that legitimately need sample rows opt out by name, so a
     * rename here fails loudly at every opt-out site rather than silently
     * leaving rows visible.
     */
    public const SCOPE = 'exclude_sample_forecast_data';

    /**
     * Human-readable label written to inventory.description for every sample
     * item, so the row is self-explanatory to anyone reading the table.
     *
     * Deliberately NOT what the global scope filters on. The scope uses the
     * ledger instead (an item is sample data when it carries a sample
     * movement), because AiAssistantLocalFactsTest requires that no ordinary
     * inventory query mention the description column, so the assistant cannot
     * answer from an internal receiving note. Filtering on description would put
     * that column into every inventory query in the app.
     */
    public const INVENTORY_MARKER = 'Sample stock used to train the demand-forecast model.';

    /**
     * Prefix written to stock_movements.notes for every sample movement.
     *
     * Shared by the opening-balance row and each monthly stock-out so one
     * NOT LIKE hides all of them.
     */
    public const MOVEMENT_PREFIX = 'sample-forecast:';

    /**
     * Prefix used by the first generator, before the ledger was made to chain.
     *
     * Kept so rows written by that version stay hidden and stay purgeable. It is
     * matched with its own LIKE rather than folded into MOVEMENT_PREFIX because
     * "sample-forecast-history:" does not start with "sample-forecast:" -- the
     * hyphen breaks the prefix match, and an OR of two NOT LIKEs is how a row
     * ends up visible again.
     */
    public const LEGACY_MOVEMENT_PREFIX = 'sample-forecast-history:';

    /** Notes value for the opening balance that starts an item's ledger. */
    public const OPENING_BALANCE_NOTES = self::MOVEMENT_PREFIX.'opening-balance';

    /**
     * Notes value for the stock-out recorded in a given month.
     */
    public static function monthNotes(string $month): string
    {
        return self::MOVEMENT_PREFIX.$month;
    }

    /**
     * Whether a notes value belongs to sample data.
     *
     * The pre-repair generator wrote "sample-forecast-history:YYYY-MM". Those
     * rows no longer exist, but the purge command still recognises them so an
     * interrupted migration can be cleaned up.
     */
    public static function isSampleMovementNotes(?string $notes): bool
    {
        return is_string($notes)
            && (str_starts_with($notes, self::MOVEMENT_PREFIX)
                || str_starts_with($notes, self::LEGACY_MOVEMENT_PREFIX));
    }

    /**
     * Whether an inventory description marks the row as sample data.
     */
    public static function isSampleInventory(?string $description): bool
    {
        return $description === self::INVENTORY_MARKER;
    }
}
