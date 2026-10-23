<?php

namespace App\Models;

use App\Services\IncidentReporterNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MaintenanceRecordEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'maintenance_record_id',
        'actor_id',
        'event_type',
        'from_status',
        'to_status',
        'details',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(static function (self $event): void {
            DB::afterCommit(static function () use ($event): void {
                app(IncidentReporterNotificationService::class)->notify($event);
            });
        });

        static::updating(static function (): never {
            throw new LogicException('Incident timeline events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Incident timeline events are append-only.');
        });
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
