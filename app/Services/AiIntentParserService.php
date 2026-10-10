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

    /**
     * Why a question could not be resolved.
     *
     * Used by the ambiguity gate. `null` means the question stands on its own.
     */
    private const AMBIGUITIES = ['no_entity', 'ambiguous_candidate', 'no_topic'];

    /**
     * How many recent exchanges are shown to the classifier. Kept small so the
     * classification prompt stays small and predictable.
     */
    private const MAX_PARSE_TURNS = 4;

    public function __construct(
        private AiCapabilityPolicy $capabilityPolicy,
        private GeminiApiService $geminiApi,
    )
    {
    }

    public function parse(string $question, array $activeTopic = [], array $turns = []): ?array
    {
        if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
            return null;
        }

        try {
            $content = $this->geminiApi->generate(
                $this->systemPrompt(),
                json_encode([
                    'question' => $question,
                    'active_topic' => $this->safeTopicForParsing($activeTopic, $turns),
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

    /**
 * Resolve references in a question against the assembled conversation context.
 *
 * This runs before the intent parser, and the parser receives the resolved
 * question rather than the raw one. It is a separate call with a separate
 * schema on purpose: the intent contract is validated with a strict both-ways
 * key diff, so extending it would invalidate every existing fixture.
 *
 * Returns null whenever it cannot produce a resolution that is grounded in the
 * user's own words. A null is a no-op, and the caller passes the raw question
 * through exactly as before, so a provider failure can never remove an answer
 * that used to work.
 *
 * @param  array<string, mixed>  $context  Assembled by the context selector.
 * @return array<string, mixed>|null
 */
public function resolveReferences(string $question, array $context): ?array
{
    if (! $this->needsReferenceResolution($question, $context)) {
        return null;
    }

    if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
        return null;
    }

    try {
        $content = $this->geminiApi->generate(
            $this->referenceResolutionPrompt(),
            json_encode([
                'question' => $question,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES) ?: '',
            [
                'responseFormat' => [
                    'text' => [
                        'mimeType' => 'APPLICATION_JSON',
                        'schema' => $this->referenceResponseSchema(),
                    ],
                ],
                'temperature' => 0,
                'maxOutputTokens' => 256,
            ]
        );

        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        $validated = $this->validateReferenceResolution($decoded, $question, $context);
        if ($validated === null) {
            Log::warning('Reference resolution was rejected.', [
                'reason' => 'not grounded in the question or the context',
                'resolved_question' => $decoded['resolved_question'] ?? null,
                'question' => $question,
                'names' => $this->contextNames($context),
                'model' => config('services.gemini.model'),
            ]);
        }

        return $validated;
    } catch (\Throwable $exception) {
        Log::warning('Reference resolution failed: '.$exception->getMessage());

        return null;
    }
}

/**
 * Whether a question is worth a resolution call.
 *
 * A question that names a concrete subject and points at nothing has nothing to
 * resolve, and asking the provider anyway would cost a round trip on the
 * majority of turns for no gain. This is a cheap deterministic gate, not a
 * second classifier: it only decides whether to call, never what the answer is.
 *
 * @param  array<string, mixed>  $context
 */
public function needsReferenceResolution(string $question, array $context): bool
{
    $normalized = mb_strtolower(trim($question));
    if ($normalized === '' || mb_strlen($normalized) > 255) {
        return false;
    }

    // Two shapes need resolving. A question that is nothing but a follow-up stem
    // has no subject of its own, so it can only mean whatever came before. And a
    // question carrying a referring word is pointing at something, not naming
    // it.
    $isBareStem = preg_match('/^(?:why|how many|where|who|explain|again)\b[\s?.!]*$/u', $normalized) === 1;

    $pointsAtSomethingElse = preg_match(
        '/\b(?:it|its|this|that|those|these|they|them|their|the same|the other|the previous|the first|the second|the third|again|also)\b/u',
        $normalized
    ) === 1;

    if (! $isBareStem && ! $pointsAtSomethingElse) {
        return false;
    }

    // A referring word only matters if there is something to resolve it against.
    // Without an anchor the rewrite has nothing to draw on, so asking would
    // spend a call and risk a rewrite the grounding check can only reject.
    return $this->hasResolvableAnchor($context);
}

/**
 * @param  array<string, mixed>  $context
 */
private function hasResolvableAnchor(array $context): bool
{
    foreach (['entity_refs', 'candidate_ids'] as $key) {
        $value = $context[$key] ?? null;
        if (is_array($value) && $value !== []) {
            return true;
        }
    }

    foreach (['recent_turns', 'relevant_turns'] as $key) {
        if (($context[$key] ?? null) !== [] && ($context[$key] ?? null) !== null) {
            return true;
        }
    }

    if (is_string($context['summary'] ?? null) && trim($context['summary']) !== '') {
        return true;
    }

    // A topic that names an item is itself an anchor: "why?" against it has
    // something to resolve to even when no ids were recorded.
    $topic = $context['topic'] ?? null;
    if (is_array($topic) && is_string($topic['item_name'] ?? null) && trim($topic['item_name']) !== '') {
        return true;
    }

    return false;
}

/**
 * Validate a resolution, then hold it to the grounding rule.
 *
 * Grounding is checked against the raw question and the structured context, not
 * against the model's own rewrite. Without this the parser's selector grounding
 * would run against a sentence the model produced, and an invented name would
 * pass simply by appearing in its own output.
 *
 * @param  array<string, mixed>  $decoded
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>|null
 */
private function validateReferenceResolution(array $decoded, string $question, array $context): ?array
{
    $expected = [
        'resolved_question',
        'is_follow_up',
        'ambiguity',
        'referenced_item_id',
        'topic_action',
    ];

    if (array_diff(array_keys($decoded), $expected) !== []
        || array_diff($expected, array_keys($decoded)) !== []) {
        return null;
    }

    $resolvedQuestion = $decoded['resolved_question'];
    $isFollowUp = $decoded['is_follow_up'];
    $ambiguity = $decoded['ambiguity'];
    $referencedItemId = $decoded['referenced_item_id'];
    $topicAction = $decoded['topic_action'];

    if (! is_string($resolvedQuestion)
        || trim($resolvedQuestion) === ''
        || mb_strlen(trim($resolvedQuestion)) > 1000
        || ! is_bool($isFollowUp)
        || ! (is_null($ambiguity) || in_array($ambiguity, self::AMBIGUITIES, true))
        || ! (is_null($referencedItemId) || (is_int($referencedItemId) && $referencedItemId > 0))
        || ! in_array($topicAction, self::TOPIC_ACTIONS, true)) {
        return null;
    }

    if (! $this->resolutionIsGrounded($resolvedQuestion, $question, $context)) {
        return null;
    }

    // An ordinal is only meaningful against a set the user was actually shown.
    $candidates = [];
    if ($ambiguity === 'ambiguous_candidate') {
        $candidates = $this->cleanIds($context['candidate_ids'] ?? []);
        if ($candidates === []) {
            return null;
        }
    }

    // A referenced item the user may no longer see resolves to nothing.
    if (is_int($referencedItemId) && ! in_array($referencedItemId, $this->groundedIds($context), true)) {
        $referencedItemId = null;
    }

    return [
        'resolved_question' => trim($resolvedQuestion),
        'is_follow_up' => $isFollowUp,
        'ambiguity' => $ambiguity,
        'candidates' => $candidates,
        'referenced_item_id' => $referencedItemId,
        'topic_action' => $topicAction,
    ];
}

/**
 * The grounding rule, applied to the rewrite.
 *
 * Every inventory id in the resolved question must appear in the source question
 * or in the ids the context offers. Every item name must appear in the source
 * question or in the names the context offers. Anything else is rejected whole.
 *
 * @param  array<string, mixed>  $context
 */
private function resolutionIsGrounded(string $resolvedQuestion, string $question, array $context): bool
{
    $allowedIds = $this->groundedIds($context);

    foreach ($this->numbersIn($resolvedQuestion) as $number) {
        if ($number > 0 && in_array($number, $this->numbersIn($question), true)) {
            continue;
        }
        if (! in_array($number, $allowedIds, true)) {
            return false;
        }
    }

    $allowedNames = $this->contextNames($context);

    // A name only has to be justified when the rewrite introduced one. Names
    // already present in the question are the user's own words.
    foreach ($this->namesIn($resolvedQuestion) as $name) {
        if ($this->nameAppearsInQuestion($name, $question)) {
            continue;
        }
        if (! $this->nameAppearsIn($name, $allowedNames)) {
            return false;
        }
    }

    return true;
}

/**
 * @param  array<string, mixed>  $context
 * @return array<int, int>
 */
private function groundedIds(array $context): array
{
    $ids = [];
    foreach (['entity_refs', 'candidate_ids'] as $key) {
        $ids = [...$ids, ...$this->cleanIds($context[$key] ?? [])];
    }

    foreach (['relevant_turns', 'recent_turns'] as $key) {
        foreach ((array) ($context[$key] ?? []) as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $ids = [...$ids, ...$this->cleanIds($turn['entity_refs'] ?? []), ...$this->cleanIds($turn['candidate_ids'] ?? [])];
        }
    }

    return $this->cleanIds($ids);
}

/**
 * Item names the context is willing to justify, gathered from its structured
 * data rather than from reply prose.
 *
 * @param  array<string, mixed>  $context
 * @return array<int, string>
 */
private function contextNames(array $context): array
{
    $names = [];

    foreach (['entity_names', 'candidate_names'] as $key) {
        foreach ((array) ($context[$key] ?? []) as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }
    }

    foreach (['topic'] as $key) {
        $topic = $context[$key] ?? null;
        if (is_array($topic) && is_string($topic['item_name'] ?? null)) {
            $names[] = trim($topic['item_name']);
        }
    }

    return array_values(array_unique(array_filter($names)));
}

/**
 * @param  array<int, string>  $names
 */
private function nameAppearsIn(string $name, array $names): bool
{
    foreach ($names as $candidate) {
        if (mb_strtolower(trim($candidate)) === mb_strtolower(trim($name))) {
            return true;
        }
    }

    return false;
}

private function nameAppearsInQuestion(string $name, string $question): bool
{
    return $this->selectorAppearsInQuestion($name, $question);
}

/**
 * @return array<int, int>
 */
private function numbersIn(string $text): array
{
    preg_match_all('/(?<!\d)(\d+)(?!\d)/u', $text, $matches);

    return array_values(array_unique(array_map('intval', $matches[1] ?? [])));
}

/**
 * @return array<int, string>
 */
private function namesIn(string $text): array
{
    // Capitalised runs are the only reliable signal available without a
    // dictionary. Lowercase function words are excluded so ordinary phrasing is
    // never mistaken for an entity the model introduced.
    preg_match_all('/\b[A-Z][A-Za-z0-9\'-]*(?:\s+[A-Z][A-Za-z0-9\'-]*)*\b/u', $text, $matches);

    $noise = [
        'I', 'A', 'An', 'The', 'Is', 'Are', 'Was', 'Were', 'Do', 'Does', 'Did',
        'What', 'Which', 'Who', 'Whose', 'Where', 'When', 'Why', 'How', 'And',
        'Or', 'It', 'This', 'That', 'These', 'Those', 'They', 'There', 'Then',
        'Of', 'In', 'On', 'At', 'To', 'For', 'From', 'By', 'With', 'Has', 'Have',
        'Had', 'Can', 'Could', 'Should', 'Would', 'Will', 'Please', 'List',
        'Show', 'Tell', 'Give', 'Check', 'Explain', 'Item', 'Items', 'Number',
        'Available', 'Left', 'Stock', 'Location', 'Assigned',
    ];

    return array_values(array_unique(array_filter(
        array_map('trim', $matches[0] ?? []),
        fn (string $name): bool => $name !== '' && ! in_array($name, $noise, true) && mb_strlen($name) > 1
    )));
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

    return array_values(array_unique($clean));
}

private function referenceResolutionPrompt(): string
{
    return 'Rewrite one user question so it stands on its own, and report whether it can be answered. '
        .'Return one JSON object with exactly these keys: resolved_question, is_follow_up, ambiguity, referenced_item_id, topic_action. '
        .'resolved_question must replace pronouns such as "it", "that one", "those", "the second one" and "the other projector" with the item they refer to, using only what the context supplies. Never invent an item, an id, a name, or a cause. If the question cannot be resolved, return it unchanged. '
        .'is_follow_up is true only when the question depends on an earlier turn. '
        .'ambiguity must be null when the question can be answered, no_entity when it needs an item but none can be identified, ambiguous_candidate when more than one supplied candidate could be meant, or no_topic when it asks something but no earlier topic applies. '
        .'referenced_item_id must be the exact inventory id the question resolves to, or null when none. '
        .'topic_action must be continue_topic, new_topic, or unclear. '
        .'Never return SQL, permissions, database instructions, answers, or additional keys.';
}

/**
 * The reference resolution schema, deliberately separate from the intent schema.
 *
 * They are validated by different rules and consumed by different stages, and
 * the intent schema's both-ways key diff must stay exactly as it is.
 */
private function referenceResponseSchema(): array
{
    return [
        'type' => 'OBJECT',
        'properties' => [
            'resolved_question' => ['type' => 'STRING', 'maxLength' => 1000],
            'is_follow_up' => ['type' => 'BOOLEAN'],
            'ambiguity' => [
                'type' => 'STRING',
                'nullable' => true,
                'enum' => self::AMBIGUITIES,
            ],
            'referenced_item_id' => ['type' => 'INTEGER', 'nullable' => true, 'minimum' => 1],
            'topic_action' => ['type' => 'STRING', 'enum' => self::TOPIC_ACTIONS],
        ],
        'required' => [
            'resolved_question',
            'is_follow_up',
            'ambiguity',
            'referenced_item_id',
            'topic_action',
        ],
        'additionalProperties' => false,
    ];
}

/**
 * Route a question to a capability, plus an execution plan.
 *
 * The plan is additive: every pre-existing key is exactly what it was, and the
 * `plan` key simply states whether this turn is one request or several. A
 * single-intent turn therefore still produces one ToolResult through one
 * authorisation, and nothing downstream has to change to keep working.
 *
 * @return array<string, mixed>
 */
public function route(string $question, array $activeTopic = [], array $turns = []): array
    {
        $route = $this->resolveRoute($question, $activeTopic, $turns);
        $route['plan'] = $this->buildPlan($route, $question, $activeTopic, $turns);

        return $route;
    }

    /**
     * Build the execution plan for a routed question.
     *
     * A compound turn is expanded into one fully routed sub-request per
     * decomposed part. Each is resolved through the same capability mapping and
     * selector rules as a top-level parse, so a sub-request cannot invent a
     * capability, a selector, or an intent the parser would not have allowed
     * on its own.
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    private function buildPlan(array $route, string $question, array $activeTopic, array $turns): array
    {
        if (($route['plan_requests'] ?? null) === null) {
            return [
                'kind' => 'single',
                'requests' => [$this->asPlanRequest($route)],
            ];
        }

        $requests = [];
        foreach ($route['plan_requests'] as $subRequest) {
            $resolved = $this->routeSubRequest($subRequest, $question, $activeTopic);
            if ($resolved !== null) {
                $requests[] = $this->asPlanRequest($resolved);
            }
        }

        // If decomposition produced nothing usable, fall back to the single
        // top-level route rather than answering a compound turn with no data.
        if (count($requests) < 2) {
            return [
                'kind' => 'single',
                'requests' => [$this->asPlanRequest($route)],
            ];
        }

        return [
            'kind' => 'compound',
            'requests' => $requests,
        ];
    }

    /**
     * Project a routed question onto the plan-request shape.
     *
     * @param  array<string, mixed>  $route
     * @return array<string, mixed>
     */
    private function asPlanRequest(array $route): array
    {
        unset($route['plan'], $route['plan_requests']);

        return $route;
    }

    /**
     * Route one decomposed sub-request with the same rules as a whole question.
     *
     * @param  array<string, mixed>  $subRequest
     * @return array<string, mixed>|null
     */
    private function routeSubRequest(array $subRequest, string $question, array $activeTopic): ?array
    {
        $intent = $subRequest['intent'];
        $capability = $this->capabilityPolicy->capabilityForIntent($intent);
        if (! is_string($capability) || ! $this->capabilityPolicy->isKnownCapability($capability)) {
            return null;
        }

        // forecast and recommendation are delegated to the Forecast page and
        // must never fan out into the factual tools.
        if ($capability === \App\Services\AiCapabilityPolicy::VIEW_DEMAND_FORECAST
            || $capability === \App\Services\AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES) {
            return null;
        }

        $filters = $subRequest['filters'];

        return [
            'original_question' => $question,
            'normalized_question' => mb_strtolower(trim($question)),
            'canonical_question_key' => hash('sha256', mb_strtolower(trim($question))),
            'classification_confidence' => 'ai_validated',
            'intent' => 'factual',
            'capability' => $capability,
            'item_name' => $subRequest['item_name'],
            'inventory_id' => $subRequest['inventory_id'],
            'serial_number' => $subRequest['serial_number'],
            'location_query' => $subRequest['location_query'],
            'category_id' => $filters['category_id'],
            'unit' => $filters['unit'],
            'query' => $subRequest['query'],
            'filters' => $filters,
            'response_type' => $subRequest['response_type'],
            'needs_external_explanation' => false,
            'needs_clarification' => false,
            'vague' => false,
            'follow_up' => false,
            'starts_new_topic' => false,
            'topic_action' => 'new_topic',
            'request_type' => $intent,
            'entity_candidate' => $subRequest['item_name'] ?? $subRequest['location_query'] ?? null,
            'entity_candidate_status' => ($subRequest['item_name'] ?? $subRequest['location_query'] ?? null) === null ? 'missing' : 'unresolved',
            'ambiguous' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRoute(string $question, array $activeTopic, array $turns): array
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

        $parsed = $this->parse($question, $activeTopic, $turns);

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
            // Carried only so buildPlan() can expand a compound turn; stripped
            // from the plan requests before they leave this class.
            'plan_requests' => $parsed['is_compound'] ? $parsed['sub_requests'] : null,
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

    /**
     * Reduce stored context to the handful of scalar fields the classifier is
     * allowed to see, plus a short window of recent exchanges.
     *
     * Turns are assistant-side only and every field is length-capped, so a long
     * reply or a client-supplied string cannot bloat or steer the prompt.
     *
     * @param  array<int, array<string, mixed>>  $turns
     * @return array<string, mixed>
     */
    private function safeTopicForParsing(array $topic, array $turns = []): array
    {
        $safe = [];
        foreach (['prior_intent', 'capability', 'response_type', 'reference_type', 'item_name'] as $key) {
            $value = $topic[$key] ?? null;
            if (is_string($value) && mb_strlen($value) <= 255) {
                $safe[$key] = $value;
            }
        }

        $recentTurns = array_values(array_filter(array_map(
            function (array $turn): array {
                $question = is_string($turn['question'] ?? null) ? mb_substr($turn['question'], 0, 255) : null;
                $reply = is_string($turn['reply'] ?? null) ? mb_substr($turn['reply'], 0, 255) : null;

                return array_filter([
                    'question' => $question,
                    'reply' => $reply,
                    'intent' => is_string($turn['intent'] ?? null) ? mb_substr($turn['intent'], 0, 64) : null,
                    'capability' => is_string($turn['capability'] ?? null) ? mb_substr($turn['capability'], 0, 64) : null,
                ], fn ($value): bool => $value !== null);
            },
            array_slice(array_values($turns), -self::MAX_PARSE_TURNS),
        ), fn (array $turn): bool => ($turn['question'] ?? null) !== null && ($turn['reply'] ?? null) !== null));

        if ($recentTurns !== []) {
            $safe['recent_turns'] = $recentTurns;
        }

        return $safe;
    }

    private function validate(array $parsed, string $question): ?array
    {
        $expectedKeys = ['intent', 'topic_action', 'item_name', 'inventory_id', 'serial_number', 'location_query', 'filters', 'query', 'response_type', 'explanation', 'explanation_topic', 'is_compound', 'sub_requests'];
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
        $isCompound = $parsed['is_compound'];
        $subRequests = $parsed['sub_requests'];

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
            || ! is_bool($explanation)
            || ! is_bool($isCompound)
            || ! is_array($subRequests)
            || (($isCompound ? count($subRequests) < 2 : $subRequests !== []) === true)) {
            return null;
        }

        foreach ($subRequests as $subRequest) {
            if ($this->validateSubRequest($subRequest, $question, $isCompound) === null) {
                return null;
            }
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
            'is_compound' => $isCompound,
            'sub_requests' => array_values(array_map(
                fn (array $subRequest): array => $this->validateSubRequest($subRequest, $question, $isCompound),
                $subRequests,
            )),
        ];
    }

    /**
     * Validate one decomposed sub-request.
     *
     * A sub-request is held to exactly the same standard as a top-level parse:
     * a known intent, a selector that really appears in the question, and no
     * extra keys. Anything the provider invents here is rejected wholesale
     * rather than silently repaired, because a sub-request that slipped through
     * would be authorised and queried on its own terms.
     *
     * Compound turns must never decompose into a forecast sub-request: demand
     * forecasting is delegated to the handoff, which is single-intent.
     *
     * @param  array<string, mixed>  $subRequest
     * @return array<string, mixed>|null
     */
    private function validateSubRequest(array $subRequest, string $question, bool $isCompound): ?array
    {
        $expectedKeys = ['intent', 'item_name', 'inventory_id', 'serial_number', 'location_query', 'filters', 'query', 'response_type'];
        if (array_diff(array_keys($subRequest), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($subRequest)) !== []) {
            return null;
        }

        $intent = $subRequest['intent'];
        $itemName = $subRequest['item_name'];
        $inventoryId = $subRequest['inventory_id'];
        $serialNumber = $subRequest['serial_number'];
        $locationQuery = $subRequest['location_query'];
        $filters = $subRequest['filters'];
        $query = $subRequest['query'];
        $responseType = $subRequest['response_type'];

        if (! is_string($intent) || ! in_array($intent, self::INTENTS, true)
            || in_array($intent, ['forecast', 'explanation', 'continue', 'inherit', 'unsupported', 'unclear'], true)
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
            || $responseType === 'same_as_topic') {
            return null;
        }

        if ($query !== null && $query === 'pending_count' && ! in_array($intent, ['pending_requests', 'own_requests'], true)) {
            return null;
        }
        if ($query !== null && in_array($query, ['inventory_identifiers', 'product_name'], true) && $intent !== 'stock') {
            return null;
        }

        if ($this->capabilityPolicy->capabilityForIntent($intent) === null) {
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
        ];
    }

    private function systemPrompt(): string
    {
        return 'Classify the user question only. Do not answer it, access data, or propose actions. Return one JSON object with exactly these keys: intent, topic_action, item_name, inventory_id, serial_number, location_query, filters, query, response_type, explanation, explanation_topic, is_compound, sub_requests. '
            .'intent must be availability, stock, pending_requests, low_stock, forecast, executive_reports, system_summary, inventory_valuation, own_assignments, own_requests, item_status, assignments, maintenance, disposal, ready_to_dispose, location, purchase_history, explanation, continue, inherit, unsupported, or unclear. Procurement and restock questions are not handled here; classify them as unsupported. '
            .'topic_action must be continue_topic, new_topic, or unclear. Use continue when the message only refers to the active topic and inherit when a new named item continues the active topic action. Use an active topic only for follow-ups or action inheritance; otherwise classify the new request independently. '
            .'Map explanation questions to intent explanation and set explanation_topic to the supported fact topic; otherwise explanation_topic must be null. Use only item names, inventory IDs, serial numbers, location names, units, and category IDs explicitly stated in the question; use null when absent. '
            .'filters must contain exactly category_id and unit. query must be null, pending_count, inventory_identifiers, or product_name. Use pending_count only when explicitly counting pending requests; use inventory_identifiers only when asking for an inventory number, ID, or asset tag; use product_name only when asking for the product name. For ordinary availability or stock questions, query must be null, including count questions. '
            .'response_type must be detail, count, list, explanation, or same_as_topic. explanation must be a boolean and true only for intent explanation. '
            .'is_compound must be true only when the question asks for two or more separate inventory facts that can be answered independently. When it is false, sub_requests must be an empty array. When it is true, sub_requests must hold one entry per separate question in the order asked, each with exactly the keys intent, item_name, inventory_id, serial_number, location_query, filters, query, response_type; the top-level fields must then describe the first sub-request and topic_action must be new_topic. A sub-request intent must never be forecast, explanation, continue, inherit, unsupported or unclear, and its response_type must never be same_as_topic. Never return SQL, permissions, database instructions, answers, or additional keys.';
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
            'is_compound' => ['type' => 'BOOLEAN'],
            'sub_requests' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'intent' => ['type' => 'STRING', 'enum' => array_values(array_diff(self::INTENTS, ['forecast', 'explanation', 'continue', 'inherit', 'unsupported', 'unclear']))],
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
                        'response_type' => ['type' => 'STRING', 'enum' => array_values(array_diff(self::RESPONSE_TYPES, ['same_as_topic']))],
                    ],
                    'required' => ['intent', 'item_name', 'inventory_id', 'serial_number', 'location_query', 'filters', 'query', 'response_type'],
                    'additionalProperties' => false,
                ],
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
