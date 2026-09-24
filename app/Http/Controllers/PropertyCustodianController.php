<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PropertyCustodianController extends Controller
{
    public function onboarding()
    {
        $user = request()->user();

        if (!$user->temporary_password) {
            return redirect()->route('propertyCustodian.dashboard');
        }

        return view('pages.propertyCustodian.onboarding', ['title' => 'Complete Your Account Setup']);
    }

    public function onboardingPost(Request $request)
    {
        $user = $request->user();

        if (!$user->temporary_password) {
            return redirect()->route('propertyCustodian.dashboard');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->temporary_password = null;
        $user->save();

        return redirect()->route('propertyCustodian.dashboard')->with('success', 'Your account has been updated.');
    }

    public function dashboard()
    {
        $categoryData = Category::query()
            ->with(['inventoryItems' => fn ($query) => $query->where('status', '!=', 'disposed')])
            ->get()
            ->map(fn ($category) => [
                'label' => $category->category_name,
                'value' => (int) $category->inventoryItems->sum('quantity'),
            ])
            ->filter(fn ($category) => $category['value'] > 0)
            ->values();

        $requestStatusData = AssignmentRequest::query()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($request) => [
                'label' => ucfirst($request->status),
                'value' => (int) $request->total,
            ])
            ->values();

        $lowStockCount = Inventory::query()
            ->where('status', 'available')
            ->select('item_name')
            ->selectRaw('SUM(quantity) as available_quantity')
            ->groupBy('item_name')
            ->having('available_quantity', '<=', 3)
            ->get()
            ->count();
        $today = now()->startOfDay();
        $approachingLifespanCount = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->whereDate('expected_end_date', '>', $today)
            ->whereDate('expected_end_date', '<=', $today->copy()->addYear())
            ->sum('quantity');
        $expiredLifespanCount = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->whereDate('expected_end_date', '<=', $today)
            ->sum('quantity');

        $recentActivity = StockMovement::query()
            ->with([
                'inventory:item_id,item_name',
                'user:id,first_name,last_name',
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($movement) {
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
                    'quantity' => in_array($type, ['Stock Out', 'Assignment', 'Transfer'], true)
                        ? -abs((int) $movement->quantity)
                        : abs((int) $movement->quantity),
                    'user' => $movement->user?->full_name ?? 'System',
                    'remarks' => $movement->notes ?: 'Inventory record updated',
                ];
            });

        return view('pages.propertyCustodian.dashboard', [
            'title' => 'Property Custodian Dashboard',
            'metrics' => [
                'inventory' => Inventory::where('status', '!=', 'disposed')->sum('quantity'),
                'available' => Inventory::where('status', 'available')->sum('quantity'),
                'lowStock' => $lowStockCount,
                'pendingRequests' => AssignmentRequest::whereIn('status', ['waiting for approval', 'waiting for transfer approval'])->count(),
                'approachingLifespan' => (int) $approachingLifespanCount,
                'expiredLifespan' => (int) $expiredLifespanCount,
            ],
            'categoryData' => $categoryData,
            'requestStatusData' => $requestStatusData,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function reports(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        $inventory = Inventory::with('category:category_id,category_name')->get();
        $activeInventory = $inventory->where('status', '!=', 'disposed');

        $categoryData = $activeInventory
            ->groupBy('category_id')
            ->map(function ($items) {
                return [
                    'label' => $items->first()->category?->category_name ?? 'Uncategorized',
                    'quantity' => (int) $items->sum('quantity'),
                    'value' => (float) $items->sum(fn ($item) => $item->quantity * $item->unit_cost),
                ];
            })
            ->sortByDesc('quantity')
            ->values();

        $statusData = $inventory
            ->groupBy('status')
            ->map(fn ($items, $status) => [
                'label' => ucwords(str_replace('_', ' ', (string) $status)),
                'quantity' => (int) $items->sum('quantity'),
            ])
            ->values();

        $today = now()->startOfDay();
        $approachingLifespanCount = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->whereDate('expected_end_date', '>', $today)
            ->whereDate('expected_end_date', '<=', $today->copy()->addYear())
            ->sum('quantity');
        $expiredLifespanCount = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->whereDate('expected_end_date', '<=', $today)
            ->sum('quantity');

        $lifecycleData = collect([
            ['label' => 'Healthy', 'quantity' => (int) $activeInventory->filter(fn ($item) => $item->lifespan_status === 'healthy')->sum('quantity')],
            ['label' => 'Approaching end of life', 'quantity' => (int) $activeInventory->filter(fn ($item) => $item->lifespan_status === 'approaching_end_of_life')->sum('quantity')],
            ['label' => 'Past expected end date', 'quantity' => (int) $activeInventory->filter(fn ($item) => $item->lifespan_status === 'end_of_useful_life')->sum('quantity')],
        ]);

        $lowStockData = $activeInventory
            ->where('status', 'available')
            ->groupBy(fn ($item) => $item->item_name . '|' . $item->category_id)
            ->map(function ($items) {
                $item = $items->first();

                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'category' => $item->category?->category_name ?? 'Uncategorized',
                    'quantity' => (int) $items->sum('quantity'),
                    'unit' => $item->unit,
                ];
            })
            ->filter(fn ($item) => $item['quantity'] <= 3)
            ->sortBy('quantity')
            ->take(8)
            ->values();

        $attentionData = $activeInventory
            ->whereIn('status', ['under_maintenance', 'under_inspection', 'ready_to_dispose'])
            ->sortBy('expected_end_date')
            ->take(8)
            ->map(fn ($item) => [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'inventory_item_no' => $item->inventory_item_no,
                'status' => ucwords(str_replace('_', ' ', $item->status)),
                'quantity' => (int) $item->quantity,
            ])
            ->values();

        $movements = StockMovement::query()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get()
            ->groupBy(fn ($movement) => $movement->created_at->format('Y-m-d'));
        $movementData = collect();
        for ($date = $dateFrom->copy()->startOfDay(); $date->lte($dateTo); $date->addDay()) {
                $day = $date->format('Y-m-d');
                $rows = $movements->get($day, collect());

                $movementData->push([
                    'label' => $date->format('M d'),
                    'stock_in' => (int) $rows->filter(fn ($row) => in_array($row->movement_type, ['stock_in', 'return'], true))->sum('quantity'),
                    'stock_out' => (int) $rows->filter(fn ($row) => in_array($row->movement_type, ['stock_out', 'assignment', 'transfer'], true))->sum('quantity'),
                    'disposals' => (int) $rows->whereIn('movement_type', ['disposed', 'ready_to_dispose'])->sum('quantity'),
                ]);
        }

        $recentTransactions = Transaction::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name'])
            ->whereBetween('transaction_date', [$dateFrom, $dateTo])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('pages.propertyCustodian.reports', [
            'title' => 'Property Custodian Reports',
            'metrics' => [
                'totalUnits' => (int) $activeInventory->sum('quantity'),
                'availableUnits' => (int) $activeInventory->where('status', 'available')->sum('quantity'),
                'assignedUnits' => (int) $activeInventory->where('status', 'assigned')->sum('quantity'),
                'totalValue' => (float) $activeInventory->sum(fn ($item) => $item->quantity * $item->unit_cost),
                'disposedUnits' => (int) $inventory->where('status', 'disposed')->sum('quantity'),
                'attentionUnits' => (int) $activeInventory->whereIn('status', ['under_maintenance', 'under_inspection', 'ready_to_dispose'])->sum('quantity'),
                'lowStockGroups' => $lowStockData->count(),
                'approachingLifespan' => (int) $approachingLifespanCount,
                'expiredLifespan' => (int) $expiredLifespanCount,
            ],
            'categoryData' => $categoryData,
            'statusData' => $statusData,
            'lifecycleData' => $lifecycleData,
            'movementData' => $movementData,
            'lowStockData' => $lowStockData,
            'attentionData' => $attentionData,
            'recentTransactions' => $recentTransactions,
            'reportFilters' => ['date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString()],
        ]);
    }

    public function inventory(Request $request)
    {
        $categories = Category::orderBy('category_name')->get();
        $endUsers = User::query()
            ->with('role:role_id,role_name')
            ->whereHas('role', fn ($query) => $query->where('role_name', 'End User'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        $inventoryMetrics = [
            'total' => (int) Inventory::where('status', '!=', 'disposed')->sum('quantity'),
            'available' => (int) Inventory::where('status', 'available')->sum('quantity'),
            'assigned' => (int) Inventory::where('status', 'assigned')->sum('quantity'),
            'attention' => (int) Inventory::whereIn('status', ['under_inspection', 'under_maintenance'])->sum('quantity'),
        ];
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category');
        $statuses = ['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'];
        $statusFilter = $request->query('status', '');
        $statusFilter = in_array($statusFilter, $statuses, true) ? $statusFilter : '';
        // Item condition is represented by its current inventory workflow status.
        $conditionStatuses = [
            'good' => ['available', 'assigned'],
            'fair' => ['under_inspection'],
            'poor' => ['under_maintenance', 'ready_to_dispose', 'disposed'],
        ];
        $conditionFilter = $request->query('condition', '');
        $conditionFilter = array_key_exists($conditionFilter, $conditionStatuses) ? $conditionFilter : '';
        $activeWorkspace = in_array($request->query('workspace', 'all'), ['all', 'available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'], true)
            ? $request->query('workspace', 'all')
            : 'all';
        $buildInventoryQuery = function (bool $applyAllFilters = true) use ($search, $categoryId, $statusFilter, $conditionFilter, $conditionStatuses) {
            return Inventory::query()
                ->with('category:category_id,category_name,is_maintenance_eligible')
                ->select('item_name', 'category_id', 'status')
                ->selectRaw('MIN(item_id) as item_id')
                ->selectRaw('MIN(item_id) as source_item_id')
                ->selectRaw('MAX(unit) as unit')
                ->selectRaw('MAX(ics_no) as ics_no')
                ->selectRaw('MAX(description) as description')
                ->selectRaw('SUM(quantity) as quantity')
                ->selectRaw('SUM(quantity * unit_cost) as total_cost')
                ->selectRaw('SUM(quantity * unit_cost) / NULLIF(SUM(quantity), 0) as unit_cost')
                ->selectRaw('MAX(date_acquired) as date_acquired')
                ->selectRaw('MAX(lifespan_years) as lifespan_years')
                ->selectRaw('MAX(expected_end_date) as expected_end_date')
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where('item_name', 'like', '%' . $search . '%')
                            ->orWhere('unit', 'like', '%' . $search . '%')
                            ->orWhere('ics_no', 'like', '%' . $search . '%')
                            ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('category_name', 'like', '%' . $search . '%'));
                    });
                })
                ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
                ->when($applyAllFilters && $statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
                ->when($applyAllFilters && $conditionFilter !== '', fn ($query) => $query->whereIn('status', $conditionStatuses[$conditionFilter]))
                ->groupBy('item_name', 'category_id', 'status')
                ->orderBy('item_name');
        };
        $paginateInventory = function ($query, string $pageName) {
            return $query->paginate(25, ['*'], $pageName)->withQueryString();
        };
        $allInventoryPage = $paginateInventory($buildInventoryQuery(), 'all_page');
        $inventoryPages = collect($statuses)
            ->mapWithKeys(fn (string $status) => [$status => $paginateInventory($buildInventoryQuery(false)->where('status', $status), $status . '_page')]);
        $inventoryItems = $allInventoryPage->getCollection()
            ->toBase()
            ->merge($inventoryPages->flatMap(fn ($page) => $page->getCollection()->toBase()))
            ->unique(fn ($item): string => $item->item_name . '|' . $item->category_id . '|' . $item->status)
            ->values();
        $inventoryByStatus = $inventoryItems->groupBy('status');
        $inventoryStatusCounts = collect($statuses)->mapWithKeys(function (string $status) use ($buildInventoryQuery): array {
            return [$status => (int) $buildInventoryQuery(false)->where('status', $status)->get()->sum('quantity')];
        });
        $inventoryFilters = [
            'search' => $search,
            'category' => $categoryId > 0 ? (string) $categoryId : '',
            'status' => $statusFilter,
            'condition' => $conditionFilter,
        ];

        $sourceItemsByGroup = Inventory::query()
            ->with(['category:category_id,category_name,is_maintenance_eligible', 'assignedTo', 'latestMaintenance', 'latestStockMovement', 'latestDisposalMovement'])
            ->orderBy('item_name')
            ->orderBy('item_id')
            ->get()
            ->groupBy(fn (Inventory $item) => $item->item_name . '|' . $item->category_id . '|' . ($item->status ?? ''));

        $maintenanceItems = $sourceItemsByGroup
            ->filter(function ($sourceItems): bool {
                $sourceItem = $sourceItems->first();

                return $sourceItem?->status === 'available'
                    && $sourceItem->category?->is_maintenance_eligible !== false;
            })
            ->values();

        $latestAssignments = Transaction::query()
            ->with('user:id,first_name,last_name,username')
            ->where('status', 'assigned')
            ->latest('transaction_date')
            ->latest('id')
            ->get()
            ->unique('item_id')
            ->keyBy('item_id');

        $attachGroupDetails = function ($collection) use ($sourceItemsByGroup, $latestAssignments): void {
            $collection->each(function (Inventory $inventoryItem) use ($sourceItemsByGroup, $latestAssignments): void {
                $matchedSourceItems = $sourceItemsByGroup->get(
                    $inventoryItem->item_name . '|' . $inventoryItem->category_id . '|' . ($inventoryItem->status ?? ''),
                    collect(),
                );

                if ($matchedSourceItems->isEmpty() && $inventoryItem->source_item_id) {
                    $fallbackSource = Inventory::query()
                        ->with(['assignedTo', 'latestMaintenance', 'latestStockMovement', 'latestDisposalMovement'])
                        ->find($inventoryItem->source_item_id);
                    $matchedSourceItems = $fallbackSource ? collect([$fallbackSource]) : collect();
                }

                $matchedSourceItems->each(function (Inventory $sourceItem) use ($latestAssignments): void {
                    $sourceItem->latestAssignment = $latestAssignments->get($sourceItem->item_id);
                    $sourceItem->latestMovement = $sourceItem->latestStockMovement;
                    $sourceItem->disposalMovement = $sourceItem->latestDisposalMovement;
                });

                $inventoryItem->sourceItems = $matchedSourceItems;
            });
        };

        $attachGroupDetails($allInventoryPage->getCollection());
        $inventoryPages->each(fn ($page) => $attachGroupDetails($page->getCollection()));
        $attachGroupDetails($inventoryItems);

        $editItemDetails = collect();
        $sourceItemsByGroup->each(function ($items) use ($editItemDetails): void {
            $serialNumbers = $items->pluck('serial_number')->filter()->values();
            $groupQuantity = (int) $items->sum('quantity');

            $items->each(function (Inventory $item) use ($editItemDetails, $serialNumbers, $groupQuantity): void {
                $editItemDetails->put((string) $item->item_id, [
                    'serialNumbers' => $serialNumbers,
                    'quantity' => $groupQuantity > 0 ? $groupQuantity : (int) $item->quantity,
                ]);
            });
        });

        return view('pages.propertyCustodian.inventory', compact(
            'allInventoryPage',
            'categories',
            'endUsers',
            'inventoryItems',
            'inventoryByStatus',
            'inventoryPages',
            'inventoryStatusCounts',
            'inventoryMetrics',
            'inventoryFilters',
            'activeWorkspace',
            'maintenanceItems',
            'sourceItemsByGroup',
            'editItemDetails',
        ));
    }

    public function exportInventory(Request $request)
    {
        $fileName = 'dnhs_inventory_' . now()->format('Y-m-d_His') . '.csv';

        $items = Inventory::query()
            ->with(['category', 'assignedTo'])
            ->where('status', '!=', 'disposed')
            ->orderBy('item_name')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Item No',
                'Asset Name',
                'Category',
                'Serial Number',
                'Quantity',
                'Unit',
                'Unit Cost (PHP)',
                'Total Cost (PHP)',
                'Status',
                'Assigned To',
                'Date Acquired',
                'Expected End Date',
                'ICS No',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->inventory_item_no ?? 'N/A',
                    $item->item_name,
                    $item->category?->category_name ?? 'Uncategorized',
                    $item->serial_number ?? 'N/A',
                    $item->quantity,
                    $item->unit,
                    number_format((float) $item->unit_cost, 2, '.', ''),
                    number_format((float) $item->total_cost, 2, '.', ''),
                    ucwords(str_replace('_', ' ', $item->status)),
                    $item->assignedTo?->full_name ?? 'Unassigned',
                    optional($item->date_acquired)->format('Y-m-d'),
                    optional($item->expected_end_date)->format('Y-m-d'),
                    $item->ics_no ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Transactions method to display the transactions page with available inventory items, incoming requests, and transactions
    public function transactions()
    {
        $inventoryItems = Inventory::query()
            ->with(['category:category_id,category_name'])
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->orderBy('item_name')
            ->get();

        $groupedInventory = $inventoryItems->groupBy(function (Inventory $item) {
            return $item->item_name . '|available';
        })->map(function ($group) {
            $first = $group->first();
            return [
                'item_id' => $first->item_id,
                'item_name' => $first->item_name,
                'status' => $first->status,
                'category_name' => $first->category?->category_name,
                'quantity' => $group->sum('quantity'),
                'inventory_item_no' => $first->inventory_item_no,
            ];
        })->sortBy('item_name')->values();

        $endUsers = \App\Models\User::query()
            ->with('role:role_id,role_name')
            ->whereHas('role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->full_name,
            ]);

        // Incoming requests from End Users requiring Custodian action
        $incomingRequests = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status',
                'item.category:category_id,category_name',
            ])
            ->where('status', 'waiting for approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        // Assignments created by this Property Custodian for End Users
        $custodianId = request()->user()?->id;
        $assignmentRequests = AssignmentRequest::query()
            ->with([
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status',
                'item.category:category_id,category_name',
            ])
            ->where('user_id', $custodianId)
            ->where('status', 'waiting for approval')
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        // Transfer requests awaiting custodian approval (peer-to-peer, step 3 of 3)
        $pendingTransfers = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status',
                'item.category:category_id,category_name',
            ])
            ->where('status', 'waiting for custodian approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        // Calculate total available stock across the warehouse for each requested item name
        $itemNames = $incomingRequests->pluck('item.item_name')->filter()->unique();

        $availableStockByItemName = Inventory::query()
            ->whereIn('item_name', $itemNames)
            ->where('status', 'available')
            ->groupBy('item_name')
            ->selectRaw('item_name, SUM(quantity) as total_available_stock')
            ->pluck('total_available_stock', 'item_name');

        $incomingRequests->each(function ($req) use ($availableStockByItemName) {
            $itemName = optional($req->item)->item_name;
            $req->total_available_stock = (int) ($availableStockByItemName->get($itemName, 0));
        });

        // Completed / official transactions
        $transactions = Transaction::query()
            ->with([
                'user:id,first_name,last_name,username',
                'fromUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,category_id',
                'item.category:category_id,category_name',
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Metrics calculation
        $totalAssignedCount   = Transaction::where('status', 'assigned')->sum('quantity');
        $pendingRequestsCount = $incomingRequests->count() + $pendingTransfers->count();
        $totalTransactionsCount = $transactions->count();
        $overdueReturnsCount  = Transaction::whereNotNull('return_date')
            ->where('return_date', '<', now())
            ->where('status', '!=', 'returned')
            ->count();

        // Return requests awaiting custodian approval (end-user initiated returns)
        $pendingReturns = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,status,assigned_to_user_id',
            ])
            ->where('status', 'waiting for custodian approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $auditLedger = StockMovement::query()
            ->with(['inventory:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name,username'])
            ->latest('created_at')
            ->latest('id')
            ->limit(25)
            ->get();

        return view('pages.propertyCustodian.transactions', [
            'title'                  => 'Transactions',
            'availableInventoryItems' => $groupedInventory,
            'endUsers'               => $endUsers,
            'incomingRequests'       => $incomingRequests,
            'assignmentRequests'     => $assignmentRequests,
            'pendingTransfers'       => $pendingTransfers,
            'pendingReturns'         => $pendingReturns,
            'transactions'           => $transactions,
            'auditLedger'            => $auditLedger,
            'totalAssignedCount'     => $totalAssignedCount,
            'pendingRequestsCount'   => $pendingRequestsCount,
            'totalTransactionsCount' => $totalTransactionsCount,
            'overdueReturnsCount'    => $overdueReturnsCount,
        ]);
    }

    public function approveRequest(Request $request, $id)
    {
        $assignmentRequest = AssignmentRequest::with(['item', 'user'])->findOrFail($id);

        if ($assignmentRequest->status !== 'waiting for approval') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $approvalResult = DB::transaction(function () use ($assignmentRequest): string {
            $assignmentRequest = AssignmentRequest::whereKey($assignmentRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignmentRequest->status !== 'waiting for approval') {
                return 'processed';
            }

            $inventoryItem = Inventory::whereKey($assignmentRequest->item_id)
                ->lockForUpdate()
                ->firstOrFail();
            $availableItems = Inventory::where('item_name', $inventoryItem->item_name)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->orderBy('item_id')
                ->lockForUpdate()
                ->get();
            $totalAvailableStock = $availableItems->sum('quantity');

            if ($assignmentRequest->quantity > $totalAvailableStock) {
                return 'insufficient';
            }

            $assignmentRequest->loadMissing(['targetUser.role']);
            $assigneeId = strtolower((string) $assignmentRequest->targetUser?->role?->role_name) === 'end user'
                ? $assignmentRequest->target_user_id
                : $assignmentRequest->user_id;
            $neededQty = $assignmentRequest->quantity;

            // 1. Allocate from the referenced item if it has available quantity
            if ($inventoryItem->status === 'available' && $inventoryItem->quantity > 0) {
                $deduct = min($neededQty, $inventoryItem->quantity);
                $inventoryItem->decrement('quantity', $deduct);
                if ($inventoryItem->quantity <= 0) {
                    $inventoryItem->status = 'assigned';
                    $inventoryItem->assigned_to_user_id = $assigneeId;
                    $inventoryItem->save();
                }
                $neededQty -= $deduct;
            }

            // 2. If more units are needed (e.g. multiple serialized rows), allocate from other matching available items
            if ($neededQty > 0) {
                $otherItems = $availableItems
                    ->where('status', 'available')
                    ->where('item_id', '!=', $inventoryItem->item_id)
                    ->where('quantity', '>', 0)
                    ->sortBy('item_id');

                foreach ($otherItems as $otherItem) {
                    if ($neededQty <= 0) {
                        break;
                    }
                    $deduct = min($neededQty, $otherItem->quantity);
                    $otherItem->decrement('quantity', $deduct);
                    if ($otherItem->quantity <= 0) {
                        $otherItem->status = 'assigned';
                        $otherItem->assigned_to_user_id = $assigneeId;
                        $otherItem->save();
                    }
                    $neededQty -= $deduct;
                }
            }

            $transaction = Transaction::create([
                'item_id' => $assignmentRequest->item_id,
                'from_user_id' => $assignmentRequest->user_id,
                'user_id' => $assigneeId,
                'quantity' => $assignmentRequest->quantity,
                'transaction_date' => now(),
                'status' => 'assigned',
            ]);

            $assignmentRequest->update([
                'transaction_id' => $transaction->id,
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            return 'approved';
        });

        if ($approvalResult === 'processed') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        if ($approvalResult === 'insufficient') {
            return redirect()->back()->with('error', 'Insufficient stock to approve this request.');
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Request approved successfully. Item has been assigned.');
    }

    public function declineRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $assignmentRequest = AssignmentRequest::findOrFail($id);

        if ($assignmentRequest->status !== 'waiting for approval') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        $assignmentRequest->update([
            'status'       => 'declined',
            'notes'        => $validated['notes'] ?? null,
            'responded_at' => now(),
        ]);

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Request has been declined.');
    }

    /**
     * Step 3 of 3: Custodian approves a peer-to-peer transfer request.
     * Executes the actual transaction manipulation and marks the original assignment as transferred.
     */
    public function approveTransfer(Request $request, $id)
    {
        // $id = the transfer AssignmentRequest (status: 'waiting for custodian approval')
        $transferRequest = AssignmentRequest::with(['item'])->findOrFail($id);

        if ($transferRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        DB::transaction(function () use ($transferRequest) {
            $senderId      = $transferRequest->user_id;
            $recipientId   = $transferRequest->target_user_id;
            $itemId        = $transferRequest->item_id;
            $transferQty   = $transferRequest->quantity;

            $inventory = Inventory::whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Find and update the sender's original assignment
            $originalAssignment = AssignmentRequest::where('item_id', $itemId)
                ->where(function ($q) use ($senderId) {
                    $q->where('user_id', $senderId)
                      ->orWhere('target_user_id', $senderId);
                })
                ->where('status', 'transfer pending')
                ->first();

            if ($originalAssignment) {
                // Update the original transaction record if it exists
                if ($originalAssignment->transaction_id) {
                    $originTx = Transaction::find($originalAssignment->transaction_id);
                    if ($originTx) {
                        if ($originTx->quantity <= $transferQty) {
                            $originTx->quantity = 0;
                            $originTx->status   = 'transferred';
                            $originTx->save();
                        } else {
                            $originTx->decrement('quantity', $transferQty);
                        }
                    }
                }

                // Mark the original assignment as transferred
                $originalAssignment->status       = 'transferred';
                $originalAssignment->responded_at = now();
                $originalAssignment->save();

                // A full transfer moves custody of this inventory record to the recipient.
                $inventory->assigned_to_user_id = $recipientId;
                $inventory->status = 'assigned';
                $inventory->save();
            }

            // 2. Create a new transaction for the recipient
            $newTransaction = Transaction::create([
                'item_id'          => $itemId,
                'from_user_id'     => $senderId,
                'user_id'          => $recipientId,
                'quantity'         => $transferQty,
                'transaction_date' => now(),
                'status'           => 'assigned',
            ]);

            // 3. Mark transfer request as approved and link it to the new transaction
            $transferRequest->status         = 'approved';
            $transferRequest->transaction_id = $newTransaction->id;
            $transferRequest->responded_at   = now();
            $transferRequest->save();
        });

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Transfer approved. Item has been transferred to the recipient.');
    }

    /**
     * Step 3 of 3: Custodian declines a peer-to-peer transfer request.
     * Restores the sender's original assignment back to 'approved'.
     */
    public function declineTransfer(Request $request, $id)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $transferRequest = AssignmentRequest::findOrFail($id);

        if ($transferRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        DB::transaction(function () use ($transferRequest, $validated) {
            $senderId = $transferRequest->user_id;
            $itemId   = $transferRequest->item_id;

            $originalAssignment = AssignmentRequest::query()
                ->where('item_id', $itemId)
                ->where(function ($query) use ($senderId) {
                    $query->where('target_user_id', $senderId)
                        ->orWhere(function ($query) use ($senderId) {
                            $query->where('user_id', $senderId)
                                ->whereHas('targetUser.role', fn ($role) => $role->where('role_name', 'Property Custodian'));
                        });
                })
                ->whereIn('status', ['approved', 'transfer pending'])
                ->lockForUpdate()
                ->first();

            if ($originalAssignment?->status === 'transfer pending') {
                $originalAssignment->update(['status' => 'approved']);
            } elseif ($originalAssignment) {
                $originalAssignment->increment('quantity', $transferRequest->quantity);
            }

            // Decline the transfer request
            $transferRequest->status       = 'declined';
            $transferRequest->notes        = $validated['notes'] ?? $transferRequest->notes;
            $transferRequest->responded_at = now();
            $transferRequest->save();
        });

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Transfer request declined. Sender\'s item restored.');
    }

    public function assignItem(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $inventoryItem = Inventory::findOrFail($validated['item_id']);
        $endUser = User::findOrFail($validated['user_id']);
        $userId = $request->user()?->getAuthIdentifier();

        if ($inventoryItem->status !== 'available') {
            return redirect()->back()->withErrors(['item_id' => 'The selected item is not available for assignment.']);
        }

        if ($validated['quantity'] > $inventoryItem->quantity) {
            return redirect()->back()->withErrors(['quantity' => 'The requested quantity exceeds the available stock.']);
        }

        DB::transaction(function () use ($inventoryItem, $endUser, $validated, $userId): void {
            AssignmentRequest::create([
                'item_id' => $inventoryItem->item_id,
            'user_id' => $userId,
                'target_user_id' => $endUser->id,
                'quantity' => $validated['quantity'],
                'status' => 'waiting for approval',
                'requested_at' => $validated['transaction_date'] ? Carbon::parse($validated['transaction_date']) : now(),
            ]);
        });

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Assignment request submitted for approval.');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->username = $validated['username'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('propertyCustodian.profile')->with('success', 'Profile updated successfully.');
    }
    
    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,category_id'],
            'description' => ['nullable', 'string'],
            'ics_no' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'date_acquired' => ['required', 'date'],
            'lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $category = Category::findOrFail($validated['category_id']);
        $lifespanYears = $validated['lifespan_years'] ?? $category->default_lifespan_years;
        $lifespanYears = $lifespanYears === null ? null : (int) $lifespanYears;
        $expectedEndDate = $lifespanYears === null
            ? null
            : Carbon::parse($validated['date_acquired'])->addYears($lifespanYears)->toDateString();
        $serialNumbers = [];
        $userId = $request->user()?->getAuthIdentifier();

        if ($category->requires_serial_number) {
            $serialData = $request->validate([
                'serial_numbers' => ['required', 'array', 'size:' . $validated['quantity']],
                'serial_numbers.*' => ['required', 'string', 'max:255', 'distinct', 'unique:inventory,serial_number'],
            ]);

            $serialNumbers = $serialData['serial_numbers'];
        }

        DB::transaction(function () use ($validated, $serialNumbers, $category, $userId, $lifespanYears, $expectedEndDate): void {
            $itemAttributes = [
                'category_id' => $validated['category_id'],
                'unit' => $validated['unit'],
                'user_id' => $userId,
                'item_name' => $validated['item_name'],
                'description' => $validated['description'] ?? null,
                'ics_no' => $validated['ics_no'] ?? null,
                'unit_cost' => $validated['unit_cost'],
                'date_acquired' => $validated['date_acquired'],
                'lifespan_years' => $lifespanYears,
                'expected_end_date' => $expectedEndDate,
            ];

            if ($category->requires_serial_number) {
                foreach ($serialNumbers as $serialNumber) {
                    $this->createInventoryItem([
                        ...$itemAttributes,
                        'quantity' => 1,
                        'serial_number' => $serialNumber,
                    ]);
                }

                return;
            }

            $this->createInventoryItem([
                ...$itemAttributes,
                'quantity' => $validated['quantity'],
                'serial_number' => null,
            ]);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item stocked in successfully.');
    }

    public function editInventory(Inventory $inventory)
    {
        return view('pages.propertyCustodian.inventory-edit', [
            'title' => 'Edit Inventory Item',
            'inventoryItem' => $inventory,
            'categories' => Category::orderBy('category_name')->get(),
        ]);
    }

    public function updateInventory(Request $request, Inventory $inventory)
    {
        if ($inventory->status !== 'available') {
            return redirect()->route('propertyCustodian.inventory')->with('error', 'Only available inventory can be updated.');
        }

        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,category_id'],
            'description' => ['nullable', 'string'],
            'ics_no' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'date_acquired' => ['required', 'date'],
            'lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $inventory->loadMissing('category');
        $isSerialized = (bool) $inventory->category?->requires_serial_number;

        $groupItems = $isSerialized
            ? Inventory::query()
                ->where('item_name', $inventory->item_name)
                ->where('category_id', $inventory->category_id)
                ->where('status', $inventory->status)
                ->get()
            : collect([$inventory]);

        $groupQuantity = (int) $groupItems->sum('quantity');

        if ($inventory->status === 'assigned' && (int) $validated['quantity'] !== (int) $inventory->quantity && (int) $validated['quantity'] !== $groupQuantity) {
            return redirect()->back()->withErrors(['quantity' => 'Assigned inventory quantity cannot be changed.'])->withInput();
        }

        if ($isSerialized && (int) $validated['quantity'] !== $groupQuantity && (int) $validated['quantity'] !== (int) $inventory->quantity) {
            return redirect()->back()->withErrors(['quantity' => 'Serialized inventory quantity cannot be changed.'])->withInput();
        }

        $lifespanYears = array_key_exists('lifespan_years', $validated)
            ? $validated['lifespan_years']
            : $inventory->lifespan_years;
        $lifespanYears = $lifespanYears === null ? null : (int) $lifespanYears;
        $expectedEndDate = $lifespanYears === null
            ? null
            : Carbon::parse($validated['date_acquired'])->addYears($lifespanYears)->toDateString();

        $updateAttributes = [
            'item_name' => $validated['item_name'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'] ?? null,
            'ics_no' => $validated['ics_no'] ?? null,
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'date_acquired' => $validated['date_acquired'],
            'lifespan_years' => $lifespanYears,
            'expected_end_date' => $expectedEndDate,
        ];

        if ($isSerialized) {
            Inventory::query()
                ->whereIn('item_id', $groupItems->pluck('item_id'))
                ->update($updateAttributes);
        } else {
            $inventory->update([
                ...$updateAttributes,
                'quantity' => $validated['quantity'],
            ]);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Inventory item updated successfully.');
    }

    public function destroyInventory(Request $request, Inventory $inventory)
    {
        $selectedIds = $request->input('selected_item_ids');

        if (is_array($selectedIds) && !empty($selectedIds)) {
            $items = Inventory::whereIn('item_id', $selectedIds)->get();

            if ($items->contains(fn ($item) => $item->status === 'assigned')) {
                return redirect()->route('propertyCustodian.inventory')->with('error', 'Assigned inventory cannot be deleted.');
            }

            if ($items->contains(fn ($item) => $item->status !== 'available')) {
                return redirect()->route('propertyCustodian.inventory')->with('error', 'Only available inventory can be deleted.');
            }

            $count = $items->count();
            Inventory::whereIn('item_id', $selectedIds)->delete();

            $message = $count === 1
                ? 'Selected inventory item deleted successfully.'
                : "{$count} inventory items deleted successfully.";

            return redirect()->route('propertyCustodian.inventory')->with('success', $message);
        }

        if ($inventory->status === 'assigned') {
            return redirect()->route('propertyCustodian.inventory')->with('error', 'Assigned inventory cannot be deleted.');
        }

        if ($inventory->status !== 'available') {
            return redirect()->route('propertyCustodian.inventory')->with('error', 'Only available inventory can be deleted.');
        }

        $inventory->delete();

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Inventory item deleted successfully.');
    }


    public function approveReturn(Request $request, $id)
    {
        $returnRequest = AssignmentRequest::findOrFail($id);

        if ($returnRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This return request has already been processed.');
        }

        DB::transaction(function () use ($returnRequest, $request) {
            $inventory = Inventory::whereKey($returnRequest->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Assignment records are the source of truth for who owns a quantity.
            // This supports partial transfers, where several users may hold portions
            // of one inventory row.
            $assignment = AssignmentRequest::query()
                ->where('item_id', $inventory->item_id)
                ->where('target_user_id', $returnRequest->user_id)
                ->where('status', 'approved')
                ->where('quantity', '>=', $returnRequest->quantity)
                ->lockForUpdate()
                ->first();

            $isLegacySingleOwner = (int) $inventory->assigned_to_user_id === (int) $returnRequest->user_id;

            if (! $assignment && ! $isLegacySingleOwner) {
                throw new \Exception('Item is not assigned to the requester.');
            }

            $quantityBefore = $inventory->quantity;
            $quantityAfter = $quantityBefore + $returnRequest->quantity;

            $inventory->update([
                'assigned_to_user_id' => null,
                'status' => 'available',
                'quantity' => $quantityAfter,
            ]);

            if ($assignment) {
                $assignment->decrement('quantity', $returnRequest->quantity);

                if ($assignment->fresh()->quantity === 0) {
                    $assignment->update([
                        'status' => 'returned',
                        'responded_at' => now(),
                    ]);
                }
            }

            $transaction = $assignment?->transaction_id
                ? Transaction::whereKey($assignment->transaction_id)->lockForUpdate()->first()
                : null;

            if (! $transaction) {
                $transaction = Transaction::query()
                    ->where('item_id', $returnRequest->item_id)
                    ->where('user_id', $returnRequest->user_id)
                    ->where('status', 'assigned')
                    ->where('quantity', '>=', $returnRequest->quantity)
                    ->latest('transaction_date')
                    ->lockForUpdate()
                    ->first();
            }

            if ($transaction) {
                if ($transaction->quantity <= $returnRequest->quantity) {
                    $transaction->update([
                        'status' => 'returned',
                        'return_date' => now(),
                    ]);
                } else {
                    $transaction->decrement('quantity', $returnRequest->quantity);
                }
            }

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'returned',
                'quantity' => $quantityBefore,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'assignment_request',
                'reference_id' => $returnRequest->id,
                'notes' => 'Item returned by end user',
            ]);

            $returnRequest->update([
                'status' => 'approved',
                'responded_at' => now(),
            ]);
        });

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Return request approved. Item is now available.');
    }

    public function declineReturn(Request $request, $id)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $returnRequest = AssignmentRequest::findOrFail($id);

        if ($returnRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This return request has already been processed.');
        }

        $returnRequest->update([
            'status' => 'declined',
            'notes' => $validated['notes'] ?? null,
            'responded_at' => now(),
        ]);

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Return request declined.');
    }

    public function markReturned(Request $request, $itemId)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = Inventory::findOrFail($itemId);

        if ($inventory->status !== 'assigned' || !$inventory->assigned_to_user_id) {
            return redirect()->back()->with('error', 'Only assigned items can be marked as returned.');
        }

        DB::transaction(function () use ($inventory, $validated, $request) {
            $inventory = Inventory::whereKey($inventory->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventory->status !== 'assigned') {
                throw new \Exception('Item is not currently assigned.');
            }

            $quantityBefore = $inventory->quantity;
            $quantityAfter = $inventory->quantity;

            $transaction = Transaction::query()
                ->where('item_id', $inventory->item_id)
                ->where('user_id', $inventory->assigned_to_user_id)
                ->where('status', 'assigned')
                ->latest('transaction_date')
                ->lockForUpdate()
                ->first();

            $inventory->assigned_to_user_id = null;
            $inventory->status = 'available';
            $inventory->save();

            if ($transaction) {
                $transaction->update([
                    'status' => 'returned',
                    'return_date' => now(),
                ]);
            }

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'returned',
                'quantity' => $quantityBefore,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'inventory',
                'reference_id' => $inventory->item_id,
                'notes' => $validated['notes'] ?? 'Item received in warehouse',
            ]);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item marked as returned and now available.');
    }

    public function sendToMaintenance(Request $request, $itemId)
    {
        $validated = $request->validate([
            'issue_description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = Inventory::findOrFail($itemId);
        $inventory->loadMissing('category');

        if ($inventory->status !== 'available') {
            return redirect()->back()->with('error', 'Only available items can be sent to maintenance.');
        }

        if ($inventory->category?->is_maintenance_eligible === false) {
            return redirect()->back()->with('error', 'Items in this category are not eligible for maintenance.');
        }

        DB::transaction(function () use ($inventory, $validated, $request) {
            $inventory = Inventory::whereKey($inventory->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventory->status !== 'available') {
                throw new \Exception('Item is not currently available.');
            }

            $quantity = $inventory->quantity;

            $inventory->update(['status' => 'under_maintenance']);

            MaintenanceRecord::create([
                'inventory_id' => $inventory->item_id,
                'reported_by' => $request->user()->id,
                'status' => 'reported',
                'issue_description' => $validated['issue_description'],
                'started_at' => now(),
            ]);

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'maintenance',
                'quantity' => $quantity,
                'quantity_before' => $quantity,
                'quantity_after' => $quantity,
                'reference_type' => 'inventory',
                'reference_id' => $inventory->item_id,
                'notes' => $validated['notes'] ?? 'Item sent to maintenance',
            ]);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item sent to maintenance.');
    }

    public function markRepaired(Request $request, $itemId)
    {
        $validated = $request->validate([
            'repair_notes' => ['nullable', 'string', 'max:1000'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $error = DB::transaction(function () use ($itemId, $validated, $request): ?string {
            $inventory = Inventory::whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventory->status !== 'under_maintenance') {
                return 'Item is not currently under maintenance.';
            }

            $maintenanceRecord = MaintenanceRecord::query()
                ->where('inventory_id', $inventory->item_id)
                ->whereIn('status', ['reported', 'in_progress'])
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (! $maintenanceRecord) {
                return 'No active maintenance record was found.';
            }

            $quantity = $inventory->quantity;
            $completedAt = now();

            $inventory->update(['status' => 'available']);

            $maintenanceRecord->update([
                'status' => 'completed',
                'repair_notes' => $validated['repair_notes'] ?? $maintenanceRecord->repair_notes,
                'maintenance_cost' => $validated['maintenance_cost'] ?? $maintenanceRecord->maintenance_cost,
                'completed_at' => $completedAt,
            ]);

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'maintenance_completed',
                'quantity' => $quantity,
                'quantity_before' => $quantity,
                'quantity_after' => $quantity,
                'reference_type' => 'maintenance_record',
                'reference_id' => $maintenanceRecord->id,
                'notes' => $validated['repair_notes'] ?? 'Item repaired and returned to inventory',
            ]);

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item marked as repaired and now available.');
    }

    public function markReadyToDispose(Request $request, $itemId)
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $error = DB::transaction(function () use ($itemId, $validated, $request): ?string {
            $inventory = Inventory::whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventory->status !== 'under_maintenance') {
                return 'Only items under maintenance can be marked ready to dispose.';
            }

            $quantity = $inventory->quantity;
            $inventory->update(['status' => 'ready_to_dispose']);

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'ready_to_dispose',
                'quantity' => $quantity,
                'quantity_before' => $quantity,
                'quantity_after' => $quantity,
                'reference_type' => 'inventory',
                'reference_id' => $inventory->item_id,
                'notes' => $validated['notes'],
            ]);

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item marked ready for disposal.');
    }

    public function disposeInventory(Request $request, $itemId)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = Inventory::findOrFail($itemId);

        if (! in_array($inventory->status, ['available', 'ready_to_dispose'], true)) {
            return redirect()->back()->with('error', 'Only available or ready-to-dispose items can be disposed.');
        }

        $error = DB::transaction(function () use ($inventory, $validated, $request): ?string {
            $inventory = Inventory::whereKey($inventory->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($inventory->status, ['available', 'ready_to_dispose'], true)) {
                return 'Item is not available for disposal.';
            }

            $quantity = $inventory->quantity;

            $inventory->update(['status' => 'disposed']);

            StockMovement::create([
                'inventory_id' => $inventory->item_id,
                'user_id' => $request->user()?->id,
                'movement_type' => 'disposed',
                'quantity' => $quantity,
                'quantity_before' => $quantity,
                'quantity_after' => 0,
                'reference_type' => 'inventory',
                'reference_id' => $inventory->item_id,
                'notes' => $validated['notes'] ?? 'Item disposed',
            ]);

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item disposed successfully.');
    }

    private function createInventoryItem(array $attributes): void
    {
        $inventoryItem = Inventory::create($attributes);

        $movementQuantity = (int) ($attributes['quantity'] ?? 0);
        if ($movementQuantity > 0) {
            StockMovement::create([
                'inventory_id' => $inventoryItem->item_id,
                'user_id' => $attributes['user_id'] ?? null,
                'movement_type' => 'stock_in',
                'quantity' => $movementQuantity,
                'quantity_before' => 0,
                'quantity_after' => $movementQuantity,
                'reference_type' => 'inventory',
                'reference_id' => $inventoryItem->item_id,
                'notes' => $attributes['description'] ?? 'Stock-in',
            ]);
        }

        $category = Category::find($attributes['category_id'] ?? null);
        $qrCode = null;

        if ($category && $category->requires_qr_code) {
            do {
                $token = 'dnhs_qr_' . Str::lower(Str::random(24));
            } while (Inventory::where('qr_code', $token)->exists());

            $qrCode = $token;
        }

        $inventoryItem->update([
            'inventory_item_no' => sprintf('INV-%06d', $inventoryItem->item_id),
            'qr_code' => $qrCode,
        ]);
    }

    public function qrLookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = trim((string) $request->query('token', ''));

        if (empty($token)) {
            return response()->json([
                'found'    => false,
                'message'  => 'No QR token provided.',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $item = Inventory::byQrToken($token)
            ->with(['category', 'assignedTo'])
            ->first();

        if (! $item) {
            return response()->json([
                'found'   => false,
                'message' => 'No item found for this QR code.',
            ], 404);
        }

        // Role-scoped payload — custodians get full detail
        $payload = [
            'found'              => true,
            'item_id'            => $item->item_id,
            'inventory_item_no'  => $item->inventory_item_no,
            'item_name'          => $item->item_name,
            'category'           => $item->category->category_name ?? null,
            'status'             => $item->status,
            'condition'          => $item->condition ?? null,
            'serial_number'      => $item->serial_number,
            'label_url'          => route('propertyCustodian.inventory.print-qr', $item->item_id),
        ];

        // Full detail only for Property Custodian role
        if ($user->role && $user->role->role_name === 'Property Custodian') {
            $payload['quantity']         = $item->quantity;
            $payload['unit']             = $item->unit;
            $payload['unit_cost']        = $item->unit_cost;
            $payload['ics_no']           = $item->ics_no;
            $payload['date_acquired']    = $item->date_acquired?->toDateString();
            $payload['lifespan_years']   = $item->lifespan_years;
            $payload['expected_end_date'] = $item->expected_end_date?->toDateString();
            $payload['lifespan_status']  = $item->lifespan_status;
            $payload['assigned_to']      = $item->assignedTo
                ? [
                    'user_id' => $item->assignedTo->id,
                    'name'    => $item->assignedTo->full_name,
                ]
                : null;
        }

        return response()->json($payload);
    }

    public function printQr(Request $request, Inventory $inventory): \Illuminate\View\View|\Illuminate\Http\JsonResponse
    {
        if (! $inventory->hasQrCode()) {
            abort(404, 'This inventory item does not have a QR code.');
        }

        // Fetch all items belonging to the same item name and category that have QR codes
        $batchItems = Inventory::query()
            ->with('category')
            ->where('item_name', $inventory->item_name)
            ->where('category_id', $inventory->category_id)
            ->whereNotNull('qr_code')
            ->orderBy('item_id')
            ->get();

        if ($batchItems->isEmpty()) {
            $batchItems = collect([$inventory->load('category')]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'activeItem' => $inventory->load('category'),
                'items' => $batchItems,
            ]);
        }

        return view('pages.propertyCustodian.print-qr', [
            'title' => 'Print QR — ' . $inventory->item_name,
            'activeItem' => $inventory->load('category'),
            'items' => $batchItems,
        ]);
    }
}
