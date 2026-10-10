<?php

namespace App\Services\Conversation;

use App\Models\AiConversationSession;
use App\Models\AiConversationTurn;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The durable turn log behind the assistant's conversation memory.
 *
 * The session-backed log in ConversationContextManager is the live topic: one
 * item, short-lived, and enough to answer the next follow-up. This class holds
 * what that topic cannot: every answered exchange, the entities each one
 * resolved against, and the candidate ids each one offered. It is what a
 * returning session is rebuilt from, and what "the second one" resolves against.
 *
 * Storage rules that are security rules, not preferences:
 *
 * - Only a `success` turn contributes entity references. A refused or
 *   unsupported turn must never leave ids behind, or a user who lost a
 *   capability could resolve against data they may no longer see.
 * - Every read is scoped by `user_id` as well as `session_id`, so one user can
 *   never read another's turns even if a session id were guessed.
 */
class ConversationTurnLog
{
    /**
     * Session key holding the id of the conversation in progress.
     *
     * The assistant has no conversation concept of its own today, so the id is
     * minted once per browser session and reused until the session ends or the
     * conversation is reset. It is deliberately not derived from the user: two
     * people sharing a device must not share a log, or an ordinal reference
     * such as "the second one" becomes ambiguous.
     */
    private const SESSION_KEY = 'ai.conversation_session_id';

    /**
     * How many successful turns contribute to the entity registry.
     *
     * The most recent successful turn is already covered by the candidate set.
     * Two further turns of depth is enough to reach "the other projector"
     * without carrying stale anchors far enough to be picked by mistake.
     */
    public const REGISTRY_WINDOW = 3;

    /**
     * How many recent exchanges are handed to the intent parser.
     */
    public const RECENT_TURNS = 10;

    /**
     * The id of the conversation this request belongs to, creating it lazily.
     *
     * This deliberately does not mark the conversation alive. Retention is
     * measured against the last answered turn, so a read that refreshes the
     * timestamp would keep a conversation alive forever and nothing would ever
     * age out. Only `record()` counts as activity.
     */
    public function sessionId(Request $request, User $user): string
    {
        if (! $request->hasSession()) {
            return $this->newSessionId();
        }

        $sessionId = $request->session()->get(self::SESSION_KEY);

        if (! is_string($sessionId) || $sessionId === '' || mb_strlen($sessionId) > 64) {
            $sessionId = $this->newSessionId();
            $request->session()->put(self::SESSION_KEY, $sessionId);
        }

        return $sessionId;
    }

    /**
     * Forget the current conversation and start a new one on the next turn.
     */
    public function forgetSession(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }

    /**
     * Append one answered exchange.
     *
     * Nothing here re-queries: the entity references and candidate ids are read
     * from the answer that was already produced.
     */
    public function record(
        User $user,
        string $sessionId,
        string $question,
        string $reply,
        array $routedQuestion,
        array $result,
        array $candidateIds = [],
        array $entityRefs = [],
    ): void {
        $userId = (int) $user->getAuthIdentifier();

        try {
            $this->touch($user, $sessionId);

            AiConversationTurn::query()->create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'turn_index' => $this->nextTurnIndex($sessionId),
                'question' => mb_substr($question, 0, 1000),
                'reply' => mb_substr($reply, 0, 4000),
                'status' => $this->normaliseStatus($result['status'] ?? null),
                'resolved' => $this->resolvedPayload($routedQuestion, $result, $candidateIds, $entityRefs),
                'candidate_ids' => $candidateIds === [] ? null : array_values($candidateIds),
            ]);
        } catch (\Throwable $exception) {
            // The log is an aid to continuity. A failure to record one must never
            // take down an answer the user is owed.
            Log::warning('AI conversation turn not recorded: '.$exception->getMessage());
        }
    }

    /**
     * The candidate ids from the most recent successful turn, in the order they
     * were offered.
     *
     * This is the only thing that can answer "the second one", because counting
     * items in reply prose is fragile.
     *
     * @return array<int, int>
     */
    public function lastCandidateIds(User $user, string $sessionId): array
    {
        $turn = $this->lastReferenceableTurn($user, $sessionId);

        $ids = $turn?->candidate_ids;

        return is_array($ids) ? $this->cleanIds($ids) : [];
    }

    /**
     * Every inventory id the last few successful turns resolved against.
     *
     * @return array<int, int>
     */
    public function entityRegistry(User $user, string $sessionId, int $window = self::REGISTRY_WINDOW): array
    {
        if ($window < 1) {
            return [];
        }

        $rows = $this->referenceableTurns($user, $sessionId)->limit($window)->get();

        $ids = [];
        foreach ($rows as $turn) {
            $resolved = $turn->resolved;
            if (is_array($resolved)) {
                $ids = [...$ids, ...$this->cleanIds($resolved['entity_refs'] ?? [])];
            }
        }

        return $this->cleanIds($ids);
    }

    /**
     * Recent exchanges, oldest first, for the context handed to the parser.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentTurns(User $user, string $sessionId, int $limit = self::RECENT_TURNS): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = AiConversationTurn::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('session_id', $sessionId)
            ->orderByDesc('turn_index')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return $rows->map(fn (AiConversationTurn $turn): array => [
            'question' => mb_substr((string) $turn->question, 0, 255),
            'reply' => mb_substr((string) $turn->reply, 0, 255),
            'intent' => is_array($turn->resolved) && is_string($turn->resolved['intent'] ?? null)
                ? mb_substr($turn->resolved['intent'], 0, 64)
                : null,
            'capability' => is_array($turn->resolved) && is_string($turn->resolved['capability'] ?? null)
                ? mb_substr($turn->resolved['capability'], 0, 64)
                : null,
        ])->all();
    }

    /**
     * The resolved request of the most recent successful turn, with the
     * candidate ids it offered.
     *
     * Returned together because an ordinal reply needs both: the ids to pick
     * from, and the action they were offered under.
     *
     * @return array{intent: string|null, capability: string|null, response_type: string|null, candidate_ids: array<int, int>}|null
     */
    public function lastResolved(User $user, string $sessionId): ?array
    {
        $turn = $this->lastReferenceableTurn($user, $sessionId);
        if ($turn === null) {
            return null;
        }

        $resolved = is_array($turn->resolved) ? $turn->resolved : [];

        return [
            'intent' => is_string($resolved['intent'] ?? null) ? $resolved['intent'] : null,
            'capability' => is_string($resolved['capability'] ?? null) ? $resolved['capability'] : null,
            'response_type' => is_string($resolved['response_type'] ?? null) ? $resolved['response_type'] : null,
            'item_name' => is_string($resolved['item_name'] ?? null) ? $resolved['item_name'] : null,
            'inventory_id' => is_int($resolved['inventory_id'] ?? null) ? $resolved['inventory_id'] : null,
            'category_id' => is_int($resolved['category_id'] ?? null) ? $resolved['category_id'] : null,
            'unit' => is_string($resolved['unit'] ?? null) ? $resolved['unit'] : null,
            'serial_number' => is_string($resolved['serial_number'] ?? null) ? $resolved['serial_number'] : null,
            'candidate_ids' => $this->cleanIds($turn->candidate_ids ?? []),
        ];
    }

    /**
     * Rebuild the live topic from the last successful recorded turn.
     *
     * The session snapshot is short-lived on purpose. When it has expired the
     * conversation itself has not: the turns are still here, inside the retention
     * window. Reconstructing the topic from them means a user who comes back
     * after a break is not treated as having no history at all.
     *
     * Returns null when nothing can be rebuilt: no recorded turn, the turn did
     * not resolve to a single item, the conversation has aged out, or the item
     * no longer exists. In every one of those cases the caller starts clean and
     * asks, which is the behaviour before this existed.
     *
     * @return array<string, mixed>|null
     */
    public function rebuildTopic(User $user, string $sessionId): ?array
    {
        if (! $this->isWithinRetention($user, $sessionId)) {
            return null;
        }

        $turn = $this->lastReferenceableTurn($user, $sessionId);
        if ($turn === null) {
            return null;
        }

        $resolved = is_array($turn->resolved) ? $turn->resolved : [];

        $capability = $resolved['capability'] ?? null;
        $intent = $resolved['intent'] ?? null;
        $responseType = $resolved['response_type'] ?? null;
        $inventoryId = $resolved['inventory_id'] ?? null;
        $itemName = $resolved['item_name'] ?? null;

        if (! is_string($capability) || $capability === ''
            || ! is_string($itemName) || trim($itemName) === ''
            || ! in_array($intent, ['factual', 'explanation'], true)
            || ! in_array($responseType, ['detail', 'count', 'list', 'explanation'], true)) {
            return null;
        }

        return [
            'version' => 1,
            'intent' => $intent,
            'capability' => $capability,
            'response_type' => $responseType,
            'reference_type' => is_int($inventoryId) ? 'inventory' : 'inventory_group',
            'reference_id' => is_int($inventoryId) ? $inventoryId : null,
            'item_name' => trim($itemName),
            'filters' => [],
        ];
    }

    /**
     * Turns older than the recent window, newest first.
     *
     * These are what the rolling summary compresses and what entity-similarity
     * retrieval draws on.
     *
     * @return array<int, array<string, mixed>>
     */
    public function turnsOutsideWindow(User $user, string $sessionId, int $window): array
    {
        $windowId = AiConversationTurn::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('session_id', $sessionId)
            ->orderByDesc('turn_index')
            ->skip(max(0, $window))
            ->take(1)
            ->value('turn_index');

        if ($windowId === null) {
            return [];
        }

        return AiConversationTurn::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('session_id', $sessionId)
            ->where('turn_index', '<', (int) $windowId)
            ->orderByDesc('turn_index')
            ->limit(50)
            ->get()
            ->map(fn (AiConversationTurn $turn): array => [
                'question' => (string) $turn->question,
                'reply' => mb_substr((string) $turn->reply, 0, 255),
                'item_name' => $this->resolvedString($turn, 'item_name'),
                'intent' => $this->resolvedString($turn, 'intent'),
                'entity_refs' => is_array($turn->resolved)
                    ? $this->cleanIds($turn->resolved['entity_refs'] ?? [])
                    : [],
            ])
            ->all();
    }

    /**
     * A rolling summary of the turns that have left the recent window.
     *
     * Topic continuity only. This must never resolve an entity: compression
     * drops facts, and a resolution built on a dropped fact produces no signal.
     * Entity references come only from the structured registry and the candidate
     * set.
     *
     * Deterministic and local. It lists the topics that were discussed rather
     * than paraphrasing them, so it cannot assert anything the turns do not
     * contain.
     */
    public function summary(User $user, string $sessionId): string
    {
        $older = $this->turnsOutsideWindow($user, $sessionId, self::RECENT_TURNS);
        if ($older === []) {
            return '';
        }

        $topics = [];
        foreach ($older as $turn) {
            $name = $turn['item_name'] ?? null;
            $label = is_string($name) && trim($name) !== ''
                ? trim($name)
                : $turn['intent'];

            if (! is_string($label) || trim($label) === '') {
                continue;
            }

            $topics[mb_strtolower(trim($label))] = trim($label);
        }

        if ($topics === []) {
            return '';
        }

        return 'Earlier in this conversation the user discussed: '
            .implode(', ', array_values($topics)).'.';
    }

    private function resolvedString(AiConversationTurn $turn, string $key): ?string
    {
        if (! is_array($turn->resolved)) {
            return null;
        }

        $value = $turn->resolved[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Whether this conversation still exists inside the retention window.
     *
     * A session that has aged out is treated as absent: the caller starts clean
     * and asks rather than reaching for context the user may no longer expect.
     */
    public function isWithinRetention(User $user, string $sessionId): bool
    {
        return AiConversationSession::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('session_id', $sessionId)
            ->where('last_touch_at', '>=', now()->subDays($this->retentionDays()))
            ->exists();
    }

    /**
     * Drop every turn belonging to a user.
     *
     * Called when the assistant is refused for their role. The session keys are
     * cleared too, and clearing only those would leave the previous role's
     * entities readable to the next one.
     */
    public function purge(User $user): void
    {
        $userId = (int) $user->getAuthIdentifier();

        AiConversationTurn::query()->where('user_id', $userId)->delete();
        AiConversationSession::query()->where('user_id', $userId)->delete();
    }

    /**
     * Delete conversations nobody has touched inside the retention window.
     */
    public function purgeExpired(): int
    {
        $cutoff = now()->subDays($this->retentionDays());

        $sessionIds = AiConversationSession::query()
            ->where('last_touch_at', '<', $cutoff)
            ->pluck('session_id')
            ->all();

        if ($sessionIds === []) {
            return 0;
        }

        AiConversationTurn::query()->whereIn('session_id', $sessionIds)->delete();
        AiConversationSession::query()->whereIn('session_id', $sessionIds)->delete();

        return count($sessionIds);
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('inventory.ai_conversation_retention_days', 7));
    }

    private function referenceableTurns(User $user, string $sessionId)
    {
        return AiConversationTurn::query()
            ->where('user_id', (int) $user->getAuthIdentifier())
            ->where('session_id', $sessionId)
            ->where('status', AiConversationTurn::STATUS_SUCCESS)
            ->orderByDesc('turn_index');
    }

    private function lastReferenceableTurn(User $user, string $sessionId): ?AiConversationTurn
    {
        return $this->referenceableTurns($user, $sessionId)->first();
    }

    /**
     * Create the owning row once, and mark the conversation alive on every turn.
     */
    private function touch(User $user, string $sessionId): void
    {
        $userId = (int) $user->getAuthIdentifier();

        AiConversationSession::query()->updateOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $userId, 'last_touch_at' => now()],
        );
    }

    /**
     * The next position in this conversation.
     *
     * Scoped by `session_id` alone, because that is what the unique index is
     * built on. Scoping by user as well would compute an index the database
     * then rejects whenever one session is shared by more than one account.
     */
    private function nextTurnIndex(string $sessionId): int
    {
        $highest = AiConversationTurn::query()
            ->where('session_id', $sessionId)
            ->max('turn_index');

        return ((int) $highest) + 1;
    }

    private function newSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function normaliseStatus(mixed $status): string
    {
        return in_array($status, [
            AiConversationTurn::STATUS_SUCCESS,
            AiConversationTurn::STATUS_CLARIFICATION,
            AiConversationTurn::STATUS_FORBIDDEN,
            AiConversationTurn::STATUS_NOT_FOUND,
            AiConversationTurn::STATUS_UNSUPPORTED,
        ], true) ? $status : AiConversationTurn::STATUS_UNSUPPORTED;
    }

    /**
     * What this turn resolved to.
     *
     * Entity references are dropped unless the turn succeeded. The candidate ids
     * are kept for a clarification, because that is exactly the turn where the
     * user was handed a list to choose from.
     */
    private function resolvedPayload(
        array $routedQuestion,
        array $result,
        array $candidateIds,
        array $entityRefs,
    ): array {
        $succeeded = ($result['status'] ?? null) === AiConversationTurn::STATUS_SUCCESS;

        return [
            'intent' => $this->shortString($routedQuestion['intent'] ?? null, 64),
            'capability' => $this->shortString($routedQuestion['capability'] ?? null, 64),
            'response_type' => $this->shortString($routedQuestion['response_type'] ?? null, 32),
            'item_name' => $this->shortString($routedQuestion['item_name'] ?? null, 255),
            'inventory_id' => $this->positiveInt($routedQuestion['inventory_id'] ?? null),
            'category_id' => $this->positiveInt($routedQuestion['category_id'] ?? null),
            'unit' => $this->shortString($routedQuestion['unit'] ?? null, 100),
            'serial_number' => $this->shortString($routedQuestion['serial_number'] ?? null, 255),
            'entity_refs' => $succeeded ? $this->cleanIds($entityRefs) : [],
            'candidate_ids' => $this->cleanIds($candidateIds),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function shortString(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? mb_substr(trim($value), 0, $max)
            : null;
    }

    /**
     * @return array<int, int>
     */
    private function cleanIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $clean = [];
        foreach ($ids as $id) {
            if (is_int($id) && $id > 0) {
                $clean[] = $id;
            } elseif (is_string($id) && ctype_digit($id) && (int) $id > 0) {
                $clean[] = (int) $id;
            }
        }

        return array_values(array_unique($clean, SORT_REGULAR));
    }
}