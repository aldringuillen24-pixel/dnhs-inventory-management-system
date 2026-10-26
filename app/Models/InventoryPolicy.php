<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPolicy extends Model
{
    protected $fillable = [
        'low_stock_threshold',
        'require_serial_number',
        'default_lifespan_years',
        'return_due_days',
        'auto_reminder_days',
        'overdue_escalation_days',
        'inventory_verification_interval_months',
    ];

    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'integer',
            'require_serial_number' => 'boolean',
            'default_lifespan_years' => 'integer',
            'return_due_days' => 'integer',
            'auto_reminder_days' => 'integer',
            'overdue_escalation_days' => 'integer',
            'inventory_verification_interval_months' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }
}
