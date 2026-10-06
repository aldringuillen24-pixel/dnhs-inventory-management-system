<?php

namespace App\Services\Conversation;

/**
 * Immutable snapshot of the assistant's per-user session state.
 *
 * The manager owns every session read and write for the chat pipeline, so the
 * controller reads one object instead of five separate session keys. Each
 * field carries the value the controller used to compute itself, so nothing
 * about the resolved state has changed.
 */
final class ConversationState
{
    /**
     * @param  array<string, mixed>|null  $topic  Raw stored conversation context.
     * @param  array<string, mixed>|null  $pendingClarification  Raw stored pending clarification.
     * @param  array<string, mixed>|null  $comparison  Validated comparison summary, or null.
     * @param  array<string, mixed>|null  $forecast  Validated forecast context, or null.
     * @param  array<int, array<string, mixed>>  $turns  Recent conversation turns, oldest first.
     */
    public function __construct(
        public readonly ?array $topic = null,
        public readonly ?array $pendingClarification = null,
        public readonly ?array $comparison = null,
        public readonly ?array $forecast = null,
        public readonly array $turns = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'pending_clarification' => $this->pendingClarification,
            'comparison' => $this->comparison,
            'forecast' => $this->forecast,
            'turns' => $this->turns,
        ];
    }
}