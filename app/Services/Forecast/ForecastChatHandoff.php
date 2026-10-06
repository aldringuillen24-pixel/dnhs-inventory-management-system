<?php

namespace App\Services\Forecast;

use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Conversation\ConversationContextManager;
use App\Services\ForecastExplanationService;
use App\Services\ProcurementQuestionMatcher;
use App\Services\StoredDemandForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hands forecast and procurement questions to the Demand Forecast page.
 *
 * Demand forecasting and AI Decision Support are not the assistant's job. This
 * handoff recognises the questions that belong there, keeps the small amount of
 * follow-up state needed to answer "explain that one", and redirects.
 *
 * Extracted from AiAssistantController verbatim: the same detection patterns,
 * the same branch order, the same MAX_TABLE_ROWS cap, the same status strings.
 * Nothing here was extended — a forecast question that was delegated before is
 * delegated now, and the tool path still refuses to produce forecast data.
 */
class ForecastChatHandoff
{
    /**
     * Maximum forecast rows rendered into a single chat message.
     *
     * The assistant is a conversational surface, not a data grid. Anything
     * longer belongs on the Demand Forecast page.
     */
    private const MAX_TABLE_ROWS = 10;

    public function __construct(
        protected AiCapabilityPolicy $capabilityPolicy,
        protected StoredDemandForecastService $storedForecastService,
        protected ForecastExplanationService $forecastExplanationService,
        protected ConversationContextManager $conversationContext,
        protected ProcurementQuestionMatcher $procurementMatcher,
    ) {
    }

    /**
     * Whether this message belongs to the forecast hand-off.
     *
     * When it does not, any stored forecast context is dropped here, because a
     * non-forecast question must not leave a forecast topic armed for the next
     * message.
     */
    public function owns(Request $request, User $user, string $message): bool
    {
        $contextKey = $this->conversationContext->forecastContextKey((int) $user->getAuthIdentifier());
        $context = $this->loadForecastContext($request, $user, $contextKey);
        $owned = $this->isDemoForecastRequest($message)
            || $this->isForecastRequest($message)
            || $this->isUrgencyQuestion($message)
            || $this->isProcurementQuestion($message)
            || ($context !== null && $this->isForecastFollowUp($message));

        if (! $owned && $context !== null) {
            $request->session()->forget($contextKey);
        }

        return $owned;
    }

    /**
     * Answer a forecast question, delegating to the Demand Forecast page.
     *
     * Clears the inventory, clarification and comparison context first so a
     * forecast reply never inherits them, then answers.
     */
    public function reply(Request $request, User $user, string $question): JsonResponse
    {
        $contextKey = $this->conversationContext->forecastContextKey((int) $user->getAuthIdentifier());
        $context = $this->loadForecastContext($request, $user, $contextKey);
        $explicitDemoRequest = $this->isDemoForecastRequest($question);
        $this->conversationContext->forgetNonForecastContext($request, $user);

        return $this->forecastChatReply($request, $user, $question, $contextKey, $context, $explicitDemoRequest);
    }

    /**
     * Delegated forecast intent is recognised here, not by the assistant's
     * tool router, so a forecast question never reaches the factual tools.
     */
    public function isDelegatedIntent(?string $intent): bool
    {
        return in_array($intent, ['forecast', 'recommendation'], true);
    }
    public function isForecastRequest(string $message): bool
    {
        return preg_match('/\b(?:demand\s+forecast|forecast(?:s|ing)?|forecasted\s+demand)\b/iu', $message) === 1;
    }
    public function isForecastFollowUp(string $message): bool
    {
        return preg_match('/\b(?:urgent|urgency|priority|priorities|explain|why|confidence|table|more|again|this|that|it)\b/iu', $message) === 1
            || preg_match('/^\s*(?:inventory\s*)?(?:id\s*)?#?\d+\s*[.!?]?\s*$/iu', $message) === 1;
    }
    public function isUrgencyQuestion(string $message): bool
    {
        return preg_match('/\b(?:(?:more|most)\s+urgent|what(?:\s+is|\x27s)?\s+(?:the\s+)?urgent|highest\s+priority|top\s+priority|needs?\s+attention\s+first)\b/iu', $message) === 1;
    }
    public function isFullForecastListRequest(string $message): bool
    {
        return preg_match('/\b(?:all|full|complete)\b.*\b(?:forecast(?:ed)?\s+)?(?:items?|list)\b|\b(?:list|show)\s+all\b.*\bforecast/iu', $message) === 1;
    }
    public function isProcurementQuestion(string $message): bool
    {
        return $this->procurementMatcher->matches($message);
    }
    public function isReferentialForecastFollowUp(string $message): bool
    {
        return preg_match('/\b(?:this|that|it|these|those)\b/iu', $message) === 1
            || $this->isProcurementQuestion($message)
            || preg_match('/\b(?:why|explain)\b/iu', $message) === 1;
    }
    public function isDemoForecastRequest(string $message): bool
    {
        return preg_match('/\b(?:sample|demo)\b.*\b(?:demand|forecast)\b|\b(?:demand|forecast)\b.*\b(?:sample|demo)\b/iu', $message) === 1;
    }
    public function requestedForecastPeriod(string $question): ?string
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
    public function forecastChatReply(
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
    public function demoForecastChatReply(
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
    public function explainDemoRows(\App\Models\User $user, array $forecast, $rows): array
    {
        $scopedForecast = $forecast;
        $scopedForecast['rows'] = $rows->all();

        return $this->forecastExplanationService->explainDemo($user, $scopedForecast);
    }
    public function matchingForecastRows($rows, string $question)
    {
        preg_match('/\b(?:inventory\s*)?id\s*#?(\d+)\b/iu', $question, $idMatch);

        return isset($idMatch[1])
            ? $rows->where('inventory_id', (int) $idMatch[1])->values()
            : $rows->filter(fn (array $row): bool => stripos($question, $row['item_name']) !== false)->values();
    }
    public function formatForecastTable(array $rows, ?string $period, bool $ranked): string
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

    private function loadForecastContext(Request $request, User $user, string $sessionKey): ?array
    {
        return $this->conversationContext->loadForecastContext($request, $user);
    }

    private function storeForecastContext(Request $request, User $user, string $sessionKey, array $context): void
    {
        $this->conversationContext->storeForecastContext($request, $user, $context);
    }
}