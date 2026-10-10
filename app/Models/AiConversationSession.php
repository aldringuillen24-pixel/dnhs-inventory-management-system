<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversationSession extends Model
{
    protected $table = 'ai_conversation_sessions';

    protected $fillable = [
        'user_id',
        'session_id',
        'last_touch_at',
    ];

    protected $casts = [
        'last_touch_at' => 'datetime',
    ];

    public function turns(): HasMany
    {
        return $this->hasMany(AiConversationTurn::class, 'session_id', 'session_id');
    }
}