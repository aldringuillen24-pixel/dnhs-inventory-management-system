<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AiIntentParserService
{
    private const INTENTS = [
        'availability',
        'stock',
        'pending_requests',
        'low_stock',
        'forecast',
        'procurement_priorities',
        'executive_reports',
        'system_summary',
        'inventory_valuation',
        'own_assignments',
        'own_requests',
        'item_status',
        'assignments',
        'maintenance',
        'disposal',
        'ready_to_dispose',
        'location',
        'purchase_history',
        'explanation',
        'continue',
        'inherit',
        'unsupported',
        'unclear',
    ];

    private const EXPLANATION_TOPICS = [
        'availability',
        'stock',
        'pending_requests',
        'low_stock',
        'forecast',
        'procurement_priorities',
        'executive_reports',
        'system_summary',
        'inventory_valuation',
        'own_assignments',
        'own_requests',
        'item_status',
        'assignments',
        'maintenance',
        'disposal',
        'ready_to_dispose',
        'location',
        'purchase_history',
    ];

    private const RESPONSE_TYPES = ['detail', 'count', 'list', 'explanation', 'same_as_topic'];

    private const TOPIC_ACTIONS = ['continue_topic', 'new_topic', 'unclear'];

    public function __construct(
        private AiCapabilityPolicy $capabilityPolicy,
        private GeminiApiService $geminiApi,
    )
    {
    }

    public function parse(string $question, array $activeTopic = []): ?array
    {
        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            return null;
        }

        try {
            $content = $this->geminiApi->generate(
                $this->systemPrompt(),
                json_encode([
                    'question' => $question,
                    'active_topic' => $this->safeTopicForParsing($activeTopic),
                ], JSON_UNESCAPED_SLASHES) ?: '',
                [
                    'responseFormat' => [
                        'text' => [
                            'mimeType' => 'APPLICATION_JSON',
                            'schema' => $this->structuredResponseSchema(),
                        ],
                    ],
                    'temperature' => 0,
                    'maxOutputTokens' => 384,
                ]
            );
            if (! is_string($content)) {
                return null;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                Log::warning('Gemini intent parser returned invalid JSON.', [
                    'json_error' => json_last_error_msg(),
                    'model' => config('services.gemini.model'),
                ]);

                return null;
            }

            $validated = $this->validate($decoded, $question);
            if ($validated === null) {
                Log::warning('Gemini intent parser response failed schema validation.', [
                    'fields' => array_keys($decoded),
                    'model' => config('services.gemini.model'),
                ]);
            }

            return $validated;
        } catch (\Throwable $exception) {
            Log::warning('Gemini intent parsing failed: '.$exception->getMessage());

            return null;
        }
    }

    public function route(string $question, array $activeTopic = []): array
    {
        $normalized = mb_strtolower(trim($question));
        $common = [
            'original_question' => $question,
            'normalized_question' => $normalized,
            'canonical_question_key' => hash('sha256', $normalized),
            'classification_confidence' => 'ai_validated',
        ];

        if ($this->isClearlyNonInventoryQuestion($normalized)) {
            return [
                ...$common,
                'intent' => 'unsupported',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => false,
                'follow_up' => false,
                'starts_new_topic' => true,
                'topic_action' => 'new_topic',
                'request_type' => 'unsupported',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => false,
                'vague' => false,
            ];
        }

        $parsed = $this->parse($question, $activeTopic);

        if ($parsed === null || $parsed['topic_action'] === 'unclear' || $parsed['intent'] === 'unclear') {
            return [
                ...$common,
                'intent' => 'clarification',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => true,
                'clarification_question' => 'Which inventory item or question would you like me to check?',
                'follow_up' => false,
                'starts_new_topic' => false,
                'topic_action' => 'unclear',
                'request_type' => 'clarification',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => true,
                'vague' => true,
            ];
        }

        if ($parsed['intent'] === 'unsupported') {
            return [
                ...$common,
                'intent' => 'unsupported',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => false,
                'follow_up' => false,
                'starts_new_topic' => true,
                'topic_action' => 'new_topic',
                'request_type' => 'unsupported',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => false,
                'vague' => false,
            ];
        }

        $topicAction = $parsed['topic_action'];
        $usesActiveAction = in_array($parsed['intent'], ['continue', 'inherit'], true);
        if ($usesActiveAction && ! $this->hasUsableTopic($activeTopic)) {
            return [
                ...$common,
                'intent' => 'clarification',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => true,
                'clarification_question' => 'Which inventory item would you like me to check?',
                'follow_up' => false,
                'starts_new_topic' => false,
                'topic_action' => 'unclear',
                'request_type' => 'clarification',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => true,
                'vague' => true,
            ];
        }

        $capability = $usesActiveAction
            ? ($activeTopic['capability'] ?? null)
            : $this->capabilityPolicy->capabilityForIntent(
                $parsed['intent'] === 'explanation' ? $parsed['explanation_topic'] : $parsed['intent']
            );
        if (! is_string($capability) || ! $this->capabilityPolicy->isKnownCapability($capability)) {
            return [
                ...$common,
                'intent' => 'unsupported',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => false,
                'follow_up' => false,
                'starts_new_topic' => true,
                'topic_action' => 'new_topic',
                'request_type' => 'unsupported',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => false,
                'vague' => false,
            ];
        }

        $selectors = array_filter([
            'item_name' => $parsed['item_name'],
            'inventory_id' => $parsed['inventory_id'],
            'serial_number' => $parsed['serial_number'],
            'location_query' => $parsed['location_query'],
            'category_id' => $parsed['filters']['category_id'],
            'unit' => $parsed['filters']['unit'],
        ], fn ($value): bool => $value !== null);

        if ($topicAction === 'continue_topic') {
            $activeName = $activeTopic['item_name'] ?? null;
            if (isset($selectors['item_name']) && is_string($activeName)
                && mb_strtolower($selectors['item_name']) !== mb_strtolower($activeName)) {
                $topicAction = 'new_topic';
            } elseif (isset($selectors['inventory_id']) && isset($activeTopic['reference_id'])
                && $selectors['inventory_id'] !== $activeTopic['reference_id']) {
                $topicAction = 'new_topic';
            }
        }

        if ($topicAction === 'continue_topic') {
            $selectors = array_replace(array_filter([
                'item_name' => $activeTopic['item_name'] ?? null,
                'inventory_id' => $activeTopic['inventory_id'] ?? $activeTopic['reference_id'] ?? null,
                'category_id' => $activeTopic['category_id'] ?? null,
                'unit' => $activeTopic['unit'] ?? null,
                'serial_number' => $activeTopic['serial_number'] ?? null,
            ], fn ($value): bool => $value !== null), $selectors);
        } elseif ($parsed['intent'] === 'inherit' && $selectors === []) {
            return [
                ...$common,
                'intent' => 'clarification',
                'capability' => null,
                'needs_external_explanation' => false,
                'needs_clarification' => true,
                'clarification_question' => 'Which inventory item would you like to check?',
                'follow_up' => false,
                'starts_new_topic' => false,
                'topic_action' => 'unclear',
                'request_type' => 'clarification',
                'entity_candidate' => null,
                'entity_candidate_status' => 'missing',
                'ambiguous' => true,
                'vague' => true,
            ];
        }

        $intent = match (true) {
            $parsed['intent'] === 'explanation' => 'explanation',
            $parsed['intent'] === 'forecast' => 'forecast',
            $parsed['intent'] === 'procurement_priorities' => 'recommendation',
            $usesActiveAction => $activeTopic['prior_intent'] ?? 'factual',
            default => 'factual',
        };
        $responseType = $parsed['response_type'] === 'same_as_topic'
            ? ($activeTopic['response_type'] ?? 'detail')
            : $parsed['response_type'];
        $topicEntity = $selectors['item_name'] ?? $selectors['location_query'] ?? null;
        $route = [
            ...$common,
            'intent' => $intent,
            'capability' => $capability,
            'item_name' => $selectors['item_name'] ?? null,
            'inventory_id' => $selectors['inventory_id'] ?? null,
            'serial_number' => $selectors['serial_number'] ?? null,
            'location_query' => $selectors['location_query'] ?? null,
            'category_id' => $selectors['category_id'] ?? null,
            'unit' => $selectors['unit'] ?? null,
            'query' => $parsed['query'],
            'filters' => $parsed['filters'],
            'response_type' => $responseType,
            'needs_external_explanation' => $parsed['explanation'] || $parsed['intent'] === 'explanation',
            'needs_clarification' => false,
            'vague' => false,
            'follow_up' => $topicAction === 'continue_topic',
            'starts_new_topic' => $topicAction === 'new_topic',
            'topic_action' => $topicAction,
            'request_type' => $parsed['intent'],
            'entity_candidate' => $topicEntity,
            'entity_candidate_status' => $topicEntity === null ? 'missing' : 'unresolved',
            'ambiguous' => false,
        ];

        return $route;
    }

    private function isClearlyNonInventoryQuestion(string $question): bool
    {
        if (preg_match('/\bweather\b/u', $question) === 1) {
            return true;
        }

        return preg_match('/\b(?:how many|number of|count of)\s+(?:users?|people|staff|employees)\b/u', $question) === 1
            && preg_match('/\b(?:inventory|item|equipment|asset|stock|assigned)\b/u', $question) !== 1;
    }

    private function hasUsableTopic(array $topic): bool
    {
        return is_string($topic['capability'] ?? null)
            && $this->capabilityPolicy->isKnownCapability($topic['capability']);
    }

    private function safeTopicForParsing(array $topic): array
    {
        $safe = [];
        foreach (['prior_intent', 'capability', 'response_type', 'reference_type', 'item_name'] as $key) {
            $value = $topic[$key] ?? null;
            if (is_string($value) && mb_strlen($value) <= 255) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function validate(array $parsed, string $question): ?array
    {
        $expectedKeys = ['intent', 'topic_action', 'item_name', 'inventory_id', 'serial_number', 'location_query', 'filters', 'query', 'response_type', 'explanation', 'explanation_topic'];
        if (array_diff(array_keys($parsed), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($parsed)) !== []) {
            return null;
        }

        $intent = $parsed['intent'];
        $topicAction = $parsed['topic_action'];
        $itemName = $parsed['item_name'];
        $inventoryId = $parsed['inventory_id'];
        $serialNumber = $parsed['serial_number'];
        $locationQuery = $parsed['location_query'];
        $filters = $parsed['filters'];
        $query = $parsed['query'];
        $responseType = $parsed['response_type'];
        $explanation = $parsed['explanation'];
        $explanationTopic = $parsed['explanation_topic'];

        if (! is_string($intent) || ! in_array($intent, self::INTENTS, true)
            || ! is_string($topicAction) || ! in_array($topicAction, self::TOPIC_ACTIONS, true)
            || ! (is_null($itemName) || (is_string($itemName) && trim($itemName) !== '' && mb_strlen(trim($itemName)) <= 255))
            || ! (is_null($inventoryId) || (is_int($inventoryId) && $inventoryId > 0))
            || ! (is_null($serialNumber) || (is_string($serialNumber) && trim($serialNumber) !== '' && mb_strlen(trim($serialNumber)) <= 255))
            || ! (is_null($locationQuery) || (is_string($locationQuery) && trim($locationQuery) !== '' && mb_strlen(trim($locationQuery)) <= 255))
            || ! is_array($filters)
            || array_diff(array_keys($filters), ['category_id', 'unit']) !== []
            || array_diff(['category_id', 'unit'], array_keys($filters)) !== []
            || ! (is_null($filters['category_id']) || (is_int($filters['category_id']) && $filters['category_id'] > 0))
            || ! (is_null($filters['unit']) || (is_string($filters['unit']) && trim($filters['unit']) !== '' && mb_strlen(trim($filters['unit'])) <= 100))
            || ! (is_null($query) || in_array($query, ['pending_count', 'inventory_identifiers', 'product_name'], true))
            || ! is_string($responseType) || ! in_array($responseType, self::RESPONSE_TYPES, true)
            || ! is_bool($explanation)) {
            return null;
        }

        if (($intent === 'continue' && $topicAction !== 'continue_topic')
            || ($intent === 'inherit' && $topicAction !== 'new_topic')
            || ($intent === 'explanation' && ! $explanation)
            || ($intent !== 'explanation' && $explanation)
            || ($query !== null && $query === 'pending_count' && ! in_array($intent, ['pending_requests', 'own_requests'], true))
            || ($query !== null && in_array($query, ['inventory_identifiers', 'product_name'], true) && $intent !== 'stock')
            || ($responseType === 'same_as_topic' && ! in_array($intent, ['continue', 'inherit'], true))) {
            return null;
        }

        if ($intent === 'explanation') {
            if (! is_string($explanationTopic) || ! in_array($explanationTopic, self::EXPLANATION_TOPICS, true)) {
                return null;
            }
        } elseif ($explanationTopic !== null) {
            return null;
        }

        if (in_array($intent, ['continue', 'inherit', 'unsupported', 'unclear'], true)
            ? $explanationTopic !== null
            : ($intent !== 'explanation' && $this->capabilityPolicy->capabilityForIntent($intent) === null)) {
            return null;
        }

        foreach ([$itemName, $serialNumber, $locationQuery, $filters['unit']] as $selector) {
            if (is_string($selector) && ! $this->selectorAppearsInQuestion($selector, $question)) {
                return null;
            }
        }
        foreach ([$inventoryId, $filters['category_id']] as $identifier) {
            if (is_int($identifier)
                && preg_match('/(?<!\d)'.preg_quote((string) $identifier, '/').'(?!\d)/u', $question) !== 1) {
                return null;
            }
        }

        return [
            'intent' => $intent,
            'topic_action' => $topicAction,
            'item_name' => is_string($itemName) ? trim($itemName) : null,
            'inventory_id' => $inventoryId,
            'serial_number' => is_string($serialNumber) ? trim($serialNumber) : null,
            'location_query' => is_string($locationQuery) ? trim($locationQuery) : null,
            'filters' => [
                'category_id' => $filters['category_id'],
                'unit' => is_string($filters['unit']) ? trim($filters['unit']) : null,
            ],
            'query' => $query,
            'response_type' => $responseType,
            'explanation' => $explanation,
            'explanation_topic' => $explanationTopic,
        ];
    }

    private function systemPrompt(): string
    {
        return 'Classify the user question only. Do not answer it, access data, or propose actions. Return one JSON object with exactly these keys: intent, topic_action, item_name, inventory_id, serial_number, location_query, filters, query, response_type, explanation, explanation_topic. '
            .'intent must be availability, stock, pending_requests, low_stock, forecast, procurement_priorities, executive_reports, system_summary, inventory_valuation, own_assignments, own_requests, item_status, assignments, maintenance, disposal, ready_to_dispose, location, purchase_history, explanation, continue, inherit, unsupported, or unclear. '
            .'topic_action must be continue_topic, new_topic, or unclear. Use continue when the message only refers to the active topic and inherit when a new named item continues the active topic action. Use an active topic only for follow-ups or action inheritance; otherwise classify the new request independently. '
            .'Map explanation questions to intent explanation and set explanation_topic to the supported fact topic; otherwise explanation_topic must be null. Use only item names, inventory IDs, serial numbers, location names, units, and category IDs explicitly stated in the question; use null when absent. '
            .'filters must contain exactly category_id and unit. query must be null, pending_count, inventory_identifiers, or product_name. Use pending_count only when explicitly counting pending requests; use inventory_identifiers only when asking for an inventory number, ID, or asset tag; use product_name only when asking for the product name. For ordinary availability or stock questions, query must be null, including count questions. '
            .'response_type must be detail, count, list, explanation, or same_as_topic. explanation must be a boolean and true only for intent explanation. Never return SQL, permissions, database instructions, answers, or additional keys.';
    }

    private function structuredResponseSchema(): array
    {
        $properties = [
            'intent' => ['type' => 'STRING', 'enum' => self::INTENTS],
            'topic_action' => ['type' => 'STRING', 'enum' => self::TOPIC_ACTIONS],
            'item_name' => ['type' => 'STRING', 'nullable' => true, 'maxLength' => 255],
            'inventory_id' => ['type' => 'INTEGER', 'nullable' => true, 'minimum' => 1],
            'serial_number' => ['type' => 'STRING', 'nullable' => true, 'maxLength' => 255],
            'location_query' => ['type' => 'STRING', 'nullable' => true, 'maxLength' => 255],
            'filters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'category_id' => ['type' => 'INTEGER', 'nullable' => true, 'minimum' => 1],
                    'unit' => ['type' => 'STRING', 'nullable' => true, 'maxLength' => 100],
                ],
                'required' => ['category_id', 'unit'],
                'additionalProperties' => false,
            ],
            'query' => [
                'type' => 'STRING',
                'nullable' => true,
                'enum' => ['pending_count', 'inventory_identifiers', 'product_name'],
            ],
            'response_type' => ['type' => 'STRING', 'enum' => self::RESPONSE_TYPES],
            'explanation' => ['type' => 'BOOLEAN'],
            'explanation_topic' => [
                'type' => 'STRING',
                'nullable' => true,
                'enum' => self::EXPLANATION_TOPICS,
            ],
        ];

        return [
            'type' => 'OBJECT',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private function selectorAppearsInQuestion(string $selector, string $question): bool
    {
        $normalize = static fn (string $value): string => trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '');
        $needle = $normalize($selector);
        $haystack = $normalize($question);
        if ($needle === '' || $haystack === '') {
            return false;
        }

        $pattern = preg_quote($needle, '/');
        $pattern = preg_replace('/([^ ]+)$/u', '$1s?', $pattern) ?? $pattern;

        return preg_match('/(?<![\pL\pN])'.$pattern.'(?![\pL\pN])/u', $haystack) === 1;
    }
}
