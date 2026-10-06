<?php

namespace App\Services\Conversation;

use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\InventoryAnswerService;
use App\Services\InventoryComparisonService;
use Illuminate\Http\Request;

/**
 * Owns every read and write of the assistant's per-user session state.
 *
 * The chat pipeline used to hold four session keys and eight helpers directly
 * on the controller. They all lived here already in substance: the same
 * prefixes, the same TTL stamp, the same user-id revalidation, and the same
 * shape checks. This class only gives those rules one address. No expiry
 * window, key name, or validation rule has been altered.
 */
class ConversationContextManager
{
    private const CONTEXT_SESSION_PREFIX = 'ai.inventory_context.user.';
    private const CLARIFICATION_SESSION_PREFIX = 'ai.inventory_clarification.user.';
    private const COMPARISON_CONTEXT_SESSION_PREFIX = 'ai.inventory_comparison_context.user.';
    private const FORECAST_CONTEXT_SESSION_PREFIX = 'ai.demand_forecast_context.user.';

    public function __construct(
        protected AiCapabilityPolicy $capabilityPolicy,
        protected InventoryAnswerService $answerService,
        protected InventoryComparisonService $comparisonService,
    ) {
    }

    public function contextKey(int $userId): string
    {
        return self::CONTEXT_SESSION_PREFIX . $userId;
    }

    public function clarificationKey(int $userId): string
    {
        return self::CLARIFICATION_SESSION_PREFIX . $userId;
    }

    public function comparisonContextKey(int $userId): string
    {
        return self::COMPARISON_CONTEXT_SESSION_PREFIX . $userId;
    }

    public function forecastContextKey(int $userId): string
    {
        return self::FORECAST_CONTEXT_SESSION_PREFIX . $userId;
    }

    /**
     * Read the whole session state for a user in one pass.
     *
     * Every field is the raw stored value; callers still resolve or revalidate
     * it exactly as they did before, so nothing is decided here that used to be
     * decided by the caller.
     */
    public function loadState(Request $request, User $user): ConversationState
    {
        $userId = (int) $user->getAuthIdentifier();

        $topic = $this->loadContext($request, $user);

        $pendingRecord = $this->loadSessionRecord($request, $user, $this->clarificationKey($userId));
        $pendingClarification = is_array($pendingRecord['context'] ?? null)
            ? $pendingRecord['context']
            : null;

        return new ConversationState(
            topic: $topic,
            pendingClarification: $pendingClarification,
            comparison: $this->loadComparisonContext($request, $user),
            forecast: $this->loadForecastContext($request, $user),
            turns: [],
        );
    }

    /**
     * Drop every stored key for a user. Used when the assistant is unavailable
     * to the role, on a comparison hand-off, and on reset.
     */
    public function forgetAll(Request $request, User $user): void
    {
        $userId = (int) $user->getAuthIdentifier();
        $request->session()->forget($this->contextKey($userId));
        $request->session()->forget($this->clarificationKey($userId));
        $request->session()->forget($this->comparisonContextKey($userId));
        $request->session()->forget($this->forecastContextKey($userId));
    }

    /**
     * Drop the three non-forecast keys, leaving any forecast context intact.
     *
     * The forecast branch of the chat pipeline used to forget exactly these
     * three before answering, so that a forecast reply never inherits — or
     * leaves behind — inventory or clarification context.
     */
    public function forgetNonForecastContext(Request $request, User $user): void
    {
        $userId = (int) $user->getAuthIdentifier();
        $request->session()->forget($this->contextKey($userId));
        $request->session()->forget($this->clarificationKey($userId));
        $request->session()->forget($this->comparisonContextKey($userId));
    }

    public function loadContext(Request $request, User $user): ?array
    {
        $stored = $this->loadSessionRecord($request, $user, $this->contextKey((int) $user->getAuthIdentifier()));
        if ($stored === null) {
            return null;
        }

        return $stored['context'];
    }

    public function loadSessionRecord(Request $request, User $user, string $sessionKey): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $stored = $request->session()->get($sessionKey);
        if ($stored === null) {
            return null;
        }

        if (
            ! is_array($stored)
            || (int) ($stored['user_id'] ?? 0) !== (int) $user->getAuthIdentifier()
            || ! is_int($stored['expires_at'] ?? null)
            || $stored['expires_at'] <= now()->timestamp
            || ! is_array($stored['context'] ?? null)
        ) {
            $request->session()->forget($sessionKey);

            return null;
        }

        return $stored;
    }

    public function loadComparisonContext(Request $request, User $user): ?array
    {
        $sessionKey = $this->comparisonContextKey((int) $user->getAuthIdentifier());
        if (! $request->hasSession()) {
            return null;
        }
        if ($user->role?->role_name !== 'Property Custodian') {
            $request->session()->forget($sessionKey);

            return null;
        }

        $stored = $this->loadSessionRecord($request, $user, $sessionKey);
        if ($stored === null) {
            return null;
        }

        $summary = $this->comparisonService->validateComparisonContextSummary($stored['context']);
        if ($summary === null) {
            $request->session()->forget($sessionKey);

            return null;
        }

        return $summary;
    }

    public function storeComparisonContext(Request $request, User $user, array $summary): void
    {
        if (! $request->hasSession() || $user->role?->role_name !== 'Property Custodian') {
            return;
        }

        $this->put($request, $user, $this->comparisonContextKey((int) $user->getAuthIdentifier()), $summary);
    }

    public function loadForecastContext(Request $request, User $user): ?array
    {
        $sessionKey = $this->forecastContextKey((int) $user->getAuthIdentifier());
        if (! $request->hasSession()) {
            return null;
        }
        if (! $this->capabilityPolicy->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            $request->session()->forget($sessionKey);

            return null;
        }

        $stored = $this->loadSessionRecord($request, $user, $sessionKey);
        $context = $stored['context'] ?? null;
        if (! is_array($context)
            || ! in_array($context['source_type'] ?? null, ['live', 'demo'], true)
            || ! is_array($context['inventory_ids'] ?? null)
            || ! is_string($context['forecast_period'] ?? null)
            || (isset($context['selected_inventory_id'])
                && (! is_int($context['selected_inventory_id'])
                    || ! in_array($context['selected_inventory_id'], $context['inventory_ids'], true)))) {
            if ($stored !== null) {
                $request->session()->forget($sessionKey);
            }

            return null;
        }

        return $context;
    }

    public function storeForecastContext(Request $request, User $user, array $context): void
    {
        if (! $request->hasSession() || ! $this->capabilityPolicy->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            return;
        }

        $this->put($request, $user, $this->forecastContextKey((int) $user->getAuthIdentifier()), $context);
    }

    public function updateContext(
        Request $request,
        User $user,
        array $routedQuestion,
        array $result,
    ): void {
        if (! $request->hasSession()) {
            return;
        }

        $sessionKey = $this->contextKey((int) $user->getAuthIdentifier());
        if (($result['status'] ?? null) === 'success') {
            $context = $this->answerService->conversationContext($user, $routedQuestion, $result);
            if ($context !== null) {
                $this->put($request, $user, $sessionKey, $context);

                return;
            }
        }

        $request->session()->forget($sessionKey);
    }

    public function updateClarification(
        Request $request,
        User $user,
        array $routedQuestion,
        array $result,
    ): void {
        if (! $request->hasSession()) {
            return;
        }

        $sessionKey = $this->clarificationKey((int) $user->getAuthIdentifier());
        if (($result['status'] ?? null) === 'unsupported'
            || (($routedQuestion['intent'] ?? null) === 'unsupported' && ($routedQuestion['capability'] ?? null) === null)) {
            $request->session()->forget($sessionKey);

            return;
        }

        $pending = ($result['status'] ?? null) === 'clarification'
            ? $this->answerService->clarificationContext($user, $routedQuestion, $result)
            : null;

        if ($pending === null) {
            if (($routedQuestion['intent'] ?? null) !== 'unsupported' || ($routedQuestion['capability'] ?? null) !== null) {
                $request->session()->forget($sessionKey);
            }

            return;
        }

        $this->put($request, $user, $sessionKey, $pending);
    }

    /**
     * Store a context payload with the shared TTL and ownership stamp.
     */
    private function put(Request $request, User $user, string $sessionKey, array $context): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $ttlMinutes = max(1, (int) config('inventory.ai_context_ttl_minutes', 15));
        $request->session()->put($sessionKey, [
            'user_id' => (int) $user->getAuthIdentifier(),
            'expires_at' => now()->addMinutes($ttlMinutes)->timestamp,
            'context' => $context,
        ]);
    }
}