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
    ];

    protected function casts(): array
    {
        return [
            'date_acquired' => 'date',
            'unit_cost' => 'decimal:2',
        ];
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
}
