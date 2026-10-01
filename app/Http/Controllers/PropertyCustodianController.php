<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\ForecastExplanationService;
use App\Services\InventoryOperationService;
use App\Services\StoredDemandForecastService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class PropertyCustodianController extends Controller
{
    public function __construct(
        protected ForecastExplanationService $forecastExplanationService,
        protected StoredDemandForecastService $storedForecastService,
        protected InventoryOperationService $inventoryOperations
    )
    {
    }

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

    public function runForecast()
    {
        return redirect()->route('propertyCustodian.reports')->with('forecastResult', $this->storedForecastService->read(request()->user()));
    }

    public function explainForecast(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['nullable', 'required_without:inventory_id', 'string', 'max:255'],
            'inventory_id' => ['nullable', 'required_without:item_name', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $forecastResult = $this->storedForecastService->read($user);

        if (($forecastResult['status'] ?? null) === 'forbidden') {
            return redirect()->route('propertyCustodian.reports')->with('error', 'That information is not available for your role.');
        }

        $matchingRows = collect($forecastResult['rows'] ?? [])->filter(function (array $forecastRow) use ($validated): bool {
            return isset($validated['inventory_id'])
                ? (int) ($forecastRow['inventory_id'] ?? 0) === (int) $validated['inventory_id']
                : strtolower(trim($forecastRow['item_name'] ?? '')) === strtolower(trim($validated['item_name'] ?? ''));
        })->values();

        if ($matchingRows->count() > 1) {
            $identities = $matchingRows->pluck('inventory_id')->map(fn (int $id): string => (string) $id)->implode(', ');

            return redirect()->route('propertyCustodian.reports')
                ->with('error', "That item name matches multiple forecast records. Specify an inventory ID: {$identities}.");
        }

        $row = $matchingRows->first();

        if ($row === null) {
            return redirect()->route('propertyCustodian.reports')->with('error', 'No forecast result is available for that item.');
        }

        $explanation = $this->forecastExplanationService->explain($user, $row);

        if (($explanation['status'] ?? null) === 'forbidden') {
            return redirect()->route('propertyCustodian.reports')->with('error', $explanation['message']);
        }

        return redirect()->route('propertyCustodian.reports')
            ->with('forecastExplanation', $explanation['explanation'])
            ->with('forecastExplanationItem', $row['item_name']);
    }

    public function forecastRecommendations(Request $request)
    {
        $demoMode = $request->query('source') === 'demo';
        $forecastResult = $demoMode
            ? $this->storedForecastService->readDemo($request->user())
            : $this->storedForecastService->read($request->user());
        $filters = $request->validate([
            'source' => ['nullable', Rule::in(['live', 'demo'])],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['Urgent', 'High', 'Medium', 'Normal'])],
            'confidence' => ['nullable', Rule::in(['High', 'Medium', 'Low'])],
            'sort' => ['nullable', Rule::in(['suggested', 'demand', 'available', 'name'])],
        ]);

        $allRows = collect($forecastResult['rows'] ?? []);
        $search = strtolower(trim($filters['search'] ?? ''));
        $rows = $allRows
            ->filter(fn (array $row): bool => $search === '' || str_contains(strtolower($row['item_name']), $search))
            ->when(isset($filters['category']), fn ($items) => $items->where('category', $filters['category']))
            ->when(isset($filters['priority']), fn ($items) => $items->where('priority', $filters['priority']))
            ->when(isset($filters['confidence']), fn ($items) => $items->where('confidence', $filters['confidence']));

        $rows = $demoMode
            ? $rows->sortBy('inventory_id')
            : match ($filters['sort'] ?? 'suggested') {
                'demand' => $rows->sortByDesc('forecast_demand'),
                'available' => $rows->sortBy('available_stock'),
                'name' => $rows->sortBy(fn (array $row): string => $row['item_name'], SORT_NATURAL | SORT_FLAG_CASE),
                default => $rows->sortBy([
                    ['suggested_procurement', 'desc'],
                    ['priority_rank', 'desc'],
                ]),
            };

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $recommendations = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('pages.propertyCustodian.forecast-details', [
            'title' => 'Demand Forecast Recommendations',
            'forecastResult' => $forecastResult,
            'demoMode' => $demoMode,
            'isDemoForecast' => $demoMode,
            'recommendations' => $recommendations,
            'categories' => $allRows->pluck('category')->filter()->unique()->sort()->values(),
            'filters' => $filters,
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

        $liveForecastResult = $this->storedForecastService->read($request->user());
        $demoForecastResult = $this->storedForecastService->readDemo($request->user());

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
            'liveForecastResult' => $liveForecastResult,
            'demoForecastResult' => $demoForecastResult,
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
        $assignedQuantity = (int) Transaction::where('status', 'assigned')->sum('quantity');
        $inventoryMetrics = [
            'total' => (int) Inventory::where('status', '!=', 'disposed')->sum('quantity') + $assignedQuantity,
            'available' => (int) Inventory::where('status', 'available')->sum('quantity'),
            'assigned' => $assignedQuantity,
            'attention' => (int) Inventory::whereIn('status', ['under_inspection', 'under_maintenance'])->sum('quantity'),
        ];
        $statuses = ['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'];
        $activeWorkspace = in_array($request->query('workspace', 'all'), ['all', 'available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'], true)
            ? $request->query('workspace', 'all')
            : 'all';
        $buildInventoryQuery = function () {
            return Inventory::query()
                ->with('category:category_id,category_name,is_maintenance_eligible,requires_serial_number')
                ->select('item_name', 'category_id', 'unit', 'status')
                ->selectRaw('MIN(item_id) as item_id')
                ->selectRaw('MIN(item_id) as source_item_id')
                ->selectRaw('MAX(ics_no) as ics_no')
                ->selectRaw('MAX(description) as description')
                ->selectRaw('SUM(quantity) as quantity')
                ->selectRaw('SUM(quantity * unit_cost) as total_cost')
                ->selectRaw('SUM(quantity * unit_cost) / NULLIF(SUM(quantity), 0) as unit_cost')
                ->selectRaw('MAX(date_acquired) as date_acquired')
                ->selectRaw('MAX(lifespan_years) as lifespan_years')
                ->selectRaw('MAX(expected_end_date) as expected_end_date')
                ->groupBy('item_name', 'category_id', 'unit', 'status')
                ->orderBy('item_name');
        };
        $paginateInventory = function ($query, string $pageName) {
            return $query->paginate(25, ['*'], $pageName)->withQueryString();
        };
        $allInventoryPage = $paginateInventory($buildInventoryQuery(), 'all_page');
        $inventoryPages = collect($statuses)
            ->mapWithKeys(fn (string $status) => [$status => $paginateInventory($buildInventoryQuery()->where('status', $status), $status . '_page')]);
        $inventoryItems = $allInventoryPage->getCollection()
            ->toBase()
            ->merge($inventoryPages->flatMap(fn ($page) => $page->getCollection()->toBase()))
            ->unique(fn ($item): string => $item->item_name . '|' . $item->category_id . '|' . $item->unit . '|' . $item->status)
            ->values();
        $inventoryByStatus = $inventoryItems->groupBy('status');
        $inventoryStatusCounts = collect($statuses)->mapWithKeys(function (string $status) use ($buildInventoryQuery, $assignedQuantity): array {
            $quantity = $status === 'assigned'
                ? $assignedQuantity
                : (int) $buildInventoryQuery()->where('status', $status)->get()->sum('quantity');

            return [$status => $quantity];
        });
        $sourceItemsByGroup = Inventory::query()
            ->with(['category:category_id,category_name,is_maintenance_eligible,requires_serial_number', 'assignedTo', 'latestMaintenance', 'latestStockMovement', 'latestDisposalMovement'])
            ->orderBy('item_name')
            ->orderBy('item_id')
            ->get()
            ->groupBy(fn (Inventory $item) => $item->item_name . '|' . $item->category_id . '|' . $item->unit . '|' . ($item->status ?? ''));

        $maintenanceItems = $sourceItemsByGroup
            ->filter(function ($sourceItems): bool {
                $sourceItem = $sourceItems->first();

                return $sourceItem?->status === 'available'
                    && $sourceItem->category?->is_maintenance_eligible !== false;
            })
            ->values();

        $activeAssignments = Transaction::query()
            ->with([
                'user:id,first_name,last_name,username',
                'assignmentRequests:id,transaction_id,item_id,user_id,target_user_id,quantity,status',
                'assignmentReturns:id,transaction_id,quantity',
            ])
            ->where('status', 'assigned')
            ->where('quantity', '>', 0)
            ->latest('transaction_date')
            ->latest('id')
            ->get()
            ->groupBy('item_id');

        $attachGroupDetails = function ($collection) use ($sourceItemsByGroup, $activeAssignments): void {
            $collection->each(function (Inventory $inventoryItem) use ($sourceItemsByGroup, $activeAssignments): void {
                $matchedSourceItems = $sourceItemsByGroup->get(
                    $inventoryItem->item_name . '|' . $inventoryItem->category_id . '|' . $inventoryItem->unit . '|' . ($inventoryItem->status ?? ''),
                    collect(),
                );

                if ($matchedSourceItems->isEmpty() && $inventoryItem->source_item_id) {
                    $fallbackSource = Inventory::query()
                        ->with(['assignedTo', 'latestMaintenance', 'latestStockMovement', 'latestDisposalMovement'])
                        ->find($inventoryItem->source_item_id);
                    $matchedSourceItems = $fallbackSource ? collect([$fallbackSource]) : collect();
                }

                $matchedSourceItems->each(function (Inventory $sourceItem) use ($activeAssignments): void {
                    $itemAssignments = $activeAssignments->get($sourceItem->item_id, collect());
                    $sourceItem->latestAssignment = $itemAssignments->first();
                    $sourceItem->assigned_quantity = (int) $itemAssignments->sum('quantity');
                    $sourceItem->receive_return_assignments = $itemAssignments
                        ->filter(fn (Transaction $transaction) => $transaction->user_id && (int) $transaction->quantity > 0)
                        ->map(function (Transaction $transaction) use ($sourceItem) {
                            $assignmentRequest = $transaction->assignmentRequests->first(fn (AssignmentRequest $assignment) =>
                                (int) $assignment->item_id === (int) $sourceItem->item_id
                                && (int) $assignment->target_user_id === (int) $transaction->user_id
                                && in_array($assignment->status, ['approved', 'accepted'], true)
                            );
                            $pendingReturnRequest = $transaction->assignmentRequests->first(fn (AssignmentRequest $assignment) =>
                                (int) $assignment->item_id === (int) $sourceItem->item_id
                                && (int) $assignment->user_id === (int) $transaction->user_id
                                && (int) $assignment->target_user_id === (int) auth()->id()
                                && in_array($assignment->status, ['waiting for custodian approval'], true)
                            );
                            $returnedQuantity = (int) $transaction->assignmentReturns->sum('quantity');

                            return [
                                'transaction_id' => $transaction->id,
                                'assignment_request_id' => $assignmentRequest?->id,
                                'return_request_id' => $pendingReturnRequest?->id,
                                'return_request_quantity' => (int) ($pendingReturnRequest?->quantity ?? 0),
                                'inventory_id' => $sourceItem->item_id,
                                'inventory_item_no' => $sourceItem->inventory_item_no,
                                'item_name' => $sourceItem->item_name,
                                'category' => $sourceItem->category?->category_name ?? 'Uncategorized',
                                'unit' => $sourceItem->unit,
                                'serial_number' => $sourceItem->serial_number,
                                'recipient' => $transaction->user?->full_name ?? $transaction->user?->username ?? 'Unknown recipient',
                                'issued_quantity' => max((int) $transaction->issued_quantity, (int) $transaction->quantity + $returnedQuantity),
                                'returned_quantity' => $returnedQuantity,
                                'remaining_quantity' => (int) $transaction->quantity,
                                'is_serialized' => (bool) $sourceItem->category?->requires_serial_number,
                            ];
                        })
                        ->values();
                    $sourceItem->active_assignee_summary = $itemAssignments
                        ->groupBy(fn (Transaction $transaction) => $transaction->user?->full_name
                            ?? $transaction->user?->username
                            ?? $transaction->manual_recipient_name
                            ?? 'Recorded assignee')
                        ->map(fn ($transactions, $name) => $name . ' (' . (int) $transactions->sum('quantity') . ')')
                        ->implode(', ');
                    $sourceItem->latestMovement = $sourceItem->latestStockMovement;
                    $sourceItem->disposalMovement = $sourceItem->latestDisposalMovement;
                });

                $inventoryItem->sourceItems = $matchedSourceItems;
                $inventoryItem->assigned_quantity = (int) $matchedSourceItems->sum('assigned_quantity');
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

        $groupedInventory = $inventoryItems->map(function (Inventory $item) {
            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->category_name,
                'quantity' => (int) $item->quantity,
                'unit' => $item->unit,
                'inventory_item_no' => $item->inventory_item_no,
                'serial_number' => $item->serial_number,
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
                'user:id,first_name,last_name,username,email',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit,serial_number',
                'item.category:category_id,category_name',
                'requestedCategory:category_id,category_name,requires_serial_number',
            ])
            ->where('status', 'waiting for approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $incomingRequests->each(function (AssignmentRequest $request): void {
            if ($request->requested_item_name) {
                $matchingItems = Inventory::query()
                    ->where('item_name', $request->requested_item_name)
                    ->where('category_id', $request->requested_category_id)
                    ->where('unit', $request->requested_unit)
                    ->where('status', 'available')
                    ->where('quantity', '>', 0)
                    ->orderBy('item_id')
                    ->get();
                $request->matching_inventory_items = $matchingItems;
                $request->total_available_stock = (int) $matchingItems->sum('quantity');
                return;
            }

            $item = $request->item;
            $request->matching_inventory_items = $item && $item->status === 'available'
                ? collect([$item])
                : collect();
            $request->total_available_stock = (int) ($item?->status === 'available' ? $item->quantity : 0);
        });

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
        $today = now()->toDateString();
        $overdueReturnsCount  = Transaction::query()
            ->where(function ($query) use ($today): void {
                $query->where(function ($query) use ($today): void {
                    $query->whereNotNull('return_date')->where('return_date', '<', $today);
                })->orWhere(function ($query) use ($today): void {
                    $query->whereNotNull('expected_return_date')->where('expected_return_date', '<', $today);
                });
            })
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

        $validated = $assignmentRequest->requested_item_name
            ? $request->validate([
                'inventory_ids' => ['required', 'array', 'min:1'],
                'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            ])
            : [];

        $approvalResult = $this->inventoryOperations->approveAssignment(
            $assignmentRequest->id,
            (int) $request->user()->id,
            $validated['inventory_ids'] ?? [],
        );

        if ($approvalResult === 'processed') {
            return redirect()->back()->with('error', 'This request has already been processed.');
        }

        if ($approvalResult === 'insufficient') {
            return redirect()->back()->with('error', 'One or more selected inventory records are no longer available in the requested quantity.');
        }

        if ($approvalResult === 'invalid_allocation') {
            return redirect()->back()->with('error', 'Selected items do not match the requested quantity. Update your selection and try again.');
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Request approved successfully. Item has been assigned.');
    }

    public function cancelItemRequest(Request $request, int $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancelled = DB::transaction(function () use ($id, $validated): bool {
            $assignmentRequest = AssignmentRequest::query()->lockForUpdate()->findOrFail($id);
            if (
                $assignmentRequest->status !== 'waiting for approval'
                || ! $assignmentRequest->requested_item_name
                || $assignmentRequest->item_id
            ) {
                return false;
            }

            $assignmentRequest->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'responded_at' => now(),
            ]);

            return true;
        });

        if (! $cancelled) {
            return redirect()->back()->with('error', 'This item request has already been processed.');
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Item request cancelled.');
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
        $transferRequest = AssignmentRequest::findOrFail($id);

        if ($transferRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        $approved = $this->inventoryOperations->approveTransfer(
            $transferRequest->id,
            (int) $request->user()->id,
        );

        if (! $approved) {
            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

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

        if (! $this->inventoryOperations->declineTransferRequest($transferRequest->id, $validated['notes'] ?? null)) {
            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Transfer request declined. Sender\'s item restored.');
    }

    public function assignItem(Request $request)
    {
        $assignmentType = $request->input('assignment_type', 'registered');

        if ($assignmentType === 'manual') {
            $validated = $request->validate([
                'item_id' => ['required', 'exists:inventory,item_id'],
                'category_id' => ['required', 'exists:categories,category_id'],
                'unit' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'integer', 'min:1'],
                'manual_recipient_name' => ['required', 'string', 'max:255'],
                'manual_department' => ['required', 'string', 'max:255'],
                'building' => ['nullable', 'string', 'max:255'],
                'room' => ['nullable', 'string', 'max:255'],
                'manual_recipient_type' => ['nullable', 'string', 'max:100'],
                'manual_contact' => ['nullable', 'string', 'max:255'],
                'manual_notes' => ['nullable', 'string', 'max:1000'],
                'transaction_date' => ['required', 'date'],
                'expected_return_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            ]);

            $result = $this->inventoryOperations->issueManual(
                $validated,
                (int) $request->user()->id,
            );

            if (is_string($result)) {
                return redirect()->back()->withInput()->with('error', $result);
            }

            return redirect()->route('propertyCustodian.transactions')->with('success', 'Item issued manually and recorded in transaction history.');
        }

        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $endUser = User::with('role')->findOrFail($validated['user_id']);
        $userId = $request->user()?->getAuthIdentifier();

        if (strtolower((string) $endUser->role?->role_name) !== 'end user') {
            return redirect()->back()->withErrors(['user_id' => 'Items can only be assigned to an End User.'])->withInput();
        }

        $assignmentResult = $this->inventoryOperations->submitRegisteredAssignment(
            (int) $validated['item_id'],
            (int) $userId,
            (int) $endUser->id,
            (int) $validated['quantity'],
            $validated['transaction_date'] ?? null,
        );

        if ($assignmentResult === 'unavailable') {
            return redirect()->back()->withErrors(['item_id' => 'The selected item is not available for assignment.'])->withInput();
        }
        if ($assignmentResult === 'insufficient') {
            return redirect()->back()->withErrors(['quantity' => 'The requested quantity exceeds the available stock.'])->withInput();
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Assignment request submitted for approval.');
    }

    public function returnManualIssue(Request $request, int $transactionId)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $error = $this->inventoryOperations->returnManualIssue(
            $transactionId,
            (int) $request->user()->id,
            $validated['notes'] ?? null,
        );

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Manual issue returned and inventory restored.');
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
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
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
                'building' => $validated['building'] ?? null,
                'room' => $validated['room'] ?? null,
                'ics_no' => $validated['ics_no'] ?? null,
                'unit_cost' => $validated['unit_cost'],
                'date_acquired' => $validated['date_acquired'],
                'lifespan_years' => $lifespanYears,
                'expected_end_date' => $expectedEndDate,
            ];

            if ($category->requires_serial_number) {
                foreach ($serialNumbers as $serialNumber) {
                    $this->inventoryOperations->stockIn([
                        ...$itemAttributes,
                        'quantity' => 1,
                        'serial_number' => $serialNumber,
                    ]);
                }

                return;
            }

            $this->inventoryOperations->stockIn([
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
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        $inventory->loadMissing('category');
        $isSerialized = (bool) $inventory->category?->requires_serial_number;

        $groupItems = $isSerialized
            ? Inventory::query()
                ->where('item_name', $inventory->item_name)
                ->where('category_id', $inventory->category_id)
                ->where('unit', $inventory->unit)
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
        foreach (['building', 'room'] as $locationField) {
            if (array_key_exists($locationField, $validated)) {
                $updateAttributes[$locationField] = $validated[$locationField];
            }
        }

        $error = $this->inventoryOperations->updateAvailableInventory(
            $inventory->item_id,
            $updateAttributes,
            (int) $validated['quantity'],
            $isSerialized,
            $groupItems->pluck('item_id')->all(),
            (int) $request->user()->id,
        );

        if ($error !== null) {
            return redirect()->back()->with('error', $error)->withInput();
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
        $validated = $request->validate([
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);
        $returnRequest = AssignmentRequest::findOrFail($id);

        if ($returnRequest->status !== 'waiting for custodian approval') {
            return redirect()->back()->with('error', 'This return request has already been processed.');
        }

        $approved = $this->inventoryOperations->approveReturn(
            $returnRequest->id,
            (int) $request->user()->id,
            $validated,
        );

        if (! $approved) {
            return redirect()->back()->with('error', 'This return request has already been processed.');
        }

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

    public function receiveReturn(Request $request)
    {
        $validated = $request->validate([
            'transaction_ids' => ['required_without:transaction_id', 'array', 'min:1'],
            'transaction_ids.*' => ['required', 'integer', 'distinct', 'exists:transactions,id'],
            'transaction_id' => ['required_without:transaction_ids', 'integer', 'exists:transactions,id'],
            'return_request_id' => ['nullable', 'integer', 'exists:requests,id'],
            'return_quantity' => ['required_with:transaction_id', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['transaction_ids'])) {
            $results = $this->inventoryOperations->receiveAssignmentReturns(
                $validated['transaction_ids'],
                (int) $request->user()->id,
            );
        } else {
            $results = $this->inventoryOperations->receiveAssignmentReturn(
                (int) $validated['transaction_id'],
                (int) $validated['return_quantity'],
                (int) $request->user()->id,
                $validated['notes'] ?? null,
                isset($validated['return_request_id']) ? (int) $validated['return_request_id'] : null,
                ['building' => $validated['building'] ?? null, 'room' => $validated['room'] ?? null],
            );
        }

        if (is_string($results)) {
            $errorField = isset($validated['transaction_ids']) ? 'transaction_ids' : 'return_quantity';

            return redirect()->back()->withErrors([$errorField => $results])->withInput();
        }

        $receivedCount = is_array($results) ? count($results) : 1;

        return redirect()->route('propertyCustodian.inventory')->with('success', $receivedCount . ' assignment return(s) received and recorded.');
    }

    public function sendToMaintenance(Request $request, $itemId)
    {
        $validated = $request->validate([
            'issue_description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $inventory = Inventory::findOrFail($itemId);
        $error = $this->inventoryOperations->sendToMaintenance(
            $inventory->item_id,
            (int) $request->user()->id,
            $validated['issue_description'],
            $validated['notes'] ?? null,
        );

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item sent to maintenance.');
    }

    public function markRepaired(Request $request, $itemId)
    {
        $validated = $request->validate([
            'repair_notes' => ['nullable', 'string', 'max:1000'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $error = $this->inventoryOperations->markRepaired(
            (int) $itemId,
            (int) $request->user()->id,
            $validated['repair_notes'] ?? null,
            isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
        );

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

        $error = $this->inventoryOperations->markReadyToDispose(
            (int) $itemId,
            (int) $request->user()->id,
            $validated['notes'],
        );

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
        $error = $this->inventoryOperations->dispose(
            (int) $itemId,
            (int) $request->user()->id,
            $validated['notes'] ?? null,
        );

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item disposed successfully.');
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
