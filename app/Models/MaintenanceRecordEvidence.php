<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecordEvidence extends Model
{
    protected $table = 'maintenance_record_evidence';

    protected $hidden = ['disk', 'path'];

    protected $fillable = [
        'maintenance_record_id',
        'uploaded_by',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
