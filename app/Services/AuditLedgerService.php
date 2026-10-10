<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds stock-movement audit ledger entries with human-readable details.
 *
 * Shared by the custodian Transactions ledger and the school-head audit view
 * so both render byte-identical details text: the frontend groups adjacent
 * identical rows, and that grouping is only honest when the text is produced
 * by one builder instead of two copies that can drift.
 */
class AuditLedgerService
{
    /**
     * @return \Illuminate\Support\Collection<int, StockMovement>
     */
    public function latest(int $limit = 25): Collection
    {
        return StockMovement::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'user:id,first_name,last_name,username',
                'assignmentReturn.recipient:id,role_id,first_name,last_name,username',
                'assignmentReturn.recipient.role:role_id,role_name',
                'assignmentReturn.receivedBy:id,role_id,first_name,last_name,username',
                'assignmentReturn.receivedBy.role:role_id,role_name',
                'assignmentRequest.user:id,role_id,first_name,last_name,username',
                'assignmentRequest.user.role:role_id,role_name',
                'assignmentRequest.targetUser:id,role_id,first_name,last_name,username',
                'assignmentRequest.targetUser.role:role_id,role_name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(fn (StockMovement $movement): StockMovement => $this->describe($movement));
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 20)
    {
        return StockMovement::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'user:id,first_name,last_name,username',
                'assignmentReturn.recipient:id,role_id,first_name,last_name,username',
                'assignmentReturn.recipient.role:role_id,role_name',
                'assignmentReturn.receivedBy:id,role_id,first_name,last_name,username',
                'assignmentReturn.receivedBy.role:role_id,role_name',
                'assignmentRequest.user:id,role_id,first_name,last_name,username',
                'assignmentRequest.user.role:role_id,role_name',
                'assignmentRequest.targetUser:id,role_id,first_name,last_name,username',
                'assignmentRequest.targetUser.role:role_id,role_name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'movements_page')
            ->through(fn (StockMovement $movement): StockMovement => $this->describe($movement));
    }

    public function describe(StockMovement $movement): StockMovement
    {
        $details = $movement->notes;

        if (in_array($movement->movement_type, ['returned', 'return'], true) && $movement->assignmentReturn) {
            $returned = $movement->assignmentReturn;
            $recipient = $this->formatPerson($returned->recipient, 'Unknown recipient');
            $receivedBy = $this->formatPerson($returned->receivedBy, 'Unknown user');
            $details = "Returned {$movement->quantity} {$movement->inventory?->unit} from {$recipient} to stockroom. Received by {$receivedBy}.";

            if ($returned->notes) {
                $details .= " Note: {$returned->notes}";
            }
        } elseif ($movement->movement_type === 'assignment' && $movement->assignmentRequest) {
            $assignment = $movement->assignmentRequest;
            $recipient = $this->formatPerson($assignment->targetUser, 'Unknown recipient');
            $requester = $this->formatPerson($assignment->user, 'Unknown requester');
            $details = "Assigned to {$recipient}; requested by {$requester}.";
        }

        $movement->setAttribute('display_details', $details ?: 'No additional details recorded.');

        return $movement;
    }

    private function formatPerson(?User $user, string $fallback): string
    {
        if (! $user) {
            return $fallback;
        }

        $name = $user->full_name ?: $user->username;
        $role = $user->role?->role_name;

        return $role ? "{$name} ({$role})" : $name;
    }
}
