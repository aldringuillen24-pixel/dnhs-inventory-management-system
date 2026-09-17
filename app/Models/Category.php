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
        'requires_serial_number',
        'is_maintenance_eligible',
    ];

    protected function casts(): array
    {
        return [
            'requires_serial_number' => 'boolean',
            'is_maintenance_eligible' => 'boolean',
        ];
    }

    public function inventoryItems()
    {
        return $this->hasMany(Inventory::class, 'category_id', 'category_id');
    }
}
