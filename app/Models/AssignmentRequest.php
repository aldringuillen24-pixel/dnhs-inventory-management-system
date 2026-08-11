<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentRequest extends Model
{
    use HasFactory;

    protected $table = 'assignment_requests';

    protected $fillable = [
        'item_id',
        'user_id',
        'target_user_id',
        'transaction_id',
        'quantity',
        'status',
        'notes',
        'requested_at',
        'responded_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Inventory::class, 'item_id', 'item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
