<?php

namespace App\Services\Tools;

use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Maintenance records. Body relocated from InventoryAnswerService unchanged,
 * including the `inventoryRecordsForRequest($request, true)` disposed-scoping
 * exception and the limit(25) cap.
 */
class MaintenanceTool implements ToolContract
{
    use InteractsWithInventory;

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_MAINTENANCE;
    }

    public function fetch(User $user, array $request): array
    {
        return $this->maintenanceRecords($request);
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
}