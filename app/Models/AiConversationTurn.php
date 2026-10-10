<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One answered exchange, appended once and never rewritten.
 *
 * `resolved` and `candidate_ids` are cast to arrays so they read back as arrays.
 * Their shape is validated in application code, not by the database, which is
 * why they are stored as `json` rather than anything the engine would enforce.
 */
class AiConversationTurn extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_CLARIFICATION = 'clarification';

    public const STATUS_FORBIDDEN = 'forbidden';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_UNSUPPORTED = 'unsupported';

    protected $table = 'ai_conversation_turns';

    protected $fillable = [
        'user_id',
        'session_id',
        'turn_index',
        'question',
        'reply',
        'status',
        'resolved',
        'candidate_ids',
    ];

    protected $casts = [
        'turn_index' => 'integer',
        'resolved' => 'array',
        'candidate_ids' => 'array',
    ];

    /**
     * Whether this turn may contribute references a later question resolves
     * against.
     *
     * A refused turn must never contribute: a user who lost a capability must
     * not be able to resolve against ids they were once shown.
     */
    public function isReferenceable(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}