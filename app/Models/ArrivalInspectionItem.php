<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArrivalInspectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'arrival_inspection_id',
        'stock_property_number',
        'description',
        'unit',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function arrivalInspection(): BelongsTo
    {
        return $this->belongsTo(ArrivalInspection::class);
    }
}
