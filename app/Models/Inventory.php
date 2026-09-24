<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'category_id',
        'unit',
        'user_id',
        'assigned_to_user_id',
        'item_name',
        'description',
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

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'inventory_id', 'item_id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class, 'inventory_id', 'item_id');
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
