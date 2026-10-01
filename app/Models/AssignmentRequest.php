<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentRequest extends Model
{
    use HasFactory;

    // Use the generic `requests` table name to represent both custodian-initiated
    // and end-user-initiated requests. Keep the model name `AssignmentRequest`
    // to avoid collision with the HTTP `Request` class.
    protected $table = 'requests';

    protected $fillable = [
        'item_id',
        'requested_item_name',
        'requested_category_id',
        'requested_unit',
        'user_id',
        'target_user_id',
        'building',
        'room',
        'transaction_id',
        'parent_request_id',
        'quantity',
        'status',
        'notes',
        'cancellation_reason',
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

    public function requestedCategory()
    {
        return $this->belongsTo(Category::class, 'requested_category_id', 'category_id');
    }

    public function parentRequest()
    {
        return $this->belongsTo(self::class, 'parent_request_id');
    }

    public function fulfillmentRequests()
    {
        return $this->hasMany(self::class, 'parent_request_id');
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

    public function assignmentReturns()
    {
        return $this->hasMany(AssignmentReturn::class, 'assignment_request_id');
    }
}
