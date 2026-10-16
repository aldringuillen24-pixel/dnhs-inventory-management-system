<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItemLog extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_DISPOSED = 'disposed';

    protected $table = 'inventory_item_logs';

    protected $fillable = [
        'item_id',
        'inventory_item_no',
        'item_name',
        'category_id',
        'category_name',
        'status',
        'action',
        'changes',
        'reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
