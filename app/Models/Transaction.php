<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_user_id',
        'item_id',
        'from_building',
        'from_room',
        'building',
        'room',
        'quantity',
        'issued_quantity',
        'transaction_date',
        'status',
        'return_date',
        'manual_recipient_name',
        'manual_department',
        'manual_recipient_type',
        'manual_contact',
        'manual_notes',
        'expected_return_date',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'return_date' => 'date',
            'expected_return_date' => 'date',
            'issued_quantity' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function item()
    {
        return $this->belongsTo(Inventory::class, 'item_id', 'item_id');
    }

    public function assignmentRequests()
    {
        return $this->hasMany(AssignmentRequest::class, 'transaction_id', 'id')->whereNotNull('item_id');
    }

    public function assignmentReturns()
    {
        return $this->hasMany(AssignmentReturn::class, 'transaction_id');
    }
}
