<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\User;
use App\Services\Tools\Concerns\InteractsWithInventory;
use App\Services\Tools\ToolRouter;
use Illuminate\Support\Str;

/**
 * Owns conversation continuity and reply wording for the assistant.
 *
 * The inventory reads this class used to perform now live behind ToolRouter and
 * the six tools; `answer()` is the thin entry point that hands off to it. What
 * remains here is the part that is about the *conversation* rather than the
 * data: what the assistant remembers between turns, when it must ask which
 * item is meant, and how a ToolResult is rendered when no provider generated
 * anything.
 */
class InventoryAnswerService
{
    use InteractsWithInventory;

    public function __construct(
        protected AiCapabilityPolicy $policy,
        protected ToolRouter $toolRouter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $routedQuestion
     * @return array<string, mixed>
     */
    public function answer(User $user, array $routedQuestion): array
    {
        return $this->toolRouter->dispatch($user, $routedQuestion);
    }

    public function conversationContext(User $user, array $routedQuestion, array $result): ?array
    {
        $capability = $routedQuestion['capability'] ?? null;
        if (($result['status'] ?? null) !== 'success' || ! in_array($capability, [
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
        ], true) || ! $this->policy->allows($user, $capability)) {
            return null;
        }

        $identity = array_intersect_key($routedQuestion, array_flip([
            'inventory_id',
            'category_id',
            'unit',
            'serial_number',
        ]));
        $itemName = $this->resolvedItemName($routedQuestion, $result);
        $intent = $routedQuestion['intent'] ?? null;
        $responseType = $routedQuestion['response_type'] ?? null;
        if ($itemName === null
            && $capability === AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY
            && array_key_exists('available_count', $result['answer'] ?? [])
            && in_array($intent, ['factual', 'explanation'], true)
            && in_array($responseType, ['count', 'list'], true)) {
            return [
                'version' => 1,
                'intent' => $intent,
                'capability' => $capability,
                'response_type' => $responseType,
                'reference_type' => 'inventory_search',
                'reference_id' => null,
                'item_name' => null,
                'filters' => ['awaiting_item_filter' => true],
            ];
        }
        if ($itemName === null) {
            return null;
        }

        if (! in_array($intent, ['factual', 'explanation'], true)
            || ! in_array($responseType, ['detail', 'count', 'list', 'explanation'], true)) {
            return null;
        }

        $referenceType = 'inventory';
        $referenceId = null;
        $resolvedName = null;

        if (isset($identity['inventory_id'])) {
            $item = Inventory::query()->whereKey((int) $identity['inventory_id'])->first();
            if (! $item
                || (isset($identity['category_id']) && (int) $item->category_id !== (int) $identity['category_id'])
                || (isset($identity['unit']) && strcasecmp((string) $item->unit, (string) $identity['unit']) !== 0)
                || (isset($identity['serial_number']) && $item->serial_number !== $identity['serial_number'])) {
                return null;
            }
            $referenceId = (int) $item->item_id;
            $resolvedName = $item->item_name;
        } else {
            $records = $this->matchingInventoryRecords($itemName, $identity);
            if ($records->isEmpty()) {
                return null;
            }

            if ($records->count() === 1) {
                $referenceId = (int) $records->first()->item_id;
                $resolvedName = $records->first()->item_name;
            } else {
                $groups = $this->groupInventoryRecords($records);
                if ($groups->count() !== 1 || $this->hasAmbiguousUnit($records)) {
                    return null;
                }

                $referenceType = 'inventory_group';
                $resolvedName = $records->first()->item_name;
            }
        }

        return [
            'version' => 1,
            'intent' => $intent,
            'capability' => $capability,
            'response_type' => $responseType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'item_name' => $resolvedName,
            'filters' => [],
        ];
    }

    public function resolveConversationContext(User $user, array $context): ?array
    {
        $capability = $context['capability'] ?? null;
        $referenceId = $context['reference_id'] ?? null;
        $expectedKeys = ['version', 'intent', 'capability', 'response_type', 'reference_type', 'reference_id', 'item_name', 'filters'];
        if (array_diff(array_keys($context), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($context)) !== []
            || ($context['version'] ?? null) !== 1
            || ! in_array($context['intent'] ?? null, ['factual', 'explanation'], true)
            || ! in_array($context['response_type'] ?? null, ['detail', 'count', 'list', 'explanation'], true)
            || ! in_array($capability, [
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
            || ! in_array($context['reference_type'] ?? null, ['inventory', 'inventory_group', 'inventory_search'], true)
            || (($context['reference_type'] ?? null) === 'inventory' && (! is_int($referenceId) || $referenceId < 1))
            || (($context['reference_type'] ?? null) !== 'inventory' && $referenceId !== null)
            || (($context['reference_type'] ?? null) !== 'inventory_search'
                && (! is_string($context['item_name'] ?? null)
                    || trim($context['item_name']) === ''
                    || mb_strlen(trim($context['item_name'])) > 255))
            || (($context['reference_type'] ?? null) === 'inventory_search'
                && (($context['item_name'] ?? null) !== null
                    || $capability !== AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY
                    || ! in_array($context['response_type'], ['count', 'list'], true)
                    || ($context['filters'] ?? null) !== ['awaiting_item_filter' => true]))
            || (($context['reference_type'] ?? null) !== 'inventory_search' && ($context['filters'] ?? null) !== [])
            || ! $this->policy->allows($user, $capability)) {
            return null;
        }

        if ($context['reference_type'] === 'inventory_search') {
            return [
                'prior_intent' => $context['intent'],
                'capability' => $capability,
                'response_type' => $context['response_type'],
                'reference_type' => 'inventory_search',
                'awaiting_item_filter' => true,
            ];
        }

        if ($context['reference_type'] === 'inventory_group') {
            $records = $this->matchingInventoryRecords($context['item_name']);
            $groups = $this->groupInventoryRecords($records);
            $canonicalName = $groups->count() === 1 ? trim((string) $groups->first()['item_name']) : '';
            if ($records->isEmpty()
                || $groups->count() !== 1
                || $this->hasAmbiguousUnit($records)
                || mb_strtolower($canonicalName) !== mb_strtolower(trim($context['item_name']))) {
                return null;
            }

            return [
                'prior_intent' => $context['intent'],
                'capability' => $capability,
                'response_type' => $context['response_type'],
                'item_name' => $canonicalName,
                'reference_type' => 'inventory_group',
                'group_context' => true,
            ];
        }

        $itemQuery = Inventory::query();
        if (! in_array($capability, [AiCapabilityPolicy::VIEW_ITEM_STATUS, AiCapabilityPolicy::VIEW_DISPOSAL], true)) {
            $itemQuery->where('status', '!=', 'disposed');
        }
        $item = $itemQuery->find($referenceId);
        if (! $item || ! $item->category_id || ! is_string($item->item_name) || trim($item->item_name) === '') {
            return null;
        }

        return [
            'prior_intent' => $context['intent'],
            'capability' => $capability,
            'response_type' => $context['response_type'],
            'inventory_id' => (int) $item->item_id,
            'category_id' => (int) $item->category_id,
            'unit' => $item->unit,
            'serial_number' => $item->serial_number,
            'item_name' => $item->item_name,
            'reference_type' => 'inventory',
            'reference_id' => (int) $item->item_id,
        ];
    }

    public function clarificationContext(User $user, array $routedQuestion, array $result): ?array
    {
        $capability = $routedQuestion['capability'] ?? null;
        $itemName = $routedQuestion['item_name'] ?? null;
        $intent = $routedQuestion['intent'] ?? null;
        $responseType = $routedQuestion['response_type'] ?? null;
        $query = $routedQuestion['query'] ?? null;
        if (
            ($result['status'] ?? null) !== 'clarification'
            || ! in_array($intent, ['factual', 'explanation'], true)
            || ! in_array($responseType, ['detail', 'count', 'list', 'explanation'], true)
            || ($query !== null && ! in_array($query, ['pending_count', 'inventory_identifiers', 'product_name'], true))
            || ! in_array($capability, [
                AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                AiCapabilityPolicy::VIEW_LOW_STOCK,
                AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
            ], true)
            || ! $this->policy->allows($user, $capability)
        ) {
            return null;
        }

        if ($itemName === null && ($routedQuestion['needs_clarification'] ?? false) === true) {
            return [
                'intent' => $intent,
                'capability' => $capability,
                'item_name' => null,
                'response_type' => $responseType,
                'query' => $query,
                'requires_item_name' => true,
            ];
        }

        if (! is_string($itemName) || trim($itemName) === '' || mb_strlen(trim($itemName)) > 255) {
            return null;
        }

        $records = $this->matchingInventoryRecords($itemName);
        if ($records->count() < 2 || $records->count() > 20) {
            return null;
        }

        return [
            'intent' => $intent,
            'capability' => $capability,
            'item_name' => trim($itemName),
            'response_type' => $responseType,
            'query' => $query,
            'candidate_ids' => $records->pluck('item_id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    public function canResolveClarification(User $user, array $pending): bool
    {
        return is_string($pending['capability'] ?? null)
            && $this->policy->allows($user, $pending['capability']);
    }

    public function refreshClarificationContext(User $user, array $pending): ?array
    {
        if (! $this->canResolveClarification($user, $pending)) {
            return null;
        }

        $requiresItemName = ($pending['requires_item_name'] ?? false) === true;
        $expectedKeys = $requiresItemName
            ? ['intent', 'capability', 'item_name', 'response_type', 'query', 'requires_item_name']
            : ['intent', 'capability', 'item_name', 'response_type', 'query', 'candidate_ids'];
        if (array_diff(array_keys($pending), $expectedKeys) !== []
            || array_diff($expectedKeys, array_keys($pending)) !== []) {
            return null;
        }

        if (! $requiresItemName) {
            $candidateIds = $pending['candidate_ids'];
            if (! is_array($candidateIds)
                || ! array_is_list($candidateIds)
                || count($candidateIds) < 2
                || count($candidateIds) > 20) {
                return null;
            }
            foreach ($candidateIds as $candidateId) {
                if (! is_int($candidateId) || $candidateId < 1) {
                    return null;
                }
            }
            if (count(array_unique($candidateIds, SORT_REGULAR)) !== count($candidateIds)) {
                return null;
            }
        } elseif ($pending['requires_item_name'] !== true || $pending['item_name'] !== null) {
            return null;
        }

        return $this->clarificationContext($user, [
            'intent' => $pending['intent'] ?? null,
            'capability' => $pending['capability'] ?? null,
            'item_name' => $pending['item_name'] ?? null,
            'response_type' => $pending['response_type'] ?? null,
            'query' => $pending['query'] ?? null,
            'needs_clarification' => true,
        ], ['status' => 'clarification']);
    }

    public function resolveClarification(User $user, array $pending, string $selection): ?array
    {
        if (! $this->canResolveClarification($user, $pending)) {
            return null;
        }

        if (($pending['requires_item_name'] ?? false) === true) {
            $expectedKeys = ['intent', 'capability', 'item_name', 'response_type', 'query', 'requires_item_name'];
            if (array_diff(array_keys($pending), $expectedKeys) !== []
                || array_diff($expectedKeys, array_keys($pending)) !== []
                || $pending['item_name'] !== null
                || ! in_array($pending['intent'], ['factual', 'explanation'], true)
                || ! in_array($pending['response_type'], ['detail', 'count', 'list', 'explanation'], true)
                || ($pending['query'] !== null && ! in_array($pending['query'], ['pending_count', 'inventory_identifiers', 'product_name'], true))
                || ! in_array($pending['capability'], [
                    AiCapabilityPolicy::VIEW_INVENTORY_STOCK,
                    AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY,
                    AiCapabilityPolicy::VIEW_LOW_STOCK,
                    AiCapabilityPolicy::VIEW_INVENTORY_LOCATION,
                ], true)) {
                return null;
            }

            $itemName = trim($selection);
            if ($itemName === '' || mb_strlen($itemName) > 255
                || $this->matchingInventoryRecords($itemName)->isEmpty()) {
                return null;
            }

            return [
                'intent' => $pending['intent'],
                'capability' => $pending['capability'],
                'item_name' => $itemName,
                'needs_external_explanation' => $pending['intent'] === 'explanation',
                'query' => $pending['query'],
                'vague' => false,
                'response_type' => $pending['response_type'],
                'needs_clarification' => false,
                'clarification_resolved' => true,
            ];
        }

        if (! is_array($pending['candidate_ids'] ?? null)
            || count($pending['candidate_ids']) < 2
            || count($pending['candidate_ids']) > 20
            || ! is_string($pending['item_name'] ?? null)) {
            return null;
        }
        if (! in_array($pending['intent'] ?? null, ['factual', 'explanation'], true)
            || ! in_array($pending['response_type'] ?? null, ['detail', 'count', 'list', 'explanation'], true)
            || (($pending['query'] ?? null) !== null && ! in_array($pending['query'], ['pending_count', 'inventory_identifiers', 'product_name'], true))
            || trim($pending['item_name']) === ''
            || mb_strlen(trim($pending['item_name'])) > 255) {
            return null;
        }

        $candidateIds = collect($pending['candidate_ids'])
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->unique()
            ->values();
        if ($candidateIds->count() !== count($pending['candidate_ids'])) {
            return null;
        }

        $items = Inventory::query()->with('category')
            ->whereIn('item_id', $candidateIds)
            ->where('status', '!=', 'disposed')
            ->get()
            ->keyBy('item_id');
        if ($items->count() !== $candidateIds->count()) {
            return null;
        }

        $selectionText = $this->normalizeClarificationSelection($selection);
        $selectedId = null;
        if (preg_match('/^(?:the )?(first|second|third)(?: one| item| match)?$/u', $selectionText, $matches) === 1) {
            $candidateIndex = ['first' => 0, 'second' => 1, 'third' => 2][$matches[1]];
            $selectedId = $candidateIds->get($candidateIndex);
        } elseif (preg_match('/\b(?:inventory\s*)?(?:id|number)\s+(\d+)\b/', $selectionText, $matches) === 1) {
            $selectedId = (int) $matches[1];
        } elseif (preg_match('/^\d+$/', $selectionText) === 1) {
            $selectedId = (int) $selectionText;
        }

        $matches = collect();
        if ($selectedId !== null) {
            $matches = $items->filter(fn (Inventory $item): bool => (int) $item->item_id === $selectedId);
        } else {
            $serialSelection = preg_replace('/^(?:serial(?: number)?|sn)\s+/u', '', $selectionText) ?? $selectionText;
            $matches = $items->filter(function (Inventory $item) use ($selectionText, $serialSelection): bool {
                if ($item->serial_number) {
                    $serial = $this->normalizeClarificationSelection($item->serial_number);
                    if ($selectionText === $serial || $serialSelection === $serial) {
                        return true;
                    }
                }

                if ($item->inventory_item_no) {
                    $assetTag = $this->normalizeClarificationSelection($item->inventory_item_no);
                    $tagSelection = preg_replace('/^(?:asset )?tag\s+/u', '', $selectionText) ?? $selectionText;
                    if ($selectionText === $assetTag || $tagSelection === $assetTag) {
                        return true;
                    }
                }

                $category = $this->normalizeClarificationSelection($item->category?->category_name ?? '');
                $categorySelection = preg_replace('/\s+category$/u', '', $selectionText) ?? $selectionText;

                return $category !== '' && $categorySelection === $category;
            });
        }

        if ($matches->count() !== 1) {
            return null;
        }

        $item = $matches->first();

        return [
            'intent' => $pending['intent'],
            'capability' => $pending['capability'],
            'item_name' => $item->item_name,
            'needs_external_explanation' => $pending['intent'] === 'explanation',
            'query' => $pending['query'] ?? null,
            'vague' => false,
            'response_type' => $pending['response_type'],
            'needs_clarification' => false,
            'inventory_id' => (int) $item->item_id,
            'category_id' => (int) $item->category_id,
            'unit' => $item->unit,
            'serial_number' => $item->serial_number,
            'clarification_resolved' => true,
        ];
    }

    public function clarificationReply(User $user, array $pending): string
    {
        if (! $this->canResolveClarification($user, $pending)) {
            return 'Which item would you like to check?';
        }

        $candidateIds = $pending['candidate_ids'] ?? [];
        if (! is_array($candidateIds) || $candidateIds === []) {
            return 'Which item would you like to check?';
        }

        $items = Inventory::query()->with('category')
            ->whereIn('item_id', $candidateIds)
            ->where('status', '!=', 'disposed')
            ->orderBy('item_id')
            ->get();
        $candidates = $this->formatClarificationCandidates($items);
        if ($candidates === []) {
            return 'Which item would you like to check?';
        }

        return 'I found multiple matches: ' . implode(', ', $candidates)
            . '. Which one do you mean? Please specify the category, serial number, or inventory ID.';
    }

    public function localReply(array $result, ?User $user = null): string
    {
        if (($result['status'] ?? null) === 'clarification') {
            $answer = $result['answer'] ?? [];
            if (! empty($answer['clarification_question'])) {
                return $answer['clarification_question'];
            }
            if (! empty($answer['clarification_candidates'])) {
                return 'I found multiple matches: ' . implode(', ', $answer['clarification_candidates'])
                    . '. Which one do you mean? Please specify the category, serial number, or inventory ID.';
            }
            if (! empty($answer['clarification_item'])) {
                return 'Which ' . $answer['clarification_item'] . ' do you mean? Please specify the category, serial number, or inventory ID.';
            }

            return 'Which item would you like to check?';
        }
        if (($result['status'] ?? null) === 'forbidden') {
            return 'That information is not available for your role.';
        }
        if (($result['status'] ?? null) === 'unsupported') {
            return $this->policy->assistantHelpText($user);
        }
        if (($result['status'] ?? null) === 'insufficient_data') {
            return 'There is not enough inventory data to answer that question yet.';
        }
        if (($result['status'] ?? null) === 'not_found') {
            return ! empty($result['answer']['location_query'])
                ? 'No inventory records were found at that location.'
                : 'That item was not found in inventory.';
        }

        $answer = $result['answer'] ?? [];
        if (isset($answer['registered_users'], $answer['inventory_records'], $answer['transactions_logged'])) {
            return sprintf(
                'System summary: %d registered %s, %d inventory %s, and %d logged %s.',
                (int) $answer['registered_users'],
                Str::plural('user', (int) $answer['registered_users']),
                (int) $answer['inventory_records'],
                Str::plural('record', (int) $answer['inventory_records']),
                (int) $answer['transactions_logged'],
                Str::plural('transaction', (int) $answer['transactions_logged'])
            );
        }
        if (isset($answer['location_items'])) {
            if ($answer['location_items'] === []) {
                return 'No matching inventory records were found.';
            }

            return collect($answer['location_items'])->map(function (array $item): string {
                $holder = $item['holder'] ? "Holder: {$item['holder']}" : 'Holder: not recorded';
                $building = $item['building'] ?: 'not recorded';
                $room = $item['room'] ?: 'not recorded';

                return sprintf(
                    '- %s (Inventory ID %d): %s; Building: %s; Room: %s; Status: %s',
                    $item['item_name'],
                    $item['inventory_id'],
                    $holder,
                    $building,
                    $room,
                    $item['status']
                );
            })->implode("\n");
        }
        foreach ([
            'status_items' => 'Inventory status',
            'assignment_records' => 'Assignment',
            'maintenance_records' => 'Maintenance',
            'disposal_records' => 'Disposal',
            'ready_to_dispose_items' => 'Ready for disposal',
            'purchase_history' => 'Stock-in',
        ] as $key => $label) {
            if (isset($answer[$key])) {
                if ($answer[$key] === []) {
                    return 'No matching inventory records were found.';
                }

                return collect($answer[$key])->map(function (array $record) use ($label): string {
                    $parts = array_filter([
                        $record['item_name'] ?? null,
                        isset($record['inventory_id']) ? 'Inventory ID ' . $record['inventory_id'] : null,
                        isset($record['status']) ? 'Status: ' . $record['status'] : null,
                        isset($record['assigned_to']) ? 'Assigned to: ' . $record['assigned_to'] : null,
                        isset($record['quantity']) ? 'Quantity: ' . $record['quantity'] . ' ' . ($record['unit'] ?? 'units') : null,
                        isset($record['date']) ? 'Date: ' . $record['date'] : null,
                        isset($record['issue']) ? 'Issue: ' . $record['issue'] : null,
                        isset($record['notes']) ? 'Notes: ' . $record['notes'] : null,
                    ]);

                    return '- ' . $label . ': ' . implode('; ', $parts);
                })->implode("\n");
            }
        }
        if (isset($answer['available_count'])) {
            if (isset($answer['item_name'])) {
                return sprintf(
                    '%s has %d %s available.',
                    $answer['item_name'],
                    $answer['available_count'],
                    Str::plural($answer['unit'] ?? 'unit', $answer['available_count'])
                );
            }

            $availableCount = (int) $answer['available_count'];
            $itemTypeCount = (int) ($answer['item_types'] ?? 0);

            return sprintf(
                '%s %d available %s across %d %s.',
                $availableCount === 1 ? 'There is' : 'There are',
                $availableCount,
                Str::plural('unit', $availableCount),
                $itemTypeCount,
                Str::plural('item type', $itemTypeCount)
            );
        }
        if (isset($answer['low_stock_count'])) {
            $count = (int) $answer['low_stock_count'];

            return sprintf(
                '%d %s %s low in stock.',
                $count,
                Str::plural('item type', $count),
                $count === 1 ? 'is' : 'are'
            );
        }
        if (isset($answer['disposal_count'])) {
            $count = (int) $answer['disposal_count'];

            return sprintf(
                '%s %d disposed %s.',
                $count === 1 ? 'There is' : 'There are',
                $count,
                Str::plural('item', $count)
            );
        }
        if (isset($answer['pending_count'])) {
            return $answer['pending_count'] === 0
                ? 'There are no pending item requests.'
                : "There are {$answer['pending_count']} pending item requests.";
        }
        if (isset($answer['pending_requests'])) {
            if ($answer['pending_requests'] === []) {
                return 'There are no pending item requests.';
            }

            return collect($answer['pending_requests'])->map(fn (array $item): string => sprintf(
                '- %s: %d %s requested',
                $item['item_name'],
                $item['quantity'],
                $item['unit'] ?? 'units'
            ))->implode("\n");
        }
        if (isset($answer['item_details'])) {
            return ($result['intent'] ?? null) === 'explanation'
                ? $this->stockExplanation($answer['item_details'])
                : $this->formatItemDetails($answer['item_details']);
        }
        if (isset($answer['inventory_identifiers'])) {
            if ($answer['inventory_identifiers'] === []) {
                return 'That item was not found in inventory.';
            }

            return collect($answer['inventory_identifiers'])->map(function (array $item): string {
                $identifiers = ['Inventory ID: ' . $item['inventory_id']];
                if ($item['inventory_no'] !== null) {
                    $identifiers[] = 'Inventory number: ' . $item['inventory_no'];
                }
                if ($item['serial_number'] !== null) {
                    $identifiers[] = 'Serial number: ' . $item['serial_number'];
                }

                return '- ' . $item['item_name'] . ': ' . implode('; ', $identifiers);
            })->implode("\n");
        }
        if (isset($answer['product_names'])) {
            return $answer['product_names'] === []
                ? 'That item was not found in inventory.'
                : 'Product name: ' . implode(', ', $answer['product_names']) . '.';
        }
        if (isset($answer['items'])) {
            if ($answer['items'] === []) {
                return ($result['status'] ?? null) === 'not_found'
                    ? 'That item was not found in inventory.'
                    : 'No matching inventory records were found.';
            }

            if (($answer['valuation_metric'] ?? null) === 'unit_cost') {
                return collect($answer['items'])->map(function (array $item): string {
                    $unit = Str::singular($item['unit'] ?? 'unit');

                    return sprintf('- %s: %s per %s.', $item['item_name'], $item['unit_cost'], $unit);
                })->implode("\n");
            }

            if (isset($answer['items'][0]['total_value'])) {
                return collect($answer['items'])->map(fn (array $item): string => "- {$item['item_name']}: {$item['total_value']} total value")->implode("\n");
            }

            if (($result['intent'] ?? null) === 'explanation') {
                return collect($answer['items'])->map(fn (array $item): string => $this->stockExplanation($item))->implode("\n");
            }

            return collect($answer['items'])->map(function (array $item): string {
                $name = $item['item_name'] ?? 'Item';
                $quantity = $item['available_quantity'] ?? $item['quantity'] ?? 0;
                $unit = $item['unit'] ?? 'units';
                $status = isset($item['stock_status']) ? " ({$item['stock_status']})" : '';
                return "- {$name}: {$quantity} {$unit}{$status}";
            })->implode("\n");
        }
        if (isset($answer['available_quantity'])) {
            return sprintf(
                '%s: %d %s available.',
                $answer['item_name'] ?? 'Inventory',
                $answer['available_quantity'],
                Str::plural($answer['unit'] ?? 'unit', $answer['available_quantity'])
            );
        }
        if (isset($answer['assigned_items'])) {
            return empty($answer['assigned_items'])
                ? 'You currently have no equipment assigned in your custody.'
                : collect($answer['assigned_items'])->map(fn (array $item): string => "- {$item['item_name']} (Qty: {$item['quantity']}, No: " . ($item['inventory_no'] ?? 'N/A') . ", Status: {$item['status']})")->implode("\n");
        }
        if (isset($answer['requests'])) {
            return empty($answer['requests']) ? 'You have no recent requests.' : collect($answer['requests'])->map(fn (array $item): string => "- {$item['item_name']}: {$item['status']}")->implode("\n");
        }

        return json_encode($answer, JSON_UNESCAPED_SLASHES) ?: 'No result was found.';
    }

    /**
     * NOTE: retained verbatim from the original answer service. It has no
     * callers — it was already dead before this refactor — so it is moved
     * rather than deleted. Removing it is a separate change.
     */
    protected function stockRows(?string $itemName = null): array
    {
        return $this->groupInventoryRecords($this->matchingInventoryRecords($itemName))->values()->all();
    }

    protected function resolvedItemName(array $request, array $result): ?string
    {
        $answer = $result['answer'] ?? [];
        $singleLocationItem = count($answer['location_items'] ?? []) === 1
            ? ($answer['location_items'][0]['item_name'] ?? null)
            : null;
        $singleListItem = count($answer['items'] ?? []) === 1
            ? ($answer['items'][0]['item_name'] ?? null)
            : null;
        $candidates = [
            $answer['item_details']['item_name'] ?? null,
            $singleLocationItem,
            $singleListItem,
            $this->singleRecordItemName($answer, [
                'status_items',
                'assignment_records',
                'maintenance_records',
                'disposal_records',
                'ready_to_dispose_items',
                'purchase_history',
            ]),
            $answer['item_name'] ?? null,
            $request['item_name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function singleRecordItemName(array $answer, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (count($answer[$key] ?? []) === 1) {
                $name = $answer[$key][0]['item_name'] ?? null;
                if (is_string($name) && trim($name) !== '') {
                    return trim($name);
                }
            }
        }

        return null;
    }

    protected function formatItemDetails(array $item): string
    {
        $details = sprintf(
            '%s: %d %s available out of %d total; %s.',
            $item['item_name'],
            $item['available_quantity'],
            $item['unit'],
            $item['quantity'],
            strtolower($item['stock_status'])
        );

        if (! empty($item['discrepancies'])) {
            $details .= ' Discrepancy: ' . implode('; ', $item['discrepancies']) . '.';
        }

        return ! empty($item['description']) ? $details . ' ' . $item['description'] : $details;
    }

    protected function stockExplanation(array $item): string
    {
        $name = $item['item_name'] ?? 'This item';
        $quantity = (int) ($item['available_quantity'] ?? 0);
        $unit = $item['unit'] ?? 'units';
        $threshold = $this->lowStockThreshold();

        if ($quantity === 0) {
            return "{$name} has no available units, so it is out of stock.";
        }
        if ($quantity <= $threshold) {
            return "{$name} is available because {$quantity} {$unit} remain. It is low in stock because that is at or below the low-stock limit of {$threshold}.";
        }

        return "{$name} is available because {$quantity} {$unit} remain. It is not low in stock; the low-stock limit is {$threshold}.";
    }
}