<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'assignment_request_id',
        'return_request_id',
        'inventory_id',
        'building',
        'room',
        'recipient_id',
        'received_by',
        'quantity',
        'notes',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function assignmentRequest()
    {
        return $this->belongsTo(AssignmentRequest::class);
    }

    public function returnRequest()
    {
        return $this->belongsTo(AssignmentRequest::class, 'return_request_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'item_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}