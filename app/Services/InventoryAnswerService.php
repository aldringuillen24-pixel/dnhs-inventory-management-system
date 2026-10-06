<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryAnswerService
{
    public function __construct(protected AiCapabilityPolicy $policy)
    {
    }

    public function answer(User $user, array $routedQuestion): array
    {
        $intent = $routedQuestion['intent'] ?? 'unsupported';
        $capability = $routedQuestion['capability'] ?? null;

        if ($intent === 'clarification') {
            return $this->result('clarification', $intent, null, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        if ($intent === 'unsupported' || $capability === null) {
            return $this->result('unsupported', $intent, $capability);
        }

        if (! $this->policy->allows($user, $capability)) {
            if (($routedQuestion['vague'] ?? false) === true) {
                return $this->result('unsupported', $intent, $capability);
            }

            return $this->result('forbidden', $intent, $capability);
        }

        if (($routedQuestion['needs_clarification'] ?? false) === true) {
            return $this->result('clarification', $intent, $capability, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        return match ($capability) {
            AiCapabilityPolicy::VIEW_OWN_ASSIGNMENTS => $this->assignedItems($user, $routedQuestion),
            AiCapabilityPolicy::VIEW_OWN_REQUESTS => $this->ownRequests($user, $routedQuestion),
            AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY => $this->warehouseAvailability($routedQuestion),
            AiCapabilityPolicy::VIEW_INVENTORY_STOCK => $this->inventoryStock($routedQuestion),
            AiCapabilityPolicy::VIEW_INVENTORY_LOCATION => $this->location($routedQuestion),
            AiCapabilityPolicy::VIEW_PENDING_REQUESTS => $this->pendingRequests($routedQuestion),
            AiCapabilityPolicy::VIEW_LOW_STOCK => $this->lowStock($routedQuestion),
            AiCapabilityPolicy::VIEW_DEMAND_FORECAST => $this->forecast($routedQuestion),
            AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS => $this->executiveSummary(),
            AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY => $this->systemSummary(),
            AiCapabilityPolicy::VIEW_INVENTORY_VALUATION => $this->inventoryValuation($routedQuestion),
            AiCapabilityPolicy::VIEW_ITEM_STATUS => $this->itemStatus($routedQuestion),
            AiCapabilityPolicy::VIEW_ASSIGNMENTS => $this->assignmentRecords($routedQuestion),
            AiCapabilityPolicy::VIEW_MAINTENANCE => $this->maintenanceRecords($routedQuestion),
            AiCapabilityPolicy::VIEW_DISPOSAL => $this->disposalRecords($routedQuestion),
            AiCapabilityPolicy::VIEW_READY_TO_DISPOSE => $this->readyToDispose($routedQuestion),
            AiCapabilityPolicy::VIEW_PURCHASE_HISTORY => $this->purchaseHistory($routedQuestion),
            default => $this->result('unsupported', $intent, $capability),
        };
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

    protected function warehouseAvailability(array $request): array
    {
        if (($request['item_name'] ?? null) !== null) {
            return $this->specificAvailability($request);
        }

        $allItems = $this->groupInventoryRecords($this->matchingInventoryRecords(null))
            ->filter(fn (array $item): bool => $item['available_quantity'] > 0)
            ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE);
        $items = $allItems
            ->take(25)
            ->values()
            ->all();

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => (int) $allItems->sum('available_quantity'),
                'item_types' => $allItems->count(),
                'calculated_at' => now()->toIso8601String(),
            ]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function specificAvailability(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'], $request, [
            'item_id',
            'category_id',
            'item_name',
            'quantity',
            'unit',
            'status',
            'serial_number',
            'inventory_item_no',
        ]);

        if ($records->isEmpty()) {
            return $this->result('not_found', $request['intent'], $request['capability'], [
                'item_name' => $request['item_name'],
            ]);
        }

        if ($this->hasAmbiguousUnit($records)) {
            return $this->clarificationForMatches($request, $this->groupInventoryRecords($records));
        }

        $groups = $this->groupInventoryRecords($records);
        if ($groups->count() > 1) {
            return $this->clarificationForMatches($request, $groups);
        }

        $item = $groups->first();
        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => $item['available_quantity'],
                'item_types' => 1,
                'item_name' => $item['item_name'],
                'unit' => $item['unit'],
            ]);
        }

        $availabilityFacts = [
            'item_name' => $item['item_name'],
            'quantity' => $item['quantity'],
            'available_quantity' => $item['available_quantity'],
            'assigned_quantity' => $item['assigned_quantity'],
            'inventory_ids' => $item['inventory_ids'],
            'unit' => $item['unit'],
            'stock_status' => $item['stock_status'],
            'calculated_at' => $item['calculated_at'],
            'discrepancies' => $item['discrepancies'],
        ];

        if (($request['intent'] ?? null) === 'explanation' || ($request['response_type'] ?? null) === 'detail') {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $availabilityFacts]);
        }

        return $this->result('success', $request['intent'], $request['capability'], $availabilityFacts);
    }

    protected function inventoryStock(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'] ?? null, $request);

        if (in_array($request['query'] ?? null, ['inventory_identifiers', 'product_name'], true)) {
            if ($records->isEmpty()) {
                return $this->result('not_found', $request['intent'], $request['capability']);
            }

            if ($request['query'] === 'product_name') {
                return $this->result('success', $request['intent'], $request['capability'], [
                    'product_names' => $records->pluck('item_name')->unique(fn ($name) => mb_strtolower(trim($name)))->values()->all(),
                ]);
            }

            return $this->result('success', $request['intent'], $request['capability'], [
                'inventory_identifiers' => $records->map(fn (Inventory $item): array => [
                    'item_name' => $item->item_name,
                    'inventory_id' => (int) $item->item_id,
                    'inventory_no' => $item->inventory_item_no,
                    'serial_number' => $item->serial_number,
                ])->all(),
            ]);
        }

        $items = $this->groupInventoryRecords($records)->values()->all();

        if (($request['item_name'] ?? null) !== null && count($items) > 1) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['item_name'] ?? null) !== null && (
            $this->hasAmbiguousCategoryOrUnit($records)
            || (($request['response_type'] ?? null) === 'detail' && $records->count() > 1)
        )) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'available_count' => (int) collect($items)->sum('quantity'),
                'item_types' => count($items),
            ]);
        }

        if (count($items) === 1 && (($request['response_type'] ?? null) === 'detail' || ($request['intent'] ?? null) === 'explanation')) {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $items[0]]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function location(array $request): array
    {
        $query = Inventory::query()->with(['assignedTo', 'category']);
        $itemName = trim((string) ($request['item_name'] ?? ''));
        $locationQuery = trim((string) ($request['location_query'] ?? ''));

        if (isset($request['inventory_id'])) {
            $query->whereKey((int) $request['inventory_id']);
        } elseif ($itemName !== '') {
            $query->whereRaw('LOWER(TRIM(item_name)) = ?', [mb_strtolower($itemName)]);
        } elseif ($locationQuery !== '') {
            $normalizedLocation = mb_strtolower($locationQuery);
            $locationVariants = array_values(array_unique([
                $normalizedLocation,
                preg_replace('/^room\s+/u', '', $normalizedLocation) ?? $normalizedLocation,
            ]));
            $query->where(function ($locationQueryBuilder) use ($locationVariants): void {
                $locationQueryBuilder
                    ->whereIn(DB::raw('LOWER(TRIM(building))'), $locationVariants)
                    ->orWhereIn(DB::raw('LOWER(TRIM(room))'), $locationVariants);
            });
        } else {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_question' => 'Which item or building/room should I look up?',
            ]);
        }

        $this->filterInventoryIdentity($query, $request);

        $records = $query->orderBy('item_id')->get();
        if ($records->isEmpty()) {
            return $this->result('not_found', $request['intent'], $request['capability'], [
                'item_name' => $itemName ?: null,
                'location_query' => $locationQuery ?: null,
            ]);
        }

        if ($itemName !== '' && $records->count() > 1) {
            return $this->result('clarification', $request['intent'], $request['capability'], [
                'clarification_candidates' => $records->map(fn (Inventory $item): string => sprintf(
                    '%s (Inventory ID %d%s)',
                    $item->item_name,
                    $item->item_id,
                    $item->serial_number ? ", Serial {$item->serial_number}" : ''
                ))->all(),
            ]);
        }

        $items = $records->map(function (Inventory $item): array {
            $activeTransactions = Transaction::query()
                ->with('user')
                ->where('item_id', $item->item_id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->orderBy('id')
                ->get();
            $holders = $activeTransactions->map(fn (Transaction $transaction): ?string =>
                $transaction->user?->full_name ?? $transaction->manual_recipient_name
            )->filter()->unique()->values();

            return [
                'inventory_id' => (int) $item->item_id,
                'item_name' => $item->item_name,
                'holder' => $holders->isNotEmpty()
                    ? $holders->implode(', ')
                    : $item->assignedTo?->full_name,
                'building' => $item->building,
                'room' => $item->room,
                'status' => $item->status,
            ];
        })->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'location_items' => $items,
            'location_query' => $locationQuery ?: null,
        ]);
    }

    protected function assignedItems(User $user, array $request): array
    {
        $items = Inventory::query()->where('assigned_to_user_id', $user->id)->where('status', 'assigned')
            ->get(['item_id', 'item_name', 'quantity', 'unit', 'inventory_item_no', 'status'])
            ->loadMissing('transactions.assignmentReturns')
            ->map(function (Inventory $item): array {
                $snapshot = $item->quantitySnapshot();

                return [
                    'item_name' => $item->item_name,
                    'quantity' => $snapshot['assigned_quantity'],
                    'unit' => $item->unit,
                    'inventory_id' => (int) $item->item_id,
                    'inventory_no' => $item->inventory_item_no,
                    'status' => $item->status,
                    'calculated_at' => $snapshot['calculated_at'],
                    'discrepancies' => $snapshot['discrepancies'],
                ];
            })->all();

        return $this->result('success', $request['intent'], $request['capability'], ['assigned_items' => $items]);
    }

    protected function ownRequests(User $user, array $request): array
    {
        if (($request['query'] ?? null) === 'pending_count') {
            $count = AssignmentRequest::query()->where('user_id', $user->id)
                ->whereIn('status', ['waiting for approval', 'waiting for transfer approval', 'waiting for custodian approval'])
                ->count();

            return $this->result('success', $request['intent'], $request['capability'], ['pending_count' => $count]);
        }

        $requests = AssignmentRequest::query()->where('user_id', $user->id)->latest('requested_at')->limit(10)
            ->get(['item_id', 'quantity', 'status', 'requested_at'])->load('item:item_id,item_name')
            ->map(fn (AssignmentRequest $itemRequest): array => [
                'item_name' => $itemRequest->item?->item_name ?? 'Item',
                'quantity' => (int) $itemRequest->quantity,
                'status' => $itemRequest->status,
                'requested_at' => $itemRequest->requested_at?->toDateString(),
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['requests' => $requests]);
    }

    protected function pendingRequests(array $request): array
    {
        $requests = AssignmentRequest::query()->whereIn('status', [
            'waiting for approval',
            'waiting for transfer approval',
            'waiting for custodian approval',
        ])->get(['item_id', 'requested_item_name', 'requested_unit', 'quantity', 'requested_at'])->load('item:item_id,item_name,unit')
            ->map(fn (AssignmentRequest $itemRequest): array => [
                'item_name' => $itemRequest->item?->item_name ?? $itemRequest->requested_item_name ?? 'Item',
                'quantity' => (int) $itemRequest->quantity,
                'unit' => $itemRequest->item?->unit ?? $itemRequest->requested_unit ?? 'units',
                'requested_at' => $itemRequest->requested_at?->toDateString(),
            ]);

        if (($request['query'] ?? null) === 'pending_count') {
            return $this->result('success', $request['intent'], $request['capability'], ['pending_count' => $requests->count()]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['pending_requests' => $requests->all()]);
    }

    protected function lowStock(array $request): array
    {
        $records = $this->matchingInventoryRecords($request['item_name'] ?? null, $request);
        if (($request['item_name'] ?? null) !== null && $this->hasAmbiguousCategoryOrUnit($records)) {
            return $this->clarificationForMatches($request, $this->groupInventoryRecords($records));
        }
        $items = array_values(array_filter($this->groupInventoryRecords($records)->values()->all(), fn (array $item): bool => $item['available_quantity'] <= $this->lowStockThreshold()));

        if (($request['item_name'] ?? null) !== null && count($items) > 1) {
            return $this->clarificationForMatches($request, collect($items));
        }

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], ['low_stock_count' => count($items)]);
        }

        if (($request['item_name'] ?? null) !== null && $items === []) {
            $records = $this->matchingInventoryRecords($request['item_name'], $request);
            $allMatches = $this->groupInventoryRecords($records);
            if ($allMatches->count() > 1) {
                return $this->clarificationForMatches($request, $allMatches);
            }
            if ($allMatches->isNotEmpty()) {
                return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $allMatches->first()]);
            }
            return $this->result('not_found', $request['intent'], $request['capability'], ['item_name' => $request['item_name']]);
        }

        if (count($items) === 1 && ($request['response_type'] ?? null) === 'detail') {
            return $this->result('success', $request['intent'], $request['capability'], ['item_details' => $items[0]]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['items' => $items]);
    }

    protected function forecast(array $request): array
    {
        return $this->result('insufficient_data', 'forecast', AiCapabilityPolicy::VIEW_DEMAND_FORECAST, [
            'message' => 'No validated stored ML forecast is available through this question path.',
        ]);
    }

    protected function executiveSummary(): array
    {
        $items = $this->groupInventoryRecords($this->matchingInventoryRecords(null));

        return $this->result('success', 'factual', AiCapabilityPolicy::VIEW_EXECUTIVE_REPORTS, [
            'total_items' => (int) $items->sum('quantity'),
            'available_items' => (int) $items->sum('available_quantity'),
            'assigned_items' => (int) $items->sum('assigned_quantity'),
            'calculated_at' => now()->toIso8601String(),
            'discrepancies' => $items->pluck('discrepancies')->flatten()->values()->all(),
        ]);
    }

    protected function systemSummary(): array
    {
        return $this->result('success', 'factual', AiCapabilityPolicy::VIEW_SYSTEM_SUMMARY, [
            'registered_users' => (int) User::count(),
            'inventory_records' => (int) Inventory::count(),
            'transactions_logged' => (int) Transaction::count(),
        ]);
    }

    protected function inventoryValuation(array $request): array
    {
        $query = Inventory::query()->where('status', '!=', 'disposed');
        $this->filterItem($query, $request['item_name'] ?? null);
        $unitCostQuestion = preg_match('/\bunit\s+cost\b|\bcost\s+per\s+unit\b|\bhow much\s+(?:is\s+)?(?:one|1|each)\b/u', strtolower((string) ($request['original_question'] ?? ''))) === 1;
        $items = $query->get(['item_name', 'quantity', 'unit', 'unit_cost'])->groupBy('item_name')->map(fn ($group, $name): array => [
            'item_name' => $name,
            'quantity' => (int) $group->sum('quantity'),
            'unit_cost' => (float) ($group->first()->unit_cost ?? 0),
            'total_value' => (float) $group->sum(fn (Inventory $item): float => (float) $item->unit_cost * (int) $item->quantity),
            'unit' => $group->first()->unit ?? 'units',
        ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], [
            'items' => $items,
            'valuation_metric' => $unitCostQuestion ? 'unit_cost' : 'total_value',
        ]);
    }

    protected function itemStatus(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $items = $records->take(25)->map(fn (Inventory $item): array => [
            'inventory_id' => (int) $item->item_id,
            'item_name' => $item->item_name,
            'status' => $item->status,
            'quantity' => (int) $item->quantity,
            'unit' => $item->unit,
            'serial_number' => $item->serial_number,
        ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['status_items' => $items]);
    }

    protected function assignmentRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $assignments = Transaction::query()->with(['user', 'item'])
            ->where('status', 'assigned')
            ->whereIn('item_id', $records->pluck('item_id'))
            ->orderByDesc('transaction_date')
            ->limit(25)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'inventory_id' => (int) $transaction->item_id,
                'item_name' => $transaction->item?->item_name ?? 'Item',
                'assigned_to' => $transaction->manual_recipient_name ?? $transaction->user?->full_name ?? 'Not recorded',
                'quantity' => (int) $transaction->quantity,
                'unit' => $transaction->item?->unit ?? 'units',
                'status' => $transaction->status,
                'date' => $transaction->transaction_date?->toDateString(),
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['assignment_records' => $assignments]);
    }

    protected function maintenanceRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $maintenance = MaintenanceRecord::query()->with('inventory')
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->latest('started_at')
            ->limit(25)
            ->get()
            ->map(fn (MaintenanceRecord $record): array => [
                'inventory_id' => (int) $record->inventory_id,
                'item_name' => $record->inventory?->item_name ?? 'Item',
                'status' => $record->status,
                'issue' => $record->issue_description,
                'notes' => $record->repair_notes,
                'date' => $record->started_at?->toDateString(),
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['maintenance_records' => $maintenance]);
    }

    protected function disposalRecords(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $items = $records->where('status', 'disposed')->map(function (Inventory $item): array {
            $movement = StockMovement::query()->where('inventory_id', $item->item_id)
                ->where('movement_type', 'disposed')->latest()->first();

            return [
                'inventory_id' => (int) $item->item_id,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'quantity' => (int) ($movement?->quantity ?? $item->quantity),
                'unit' => $item->unit,
                'date' => $movement?->created_at?->toDateString(),
                'notes' => $movement?->notes,
            ];
        })->values()->all();

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $request['intent'], $request['capability'], [
                'disposal_count' => count($items),
            ]);
        }

        return $this->result('success', $request['intent'], $request['capability'], ['disposal_records' => $items]);
    }

    protected function readyToDispose(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true)
            ->whereIn('status', ['ready_to_dispose', 'disposal_review']);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $items = $records->take(25)->map(fn (Inventory $item): array => [
            'inventory_id' => (int) $item->item_id,
            'item_name' => $item->item_name,
            'status' => $item->status,
            'quantity' => (int) $item->quantity,
            'unit' => $item->unit,
        ])->values()->all();

        return $this->result('success', $request['intent'], $request['capability'], ['ready_to_dispose_items' => $items]);
    }

    protected function purchaseHistory(array $request): array
    {
        $records = $this->inventoryRecordsForRequest($request, true);
        if ($this->hasNoMatchingItem($request, $records)) {
            return $this->result('not_found', $request['intent'], $request['capability']);
        }
        if ($this->needsRecordClarification($request, $records)) {
            return $this->recordClarification($request, $records);
        }

        $history = StockMovement::query()->with('inventory')
            ->where('movement_type', 'stock_in')
            ->when($records->isNotEmpty(), fn ($query) => $query->whereIn('inventory_id', $records->pluck('item_id')))
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'inventory_id' => (int) $movement->inventory_id,
                'item_name' => $movement->inventory?->item_name ?? 'Item',
                'quantity' => (int) $movement->quantity,
                'unit' => $movement->inventory?->unit ?? 'units',
                'date' => $movement->created_at?->toDateString(),
                'notes' => $movement->notes,
            ])->all();

        return $this->result('success', $request['intent'], $request['capability'], ['purchase_history' => $history]);
    }

    protected function inventoryRecordsForRequest(array $request, bool $includeDisposed = false)
    {
        $query = Inventory::query()->orderBy('item_id');
        if (! $includeDisposed) {
            $query->where('status', '!=', 'disposed');
        }
        if (isset($request['inventory_id'])) {
            $query->where('item_id', (int) $request['inventory_id']);
        }
        if (is_string($request['serial_number'] ?? null) && trim($request['serial_number']) !== '') {
            $query->where('serial_number', trim($request['serial_number']));
        }
        if (is_string($request['item_name'] ?? null) && trim($request['item_name']) !== '') {
            $name = mb_strtolower(str_replace('-', ' ', trim($request['item_name'])));
            $query->whereRaw("LOWER(REPLACE(item_name, '-', ' ')) LIKE ?", ['%' . $name . '%']);
        }

        return $query->get();
    }

    protected function hasNoMatchingItem(array $request, $records): bool
    {
        return ($request['item_name'] ?? null) !== null
            || isset($request['inventory_id'])
            || isset($request['serial_number'])
            ? $records->isEmpty()
            : false;
    }

    protected function needsRecordClarification(array $request, $records): bool
    {
        $hasSelector = (is_string($request['item_name'] ?? null) && trim($request['item_name']) !== '')
            || (is_string($request['serial_number'] ?? null) && trim($request['serial_number']) !== '');

        return ! isset($request['inventory_id']) && $hasSelector && $records->count() > 1;
    }

    protected function recordClarification(array $request, $records): array
    {
        return $this->result('clarification', $request['intent'], $request['capability'], [
            'clarification_item' => $request['item_name'] ?? null,
            'clarification_candidates' => $this->formatClarificationCandidates($records),
        ]);
    }

    protected function stockRows(?string $itemName = null): array
    {
        return $this->groupInventoryRecords($this->matchingInventoryRecords($itemName))->values()->all();
    }

    protected function matchingInventoryRecords(?string $itemName, array $identity = [], ?array $columns = null)
    {
        $query = Inventory::query()->where('status', '!=', 'disposed');
        $this->filterInventoryIdentity($query, $identity);
        $query->orderBy('item_id');
        $columns ??= ['item_id', 'category_id', 'item_name', 'description', 'quantity', 'unit', 'status', 'serial_number', 'inventory_item_no'];
        if ($itemName === null) {
            return $query->get($columns);
        }

        $normalizedName = strtolower(trim(str_replace('-', ' ', $itemName)));
        $normalizedName = preg_replace('/\s+/', ' ', $normalizedName) ?? $normalizedName;
        $terms = [$normalizedName];
        if (preg_match('/([a-z]+)$/', $normalizedName, $matches) === 1) {
            $lastWord = $matches[1];
            if (preg_match('/ies$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -3) . 'y';
            } elseif (preg_match('/(?:ches|shes|sses|xes|zes)$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -2);
            } elseif (preg_match('/[^s]s$/', $lastWord) === 1) {
                $terms[] = substr($normalizedName, 0, -1);
            }
        }

        $terms = array_values(array_unique($terms));
        foreach ($terms as $term) {
            $exact = (clone $query)->whereRaw("LOWER(REPLACE(TRIM(item_name), '-', ' ')) = ?", [$term])
                ->get($columns);
            if ($exact->isNotEmpty()) {
                return $exact;
            }
        }

        foreach ($terms as $term) {
            $matches = (clone $query)->whereRaw("LOWER(REPLACE(item_name, '-', ' ')) LIKE ?", ['%' . $term . '%'])
                ->get($columns);
            if ($matches->isNotEmpty()) {
                return $matches;
            }
        }

        return collect();
    }

    protected function filterInventoryIdentity($query, array $identity): void
    {
        if (isset($identity['inventory_id'])) {
            $query->where('item_id', (int) $identity['inventory_id']);
        }
        if (isset($identity['category_id'])) {
            $query->where('category_id', (int) $identity['category_id']);
        }
        if (is_string($identity['unit'] ?? null) && $identity['unit'] !== '') {
            $query->whereRaw('LOWER(TRIM(unit)) = ?', [mb_strtolower($identity['unit'])]);
        }
        if (is_string($identity['serial_number'] ?? null) && $identity['serial_number'] !== '') {
            $query->where('serial_number', $identity['serial_number']);
        }
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

    protected function hasAmbiguousCategoryOrUnit($records): bool
    {
        $categories = $records->pluck('category_id')->filter()->unique()->count();
        return $categories > 1 || $this->hasAmbiguousUnit($records);
    }

    protected function hasAmbiguousUnit($records): bool
    {
        $units = $records->pluck('unit')->filter()->map(fn ($unit): string => strtolower(trim($unit)))->unique()->count();

        return $units > 1;
    }

    protected function clarificationForMatches(array $request, $matches): array
    {
        $records = isset($request['item_name'])
            ? $this->matchingInventoryRecords($request['item_name'])
            : collect();
        $candidates = $this->formatClarificationCandidates($records);
        if ($candidates === []) {
            $candidates = collect($matches)->pluck('item_name')->filter()->unique()->values()->all();
        }

        return $this->result('clarification', $request['intent'] ?? null, $request['capability'] ?? null, [
            'clarification_item' => $request['item_name'] ?? null,
            'clarification_candidates' => $candidates,
        ]);
    }

    protected function formatClarificationCandidates($records): array
    {
        $records = collect($records)->take(20);
        $categoryNames = Category::query()
            ->whereIn('category_id', $records->pluck('category_id')->filter()->unique())
            ->pluck('category_name', 'category_id');

        return $records->values()->map(fn (Inventory $item, int $index): string => sprintf(
            '%d) %s (Category: %s, Unit: %s, Inventory ID: %d%s%s)',
            $index + 1,
            $item->item_name,
            $categoryNames[$item->category_id] ?? 'Uncategorized',
            $item->unit,
            $item->item_id,
            $item->serial_number ? ", Serial: {$item->serial_number}" : '',
            $item->inventory_item_no ? ", Asset Tag: {$item->inventory_item_no}" : ''
        ))->all();
    }

    protected function normalizeClarificationSelection(string $selection): string
    {
        $selection = mb_strtolower(trim($selection));
        $selection = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $selection) ?? $selection;

        return trim(preg_replace('/\s+/u', ' ', $selection) ?? $selection);
    }

    protected function groupInventoryRecords($records)
    {
        $records->loadMissing('transactions.assignmentReturns');

        return $records->groupBy(fn (Inventory $item): string => strtolower(trim($item->item_name)))
            ->map(function ($group): array {
                $snapshots = $group->map(fn (Inventory $item): array => $item->quantitySnapshot());
                $available = (int) $snapshots->sum('available_quantity');
                return [
                    'item_name' => $group->first()->item_name,
                    'description' => $group->first()->description,
                    'quantity' => (int) $snapshots->sum('total_quantity'),
                    'available_quantity' => $available,
                    'assigned_quantity' => (int) $snapshots->sum('assigned_quantity'),
                    'inventory_ids' => $snapshots->pluck('inventory_id')->values()->all(),
                    'unit' => $group->first()->unit ?? 'units',
                    'stock_status' => $this->stockStatus($available),
                    'statuses' => $group->pluck('status')->unique()->values()->all(),
                    'calculated_at' => $snapshots->max('calculated_at'),
                    'discrepancies' => $snapshots->pluck('discrepancies')->flatten()->values()->all(),
                ];
            })->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE);
    }

    protected function filterItem($query, ?string $itemName): void
    {
        if ($itemName !== null) {
            $query->whereRaw('LOWER(item_name) LIKE ?', ['%' . strtolower($itemName) . '%']);
        }
    }

    protected function stockStatus(int $availableQuantity): string
    {
        return $availableQuantity === 0
            ? 'Out of Stock'
            : ($availableQuantity <= $this->lowStockThreshold() ? 'Low Stock' : 'In Stock');
    }

    protected function lowStockThreshold(): int
    {
        return max(1, (int) config('inventory.low_stock_threshold', 5));
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

    protected function result(string $status, ?string $intent, ?string $capability, array $answer = []): array
    {
        return [
            'status' => $status,
            'intent' => $intent,
            'capability' => $capability,
            'answer' => $answer,
            'explanation_data' => $answer,
        ];
    }
}
