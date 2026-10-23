<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArrivalInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'inspection_verified',
        'iar_number',
        'iar_date',
        'entity_name',
        'fund_cluster',
        'supplier_name',
        'contract_title',
        'requesting_office',
        'responsibility_center_code',
        'invoice_number',
        'invoice_date',
        'acceptance_status',
        'acceptance_remarks',
        'inspection_date',
        'receipt_date',
        'committee_member_1_name',
        'committee_member_1_designation',
        'committee_member_2_name',
        'committee_member_2_designation',
        'committee_member_3_name',
        'committee_member_3_designation',
        'custodian_name',
        'custodian_designation',
        'created_by',
        'inspected_by',
        'inspected_at',
        'completed_by',
        'completed_at',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'iar_date' => 'date',
            'inspection_verified' => 'boolean',
            'invoice_date' => 'date',
            'inspection_date' => 'date',
            'receipt_date' => 'date',
            'inspected_at' => 'datetime',
            'completed_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArrivalInspectionItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
