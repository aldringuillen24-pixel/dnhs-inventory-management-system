<?php

namespace App\Services\Tools;

use App\Models\AssignmentRequest;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Request facts: the caller's own requests and the pending queue.
 *
 * The three persisted `waiting for ...` status strings are compared literally
 * and must not be reworded, reordered or consolidated.
 */
class RequestTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return in_array($capability, [
            AiCapabilityPolicy::VIEW_OWN_REQUESTS,
            AiCapabilityPolicy::VIEW_PENDING_REQUESTS,
        ], true);
    }

    public function fetch(User $user, array $request): array
    {
        return match ($request['capability'] ?? null) {
            AiCapabilityPolicy::VIEW_OWN_REQUESTS => $this->ownRequests($user, $request),
            AiCapabilityPolicy::VIEW_PENDING_REQUESTS => $this->pendingRequests($request),
            default => $this->result('unsupported', $request['intent'] ?? null, $request['capability'] ?? null),
        };
    }

    protected function ownRequests(User $user, array $request): array
    {
        if (($request['query'] ?? null) === 'pending_count') {
            $count = AssignmentRequest::query()->where('user_id', $user->id)
                ->whereIn('status', [
                    'waiting for approval',
                    'waiting for transfer approval',
                    'waiting for custodian approval',
                    // An unmet request is still open on the end user's side, so
                    // it belongs in their pending count.
                    AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
                ])
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
            AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
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
}