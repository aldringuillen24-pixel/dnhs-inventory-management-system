<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    public const INCIDENT_TYPES = ['damaged', 'needs_repair', 'lost', 'stolen'];

    public const INCIDENT_STATUSES = [
        'reported',
        'under_review',
        'awaiting_reporter',
        'declined',
        'processing_repair',
        'processing_disposal',
        'under_investigation',
        'resolved',
    ];

    protected $fillable = [
        'inventory_id',
        'quantity',
        'reported_by',
        'assigned_to',
        'assignment_transaction_id',
        'status',
        'incident_status',
        'incident_outcome',
        'repair_return_destination',
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
            'quantity' => 'integer',
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

    public function assignmentTransaction()
    {
        return $this->belongsTo(Transaction::class, 'assignment_transaction_id');
    }

    public function events()
    {
        return $this->hasMany(MaintenanceRecordEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function evidence()
    {
        return $this->hasMany(MaintenanceRecordEvidence::class);
    }

    public function isIncidentReport(): bool
    {
        return $this->incident_status !== null && in_array($this->diagnosis, self::INCIDENT_TYPES, true);
    }
}
