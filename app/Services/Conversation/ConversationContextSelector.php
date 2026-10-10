<?php

namespace App\Services\Conversation;

use App\Models\Inventory;
use App\Models\User;

/**
 * Assembles the payload the reference resolver is given.
 *
 * Five channels feed it. Three are text and two are data, and the difference
 * matters: text channels answer action and topic references, while the data
 * channels are the only thing that can answer an ordinal reference, because
 * counting items in prose is fragile once the summary compresses a list.
 *
 * The payload is token-bounded. When it overflows, the oldest relevant turns are
 * dropped first and the summary is compressed next. The entity registry and the
 * candidate set are never dropped, because they are what make a follow-up
 * resolvable at all.
 */
class ConversationContextSelector
{
    /**
     * How many recent exchanges are included verbatim.
     */
    public const RECENT_TURNS = 10;

    /**
     * How many older turns sharing an entity with the question are included.
     */
    public const RELEVANT_TURNS = 5;

    /**
     * Rough character budget for the assembled payload.
     *
     * A character budget rather than a real token count: it is predictable, it
     * needs no tokenizer dependency, and the parser truncates each field again
     * before it reaches the provider.
     */
    private const CHARACTER_BUDGET = 6000;

    private const MAX_FIELD_LENGTH = 255;

    /**
     * Build the payload for one question.
     *
     * @return array<string, mixed>
     */
    public function select(
        User $user,
        string $sessionId,
        string $question,
        ?array $topic,
        ?array $lastResolved,
    ): array {
        $recentTurns = $this->recentTurns($user, $sessionId);
        $entityRefs = $this->entityRegistry($user, $sessionId);
        $candidateIds = $this->candidateIds($user, $sessionId, $lastResolved);
        $summary = $this->summary($user, $sessionId);
        $relevantTurns = $this->relevantTurns($user, $sessionId, $question, $recentTurns);

        $payload = [
            'recent_turns' => $recentTurns,
            'summary' => $summary,
            'relevant_turns' => $relevantTurns,
            'entity_refs' => $entityRefs,
            'entity_names' => $this->namesFor($entityRefs),
            'candidate_ids' => $candidateIds,
            'candidate_names' => $this->namesFor($candidateIds),
            'topic' => $this->safeTopic($topic),
        ];

        return $this->enforceBudget($payload);
    }

    /**
     * Drop or compress until the payload fits.
     *
     * Order matters: text first, structured data never. A payload that still
     * overflows once every droppable turn is gone is cut at the summary, which
     * is the only field allowed to lose meaning.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enforceBudget(array $payload): array
    {
        while ($this->size($payload) > self::CHARACTER_BUDGET && $payload['relevant_turns'] !== []) {
            array_pop($payload['relevant_turns']);
        }

        if ($this->size($payload) > self::CHARACTER_BUDGET) {
            $summary = is_string($payload['summary'] ?? null) ? $payload['summary'] : '';
            $payload['summary'] = mb_substr($summary, 0, 400);
        }

        if ($this->size($payload) > self::CHARACTER_BUDGET) {
            $payload['recent_turns'] = array_slice($payload['recent_turns'], -3);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function size(array $payload): int
    {
        return mb_strlen(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTurns(User $user, string $sessionId): array
    {
        return array_map(
            fn (array $turn): array => [
                'question' => $this->clip($turn['question'] ?? null),
                'reply' => $this->clip($turn['reply'] ?? null),
                'intent' => $this->clip($turn['intent'] ?? null, 64),
                'capability' => $this->clip($turn['capability'] ?? null, 64),
            ],
            $this->turnLog->recentTurns($user, $sessionId, self::RECENT_TURNS),
        );
    }

    /**
     * Older turns that share an entity with the question.
     *
     * Matched by shared entity, never by keyword overlap on the question text.
     * "it" and "that one" have no words in common with what they refer to, so a
     * keyword match would return nothing exactly when it is needed.
     *
     * @param  array<int, array<string, mixed>>  $recentTurns
     * @return array<int, array<string, mixed>>
     */
    private function relevantTurns(
        User $user,
        string $sessionId,
        string $question,
        array $recentTurns,
    ): array {
        $questionIds = $this->numbersIn($question);
        $questionWords = $this->wordsIn($question);

        if ($questionIds === [] && count($questionWords) < 2) {
            return [];
        }

        $older = $this->turnLog->turnsOutsideWindow($user, $sessionId, self::RECENT_TURNS);

        $scored = [];
        foreach ($older as $turn) {
            $score = $this->entityOverlap($turn, $questionIds)
                + $this->nameOverlap($turn, $questionWords);

            if ($score > 0) {
                $scored[] = ['score' => $score, 'turn' => $turn];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $entry): array => [
            'question' => $this->clip($entry['turn']['question'] ?? null),
            'reply' => $this->clip($entry['turn']['reply'] ?? null),
            'entity_refs' => $entry['turn']['entity_refs'] ?? [],
            'item_name' => $this->clip($entry['turn']['item_name'] ?? null),
        ], array_slice($scored, 0, self::RELEVANT_TURNS));
    }

    /**
     * @param  array<string, mixed>  $turn
     * @param  array<int, int>  $questionIds
     */
    private function entityOverlap(array $turn, array $questionIds): int
    {
        if ($questionIds === []) {
            return 0;
        }

        return count(array_intersect($questionIds, $this->numbersIn($turn['question'] ?? '')))
            + count(array_intersect($questionIds, $this->cleanIds($turn['entity_refs'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $turn
     * @param  array<int, string>  $questionWords
     */
    private function nameOverlap(array $turn, array $questionWords): int
    {
        $name = mb_strtolower(trim((string) ($turn['item_name'] ?? '')));
        if ($name === '') {
            return 0;
        }

        $nameWords = $this->wordsIn($name);

        return count(array_intersect($nameWords, $questionWords));
    }

    /**
     * @return array<int, string>
     */
    private function wordsIn(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;

        return array_values(array_filter(explode(' ', preg_replace('/\s+/u', ' ', $normalized) ?? $normalized)));
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
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function namesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Inventory::query()
            ->whereIn('item_id', $ids)
            ->pluck('item_name')
            ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): string => mb_substr(trim($name), 0, self::MAX_FIELD_LENGTH))
            ->values()
            ->all();
    }

    /**
     * The topic snapshot, reduced to the scalars the resolver may see.
     *
     * @param  array<string, mixed>|null  $topic
     * @return array<string, mixed>
     */
    private function safeTopic(?array $topic): array
    {
        if ($topic === null) {
            return [];
        }

        $safe = [];
        foreach (['prior_intent', 'capability', 'response_type', 'reference_type', 'item_name'] as $key) {
            $value = $topic[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $safe[$key] = mb_substr($value, 0, self::MAX_FIELD_LENGTH);
            }
        }

        return $safe;
    }

    private function clip(mixed $value, int $max = self::MAX_FIELD_LENGTH): ?string
    {
        return is_string($value) ? mb_substr($value, 0, $max) : null;
    }

    public function __construct(private ConversationTurnLog $turnLog) {}

    /**
     * @param  array<string, mixed>|null  $lastResolved
     * @return array<int, int>
     */
    private function entityRegistry(User $user, string $sessionId): array
    {
        return $this->turnLog->entityRegistry($user, $sessionId);
    }

    /**
     * @param  array<string, mixed>|null  $lastResolved
     * @return array<int, int>
     */
    private function candidateIds(User $user, string $sessionId, ?array $lastResolved): array
    {
        $ids = $this->turnLog->lastCandidateIds($user, $sessionId);
        if ($ids === [] && is_array($lastResolved)) {
            $ids = $this->cleanIds($lastResolved['candidate_ids'] ?? []);
        }

        return $ids;
    }

    private function summary(User $user, string $sessionId): string
    {
        return $this->turnLog->summary($user, $sessionId);
    }
}