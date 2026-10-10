<?php

namespace App\Support;

use App\Models\Inventory;
use Illuminate\Support\Collection;

/**
 * Decides whether an inventory record can satisfy a requestable item type.
 *
 * A request identifies an item by NAME + CATEGORY + UNIT, never by `item_id`,
 * because an end user requests an item *type* and the custodian allocates a
 * concrete record later. Those three must match, but three real-world sources of
 * legitimate mismatch are tolerated:
 *
 *  1. **`item_id IS NULL`.** Not a mismatch to tolerate — it is the normal shape
 *     of an item-type request. Matching is by the triple, which is why this
 *     class exists instead of a `where item_id` join.
 *  2. **Singular vs plural.** An end user typing into a free-text field writes
 *     "chair"; the catalogue says "Chairs". Both name the same requestable thing.
 *  3. **Unit placeholder.** When an end user names an item that was never
 *     catalogued there is no unit to record, so the generic placeholder is
 *     stored instead. A placeholder must not narrow which stock can satisfy the
 *     request.
 *
 * CATEGORY is never loosened. Two items sharing a name in different categories
 * are deliberately different things, and the existing tests depend on that.
 *
 * This class is the single authority for all three decisions that must agree:
 * whether a request has stock (storeRequest), what the custodian is shown
 * (transactions), and whether a selected record is a valid allocation
 * (approveItemTypeRequest). If they ever disagreed, the UI would promise an
 * Assign that the server then refuses.
 */
final class RequestableItemMatcher
{
    /**
     * Units that carry no information about the thing being requested. A
     * request recorded with one of these matches any unit.
     */
    private const GENERIC_UNITS = ['unit', 'units', 'pcs', 'pc', 'quantity'];

    /**
     * Fold spelling differences that describe the same requestable item.
     *
     * Deliberately narrow: trailing plural only, and only when a plausible stem
     * remains. Anything more aggressive ("chair" vs "chairs" is wanted;
     * "chair" vs "chaises" is not) would merge items the user must be able to
     * choose between.
     */
    public static function normaliseName(string $name): string
    {
        $normalised = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));

        // "boxes" -> "box", "buses" -> "bus", "glasses" -> "glass"
        if (mb_strlen($normalised) > 3 && str_ends_with($normalised, 'es')) {
            return mb_substr($normalised, 0, -2);
        }

        // "chairs" -> "chair". Guarded so "glass" is not folded to "gla" and
        // a bare "s" name is not emptied out.
        if (mb_strlen($normalised) > 3 && str_ends_with($normalised, 's') && ! str_ends_with($normalised, 'ss')) {
            return mb_substr($normalised, 0, -1);
        }

        return $normalised;
    }

    public static function nameMatches(string $requested, string $candidate): bool
    {
        $requested = self::normaliseName($requested);

        return $requested !== '' && $requested === self::normaliseName($candidate);
    }

    /**
     * A generic placeholder, or a blank, means the unit was never known.
     */
    public static function unitIsGeneric(?string $unit): bool
    {
        $unit = mb_strtolower(trim((string) $unit));

        return $unit === '' || in_array($unit, self::GENERIC_UNITS, true);
    }

    public static function unitMatches(?string $requested, ?string $candidate): bool
    {
        if (self::unitIsGeneric($requested)) {
            return true;
        }

        return self::normaliseName((string) $requested) === self::normaliseName((string) $candidate);
    }

    /**
     * Whether this inventory record is a valid fulfillment candidate.
     */
    public static function matches(string $name, int $categoryId, ?string $unit, Inventory $inventory): bool
    {
        return (int) $inventory->category_id === $categoryId
            && self::nameMatches($name, (string) $inventory->item_name)
            && self::unitMatches($unit, $inventory->unit);
    }

    /**
     * Records that could fulfill the request, cheapest-to-select first.
     *
     * Category is filtered in SQL because it is exact; name and unit are
     * filtered here because they are normalised. A category holds few item
     * types, so this stays cheap.
     *
     * @return Collection<int, Inventory>
     */
    public static function candidates(string $name, int $categoryId, ?string $unit): Collection
    {
        return Inventory::query()
            ->where('category_id', $categoryId)
            ->orderBy('item_id')
            ->get()
            ->filter(fn (Inventory $inventory): bool => self::matches($name, $categoryId, $unit, $inventory))
            ->values();
    }

    /**
     * Available quantity that could actually be issued right now.
     *
     * Used by storeRequest to decide between the approval queue and unmet
     * demand, so it must agree exactly with what the custodian is later shown.
     */
    public static function availableQuantity(string $name, int $categoryId, ?string $unit): int
    {
        return (int) self::candidates($name, $categoryId, $unit)
            ->filter(fn (Inventory $inventory): bool => $inventory->status === 'available' && (int) $inventory->quantity > 0)
            ->sum('quantity');
    }

    /**
     * Quantity already spoken for by requests sitting in `waiting for approval`.
     *
     * Soft hold only: stock rows are NOT deducted until approval (see
     * InventoryOperationService::approveAssignment), so without this the UI
     * shows 30 available while 20 are already promised and a second 20 can be
     * queued. Displayed as "Available X, Pending Y"; validation uses
     * available minus pending. Terminal states (approved/declined/cancelled)
     * leave this pool automatically because the status changes.
     */
    public static function pendingQuantity(string $name, int $categoryId, ?string $unit): int
    {
        return (int) \App\Models\AssignmentRequest::query()
            ->where('status', 'waiting for approval')
            ->whereNotNull('requested_item_name')
            ->where('requested_category_id', $categoryId)
            ->get()
            ->filter(fn ($request): bool => self::nameMatches($name, (string) $request->requested_item_name)
                && self::unitMatches($unit, $request->requested_unit))
            ->sum('quantity');
    }

    /**
     * Free-to-promise quantity: available minus pending holds, never negative.
     */
    public static function freeQuantity(string $name, int $categoryId, ?string $unit): int
    {
        return max(0, self::availableQuantity($name, $categoryId, $unit) - self::pendingQuantity($name, $categoryId, $unit));
    }

    /**
     * Pending hold against one concrete inventory record: custodian-issued
     * assignments awaiting end-user acceptance (`waiting for approval` with
     * this item_id). Stock is untouched until approval, so the assign modal
     * must show and enforce quantity minus this hold.
     */
    public static function pendingHoldForItem(int $itemId): int
    {
        return (int) \App\Models\AssignmentRequest::query()
            ->where('status', 'waiting for approval')
            ->where('item_id', $itemId)
            ->sum('quantity');
    }
}
