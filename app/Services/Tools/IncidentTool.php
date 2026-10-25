<?php

namespace App\Services\Tools;

use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Services\AiCapabilityPolicy;
use App\Services\Tools\Concerns\InteractsWithInventory;

/**
 * Read-only access to open incident reports and active investigations.
 */
class IncidentTool implements ToolContract
{
    use InteractsWithInventory;

    private const OPEN_REPORT_STATUSES = [
        'reported',
        'under_review',
        'awaiting_reporter',
        'processing_repair',
        'processing_disposal',
    ];

    public function handles(string $capability): bool
    {
        return $capability === AiCapabilityPolicy::VIEW_INCIDENT_REPORTS;
    }

    public function fetch(User $user, array $request): array
    {
        $intent = $request['request_type'] ?? $request['intent'] ?? null;
        if (! in_array($intent, ['incident_reports', 'investigations'], true)) {
            return $this->result('unsupported', is_string($intent) ? $intent : null, $request['capability'] ?? null);
        }

        $query = MaintenanceRecord::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'reportedBy:id,first_name,last_name,username',
            ])
            ->whereIn('diagnosis', MaintenanceRecord::INCIDENT_TYPES)
            ->whereIn(
                'incident_status',
                $intent === 'investigations' ? ['under_investigation'] : self::OPEN_REPORT_STATUSES,
            );

        if (($request['item_name'] ?? null) !== null
            || isset($request['inventory_id'])
            || ($request['serial_number'] ?? null) !== null) {
            $items = $this->inventoryRecordsForRequest($request, true);
            if ($this->hasNoMatchingItem($request, $items)) {
                return $this->result('not_found', $intent, $request['capability']);
            }
            if ($this->needsRecordClarification($request, $items)) {
                return $this->recordClarification($request, $items);
            }
            $query->whereIn('inventory_id', $items->pluck('item_id'));
        }

        if (($request['response_type'] ?? null) === 'count') {
            return $this->result('success', $intent, $request['capability'], [
                ($intent === 'investigations' ? 'investigation_count' : 'incident_report_count') => $query->count(),
            ]);
        }

        $records = $query->latest('created_at')->latest('id')->limit(25)->get()
            ->map(fn (MaintenanceRecord $record): array => [
                'report_id' => (int) $record->id,
                'item_name' => $record->inventory?->item_name ?? 'Unknown item',
                'inventory_no' => $record->inventory?->inventory_item_no,
                'type' => $record->diagnosis,
                'status' => $record->incident_status,
                'quantity' => (int) $record->quantity,
                'unit' => $record->inventory?->unit ?? 'units',
                'reporter' => $record->reportedBy
                    ? ($record->reportedBy->full_name ?: $record->reportedBy->username)
                    : 'Unknown reporter',
                'details' => $record->issue_description,
                'submitted_at' => $record->created_at?->toDateString(),
            ])->all();

        return $this->result('success', $intent, $request['capability'], [
            $intent === 'investigations' ? 'investigations' : 'incident_reports' => $records,
            'showing_latest' => count($records) === 25,
        ]);
    }
}
