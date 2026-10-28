<?php

namespace App\Services;

use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IncidentLifecycleService
{
    private const TRANSITIONS = [
        'reported' => ['under_review', 'awaiting_reporter', 'declined'],
        'under_review' => [
            'awaiting_reporter',
            'declined',
            'processing_repair',
            'processing_disposal',
            'under_investigation',
            'resolved',
        ],
        'awaiting_reporter' => ['reported', 'declined'],
        'processing_repair' => ['processing_disposal', 'resolved'],
        'processing_disposal' => ['resolved'],
        'under_investigation' => ['resolved'],
        'declined' => [],
        'resolved' => [],
    ];

    public function transition(
        MaintenanceRecord $record,
        string $toStatus,
        int $actorId,
        ?string $details = null,
        array $metadata = [],
    ): MaintenanceRecord {
        return DB::transaction(function () use ($record, $toStatus, $actorId, $details, $metadata): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());
            $fromStatus = $record->incident_status;

            if (! $record->isIncidentReport() || ! in_array($toStatus, MaintenanceRecord::INCIDENT_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'This incident transition is not valid.']);
            }

            if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This incident cannot transition to the requested status.']);
            }

            if (
                in_array($toStatus, ['awaiting_reporter', 'declined'], true)
                && $record->events()->where('event_type', 'incident_handover_confirmed')->exists()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'This report cannot be declined or returned for information after affected units have been handed over.',
                ]);
            }

            $this->validateTransitionRequirements($record, $toStatus, $details, $metadata);
            $legacyStatus = match ($toStatus) {
                'reported' => 'reported',
                'declined' => 'cancelled',
                'resolved' => 'completed',
                default => 'in_progress',
            };
            $completedAt = in_array($toStatus, ['declined', 'resolved'], true) ? now() : null;

            $record->forceFill([
                'incident_status' => $toStatus,
                'incident_outcome' => $toStatus === 'resolved' ? $metadata['outcome'] : $record->incident_outcome,
                'status' => $legacyStatus,
                'completed_at' => $completedAt,
            ])->save();

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => $toStatus === 'reported' ? 'reporter_response_received' : 'status_changed',
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'details' => $details,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            return $record->fresh();
        });
    }

    public function recordSubmission(
        MaintenanceRecord $record,
        int $actorId,
        ?int $assignmentTransactionId = null,
    ): MaintenanceRecord {
        if (! in_array($record->diagnosis, MaintenanceRecord::INCIDENT_TYPES, true)) {
            throw new InvalidArgumentException('The maintenance record does not have a supported incident type.');
        }

        return DB::transaction(function () use ($record, $actorId, $assignmentTransactionId): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());

            if ($record->incident_status !== null || $record->status !== 'reported' || (int) $record->reported_by !== $actorId) {
                throw ValidationException::withMessages(['report' => 'This incident report has already entered its lifecycle.']);
            }

            $record->forceFill([
                'incident_status' => 'reported',
                'assignment_transaction_id' => $assignmentTransactionId,
            ])->save();

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => 'report_submitted',
                'from_status' => null,
                'to_status' => 'reported',
                'details' => 'Incident report submitted.',
                'metadata' => [
                    'affected_quantity' => $record->quantity,
                    'inventory_id' => (int) $record->inventory_id,
                    'assignment_transaction_id' => $assignmentTransactionId,
                ],
                'created_at' => $record->created_at ?? now(),
            ]);

            return $record->fresh();
        });
    }

    public function cancelByReporter(MaintenanceRecord $record, int $reporterId): MaintenanceRecord
    {
        return DB::transaction(function () use ($record, $reporterId): MaintenanceRecord {
            $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->getKey());

            if (
                ! $record->isIncidentReport()
                || (int) $record->reported_by !== $reporterId
            ) {
                abort(404);
            }

            if ($record->incident_status !== 'reported') {
                throw ValidationException::withMessages([
                    'report' => 'Only reports waiting for custodian review can be cancelled.',
                ]);
            }

            $record->forceFill([
                'incident_status' => 'cancelled',
                'status' => 'cancelled',
                'completed_at' => now(),
            ])->save();

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $reporterId,
                'event_type' => 'report_cancelled',
                'from_status' => 'reported',
                'to_status' => 'cancelled',
                'details' => 'Blotter report cancelled by the reporter.',
            ]);

            return $record->fresh();
        });
    }

    public function currentAssignedQuantity(int $inventoryId, int $reporterId, ?int $transactionId = null): int
    {
        if ($transactionId !== null) {
            return (int) Transaction::query()
                ->whereKey($transactionId)
                ->where('item_id', $inventoryId)
                ->where('user_id', $reporterId)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->value('quantity');
        }

        $transactionQuantity = (int) Transaction::query()
            ->where('item_id', $inventoryId)
            ->where('user_id', $reporterId)
            ->where('status', 'assigned')
            ->where('quantity', '>', 0)
            ->sum('quantity');

        if ($transactionQuantity > 0) {
            return $transactionQuantity;
        }

        $approvedAssignmentQuantity = (int) AssignmentRequest::query()
            ->where('item_id', $inventoryId)
            ->where('target_user_id', $reporterId)
            ->where('status', 'approved')
            ->sum('quantity');

        if ($approvedAssignmentQuantity > 0) {
            return $approvedAssignmentQuantity;
        }

        $inventory = Inventory::query()->whereKey($inventoryId)->first();

        return $inventory && (int) $inventory->assigned_to_user_id === $reporterId
            ? (int) $inventory->quantity
            : 0;
    }

    public function assertAffectedQuantity(
        int $inventoryId,
        int $reporterId,
        int $quantity,
        ?int $transactionId = null,
    ): void {
        $assignedQuantity = $this->currentAssignedQuantity($inventoryId, $reporterId, $transactionId);

        if ($quantity < 1 || $quantity > $assignedQuantity) {
            throw ValidationException::withMessages([
                'quantity' => "Affected quantity must be between 1 and the current assigned quantity ({$assignedQuantity}).",
            ]);
        }
    }

    private function validateTransitionRequirements(
        MaintenanceRecord $record,
        string $toStatus,
        ?string $details,
        array $metadata,
    ): void {
        if (in_array($toStatus, ['awaiting_reporter', 'declined'], true) && blank($details)) {
            throw ValidationException::withMessages(['details' => 'A reason is required for this decision.']);
        }

        if ($toStatus === 'processing_repair') {
            $this->assertType($record, ['damaged', 'needs_repair']);
            if (blank($metadata['inspection_findings'] ?? null) || ($metadata['handover_confirmed'] ?? false) !== true) {
                throw ValidationException::withMessages(['inspection_findings' => 'Record inspection findings and confirm handover of the affected units before repair processing.']);
            }
        }

        if ($toStatus === 'processing_disposal') {
            $this->assertType($record, ['damaged', 'needs_repair']);
            if (
                ($metadata['repairability'] ?? null) !== 'not_repairable'
                || blank($metadata['inspection_findings'] ?? null)
                || ($metadata['handover_confirmed'] ?? false) !== true
            ) {
                throw ValidationException::withMessages(['inspection_findings' => 'Inspection findings, confirmed handover, and confirmation that the units are not repairable are required.']);
            }
        }

        if ($toStatus === 'under_investigation') {
            $this->assertType($record, ['lost', 'stolen']);
        }

        if ($toStatus === 'resolved') {
            $outcome = $metadata['outcome'] ?? null;
            $allowedOutcomes = in_array($record->diagnosis, ['lost', 'stolen'], true)
                ? ['recovered', 'confirmed_lost_stolen', 'unsubstantiated']
                : ['repaired', 'disposed', 'unsubstantiated', 'no_change'];

            if (! in_array($outcome, $allowedOutcomes, true)) {
                throw ValidationException::withMessages(['outcome' => 'A valid final incident outcome is required.']);
            }
            if (
                in_array($record->diagnosis, ['lost', 'stolen'], true)
                && $record->incident_status !== 'under_investigation'
            ) {
                throw ValidationException::withMessages(['status' => 'A Lost or Stolen report must be under investigation before it can be resolved.']);
            }
        }
    }

    private function assertType(MaintenanceRecord $record, array $allowedTypes): void
    {
        if (! in_array($record->diagnosis, $allowedTypes, true)) {
            throw ValidationException::withMessages(['status' => 'This workflow is not valid for the reported incident type.']);
        }
    }
}
