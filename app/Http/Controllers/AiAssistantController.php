<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiInventoryService;
use App\Services\AiCapabilityPolicy;
use App\Services\Conversation\ConversationContextManager;
use App\Services\Conversation\ConversationTurnLog;
use App\Services\Conversation\ConversationContextSelector;
use App\Services\Forecast\ForecastChatHandoff;
use App\Services\InventoryAnswerService;
use App\Services\Response\AnswerComposer;
use App\Services\Tools\CompoundPlanExecutor;
use App\Services\AiIntentParserService;
use App\Services\InventoryComparisonService;
use App\Http\Requests\InventoryComparisonRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiIntentParserService $intentParser,
        protected InventoryAnswerService $answerService,
        protected AiInventoryService $aiService,
        protected AiCapabilityPolicy $capabilityPolicy,
        protected InventoryComparisonService $comparisonService,
        protected ForecastChatHandoff $forecastHandoff,
        protected ConversationContextManager $conversationContext,
        protected ConversationTurnLog $turnLog,
        protected ConversationContextSelector $contextSelector,
        protected CompoundPlanExecutor $compoundPlanExecutor,
        protected AnswerComposer $answerComposer,
    ) {}

    /**
     * Handle incoming chat message from the AI Assistant Drawer.
     */
    public function chat(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Get the authenticated user
        $user = $request->user();
        $user?->unsetRelation('role');

        // Check if the user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if the user has permission to use the AI assistant
        if (!$this->capabilityPolicy->canUseAssistant($user)) {
            if ($request->hasSession()) {
                $this->conversationContext->forgetAll($request, $user);
            }

            // The durable turn log holds entity references and candidate ids the
            // previous role was shown. Clearing only the session keys would
            // leave those readable once the role changes, so the rows go too.
            $this->turnLog->purge($user);

            return response()->json([
                'success' => false,
                'message' => 'The AI assistant is not available for your role.',
            ], 403);
        }

        // Process the user's message and generate a response
        try {
            if ($this->comparisonService->isComparisonRequest($validated['message'])) {
                $this->conversationContext->forgetAll($request, $user);

                return response()->json([
                    'success' => true,
                    'reply' => 'Review the comparison details and confirm to calculate the two full calendar months.',
                    'comparison_form' => $this->comparisonService->draft($validated['message']),
                    'role' => $user->role?->role_name ?? 'End User',
                ]);
            }

            // Forecast, urgency and procurement questions are delegated to the Demand
            // Forecast page rather than answered here.
            if ($this->forecastHandoff->owns($request, $user, $validated['message'])) {
                return $this->forecastHandoff->reply($request, $user, $validated['message']);
            }

            $state = $this->conversationContext->loadState($request, $user);

            $comparisonContext = $state->comparison;
            if ($comparisonContext !== null) {
                if ($this->comparisonService->isComparisonFollowUp($validated['message'], $comparisonContext)) {
                    if ($this->comparisonService->comparisonFollowUpMentionsOtherPeriods($validated['message'], $comparisonContext)) {
                        $reply = 'That question mentions different months from the latest confirmed comparison. Please name a new comparison to check those periods.';
                        $request->session()->forget($this->conversationContext->comparisonContextKey((int) $user->getAuthIdentifier()));
                    } else {
                        $reply = $this->aiService->explainComparisonFollowUp($user, $comparisonContext);
                        $this->conversationContext->storeComparisonContext($request, $user, $comparisonContext);
                    }

                    return response()->json([
                        'success' => true,
                        'reply' => $reply,
                        'role' => $user->role?->role_name ?? 'End User',
                    ]);
                }

                $request->session()->forget($this->conversationContext->comparisonContextKey((int) $user->getAuthIdentifier()));
            }

            // Load the current conversation context from the session, if available
            $context = $state->topic;

            // If a context exists, attempt to resolve it using the answer service
            if ($context !== null) {
                $resolvedContext = $this->answerService->resolveConversationContext($user, $context);
                if ($resolvedContext === null) {
                    $request->session()->forget($this->conversationContext->contextKey((int) $user->getAuthIdentifier()));
                    $context = null;
                } else {
                    $context = $resolvedContext;
                }
            }

            // The session snapshot is short-lived. When it is gone the recorded
            // turns are not, so the topic is rebuilt from them if the
            // conversation is still inside the retention window. A user who
            // returns after a break keeps their history; one whose conversation
            // has aged out starts clean and is asked.
            if ($context === null) {
                $context = $this->rebuiltContext($request, $user);
            }

            // Load any pending clarification context from the session
            $pendingClarification = $state->pendingClarification;
            if ($pendingClarification !== null) {
                $pendingClarification = $this->answerService->refreshClarificationContext($user, $pendingClarification);
                if ($pendingClarification === null) {
                    $request->session()->forget($this->conversationContext->clarificationKey((int) $user->getAuthIdentifier()));
                }
            }
            $routedQuestion = null;
            $result = null;

            if ($pendingClarification !== null) {
                $routedQuestion = $this->answerService->resolveClarification($user, $pendingClarification, $validated['message']);
                if ($routedQuestion !== null) {
                    $request->session()->forget($this->conversationContext->clarificationKey((int) $user->getAuthIdentifier()));
                    $context = null;
                    $request->session()->forget($this->conversationContext->contextKey((int) $user->getAuthIdentifier()));
                } else {
                    $clarificationAction = [
                        'prior_intent' => $pendingClarification['intent'] ?? null,
                        'capability' => $pendingClarification['capability'] ?? null,
                        'response_type' => $pendingClarification['response_type'] ?? null,
                    ];
                    $newQuestion = $this->intentParser->route($validated['message'], $clarificationAction, $state->turns);
                    $startsNewSupportedQuestion = ($newQuestion['follow_up'] ?? false) === false
                        && ($newQuestion['capability'] ?? null) !== null
                        && ($newQuestion['needs_clarification'] ?? false) === false;
                    $startsNewUnsupportedQuestion = ($newQuestion['intent'] ?? null) === 'unsupported';

                    if ($startsNewSupportedQuestion || $startsNewUnsupportedQuestion) {
                        $request->session()->forget($this->conversationContext->clarificationKey((int) $user->getAuthIdentifier()));
                        $routedQuestion = $newQuestion;
                    } else {
                        $routedQuestion = [
                            'intent' => $pendingClarification['intent'],
                            'capability' => $pendingClarification['capability'],
                            'item_name' => $pendingClarification['item_name'] ?? null,
                            'needs_external_explanation' => $pendingClarification['intent'] === 'explanation',
                            'query' => $pendingClarification['query'] ?? null,
                            'vague' => false,
                            'response_type' => $pendingClarification['response_type'],
                            'needs_clarification' => true,
                        ];
                        $clarificationQuestion = $this->answerService->clarificationReply($user, $pendingClarification);
                        $result = [
                            'status' => 'clarification',
                            'intent' => 'clarification',
                            'capability' => $pendingClarification['capability'],
                            'answer' => ['clarification_question' => $clarificationQuestion],
                        ];
                    }
                }
            } elseif ($pendingClarification !== null) {
                $request->session()->forget($this->conversationContext->clarificationKey((int) $user->getAuthIdentifier()));
                $pendingClarification = null;
            }

            if ($routedQuestion === null && $pendingClarification === null) {
                $routedQuestion = $this->rememberedSelection($request, $user, $validated['message']);
            }

            if ($routedQuestion === null && $pendingClarification === null) {
                $routedQuestion = $this->applicationFollowUp($validated['message'], $context);
            }

            // Reference resolution is the fallback, not the first choice.
            //
            // The deterministic resolvers above are free and already cover the
            // short forms: "why?", "where is it?", "how many are available?".
            // Running the provider first would spend two extra calls re-deciding
            // what a regex decides locally. It runs only when nothing else
            // resolved the turn, which is exactly where the regexes cannot help:
            // ordinal and comparative references, and restatements.
            //
            // A resolution that cannot be made leaves the raw question in place
            // and the parser receives it unchanged, exactly as it did before.
            $question = $validated['message'];

            if ($routedQuestion === null && $pendingClarification === null) {
                $resolution = $this->resolveReferences($request, $user, $question, $context);

                if ($resolution !== null) {
                    $gate = $this->ambiguityResult($user, $resolution);

                    $routedQuestion = $gate ?? $this->intentParser->route(
                        $resolution['resolved_question'],
                        $context ?? [],
                        $state->turns,
                    );
                }
            }

            $routedQuestion ??= $this->intentParser->route($question, $context ?? [], $state->turns);

            // A compound turn fans out into one authorised sub-request per
            // decomposed part. A single-intent turn takes the unchanged path
            // below, so ordinary questions are unaffected.
            if (($routedQuestion['plan']['kind'] ?? 'single') === 'compound') {
                return $this->answerCompound($request, $user, $validated['message'], $routedQuestion);
            }

            if ($result === null) {
                $result = $this->answerService->answer($user, $routedQuestion);
            }

            if (($routedQuestion['follow_up'] ?? false) && ($result['status'] ?? null) === 'forbidden') {
                $request->session()->forget($this->conversationContext->contextKey((int) $user->getAuthIdentifier()));
                $result = [
                    'status' => 'clarification',
                    'intent' => 'clarification',
                    'capability' => null,
                    'answer' => ['clarification_question' => 'Which item would you like to check?'],
                ];
            }

            // Whether the provider may phrase this turn's answer is decided by
            // AnswerComposer::mayGenerateFor(), next to the grounding rule that
            // backs it up. Factual turns attempt generation too and fall back to
            // localReply() whenever generation is refused or rejected.
            $reply = $this->answerComposer->mayGenerateFor($routedQuestion['capability'] ?? null)
                && ($result['status'] ?? null) === 'success'
                ? $this->aiService->ask($user, $validated['message'], $result)
                : $this->answerService->localReply($result, $user);

            $this->conversationContext->updateContext($request, $user, $routedQuestion, $result);
            $this->conversationContext->updateClarification($request, $user, $routedQuestion, $result);
            $this->conversationContext->appendTurn(
                $user,
                $validated['message'],
                $reply,
                is_string($routedQuestion['intent'] ?? null) ? $routedQuestion['intent'] : null,
                is_string($routedQuestion['capability'] ?? null) ? $routedQuestion['capability'] : null,
                $request,
            );

            // Appended, never overwritten. This is the write-back edge that
            // keeps a conversation reachable past the live topic snapshot.
            $this->turnLog->record(
                $user,
                $this->turnLog->sessionId($request, $user),
                $validated['message'],
                $reply,
                $routedQuestion,
                $result,
                $this->answerService->lastCandidateIds($result),
                $this->answerService->lastEntityRefs($result),
            );

            return response()->json([
                'success' => true,
                'reply'   => $reply,
                'role'    => $user->role?->role_name ?? 'End User',
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Assistant Controller Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to process your question at this moment. Please try again.',
            ], 500);
        }
    }

    public function compare(InventoryComparisonRequest $request): JsonResponse
    {
        $result = $this->comparisonService->compare($request->comparisonData());
        $user = $request->user();
        $userId = (int) $user->getAuthIdentifier();
        $contextKey = $this->conversationContext->comparisonContextKey($userId);
        $request->session()->forget($this->conversationContext->contextKey($userId));
        $request->session()->forget($this->conversationContext->clarificationKey($userId));
        $summary = $this->comparisonService->comparisonContextSummary($result);
        if ($summary !== null) {
            $this->conversationContext->storeComparisonContext($request, $user, $summary);
        } else {
            $request->session()->forget($contextKey);
        }
        $result['explanation'] = $this->aiService->explainComparison($request->user(), $result);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        if ($request->hasSession()) {
            $user = $request->user();
            if ($user !== null) {
                $this->conversationContext->forgetAll($request, $user);
            }
        }

        // The recorded turns stay until retention expires. Only the identity of
        // the conversation in progress is dropped, so the next message opens a
        // new one rather than inheriting what the last one resolved.
        $this->turnLog->forgetSession($request);

        return response()->json(['success' => true]);
    }

    /**
     * Renders forecast rows as a markdown table, capped and summarised.
     *
     * The full forecast runs to 99 rows across 9 columns. Emitting all of it
     * produced a message that overwhelmed the chat panel and was unreadable, so
     * only the most useful rows appear and the complete list is pointed at the
     * Demand Forecast page, which paginates, filters and exports it properly.
     */

    /**
     * Answer a compound turn.
     *
     * Each sub-request is dispatched and authorised on its own, then the
     * answers are merged into one conversational reply. A part the role cannot
     * see is reported as unavailable rather than silently dropped, so the user
     * learns the question was understood and refused, not ignored.
     */
private function answerCompound(Request $request, User $user, string $message, array $routedQuestion): JsonResponse
    {
        $results = $this->compoundPlanExecutor->execute($user, $routedQuestion['plan']);
        $reply = $this->answerService->mergeReplies($results, $user);
        $primary = $results[0] ?? ['status' => 'unsupported', 'intent' => null, 'capability' => null, 'answer' => []];

        $this->conversationContext->updateContext($request, $user, $routedQuestion, $primary);
        $this->conversationContext->updateClarification($request, $user, $routedQuestion, $primary);
        $this->conversationContext->appendTurn(
            $user,
            $message,
            $reply,
            is_string($primary['intent'] ?? null) ? $primary['intent'] : null,
            is_string($primary['capability'] ?? null) ? $primary['capability'] : null,
            $request,
        );

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'role' => $user->role?->role_name ?? 'End User',
        ]);
    }

/**
     /**
 * Resolve references in the question against the assembled context.
 *
 * The selector is given the conversation, the payload is assembled from it, and
 * the parser is asked to rewrite the question so it stands on its own. A
 * resolution that is not grounded in the user's own words is rejected inside the
 * parser, and a null here means the raw question is used unchanged.
 *
 * @param  array<string, mixed>|null  $context
 * @return array<string, mixed>|null
 */
    private function resolveReferences(Request $request, User $user, string $question, ?array $context): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        try {
            $sessionId = $this->turnLog->sessionId($request, $user);
            $payload = $this->contextSelector->select(
                $user,
                $sessionId,
                $question,
                $context,
                $this->turnLog->lastResolved($user, $sessionId),
            );

            return $this->intentParser->resolveReferences($question, $payload);
        } catch (\Throwable $e) {
            Log::warning('AI reference resolution failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Turn an unresolved resolution into the question to ask.
     *
     * Returns null when the resolution is usable, which is the signal to continue
     * with the rewritten question. The three refusals each ask something
     * different: which item, which candidate, or what to check.
     *
     * @param  array<string, mixed>  $resolution
     * @return array<string, mixed>|null
     */
    private function ambiguityResult(User $user, array $resolution): ?array
    {
        $ambiguity = $resolution['ambiguity'] ?? null;
        if ($ambiguity === null) {
            return null;
        }

        $question = match ($ambiguity) {
            'ambiguous_candidate' => $this->answerService->clarificationReply($user, [
                'capability' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                'candidate_ids' => $resolution['candidates'] ?? [],
            ]),
            'no_entity' => 'Which item would you like to check?',
            default => 'What would you like me to check?',
        };

        return [
            'intent' => 'clarification',
            'capability' => null,
            'item_name' => null,
            'needs_external_explanation' => false,
            'query' => null,
            'vague' => true,
            'response_type' => 'detail',
            'needs_clarification' => true,
            'follow_up' => false,
            'starts_new_topic' => false,
            'topic_action' => 'unclear',
            'request_type' => 'clarification',
            'entity_candidate' => null,
            'entity_candidate_status' => 'missing',
            'ambiguous' => true,
            'clarification_question' => $question,
        ];
    }

    /**
     * Reconstruct the live topic from the recorded turns, re-authorised.
     *
     * The rebuilt payload goes through the same resolveConversationContext()
     * validation a stored topic goes through, so an item that has since been
     * deleted, disposed, or moved outside the user's capabilities rebuilds to
     * nothing rather than to a stale reference.
     */
    private function rebuiltContext(Request $request, User $user): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        try {
            $stored = $this->turnLog->rebuildTopic($user, $this->turnLog->sessionId($request, $user));
            if ($stored === null) {
                return null;
            }

            $context = $this->answerService->resolveConversationContext($user, $stored);
            if ($context === null) {
                return null;
            }

            $this->conversationContext->storeContext($request, $user, $context);

            return $context;
        } catch (\Throwable $e) {
            Log::warning('AI conversation rebuild failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Resolve an ordinal reply against the ids a previous answer offered.
     *
     * After an answer that listed several items, "the second one" has nothing to
     * select from: a pending clarification is not pending, and the list was
     * never kept. The durable turn log is, so it supplies both the candidate ids
     * and the action they were offered under.
 *
     * Returns null unless it resolves cleanly, and never raises the assistant's
     * own capability bar: the previous turn's capability is re-authorised here
     * before any record is read, so a user who has lost a capability resolves
     * nothing.
     */
    private function rememberedSelection(Request $request, User $user, string $message): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $last = $this->turnLog->lastResolved($user, $this->turnLog->sessionId($request, $user));
        if ($last === null || $last['candidate_ids'] === []) {
            return null;
        }

        try {
            return $this->answerService->resolveRememberedSelection($user, $last, $message);
        } catch (\Throwable $e) {
            Log::warning('AI remembered selection failed: '.$e->getMessage());

            return null;
        }
    }

    private function applicationFollowUp(string $question, ?array $context): ?array
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = trim(preg_replace('/[^\pL\pN\s?]/u', ' ', $normalized) ?? $normalized);
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        $followUp = match (true) {
            preg_match('/^why(?:\s+(?:is|are)\s+(?:it|this|that)\s+[^?]+)?\??$/u', $normalized) === 1 => 'why',
            preg_match('/^(?:how many|what(?: is| are) the)\s+(?:units?\s+)?(?:are\s+)?(?:available|left|remaining)(?:\s+now)?\??$/u', $normalized) === 1 => 'availability',
            preg_match('/^(?:is|are)\s+(?:it|this|that)\s+(?:available|in stock|low)(?:\s+in stock)?\??$/u', $normalized) === 1 => 'availability',
            preg_match('/^(?:where is|where are)\s+(?:it|this|that|they)\s*(?:stored|located)?\??$/u', $normalized) === 1 => 'location',
            preg_match('/^(?:who has|who is using)\s+(?:it|this|that|them)\??$/u', $normalized) === 1 => 'holder',
            preg_match('/^(?:it|this|that|those|they)\??$/u', $normalized) === 1 => 'same_topic',
            default => null,
        };

        if ($followUp === null || preg_match('/\b(?:pending|request|requests|waiting|approval)\b/u', $normalized) === 1) {
            return null;
        }

        $common = [
            'original_question' => $question,
            'normalized_question' => $normalized,
            'canonical_question_key' => hash('sha256', $normalized),
            'classification_confidence' => 'application_validated',
            'follow_up' => true,
            'starts_new_topic' => false,
            'topic_action' => 'continue_topic',
            'ambiguous' => false,
            'vague' => false,
        ];

        if ($context === null || ($context['reference_type'] ?? null) === 'inventory_search') {
            return [
                ...$common,
                'intent' => 'clarification',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => true,
                'clarification_question' => $followUp === 'why'
                    ? 'What would you like me to explain?'
                    : ($followUp === 'location' ? 'Which item would you like me to locate?' : 'Which item would you like to check?'),
                'request_type' => 'clarification',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
            ];
        }

        $capability = match ($followUp) {
            'availability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
            'location' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            'holder' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            'why' => in_array($context['capability'] ?? null, [
                AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                AiCapabilityPolicy::VIEW_LOW_STOCK,
                AiCapabilityPolicy::VIEW_ITEM_STATUS,
                AiCapabilityPolicy::VIEW_ASSIGNMENTS,
                AiCapabilityPolicy::VIEW_MAINTENANCE,
                AiCapabilityPolicy::VIEW_DISPOSAL,
                AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
                AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
            ], true) ? $context['capability'] : null,
            default => $context['capability'] ?? null,
        };

        if (! is_string($capability) || ! $this->capabilityPolicy->isKnownCapability($capability)) {
            return [
                ...$common,
                'intent' => 'clarification',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => true,
                'clarification_question' => 'Which inventory information would you like me to explain?',
                'request_type' => 'clarification',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
            ];
        }

        $intent = $followUp === 'why' ? 'explanation' : ($context['prior_intent'] ?? 'factual');
        $responseType = match ($followUp) {
            'why' => 'explanation',
            'availability' => 'count',
            default => $context['response_type'] ?? 'detail',
        };
        $identity = ($context['reference_type'] ?? null) === 'inventory_group'
            ? []
            : array_filter([
                'inventory_id' => $context['inventory_id'] ?? null,
                'category_id' => $context['category_id'] ?? null,
                'unit' => $context['unit'] ?? null,
                'serial_number' => $context['serial_number'] ?? null,
            ], fn ($value): bool => $value !== null);

        return [
            ...$common,
            ...$identity,
            'intent' => $intent,
            'capability' => $capability,
            'item_name' => $context['item_name'] ?? null,
            'needs_external_explanation' => $intent === 'explanation',
            'needs_clarification' => false,
            'response_type' => $responseType,
            'query' => null,
            'filters' => [],
            'request_type' => $intent,
            'entity_candidate' => $context['item_name'] ?? null,
            'entity_candidate_status' => 'resolved',
        ];
    }
}
