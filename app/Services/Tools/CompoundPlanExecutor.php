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
     * Guard against a provider that decomposes one question into a long list.
     *
     * This bounds work per turn, not a business row cap: the per-capability
     * take(25)/limit(25) caps inside the tools still apply to each part.
     */
    private const MAX_SUB_REQUESTS = 6;

    public function __construct(
        private ToolRouter $router,
        private AiCapabilityPolicy $policy,
    ) {
    }

    /**
     * Execute a plan and return one ToolResult per sub-request, in order.
     *
     * @param  array<string, mixed>  $plan
     * @return array<int, array<string, mixed>>
     */
    public function execute(User $user, array $plan): array
    {
        $requests = $plan['requests'] ?? [];
        if (! is_array($requests) || $requests === []) {
            return [];
        }

        $results = [];
        foreach (array_slice(array_values($requests), 0, self::MAX_SUB_REQUESTS) as $request) {
            if (! is_array($request)) {
                continue;
            }

            // Each part is authorised on its own capability. This is the gate
            // that makes a compound turn no broader than a single one.
            if (! is_string($request['capability'] ?? null)
                || ! $this->policy->allows($user, $request['capability'])) {
                $results[] = $this->result(
                    ($request['vague'] ?? false) === true ? 'unsupported' : 'forbidden',
                    $request['intent'] ?? null,
                    $request['capability'] ?? null,
                    $request,
                );
                continue;
            }

            $dispatched = $this->router->dispatch($user, $request);

            // Carry the subject so the merged reply can label each part. This
            // is a sibling key: status, answer and explanation_data are exactly
            // what the router returned.
            $dispatched['sub_request'] = $this->describe($request);

            $results[] = $dispatched;
        }

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