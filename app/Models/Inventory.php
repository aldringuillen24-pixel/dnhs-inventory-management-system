<?php

namespace App\Models;

use App\Support\SampleForecastData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    /**
     * Sample rows exist so the demand-forecast model has history on a fresh
     * installation. They must not be visible as real property, so they are
     * filtered out here rather than at each call site: there are dozens of
     * inventory queries across the app and a filter that has to be remembered
     * would eventually be forgotten, putting fake stock in front of a user.
     *
     * The forecast reader and the training command opt back in by name with
     * withoutGlobalScope(SampleForecastData::SCOPE).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(SampleForecastData::SCOPE, function ($query) {
            // Identified by the ledger rather than by a marker on the row
            // itself. Two reasons:
            //
            //  - AiAssistantLocalFactsTest requires that no ordinary inventory
            //    query mentions the description column at all, so the AI cannot
            //    answer from an internal receiving note. Filtering on
            //    description here would put that column into every query.
            //  - No schema change is needed to add a marker column, which
            //    matters because a scope referencing a missing column breaks
            //    every inventory query on an installation that has not run the
            //    migration.
            //
            // Every sample item has at least its opening stock_in, so "has a
            // sample movement" and "is sample data" are the same set.
            $query->whereNotExists(function ($movements) {
                $movements->selectRaw('1')
                    ->from('stock_movements')
                    ->whereColumn('stock_movements.inventory_id', 'inventory.item_id')
                    ->where(fn ($notes) => $notes
                        ->where('stock_movements.notes', 'like', SampleForecastData::MOVEMENT_PREFIX.'%')
                        ->orWhere('stock_movements.notes', 'like', SampleForecastData::LEGACY_MOVEMENT_PREFIX.'%'));
            });
        });
    }

    protected $table = 'inventory';

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'category_id',
        'unit',
        'user_id',
        'assigned_to_user_id',
        'item_name',
        'description',
        'building',
        'room',
        'quantity',
        'unit_cost',
        'ics_no',
        'serial_number',
        'inventory_item_no',
        'status',
        'qr_code',
        'date_acquired',
        'lifespan_years',
        'expected_end_date',
    ];

    /**
     * Statuses in which a record's units are still issued out to someone.
     *
     * `status` carries two independent meanings: custody and condition. Flagging
     * an assigned item for inspection rewrites `status` to `under_inspection`
     * (InventoryOperationService::sendToInspection) without releasing the units —
     * `assigned_to_user_id` is untouched and markInspected() later restores
     * `status_before`. So `under_inspection` overlays custody rather than replacing
     * it. A status equality filter against 'assigned' therefore hides an item that
     * is still in someone's hands.
     *
     * A status alone is not sufficient to prove custody: stock flagged straight
     * from `available` also reads `under_inspection` but has no holder. Pair this
     * with an active assignment transaction to tell the two apart — a manual issue
     * has a transaction but no `assigned_to_user_id`, since its recipient is not a
     * user record.
     */
    public const CUSTODY_STATUSES = ['assigned', 'under_inspection'];

    protected function casts(): array
    {
        return [
            'date_acquired' => 'date',
            'lifespan_years' => 'integer',
            'expected_end_date' => 'date',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function getLifespanStatusAttribute(): ?string
    {
        if (! $this->expected_end_date) {
            return null;
        }

        $today = now()->startOfDay();

        if ($this->expected_end_date->lte($today)) {
            return 'end_of_useful_life';
        }

        if ($this->expected_end_date->lte($today->copy()->addYear())) {
            return 'approaching_end_of_life';
        }

        return 'healthy';
    }

    public function getLifespanRemainingPercentageAttribute(): ?int
    {
        if (! $this->date_acquired || ! $this->expected_end_date) {
            return null;
        }

        $acquiredAt = $this->date_acquired->startOfDay();
        $endAt = $this->expected_end_date->startOfDay();
        $totalSeconds = $endAt->timestamp - $acquiredAt->timestamp;

        if ($totalSeconds <= 0) {
            return 0;
        }

        $remainingSeconds = $endAt->timestamp - now()->startOfDay()->timestamp;

        return max(0, min(100, (int) round(($remainingSeconds / $totalSeconds) * 100)));
    }

    public function hasQrCode(): bool
    {
        return ! empty($this->qr_code);
    }

    public function scopeByQrToken($query, string $token)
    {
        return $query->where('qr_code', $token);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'item_id', 'item_id');
    }

    public function quantitySnapshot(): array
    {
        $available = $this->status === 'available' ? max(0, (int) $this->quantity) : 0;
        $assignedFromTransactions = (int) $this->transactions
            ->where('status', 'assigned')
            ->sum(function (Transaction $transaction): int {
                $issued = (int) ($transaction->issued_quantity ?: $transaction->quantity);
                $returned = (int) $transaction->assignmentReturns->sum('quantity');

                return max(0, $issued - $returned);
            });
        $storedAssigned = $this->status === 'assigned' ? max(0, (int) $this->quantity) : 0;
        $assigned = $assignedFromTransactions > 0 ? $assignedFromTransactions : $storedAssigned;
        $discrepancies = [];

        if ($assignedFromTransactions > 0 && $storedAssigned > 0 && $assignedFromTransactions !== $storedAssigned) {
            $discrepancies[] = sprintf(
                'stored assigned quantity is %d but active transactions calculate %d',
                $storedAssigned,
                $assignedFromTransactions
            );
        }

        if ($this->status === 'assigned' && $available > 0) {
            $discrepancies[] = sprintf(
                'status is assigned but stored available quantity is %d',
                $available
            );
        }

        return [
            'inventory_id' => (int) $this->item_id,
            'total_quantity' => $available + $assigned,
            'available_quantity' => $available,
            'assigned_quantity' => $assigned,
            'discrepancies' => $discrepancies,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The quantity that is actually on the books for this record.
     *
     * A fully issued record keeps 0 in `quantity` because the units moved onto the
     * assignment transaction (see InventoryOperationService::issueManual). Statuses
     * that sit alongside custody (assigned, and inspection flagged from assigned)
     * must therefore read the transaction total instead of the stored column.
     */
    public function effectiveQuantity(): int
    {
        $transactions = $this->relationLoaded('transactions')
            ? $this->transactions
            : $this->transactions()->with('assignmentReturns:id,transaction_id,quantity')->get();

        $issued = (int) $transactions->where('status', 'assigned')->sum(function ($transaction): int {
            $out = (int) ($transaction->issued_quantity ?: $transaction->quantity);
            $returned = (int) $transaction->assignmentReturns->sum('quantity');

            return max(0, $out - $returned);
        });

        return $issued > 0 ? $issued : max(0, (int) $this->quantity);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'inventory_id', 'item_id');
    }

    public function assignmentReturns()
    {
        return $this->hasMany(AssignmentReturn::class, 'inventory_id', 'item_id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class, 'inventory_id', 'item_id');
    }

    public function inspectionRecords()
    {
        return $this->hasMany(InspectionRecord::class, 'inventory_id', 'item_id');
    }

    public function latestInspection()
    {
        return $this->hasOne(InspectionRecord::class, 'inventory_id', 'item_id')->latestOfMany();
    }

    public function latestMaintenance()
    {
        return $this->hasOne(MaintenanceRecord::class, 'inventory_id', 'item_id')->latestOfMany();
    }

    public function latestStockMovement()
    {
        return $this->hasOne(StockMovement::class, 'inventory_id', 'item_id')->latestOfMany();
    }

    public function latestDisposalMovement()
    {
        return $this->hasOne(StockMovement::class, 'inventory_id', 'item_id')
            ->whereIn('movement_type', ['disposed', 'ready_to_dispose'])
            ->latestOfMany();
    }
}
