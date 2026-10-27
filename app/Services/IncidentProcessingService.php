<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentProcessingService
{
    public function recordInspection(
        MaintenanceRecord $record,
        int $actorId,
        string $findings,
        string $repairability,
    ): MaintenanceRecordEvent {
        return DB::transaction(function () use ($record, $actorId, $findings, $repairability): MaintenanceRecordEvent {
            $record = $this->lockProcessableRecord($record->id);
            $this->assertRepairIncident($record);

            if (! in_array($repairability, ['repairable', 'not_repairable', 'pending'], true)) {
                throw ValidationException::withMessages(['repairability' => 'Choose repairable, not repairable, or pending.']);
            }

            if ($this->latestHandover($record)) {
                throw ValidationException::withMessages(['inspection' => 'Inspection cannot be changed after handover has been recorded.']);
            }

            return MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => 'physical_inspection_recorded',
                'from_status' => $record->incident_status,
                'to_status' => $record->incident_status,
                'details' => $findings,
                'metadata' => [
                    'repairability' => $repairability,
                    'affected_quantity' => (int) $record->quantity,
                ],
            ]);
        });
    }

    public function recordInvestigationAction(
        MaintenanceRecord $record,
        int $actorId,
        string $details,
        array $evidenceIds = [],
    ): MaintenanceRecordEvent {
        return DB::transaction(function () use ($record, $actorId, $details, $evidenceIds): MaintenanceRecordEvent {
            $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertActiveInvestigation($record);

            return MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => 'investigation_action_recorded',
                'from_status' => $record->incident_status,
                'to_status' => $record->incident_status,
                'details' => $details,
                'metadata' => [
                    'evidence_ids' => array_values(array_map('intval', $evidenceIds)),
                ],
            ]);
        });
    }

    /**
     * Record custody receipt and isolate exactly the incident quantity into a
     * separate inventory row, leaving all other stock and assignments intact.
     *
     * @return array{assignment_return_id: int, inventory: Inventory}
     */
    public function confirmHandover(
        MaintenanceRecord $record,
        int $actorId,
        string $details,
        InventoryOperationService $inventoryOperations,
    ): array {
        return DB::transaction(function () use ($record, $actorId, $details, $inventoryOperations): array {
            $record = $this->lockProcessableRecord($record->id);
            $this->assertRepairIncident($record);

            $inspection = $this->latestInspection($record);
            if (! $inspection || ! in_array($inspection->metadata['repairability'] ?? null, ['repairable', 'not_repairable'], true)) {
                throw ValidationException::withMessages(['inspection' => 'Record a complete physical inspection before confirming handover.']);
            }
            if ($this->latestHandover($record)) {
                throw ValidationException::withMessages(['handover' => 'Handover has already been recorded for this report.']);
            }

            $transactionQuery = Transaction::query()
                ->where('item_id', $record->inventory_id)
                ->where('user_id', $record->reported_by)
                ->where('status', 'assigned')
                ->where('quantity', '>=', (int) $record->quantity);

            if ($record->assignment_transaction_id) {
                $transactionQuery->whereKey($record->assignment_transaction_id);
            }

            $transaction = $transactionQuery->lockForUpdate()->first();
            if (! $transaction) {
                throw ValidationException::withMessages([
                    'handover' => 'The reporter no longer has an active assignment covering the affected quantity.',
                ]);
            }

            $assignmentReturn = $inventoryOperations->receiveAssignmentReturn(
                (int) $transaction->id,
                (int) $record->quantity,
                $actorId,
                $details,
            );
            if (is_string($assignmentReturn)) {
                throw ValidationException::withMessages(['handover' => $assignmentReturn]);
            }

            try {
                $inventory = $inventoryOperations->isolateIncidentQuantity(
                    (int) $record->inventory_id,
                    (int) $record->quantity,
                    $actorId,
                    (int) $record->id,
                    $details,
                );
            } catch (\DomainException $exception) {
                throw ValidationException::withMessages(['handover' => $exception->getMessage()]);
            }

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => 'incident_handover_confirmed',
                'from_status' => $record->incident_status,
                'to_status' => $record->incident_status,
                'details' => $details,
                'metadata' => [
                    'affected_quantity' => (int) $record->quantity,
                    'source_inventory_id' => (int) $record->inventory_id,
                    'assignment_transaction_id' => (int) $transaction->id,
                    'assignment_request_id' => $assignmentReturn->assignment_request_id,
                    'assignment_return_id' => $assignmentReturn->id,
                    'processing_inventory_id' => $inventory->item_id,
                    'repairability' => $inspection->metadata['repairability'],
                ],
            ]);

            return [
                'assignment_return_id' => (int) $assignmentReturn->id,
                'inventory' => $inventory,
            ];
        });
    }

    public function latestInspection(MaintenanceRecord $record): ?MaintenanceRecordEvent
    {
        return MaintenanceRecordEvent::query()
            ->where('maintenance_record_id', $record->id)
            ->where('event_type', 'physical_inspection_recorded')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function latestHandover(MaintenanceRecord $record): ?MaintenanceRecordEvent
    {
        return MaintenanceRecordEvent::query()
            ->where('maintenance_record_id', $record->id)
            ->where('event_type', 'incident_handover_confirmed')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function recordBlockedAction(MaintenanceRecord $record, int $actorId, string $details, string $stage): void
    {
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $record->id,
            'actor_id' => $actorId,
            'event_type' => 'incident_processing_blocked',
            'from_status' => $record->incident_status,
            'to_status' => $record->incident_status,
            'details' => $details,
            'metadata' => ['stage' => $stage],
        ]);
    }

    public function linkedReportForProcessingInventory(int $inventoryId): ?MaintenanceRecord
    {
        $events = MaintenanceRecordEvent::query()
            ->where('event_type', 'incident_handover_confirmed')
            ->whereJsonContains('metadata->processing_inventory_id', $inventoryId)
            ->latest('id')
            ->get();

        foreach ($events as $event) {
            $record = MaintenanceRecord::query()->find($event->maintenance_record_id);
            if ($record?->incident_status === 'processing_repair') {
                return $record;
            }
        }

        return null;
    }

    public function resolveRepairedInventory(
        MaintenanceRecord $record,
        int $inventoryId,
        int $actorId,
        string $repairNotes,
        ?float $maintenanceCost,
        IncidentLifecycleService $incidentLifecycle,
    ): void {
        if ($record->incident_status !== 'processing_repair') {
            return;
        }

        $repairEvent = MaintenanceRecordEvent::query()
            ->where('maintenance_record_id', $record->id)
            ->where('event_type', 'incident_repair_started')
            ->latest('id')
            ->first();

        $incidentLifecycle->transition(
            $record,
            'resolved',
            $actorId,
            $repairNotes,
            [
                'outcome' => 'repaired',
                'processing_inventory_id' => $inventoryId,
                'maintenance_record_id' => data_get($repairEvent?->metadata, 'maintenance_record_id'),
                'maintenance_cost' => $maintenanceCost,
                'repair_return_destination' => $record->repair_return_destination ?? 'custodian_available',
            ],
        );
    }

    public function resolveDisposedInventory(array $inventoryIds, int $actorId, ?string $notes): void
    {
        $inventoryIds = array_map('intval', $inventoryIds);
        if ($inventoryIds === []) {
            return;
        }

        $events = MaintenanceRecordEvent::query()
            ->where('event_type', 'incident_handover_confirmed')
            ->get()
            ->filter(fn (MaintenanceRecordEvent $event): bool => in_array(
                (int) ($event->metadata['processing_inventory_id'] ?? 0),
                $inventoryIds,
                true,
            ));

        foreach ($events->unique('maintenance_record_id') as $event) {
            $record = MaintenanceRecord::query()->find($event->maintenance_record_id);
            if (! $record || $record->incident_status !== 'processing_disposal') {
                continue;
            }

            $processingInventoryId = (int) ($event->metadata['processing_inventory_id'] ?? 0);
            $disposalMovement = StockMovement::query()
                ->where('inventory_id', $processingInventoryId)
                ->where('user_id', $actorId)
                ->where('movement_type', 'disposed')
                ->latest('id')
                ->first();

            if (! $disposalMovement) {
                throw new \LogicException('The completed incident disposal has no matching stock movement.');
            }

            app(IncidentLifecycleService::class)->transition(
                $record,
                'resolved',
                $actorId,
                $notes ?: 'The affected units completed the existing disposal process.',
                [
                    'outcome' => 'disposed',
                    'processing_inventory_id' => $processingInventoryId,
                    'disposal_movement_id' => (int) $disposalMovement->id,
                    'affected_quantity' => (int) $record->quantity,
                ],
            );
        }
    }

    private function lockProcessableRecord(int $recordId): MaintenanceRecord
    {
        $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($recordId);
        if (! $record->isIncidentReport() || $record->incident_status !== 'under_review') {
            throw ValidationException::withMessages(['status' => 'This report must be under custodian review before processing.']);
        }

        return $record;
    }

    private function assertRepairIncident(MaintenanceRecord $record): void
    {
        if (! in_array($record->diagnosis, ['damaged', 'needs_repair'], true)) {
            throw ValidationException::withMessages(['type' => 'Inspection and repair processing apply only to Damaged or Needs Repair reports.']);
        }
    }

    public function assertActiveInvestigation(MaintenanceRecord $record): void
    {
        if (
            ! $record->isIncidentReport()
            || ! in_array($record->diagnosis, ['lost', 'stolen'], true)
            || $record->incident_status !== 'under_investigation'
        ) {
            throw ValidationException::withMessages([
                'status' => 'This action requires an active Lost or Stolen investigation.',
            ]);
        }
    }
}
