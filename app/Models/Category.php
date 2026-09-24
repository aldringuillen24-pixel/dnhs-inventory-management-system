<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'default_lifespan_years',
        'requires_serial_number',
        'requires_qr_code',
        'is_maintenance_eligible',
    ];

    protected function casts(): array
    {
        return [
            'default_lifespan_years' => 'integer',
            'requires_serial_number' => 'boolean',
            'requires_qr_code' => 'boolean',
            'is_maintenance_eligible' => 'boolean',
        ];
    }

    public function inventoryItems()
    {
        return $this->hasMany(Inventory::class, 'category_id', 'category_id');
    }
}
