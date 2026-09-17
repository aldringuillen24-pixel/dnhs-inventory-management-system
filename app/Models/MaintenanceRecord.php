<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'reported_by',
        'assigned_to',
        'status',
        'issue_description',
        'diagnosis',
        'repair_notes',
        'maintenance_cost',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_cost' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'item_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
