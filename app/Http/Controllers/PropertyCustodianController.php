<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditLedgerService;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\ForecastAiRecommendationService;
use App\Services\ForecastDecisionSupportService;
use App\Services\ForecastExplanationService;
use App\Services\InventoryOperationService;
use App\Services\StoredDemandForecastService;
use App\Support\RequestableItemMatcher;
use Barryvdh\DomPDF\Facade\Pdf;
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
        protected ForecastDecisionSupportService $forecastDecisionSupport,
        protected ForecastAiRecommendationService $aiForecastRecommendations,
        protected AuditLedgerService $auditLedgerService,
        protected InventoryOperationService $inventoryOperations
    )
    {
    }

    public function dashboard(Request $request)
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

        // HAVING repeats the aggregate instead of referencing the select alias
        // `available_quantity`. MySQL resolves alias references in HAVING, but
        // PostgreSQL raises "column available_quantity does not exist" because it
        // resolves HAVING names against the table first. Repeating SUM(quantity)
        // is standard SQL and behaves identically on both drivers.
        $lowStockCount = Inventory::query()
            ->where('status', 'available')
            ->select('item_name')
            ->selectRaw('SUM(quantity) as available_quantity')
            ->groupBy('item_name')
            ->havingRaw('SUM(quantity) <= ?', [3])
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

        return response()->json([
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

    public function runForecast(Request $request)
    {
        $forecastResult = $this->storedForecastService->read($request->user());

        if ($request->expectsJson()) {
            return response()->json($forecastResult);
        }

        return redirect()->route('propertyCustodian.reports')->with('forecastResult', $forecastResult);
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
            $message = 'That information is not available for your role.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('propertyCustodian.reports')->with('error', $message);
        }

        $matchingRows = collect($forecastResult['rows'] ?? [])->filter(function (array $forecastRow) use ($validated): bool {
            return isset($validated['inventory_id'])
                ? (int) ($forecastRow['inventory_id'] ?? 0) === (int) $validated['inventory_id']
                : strtolower(trim($forecastRow['item_name'] ?? '')) === strtolower(trim($validated['item_name'] ?? ''));
        })->values();

        if ($matchingRows->count() > 1) {
            $identities = $matchingRows->pluck('inventory_id')->map(fn (int $id): string => (string) $id)->implode(', ');
            $message = "That item name matches multiple forecast records. Specify an inventory ID: {$identities}.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'matches' => $matchingRows->values()], 422);
            }

            return redirect()->route('propertyCustodian.reports')
                ->with('error', $message);
        }

        $row = $matchingRows->first();

        if ($row === null) {
            $message = 'No forecast result is available for that item.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return redirect()->route('propertyCustodian.reports')->with('error', $message);
        }

        $explanation = $this->forecastExplanationService->explain($user, $row);

        if (($explanation['status'] ?? null) === 'forbidden') {
            if ($request->expectsJson()) {
                return response()->json(['message' => $explanation['message']], 403);
            }

            return redirect()->route('propertyCustodian.reports')->with('error', $explanation['message']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'explanation' => $explanation['explanation'],
                'item' => $row['item_name'],
                // Passed through so the SPA can tell a Gemini-written
                // explanation from the deterministic one. It was previously
                // dropped here, and the client then assumed "local" and blamed
                // the provider for text it had never requested.
                'source' => $explanation['source'] ?? 'local',
                // The specific reason a local explanation was used. Without it the
                // client falls back to a generic caption, which reported
                // "calculated directly from the forecast" even when a reply had
                // been rejected for citing figures the forecast never produced.
                'provider_status' => $explanation['provider_status'] ?? null,
            ]);
        }

        return redirect()->route('propertyCustodian.reports')
            ->with('forecastExplanation', $explanation['explanation'])
            ->with('forecastExplanationItem', $row['item_name']);
    }

    /**
     * Forecast-only decision support. The ranked selection is produced locally by
     * ForecastDecisionSupportService, so `inventory_ids` always matches the rows
     * shown in the forecast table and later re-exported as a PDF.
     */
    public function forecastDecisionSupport(Request $request)
    {
        $validated = $request->validate([
            'prompt_type' => ['required', 'string', Rule::in(ForecastDecisionSupportService::PROMPT_TYPES)],
            // Follow-up context: ids only, never free prose, so a follow-up can
            // only narrow the previous answer to rows the forecast supplied.
            'inventory_ids' => ['nullable', 'array', 'max:'.ForecastDecisionSupportService::MAX_REQUESTED_ITEMS],
            'inventory_ids.*' => ['integer', 'min:1'],
            // A named count ("give me 5 items") narrows the same ranking. The
            // service clamps it to the hard ceiling and discloses the cap.
            'max_items' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $result = $this->forecastDecisionSupport->answer(
            $request->user(),
            $validated['prompt_type'],
            [
                'inventory_ids' => $validated['inventory_ids'] ?? [],
                'max_items' => $validated['max_items'] ?? null,
            ],
        );

        if (($result['status'] ?? null) === 'forbidden') {
            return response()->json(['message' => $result['message']], 403);
        }

        if (($result['status'] ?? null) !== 'success') {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    /**
     * Parses a free-text forecast question the chat's pattern list could not
     * classify, without answering it.
     *
     * The provider returns a strict form only — prompt type, item reference,
     * count — and the service validates every field against the question and
     * the prior answer. An unusable parse is reported as unresolved so the
     * chat falls back to its guidance message instead of guessing.
     */
    public function parseForecastQuestion(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'inventory_ids' => ['nullable', 'array', 'max:'.ForecastDecisionSupportService::MAX_REQUESTED_ITEMS],
            'inventory_ids.*' => ['integer', 'min:1'],
            'item_names' => ['nullable', 'array', 'max:'.ForecastDecisionSupportService::MAX_REQUESTED_ITEMS],
            'item_names.*' => ['string', 'max:255'],
        ]);

        $user = $request->user();
        $parse = $this->forecastDecisionSupport->parseQuestion(
            $user,
            $validated['message'],
            $validated['inventory_ids'] ?? [],
            $validated['item_names'] ?? [],
        );

        if (! is_array($parse)) {
            return response()->json([
                'status' => 'unresolved',
                'message' => 'That question was not understood. Name an item or pick a question below.',
            ]);
        }

        return response()->json(['status' => 'ok', 'parse' => $parse]);
    }

    /**
     * AI recommendations covering every row in the stored forecast.
     *
     * Unlike decision support, which answers one question about at most ten
     * rows, this covers the whole forecast: every row is sorted into exactly one
     * work queue and given a plain-English sentence. Results are cached against
     * the forecast's own generation stamp, so a cycle is written once and reused
     * until the model retrains. `refresh` forces a rewrite for that cycle.
     *
     * The endpoint creates nothing. It returns advice.
     */
    public function forecastAiRecommendations(Request $request)
    {
        $validated = $request->validate([
            'refresh' => ['nullable', 'boolean'],
        ]);

        $result = $this->aiForecastRecommendations->recommendations(
            $request->user(),
            (bool) ($validated['refresh'] ?? false),
        );

        if (($result['status'] ?? null) === 'forbidden') {
            return response()->json(['message' => $result['message']], 403);
        }

        if (($result['status'] ?? null) !== 'success') {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json($result);
    }

    /**
     * Renders an AI-recommended procurement list as a signed PDF.
     *
     * The rows are re-read from the stored forecast rather than trusted from the
     * client, so the printed quantities always match the model output. IDs that
     * are no longer forecast (deleted items, stale training run) are reported
     * instead of being silently dropped.
     */
    public function forecastProcurementListPdf(Request $request)
    {
        $validated = $request->validate([
            'inventory_ids' => ['required', 'array', 'min:1', 'max:'.ForecastDecisionSupportService::MAX_REQUESTED_ITEMS],
            'inventory_ids.*' => ['required', 'integer', 'min:1'],
            'prompt_type' => ['nullable', 'string', Rule::in(ForecastDecisionSupportService::PROMPT_TYPES)],
        ]);

        $forecast = $this->storedForecastService->read($request->user());

        if (($forecast['status'] ?? null) === 'forbidden') {
            return response()->json(['message' => 'That information is not available for your role.'], 403);
        }

        if (($forecast['status'] ?? null) !== 'success') {
            return response()->json([
                'message' => 'No trained production ML forecast is available, so no procurement list can be produced.',
            ], 422);
        }

        $rowsById = collect($forecast['rows'] ?? [])->keyBy('inventory_id');
        $items = [];
        $missing = [];

        // The client's ordering is preserved so the printed list matches the
        // ranking the assistant presented.
        foreach ($validated['inventory_ids'] as $inventoryId) {
            $row = $rowsById->get($inventoryId);

            if (! is_array($row)) {
                $missing[] = (int) $inventoryId;

                continue;
            }

            $items[] = $row;
        }

        if ($missing !== []) {
            return response()->json([
                'message' => 'These items are no longer in the current forecast, so the list cannot be produced: '
                    .implode(', ', $missing).'. Retrain the model, then ask again.',
                'missing_inventory_ids' => $missing,
            ], 422);
        }

        $promptType = $validated['prompt_type'] ?? ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST;
        $generatedAt = is_string($forecast['generated_at'] ?? null)
            ? Carbon::parse($forecast['generated_at'])
            : null;
        $period = $forecast['forecast_period'] ?? 'Forecast period unavailable';

        $pdf = Pdf::loadView('pdfs.procurement-priority-list', [
            'items' => $items,
            'question' => ForecastDecisionSupportService::questionLabel($promptType),
            'forecastPeriod' => $period,
            'generatedAt' => $generatedAt?->format('F j, Y g:i A'),
            'preparedBy' => trim($request->user()->first_name.' '.$request->user()->last_name)
                ?: $request->user()->username,
            'itemCount' => count($items),
            'totalUnits' => (int) collect($items)->sum(fn (array $row): int => (int) ($row['suggested_procurement'] ?? 0)),
        ]);

        $slug = str($period)->slug('-')->value() ?: 'forecast';

        return $pdf->download("procurement-priority-list-{$slug}.pdf");
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

        return $this->viewOrJson($request, 'pages.propertyCustodian.forecast-details', [
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
        // Default is month-to-date (the 1st through today), so the range rolls
        // into the new month on its own. Explicit dates still win.
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        return response()->json(array_merge(
            ['title' => 'Property Custodian Reports'],
            $this->reportPayload($request->user(), $dateFrom, $dateTo),
            ['reportFilters' => ['date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString()]],
        ));
    }

    /**
     * The report dataset shared by the JSON page and the printable PDF.
     *
     * One computation serves both surfaces so the downloaded report can never
     * disagree with the screen. Anything added here appears in both; anything
     * that must stay screen-only belongs in the Vue layer instead.
     *
     * @return array<string, mixed>
     */
    private function reportPayload(User $user, Carbon $dateFrom, Carbon $dateTo): array
    {
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

        // Closest end-of-life dates first, so the report leads with what needs
        // a procurement decision soonest. Grouped by item: per-record rows
        // carry no meaningful quantity once units are issued out (the row
        // quantity drops to zero and the units live in transactions), so the
        // list aggregates like the low-stock panel instead of printing zeros.
        $expiringData = $activeInventory
            ->filter(fn ($item) => $item->expected_end_date !== null)
            ->groupBy(fn ($item) => $item->item_name.'|'.$item->category_id)
            ->map(function ($items) {
                $earliest = $items->sortBy(fn ($item) => $item->expected_end_date->timestamp)->first();

                return [
                    'item_name' => $earliest->item_name,
                    'category' => $earliest->category?->category_name ?? 'Uncategorized',
                    'expected_end_date' => $earliest->expected_end_date->format('Y-m-d'),
                    'quantity' => (int) $items->sum('quantity'),
                    'tone' => $earliest->lifespan_status === 'end_of_useful_life' ? 'expired'
                        : ($earliest->lifespan_status === 'approaching_end_of_life' ? 'approaching' : 'healthy'),
                ];
            })
            ->sortBy(fn ($row) => $row['expected_end_date'])
            ->take(8)
            ->values();

        // Who holds custody stock, from the assignment transactions themselves.
        // Inventory rows cannot answer this: once units are issued out the row
        // quantity is zero and the units live in transactions, so summing rows
        // reported every holder as holding nothing.
        $assignedTransactions = Transaction::query()
            ->with(['user:id,first_name,last_name'])
            ->where('status', 'assigned')
            ->get();
        $holderData = $assignedTransactions
            ->groupBy(fn ($transaction) => $transaction->manual_recipient_name
                ?? ($transaction->user_id ? 'user:'.$transaction->user_id : 'unlinked'))
            ->map(function ($transactions, $key) {
                $first = $transactions->first();

                return [
                    'holder_name' => $first->manual_recipient_name
                        ?? ($first->user
                            ? (trim($first->user->first_name.' '.$first->user->last_name) ?: $first->user->username)
                            : 'Unlinked records'),
                    'quantity' => (int) $transactions->sum('quantity'),
                ];
            })
            ->sortByDesc('quantity')
            ->take(8)
            ->values();

        // Assigned transactions past their expected return date, oldest first.
        $overdueQuery = Transaction::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name'])
            ->where('status', 'assigned')
            ->whereDate('expected_return_date', '<', $today);
        $overdueCount = (clone $overdueQuery)->count();
        $overdueData = $overdueQuery
            ->orderBy('expected_return_date')
            ->limit(8)
            ->get()
            ->map(fn ($transaction) => [
                'item_name' => $transaction->item?->item_name ?? 'Unknown item',
                'holder_name' => $transaction->manual_recipient_name
                    ?? ($transaction->user
                        ? (trim($transaction->user->first_name.' '.$transaction->user->last_name) ?: $transaction->user->username)
                        : 'Unknown holder'),
                'expected_return_date' => $transaction->expected_return_date?->format('Y-m-d'),
                'quantity' => (int) $transaction->quantity,
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

        $liveForecastResult = $this->storedForecastService->read($user);
        $demoForecastResult = $this->storedForecastService->readDemo($user);

        return [
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
            'expiringData' => $expiringData,
            'holderData' => $holderData,
            'overdueData' => $overdueData,
            'overdueCount' => (int) $overdueCount,
            'recentTransactions' => $recentTransactions,
            'liveForecastResult' => $liveForecastResult,
            'demoForecastResult' => $demoForecastResult,
        ];
    }

    /**
     * Downloads the report page as a clean, card-style PDF.
     *
     * Browser printing cannot reproduce the screen faithfully — charts do not
     * render and grid layouts split across pages — so the printable document
     * is rendered server-side from the same dataset as the page. Tables
     * replace charts; every figure matches the screen by construction.
     */
    public function reportsSummaryPdf(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->startOfMonth()->startOfDay();
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        $payload = $this->reportPayload($request->user(), $dateFrom, $dateTo);

        $forecastRows = collect(($payload['liveForecastResult'] ?? [])['rows'] ?? []);
        $gaps = $forecastRows->filter(fn (array $row): bool => ($row['needs_procurement'] ?? false) === true);

        $pdf = Pdf::loadView('pdfs.custodian-report-summary', array_merge($payload, [
            'periodLabel' => $dateFrom->format('M d, Y').' to '.$dateTo->format('M d, Y'),
            'generatedAt' => now()->format('F j, Y g:i A'),
            'preparedBy' => trim($request->user()->first_name.' '.$request->user()->last_name)
                ?: $request->user()->username,
            'forecastAvailable' => (($payload['liveForecastResult'] ?? [])['status'] ?? null) === 'success',
            'forecastPeriod' => ($payload['liveForecastResult'] ?? [])['forecast_period'] ?? null,
            'forecastGaps' => $gaps->count(),
            'forecastWeak' => $gaps->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'success'
                || in_array($row['confidence'] ?? null, ['Low', 'Medium'], true))->count(),
        ]));

        $slug = $dateFrom->format('Ymd').'-'.$dateTo->format('Ymd');

        return $pdf->download("custodian-report-summary-{$slug}.pdf");
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
        // Performance: a single GROUP BY status aggregate serves the tab badges
        // and the metric cards. Previously each status ran its own full-table
        // GROUP BY scan plus PHP-side summation (13 aggregate queries).
        // Performance: a single GROUP BY status aggregate serves the tab badges and the
        // metric cards. Custody-based statuses (assigned, and items flagged for
        // inspection while still with a user) read the assignment transaction
        // because inventory.quantity is 0 once units are issued out. Deriving both
        // from the same join keeps Assigned and Under Inspection mutually exclusive,
        // so nothing is double counted in the total.
        $activeAssignmentsForTotals = Transaction::query()
            ->select('item_id')
            ->selectRaw('SUM(quantity) as active_assigned_quantity')
            ->where('status', 'assigned')
            ->groupBy('item_id');
        $statusQuantities = Inventory::query()
            ->leftJoinSub($activeAssignmentsForTotals, 'active_assignments', function ($join): void {
                $join->on('inventory.item_id', '=', 'active_assignments.item_id');
            })
            ->select('inventory.status')
            ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END) as total_quantity")
            ->whereNotNull('inventory.status')
            ->groupBy('inventory.status')
            ->pluck('total_quantity', 'status');
        $quantityForStatus = fn (string $status): int => (int) ($statusQuantities[$status] ?? 0);

        // Flagged records whose units are still issued out. They belong to both the
        // Assigned workspace and the Under Inspection workspace, so the Assigned
        // figures add them on top of the plain `assigned` quantity. They are also
        // already inside the per-status `under_inspection` quantity used for
        // `attention`, and `total` remains the de-duplicated sum of statuses.
        //
        // "Still issued out" is an active assignment transaction, not
        // `assigned_to_user_id`: a manual issue is assigned to an external
        // recipient with no users row, so it has a transaction but a null holder.
        $custodyFlaggedInspectionQuantity = (int) (Inventory::query()
            ->leftJoinSub($activeAssignmentsForTotals, 'active_assignments', function ($join): void {
                $join->on('inventory.item_id', '=', 'active_assignments.item_id');
            })
            ->where('inventory.status', 'under_inspection')
            ->whereNotNull('active_assignments.item_id')
            ->selectRaw('COALESCE(SUM(COALESCE(active_assignments.active_assigned_quantity, inventory.quantity)), 0) as total_quantity')
            ->value('total_quantity') ?? 0);

        $inventoryMetrics = [
            'total' => (int) $statusQuantities->except('disposed')->map(fn ($total) => (int) $total)->sum(),
            'available' => (int) ($statusQuantities['available'] ?? 0),
            'assigned' => (int) ($statusQuantities['assigned'] ?? 0) + $custodyFlaggedInspectionQuantity,
            'attention' => (int) ($statusQuantities['under_inspection'] ?? 0) + (int) ($statusQuantities['under_maintenance'] ?? 0),
        ];
        $statuses = ['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'];
        $activeWorkspace = in_array($request->query('workspace', 'all'), ['all', 'available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'], true)
            ? $request->query('workspace', 'all')
            : 'all';
        // A record flagged for inspection while its units are still issued out is both
        // assigned and awaiting inspection. Flagging only rewrites `status`, so a
        // plain status equality filter drops it out of the Assigned workspace and
        // the holder appears to have lost the item. Assigned therefore also matches
        // flagged records that still carry an active assignment transaction, which
        // is what separates "flagged while assigned" from "flagged from stock" —
        // the latter has no transaction and stays in Under Inspection only. Under
        // Inspection keeps every flagged record, so the item stays visible in both
        // workspaces until markInspected() restores `status_before`. Totals are still
        // derived per status, so nothing is double counted in the overall total.
        $workspaceStatuses = [
            'available' => ['available'],
            'under_maintenance' => ['under_maintenance'],
            'under_inspection' => ['under_inspection'],
            'ready_to_dispose' => ['ready_to_dispose'],
            'disposed' => ['disposed'],
        ];
        // Rows are collapsed so identical stock reads as a single line. The key is
        // (name, category, unit, status, ics_no) so units sharing an ICS slip —
        // e.g. four Epson units under ICS-2026-2 — render as one `4 pieces` row
        // with each serial kept in the Records modal. Different ICS numbers stay
        // separate rows, and rows without an ICS (NULL/"") group together.
        $groupKey = fn ($itemName, $categoryId, $unit, $status, $icsNo): string => implode('|', [
            (string) $itemName,
            (string) $categoryId,
            (string) $unit,
            (string) ($status ?? ''),
            (string) ($icsNo ?? ''),
        ]);
        $buildInventoryQuery = function () {
            $activeAssignments = Transaction::query()
                ->select('item_id')
                ->selectRaw('SUM(quantity) as active_assigned_quantity')
                ->where('status', 'assigned')
                ->groupBy('item_id');

            return Inventory::query()
                ->leftJoinSub($activeAssignments, 'active_assignments', function ($join): void {
                    $join->on('inventory.item_id', '=', 'active_assignments.item_id');
                })
                ->with('category:category_id,category_name,is_maintenance_eligible,requires_serial_number')
                ->select('inventory.item_name', 'inventory.category_id', 'inventory.unit', 'inventory.status', 'inventory.ics_no')
                ->selectRaw('MIN(inventory.item_id) as item_id')
                ->selectRaw('MIN(inventory.item_id) as source_item_id')
                ->selectRaw('MIN(inventory.inventory_item_no) as inventory_item_no')
                ->selectRaw('MAX(inventory.description) as description')
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END) as quantity")
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END * inventory.unit_cost) as total_cost")
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END * inventory.unit_cost) / NULLIF(SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END), 0) as unit_cost")
                ->selectRaw('MAX(inventory.date_acquired) as date_acquired')
                ->selectRaw('MAX(inventory.lifespan_years) as lifespan_years')
                ->selectRaw('MAX(inventory.expected_end_date) as expected_end_date')
                ->selectRaw('COUNT(*) as source_count')
                ->groupBy('inventory.item_name', 'inventory.category_id', 'inventory.unit', 'inventory.status', 'inventory.ics_no')
                ->orderBy('inventory.item_name');
        };
        $paginateInventory = function ($query, string $pageName) {
            return $query->paginate(25, ['*'], $pageName)->withQueryString();
        };
        $allInventoryPage = $paginateInventory(
            $buildInventoryQuery()->where(function ($query): void {
                $query->whereNull('inventory.status')
                    ->orWhere('inventory.status', '!=', 'disposed');
            }),
            'all_page',
        );
        $inventoryPages = collect($statuses)
            ->mapWithKeys(function (string $status) use ($buildInventoryQuery, $paginateInventory, $workspaceStatuses): array {
                $query = $buildInventoryQuery();

                if ($status === 'assigned') {
                    // Custody: either a plain assigned record, or a flagged one whose
                    // units are still issued out on an active assignment transaction.
                    $query->whereIn('inventory.status', Inventory::CUSTODY_STATUSES)
                        ->where(function ($scope): void {
                            $scope->where('inventory.status', 'assigned')
                                ->orWhereNotNull('active_assignments.item_id');
                        });
                } else {
                    $query->where('status', $workspaceStatuses[$status][0] ?? $status);
                }

                return [$status => $paginateInventory($query, $status . '_page')];
            });
        $inventoryItems = $allInventoryPage->getCollection()
            ->toBase()
            ->merge($inventoryPages->flatMap(fn ($page) => $page->getCollection()->toBase()))
            ->unique(fn ($item): string => $groupKey($item->item_name, $item->category_id, $item->unit, $item->status, $item->ics_no))
            ->values();
        $inventoryByStatus = $inventoryItems->groupBy('status');
        $inventoryStatusCounts = collect($statuses)
            ->mapWithKeys(fn (string $status): array => [$status => $quantityForStatus($status)]);
        // The Assigned workspace lists the custody statuses, so its badge has to
        // match that list rather than the single `assigned` status bucket.
        $inventoryStatusCounts['assigned'] = (int) $inventoryStatusCounts['assigned'] + $custodyFlaggedInspectionQuantity;
        // Performance: hydrate source rows only for the groups actually rendered
        // on this page (the 7 paginators above), instead of the entire inventory
        // table with 5 eager relations each. Group membership is an OR of
        // (item_name, category_id, unit, status, ics_no) tuples taken from
        // the page rows — per-unit serials stay inside the group's sourceItems.
        $displayedGroups = $inventoryItems
            ->map(fn (Inventory $item): array => [
                'item_name' => $item->item_name,
                'category_id' => $item->category_id,
                'unit' => $item->unit,
                'status' => $item->status ?? '',
                'ics_no' => $item->ics_no ?? '',
            ])
            ->unique(fn (array $group): string => implode("\0", [
                (string) $group['item_name'],
                (string) $group['category_id'],
                (string) $group['unit'],
                (string) $group['status'],
                (string) $group['ics_no'],
            ]))
            ->values();
        $sourceItemsByGroup = Inventory::query()
            ->with(['category:category_id,category_name,is_maintenance_eligible,requires_serial_number', 'assignedTo', 'latestMaintenance', 'latestStockMovement', 'latestDisposalMovement'])
            ->orderBy('item_name')
            ->orderBy('item_id')
            ->where(function ($query) use ($displayedGroups): void {
                if ($displayedGroups->isEmpty()) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                foreach ($displayedGroups as $group) {
                    $query->orWhere(function ($query) use ($group): void {
                        $query->where('item_name', $group['item_name'])
                            ->where('category_id', $group['category_id'])
                            ->where('unit', $group['unit'])
                            ->where('status', $group['status'])
                            ->where(function ($ics) use ($group): void {
                                // NULL and "" read as the same "no ICS" group key.
                                if ($group['ics_no'] === '') {
                                    $ics->whereNull('ics_no')->orWhere('ics_no', '');

                                    return;
                                }

                                $ics->where('ics_no', $group['ics_no']);
                            });
                    });
                }
            })
            ->get()
            ->groupBy(fn (Inventory $item) => $groupKey($item->item_name, $item->category_id, $item->unit, $item->status, $item->ics_no));

        // Performance: the maintenance picker lists every available row, so it is
        // sourced from the pool above (already hydrated with relations) plus a
        // lean top-up of the available rows outside the displayed groups. This
        // avoids hydrating displayed rows twice while keeping the full list.
        // The eligibility filter is identical to the previous implementation.
        $eligibleForMaintenance = fn ($sourceItems): bool => (bool) (($sourceItem = $sourceItems->first())
            && $sourceItem->status === 'available'
            && $sourceItem->category?->is_maintenance_eligible !== false);
        $displayedAvailableGroups = $displayedGroups->filter(fn (array $group): bool => $group['status'] === 'available')->values();
        $maintenanceRemainderQuery = Inventory::query()
            ->with('category:category_id,category_name,is_maintenance_eligible')
            ->where('status', 'available')
            ->orderBy('item_name')
            ->orderBy('item_id');

        if ($displayedAvailableGroups->isNotEmpty()) {
            // Exclude exactly the displayed groups (including their ICS).
            $maintenanceRemainderQuery->whereNot(function ($query) use ($displayedAvailableGroups): void {
                foreach ($displayedAvailableGroups as $group) {
                    $query->orWhere(function ($query) use ($group): void {
                        $query->where('item_name', $group['item_name'])
                            ->where('category_id', $group['category_id'])
                            ->where('unit', $group['unit'])
                            ->where(function ($ics) use ($group): void {
                                if ($group['ics_no'] === '') {
                                    $ics->whereNull('ics_no')->orWhere('ics_no', '');

                                    return;
                                }

                                $ics->where('ics_no', $group['ics_no']);
                            });
                    });
                }
            });
        }

        $maintenanceItems = $sourceItemsByGroup
            ->toBase()
            ->filter($eligibleForMaintenance)
            ->merge(
                $maintenanceRemainderQuery->get()
                    ->groupBy(fn (Inventory $item) => $groupKey($item->item_name, $item->category_id, $item->unit, $item->status, $item->ics_no))
                    ->toBase()
                    ->filter($eligibleForMaintenance)
            )
            ->sortBy(fn ($sourceItems): string => $sourceItems->first()->item_name . "\0" . str_pad((string) $sourceItems->first()->item_id, 10, '0', STR_PAD_LEFT))
            ->values();

        // Performance: assignment details are only needed for the source rows
        // above, so scope the transaction hydration to those item IDs instead
        // of loading every assigned transaction in the system.
        $scopedSourceIds = $sourceItemsByGroup->flatten()->pluck('item_id')->unique()->values();
        $activeAssignmentsQuery = Transaction::query()
            ->with([
                'user:id,first_name,last_name,username',
                'assignmentRequests:id,transaction_id,item_id,user_id,target_user_id,quantity,status',
                'assignmentReturns:id,transaction_id,quantity',
            ])
            ->where('status', 'assigned')
            ->where('quantity', '>', 0)
            ->latest('transaction_date')
            ->latest('id');

        if ($scopedSourceIds->isNotEmpty()) {
            $activeAssignmentsQuery->whereIn('item_id', $scopedSourceIds);
        } else {
            $activeAssignmentsQuery->whereRaw('0 = 1');
        }

        $activeAssignments = $activeAssignmentsQuery->get()->groupBy('item_id');

        $attachGroupDetails = function ($collection) use ($sourceItemsByGroup, $activeAssignments, $groupKey): void {
            $collection->each(function (Inventory $inventoryItem) use ($sourceItemsByGroup, $activeAssignments, $groupKey): void {
                $matchedSourceItems = $sourceItemsByGroup->get(
                    $groupKey(
                        $inventoryItem->item_name,
                        $inventoryItem->category_id,
                        $inventoryItem->unit,
                        $inventoryItem->status,
                        $inventoryItem->ics_no,
                    ),
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
                        ->filter(fn (Transaction $transaction) => (int) $transaction->quantity > 0
                            && ($transaction->user_id || ($transaction->manual_recipient_name && $transaction->expected_return_date)))
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
                                'recipient' => $transaction->user?->full_name
                                    ?? $transaction->user?->username
                                    ?? $transaction->manual_recipient_name
                                    ?? 'Unknown recipient',
                                'issued_quantity' => max((int) $transaction->issued_quantity, (int) $transaction->quantity + $returnedQuantity),
                                'returned_quantity' => $returnedQuantity,
                                'remaining_quantity' => (int) $transaction->quantity,
                                'transaction_date' => $transaction->transaction_date?->toDateString(),
                                'expected_return_date' => $transaction->expected_return_date?->toDateString(),
                                'building' => $transaction->building ?? $sourceItem->building,
                                'room' => $transaction->room ?? $sourceItem->room,
                                'from_building' => $transaction->from_building,
                                'from_room' => $transaction->from_room,
                                'is_manual_return' => (bool) $transaction->manual_recipient_name,
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

        // Edit context is keyed by the wider (name, category, unit, status) tuple on
        // purpose, not by the listing group key. editInventory() — the endpoint the
        // modal actually calls — returns every serial sharing that tuple, and
        // updateInventory() validates `serial_numbers` against the whole tuple. The
        // listing key adds ics_no so slips sharing a name render as one row per
        // slip, which must not narrow the set of serials an edit can address.
        $editItemDetails = collect();
        $sourceItemsByGroup
            ->flatten(1)
            ->groupBy(fn (Inventory $item) => $item->item_name . '|' . $item->category_id . '|' . $item->unit . '|' . ($item->status ?? ''))
            ->each(function ($items) use ($editItemDetails): void {
                $serialNumbers = $items->pluck('serial_number')->filter()->values();
                $groupQuantity = (int) $items->sum('quantity');

                $items->each(function (Inventory $item) use ($editItemDetails, $serialNumbers, $groupQuantity): void {
                    $editItemDetails->put((string) $item->item_id, [
                        'serialNumbers' => $serialNumbers,
                        'quantity' => $groupQuantity > 0 ? $groupQuantity : (int) $item->quantity,
                    ]);
                });
            });

        return response()->json(compact(
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

        if ($request->expectsJson()) {
            return response()->json([
                'file' => $fileName,
                'count' => $items->count(),
                'rows' => $items->map(fn (Inventory $item) => [
                    'item_no' => $item->inventory_item_no,
                    'item_name' => $item->item_name,
                    'category' => $item->category?->category_name,
                    'serial_number' => $item->serial_number,
                    'quantity' => (int) $item->quantity,
                    'unit' => $item->unit,
                    'unit_cost' => (float) $item->unit_cost,
                    'total_cost' => (float) $item->total_cost,
                    'status' => $item->status,
                    'assigned_to' => $item->assignedTo?->full_name,
                    'date_acquired' => $item->date_acquired?->format('Y-m-d'),
                    'expected_end_date' => $item->expected_end_date?->format('Y-m-d'),
                    'ics_no' => $item->ics_no,
                ])->values(),
            ]);
        }

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
    public function transactions(Request $request)
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

        // Incoming requests from End Users requiring Custodian action.
        // Includes unmet demand (an item requested when stock was zero). It sits
        // in the same tab because it is the same request queue; the only
        // difference is that there is nothing to allocate, so the row offers
        // decline alone. See EndUserController::STATUS_WAITING_FOR_PROCUREMENT.
        $incomingRequests = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username,email',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit,serial_number',
                'item.category:category_id,category_name',
                'requestedCategory:category_id,category_name,requires_serial_number',
            ])
            ->whereIn('status', ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT])
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $incomingRequests->each(function (AssignmentRequest $request): void {
            if ($request->requested_item_name) {
                // Matched through the shared matcher rather than an exact
                // name+unit equality, so a request recorded as "chair" with a
                // placeholder unit still recognises "Chairs" in stock once the
                // custodian stocks it. Without this, stock health would stay at
                // zero forever and the row could never become assignable.
                $matchingItems = RequestableItemMatcher::candidates(
                    (string) $request->requested_item_name,
                    (int) $request->requested_category_id,
                    $request->requested_unit,
                )->filter(fn (Inventory $item): bool => $item->status === 'available' && (int) $item->quantity > 0)
                    ->values();

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

        // Ledger details come from the shared builder so the custodian ledger
        // and the school-head audit view render byte-identical text. The
        // frontend groups adjacent identical rows, which is only honest when
        // both pages describe movements the same way.
        $auditLedger = $this->auditLedgerService->latest(25);

        return response()->json([
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

        // An unmet request becomes fulfillable as soon as the custodian stocks the
        // item, so it is approvable too. Approving it now allocates real stock
        // and creates the normal fulfillment rows; the unmet status only ever
        // meant "there was nothing to allocate yet".
        if (! in_array($assignmentRequest->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT], true)) {
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

        // Unmet demand is declinable too. It was never approvable — there is no
        // stock to allocate — so decline is its only custodian action.
        if (! in_array($assignmentRequest->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT], true)) {
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
            'date_acquired' => ['required', 'date', 'before_or_equal:today'],
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

            if ($category->requires_qr_code) {
                for ($index = 0; $index < (int) $validated['quantity']; $index++) {
                    $this->inventoryOperations->stockIn([
                        ...$itemAttributes,
                        'quantity' => 1,
                        'serial_number' => null,
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

    public function editInventory(Request $request, Inventory $inventory)
    {
        $inventory->loadMissing('category');
        $serialNumbers = $inventory->category?->requires_serial_number
            ? Inventory::query()
                ->where('item_name', $inventory->item_name)
                ->where('category_id', $inventory->category_id)
                ->where('unit', $inventory->unit)
                ->where('status', $inventory->status)
                ->orderBy('item_id')
                ->pluck('serial_number')
                ->map(fn ($serialNumber) => $serialNumber ?? '')
                ->values()
            : collect(array_filter([$inventory->serial_number]));

        return response()->json([
            'title' => 'Edit Inventory Item',
            'inventoryItem' => $inventory,
            'categories' => Category::orderBy('category_name')->get(),
            'serialNumbers' => $serialNumbers,
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
            'date_acquired' => ['required', 'date', 'before_or_equal:today'],
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

        $serialNumbers = null;
        if ($isSerialized) {
            $groupItemIds = $groupItems->pluck('item_id')->all();
            $serialData = $request->validate([
                'serial_numbers' => ['required', 'array', 'size:' . count($groupItemIds)],
                'serial_numbers.*' => [
                    'required',
                    'string',
                    'max:255',
                    'distinct',
                    Rule::unique('inventory', 'serial_number')->whereNotIn('item_id', $groupItemIds),
                ],
            ]);
            $serialNumbers = $serialData['serial_numbers'];
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
            $serialNumbers,
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

            if ($this->inventoryHasLinkedHistory($items->modelKeys())) {
                return redirect()->route('propertyCustodian.inventory')->with(
                    'error',
                    'One or more selected items have request or assignment history and cannot be deleted. Return assigned items if needed, then dispose of them to preserve their records.'
                );
            }

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

        if ($this->inventoryHasLinkedHistory([$inventory->item_id])) {
            return redirect()->route('propertyCustodian.inventory')->with(
                'error',
                'This item has request or assignment history and cannot be deleted. Return it if assigned, then dispose of it to preserve its records.'
            );
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

    private function inventoryHasLinkedHistory(array $itemIds): bool
    {
        if ($itemIds === []) {
            return false;
        }

        return DB::table('requests')->whereIn('item_id', $itemIds)->exists()
            || DB::table('transactions')->whereIn('item_id', $itemIds)->exists()
            || DB::table('maintenance_records')->whereIn('inventory_id', $itemIds)->exists()
            || DB::table('assignment_returns')->whereIn('inventory_id', $itemIds)->exists();
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
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'issue_description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['inventory_ids'])) {
            $itemIds = array_map('intval', $validated['inventory_ids']);
            if (! in_array((int) $itemId, $itemIds, true)) {
                return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
            }

            $error = $this->inventoryOperations->sendItemsToMaintenance(
                $itemIds,
                (int) $request->user()->id,
                $validated['issue_description'],
                $validated['notes'] ?? null,
            );
            $successMessage = count($itemIds) . ' inventory item(s) sent to maintenance.';
        } else {
            $inventory = Inventory::findOrFail($itemId);
            $error = $this->inventoryOperations->sendToMaintenance(
                $inventory->item_id,
                (int) $request->user()->id,
                $validated['issue_description'],
                $validated['notes'] ?? null,
            );
            $successMessage = 'Item sent to maintenance.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function sendToInspection(Request $request, $itemId)
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $actorId = (int) $request->user()->id;
        $reason = isset($validated['reason']) && $validated['reason'] !== ''
            ? $validated['reason']
            : null;

        if (isset($validated['inventory_ids'])) {
            $itemIds = array_map('intval', $validated['inventory_ids']);
            if (! in_array((int) $itemId, $itemIds, true)) {
                return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
            }

            $error = $this->inventoryOperations->sendItemsToInspection($itemIds, $actorId, $reason);
            $successMessage = count($itemIds) . ' inventory item(s) sent for inspection.';
        } else {
            $inventory = Inventory::findOrFail($itemId);
            $error = $this->inventoryOperations->sendToInspection($inventory->item_id, $actorId, $reason);
            $successMessage = 'Item sent for inspection.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function markRepaired(Request $request, $itemId)
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'repair_notes' => ['nullable', 'string', 'max:1000'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($validated['inventory_ids'])) {
            $itemIds = array_map('intval', $validated['inventory_ids']);
            if (! in_array((int) $itemId, $itemIds, true)) {
                return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
            }

            $error = $this->inventoryOperations->markItemsRepaired(
                $itemIds,
                (int) $request->user()->id,
                $validated['repair_notes'] ?? null,
                isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
            );
            $successMessage = count($itemIds) . ' item(s) marked as repaired and returned to available inventory.';
        } else {
            $error = $this->inventoryOperations->markRepaired(
                (int) $itemId,
                (int) $request->user()->id,
                $validated['repair_notes'] ?? null,
                isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
            );
            $successMessage = 'Item marked as repaired and now available.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function markReadyToDispose(Request $request, $itemId)
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if (isset($validated['inventory_ids'])) {
            $itemIds = array_map('intval', $validated['inventory_ids']);
            if (! in_array((int) $itemId, $itemIds, true)) {
                return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
            }

            $error = $this->inventoryOperations->markItemsReadyToDispose(
                $itemIds,
                (int) $request->user()->id,
                $validated['notes'],
            );
            $successMessage = count($itemIds) . ' item(s) marked ready for disposal.';
        } else {
            $error = $this->inventoryOperations->markReadyToDispose(
                (int) $itemId,
                (int) $request->user()->id,
                $validated['notes'],
            );
            $successMessage = 'Item marked ready for disposal.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function disposeInventory(Request $request, $itemId)
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['inventory_ids'])) {
            $itemIds = array_map('intval', $validated['inventory_ids']);
            if (! in_array((int) $itemId, $itemIds, true)) {
                return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
            }

            $error = $this->inventoryOperations->disposeItems(
                $itemIds,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
            );
            $successMessage = count($itemIds) . ' inventory item(s) disposed successfully.';
        } else {
            $error = $this->inventoryOperations->dispose(
                (int) $itemId,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
            );
            $successMessage = 'Item disposed successfully.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
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
        ];

        // Full detail only for Property Custodian role
        if ($user->role && $user->role->role_name === 'Property Custodian') {
            $activeAssignments = $item->status === 'assigned'
                ? Transaction::query()
                    ->with(['user:id,first_name,last_name,username', 'assignmentReturns:id,transaction_id,quantity'])
                    ->where('item_id', $item->item_id)
                    ->where('status', 'assigned')
                    ->where('quantity', '>', 0)
                    ->latest('transaction_date')
                    ->latest('id')
                    ->get()
                : collect();
            $payload['quantity']         = $item->status === 'assigned'
                ? (int) $activeAssignments->sum('quantity')
                : $item->quantity;
            $payload['unit']             = $item->unit;
            $payload['unit_cost']        = $item->unit_cost;
            $payload['ics_no']           = $item->ics_no;
            $payload['date_acquired']    = $item->date_acquired?->toDateString();
            $payload['lifespan_years']   = $item->lifespan_years;
            $payload['expected_end_date'] = $item->expected_end_date?->toDateString();
            $payload['lifespan_status']  = $item->lifespan_status;
            $payload['assigned_to']      = $item->status === 'assigned'
                ? $activeAssignments
                    ->groupBy(fn (Transaction $transaction) => $transaction->user?->full_name
                        ?? $transaction->user?->username
                        ?? $transaction->manual_recipient_name
                        ?? 'Recorded assignee')
                    ->map(fn ($transactions, $name) => $name . ' (' . (int) $transactions->sum('quantity') . ')')
                    ->implode(', ')
                : null;
            $payload['return_assignments'] = $activeAssignments
                ->filter(fn (Transaction $transaction) => (int) $transaction->quantity > 0
                    && ($transaction->user_id || ($transaction->manual_recipient_name && $transaction->expected_return_date)))
                ->map(function (Transaction $transaction) use ($item): array {
                    $returnedQuantity = (int) $transaction->assignmentReturns->sum('quantity');

                    return [
                        'transaction_id' => $transaction->id,
                        'inventory_id' => $item->item_id,
                        'inventory_item_no' => $item->inventory_item_no,
                        'recipient' => $transaction->user?->full_name
                            ?? $transaction->user?->username
                            ?? $transaction->manual_recipient_name
                            ?? 'Unknown recipient',
                        'issued_quantity' => max((int) $transaction->issued_quantity, (int) $transaction->quantity + $returnedQuantity),
                        'returned_quantity' => $returnedQuantity,
                        'remaining_quantity' => (int) $transaction->quantity,
                        'unit' => $item->unit,
                        'serial_number' => $item->serial_number,
                        'transaction_date' => $transaction->transaction_date?->toDateString(),
                        'expected_return_date' => $transaction->expected_return_date?->toDateString(),
                        'building' => $transaction->building ?? $item->building,
                        'room' => $transaction->room ?? $item->room,
                        'is_manual_return' => (bool) $transaction->manual_recipient_name,
                        'is_serialized' => (bool) $item->category?->requires_serial_number,
                    ];
                })
                ->values();
        }

        return response()->json($payload);
    }
}
