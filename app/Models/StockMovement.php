<?php

namespace App\Models;

use App\Support\SampleForecastData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Movements are read independently of the inventory list -- the custodian
     * dashboard, the reports history, the audit ledger, the school-head
     * dashboard and the AI assistant's history tool each query this table
     * directly. Hiding sample inventory rows is therefore not enough on its
     * own: the sample ledger would still be on screen. Same opt-out contract as
     * Inventory, see SampleForecastData::SCOPE.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(SampleForecastData::SCOPE, function ($query) {
            // "matches neither pattern" is two chained where() calls, which are
            // an AND, nested inside the OR that allows NULL notes. Written as
            // two OR'd NOT LIKEs the scope lets every sample row through,
            // because a row matching only one of the two patterns satisfies the
            // other clause -- which is exactly what happened.
            $query->where(fn ($inner) => $inner
                ->whereNull('notes')
                ->orWhere(fn ($neither) => $neither
                    ->where('notes', 'not like', SampleForecastData::MOVEMENT_PREFIX.'%')
                    ->where('notes', 'not like', SampleForecastData::LEGACY_MOVEMENT_PREFIX.'%')));
        });
    }

    protected $table = 'stock_movements';

    protected $fillable = [
        'inventory_id',
        'user_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'from_building',
        'from_room',
        'to_building',
        'to_room',
        'notes',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignmentReturn()
    {
        return $this->belongsTo(AssignmentReturn::class, 'reference_id');
    }

    public function assignmentRequest()
    {
        return $this->belongsTo(AssignmentRequest::class, 'reference_id');
    }
}
