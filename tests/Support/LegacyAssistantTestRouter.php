<?php

namespace Tests\Support;

use App\Services\AiCapabilityPolicy;

class LegacyAssistantTestRouter
{
    public function __construct(private AssistantTestKeywordMatcher $keywordMatcher)
    {
    }

    // Routes a user's question to the appropriate handler based on its content and context.
    public function route(string $question, array $context = []): array
    {
        $normalized = $this->normalize($question);
        $routed = $this->classify($normalized, $context);
        if (($context['awaiting_item_filter'] ?? false) === true
            && ($routed['intent'] ?? null) === 'unsupported'
            && preg_match('/^(?:what|which|who|where|how|why|is|are|do|does|did|can|could|would|will|should)\b/u', $normalized) === 1) {
            $routed = $this->clarification('Which available item would you like to check?');
        }
        $entityCandidate = $routed['item_name'] ?? $routed['location_query'] ?? null;

        return [
            ...$routed,
            'original_question' => $question,
            'normalized_question' => $normalized,
            'canonical_question_key' => $this->canonicalQuestionKeyFromNormalized($normalized),
            'request_type' => $routed['intent'],
            'entity_candidate' => $entityCandidate,
            'entity_candidate_status' => $entityCandidate === null
                ? 'missing'
                : (($routed['needs_clarification'] ?? false) ? 'ambiguous' : 'unresolved'),
            'ambiguous' => ($routed['needs_clarification'] ?? false) === true,
            'follow_up' => ($routed['follow_up'] ?? false) === true,
            'classification_confidence' => in_array($routed['intent'], ['clarification', 'unsupported'], true)
                ? 'low'
                : 'rule_based',
        ];
    }

    public function canonicalQuestionKey(string $question): string
    {
        return $this->canonicalQuestionKeyFromNormalized($this->normalize($question));
    }

    public function hasSameCanonicalQuestion(string $first, string $second): bool
    {
        return hash_equals($this->canonicalQuestionKey($first), $this->canonicalQuestionKey($second));
    }

    public function matchType(string $firstQuestion, array $firstRoute, string $secondQuestion, array $secondRoute): ?string
    {
        if ($this->hasSameCanonicalQuestion($firstQuestion, $secondQuestion)) {
            return 'exact_canonical';
        }

        return $this->hasSameResolvedRequest($firstRoute, $secondRoute)
            ? 'same_resolved_request'
            : null;
    }

    public function hasSameResolvedRequest(array $first, array $second): bool
    {
        foreach ([$first, $second] as $request) {
            if (($request['ambiguous'] ?? false) === true
                || ($request['entity_candidate_status'] ?? null) !== 'resolved'
                || ! is_int($request['resolved_entity_id'] ?? null)
                || $request['resolved_entity_id'] <= 0
                || in_array($request['intent'] ?? null, ['clarification', 'unsupported'], true)
                || ! is_string($request['capability'] ?? null)) {
                return false;
            }
        }

        foreach (['intent', 'capability', 'resolved_entity_id', 'response_type', 'query', 'location_query', 'filters'] as $field) {
            if (($first[$field] ?? null) !== ($second[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function canonicalQuestionKeyFromNormalized(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    private function classify(string $normalized, array $context): array
    {
        $preservedItemName = $this->itemName($normalized);
        $normalized = str_replace('-', ' ', $normalized);

        if ($normalized === '') {
            return $this->unsupported();
        }

        if (in_array($normalized, ['how', 'item'], true)) {
            return $this->clarification($normalized === 'how'
                ? 'What inventory question would you like to ask?'
                : 'Which item would you like to check?');
        }

        if (preg_match('/\b(?:not|never|no)\s+(?:currently\s+)?available\b/u', $normalized) === 1
            && ! str_contains($normalized, 'no longer available')) {
            return $this->clarification('Could you clarify which inventory availability you want to check?');
        }

        // Check for "what about" questions that refer to a specific item
        if (preg_match('/^(?:what|how) about (.+)$/u', $normalized, $matches) === 1
            && ! in_array(trim($matches[1]), ['this', 'that', 'this one', 'that one', 'it', 'those', 'them', 'these', 'these ones', 'those ones', 'the item', 'the items'], true)) {
            return $this->routeWhatAbout(trim($matches[1]), $context);
        }

        // Check for generic references to items without specifying which one
        if (preg_match('/^(?:the )?(?:first|second|third)(?: one| item| match)?$/u', $normalized) === 1) {
            return $this->clarification();
        }

        // Check for follow-up questions that depend on previous context
        $followUp = $this->followUpType($normalized);
        if ($followUp !== null) {
            return $this->routeFollowUp($followUp, $context);
        }

        // Handle questions that are awaiting an item filter and meet specific criteria
        if (($context['awaiting_item_filter'] ?? false) === true
            && preg_match('/^[\p{L}\p{N}]+(?:\s+[\p{L}\p{N}]+)*$/u', $normalized) === 1
            && preg_match('/^(?:what|which|who|where|how|why|is|are|do|does|did|can|could|would|will|should)\b/u', $normalized) !== 1
            && ! $this->isGenericItemName($normalized)
            && ! $this->containsAny($normalized, ['available', 'in stock', 'stock', 'status', 'inventory', 'items', 'equipment'])) {
            return [
                ...$this->request(
                    $context['prior_intent'] ?? 'factual',
                    AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                    $normalized,
                    false,
                    null,
                    false,
                    $context['response_type'] ?? 'count'
                ),
                'starts_new_topic' => true,
            ];
        }

        $itemName = $preservedItemName ?? $this->itemName($normalized);
        $responseType = $this->responseType($normalized, $itemName);

        // Handle questions that are specifically about item availability
        if (preg_match('/^.+\s+available$/u', $normalized) === 1 && $itemName !== null) {
            $responseType = 'count';
        }
        $explanation = $this->containsAny($normalized, ['why', 'explain', 'reason']);
        $hasCount = $responseType === 'count';
        $identifierQuestion = $this->containsAny($normalized, ['inventory number', 'inventory no', 'inventory id', 'asset tag']);
        $productNameQuestion = $this->containsAny($normalized, ['product name', 'item name', 'name of the product', 'name of the item']);

        if ($this->isLocationQuestion($normalized)) {
            [$locationItem, $locationQuery] = $this->locationTargets($normalized);

            return [
                ...$this->request('factual', AiCapabilityPolicy::VIEW_INVENTORY_LOCATION, $locationItem, false, null, false, 'detail'),
                'location_query' => $locationQuery,
            ];
        }

        if ($identifierQuestion || $productNameQuestion) {
            $query = $identifierQuestion ? 'inventory_identifiers' : 'product_name';

            return $this->request(
                'factual',
                AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                $itemName,
                false,
                $query,
                false,
                'list',
                $itemName === null
            );
        }

        // Handle questions related to inventory valuation, financials, or budget
        if ($this->containsAny($normalized, ['valuation', 'financial', 'unit cost', 'budget', 'inventory value', 'total value'])) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_INVENTORY_VALUATION, $itemName, false, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        // Handle questions related to procurement, forecasting, or system summaries
        if ($this->containsAny($normalized, ['system summary', 'system overview'])) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY, null, false, null, false, $responseType);
        }

        // Handle questions related to procurement recommendations or priorities    
        if ($this->containsAny($normalized, ['procure', 'buy first', 'prioritize', 'procurement priority', 'purchase first', 'what should we purchase'])) {
            return $this->request('recommendation', AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES, $itemName, true, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        // Handle questions related to forecasting, demand, or usage predictions
        if ($this->containsAny($normalized, ['forecast', 'demand', 'predict usage', 'estimate usage'])) {
            return $this->request('forecast', AiCapabilityPolicy::VIEW_DEMAND_FORECAST, $itemName, true, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        $ownAssignments = $this->containsAny($normalized, [
            'assigned items', 'assignments', 'items assigned to me', 'my assignments', 'my assigned items',
            'items are assigned to me', 'what is assigned to me', 'assigned to my account', 'in my custody',
            'what do i have', 'my equipment',
        ]);
        $ownRequests = $this->containsAny($normalized, ['my request', 'my requests', 'assignment request', 'return request']);
        $topicMatches = $this->keywordMatcher->matchTopics($normalized);
        $pendingRequests = $topicMatches['pendingRequests'];
        $lowStock = $topicMatches['lowStock'];
        $availability = $topicMatches['availability'];
        $assigned = $topicMatches['assigned'];
        $disposed = $topicMatches['disposed'];
        $underMaintenance = $topicMatches['underMaintenance'];
        $readyToDispose = $topicMatches['readyToDispose'];
        $itemSearch = $topicMatches['itemSearch'];
        $location = $topicMatches['location'];
        $purchaseHistory = $topicMatches['purchaseHistory'];
        $demandForecast = $topicMatches['demandForecast'];
        $procurement = $topicMatches['procurement'];
        $itemHistory = $topicMatches['itemHistory'];
        $inventoryMovement = $topicMatches['inventoryMovement'];
        $quantityChanges = $topicMatches['quantityChanges'];
        $expired = $topicMatches['expired'];
        $inventorySummary = $topicMatches['inventorySummary'];
        $count = $topicMatches['count'];
        $reports = $topicMatches['reports'];
        $maintenanceHistory = $topicMatches['maintenanceHistory'];
        $assignmentHistory = $topicMatches['assignmentHistory'];
        $hasCount = $hasCount || $count;
        if ($count) {
            $responseType = 'count';
        }

        
        // Handle questions related to stock details, such as item details, information about items, and inventory status
        $stockDetails = $this->containsAny($normalized, [
            'detail', 'information about', 'information for', 'describe', 'tell me about',
            'status of', 'what is the status', 'inventory status', 'stock status',
        ]);

        // If the question is about the user's own requests and contains keywords indicating pending or waiting status, route it to the appropriate handler for factual information about their own requests.
        if ($ownRequests && $this->containsAny($normalized, ['pending', 'waiting', 'unprocessed', 'outstanding'])) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_OWN_REQUESTS, $itemName, false, $hasCount ? 'pending_count' : null, false, $responseType);
        }

        // If the question is about the user's own assignments, route it to the appropriate handler for factual information about their own assignments.
        if ($ownAssignments) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS, null, false, null, false, 'list');
        }

        if ($assignmentHistory) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_ASSIGNMENTS, $itemName, false, null, false, 'list');
        }

        // If the question is about requests in general, route it to the appropriate handler for factual information about all pending inventory requests.
        if (in_array($normalized, ['request', 'requests'], true)) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_OWN_REQUESTS, null, false, null, true, 'list');
        }

        if ($purchaseHistory) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_PURCHASE_HISTORY, $itemName, false, null, false, $responseType);
        }

        if ($demandForecast) {
            return $this->request('forecast', AiCapabilityPolicy::VIEW_DEMAND_FORECAST, $itemName, true, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($procurement) {
            return $this->request('recommendation', AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES, $itemName, true, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($maintenanceHistory) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_MAINTENANCE, $itemName, false, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($location) {
            [$locationItem, $locationQuery] = $this->locationTargets($normalized);
            if ($locationItem === null && $locationQuery === null) {
                return $this->clarification('Which item or building/room should I look up?');
            }

            return [
                ...$this->request('factual', AiCapabilityPolicy::VIEW_INVENTORY_LOCATION, $locationItem, false, null, false, $responseType),
                'location_query' => $locationQuery,
            ];
        }

        if ($itemSearch
            && ! $identifierQuestion
            && ! $productNameQuestion
            && ! $purchaseHistory
            && ! $demandForecast
            && ! $procurement
            && ! $location
            && ! $inventorySummary
            && ! $reports
            && ! $pendingRequests
            && ! $ownRequests
            && ! $ownAssignments
            && ! $maintenanceHistory
            && ! $assignmentHistory
            && ! $underMaintenance
            && ! $disposed
            && ! $readyToDispose
            && ! $assigned
            && ! $lowStock
            && ! $availability) {
            $genericList = $this->containsAny($normalized, [
                'all items', 'all inventory', 'list items', 'list inventory', 'inventory list', 'search all inventory',
            ]);
            if ($itemName === null && ! $genericList) {
                return $this->clarification('Which item would you like me to find?');
            }

            return $this->request('factual', AiCapabilityPolicy::VIEW_INVENTORY_STOCK, $itemName, false, null, false, $responseType);
        }

        if ($inventorySummary) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS, null, false, null, false, 'list');
        }

        if ($itemHistory || $inventoryMovement || $quantityChanges || $expired) {
            return $this->unsupported();
        }

        if ($reports || $this->containsAny($normalized, ['trend', 'trends', 'summary', 'summaries'])) {
            if ($this->containsAny($normalized, ['maintenance report'])) {
                return $this->request('factual', AiCapabilityPolicy::VIEW_MAINTENANCE, $itemName, false, null, false, $responseType);
            }
            if ($this->containsAny($normalized, ['assignment report'])) {
                return $this->request('factual', AiCapabilityPolicy::VIEW_ASSIGNMENTS, $itemName, false, null, false, $responseType);
            }
            if ($this->containsAny($normalized, ['disposal report'])) {
                return $this->request('factual', AiCapabilityPolicy::VIEW_DISPOSAL, $itemName, false, null, false, $responseType);
            }
            if ($this->containsAny($normalized, ['forecast report'])) {
                return $this->request('forecast', AiCapabilityPolicy::VIEW_DEMAND_FORECAST, $itemName, true, null, false, $responseType);
            }
            if ($this->containsAny($normalized, ['procurement report'])) {
                return $this->request('recommendation', AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES, $itemName, true, null, false, $responseType);
            }

            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS, null, $explanation, null, false, 'list');
        }

        if ($pendingRequests) {
            $query = $hasCount || preg_match('/\bare there\b/', $normalized) === 1 ? 'pending_count' : null;
            return $this->request('factual', AiCapabilityPolicy::VIEW_PENDING_REQUESTS, $itemName, false, $query, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($ownRequests) {
            return $this->request('factual', AiCapabilityPolicy::VIEW_OWN_REQUESTS, $itemName, false, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($underMaintenance) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_MAINTENANCE, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($disposed) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_DISPOSAL, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($readyToDispose) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_READY_TO_DISPOSE, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($assigned && $itemName !== null) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_ASSIGNMENTS, $itemName, $explanation, null, false, $responseType);
        }

        if ($lowStock) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_LOW_STOCK, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($availability) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        if ($itemName !== null && ($stockDetails || $this->containsAny($normalized, ['show', 'check', 'find', 'what is', 'what are']))) {
            return $this->request($explanation ? 'explanation' : 'factual', AiCapabilityPolicy::VIEW_INVENTORY_STOCK, $itemName, $explanation, null, false, $responseType === 'list' ? 'detail' : $responseType);
        }

        if ($this->containsAny($normalized, ['request', 'requests'])) {
            if ($this->containsAny($normalized, ['waiting', 'pending', 'unprocessed', 'outstanding', 'approval'])) {
                $query = $hasCount || preg_match('/\bare there\b/', $normalized) === 1 ? 'pending_count' : null;
                return $this->request('factual', AiCapabilityPolicy::VIEW_PENDING_REQUESTS, null, false, $query, false, $responseType);
            }

            return $this->clarification('Do you mean your requests or all pending inventory requests?');
        }

        if (preg_match('/\b(?:show|check|find|describe|details?)\s+(?:me\s+)?(?:the\s+)?(?:item|items|inventory|equipment|stock|supplies|assets|property|goods)\b/', $normalized) === 1) {
            return $this->clarification();
        }

        if ($stockDetails && $itemName === null) {
            return $this->clarification();
        }

        if ($this->containsAny($normalized, ['inventory', 'stock', 'quantity', 'count', 'status'])
            || ($hasCount && $itemName !== null)) {
            $intent = $explanation ? 'explanation' : 'factual';
            return $this->request($intent, AiCapabilityPolicy::VIEW_INVENTORY_STOCK, $itemName, $explanation, null, false, $responseType, $this->needsItemClarification($normalized, $itemName));
        }

        return $this->unsupported();
    }

    protected function request(
        string $intent,
        string $capability,
        ?string $itemName,
        bool $needsExplanation,
        ?string $query = null,
        bool $vague = false,
        string $responseType = 'list',
        bool $needsClarification = false
    ): array
    {
        return [
            'intent' => $intent,
            'capability' => $capability,
            'item_name' => $itemName,
            'needs_external_explanation' => $needsExplanation,
            'query' => $query,
            'vague' => $vague,
            'response_type' => $responseType,
            'needs_clarification' => $needsClarification,
        ];
    }

    protected function unsupported(): array
    {
        return [
            'intent' => 'unsupported',
            'capability' => null,
            'item_name' => null,
            'needs_external_explanation' => false,
            'response_type' => 'list',
            'needs_clarification' => false,
        ];
    }

    protected function routeFollowUp(string $followUp, array $context): array
    {
        if (! $this->validContext($context)) {
            $question = $followUp === 'why'
                ? 'What would you like me to explain?'
                : 'Which item would you like to check?';

            return [
                ...$this->clarification($question),
                'follow_up' => true,
            ];
        }

        $capability = match ($followUp) {
            'location' => AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            'product_name', 'inventory_identifiers' => AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
            'count', 'availability' => AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
            'low_stock' => AiCapabilityPolicy::VIEW_LOW_STOCK,
            'why' => in_array($context['capability'], [
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
            default => $context['capability'],
        };

        if ($capability === null) {
            return [
                ...$this->clarification('Which inventory information would you like me to explain?'),
                'follow_up' => true,
            ];
        }

        $intent = match ($followUp) {
            'why' => 'explanation',
            'item' => $context['prior_intent'] ?? 'factual',
            default => 'factual',
        };

        $responseType = match ($followUp) {
            'why' => 'explanation',
            'count' => 'count',
            'product_name', 'inventory_identifiers' => 'list',
            'item' => $context['response_type'] ?? 'detail',
            default => 'detail',
        };
        $query = match ($followUp) {
            'product_name' => 'product_name',
            'inventory_identifiers' => 'inventory_identifiers',
            default => null,
        };
        $identity = ($context['group_context'] ?? false) === true
            ? []
            : [
                'inventory_id' => (int) $context['inventory_id'],
                'category_id' => (int) $context['category_id'],
                'unit' => $context['unit'],
                'serial_number' => $context['serial_number'] ?? null,
            ];

        return [
            ...$this->request(
                $intent,
                $capability,
                $context['item_name'],
                $intent === 'explanation',
                $query,
                false,
                $responseType
            ),
            ...$identity,
            'follow_up' => true,
        ];
    }

    protected function routeWhatAbout(string $itemName, array $context): array
    {
        $itemName = trim(preg_replace('/^(?:the|a|an)\s+/u', '', $itemName) ?? $itemName);

        if (! $this->validPriorAction($context)) {
            return [
                ...$this->clarification("What would you like to know about {$itemName}?"),
                'follow_up' => true,
            ];
        }

        $intent = $context['prior_intent'];
        $responseType = in_array($context['response_type'] ?? null, ['detail', 'count', 'list', 'explanation'], true)
            ? $context['response_type']
            : 'detail';

        return [
            ...$this->request(
                $intent,
                $context['capability'],
                $itemName,
                $intent === 'explanation',
                null,
                false,
                $responseType,
                $this->needsItemClarification($itemName, null)
            ),
            'starts_new_topic' => true,
        ];
    }

    protected function validPriorAction(array $context): bool
    {
        return in_array($context['prior_intent'] ?? null, ['factual', 'explanation'], true)
            && in_array($context['capability'] ?? null, [
                AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                AiCapabilityPolicy::VIEW_LOW_STOCK,
                AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
                AiCapabilityPolicy::VIEW_ITEM_STATUS,
                AiCapabilityPolicy::VIEW_ASSIGNMENTS,
                AiCapabilityPolicy::VIEW_MAINTENANCE,
                AiCapabilityPolicy::VIEW_DISPOSAL,
                AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
                AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
            ], true)
            && in_array($context['response_type'] ?? null, ['detail', 'count', 'list', 'explanation'], true);
    }

    protected function validContext(array $context): bool
    {
        if (($context['awaiting_item_filter'] ?? false) === true) {
            $expectedKeys = ['prior_intent', 'capability', 'response_type', 'reference_type', 'awaiting_item_filter'];

            return array_diff(array_keys($context), $expectedKeys) === []
                && array_diff($expectedKeys, array_keys($context)) === []
                && ($context['reference_type'] ?? null) === 'inventory_search'
                && in_array($context['prior_intent'] ?? null, ['factual', 'explanation'], true)
                && ($context['capability'] ?? null) === AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY
                && in_array($context['response_type'] ?? null, ['count', 'list'], true);
        }

        if (($context['group_context'] ?? false) === true) {
            $expectedKeys = ['prior_intent', 'capability', 'response_type', 'item_name', 'reference_type', 'group_context'];

            return array_diff(array_keys($context), $expectedKeys) === []
                && array_diff($expectedKeys, array_keys($context)) === []
                && is_string($context['item_name'] ?? null)
                && trim($context['item_name']) !== ''
                && ($context['reference_type'] ?? null) === 'inventory_group'
                && in_array($context['prior_intent'] ?? null, ['factual', 'explanation'], true)
                && in_array($context['response_type'] ?? null, ['detail', 'count', 'list', 'explanation'], true)
                && in_array($context['capability'] ?? null, [
                    AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                    AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                    AiCapabilityPolicy::VIEW_LOW_STOCK,
                ], true);
        }

        return is_int($context['inventory_id'] ?? null)
            && $context['inventory_id'] > 0
            && is_int($context['category_id'] ?? null)
            && $context['category_id'] > 0
            && is_string($context['item_name'] ?? null)
            && trim($context['item_name']) !== ''
            && is_string($context['unit'] ?? null)
            && trim($context['unit']) !== ''
            && (is_null($context['serial_number'] ?? null) || is_string($context['serial_number']))
            && in_array($context['prior_intent'] ?? null, ['factual', 'explanation'], true)
            && ($context['reference_type'] ?? null) === 'inventory'
            && is_int($context['reference_id'] ?? null)
            && $context['reference_id'] === $context['inventory_id']
            && in_array($context['capability'] ?? null, [
                AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                AiCapabilityPolicy::VIEW_LOW_STOCK,
                AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
                AiCapabilityPolicy::VIEW_ITEM_STATUS,
                AiCapabilityPolicy::VIEW_ASSIGNMENTS,
                AiCapabilityPolicy::VIEW_MAINTENANCE,
                AiCapabilityPolicy::VIEW_DISPOSAL,
                AiCapabilityPolicy::VIEW_READY_TO_DISPOSE,
                AiCapabilityPolicy::VIEW_PURCHASE_HISTORY,
            ], true);
    }

    protected function clarification(string $question = 'Which item would you like to check?'): array
    {
        return [
            'intent' => 'clarification',
            'capability' => null,
            'item_name' => null,
            'needs_external_explanation' => false,
            'response_type' => 'clarification',
            'needs_clarification' => true,
            'clarification_question' => $question,
        ];
    }

    // Determine the type of follow-up question based on the content of the question.
    protected function followUpType(string $question): ?string
    {
        if (preg_match('/^why[?.!]*$/u', $question) === 1
            || preg_match('/^why (?:is (?:it|this|that)|are (?:they|those|these))(?:\s+low(?: in)? stock)?$/u', $question) === 1) {
            return 'why';
        }

        if (preg_match('/^what is (?:the )?(?:name|product name|item name)(?: (?:of|for|the) (?:product|item|it|this|that)(?: name)?)?$/u', $question) === 1) {
            return 'product_name';
        }

        if (preg_match('/^what is (?:the )?(?:inventory number|inventory no|inventory id|asset tag)(?: of (?:it|this|that))?$/u', $question) === 1) {
            return 'inventory_identifiers';
        }

        if (in_array($question, ['it', 'those', 'them', 'this', 'that', 'these', 'this one', 'that one', 'these ones', 'those ones'], true)
            || preg_match('/^(?:what|how) about (?:it|those|them|this|that|these|this one|that one|these ones|those ones)$/u', $question) === 1) {
            return 'item';
        }

        if (preg_match('/^(?:is (?:it|this|that)|are (?:those|they|these)) available$/u', $question) === 1
            || str_contains($question, 'is it available') || str_contains($question, 'is this available') || str_contains($question, 'are those available')) {
            return 'availability';
        }

        if (preg_match('/^(?:where is|where are|where can i find) (?:it|this|that|these|those|them|the item|the items)$/u', $question) === 1) {
            return 'location';
        }

        if (preg_match('/^who has (?:it|this|that|these|those|them|the item|the items)$/u', $question) === 1) {
            return 'location';
        }

        if (preg_match('/^(?:is (?:it|this|that)|are (?:those|they|these)) low(?: in)? stock$/u', $question) === 1) {
            return 'low_stock';
        }

        if (preg_match('/^how many (?:(?:of (?:it|this|that|these|those|them) )?(?:are )?(?:available|left|remaining)|(?:left|remaining))$/u', $question) === 1
            || preg_match('/how many (?:are )?(?:available|left|remaining)/u', $question) === 1) {
            return 'count';
        }

        return null;
    }

    protected function responseType(string $question, ?string $itemName): string
    {
        if ($this->containsAny($question, ['why', 'explain', 'reason'])) {
            return 'explanation';
        }
        if (preg_match('/\bhow many\b|\bhow much\b|\bcount\b|\bnumber of\b|\bremaining quantity\b|\bquantity left\b|\btotal\b/', $question) === 1) {
            return 'count';
        }
        if ($this->containsAny($question, ['which items', 'what items', 'what supplies', 'list', 'show all', 'all available', 'all inventory'])) {
            return 'list';
        }

        return $itemName === null ? 'list' : 'detail';
    }

    protected function needsItemClarification(string $question, ?string $itemName): bool
    {
        if ($itemName !== null) {
            return false;
        }

        return preg_match('/\b(?:this|that|the)\s+(?:item|items|equipment|inventory|stock)\b|\b(?:available|in stock)\s+item\b|\bitem\s+(?:available|in stock)\b|\b(?:how many|how much)(?:\s+(?:items?|stock|quantity))?\s+(?:are\s+|is\s+)?(?:left|remaining)\b/', $question) === 1;
    }

    protected function containsAny(string $question, array $terms): bool
    {
        foreach ($terms as $term) {
            $pattern = '/(?:^|\s)' . preg_quote($term, '/') . '(?:$|\s)/u';
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(string $question): string
    {
        $question = mb_strtolower(trim(str_replace(['‐', '‑', '–', '—'], '-', $question)));
        $question = str_replace(['’', '‘', 'ʼ'], "'", $question);
        foreach ([
            '/\bavailabe\b/' => 'available',
            '/\bavaiable\b/' => 'available',
            '/\bquatity\b/' => 'quantity',
            '/\bpendng\b/' => 'pending',
            "/\\bwhat's\\b/" => 'what is',
        ] as $pattern => $replacement) {
            $question = preg_replace($pattern, $replacement, $question) ?? $question;
        }

        $question = preg_replace('/(?<![\p{L}\p{N}])[.,!?;:]+|[.,!?;:]+(?![\p{L}\p{N}])/u', ' ', $question) ?? $question;
        $question = preg_replace("/[^\\p{L}\\p{N}\\s._\/#@+%&'-]/u", ' ', $question) ?? $question;

        return trim(preg_replace('/\s+/u', ' ', $question) ?? $question);
    }

    protected function itemName(string $question): ?string
    {
        if (preg_match('/\b(?:product|item) name\s+of\s+(?:the\s+)?(?:available|in stock)\s+items?\b/u', $question) === 1) {
            return null;
        }

        $patterns = [
            '/\bhow much\s+(.+?)\s+stock\s+(?:is\s+)?(?:left|remaining)\b/',
            '/\b(?:is there|are there|is|are)\s+(?:any\s+)?(?:a\s+|an\s+)?(.+?)\s+(?:low in stock|available|in stock)\b/',
            '/\b(?:how many|how much)\s+(.+?)\s+(?:stock\s+)?(?:are\s+)?(?:available|in stock|left|remaining)\b/',
            '/\b(?:what is|what are)\s+(?:the\s+)?(?:remaining\s+)?(?:quantity|stock)\s+of\s+(.+)$/',
            '/\b(?:remaining quantity|quantity left|stock left|stock remaining)\s+(?:of\s+)?(.+)$/',
            '/\bdo we\s+(?:still\s+)?have\s+(.+)$/',
            '/\b(?:is there|do we have)\s+(?:any\s+)?(.+)$/',
            '/\b(?:details?|information)\s+(?:for|about)\s+(?:the\s+)?(.+)$/',
            '/\b(?:tell me about|describe|show|check|find|search(?: for)?|look for|locate)\s+(?:me\s+)?(?:the\s+)?(.+?)(?:\s+details?)?$/',
            '/\b(?:what is|what are)\s+(?:the\s+)?status\s+of\s+(.+)$/',
            '/\b(?:is|are|was|were)\s+(?:the\s+)?(.+?)\s+(?:under maintenance|being repaired|in repair|in maintenance|not working|broken|malfunctioning|needs repair)\b/',
            '/\b(?:was|were|has|have)\s+(?:the\s+)?(.+?)\s+(?:been\s+)?(?:disposed(?: of)?|discarded|thrown away|trashed)\b/',
            '/\b(?:is|are)\s+(?:the\s+)?(.+?)\s+(?:ready to dispose|ready for disposal|ready for discard|ready for removal|ready to be discarded|ready to be removed|ready to be trashed)\b/',
            '/\bwho\s+(?:is|was|are|were)\s+(?:the\s+)?(.+?)\s+assigned to\b/',
            '/\bstatus\s+of\s+(.+)$/',
            '/\b(?:predict|estimate)\s+(?:the\s+)?(?:demand|usage)\s+(?:for|of)\s+(.+)$/',
            '/\b(?:total value|inventory value|unit cost|cost of|value of)\s+(?:of\s+)?(?:the\s+)?(.+)$/',
            '/\bwhy\s+(?:is|are)\s+(.+?)\s+(?:low(?:\s+in)?\s+stock|available|in stock)\b/',
            '/\b(?:is|are)\s+(?:the\s+)?(.+?)\s+(?:low in stock|available|in stock|assigned)\b/',
            '/\b(?:available|in stock)\s+(?:of\s+|for\s+)?(.+)$/',
            '/\b(.+?)\s+available$/',
            '/\b(?:inventory(?: item)? number|inventory no|inventory id|asset tag)\s+(?:of|for)\s+(?:the\s+)?(.+)$/',
            '/\b(?:product|item) name\s+(?:of|for)\s+(?:the\s+)?(.+)$/',
            '/\bname of (?:the )?(?:product|item)\s+(.+)$/',
            '/\b(?:stock|quantity)\s+(?:of|for)\s+(.+)$/',
            '/\b(?:low stock|low in stock|running out|running low)\s+(?:for|of|on)\s+(.+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $question, $matches) === 1) {
                $item = trim($matches[1]);
                $item = preg_replace('/\s+(?:boxes?|pieces?|units?|pcs?|sets?|pairs?)$/i', '', $item) ?: $item;
                $item = preg_replace('/\s+is$/i', '', $item) ?: $item;
                $item = preg_replace('/(?:\s+|-)stock$/i', '', $item) ?: $item;
                $item = preg_replace('/^(?:the|a|an)\s+/i', '', $item) ?: $item;
                $item = trim(preg_replace('/\s+/u', ' ', $item) ?? $item);

                if (preg_match('/^(?:how many|how much|what|which|who|where|is there|are there)\b/u', $item) === 1) {
                    continue;
                }

                if ($item !== '' && ! $this->isGenericItemName($item)) {
                    return $item;
                }
            }
        }

        return null;
    }

    protected function isLocationQuestion(string $question): bool
    {
        return preg_match('/\bwhere\b|\blocation\b|\bwho has\b|\bassigned to room\b|\bwhich items? (?:are )?in\b|\bwhat items? (?:are )?in\b/', $question) === 1;
    }

    protected function locationTargets(string $question): array
    {
        foreach ([
            '/\bwhere is (?:the )?(.+?)\s+(?:stored|located)\s*$/u',
            '/\bwhere can i find (?:the )?(.+?)\s*$/u',
            '/\bwhere is (?:the )?(.+?)\s*$/',
            '/\bwho has (?:the )?(.+?)\s*$/',
            '/\blocation of (?:the )?(.+?)\s*$/',
        ] as $pattern) {
            if (preg_match($pattern, $question, $matches) === 1) {
                return [trim($matches[1]), null];
            }
        }

        foreach ([
            '/\bwhat equipment is stored in (?:the )?(.+?)\s*$/u',
            '/\b(?:what|which) items? (?:are )?stored in (?:the )?(.+?)\s*$/u',
            '/\bwhat is assigned to (?:the )?(room\s+\w+)\s*$/',
            '/\b(?:what|which) items? (?:are )?assigned to (?:the )?(room\s+\w+)\s*$/',
            '/\b(?:what|which) items? (?:are )?in (?:the )?(.+?)\s*$/',
        ] as $pattern) {
            if (preg_match($pattern, $question, $matches) === 1) {
                return [null, trim($matches[1])];
            }
        }

        return [null, null];
    }

    protected function isGenericItemName(string $item): bool
    {
        $normalized = $this->normalize($item);
        if (preg_match('/^(?:available|in stock) items?$/u', $normalized) === 1) {
            return true;
        }
        if (preg_match('/^(?:inventory|stock)\s+(?:report|reports|trend|trends|summary|overview)$/', $normalized) === 1
            || preg_match('/\b(?:items?|inventory|equipment|stock|supplies|assets|property|goods|requests?)$/', $normalized) === 1) {
            return true;
        }
        if (preg_match('/^(?:(?:this|that|the)\s+)?(?:item|items|inventory|equipment|stock|supplies|assets|property|goods)$/', $normalized) === 1) {
            return true;
        }

        return in_array($normalized, [
            'item', 'items', 'inventory', 'equipment', 'stock', 'supplies', 'assets', 'property', 'goods',
            'stock is', 'quantity is',
            'low', 'low in stock', 'available', 'in stock', 'it', 'this', 'that', 'one', 'this one', 'that one',
            'those', 'them', 'these', 'these ones', 'those ones',
            'are', 'is', 'any', 'request', 'requests', 'all items', 'all inventory',
        ], true);
    }
}
