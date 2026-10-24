<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferReleaseSlip extends Model
{
    protected $fillable = [
        'request_id',
        'custodian_id',
        'recipient_name',
        'department_unit',
        'designation',
        'expected_return_date',
        'actual_return_date',
        'purpose',
        'condition_upon_release',
        'released_by_name',
        'released_by_date',
        'received_by_name',
        'received_by_date',
        'approved_by_name',
        'approved_by_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'expected_return_date' => 'date:Y-m-d',
        'actual_return_date' => 'date:Y-m-d',
        'released_by_date' => 'date:Y-m-d',
        'received_by_date' => 'date:Y-m-d',
        'approved_by_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(AssignmentRequest::class, 'request_id');
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }
}
