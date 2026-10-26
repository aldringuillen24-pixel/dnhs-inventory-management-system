<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPolicyReminderDelivery extends Model
{
    protected $fillable = [
        'recipient_id',
        'reminder_type',
        'reference_key',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
