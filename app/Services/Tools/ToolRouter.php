<?php

namespace App\Services\Tools;

use App\Models\User;
use App\Services\AiCapabilityPolicy;

/**
 * Sends an authorised request to the tool that owns its capability.
 *
 * The router is the single place where a request stops being a question and
 * becomes a database read. It holds the pre-arms (clarification, unsupported,
 * forbidden) that used to sit at the top of InventoryAnswerService::answer(),
 * so they run per sub-request rather than per turn, and it holds the
 * AiCapabilityPolicy gate.
 *
 * That gate is a security boundary, not routing detail. It lives here rather
 * than in the controller so that every caller of the answer path — including
 * the compound executor that fans a turn out into several sub-requests — is
 * authorised by the same code before any inventory row is touched.
 */
class ToolRouter
{
    /** @var array<int, ToolContract> */
    private array $tools;

    public function __construct(
        private AiCapabilityPolicy $policy,
        InventoryTool $inventoryTool,
        AssignmentTool $assignmentTool,
        RequestTool $requestTool,
        MaintenanceTool $maintenanceTool,
        HistoryTool $historyTool,
        DisposalTool $disposalTool,
        TransferTool $transferTool,
        ReturnTool $returnTool,
        ScenarioTool $scenarioTool,
        ForecastStub $forecastStub,
        IncidentTool $incidentTool,
    ) {
        $this->tools = [
            $inventoryTool,
            $assignmentTool,
            $requestTool,
            $maintenanceTool,
            $historyTool,
            $disposalTool,
            $transferTool,
            $returnTool,
            $scenarioTool,
            $forecastStub,
            $incidentTool,
        ];
    }

    /**
     * Resolve one request into one ToolResult.
     *
     * @param  array<string, mixed>  $routedQuestion
     * @return array<string, mixed>
     */
    public function dispatch(User $user, array $routedQuestion): array
    {
        $intent = $routedQuestion['intent'] ?? 'unsupported';
        $capability = $routedQuestion['capability'] ?? null;

        if (! is_string($intent) || ($capability !== null && ! is_string($capability))) {
            return $this->result('invalid_request', null, null, [
                'message' => 'The request could not be safely routed because its parameters were invalid.',
            ]);
        }

        if ($intent === 'clarification') {
            return $this->result('clarification', $intent, null, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        if ($intent === 'unsupported' || $capability === null) {
            return $this->result('unsupported', $intent, $capability);
        }

        if (! is_string($capability) || ! $this->policy->isKnownCapability($capability)) {
            return $this->result('unsupported', is_string($intent) ? $intent : null, null);
        }

        if (! $this->policy->allows($user, $capability)) {
            if (($routedQuestion['vague'] ?? false) === true) {
                return $this->result('unsupported', $intent, $capability);
            }

            return $this->result('forbidden', $intent, $capability);
        }

        if (! $this->validParameters($routedQuestion)) {
            return $this->result('invalid_request', is_string($intent) ? $intent : null, $capability, [
                'message' => 'The request could not be safely routed because its parameters were invalid.',
            ]);
        }

        if (($routedQuestion['needs_clarification'] ?? false) === true) {
            return $this->result('clarification', $intent, $capability, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        $tool = $this->toolFor($capability);
        if ($tool === null) {
            return $this->result('unsupported', $intent, $capability);
        }

        return $tool->fetch($user, $routedQuestion);
    }

    /**
     * Validate the selector and filter shapes before they reach a tool query.
     *
     * @param  array<string, mixed>  $request
     */
    private function validParameters(array $request): bool
    {
        foreach ([
            'item_name' => 160,
            'location_query' => 160,
            'serial_number' => 120,
            'unit' => 50,
            'original_question' => 1000,
            'clarification_question' => 300,
        ] as $key => $maxLength) {
            if (array_key_exists($key, $request)
                && $request[$key] !== null
                && (! is_string($request[$key]) || mb_strlen($request[$key]) > $maxLength)) {
                return false;
            }
        }

        foreach (['inventory_id', 'category_id'] as $key) {
            if (array_key_exists($key, $request)
                && $request[$key] !== null
                && (! is_int($request[$key]) || $request[$key] < 1)) {
                return false;
            }
        }

        if (array_key_exists('inventory_ids', $request)) {
            $ids = $request['inventory_ids'];
            if (! is_array($ids)
                || ! array_is_list($ids)
                || $ids === []
                || count($ids) > 25
                || count(array_filter($ids, fn ($id): bool => is_int($id) && $id > 0)) !== count($ids)) {
                return false;
            }
        }

        if (array_key_exists('filters', $request)) {
            $filters = $request['filters'];
            if (! is_array($filters)
                || array_diff(array_keys($filters), ['category_id', 'unit']) !== []) {
                return false;
            }
            if (isset($filters['category_id']) && (! is_int($filters['category_id']) || $filters['category_id'] < 1)) {
                return false;
            }
            if (isset($filters['unit']) && (! is_string($filters['unit']) || mb_strlen($filters['unit']) > 50)) {
                return false;
            }
        }

        if (isset($request['response_type'])
            && ! in_array($request['response_type'], ['detail', 'count', 'list', 'explanation', 'same_as_topic'], true)) {
            return false;
        }
        if (isset($request['query']) && (! is_string($request['query']) || mb_strlen($request['query']) > 80)) {
            return false;
        }
        foreach (['vague', 'needs_clarification'] as $key) {
            if (isset($request[$key]) && ! is_bool($request[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The tool that claims this capability, or null when none does.
     *
     * A capability with no tool stays `unsupported`, which is what procurement
     * priorities have always returned from this path.
     */
    public function toolFor(string $capability): ?ToolContract
    {
        foreach ($this->tools as $tool) {
            if ($tool->handles($capability)) {
                return $tool;
            }
        }

        return null;
    }

    private function result(string $status, ?string $intent, ?string $capability, array $answer = []): array
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