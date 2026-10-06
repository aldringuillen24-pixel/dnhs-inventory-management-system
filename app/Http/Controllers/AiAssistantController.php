<?php

namespace App\Http\Controllers;

use App\Services\AiInventoryService;
use App\Services\AiCapabilityPolicy;
use App\Services\Conversation\ConversationContextManager;
use App\Services\ForecastExplanationService;
use App\Services\InventoryAnswerService;
use App\Services\AiIntentParserService;
use App\Services\InventoryComparisonService;
use App\Services\StoredDemandForecastService;
use App\Http\Requests\InventoryComparisonRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiAssistantController extends Controller
{
    /**
     * Maximum forecast rows rendered into a single chat message.
     *
     * The assistant is a conversational surface, not a data grid. Anything
     * longer belongs on the Demand Forecast page.
     */
    private const MAX_TABLE_ROWS = 10;

    public function __construct(
        protected AiIntentParserService $intentParser,
        protected InventoryAnswerService $answerService,
        protected AiInventoryService $aiService,
        protected AiCapabilityPolicy $capabilityPolicy,
        protected InventoryComparisonService $comparisonService,
        protected StoredDemandForecastService $storedForecastService,
        protected ForecastExplanationService $forecastExplanationService,
        protected ConversationContextManager $conversationContext,
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

            $state = $this->conversationContext->loadState($request, $user);
            $forecastContext = $state->forecast;
            $explicitDemoRequest = $this->isDemoForecastRequest($validated['message']);
            $forecastRequest = $this->isForecastRequest($validated['message']);
            $forecastFollowUp = $forecastContext !== null && $this->isForecastFollowUp($validated['message']);
            if ($explicitDemoRequest || $forecastRequest || $this->isUrgencyQuestion($validated['message'])
                || $this->isProcurementQuestion($validated['message']) || $forecastFollowUp) {
                $this->conversationContext->forgetNonForecastContext($request, $user);

                return $this->forecastChatReply(
                    $request,
                    $user,
                    $validated['message'],
                    $this->conversationContext->forecastContextKey((int) $user->getAuthIdentifier()),
                    $forecastContext,
                    $explicitDemoRequest,
                );
            }
            if ($forecastContext !== null) {
                $request->session()->forget($this->conversationContext->forecastContextKey((int) $user->getAuthIdentifier()));
            }

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
                    $newQuestion = $this->intentParser->route($validated['message'], $clarificationAction);
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
                $routedQuestion = $this->applicationFollowUp($validated['message'], $context);
            }

            $routedQuestion ??= $this->intentParser->route($validated['message'], $context ?? []);
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

            $providerMayExplain = in_array($routedQuestion['capability'] ?? null, [
                \App\Services\AiCapabilityPolicy::VIEW_DEMAND_FORECAST,
                \App\Services\AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES,
                \App\Services\AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS,
                \App\Services\AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                \App\Services\AiCapabilityPolicy::VIEW_ITEM_STATUS,
                \App\Services\AiCapabilityPolicy::VIEW_ASSIGNMENTS,
                \App\Services\AiCapabilityPolicy::VIEW_MAINTENANCE,
                \App\Services\AiCapabilityPolicy::VIEW_DISPOSAL,
                \App\Services\AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
                \App\Services\AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
                \App\Services\AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
            ], true);
            $reply = (($routedQuestion['needs_external_explanation'] ?? false)
                && $providerMayExplain
                && ($result['status'] ?? null) === 'success')
                ? $this->aiService->ask($user, $validated['message'], $result)
                : $this->answerService->localReply($result, $user);

            $this->conversationContext->updateContext($request, $user, $routedQuestion, $result);
            $this->conversationContext->updateClarification($request, $user, $routedQuestion, $result);

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

        return response()->json(['success' => true]);
    }

    private function isForecastRequest(string $message): bool
    {
        return preg_match('/\b(?:demand\s+forecast|forecast(?:s|ing)?|forecasted\s+demand)\b/iu', $message) === 1;
    }

    private function isForecastFollowUp(string $message): bool
    {
        return preg_match('/\b(?:urgent|urgency|priority|priorities|explain|why|confidence|table|more|again|this|that|it)\b/iu', $message) === 1
            || preg_match('/^\s*(?:inventory\s*)?(?:id\s*)?#?\d+\s*[.!?]?\s*$/iu', $message) === 1;
    }

    private function isUrgencyQuestion(string $message): bool
    {
        return preg_match('/\b(?:(?:more|most)\s+urgent|what(?:\s+is|\x27s)?\s+(?:the\s+)?urgent|highest\s+priority|top\s+priority|needs?\s+attention\s+first)\b/iu', $message) === 1;
    }

    private function isFullForecastListRequest(string $message): bool
    {
        return preg_match('/\b(?:all|full|complete)\b.*\b(?:forecast(?:ed)?\s+)?(?:items?|list)\b|\b(?:list|show)\s+all\b.*\bforecast/iu', $message) === 1;
    }

    private function isProcurementQuestion(string $message): bool
    {
        return preg_match('/\b(?:why|need|should|must)\b.*\b(?:procure|procurement|purchase|buy|order)\b/iu', $message) === 1;
    }

    private function isReferentialForecastFollowUp(string $message): bool
    {
        return preg_match('/\b(?:this|that|it|these|those)\b/iu', $message) === 1
            || $this->isProcurementQuestion($message)
            || preg_match('/\b(?:why|explain)\b/iu', $message) === 1;
    }

    private function loadForecastContext(Request $request, \App\Models\User $user, string $sessionKey): ?array
    {
        return $this->conversationContext->loadForecastContext($request, $user);
    }

    private function storeForecastContext(Request $request, \App\Models\User $user, string $sessionKey, array $context): void
    {
        $this->conversationContext->storeForecastContext($request, $user, $context);
    }

    private function forecastChatReply(
        Request $request,
        \App\Models\User $user,
        string $question,
        string $contextKey,
        ?array $context,
        bool $explicitDemoRequest,
    ): JsonResponse {
        if (! $this->capabilityPolicy->allows($user, AiCapabilityPolicy::VIEW_DEMAND_FORECAST)) {
            $request->session()->forget($contextKey);

            return response()->json(['success' => false, 'message' => 'That information is not available for your role.'], 403);
        }

        $demoMode = $explicitDemoRequest
            || ($context['source_type'] ?? null) === 'demo'
            || (config('forecast.demo_mode') && ($this->isForecastRequest($question)
                || $this->isUrgencyQuestion($question) || $this->isProcurementQuestion($question)));
        if ($demoMode) {
            return $this->demoForecastChatReply($request, $user, $question, $contextKey, $context, $explicitDemoRequest);
        }

        $forecast = $this->storedForecastService->read($user);
        if (($forecast['status'] ?? null) !== 'success' && ($forecast['status'] ?? null) !== 'insufficient_data') {
            $request->session()->forget($contextKey);
            $message = match ($forecast['status'] ?? null) {
                'stale' => 'The stored ML forecast is stale. No proxy estimate was substituted; ask the Property Custodian to refresh it.',
                'missing' => 'No stored ML forecast is available yet. Forecast training runs separately from chat.',
                'forbidden' => 'That information is not available for your role.',
                default => 'The stored ML forecast is invalid or unavailable. No proxy estimate was substituted.',
            };

            return response()->json(['success' => true, 'reply' => $message, 'role' => $user->role?->role_name ?? 'End User']);
        }

        $rows = collect($forecast['rows'])->where('status', 'success')->values();
        if (is_array($context) && ($context['source_type'] ?? null) === 'live') {
            $ids = $context['inventory_ids'];
            if (! $this->isFullForecastListRequest($question) && ! $this->isUrgencyQuestion($question)) {
                $rows = $rows->whereIn('inventory_id', $ids)->values();
            }
        }
        $expectedPeriod = isset($forecast['forecast_period'])
            ? \Illuminate\Support\Carbon::parse($forecast['forecast_period'])->format('Y-m')
            : null;
        $requestedPeriod = $this->requestedForecastPeriod($question);
        if ($requestedPeriod !== null && $expectedPeriod !== $requestedPeriod) {
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $rows->pluck('inventory_id')->all(),
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);

            return response()->json([
                'success' => true,
                'reply' => 'The validated stored forecast covers '.($forecast['forecast_period'] ?? 'one forecast period').'. I cannot estimate a different period from this result. Would you like the available period?',
                'role' => $user->role?->role_name ?? 'End User',
            ]);
        }

        $matches = $this->matchingForecastRows($rows, $question);
        if ($matches->isEmpty()
            && is_array($context)
            && isset($context['selected_inventory_id'])
            && $this->isReferentialForecastFollowUp($question)) {
            $matches = $rows->where('inventory_id', $context['selected_inventory_id'])->values();
        }
        $fullListRequested = $this->isFullForecastListRequest($question);
        if ($this->isProcurementQuestion($question) && $matches->isEmpty()) {
            $request->session()->forget($contextKey);

            return response()->json([
                'success' => true,
                'reply' => 'Which inventory item should I explain? Please provide its exact name or inventory ID.',
                'role' => $user->role?->role_name ?? 'End User',
            ]);
        }
        $asksForItem = preg_match('/\b(?:for|about)\s+/iu', $question) === 1
            && $requestedPeriod === null
            && preg_match('/\b(?:all|every|each)\s+(?:items?|inventory)\b/iu', $question) !== 1;
        if ($asksForItem && $matches->isEmpty()) {
            $request->session()->forget($contextKey);
            $reply = 'Which inventory item should I explain? Please provide its exact name or inventory ID.';
        } elseif ($matches->count() > 1) {
            $choices = $matches->map(fn (array $row): string => "ID {$row['inventory_id']} ({$row['category']})")->implode(', ');
            $reply = "That item name matches multiple inventory records: {$choices}. Which inventory ID should I use?";
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $matches->pluck('inventory_id')->all(),
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($rows->isEmpty()) {
            $request->session()->forget($contextKey);
            $reply = 'There is not enough verified history for an ML forecast in the stored result. No estimate was substituted.';
        } elseif ($this->isUrgencyQuestion($question)) {
            $rows = $rows->sortBy([['priority_rank', 'desc'], ['suggested_procurement', 'desc'], ['item_name', 'asc']])->values();
            $reply = $this->formatForecastTable($rows->all(), $forecast['forecast_period'] ?? null, true);
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $rows->pluck('inventory_id')->all(),
                'selected_inventory_id' => $rows->first()['inventory_id'] ?? null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($matches->count() === 1) {
            $row = $matches->first();
            $explanation = $this->forecastExplanationService->explain($user, $row);
            $reply = $explanation['explanation'];
            $rows = collect([$row]);
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => [(int) $row['inventory_id']],
                'selected_inventory_id' => (int) $row['inventory_id'],
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($fullListRequested) {
            $reply = $this->formatForecastTable($rows->all(), $forecast['forecast_period'] ?? null, false);
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $rows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($matches->isEmpty() && ($context === null || $this->isForecastRequest($question)) && ! $this->isUrgencyQuestion($question)) {
            $reply = 'Would you like the full forecast list, details for a specific item, or an explanation of the forecast? Please include an inventory ID for duplicate item names.';
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $rows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } else {
            $reply = 'Which inventory item should I explain? Please provide its exact name or inventory ID.';
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'live',
                'inventory_ids' => $rows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        }

        return response()->json(['success' => true, 'reply' => $reply, 'role' => $user->role?->role_name ?? 'End User']);
    }

    private function demoForecastChatReply(
        Request $request,
        \App\Models\User $user,
        string $question,
        string $contextKey,
        ?array $context,
        bool $explicitDemoRequest,
    ): JsonResponse {
        $forecast = $this->storedForecastService->readDemo($user);
        $allRows = collect($forecast['rows'] ?? []);
        $fullListRequested = $this->isFullForecastListRequest($question);
        $urgencyQuestion = $this->isUrgencyQuestion($question);
        $rows = $allRows;
        if (is_array($context)
            && ($context['source_type'] ?? null) === 'demo'
            && ! $fullListRequested
            && ! $urgencyQuestion
            && ! $explicitDemoRequest) {
            $rows = $rows->whereIn('inventory_id', $context['inventory_ids'])->values();
        }

        if ($allRows->isEmpty() || in_array($forecast['status'] ?? null, ['missing', 'error', 'forbidden'], true)) {
            $request->session()->forget($contextKey);
            $result = $this->forecastExplanationService->explainDemo($user, $forecast);

            return response()->json(['success' => true, 'reply' => $result['explanation'], 'role' => $user->role?->role_name ?? 'End User']);
        }

        $requestedPeriod = $this->requestedForecastPeriod($question);
        $expectedPeriod = isset($forecast['forecast_period'])
            ? \Illuminate\Support\Carbon::createFromFormat('!Y-m', $forecast['forecast_period'])->format('Y-m')
            : null;
        if ($requestedPeriod !== null && $requestedPeriod !== $expectedPeriod) {
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);

            return response()->json([
                'success' => true,
                'reply' => 'The Sample/Demo result covers '.($forecast['forecast_period'] ?? 'one sample period').' only. No Sample/Demo forecast is available for the requested period.',
                'role' => $user->role?->role_name ?? 'End User',
            ]);
        }

        $matches = $this->matchingForecastRows($rows, $question);
        $selectedId = $context['selected_inventory_id'] ?? null;
        if ($matches->isEmpty() && $selectedId !== null && $this->isReferentialForecastFollowUp($question)) {
            $matches = $rows->where('inventory_id', $selectedId)->values();
        }

        if ($urgencyQuestion) {
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
            $reply = 'Sample/Demo forecasts do not include live available stock, pending demand, or safety stock, so I cannot rank procurement urgency. Would you like the full sample list or details for a specific inventory ID?';
        } elseif ($this->isProcurementQuestion($question) && $matches->isEmpty()) {
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
            $reply = 'Which Sample/Demo inventory ID do you mean? Sample forecasts have no live stock, pending demand, or safety-stock calculation and cannot establish a procurement need.';
        } elseif ($matches->count() > 1) {
            $choices = $matches->map(fn (array $row): string => "ID {$row['inventory_id']} ({$row['category']})")->implode(', ');
            $reply = "That name matches multiple Sample/Demo items: {$choices}. Which inventory ID should I explain?";
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $matches->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($matches->count() === 1) {
            $result = $this->explainDemoRows($user, $forecast, $matches);
            $reply = $result['explanation'];
            if ($this->isProcurementQuestion($question)) {
                $reply .= "\nThis Sample/Demo prediction alone cannot establish whether procurement is needed; live stock, pending demand, and safety stock are not included.";
            }
            $selectedId = (int) $matches->first()['inventory_id'];
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => [$selectedId],
                'selected_inventory_id' => $selectedId,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif ($fullListRequested) {
            $result = $this->forecastExplanationService->explainDemo($user, $forecast);
            $reply = $result['explanation'];
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } elseif (is_array($context)
            && ($context['source_type'] ?? null) === 'demo'
            && $selectedId === null
            && $this->isReferentialForecastFollowUp($question)) {
            $reply = 'Which Sample/Demo inventory ID should I explain? Sample predictions alone cannot establish a procurement need.';
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
        } else {
            $this->storeForecastContext($request, $user, $contextKey, [
                'source_type' => 'demo',
                'inventory_ids' => $allRows->pluck('inventory_id')->all(),
                'selected_inventory_id' => null,
                'forecast_period' => (string) ($forecast['forecast_period'] ?? ''),
            ]);
            $reply = 'Would you like the full Sample/Demo list or details for a specific item? Please include an inventory ID if names are duplicated. Sample/Demo results cannot determine procurement urgency.';
        }

        return response()->json(['success' => true, 'reply' => $reply, 'role' => $user->role?->role_name ?? 'End User']);
    }

    private function explainDemoRows(\App\Models\User $user, array $forecast, $rows): array
    {
        $scopedForecast = $forecast;
        $scopedForecast['rows'] = $rows->all();

        return $this->forecastExplanationService->explainDemo($user, $scopedForecast);
    }

    private function matchingForecastRows($rows, string $question)
    {
        preg_match('/\b(?:inventory\s*)?id\s*#?(\d+)\b/iu', $question, $idMatch);

        return isset($idMatch[1])
            ? $rows->where('inventory_id', (int) $idMatch[1])->values()
            : $rows->filter(fn (array $row): bool => stripos($question, $row['item_name']) !== false)->values();
    }

    /**
     * Renders forecast rows as a markdown table, capped and summarised.
     *
     * The full forecast runs to 99 rows across 9 columns. Emitting all of it
     * produced a message that overwhelmed the chat panel and was unreadable, so
     * only the most useful rows appear and the complete list is pointed at the
     * Demand Forecast page, which paginates, filters and exports it properly.
     */
    private function formatForecastTable(array $rows, ?string $period, bool $ranked): string
    {
        if ($rows === []) {
            return 'No validated forecast rows are available for this scope.';
        }

        // Always present the rows in decision order, never in inventory-id
        // order. A "full forecast list" previously came back sorted by ID, so
        // Normal rows were interleaved with High ones and the summary looked
        // arbitrary even though it was complete.
        //
        // Sorted as three stable passes rather than one multi-key sortBy array:
        // that form expects nested [property, direction] pairs, so a flat list
        // of closures is silently treated as a single comparison.
        $ordered = collect($rows)
            ->sortBy(fn (array $row): string => (string) ($row['item_name'] ?? ''))
            ->sortByDesc(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0))
            ->sortByDesc(fn (array $row): int => (int) ($row['priority_rank'] ?? 0))
            ->values()
            ->all();

        $total = count($ordered);
        $shown = array_slice($ordered, 0, self::MAX_TABLE_ROWS);

        $title = $ranked ? 'Advisory forecast priorities' : 'Validated ML demand forecast';
        $lines = [
            "{$title} for ".($period ?: 'the stored forecast period').'. Advisory only; no inventory changes or purchase orders are created.',
        ];

        // A one-line breakdown so the ranking below it reads at a glance.
        $counts = collect($ordered)
            ->groupBy(fn (array $row): string => (string) ($row['priority'] ?? 'Normal'))
            ->map(fn ($group): int => $group->count())
            ->sortDesc()
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->implode(' · ');

        if ($counts !== '') {
            $lines[] = $counts.' — '.$total.($total === 1 ? ' item' : ' items').' in the forecast.';
        }

        if ($total > count($shown)) {
            $lines[] = 'Showing the '.count($shown).' highest priority of '.$total.' items.';
        }

        // Narrowed to the decision columns. The removed ones (available stock,
        // pending demand, confidence, advisory status) are still shown on the
        // Demand Forecast page and explained by AI Decision Support.
        $lines[] = '| Item | Predicted demand | Safety stock | Suggested quantity | Priority |';
        $lines[] = '|---|---:|---:|---:|---|';

        foreach ($shown as $row) {
            $lines[] = '| '.$row['item_name'].' (ID '.$row['inventory_id'].') | '
                .($row['forecast_demand'] ?? 'N/A').' '.($row['unit'] ?? '')
                .' | '.($row['safety_stock'] ?? 'N/A').' '.($row['unit'] ?? '')
                .' | '.($row['suggested_procurement'] ?? 'N/A').' '.($row['unit'] ?? '')
                .' | '.($row['priority'] ?? '—').' |';
        }

        if ($total > count($shown)) {
            $lines[] = '';
            $lines[] = 'That is the top '.count($shown).', not the full list. Open **Demand Forecast** in the sidebar to filter and sort all '
                .$total.' items and export them as a PDF, or use **AI Decision Support** there to ask what to buy first.';
        }

        return implode("\n", $lines);
    }

    private function requestedForecastPeriod(string $question): ?string
    {
        if (preg_match('/\b(20\d{2}-(?:0[1-9]|1[0-2]))\b/u', $question, $matches) === 1) {
            return $matches[1];
        }

        $monthPattern = 'January|February|March|April|May|June|July|August|September|October|November|December';
        if (preg_match('/\b('.$monthPattern.')\s+(20\d{2})\b/iu', $question, $matches) === 1) {
            return \Illuminate\Support\Carbon::createFromFormat('!F Y', ucfirst(strtolower($matches[1])).' '.$matches[2])->format('Y-m');
        }

        return null;
    }

    private function isDemoForecastRequest(string $message): bool
    {
        return preg_match('/\b(?:sample|demo)\b.*\b(?:demand|forecast)\b|\b(?:demand|forecast)\b.*\b(?:sample|demo)\b/iu', $message) === 1;
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
