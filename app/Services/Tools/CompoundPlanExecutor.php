<?php

namespace App\Services\Tools;

use App\Models\User;
use App\Services\AiCapabilityPolicy;

/**
 * Runs the sub-requests of a compound turn and merges what comes back.
 *
 * The important property is that authorisation happens per sub-request. A
 * question like "how many projectors are available and show the system
 * summary" carries two capabilities, and a role may hold one without the
 * other. Checking the turn as a whole would either answer the part the role
 * cannot see, or withhold the part it can.
 *
 * Every sub-request is therefore dispatched through ToolRouter::dispatch(),
 * which runs the AiCapabilityPolicy gate again for that sub-request. A denied
 * part comes back as `forbidden` and is reported as such in the merged reply;
 * the rest of the turn is still answered.
 */
class CompoundPlanExecutor
{
    /**
     * Bound the number of tool calls and the single optional dependent-planning
     * round. These are in addition to the row limits enforced by each tool.
     */
    private const MAX_RETRIEVAL_STEPS = 6;
    private const MAX_PLAN_DEPTH = 2;

    public function __construct(
        private ToolRouter $router,
        private AiCapabilityPolicy $policy,
    ) {
    }

    /**
     * Execute a plan and return one ToolResult per sub-request, in order.
     *
     * @param  array<string, mixed>  $plan
     * @param  callable(User, array<string, mixed>): array<string, mixed>|null  $forecastDispatcher
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user, array $plan, ?callable $forecastDispatcher = null): array
    {
        $requests = $plan['requests'] ?? [];
        if (! is_array($requests) || $requests === []) {
            return [];
        }

        $hasNestedPlan = false;
        foreach ($requests as $request) {
            if (is_array($request)
                && (array_key_exists('requests', $request) || array_key_exists('plan', $request))) {
                $hasNestedPlan = true;
                break;
            }
        }
        if (! is_int($plan['depth'] ?? 1)
            || ($plan['depth'] ?? 1) < 1
            || ($plan['depth'] ?? 1) > self::MAX_PLAN_DEPTH
            || $hasNestedPlan) {
            return [$this->result(
                'invalid_request',
                'factual',
                null,
                ['message' => 'The retrieval plan exceeded its permitted depth.'],
            )];
        }

        $firstRequest = $requests[0] ?? null;
        if (is_array($firstRequest) && self::isMultiEvidenceQuestion($firstRequest)) {
            return $this->executeReplacementAssessment($user, $firstRequest);
        }

        $results = [];
        foreach (array_slice(array_values($requests), 0, self::MAX_RETRIEVAL_STEPS) as $request) {
            if (! is_array($request)) {
                continue;
            }

            // Each part is authorised on its own capability. This is the gate
            // that makes a compound turn no broader than a single one.
            if (! is_string($request['capability'] ?? null)
                || ! $this->policy->allows($user, $request['capability'])) {
                $results[] = $this->result(
                    ($request['vague'] ?? false) === true ? 'unsupported' : 'forbidden',
                    is_string($request['intent'] ?? null) ? $request['intent'] : null,
                    is_string($request['capability'] ?? null) ? $request['capability'] : null,
                    $request,
                );
                continue;
            }

            $dispatched = ($request['capability'] ?? null) === AiCapabilityPolicy::VIEW_DEMAND_FORECAST
                && $forecastDispatcher !== null
                ? $forecastDispatcher($user, $request)
                : $this->router->dispatch($user, $request);

            // Carry the subject so the merged reply can label each part. This
            // is a sibling key: status, answer and explanation_data are exactly
            // what the router returned.
            $dispatched['sub_request'] = $this->describe($request);

            $results[] = $dispatched;
        }

        if (count($requests) > self::MAX_RETRIEVAL_STEPS) {
            $results[] = [
                'status' => 'insufficient_data',
                'intent' => 'factual',
                'capability' => null,
                'answer' => ['missing_evidence' => ['The retrieval step limit was reached; remaining planned requests were not run.']],
                'explanation_data' => [],
                'sub_request' => ['label' => 'Evidence gaps'],
            ];
        }

        return $results;
    }

    /**
     * Summarize repeat maintenance records only from the evidence already
     * retrieved; this does not infer that repeated records share a root cause.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $locations
     * @return array<string, mixed>
     */
    private function recurringEquipmentSummary(array $records, array $locations): array
    {
        $locationById = collect($locations)->keyBy('inventory_id');
        $recurring = collect($records)
            ->filter(fn ($record): bool => is_array($record) && is_int($record['inventory_id'] ?? null))
            ->groupBy('inventory_id')
            ->filter(fn ($group): bool => $group->count() >= 2)
            ->map(function ($group, string $id) use ($locationById): array {
                $first = $group->first();
                $location = $locationById->get((int) $id, []);

                return [
                    'inventory_id' => (int) $id,
                    'item_name' => $first['item_name'] ?? 'Item',
                    'record_count' => $group->count(),
                    'building' => $location['building'] ?? null,
                    'room' => $location['room'] ?? null,
                ];
            })->values()->all();

        return [
            'items' => $recurring,
            'evidence_basis' => 'An item is listed only when at least two maintenance records for its inventory id appear in the retrieved records.',
            'uncertainty' => 'Repeated records do not establish the same root cause. The maintenance tool returns at most 25 records, and missing location values remain unknown.',
        ];
    }

    /**
     * Whether this is the narrow maintenance/location/replacement question
     * that needs dependent evidence retrieval.
     *
     * @param  array<string, mixed>  $request
     */
    public static function isMultiEvidenceQuestion(array $request): bool
    {
        if (! is_string($request['original_question'] ?? null)) {
            return false;
        }
        $question = mb_strtolower($request['original_question']);

        return preg_match('/\b(?:maintenance|repair|equipment problem|equipment issue|recurring|repeat(?:ed|ing)?)\b/u', $question) === 1
            && preg_match('/\b(?:classrooms?|rooms?|buildings?|locations?)\b/u', $question) === 1
            && preg_match('/\b(?:replace|replacement|replacing)\b/u', $question) === 1;
    }

    /**
     * Retrieve maintenance records first, then locations, condition/age, and
     * repair-versus-recorded-cost evidence for the resulting inventory ids.
     *
     * @param  array<string, mixed>  $initialRequest
     * @return array<int, array<string, mixed>>
     */
    private function executeReplacementAssessment(User $user, array $initialRequest): array
    {
        if (count($initialRequest) === 0) {
            return [];
        }

        $trace = [];
        $results = [];
        $maintenanceRequest = $this->stepRequest(
            $initialRequest,
            AiCapabilityPolicy::VIEW_MAINTENANCE,
            'maintenance records',
        );
        $maintenance = $this->dispatchStep($user, $maintenanceRequest, 'Maintenance records', $trace);
        $results[] = $maintenance;

        if (($maintenance['status'] ?? null) !== 'success') {
            $this->addEvidenceGap($results, $trace, 'Maintenance records were unavailable, so affected inventory ids could not be established.');

            return $this->withTrace($results, $trace);
        }

        $records = $maintenance['answer']['maintenance_records'] ?? null;
        if (! is_array($records) || $records === []) {
            $this->addEvidenceGap($results, $trace, 'No maintenance records were found; recurring problems and affected items cannot be established.');

            return $this->withTrace($results, $trace);
        }
        $trace[0]['decision'] = 'maintenance records found; derive affected inventory ids';
        $missingEvidence = [];

        $ids = collect($records)
            ->pluck('inventory_id')
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->unique()
            ->take(25)
            ->values()
            ->all();
        if ($ids === []) {
            $this->addEvidenceGap($results, $trace, 'Maintenance records did not include usable inventory ids for follow-up retrieval.');

            return $this->withTrace($results, $trace);
        }
        if (count($records) >= 25) {
            $missingEvidence[] = 'The maintenance tool returned its 25-record maximum; further matching history may be omitted.';
        }

        $location = $this->dispatchStep(
            $user,
            $this->stepRequest($initialRequest, AiCapabilityPolicy::VIEW_INVENTORY_LOCATION, 'inventory locations', $ids),
            'Locations',
            $trace,
        );
        $results[] = $location;
        if (($location['status'] ?? null) !== 'success') {
            $trace[1]['decision'] = 'location retrieval failed; continue with other evidence';
            $missingEvidence[] = 'Location evidence could not be retrieved for the affected items.';
        } elseif (($location['answer']['location_items'] ?? []) === []) {
            $trace[1]['decision'] = 'no location rows; continue with other evidence';
            $missingEvidence[] = 'No location records were returned for the affected items.';
        } else {
            $trace[1]['decision'] = 'location rows found; continue with condition and cost evidence';
        }
        $results[] = [
            'status' => 'success',
            'intent' => 'factual',
            'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
            'answer' => ['recurring_equipment_summary' => $this->recurringEquipmentSummary(
                $records,
                $location['answer']['location_items'] ?? [],
            )],
            'explanation_data' => [],
            'sub_request' => ['label' => 'Recurring pattern'],
        ];

        $condition = $this->dispatchStep(
            $user,
            $this->stepRequest($initialRequest, AiCapabilityPolicy::VIEW_ITEM_STATUS, 'condition and acquisition age', $ids, 'item condition age acquisition'),
            'Condition and acquisition',
            $trace,
        );
        $results[] = $condition;
        if (($condition['status'] ?? null) !== 'success') {
            $trace[2]['decision'] = 'condition retrieval failed; continue with repair-cost evidence';
            $missingEvidence[] = 'Condition and acquisition-age evidence could not be retrieved.';
        } elseif (($condition['answer']['condition_age_analytics']['items'] ?? []) === []) {
            $trace[2]['decision'] = 'no condition rows; continue with repair-cost evidence';
            $missingEvidence[] = 'No condition or acquisition-age rows were returned for the affected items.';
        } else {
            $trace[2]['decision'] = 'condition rows found; continue with repair-cost evidence';
        }

        $costs = $this->dispatchStep(
            $user,
            $this->stepRequest($initialRequest, AiCapabilityPolicy::VIEW_MAINTENANCE, 'should we repair or replace', $ids),
            'Repair/replacement evidence',
            $trace,
        );
        $results[] = $costs;
        if (($costs['status'] ?? null) !== 'success') {
            $trace[3]['decision'] = 'repair-cost retrieval failed; stop';
            $missingEvidence[] = 'Repair-cost and replacement-comparison evidence could not be retrieved.';
        } else {
            $trace[3]['decision'] = 'repair-cost evidence retrieved; stop';
        }
        if ($missingEvidence !== []) {
            $this->addEvidenceGap($results, $trace, implode(' ', $missingEvidence));
        }

        return $this->withTrace($results, $trace);
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, int>|null  $inventoryIds
     * @return array<string, mixed>
     */
    private function stepRequest(
        array $source,
        string $capability,
        string $toolQuestion,
        ?array $inventoryIds = null,
    ): array {
        $request = [
            'intent' => 'factual',
            'capability' => $capability,
            'original_question' => $toolQuestion,
            'response_type' => 'list',
            'filters' => [],
        ];

        if (is_string($source['item_name'] ?? null) && trim($source['item_name']) !== '') {
            $request['item_name'] = $source['item_name'];
        }
        if (is_string($source['serial_number'] ?? null) && trim($source['serial_number']) !== '') {
            $request['serial_number'] = $source['serial_number'];
        }
        if (is_int($source['inventory_id'] ?? null) && $source['inventory_id'] > 0) {
            $request['inventory_id'] = $source['inventory_id'];
        }
        if ($inventoryIds !== null) {
            unset($request['item_name'], $request['serial_number'], $request['inventory_id']);
            $request['inventory_ids'] = $inventoryIds;
        }

        return $request;
    }

    /**
     * Every retrieval step goes through the router, which checks policy and
     * validates its parameters before selecting a fixed tool.
     *
     * @param  array<string, mixed>  $request
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<string, mixed>
     */
    private function dispatchStep(User $user, array $request, string $label, array &$trace): array
    {
        $result = $this->router->dispatch($user, $request);
        $trace[] = [
            'step' => count($trace) + 1,
            'tool' => $request['capability'],
            'params' => [
                ...array_intersect_key($request, array_flip(['item_name', 'inventory_id', 'inventory_ids'])),
                'tool_question' => $request['original_question'],
            ],
            'authorized' => ! in_array($result['status'] ?? null, ['forbidden', 'invalid_request'], true),
            'status' => $result['status'] ?? 'unknown',
            'decision' => ($result['status'] ?? null) === 'success' ? 'pending sufficiency check' : 'step failed',
        ];
        $result['sub_request'] = ['label' => $label];

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<int, array<string, mixed>>  $trace
     */
    private function addEvidenceGap(array &$results, array &$trace, string $message): void
    {
        $trace[] = [
            'step' => count($trace) + 1,
            'tool' => null,
            'params' => [],
            'authorized' => null,
            'decision' => 'stop: '.$message,
        ];
        $results[] = [
            'status' => 'insufficient_data',
            'intent' => 'factual',
            'capability' => AiCapabilityPolicy::VIEW_MAINTENANCE,
            'answer' => ['missing_evidence' => [$message]],
            'explanation_data' => ['missing_evidence' => [$message]],
            'sub_request' => ['label' => 'Evidence gaps'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<int, array<string, mixed>>
     */
    private function withTrace(array $results, array $trace): array
    {
        foreach ($results as &$result) {
            $result['retrieval_trace'] = $trace;
        }
        unset($result);

        return $results;
    }

    /**
     * The subject of a sub-request, used to label its part of a merged reply.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function describe(array $request): array
    {
        if (is_string($request['label'] ?? null)) {
            return ['label' => $request['label']];
        }
        if (($request['capability'] ?? null) === AiCapabilityPolicy::VIEW_DEMAND_FORECAST) {
            return ['label' => 'Forecast'];
        }

        return [
            'item_name' => $request['item_name'] ?? null,
            'location_query' => $request['location_query'] ?? null,
            'inventory_id' => $request['inventory_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function result(string $status, ?string $intent, ?string $capability, array $request): array
    {
        return [
            'status' => $status,
            'intent' => $intent,
            'capability' => $capability,
            'answer' => [],
            'explanation_data' => [],
            'sub_request' => $this->describe($request),
        ];
    }
}