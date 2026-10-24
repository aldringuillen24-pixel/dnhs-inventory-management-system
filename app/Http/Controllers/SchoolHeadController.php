<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\ArrivalInspection;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryItemLog;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Services\AuditLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class SchoolHeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inventory = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->get();

        $lowStockCount = $inventory
            ->where('status', 'available')
            ->groupBy(fn ($item) => $item->item_name . '|' . $item->category_id)
            ->filter(fn ($items) => $items->sum('quantity') <= 3)
            ->count();

        $today = now()->startOfDay();
        $approachingLifespanCount = $inventory
            ->filter(fn ($item) => $item->expected_end_date
                && $item->expected_end_date->gt($today)
                && $item->expected_end_date->lte($today->copy()->addYear()))
            ->sum('quantity');
        $expiredLifespanCount = $inventory
            ->filter(fn ($item) => $item->expected_end_date && $item->expected_end_date->lte($today))
            ->sum('quantity');

        $statusData = $inventory
            ->groupBy('status')
            ->map(fn ($items, $status) => [
                'label' => ucfirst(str_replace('_', ' ', (string) $status)),
                'quantity' => (int) $items->sum('quantity'),
            ])
            ->values();

        $conditionData = collect([
            ['label' => 'Damaged', 'value' => (int) $inventory->where('status', 'damaged')->sum('quantity')],
            ['label' => 'Under Repair', 'value' => (int) $inventory->whereIn('status', ['under_repair', 'under_maintenance'])->sum('quantity')],
            ['label' => 'Lost', 'value' => (int) $inventory->where('status', 'lost')->sum('quantity')],
            ['label' => 'Disposal Review', 'value' => (int) $inventory->whereIn('status', ['ready_to_dispose', 'disposal_review'])->sum('quantity')],
        ]);

        $custodianIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_name', 'Property Custodian'))
            ->pluck('id');
        $pendingRequests = AssignmentRequest::query()
            ->whereIn('status', [
                'waiting for approval',
                'waiting for transfer approval',
                'waiting for custodian approval',
                'transfer draft',
                AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
            ])
            ->get(['status', 'target_user_id']);
        $pendingRequestData = [
            [
                'label' => 'Item Requests',
                'value' => $pendingRequests->filter(fn ($request) => ($request->status === 'waiting for approval' || $request->status === AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT) && $custodianIds->contains($request->target_user_id))->count(),
            ],
            [
                'label' => 'Transfer Requests',
                'value' => $pendingRequests->filter(fn ($request) => in_array($request->status, ['waiting for transfer approval', 'transfer draft'], true)
                    || (in_array($request->status, ['waiting for approval', 'waiting for custodian approval'], true) && ! $custodianIds->contains($request->target_user_id)))->count(),
            ],
            [
                'label' => 'Return Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for custodian approval'
                    && $custodianIds->contains($request->target_user_id))->count(),
            ],
        ];

        $categoryData = Category::query()
            ->with(['inventoryItems' => fn ($query) => $query->where('status', '!=', 'disposed')])
            ->get()
            ->map(fn ($category) => [
                'label' => $category->category_name,
                'quantity' => (int) $category->inventoryItems->sum('quantity'),
            ])
            ->filter(fn ($category) => $category['quantity'] > 0)
            ->sortByDesc('quantity')
            ->values();

        $recentActivity = StockMovement::query()
            ->with([
                'inventory:item_id,item_name',
                'user:id,first_name,last_name',
            ])
            ->latest()
            ->limit(8)
            ->get();

        $recentActivity = $recentActivity->map(function ($movement) {
            $type = match ($movement->movement_type) {
                'stock_in' => 'Stock In',
                'stock_out' => 'Stock Out',
                'assignment' => 'Assignment',
                'transfer' => 'Transfer',
                'returned', 'return' => 'Return',
                default => ucwords(str_replace('_', ' ', $movement->movement_type)),
            };

            return [
                'date' => $movement->created_at,
                'type' => $type,
                'item' => $movement->inventory?->item_name ?? 'Inventory item',
                'quantity' => abs((int) $movement->quantity),
                'user' => $movement->user?->full_name ?? 'System',
                'remarks' => $movement->notes ?: 'Inventory record updated',
            ];
        });

        return response()->json([
            'title' => 'School Head Dashboard',
            'metrics' => [
                'totalUnits' => (int) $inventory->sum('quantity'),
                'availableUnits' => (int) $inventory->where('status', 'available')->sum('quantity'),
                'assignedUnits' => (int) $inventory->where('status', 'assigned')->sum('quantity'),
                'totalValue' => (float) $inventory->sum(fn ($item) => $item->quantity * $item->unit_cost),
                'lowStockItems' => $lowStockCount,
                'attentionItems' => (int) $inventory->whereIn('status', ['under_inspection', 'under_repair', 'ready_to_dispose'])->sum('quantity'),
                'approachingLifespan' => (int) $approachingLifespanCount,
                'expiredLifespan' => (int) $expiredLifespanCount,
                'pendingRequests' => $pendingRequests->count(),
            ],
            'categoryData' => $categoryData,
            'statusData' => $statusData,
            'conditionData' => $conditionData,
            'pendingRequestData' => $pendingRequestData,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function inventoryOverview(Request $request): JsonResponse
    {
        $inventory = Inventory::query()
            ->with(['transactions' => fn ($query) => $query->where('status', 'assigned')->with('assignmentReturns:id,transaction_id,quantity')])
            ->where('status', '!=', 'disposed')
            ->get();

        // Custody-based statuses read the assignment transaction: a fully issued
        // record keeps 0 in inventory.quantity, so summing the column reports 0.
        // Under repair joins the list because repair can be initiated while the
        // units are still issued out. Legacy inspection rows remain readable
        // until the cleanup migration runs.
        $effective = fn ($item): int => in_array($item->status, ['assigned', 'under_inspection', 'under_repair'], true)
            ? $item->effectiveQuantity()
            : (int) $item->quantity;

        $categories = Category::query()
            ->with(['inventoryItems' => fn ($query) => $query
                ->where('status', '!=', 'disposed')
                ->with(['transactions' => fn ($query) => $query->where('status', 'assigned')->with('assignmentReturns:id,transaction_id,quantity')]),
            ])
            ->get()
            ->map(function ($category) use ($effective) {
                $items = $category->inventoryItems;

                return [
                    'category_id' => $category->category_id,
                    'name' => $category->category_name,
                    'total' => (int) $items->sum($effective),
                    'available' => (int) $items->where('status', 'available')->sum($effective),
                    'assigned' => (int) $items->where('status', 'assigned')->sum($effective),
                    'value' => (float) $items->sum(fn ($item) => $effective($item) * $item->unit_cost),
                ];
            })
            ->filter(fn ($category) => $category['total'] > 0)
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'title' => 'Inventory Overview',
            'categories' => $categories,
            'exportCategories' => Category::query()
                ->orderBy('category_name')
                ->get(['category_id', 'category_name'])
                ->map(fn (Category $category): array => [
                    'category_id' => $category->category_id,
                    'category_name' => $category->category_name,
                ]),
            'metrics' => [
                'units' => (int) $inventory->sum($effective),
                'available' => (int) $inventory->where('status', 'available')->sum($effective),
                'assigned' => (int) $inventory->where('status', 'assigned')->sum($effective),
                'lowStock' => $categories->where('available', '<=', 3)->count(),
                'value' => (float) $inventory->sum(fn ($item) => $effective($item) * $item->unit_cost),
            ],
            'monitorRows' => $this->monitorRows($effective),
            'incidentReports' => $this->incidentMonitorRows(false),
            'investigations' => $this->incidentMonitorRows(true),
        ]);
    }

    /**
     * Read-only open incident records grouped like the custodian workbench.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function incidentMonitorRows(bool $investigations): \Illuminate\Support\Collection
    {
        $query = MaintenanceRecord::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'reportedBy:id,first_name,last_name,username',
            ])
            ->whereIn('diagnosis', MaintenanceRecord::INCIDENT_TYPES)
            ->whereIn('incident_status', [
                'reported',
                'under_review',
                'awaiting_reporter',
                'processing_repair',
                'processing_disposal',
                'under_investigation',
            ]);

        if ($investigations) {
            $query->where('incident_status', 'under_investigation');
        } else {
            $query->where('incident_status', '!=', 'under_investigation');
        }

        return $query
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(fn (MaintenanceRecord $record): array => [
                'id' => $record->id,
                'item_name' => $record->inventory?->item_name ?? 'Unknown item',
                'inventory_item_no' => $record->inventory?->inventory_item_no,
                'type' => $record->diagnosis,
                'status' => $record->incident_status,
                'quantity' => (int) $record->quantity,
                'unit' => $record->inventory?->unit,
                'reporter' => $record->reportedBy
                    ? ($record->reportedBy->full_name ?: $record->reportedBy->username)
                    : 'Unknown reporter',
                'details' => $record->issue_description,
                'submitted_at' => $record->created_at,
            ])
            ->values();
    }

    /**
     * Item-level monitor rows: every state the custodian workbench shows,
     * shaped for read-only display.
     *
     * Holder names resolve through the same assignment link the custodian
     * views use; rows without one report no holder rather than inventing one.
     * Quantities honor the issued-out rule (assigned units live in
     * transactions), so the monitor can never disagree with the workbench.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function monitorRows(callable $effective): \Illuminate\Support\Collection
    {
        return Inventory::query()
            ->with([
                'category:category_id,category_name',
                'assignedTo:id,first_name,last_name,username',
                'transactions' => fn ($query) => $query->where('status', 'assigned')->with('assignmentReturns:id,transaction_id,quantity'),
            ])
            ->orderBy('item_name')
            ->get()
            ->map(function ($item) use ($effective) {
                $holder = $item->assignedTo
                    ? (trim($item->assignedTo->first_name.' '.$item->assignedTo->last_name) ?: $item->assignedTo->username)
                    : null;

                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'inventory_item_no' => $item->inventory_item_no,
                    'category' => $item->category?->category_name ?? 'Uncategorized',
                    'unit' => $item->unit,
                    'status' => $item->status,
                    'status_label' => ucwords(str_replace('_', ' ', (string) $item->status)),
                    'quantity' => $effective($item),
                    'holder_name' => $holder,
                    'expected_end_date' => $item->expected_end_date?->format('Y-m-d'),
                ];
            })
            ->values();
    }

    public function reports(Request $request): JsonResponse
    {
        $inventory = Inventory::query()->where('status', '!=', 'disposed')->get();
        $statusData = $inventory->groupBy('status')->map(fn ($items, $status) => [
            'label' => ucfirst(str_replace('_', ' ', (string) $status)),
            'quantity' => (int) $items->sum('quantity'),
        ])->values();
        $categoryData = Category::with(['inventoryItems' => fn ($query) => $query->where('status', '!=', 'disposed')])
            ->get()
            ->map(fn ($category) => [
                'label' => $category->category_name,
                'quantity' => (int) $category->inventoryItems->sum('quantity'),
            ])
            ->filter(fn ($category) => $category['quantity'] > 0)
            ->sortByDesc('quantity')
            ->values();
        $recentTransactions = Transaction::with(['item:item_id,item_name', 'user:id,first_name,last_name'])
            ->latest('transaction_date')->latest('id')->limit(10)->get();

        return response()->json([
            'title' => 'Reports',
            'metrics' => [
                'totalUnits' => (int) $inventory->sum('quantity'),
                'availableUnits' => (int) $inventory->where('status', 'available')->sum('quantity'),
                'assignedUnits' => (int) $inventory->where('status', 'assigned')->sum('quantity'),
                'totalValue' => (float) $inventory->sum(fn ($item) => $item->quantity * $item->unit_cost),
            ],
            'categoryData' => $categoryData,
            'statusData' => $statusData,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = UserAuditLog::query()
            ->with(['actor', 'targetUser'])
            ->orderByDesc('created_at')
            ->paginate(10);

        // Item-movement ledger, same builder as the custodian view so both
        // pages describe each movement identically. Returned whole (capped)
        // rather than paginated: pagination slices records before grouping and
        // would split one batch across pages. The frontend paginates the
        // groups instead, so a ×15 batch is never cut in two. Read-only by
        // design: the school-head role has no movement endpoints to act through.
        $movements = app(AuditLedgerService::class)->latest(100);
        $itemLogs = InventoryItemLog::query()
            ->with('user:id,first_name,last_name,username')
            ->whereIn('action', [
                InventoryItemLog::ACTION_UPDATED,
                InventoryItemLog::ACTION_DELETED,
            ])
            ->where(function ($query): void {
                $query->whereNull('reason')
                    ->orWhere('reason', 'not like', 'Isolated incident #%');
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get();
        $incidentEvents = MaintenanceRecordEvent::query()
            ->with([
                'actor:id,first_name,last_name,username',
                'maintenanceRecord.inventory:item_id,item_name,inventory_item_no',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (MaintenanceRecordEvent $event): array => [
                'id' => "incident-{$event->id}",
                'occurred_at' => $event->created_at,
                'type' => 'Incident / Maintenance',
                'action' => $event->event_type,
                'item_name' => $event->maintenanceRecord?->inventory?->item_name ?? 'Incident report',
                'reference' => $event->maintenanceRecord?->inventory?->inventory_item_no,
                'actor' => $event->actor,
                'details' => $event->details,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
            ]);

        $requestDecisions = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'item:item_id,item_name,inventory_item_no',
            ])
            ->whereNotNull('responded_at')
            ->whereNotIn('status', [
                'waiting for approval',
                'waiting for transfer approval',
                'waiting for custodian approval',
                'transfer draft',
                AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
                'draft',
            ])
            ->latest('responded_at')
            ->limit(100)
            ->get()
            ->map(fn (AssignmentRequest $assignment): array => [
                'id' => "request-{$assignment->id}",
                'occurred_at' => $assignment->responded_at,
                'type' => 'Request Decision',
                'action' => $assignment->status,
                'item_name' => $assignment->item?->item_name ?? $assignment->requested_item_name ?? 'Item request',
                'reference' => $assignment->item?->inventory_item_no,
                'actor' => null,
                'details' => trim(implode(' · ', array_filter([
                    'Requested by '.($assignment->user?->full_name ?: $assignment->user?->username ?? 'Unknown user'),
                    $assignment->targetUser
                        ? 'For '.($assignment->targetUser->full_name ?: $assignment->targetUser->username)
                        : null,
                    $assignment->notes,
                ]))),
                'quantity' => (int) $assignment->quantity,
            ]);

        $arrivalEvents = ArrivalInspection::query()
            ->with([
                'creator:id,first_name,last_name,username',
                'inspector:id,first_name,last_name,username',
                'completer:id,first_name,last_name,username',
                'items:id,arrival_inspection_id,description,quantity,unit',
            ])
            ->whereIn('status', ['inspected', 'approved', 'returned', 'posted'])
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->flatMap(function (ArrivalInspection $inspection): array {
                $events = [[
                    'id' => "arrival-created-{$inspection->id}",
                    'occurred_at' => $inspection->created_at,
                    'type' => 'Arrival Inspection',
                    'action' => 'submitted',
                    'item_name' => $inspection->items->pluck('description')->filter()->take(3)->implode(', ') ?: 'Arrival inspection',
                    'reference' => $inspection->iar_number,
                    'actor' => $inspection->creator,
                    'details' => $inspection->supplier_name,
                ]];

                if ($inspection->inspected_at) {
                    $events[] = [
                        'id' => "arrival-inspected-{$inspection->id}",
                        'occurred_at' => $inspection->inspected_at,
                        'type' => 'Arrival Inspection',
                        'action' => 'inspected',
                        'item_name' => $inspection->items->pluck('description')->filter()->take(3)->implode(', ') ?: 'Arrival inspection',
                        'reference' => $inspection->iar_number,
                        'actor' => $inspection->inspector,
                        'details' => $inspection->acceptance_remarks,
                    ];
                }

                if ($inspection->completed_at) {
                    $events[] = [
                        'id' => "arrival-approved-{$inspection->id}",
                        'occurred_at' => $inspection->completed_at,
                        'type' => 'Arrival Inspection',
                        'action' => 'approved',
                        'item_name' => $inspection->items->pluck('description')->filter()->take(3)->implode(', ') ?: 'Arrival inspection',
                        'reference' => $inspection->iar_number,
                        'actor' => $inspection->completer,
                        'details' => $inspection->custodian_signature_name,
                    ];
                }

                if ($inspection->custodian_returned_at) {
                    $events[] = [
                        'id' => "arrival-returned-{$inspection->id}",
                        'occurred_at' => $inspection->custodian_returned_at,
                        'type' => 'Arrival Inspection',
                        'action' => 'returned for correction',
                        'item_name' => $inspection->items->pluck('description')->filter()->take(3)->implode(', ') ?: 'Arrival inspection',
                        'reference' => $inspection->iar_number,
                        'actor' => null,
                        'details' => $inspection->custodian_return_reason,
                    ];
                }

                if ($inspection->posted_at) {
                    $events[] = [
                        'id' => "arrival-posted-{$inspection->id}",
                        'occurred_at' => $inspection->posted_at,
                        'type' => 'Arrival Inspection',
                        'action' => 'posted to inventory',
                        'item_name' => $inspection->items->pluck('description')->filter()->take(3)->implode(', ') ?: 'Arrival inspection',
                        'reference' => $inspection->iar_number,
                        'actor' => null,
                        'details' => $inspection->supplier_name,
                    ];
                }

                return $events;
            });
        $arrivalEvents = $arrivalEvents
            ->sortByDesc('occurred_at')
            ->take(100)
            ->values();

        return response()->json([
            'title' => 'Audit Logs',
            'logs' => $logs,
            'movements' => $movements,
            'itemLogs' => $itemLogs,
            'incidentLogs' => $incidentEvents,
            'requestLogs' => $requestDecisions,
            'arrivalLogs' => $arrivalEvents,
        ]);
    }

    public function previewAuditLogsExport(Request $request)
    {
        $viewData = $this->auditLogsExportData($request);

        return response()->view('pdfs.school-head-logs-export', $viewData);
    }

    public function exportAuditLogsPdf(Request $request)
    {
        $viewData = $this->auditLogsExportData($request);
        $pdf = Pdf::loadView('pdfs.school-head-logs-export', $viewData);
        $pdf->setOption('defaultMediaType', 'print');
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('school_head_logs_'.now()->format('Y-m-d_His').'.pdf');
    }

    private function auditLogsExportData(Request $request): array
    {
        $validated = $request->validate([
            'log_types' => ['required', 'array', 'min:1'],
            'log_types.*' => [
                'required',
                'string',
                Rule::in(['accounts', 'movements', 'itemLogs', 'incidents', 'requests', 'arrivals']),
            ],
        ]);

        $labels = [
            'accounts' => 'Account Activity',
            'movements' => 'Item Movements',
            'itemLogs' => 'Item Logs',
            'incidents' => 'Incident & Maintenance',
            'requests' => 'Request Decisions',
            'arrivals' => 'Arrival Inspections',
        ];
        $selectedTypes = array_values(array_unique($validated['log_types']));
        $payload = $this->auditLogs($request)->getData(true);
        $rows = collect();

        if (in_array('accounts', $selectedTypes, true)) {
            $rows = $rows->concat(UserAuditLog::query()
                ->with(['actor:id,first_name,last_name,username', 'targetUser:id,first_name,last_name,username'])
                ->latest('created_at')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (UserAuditLog $log): array => [
                    'occurred_at' => $log->created_at?->format('Y-m-d H:i:s'),
                    'type' => $labels['accounts'],
                    'action' => $log->action,
                    'target_name' => $log->target_name
                        ?: ($log->targetUser?->full_name ?: $log->targetUser?->username),
                    'details' => is_array($log->details) ? json_encode($log->details) : $log->details,
                    'actor_name' => $log->actor_name
                        ?: ($log->actor?->full_name ?: $log->actor?->username),
                ]));
        }

        if (in_array('movements', $selectedTypes, true)) {
            $rows = $rows->concat(collect($payload['movements'] ?? [])->map(fn (array $log): array => [
                'occurred_at' => $log['created_at'] ?? null,
                'type' => $labels['movements'],
                'action' => str_replace('_', ' ', $log['movement_type'] ?? ''),
                'item_name' => $log['inventory']['item_name'] ?? 'Inventory item',
                'reference' => $log['inventory']['inventory_item_no'] ?? null,
                'details' => $log['display_details'] ?? $log['notes'] ?? null,
                'actor_name' => $this->auditExportActorName($log['user'] ?? null),
            ]));
        }

        if (in_array('itemLogs', $selectedTypes, true)) {
            $rows = $rows->concat(collect($payload['itemLogs'] ?? [])->map(function (array $log) use ($labels): array {
                $changes = collect($log['changes'] ?? [])->map(function ($value, string $field): string {
                    if (is_array($value) && (array_key_exists('old', $value) || array_key_exists('new', $value))) {
                        return str_replace('_', ' ', $field).': '.($value['old'] ?? '—').' → '.($value['new'] ?? '—');
                    }

                    return str_replace('_', ' ', $field).': '.(is_scalar($value) ? (string) $value : '—');
                })->implode('; ');

                return [
                    'occurred_at' => $log['created_at'] ?? null,
                    'type' => $labels['itemLogs'],
                    'action' => $log['action'] ?? null,
                    'item_name' => $log['item_name'] ?? 'Inventory item',
                    'reference' => $log['inventory_item_no'] ?? null,
                    'details' => implode(' · ', array_filter([$changes, $log['reason'] ?? null])),
                    'actor_name' => $this->auditExportActorName($log['user'] ?? null),
                ];
            }));
        }

        foreach (['incidents', 'requests', 'arrivals'] as $type) {
            if (! in_array($type, $selectedTypes, true)) {
                continue;
            }

            $rows = $rows->concat(collect($payload[match ($type) {
                'incidents' => 'incidentLogs',
                'requests' => 'requestLogs',
                'arrivals' => 'arrivalLogs',
            }] ?? [])->map(fn (array $log): array => [
                'occurred_at' => $log['occurred_at'] ?? null,
                'type' => $labels[$type],
                'action' => $log['action'] ?? null,
                'item_name' => $log['item_name'] ?? null,
                'reference' => $log['reference'] ?? null,
                'details' => trim(implode(' · ', array_filter([
                    isset($log['from_status']) || isset($log['to_status'])
                        ? (string) ($log['from_status'] ?? '—').' → '.(string) ($log['to_status'] ?? '—')
                        : null,
                    $log['details'] ?? null,
                    ! empty($log['quantity']) ? $log['quantity'].' units' : null,
                ]))),
                'actor_name' => $this->auditExportActorName($log['actor'] ?? null),
            ]));
        }

        return [
            'logs' => $rows->filter(fn (array $row): bool => ! empty($row['occurred_at']))
                ->sortByDesc('occurred_at')
                ->values(),
            'logTypes' => array_map(fn (string $type): string => $labels[$type], $selectedTypes),
            'generatedAt' => now()->format('F j, Y g:i A'),
        ];
    }

    private function auditExportActorName(?array $actor): string
    {
        if (! $actor) {
            return 'Not recorded';
        }

        $name = trim(($actor['first_name'] ?? '').' '.($actor['last_name'] ?? ''));

        return $name ?: ($actor['username'] ?? 'Not recorded');
    }

    public function updateProfile(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail(Auth::id());
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
        $user->building = $validated['building'] ?? null;
        $user->room = $validated['room'] ?? null;
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        return redirect()->route('schoolHead.profile')->with('success', 'Profile updated successfully.');
    }
}