<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'flagged_by',
        'inspected_by',
        'status_before',
        'status',
        'finding_notes',
        'flagged_at',
        'inspected_at',
    ];

    protected function casts(): array
    {
        return [
            'flagged_at' => 'datetime',
            'inspected_at' => 'datetime',
        ];
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'item_id');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}