<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentRequest extends Model
{
    use HasFactory;

    /**
     * An item-type request submitted when the item had zero available stock.
     *
     * It cannot be assigned, so it never enters the approval queue: the
     * custodian has nothing to allocate. It exists so an end user can still
     * state a real need when the system is empty, and it feeds the forecast as
     * unmet demand. See docs/inventory-workflows.md and docs/ai-assistant.md.
     *
     * Distinct from 'waiting for approval', which a custodian can act on today.
     */
    public const STATUS_WAITING_FOR_PROCUREMENT = 'waiting for procurement';

    // Use the generic `requests` table name to represent both custodian-initiated
    // and end-user-initiated requests. Keep the model name `AssignmentRequest`
    // to avoid collision with the HTTP `Request` class.
    protected $table = 'requests';

    protected $appends = ['matching_inventory_items'];

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
        'source_assignment_id',
        'quantity',
        'status',
        'priority',
        'notes',
        'cancellation_reason',
        'requested_at',
        'responded_at',
        'received_at',
        'expected_return_date',
        'release_slip_fields',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'received_at' => 'datetime',
        'release_slip_fields' => 'array',
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

    public function sourceAssignment()
    {
        return $this->belongsTo(self::class, 'source_assignment_id');
    }

    public function transferRequests()
    {
        return $this->hasMany(self::class, 'source_assignment_id');
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

    public function releaseSlip()
    {
        return $this->hasOne(TransferReleaseSlip::class, 'request_id');
    }

    public function getMatchingInventoryItemsAttribute()
    {
        return $this->attributes['matching_inventory_items'] ?? collect();
    }
}
