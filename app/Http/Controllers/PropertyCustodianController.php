<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\ArrivalInspection;
use App\Models\Category;
use App\Models\InventoryItemLog;
use App\Models\User;
use App\Services\AuditLedgerService;
use App\Services\IncidentLifecycleService;
use App\Services\IncidentProcessingService;
use App\Models\Transaction;
use App\Models\Inventory;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\MaintenanceRecordEvidence;
use App\Models\StockMovement;
use App\Models\TransferReleaseSlip;
use App\Services\Forecasting\ForecastAiRecommendationService;
use App\Services\ForecastDecisionSupportService;
use App\Services\Forecasting\ForecastExplanationService;
use App\Services\InventoryOperationService;
use App\Services\Forecasting\StoredDemandForecastService;
use App\Support\RequestableItemMatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        // Sidebar Transaction badge: same queues the Transactions page shows,
        // counted with the same status predicates (no new tables).
        $pendingIncomingCount = AssignmentRequest::query()
            ->whereIn('status', ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT])
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->count();
        $pendingTransferCount = AssignmentRequest::query()
            ->whereIn('status', ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'])
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->count();
        $pendingReturnCount = AssignmentRequest::query()
            ->where('status', 'waiting for custodian approval')
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            ->count();

        return response()->json([
            'title' => 'Property Custodian Dashboard',
            'pendingActionCount' => $pendingIncomingCount + $pendingTransferCount + $pendingReturnCount,
            'metrics' => [
                'inventory' => Inventory::where('status', '!=', 'disposed')->sum('quantity'),
                'available' => Inventory::where('status', 'available')->sum('quantity'),
                'lowStock' => $lowStockCount,
                // Custodian-actionable queues only, mirroring the Transactions
                // tabs: incoming requests plus custodian assignments awaiting
                // acceptance. Legacy transfers still waiting for recipient
                // action are routed to the custodian for the release-slip flow.
                'pendingRequests' => AssignmentRequest::query()
                    ->whereIn('status', ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT])
                    ->where(function ($query) use ($request): void {
                        $query->where(function ($incoming): void {
                            $incoming->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
                                ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'));
                        })->orWhere(function ($assignments) use ($request): void {
                            // Same scope as the Transactions assignments queue:
                            // this custodian's rows awaiting end-user acceptance.
                            $assignments->where('status', 'waiting for approval')
                                ->where('user_id', $request->user()?->id)
                                ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'));
                        });
                    })
                    ->count(),
                'pendingTransfers' => AssignmentRequest::query()
                    ->whereIn('status', ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'])
                    ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
                    ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
                    ->count(),
                'pendingReturns' => AssignmentRequest::query()
                    ->where('status', 'waiting for custodian approval')
                    ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
                    ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
                    ->count(),
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
            ->whereIn('status', ['under_repair', 'under_inspection', 'ready_to_dispose'])
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
                'attentionUnits' => (int) $activeInventory->whereIn('status', ['under_repair', 'under_inspection', 'ready_to_dispose'])->sum('quantity'),
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
        // Historical inspection-flagged records continue to read assignment
        // quantities from active transactions until the cleanup migration runs.
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
            ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection', 'under_repair') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END) as total_quantity")
            ->whereNotNull('inventory.status')
            ->groupBy('inventory.status')
            ->pluck('total_quantity', 'status');
        $quantityForStatus = fn (string $status): int => (int) ($statusQuantities[$status] ?? 0);

        // Keep legacy flagged records readable as assigned until the cleanup
        // migration restores their previous statuses.
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
            'attention' => (int) ($statusQuantities['under_inspection'] ?? 0) + (int) ($statusQuantities['under_repair'] ?? 0),
        ];
        $statuses = ['available', 'assigned', 'under_repair', 'ready_to_dispose', 'disposed'];
        // Legacy bookmark support: the workspace used to be called
        // under_maintenance. Map it so saved links keep working.
        $requestedWorkspace = $request->query('workspace', 'all') === 'under_maintenance'
            ? 'under_repair'
            : $request->query('workspace', 'all');
        $activeWorkspace = in_array($requestedWorkspace, ['all', 'available', 'assigned', 'under_repair', 'ready_to_dispose', 'disposed'], true)
            ? $requestedWorkspace
            : 'all';
        // Historical under_inspection rows remain readable until the cleanup
        // migration runs; there is no longer a workspace for creating or processing them.
        $workspaceStatuses = [
            'available' => ['available'],
            'under_repair' => ['under_repair'],
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
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection', 'under_repair') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END) as quantity")
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection', 'under_repair') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END * inventory.unit_cost) as total_cost")
                ->selectRaw("SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection', 'under_repair') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END * inventory.unit_cost) / NULLIF(SUM(CASE WHEN inventory.status IN ('assigned', 'under_inspection', 'under_repair') THEN COALESCE(active_assignments.active_assigned_quantity, inventory.quantity) ELSE inventory.quantity END), 0) as unit_cost")
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
                    // Partially issued stock appears here: an `available` record
                    // carrying active assignments shows its issued portion while
                    // its remainder stays under Available. Legacy flagged rows
                    // remain included until the cleanup migration runs.
                    $query->where(function ($scope): void {
                        $scope->whereIn('inventory.status', Inventory::CUSTODY_STATUSES)
                            ->orWhere(function ($partial): void {
                                $partial->where('inventory.status', 'available')
                                    ->whereNotNull('active_assignments.item_id');
                            });
                    })->where(function ($scope): void {
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
        // Partially issued stock reads in the Assigned list too, so its issued
        // portion counts toward the badge. The remainder still counts under
        // Available, so the two badges never double count a unit.
        $partiallyIssuedAssignedQuantity = (int) (Inventory::query()
            ->joinSub($activeAssignmentsForTotals, 'active_assignments', function ($join): void {
                $join->on('inventory.item_id', '=', 'active_assignments.item_id');
            })
            ->where('inventory.status', 'available')
            ->selectRaw('COALESCE(SUM(active_assignments.active_assigned_quantity), 0) as total_quantity')
            ->value('total_quantity') ?? 0);
        $inventoryStatusCounts['assigned'] = (int) $inventoryStatusCounts['assigned'] + $partiallyIssuedAssignedQuantity;
        // Performance: hydrate source rows only for the groups actually rendered
        // on this page (the 6 paginators above), instead of the entire inventory
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
            ->with(['category:category_id,category_name,is_maintenance_eligible,requires_serial_number', 'assignedTo', 'latestMaintenance', 'latestMaintenance.reportedBy:id,first_name,last_name,username', 'latestMaintenance.evidence:id,maintenance_record_id,original_filename', 'latestStockMovement', 'latestDisposalMovement'])
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

        // Performance: the repair picker lists every available row, so it is
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

        // Pending holds for the Available/All tabs: requests in
        // `waiting for approval` promise units the stock rows still show as
        // available (deduction happens at approval). Unallocated `draft`
        // children hold rows the same way. Loaded once; matched per
        // group below with the same normalisation the request flow uses.
        // Draft parents carry only the requested triple (see
        // pendingQuantity); draft children carry only item_id, so the two
        // pools never count the same units twice.
        $holdItemTypeRequests = AssignmentRequest::query()
            ->whereIn('status', ['waiting for approval', 'draft'])
            ->whereNotNull('requested_item_name')
            ->get(['requested_item_name', 'requested_category_id', 'requested_unit', 'quantity']);
        $holdByItemId = AssignmentRequest::query()
            ->whereIn('status', ['waiting for approval', 'draft'])
            ->whereNotNull('item_id')
            ->selectRaw('item_id, SUM(quantity) as hold_quantity')
            ->groupBy('item_id')
            ->pluck('hold_quantity', 'item_id');

        $attachGroupDetails = function ($collection) use ($sourceItemsByGroup, $activeAssignments, $groupKey, $holdItemTypeRequests, $holdByItemId): void {
            $collection->each(function (Inventory $inventoryItem) use ($sourceItemsByGroup, $activeAssignments, $groupKey, $holdItemTypeRequests, $holdByItemId): void {
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
                        ->with(['assignedTo', 'latestMaintenance', 'latestMaintenance.reportedBy:id,first_name,last_name,username', 'latestMaintenance.evidence:id,maintenance_record_id,original_filename', 'latestStockMovement', 'latestDisposalMovement'])
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

                // Soft-hold display for available rows: item-type holds match
                // the triple (any ICS group of the triple can fulfill them),
                // record holds match this group's source rows exactly.
                if (($inventoryItem->status ?? '') === 'available') {
                    $typeHold = (int) $holdItemTypeRequests
                        ->filter(fn (AssignmentRequest $request): bool => (int) $request->requested_category_id === (int) $inventoryItem->category_id
                            && RequestableItemMatcher::nameMatches((string) $inventoryItem->item_name, (string) $request->requested_item_name)
                            && RequestableItemMatcher::unitMatches((string) $request->requested_unit, $inventoryItem->unit))
                        ->sum('quantity');
                    $recordHold = (int) $matchedSourceItems->sum(fn (Inventory $source): int => (int) ($holdByItemId[$source->item_id] ?? 0));
                    $pendingHold = $typeHold + $recordHold;
                    $inventoryItem->pending_hold = $pendingHold;
                    $inventoryItem->free_quantity = max(0, (int) $inventoryItem->quantity - $pendingHold);
                } else {
                    $inventoryItem->pending_hold = 0;
                    $inventoryItem->free_quantity = (int) $inventoryItem->quantity;
                }
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

    public function inventoryIcsNumbers()
    {
        $icsNumbers = Inventory::query()
            ->whereNotNull('ics_no')
            ->where('ics_no', '<>', '')
            ->distinct()
            ->orderBy('ics_no')
            ->pluck('ics_no');

        return response()->json(['icsNumbers' => $icsNumbers]);
    }

    public function exportInventoryPdf(Request $request)
    {
        $workspace = $request->query('workspace') ?: 'all';
        if ($workspace === 'under_maintenance') {
            $workspace = 'under_repair';
        }
        $workspaces = [
            'all',
            'available',
            'assigned',
            'under_repair',
            'under_inspection',
            'ready_to_dispose',
            'disposed',
            'incident_reports',
            'investigations',
        ];
        if (! in_array($workspace, $workspaces, true)) {
            $workspace = 'all';
        }

        $filters = Validator::make(
            [
                'search' => trim((string) $request->query('search', '')),
                'category_id' => $request->query('category_id') ?: null,
                'ics_numbers' => $request->query('ics_numbers', []),
            ],
            [
                'search' => ['nullable', 'string', 'max:100'],
                'category_id' => ['nullable', 'integer', 'exists:categories,category_id'],
                'ics_numbers' => ['nullable', 'array'],
                'ics_numbers.*' => ['required', 'string', 'max:255', 'distinct'],
            ]
        )->validate();

        $signatureFields = Validator::make(
            [
                'received_from_name' => $request->query('received_from_name'),
                'received_from_position' => $request->query('received_from_position'),
                'received_from_date' => $request->query('received_from_date'),
                'received_by_name' => $request->query('received_by_name'),
                'received_by_role' => $request->query('received_by_role'),
                'received_by_date' => $request->query('received_by_date'),
            ],
            [
                'received_from_name' => ['nullable', 'string', 'max:255'],
                'received_from_position' => ['nullable', 'string', 'max:255'],
                'received_from_date' => ['nullable', 'string', 'max:255'],
                'received_by_name' => ['nullable', 'string', 'max:255'],
                'received_by_role' => ['nullable', 'string', 'max:255'],
                'received_by_date' => ['nullable', 'string', 'max:255'],
            ]
        )->validate();

        $search = strtolower($filters['search'] ?? '');
        $categoryId = $filters['category_id'] ?? null;
        $icsNumbers = $filters['ics_numbers'] ?? [];

        $query = Inventory::query()->with('category');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($icsNumbers !== []) {
            $query->whereIn('ics_no', $icsNumbers);
        }
        if ($workspace === 'incident_reports' || $workspace === 'investigations') {
            $incidentStatuses = $workspace === 'investigations'
                ? ['under_investigation']
                : ['reported', 'under_review', 'awaiting_reporter', 'processing_repair', 'processing_disposal'];
            $query->whereIn('item_id', MaintenanceRecord::query()
                ->select('inventory_id')
                ->whereIn('diagnosis', MaintenanceRecord::INCIDENT_TYPES)
                ->whereIn('incident_status', $incidentStatuses));
        }

        $inventoryItems = $query->orderBy('item_name')->get();
        $assignmentsByItem = Transaction::query()
            ->with('user:id,first_name,last_name,username')
            ->where('status', 'assigned')
            ->whereIn('item_id', $inventoryItems->pluck('item_id'))
            ->get()
            ->groupBy('item_id');

        $inventoryItems = $inventoryItems->filter(function (Inventory $item) use ($workspace, $assignmentsByItem): bool {
            $hasAssignments = $assignmentsByItem->has($item->item_id);

            if ($workspace === 'all') {
                return $item->status !== 'disposed';
            }
            if ($workspace === 'assigned') {
                return $item->status === 'assigned'
                    || ($item->status === 'under_inspection' && $hasAssignments)
                    || ($item->status === 'available' && $hasAssignments);
            }
            if (in_array($workspace, ['incident_reports', 'investigations'], true)) {
                return true;
            }

            return $item->status === $workspace;
        })->values();

        $groups = $inventoryItems
            ->groupBy(fn (Inventory $item): string => implode('|', [
                (string) $item->item_name,
                (string) $item->category_id,
                (string) $item->unit,
                (string) ($item->status ?? ''),
                (string) ($item->ics_no ?? ''),
                (string) ($item->lifespan_years ?? ''),
            ]))
            ->map(function ($items) use ($assignmentsByItem) {
                $itemAssignments = $items
                    ->flatMap(fn (Inventory $item) => $assignmentsByItem->get($item->item_id, collect()));
                $assignees = $itemAssignments
                    ->groupBy(fn (Transaction $transaction): string => $transaction->user?->full_name
                        ?? $transaction->user?->username
                        ?? $transaction->manual_recipient_name
                        ?? 'Recorded assignee')
                    ->keys()
                    ->implode(', ');
                $assigneeNames = $itemAssignments
                    ->map(fn (Transaction $transaction): string => $transaction->user?->full_name
                        ?? $transaction->user?->username
                        ?? $transaction->manual_recipient_name
                        ?? 'Recorded assignee')
                    ->unique()
                    ->implode(', ');

                $displayQuantity = (int) $items->sum(function (Inventory $item) use ($assignmentsByItem): int {
                    $itemAssignments = $assignmentsByItem->get($item->item_id, collect());
                    $assignedQuantity = (int) $itemAssignments->sum('quantity');

                    return in_array($item->status, ['assigned', 'under_inspection'], true) && $itemAssignments->isNotEmpty()
                        ? $assignedQuantity
                        : (int) $item->quantity;
                });
                $costQuantity = (int) $items->sum(function (Inventory $item) use ($assignmentsByItem): int {
                    $itemAssignments = $assignmentsByItem->get($item->item_id, collect());
                    $assignedQuantity = (int) $itemAssignments->sum('quantity');

                    return in_array($item->status, ['assigned', 'under_inspection', 'under_repair'], true)
                        ? ($itemAssignments->isNotEmpty() ? $assignedQuantity : (int) $item->quantity)
                        : (int) $item->quantity;
                });
                $totalCost = (float) $items->sum(function (Inventory $item) use ($assignmentsByItem): float {
                    $itemAssignments = $assignmentsByItem->get($item->item_id, collect());
                    $assignedQuantity = (int) $itemAssignments->sum('quantity');
                    $quantity = in_array($item->status, ['assigned', 'under_inspection', 'under_repair'], true)
                        ? ($itemAssignments->isNotEmpty() ? $assignedQuantity : (int) $item->quantity)
                        : (int) $item->quantity;

                    return $quantity * (float) $item->unit_cost;
                });

                return [
                    'item_name' => $items->first()->item_name,
                    'ics_no' => $items->first()->ics_no,
                    'category' => $items->first()->category?->category_name ?? 'Uncategorized',
                    'description' => $items->max('description') ?? '',
                    'unit' => $items->first()->unit,
                    'lifespan_years' => $items->first()->lifespan_years,
                    'quantity' => $displayQuantity,
                    'unit_cost' => $costQuantity > 0 ? $totalCost / $costQuantity : (float) $items->avg('unit_cost'),
                    'total_cost' => $totalCost,
                    'status' => $items->first()->status === 'available' && $displayQuantity > 0
                        ? 'Assigned'
                        : ucwords(str_replace('_', ' ', (string) $items->first()->status)),
                    'assignee' => $assignees,
                    'assignee_names' => $assigneeNames,
                ];
            })
            ->filter(function (array $group) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower(implode(' ', [
                    $group['item_name'],
                    $group['category'],
                    $group['ics_no'] ?? '',
                    $group['description'],
                    $group['assignee_names'],
                ])), $search);
            })
            ->sortBy('item_name')
            ->values();

        $icsPages = $icsNumbers !== []
            ? collect($icsNumbers)->map(function (string $icsNumber) use ($groups): array {
                $pageGroups = $groups
                    ->filter(fn (array $group): bool => (string) ($group['ics_no'] ?? '') === $icsNumber)
                    ->values();

                return [
                    'ics_no' => $icsNumber,
                    'groups' => $pageGroups,
                    'total_quantity' => $pageGroups->sum('quantity'),
                ];
            })
            : $groups
                ->groupBy(fn (array $group): string => (string) ($group['ics_no'] ?? ''))
                ->map(fn ($pageGroups, string $icsNumber): array => [
                    'ics_no' => $icsNumber !== '' ? $icsNumber : null,
                    'groups' => $pageGroups->values(),
                    'total_quantity' => $pageGroups->sum('quantity'),
                ])
                ->sortBy(fn (array $page): string => $page['ics_no'] ?? "\xFF")
                ->values();

        if ($icsPages->isEmpty()) {
            $icsPages = collect([[
                'ics_no' => $icsNumbers[0] ?? null,
                'groups' => collect(),
                'total_quantity' => 0,
            ]]);
        }

        $filterSummary = "Workspace: {$workspace}"
            . ($search !== '' ? "; Search: \"{$filters['search']}\"" : '')
            . ($categoryId ? '; Category: ' . (Category::find($categoryId)?->category_name ?? $categoryId) : '');

        $preparedBy = trim($request->user()->first_name . ' ' . $request->user()->last_name)
            ?: $request->user()->username;
        $isPreview = $request->boolean('preview');
        $viewData = [
            'icsPages' => $icsPages,
            'groups' => $groups,
            'totalQuantity' => $groups->sum('quantity'),
            'totalValue' => $groups->sum('total_cost'),
            'filterSummary' => $filterSummary,
            'workspace' => $workspace,
            'generatedAt' => now()->format('F j, Y g:i A'),
            'preparedBy' => $request->query->has('received_by_name')
                ? ($signatureFields['received_by_name'] ?? '')
                : $preparedBy,
            'receivedFromName' => $signatureFields['received_from_name'] ?? '',
            'receivedFromPosition' => $signatureFields['received_from_position'] ?? '',
            'receivedFromDate' => $signatureFields['received_from_date'] ?? '',
            'receivedByRole' => $request->query->has('received_by_role')
                ? ($signatureFields['received_by_role'] ?? '')
                : 'Property Custodian',
            'receivedByDate' => $signatureFields['received_by_date'] ?? '',
            'isPreview' => $isPreview,
        ];

        if ($request->boolean('preview')) {
            return response()->view('pdfs.inventory-export', $viewData);
        }

        $pdf = Pdf::loadView('pdfs.inventory-export', $viewData);
        $pdf->setOption('defaultMediaType', 'print');
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download("dnhs_inventory_{$workspace}_" . now()->format('Y-m-d_His') . '.pdf');
    }

    public function generateRfqPdf(Request $request)
    {
        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'quotation_no' => ['nullable', 'string', 'max:255'],
            'rfq_date' => ['nullable', 'date'],
            'procurement_mode' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:1000'],
            'bac_chair_name' => ['nullable', 'string', 'max:255'],
            'bac_chair_title' => ['nullable', 'string', 'max:255'],
            'bac_contact_numbers' => ['nullable', 'string', 'max:255'],
            'submission_deadline' => ['nullable', 'string', 'max:255'],
            'secretariat_contact' => ['nullable', 'string', 'max:255'],
            'verification_contact' => ['nullable', 'string', 'max:255'],
            'inspection_schedule' => ['nullable', 'string', 'max:1000'],
            'auction_schedule' => ['nullable', 'string', 'max:1000'],
            'pullout_schedule' => ['nullable', 'string', 'max:1000'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_tin' => ['nullable', 'string', 'max:255'],
            'supplier_address' => ['nullable', 'string', 'max:1000'],
            'supplier_phone' => ['nullable', 'string', 'max:255'],
            'supplier_fax' => ['nullable', 'string', 'max:255'],
            'supplier_email' => ['nullable', 'email', 'max:255'],
            'representative_signature' => ['nullable', 'string', 'max:255'],
            'representative_date' => ['nullable', 'string', 'max:255'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'floor_price' => ['nullable', 'numeric', 'min:0'],
            'requirements' => ['nullable', 'array', 'max:50'],
            'requirements.*' => ['array'],
            'requirements.*.description' => ['required', 'string', 'max:1000'],
            'requirements.*.compliance' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['nullable', 'string', 'max:5000'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['array'],
            'overrides.*.description' => ['nullable', 'string', 'max:1000'],
            'overrides.*.unit' => ['nullable', 'string', 'max:100'],
            'overrides.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.price_offered' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.compliance' => ['nullable', 'string', 'max:255'],
        ]);

        $ids = $validated['selected_ids'];
        $selectedItems = Inventory::query()
            ->with('category')
            ->whereIn('item_id', $ids)
            ->whereIn('status', ['ready_to_dispose', 'disposed'])
            ->get()
            ->keyBy('item_id');

        if ($selectedItems->count() !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selected_ids' => 'Only items marked ready to dispose or disposed can be added to an RFQ.',
            ]);
        }

        $overrides = $validated['overrides'] ?? [];
        $items = collect($ids)->map(function ($itemId) use ($selectedItems, $overrides): array {
            $item = $selectedItems->get($itemId);
            $row = [
                'item_id' => $item->item_id,
                'description' => $item->item_name,
                'unit' => $item->unit ?? '',
                'quantity' => (float) ($item->quantity ?? 0),
                'unit_price' => (float) ($item->unit_cost ?? 0),
                'price_offered' => '',
                'compliance' => '',
            ];

            foreach (($overrides[$itemId] ?? []) as $key => $value) {
                if (array_key_exists($key, $row) && $key !== 'item_id') {
                    $row[$key] = $value;
                }
            }

            return $row;
        })->values();

        $calculatedTotal = $items->sum(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price']);
        $total = array_key_exists('total', $validated) ? (float) $validated['total'] : $calculatedTotal;
        $floorPrice = array_key_exists('floor_price', $validated) ? (float) $validated['floor_price'] : $calculatedTotal;
        $defaults = [
            'quotation_no' => 'RFQ-' . now()->format('Y-m-d'),
            'rfq_date' => now()->toDateString(),
            'procurement_mode' => 'Public Bidding',
            'project_name' => 'Public Bidding for one (1) Lot of Disposal of various unserviceable properties',
            'bac_chair_name' => 'LILIBETH L. CASTRO',
            'bac_chair_title' => 'Chair, Bids and Awards Committee',
            'bac_contact_numbers' => '(034) 724-5720, (034) 454-0746',
            'submission_deadline' => '',
            'secretariat_contact' => '(09483841228)',
            'verification_contact' => '(09097148630)',
            'inspection_schedule' => '',
            'auction_schedule' => '',
            'pullout_schedule' => '',
            'supplier_name' => '',
            'supplier_tin' => '',
            'supplier_address' => '',
            'supplier_phone' => '',
            'supplier_fax' => '',
            'supplier_email' => '',
            'representative_signature' => '',
            'representative_date' => '',
        ];
        $metadata = array_replace($defaults, array_intersect_key($validated, $defaults));
        $requirements = $validated['requirements'] ?? [
            ['description' => 'Updated/Valid PHILGEPS Certificate of registration', 'compliance' => ''],
            ['description' => 'Quotation sheet shall include the terms of payment, price validity, company TIN.', 'compliance' => ''],
            ['description' => 'Business/Mayor’s Permit or Official Receipt as proof of renewal upon post-qualification', 'compliance' => ''],
        ];
        $defaultTerms = [
            'submission_address' => '1. Quotations and other requirements stated below shall be addressed to the Bids and Awards Committee (BAC) Chairperson c/o BAC Secretariat at Escalante City, Negros Occidental, Philippines. If quotation is sealed, please indicate in the envelope the quotation number and name of bidder.',
            'submission_documents_intro' => '2. In addition, you are required to submit the following necessary documents:',
            'document_mayors_permit_post_qualification' => 'a. Present valid Mayor’s Permit or Official Receipt as proof of renewal upon post-qualification; and,',
            'document_mayors_permit_payment' => 'b. Present valid Mayor’s Permit prior to payment.',
            'document_philgeps' => 'c. PhilGEPS Registration Number',
            'document_dole' => 'd. Certification re: Compliance to DOLE Rules and Regulations as to employee compensation',
            'previous_submission_note' => '(Note: Supplier who already submitted their documents in the previous procurement/s may no longer submit the necessary documents mentioned above.)',
            'price_validity' => 'Price should be valid thirty (30) calendar days after the deadline of submission of quotation.',
            'ocular_inspection' => 'The BAC, if necessary, may order the conduct of an ocular inspection prior to award and/or conduct of the event.',
            'award' => 'The provider who submitted the lowest calculated responsive quotation and who passed post qualification conducted by the BAC and end-user shall be awarded the contract.',
            'award_tie_break' => 'In case of tie of two or more Bidders whose bids are determined and declared as the Lowest Calculated and Responsive Bids (LCRB) having been Post-Qualified, the method to be used to break the tie is “draw lots” which is non-discretionary and non discriminatory.',
            'award_absent_bidder' => 'In case of non-appearance of one party, it waives the right to participate in the drawing of lots but not in the acceptance of award if declared as winner.',
            'delivery' => 'Delivery of Goods/s shall be on the date specified in the Request for Quotation (RFQ) or in the Purchase Order. In case the service/s shall be moved by the end-user within the validity period (30 days) the service provider is responsible to accommodate the request. On the other hand, the service provider may reject the award if the new schedule is beyond the validity period.',
            'evaluation_intro' => 'Quotation shall be compared and evaluated on the basis of the following criteria:',
            'evaluation_price' => '1. Price Quotation;',
            'evaluation_compliance' => '2. Compliance with Items & Description',
            'evaluation_documents' => '3. Completeness of documents submitted',
            'evaluation_note' => 'Note: (Validation of the submitted documents is done during post-qualification.)',
            'instruction_sources' => '1. Provider shall be responsible for the source(s) of its goods/services/equipment, which shall provide in accordance with the schedule, and specifications of the RFQ or contract. Failure of the provider to comply with this provision shall be ground for cancellation of the award or purchase order/contract issued to the provider.',
            'instruction_failure' => '2. Provider who accepted a contract but failed to deliver the required goods/services/equipment within the time called for in the contract shall be disqualified from participating in DepEd or any of the DepEd units’ future procurement activities. This is without prejudice to the imposition of other sanctions prescribed under R.A. 9184 and its revised IRR against the supplier.',
            'instruction_taxes' => '3. All duties, excise, and other taxes and revenue charges shall be paid by the provider.',
            'instruction_withholding' => '4. All transactions are subject to withholding of credible Value Added Tax and/or Expanded Value Added Tax per revenue regulation(s) of the Bureau of Internal Revenue.',
            'liquidated_damages' => 'A penalty of one-tenth of one percent (0.001) of the total value of the undelivered goods/services/equipment shall be charged as liquidated damages for every day of delay of the delivery of the purchased Goods.',
            'warranty' => 'Supplier warrants that all goods/services/equipment to be provided are of acceptable industry standard.',
            'payment' => 'One Hundred Percent (100%) may be paid to the Provider’s Account less Government Taxes after the complete delivery/ies; based on the actual completed Delivery of the Goods/Services.',
            'reservation' => 'The DepEd Escalante City-Bids and Awards Committee reserves the right to accept or reject any bid, to annul the bidding process, and to reject all bids at any time prior to contract award, without thereby incurring any liability to the affected bidder or bidders.',
        ];
        $terms = array_replace($defaultTerms, $validated['terms'] ?? []);

        $pdf = Pdf::loadView('pdfs.rfq', [
            'metadata' => $metadata,
            'items' => $items,
            'total' => $total,
            'floorPrice' => $floorPrice,
            'requirements' => $requirements,
            'terms' => $terms,
        ]);
        $pdf->setPaper('letter', 'portrait');
        $pdf->render();

        $footerSeal = public_path('images/footer/escalante_city_education_seal.svg');
        $pdf->getDomPDF()->getCanvas()->page_script(static function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($footerSeal): void {
            if ($pageNumber !== $pageCount) {
                return;
            }

            $pageWidth = $canvas->get_width();
            $pageHeight = $canvas->get_height();
            $leftMargin = 36;
            $sealSize = 34;
            $footerTop = $pageHeight - 78;
            $textX = $leftMargin + $sealSize + 10;
            $font = $fontMetrics->getFont('Times-Roman', 'normal');
            $fontSize = 8.5;
            $lineHeight = 11;
            $footerLines = [
                'Escalante City, Negros Occidental, Philippines',
                'Tel. Nos. - (034) 724-5720, (034) 454-0746',
                'Escalante.city001@deped.gov.ph',
                'Depedescalantecity.weebly.com',
            ];
            $sealTop = $footerTop + 7 + (($lineHeight * (count($footerLines) - 1) - $sealSize) / 2);

            $canvas->line($leftMargin, $footerTop - 8, $pageWidth - $leftMargin, $footerTop - 8, [0, 0, 0], 0.7);
            $canvas->image($footerSeal, $leftMargin, $sealTop, $sealSize, $sealSize);

            foreach ($footerLines as $index => $line) {
                $canvas->text(
                    $textX,
                    $footerTop + 7 + ($index * $lineHeight),
                    $line,
                    $font,
                    $fontSize,
                    [0, 0, 0],
                );
            }
        });

        return $pdf->stream('RFQ-' . now()->format('Y-m-d') . '.pdf');
    }

    public function generateItbPdf(Request $request)
    {
        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'floor_prices' => ['sometimes', 'array'],
            'floor_prices.*' => ['required', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'metadata.bid_description' => ['nullable', 'string', 'max:1000'],
            'metadata.bid_submission_copies' => ['nullable', 'string', 'max:255'],
            'metadata.pullout_period' => ['nullable', 'string', 'max:255'],
            'metadata.signatories' => ['nullable', 'array', 'size:6'],
            'metadata.signatories.*.name' => ['nullable', 'string', 'max:255'],
            'metadata.signatories.*.title' => ['nullable', 'string', 'max:255'],
            'metadata.signatories.*.role' => ['nullable', 'string', 'max:255'],
            'metadata.corporation_requirement' => ['nullable', 'string', 'max:500'],
            'metadata.proprietorship_requirement' => ['nullable', 'string', 'max:500'],
            'metadata.business_permit_requirement' => ['nullable', 'string', 'max:500'],
            'metadata.additional_requirements' => ['nullable', 'array'],
            'metadata.additional_requirements.*' => ['nullable', 'string', 'max:500'],
            'metadata.school_name' => ['nullable', 'string', 'max:255'],
            'metadata.school_address' => ['nullable', 'string', 'max:1000'],
            'metadata.submission_deadline' => ['nullable', 'date'],
            'metadata.submission_time' => ['nullable', 'string', 'max:50'],
            'metadata.bid_opening_date' => ['nullable', 'date'],
            'metadata.bid_opening_time' => ['nullable', 'string', 'max:50'],
            'metadata.secretariat_name' => ['nullable', 'string', 'max:255'],
            'metadata.secretariat_role' => ['nullable', 'string', 'max:255'],
            'metadata.secretariat_role_detail' => ['nullable', 'string', 'max:255'],
            'metadata.contact_numbers' => ['nullable', 'string', 'max:255'],
            'metadata.inspection_day' => ['nullable', 'date'],
            'metadata.inspection_time' => ['nullable', 'string', 'max:50'],
            'metadata.auction_date' => ['nullable', 'date'],
            'metadata.auction_time' => ['nullable', 'string', 'max:50'],
            'metadata.form_acquisition_from' => ['nullable', 'date'],
            'metadata.form_acquisition_to' => ['nullable', 'date', 'after_or_equal:metadata.form_acquisition_from'],
        ]);

        $ids = $validated['selected_ids'];
        $floorPrices = $validated['floor_prices'] ?? null;
        if ($floorPrices !== null && count($floorPrices) !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'floor_prices' => 'A floor price is required for each selected item.',
            ]);
        }

        $selectedItems = Inventory::query()
            ->whereIn('item_id', $ids)
            ->whereIn('status', ['ready_to_dispose', 'disposed'])
            ->get()
            ->keyBy('item_id');

        if ($selectedItems->count() !== count($ids)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'selected_ids' => 'Only items marked ready to dispose or disposed can be added to an ITB.',
            ]);
        }

        $items = collect($ids)->values()->map(function ($itemId, $index) use ($selectedItems, $floorPrices): array {
            $item = $selectedItems->get($itemId);

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name ?? '',
                'quantity' => (float) ($item->quantity ?? 0),
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'floor_price' => $floorPrices === null
                    ? (float) ($item->quantity ?? 0) * (float) ($item->unit_cost ?? 0)
                    : (float) $floorPrices[$index],
            ];
        })->values();

        $metadata = array_replace([
            'bid_description' => 'public bidding for one (1) lot of Disposal of various unserviceable properties and equipment',
            'corporation_requirement' => 'SEC Registration Certificate for Corporation; or',
            'proprietorship_requirement' => 'DTI Registration Certificate for Sole Proprietorship;',
            'business_permit_requirement' => 'Valid Business Permit.',
            'additional_requirements' => [],
            'school_name' => 'Dian-ay National High School',
            'school_address' => 'Barangay Dian-ay, Escalante City',
            'submission_deadline' => null,
            'submission_time' => '',
            'bid_opening_date' => null,
            'bid_opening_time' => '',
            'bid_submission_copies' => '(3) (original copy (1) and 2 photocopies)',
            'pullout_period' => 'seven (7) calendar days',
            'signatories' => [
                ['name' => 'LILIBETH L. CASTRO', 'title' => 'Teacher III', 'role' => 'BAC Chairperson'],
                ['name' => 'RIZALINA P. MAXILOM', 'title' => 'Principal I', 'role' => 'Chairperson, Disposal Committee'],
                ['name' => 'LEE-RISA M. PRIETOS', 'title' => 'BAC SECRETARIAT', 'role' => ''],
                ['name' => 'MELISSA L. PRUDENTE', 'title' => 'BAC MEMBER', 'role' => ''],
                ['name' => 'ROBERTDAWN S. FUAMASING', 'title' => 'BAC Member', 'role' => ''],
                ['name' => 'CHARISSA MAE I. SAPA', 'title' => 'BAC Member', 'role' => ''],
            ],
            'secretariat_name' => '',
            'secretariat_role' => 'Administrative Assistant II',
            'secretariat_role_detail' => 'Head, BAC Secretariat',
            'contact_numbers' => '',
            'inspection_day' => null,
            'inspection_time' => '',
            'auction_date' => null,
            'auction_time' => '',
            'form_acquisition_from' => null,
            'form_acquisition_to' => null,
        ], $validated['metadata'] ?? []);

        $pdf = Pdf::loadView('pdfs.itb', [
            'items' => $items,
            'metadata' => $metadata,
        ]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('ITB-' . now()->format('Y-m-d') . '.pdf');
    }

    public function generateRcpiPpePdf(Request $request)
    {
        $validated = $request->validate([
            'inventory_type' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'accountable_person' => ['nullable', 'string', 'max:255'],
            'accountability_date' => ['nullable', 'date'],
            'recommending_name' => ['nullable', 'string', 'max:255'],
            'recommending_title' => ['nullable', 'string', 'max:255'],
            'approving_name' => ['nullable', 'string', 'max:255'],
            'approving_title' => ['nullable', 'string', 'max:255'],
            'verifying_title' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.article' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.stock_number' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:100'],
            'items.*.unit_value' => ['required', 'numeric', 'min:0'],
            'items.*.book_quantity' => ['required', 'integer', 'min:0'],
            'items.*.counted_quantity' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'max:255'],
            'items.*.whereabouts' => ['nullable', 'string', 'max:255'],
        ]);

        $items = collect($validated['items'])
            ->map(function (array $item): array {
                $difference = (int) $item['counted_quantity'] - (int) $item['book_quantity'];

                return [
                    ...$item,
                    'variance_quantity' => abs($difference),
                    'variance_value' => abs($difference) * (float) $item['unit_value'],
                    'variance_type' => $difference < 0 ? 'Shortage' : ($difference > 0 ? 'Overage' : ''),
                ];
            })
            ->all();

        $pdf = Pdf::loadView('pdfs.rcpi-ppe', [
            ...$validated,
            'items' => $items,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('RCPI-PPE_DRAFT_' . now()->format('Y-m-d_His') . '.pdf');
    }

    public function generateIirupPdf(Request $request)
    {
        $validated = $request->validate([
            'report_date' => ['nullable', 'date'],
            'entity_name' => ['nullable', 'string', 'max:255'],
            'fund_cluster' => ['nullable', 'string', 'max:255'],
            'accountable_officer_name' => ['nullable', 'string', 'max:255'],
            'accountable_officer_designation' => ['nullable', 'string', 'max:255'],
            'accountable_officer_station' => ['nullable', 'string', 'max:255'],
            'approving_official_name' => ['nullable', 'string', 'max:255'],
            'approving_official_designation' => ['nullable', 'string', 'max:255'],
            'inspection_officer_name' => ['nullable', 'string', 'max:255'],
            'inspection_designation' => ['nullable', 'string', 'max:255'],
            'witness_name' => ['nullable', 'string', 'max:255'],
            'witness_disposition_day' => ['nullable', 'string', 'max:20'],
            'witness_disposition_date' => ['nullable', 'string', 'max:100'],
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:inventory,item_id'],
            'overrides' => ['nullable', 'array'],
            'overrides.*' => ['nullable', 'array'],
            'overrides.*.date_acquired' => ['nullable', 'date'],
            'overrides.*.item_name' => ['nullable', 'string', 'max:255'],
            'overrides.*.inventory_item_no' => ['nullable', 'string', 'max:255'],
            'overrides.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.total_cost' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.accumulated_depreciation' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.accumulated_impairment' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.carrying_amount' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.remarks' => ['nullable', 'string', 'max:2000'],
            'overrides.*.appraised_value' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.disposal_sale' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.disposal_transfer' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.disposal_destruction' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.disposal_others' => ['nullable', 'string', 'max:255'],
            'overrides.*.disposal_total' => ['nullable', 'numeric', 'min:0'],
            'overrides.*.or_no' => ['nullable', 'string', 'max:255'],
            'overrides.*.sales_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ids = $validated['selected_ids'] ?? [];
        $items = empty($ids)
            ? collect()
            : Inventory::query()->with('category')->whereIn('item_id', $ids)->get();

        $itemData = $items->map(function (Inventory $item): array {
            $quantity = (int) ($item->quantity ?? 0);
            $unitCost = (float) ($item->unit_cost ?? 0);
            $totalCost = $quantity * $unitCost;

            return [
                'item_id' => $item->item_id,
                'date_acquired' => $item->date_acquired ? $item->date_acquired->format('Y-m-d') : '',
                'item_name' => $item->item_name ?? '',
                'inventory_item_no' => $item->inventory_item_no ?? '',
                'category_id' => $item->category_id ?? $item->category?->category_id,
                'category_name' => $item->category?->category_name ?? 'Uncategorized',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'accumulated_depreciation' => 0,
                'accumulated_impairment' => 0,
                'carrying_amount' => $totalCost,
                'remarks' => $item->description ?? $item->status ?? '',
                'appraised_value' => 0,
                'disposal_sale' => 0,
                'disposal_transfer' => 0,
                'disposal_destruction' => 0,
                'disposal_others' => '',
                'disposal_total' => 0,
                'or_no' => '',
                'sales_amount' => 0,
            ];
        })->values()->all();

        // Merge frontend overrides keyed by item_id into computed item data
        $overrides = $validated['overrides'] ?? [];
        $allowedOverrideKeys = [
            'date_acquired', 'item_name', 'inventory_item_no',
            'quantity', 'unit_cost', 'total_cost',
            'accumulated_depreciation', 'accumulated_impairment', 'carrying_amount',
            'remarks', 'appraised_value',
            'disposal_sale', 'disposal_transfer', 'disposal_destruction', 'disposal_others',
            'disposal_total', 'or_no', 'sales_amount',
        ];

        foreach ($itemData as &$row) {
            $itemId = $row['item_id'];
            if (isset($overrides[$itemId]) && is_array($overrides[$itemId])) {
                foreach ($allowedOverrideKeys as $key) {
                    if (array_key_exists($key, $overrides[$itemId])) {
                        $row[$key] = $overrides[$itemId][$key];
                    }
                }
            }
        }
        unset($row);

        $groups = collect($itemData)
            ->groupBy(fn (array $row) => (string) ($row['category_id'] ?? '0'))
            ->map(fn ($g) => [
                'category_id'   => $g->first()['category_id'],
                'category_name' => $g->first()['category_name'],
                'items'         => $g->values()->all(),
            ])
            ->sortBy('category_name')
            ->values()
            ->all();

        $totals = [
            'total_cost' => array_sum(array_column($itemData, 'total_cost')),
            'accumulated_depreciation' => array_sum(array_column($itemData, 'accumulated_depreciation')),
            'carrying_amount' => array_sum(array_column($itemData, 'carrying_amount')),
            'appraised_value' => array_sum(array_column($itemData, 'appraised_value')),
        ];

        $user = $request->user();
        $accountableOfficerName = $validated['accountable_officer_name']
            ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
            ?: ($user->username ?? '');
        $accountableOfficerDesignation = $validated['accountable_officer_designation'] ?? 'SCHOOL PROPERTY CUSTODIAN';
        $station = $validated['accountable_officer_station']
            ?? trim(($user->building ?? '') . (($user->room ?? '') ? ', Room ' . $user->room : ''));

        $pdf = Pdf::loadView('pdfs.disposal-inspection-report', [
            'items' => $itemData,
            'groups' => $groups,
            'totals' => $totals,
            'entity_name' => $validated['entity_name'] ?? 'Dian-ay National High School',
            'fund_cluster' => $validated['fund_cluster'] ?? '',
            'as_at_date' => $validated['report_date'] ?? now()->format('F j, Y'),
            'accountable_officer' => [
                'name' => $accountableOfficerName,
                'designation' => $accountableOfficerDesignation,
                'station' => $station ?: 'Dian-ay National High School',
            ],
            'requested_by_name' => $accountableOfficerName,
            'requested_by_designation' => $accountableOfficerDesignation,
            'approved_by_name' => $validated['approving_official_name'] ?? '',
            'approved_by_designation' => $validated['approving_official_designation'] ?? 'PRINCIPAL I',
            'inspection_officer_name' => $validated['inspection_officer_name'] ?? '',
            'inspection_designation' => $validated['inspection_designation'] ?? '',
            'witness_name' => $validated['witness_name'] ?? '',
            'witness_disposition_day' => $validated['witness_disposition_day'] ?? '',
            'witness_disposition_date' => $validated['witness_disposition_date'] ?? '',
        ]);
        $pdf->setPaper('letter', 'landscape');

        return $pdf->download('IIRUP_' . now()->format('Y-m-d_His') . '.pdf');
    }

    public function exportTransactionsPdf(Request $request)
    {
        $filters = Validator::make(
            [
                'tab' => $request->query('tab', 'requests'),
                'search' => trim((string) $request->query('search', '')),
                'date_from' => $request->query('date_from') ?: null,
                'date_to' => $request->query('date_to') ?: null,
            ],
            [
                'tab' => ['required', 'string', 'in:requests,transfers,returns,history,ledger'],
                'search' => ['nullable', 'string', 'max:100'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date'],
            ]
        )->validate();

        $tab = $filters['tab'];
        $search = (string) ($filters['search'] ?? '');
        $dateFrom = $filters['date_from'] ? substr((string) $filters['date_from'], 0, 10) : null;
        $dateTo = $filters['date_to'] ? substr((string) $filters['date_to'], 0, 10) : null;

        $rows = app(\App\Support\TransactionsExportRows::class)
            ->forTab($tab, (int) $request->user()->id, $search, $dateFrom, $dateTo);

        $filterSummary = 'Tab: '.$tab
            . ($search !== '' ? '; Search: "'.$search.'"' : '')
            . ($tab === 'history' && ($dateFrom || $dateTo)
                ? '; Dates: '.($dateFrom ?? '…').' to '.($dateTo ?? '…')
                : '');

        $pdf = Pdf::loadView('pdfs.transactions-export', [
            'tab' => $tab,
            'rows' => collect($rows),
            'filterSummary' => $filterSummary,
            'generatedAt' => now()->format('F j, Y g:i A'),
            'preparedBy' => trim($request->user()->first_name.' '.$request->user()->last_name)
                ?: $request->user()->username,
        ]);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('transactions_'.$tab.'_'.now()->format('Y-m-d_His').'.pdf');
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
            $pendingHold = (int) AssignmentRequest::query()
                ->whereIn('status', ['waiting for approval', 'draft'])
                ->where('item_id', $item->item_id)
                ->sum('quantity');

            return [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'status' => $item->status,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->category_name,
                'quantity' => (int) $item->quantity,
                'pending_hold' => $pendingHold,
                'free_quantity' => max(0, (int) $item->quantity - $pendingHold),
                'unit' => $item->unit,
                'unit_cost' => $item->unit_cost === null ? null : (float) $item->unit_cost,
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
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit,unit_cost,serial_number',
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
                // Exclude this row itself: the hold that matters is OTHER
                // requests promising the same units, not the request's own line.
                $request->pending_hold_stock = max(0, RequestableItemMatcher::pendingQuantity(
                    (string) $request->requested_item_name,
                    (int) $request->requested_category_id,
                    $request->requested_unit,
                ) - (int) $request->quantity);
                $request->total_free_stock = max(0, $request->total_available_stock - $request->pending_hold_stock);

                return;
            }

            $item = $request->item;
            $request->matching_inventory_items = $item && $item->status === 'available'
                ? collect([$item])
                : collect();
            $request->total_available_stock = (int) ($item?->status === 'available' ? $item->quantity : 0);
            $request->pending_hold_stock = $item
                ? (int) AssignmentRequest::query()
                    ->where('status', 'waiting for approval')
                    ->where('item_id', $item->item_id)
                    ->where('id', '!=', $request->id)
                    ->sum('quantity')
                : 0;
            $request->total_free_stock = max(0, $request->total_available_stock - $request->pending_hold_stock);
        });

        // Assignments created by this Property Custodian for End Users
        $custodianId = request()->user()?->id;
        $assignmentRequests = AssignmentRequest::query()
            ->with([
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit_cost',
                'item.category:category_id,category_name',
            ])
            ->where('user_id', $custodianId)
            ->where('status', 'waiting for approval')
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        // Draft allocations: printed slips awaiting signed-slip collection.
        // Hold-only rows (no deduction, no transaction, no ledger movement).
        // Allocated or deleted from the Drafts tab; invisible everywhere else.
        $draftRequests = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'fulfillmentRequests:id,item_id,parent_request_id,quantity,status',
                'fulfillmentRequests.item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit,unit_cost,serial_number',
                'fulfillmentRequests.item.category:category_id,category_name,requires_serial_number',
            ])
            ->where('status', 'draft')
            ->whereNull('parent_request_id')
            ->orderBy('requested_at', 'desc')
            ->get();

        // Transfers awaiting release-slip preparation; drafts move to their own queue.
        $pendingTransfers = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit_cost',
                'item.category:category_id,category_name',
            ])
            ->whereIn('status', ['waiting for transfer approval', 'waiting for custodian approval'])
            ->whereHas('user.role', fn ($q) => $q->where('role_name', 'End User'))
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('requested_at', 'desc')
            ->get();

        $transferDrafts = AssignmentRequest::query()
            ->with([
                'user:id,first_name,last_name,username',
                'targetUser:id,first_name,last_name,username',
                'item:item_id,inventory_item_no,item_name,quantity,category_id,status,unit_cost',
                'releaseSlip',
            ])
            ->where('status', 'transfer draft')
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
        // Per-queue counts matching the tabs that render them: Requests lists
        // incoming requests plus custodian assignments awaiting acceptance;
        // Transfers and Returns list their own review queues.
        $pendingRequestsCount = $incomingRequests->count() + $assignmentRequests->count();
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
            'draftRequests'          => $draftRequests,
            'pendingTransfers'       => $pendingTransfers,
            'transferDrafts'         => $transferDrafts,
            'pendingReturns'         => $pendingReturns,
            'transactions'           => $transactions,
            'auditLedger'            => $auditLedger,
            'totalAssignedCount'     => $totalAssignedCount,
            'pendingRequestsCount'   => $pendingRequestsCount,
            'pendingTransfersCount'  => $pendingTransfers->count(),
            'pendingReturnsCount'    => $pendingReturns->count(),
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

    public function draftRequest(Request $request, int $id)
    {
        $assignmentRequest = AssignmentRequest::query()->findOrFail($id);
        $validated = $request->validate([
            'inventory_ids' => [
                Rule::requiredIf(fn (): bool => (bool) $assignmentRequest->requested_item_name && ! $assignmentRequest->item_id),
                'nullable',
                'array',
                'min:1',
                'max:100',
            ],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            ...$this->draftSlipFieldRules(),
        ]);

        $result = DB::transaction(function () use ($request, $id, $validated): AssignmentRequest|string {
            $parent = AssignmentRequest::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($parent->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT], true)) {
                return 'processed';
            }
            abort_unless((int) $parent->target_user_id === (int) $request->user()->id, 403);

            $requesterId = (int) $parent->user_id;
            $inventoryIds = $parent->item_id
                ? [(int) $parent->item_id]
                : array_map('intval', $validated['inventory_ids'] ?? []);
            $lines = $this->draftPropertyReleaseSlipLines($parent, $inventoryIds);
            $items = Inventory::query()
                ->whereIn('item_id', array_column($lines, 'item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            foreach ($lines as $line) {
                $item = $items->get((int) $line['item_id']);
                $held = (int) AssignmentRequest::query()
                    ->whereIn('status', ['waiting for approval', 'draft'])
                    ->where('item_id', $line['item_id'])
                    ->where('id', '!=', $parent->id)
                    ->sum('quantity');

                if (! $item || (int) $line['qty'] > max(0, (int) $item->quantity - $held)) {
                    return 'insufficient';
                }
            }

            $recipient = User::query()->with('role')->findOrFail($requesterId);
            abort_unless($recipient->status === 'active' && $recipient->role?->role_name === 'End User', 422, 'The requester is no longer an active End User.');

            $firstItem = $items->get((int) $lines[0]['item_id']);
            $fields = $validated['slip_fields'] ?? [];
            $parent->update([
                'user_id' => (int) $request->user()->id,
                'target_user_id' => $requesterId,
                'item_id' => null,
                'requested_item_name' => $parent->requested_item_name ?? $firstItem->item_name,
                'requested_category_id' => $parent->requested_category_id ?? $firstItem->category_id,
                'requested_unit' => $parent->requested_unit ?? $firstItem->unit,
                'building' => $recipient->building ?? $parent->building ?? $firstItem->building,
                'room' => $recipient->room ?? $parent->room ?? $firstItem->room,
                'status' => 'draft',
                'requested_at' => now(),
                'expected_return_date' => $fields['expected_return_date'] ?? null,
                'release_slip_fields' => $fields,
            ]);

            foreach ($lines as $line) {
                AssignmentRequest::create([
                    'user_id' => (int) $request->user()->id,
                    'target_user_id' => $requesterId,
                    'item_id' => (int) $line['item_id'],
                    'building' => $parent->building,
                    'room' => $parent->room,
                    'parent_request_id' => $parent->id,
                    'quantity' => (int) $line['qty'],
                    'status' => 'draft',
                    'requested_at' => $parent->requested_at,
                    'expected_return_date' => $parent->expected_return_date,
                    'release_slip_fields' => [
                        'conditions' => [
                            $line['item_id'] => $fields['conditions'][$line['item_id']] ?? null,
                        ],
                    ],
                ]);
            }

            return $parent->fresh();
        });

        if (is_string($result)) {
            $message = $result === 'insufficient'
                ? 'One or more selected inventory records no longer have enough free stock for this draft.'
                : 'This request has already been processed.';

            return response()->json(['message' => $message], $result === 'insufficient' ? 422 : 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Request saved as a draft. Print the slip, collect the required sign-off, then allocate from Drafts.',
            'draft_id' => (int) $result->id,
        ]);
    }

    /**
     * Print-and-hold: persist the Assign modal's selection as a `draft`
     * parent plus one `draft` child per inventory row. Nothing is deducted
     * and no transaction or movement is written — the slip is printable
     * immediately and the units are held until Allocate (or Delete).
     */
    public function storeDraft(Request $request)
    {
        if (is_array($request->input('items'))) {
            return $this->storeMultiDraft($request);
        }

        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
            ...$this->draftSlipFieldRules(),
            'is_temporary' => ['sometimes', 'boolean'],
            'expected_return_date' => [
                Rule::requiredIf(fn () => $request->boolean('is_temporary')),
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $floor = now()->toDateString();
                    if ($request->filled('transaction_date')) {
                        try {
                            $floor = Carbon::parse($request->input('transaction_date'))->toDateString();
                        } catch (\Throwable) {
                        }
                    }
                    if ((string) $value < $floor) {
                        $fail('The expected return date must be on or after the assignment date.');
                    }
                },
            ],
        ]);

        $endUser = User::with('role')->findOrFail($validated['user_id']);

        if (strtolower((string) $endUser->role?->role_name) !== 'end user') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Items can only be drafted for an End User.'], 422);
            }

            return redirect()->back()->withErrors(['user_id' => 'Items can only be drafted for an End User.'])->withInput();
        }

        $draft = $this->inventoryOperations->createDraft(
            (int) $validated['item_id'],
            (int) $request->user()->id,
            (int) $endUser->id,
            (int) $validated['quantity'],
            $validated['transaction_date'] ?? null,
            $validated['expected_return_date'] ?? ($validated['slip_fields']['expected_return_date'] ?? null),
        );

        if (is_string($draft)) {
            $message = match ($draft) {
                'unavailable' => 'The selected item is not available for a draft hold.',
                'insufficient' => 'The requested quantity exceeds the free stock (available minus units already promised).',
                default => 'The selected item cannot be drafted in the requested quantity.',
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->with('error', $message)->withInput();
        }

        if (isset($validated['slip_fields'])) {
            $draft->update(['release_slip_fields' => $validated['slip_fields']]);
        }

        $draft->loadMissing('fulfillmentRequests:id,item_id,parent_request_id');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Draft saved. Print the slip, then allocate once the signed slip is collected.',
                'draft_id' => (int) $draft->id,
                'inventory_ids' => $draft->fulfillmentRequests->map(fn ($child) => (int) $child->item_id)->values()->all(),
            ]);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Draft saved. Print the slip, then allocate once the signed slip is collected.');
    }

    private function storeMultiDraft(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'transaction_date' => ['nullable', 'date'],
            'is_temporary' => ['sometimes', 'boolean'],
            'expected_return_date' => [
                Rule::requiredIf(fn () => $request->boolean('is_temporary')),
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $floor = now()->toDateString();
                    if ($request->filled('transaction_date')) {
                        try {
                            $floor = Carbon::parse($request->input('transaction_date'))->toDateString();
                        } catch (\Throwable) {
                        }
                    }
                    if ((string) $value < $floor) {
                        $fail('The expected return date must be on or after the assignment date.');
                    }
                },
            ],
            ...$this->draftSlipFieldRules(),
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'exists:inventory,item_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $endUser = User::with('role')->findOrFail($validated['user_id']);

        if (strtolower((string) $endUser->role?->role_name) !== 'end user') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Items can only be drafted for an End User.'], 422);
            }

            return redirect()->back()->withErrors(['user_id' => 'Items can only be drafted for an End User.'])->withInput();
        }

        $result = $this->inventoryOperations->createDrafts(
            array_values($validated['items']),
            (int) $request->user()->id,
            (int) $endUser->id,
            $validated['transaction_date'] ?? null,
            $validated['expected_return_date'] ?? ($validated['slip_fields']['expected_return_date'] ?? null),
        );

        if (is_array($result)) {
            return $this->assignmentLineErrors($request, $result['errors'], count($validated['items']));
        }

        $draft = $result;
        if (isset($validated['slip_fields'])) {
            $draft->update(['release_slip_fields' => $validated['slip_fields']]);
        }
        $draft->loadMissing('fulfillmentRequests:id,item_id,parent_request_id');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Draft saved. Print the slip, then allocate once the signed slip is collected.',
                'draft_id' => (int) $draft->id,
                'inventory_ids' => $draft->fulfillmentRequests->map(fn ($child) => (int) $child->item_id)->values()->all(),
            ]);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Draft saved. Print the slip, then allocate once the signed slip is collected.');
    }

    public function updateDraft(Request $request, int $id)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'transaction_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            ...$this->draftSlipFieldRules(true),
        ]);

        $result = $this->inventoryOperations->updateDraft(
            $id,
            (int) $validated['user_id'],
            array_values($validated['items']),
            $validated['transaction_date'],
            $validated['expected_return_date'] ?? null,
            $validated['slip_fields'],
        );

        if (is_array($result)) {
            return $this->assignmentLineErrors($request, $result['errors'], count($validated['items']));
        }

        if (is_string($result)) {
            $message = match ($result) {
                'invalid_recipient' => 'Select an active End User as the recipient.',
                default => 'This draft is no longer available to edit.',
            };

            return response()->json(['message' => $message], $result === 'invalid_recipient' ? 422 : 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Draft updated. Inventory is still held and has not been allocated.',
            'draft_id' => $result->id,
        ]);
    }

    private function draftSlipFieldRules(bool $required = false): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'slip_fields' => [$presence, 'array'],
            'slip_fields.recipient_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.department_unit' => ['nullable', 'string', 'max:150'],
            'slip_fields.designation' => ['nullable', 'string', 'max:150'],
            'slip_fields.purpose' => ['nullable', 'string', Rule::in([
                'Official Use (Off-site)',
                'Transfer / Relocation',
                'Repair / Maintenance',
                'Disposal',
            ])],
            'slip_fields.conditions' => ['nullable', 'array'],
            'slip_fields.conditions.*' => ['nullable', 'string', 'max:100'],
            'slip_fields.expected_return_date' => ['nullable', 'date'],
            'slip_fields.actual_return_date' => ['nullable', 'date'],
            'slip_fields.released_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_date' => ['nullable', 'date'],
            'slip_fields.received_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_date' => ['nullable', 'date'],
            'slip_fields.approved_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Preview a draft slip without saving it.
     *
     * Accepts slip fields and inventory IDs directly, generates a PDF preview
     * without creating a draft request. Used by the Assign modal's slip preview.
     */
    public function draftSlipPreview(Request $request)
    {
        $validated = $request->validate([
            'slip_fields' => ['nullable', 'array'],
            'slip_fields.recipient_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.department_unit' => ['nullable', 'string', 'max:150'],
            'slip_fields.designation' => ['nullable', 'string', 'max:150'],
            'slip_fields.purpose' => ['nullable', 'string', Rule::in([
                'Official Use (Off-site)',
                'Transfer / Relocation',
                'Repair / Maintenance',
                'Disposal',
            ])],
            'slip_fields.conditions' => ['nullable', 'array'],
            'slip_fields.conditions.*' => ['nullable', 'string', 'max:100'],
            'slip_fields.expected_return_date' => ['nullable', 'date'],
            'slip_fields.actual_return_date' => ['nullable', 'date'],
            'slip_fields.released_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_date' => ['nullable', 'date'],
            'slip_fields.received_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_date' => ['nullable', 'date'],
            'slip_fields.approved_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_date' => ['nullable', 'date'],
            'inventory_ids' => ['required', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'exists:inventory,item_id'],
            'inventory_quantities' => ['nullable', 'array'],
            'inventory_quantities.*' => ['required', 'integer', 'min:1'],
            'document_date' => ['nullable', 'date'],
        ]);

        $inventoryIds = $validated['inventory_ids'];
        $slipFields = $validated['slip_fields'] ?? $assignmentRequest->release_slip_fields ?? [];

        $items = Inventory::query()
            ->with('category')
            ->whereIn('item_id', $inventoryIds)
            ->get()
            ->keyBy('item_id');

        abort_unless($items->count() === count($inventoryIds), 422, 'Selected inventory could not be found.');

        $custodian = $request->user();
        $lines = [];
        foreach ($inventoryIds as $itemId) {
            $item = $items->get((int) $itemId);
            $quantity = (int) ($validated['inventory_quantities'][$itemId] ?? $item->quantity);
            abort_unless($quantity <= (int) $item->quantity, 422, 'The requested quantity exceeds the available inventory.');
            $lines[] = [
                'item_id' => $item->item_id,
                'item_no' => $item->inventory_item_no ?? (string) $item->item_id,
                'description' => trim($item->item_name . ($item->serial_number ? ' (SN: ' . $item->serial_number . ')' : '')),
                'qty' => $quantity,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'amount' => (float) ($item->unit_cost ?? 0) * $quantity,
                'condition' => $slipFields['conditions'][$itemId] ?? null,
            ];
        }

        $data = [
            'docNo' => 'DRAFT-PREVIEW',
            'documentDate' => $validated['document_date'] ?? now()->toDateString(),
            'recipientName' => $slipFields['recipient_name'] ?? '',
            'custodianName' => trim($custodian->first_name . ' ' . $custodian->last_name) ?: $custodian->username,
            'lines' => $lines,
            'total' => collect($lines)->sum('amount'),
            'expectedReturnDate' => $slipFields['expected_return_date'] ?? null,
            'actualReturnDate' => $slipFields['actual_return_date'] ?? null,
            'slipFields' => $slipFields,
        ];

        return Pdf::loadView('pdfs.property-release-slip', $data)->stream('DRAFT-PREVIEW.pdf');
    }

    /**
     * Allocate a draft: the single custody-writing step of the draft flow.
     * Revalidates against free stock excluding the draft's own hold, then
     * assigns exactly once. Retries after success report already-processed;
     * drafts starved by newer holds fail stale with Delete/Re-pick guidance.
     */
    public function allocateDraft(Request $request, int $id)
    {
        $result = $this->inventoryOperations->allocateDraft(
            $id,
            (int) $request->user()->id,
        );

        if ($result === 'allocated') {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Draft allocated. Item has been assigned.']);
            }

            return redirect()->route('propertyCustodian.transactions')->with('success', 'Draft allocated. Item has been assigned.');
        }

        $message = match ($result) {
            'processed' => 'This draft has already been processed.',
            default => 'This draft can no longer be allocated: the held units are no longer available. Delete it or pick the units again.',
        };
        $status = $result === 'processed' ? 200 : 422;

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return redirect()->back()->with('error', $message);
    }

    public function releaseSlipData(int $id, Request $request)
    {
        $assignmentRequest = AssignmentRequest::query()->findOrFail($id);
        $validated = $request->validate([
            'inventory_ids' => ['nullable', 'array'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
        ]);
        $data = $this->propertyReleaseSlipData(
            $assignmentRequest,
            $request->user(),
            $validated['inventory_ids'] ?? [],
        );

        return response()->json($data);
    }

    public function releaseSlipPdf(int $id, Request $request)
    {
        $validated = $request->validate([
            'inventory_ids' => ['nullable', 'array'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'slip_fields' => ['nullable', 'array'],
            'slip_fields.recipient_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.department_unit' => ['nullable', 'string', 'max:150'],
            'slip_fields.designation' => ['nullable', 'string', 'max:150'],
            'slip_fields.purpose' => ['nullable', 'string', Rule::in([
                'Official Use (Off-site)',
                'Transfer / Relocation',
                'Repair / Maintenance',
                'Disposal',
            ])],
            'slip_fields.conditions' => ['nullable', 'array'],
            'slip_fields.conditions.*' => ['nullable', 'string', 'max:100'],
            'slip_fields.expected_return_date' => ['nullable', 'date'],
            'slip_fields.actual_return_date' => ['nullable', 'date'],
            'slip_fields.released_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_date' => ['nullable', 'date'],
            'slip_fields.received_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_date' => ['nullable', 'date'],
            'slip_fields.approved_by_signature' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_date' => ['nullable', 'date'],
        ]);

        $assignmentRequest = AssignmentRequest::query()->findOrFail($id);
        $data = $this->propertyReleaseSlipData(
            $assignmentRequest,
            $request->user(),
            $validated['inventory_ids'] ?? [],
        );
        $slipFields = $validated['slip_fields'] ?? [];
        $data['recipientName'] = $slipFields['recipient_name'] ?? $data['recipientName'];
        $slipFields['released_by_name'] = $slipFields['released_by_name'] ?? $data['custodianName'];
        $slipFields['received_by_name'] = $slipFields['received_by_name'] ?? $data['recipientName'];
        $data['slipFields'] = $slipFields;
        $data['expectedReturnDate'] = $slipFields['expected_return_date'] ?? $data['expectedReturnDate'];
        $data['actualReturnDate'] = $slipFields['actual_return_date'] ?? $data['actualReturnDate'];
        $data['lines'] = collect($data['lines'])
            ->map(function (array $line) use ($slipFields): array {
                $line['condition'] = $slipFields['conditions'][$line['item_id']] ?? null;

                return $line;
            })
            ->all();

        return $request->boolean('preview')
            ? Pdf::loadView('pdfs.property-release-slip', $data)->stream($data['docNo'].'.pdf')
            : Pdf::loadView('pdfs.property-release-slip', $data)->download($data['docNo'].'.pdf');
    }

    private function propertyReleaseSlipData(AssignmentRequest $assignmentRequest, User $custodian, array $inventoryIds = []): array
    {
        if ($assignmentRequest->status !== 'approved') {
            abort_unless(
                in_array($assignmentRequest->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT, 'draft'], true),
                409,
                'A release slip is available only for a pending or approved request.',
            );

            $lines = $this->draftPropertyReleaseSlipLines($assignmentRequest, $inventoryIds);
            $assignmentRequest->loadMissing(['user.role', 'targetUser.role']);
            // Drafts are custodian-held for a named recipient: the slip goes to
            // the target end user, not the custodian who keyed the draft.
            $recipient = $assignmentRequest->status === 'draft'
                ? ($assignmentRequest->targetUser ?? $assignmentRequest->user)
                : ($assignmentRequest->requested_item_name
                    ? $assignmentRequest->user
                    : (strtolower((string) $assignmentRequest->targetUser?->role?->role_name) === 'end user'
                        ? $assignmentRequest->targetUser
                        : $assignmentRequest->user));

            return [
                'docNo' => sprintf('PRS-%s-%06d', now()->format('Y'), $assignmentRequest->id),
                'documentDate' => now()->toDateString(),
                'recipientName' => $recipient
                    ? trim($recipient->first_name.' '.$recipient->last_name) ?: $recipient->username
                    : 'Unknown Recipient',
                'custodianName' => trim($custodian->first_name.' '.$custodian->last_name) ?: $custodian->username,
                'lines' => $lines,
                'total' => collect($lines)->sum('amount'),
                'expectedReturnDate' => $assignmentRequest->expected_return_date
                    ? Carbon::parse($assignmentRequest->expected_return_date)->toDateString()
                    : null,
                'actualReturnDate' => null,
                'slipFields' => $assignmentRequest->release_slip_fields ?? [],
            ];
        }

        $assignmentRequest->loadMissing([
            'transaction.item',
            'transaction.user',
            'fulfillmentRequests.transaction.item',
            'fulfillmentRequests.transaction.user',
        ]);

        $transactions = collect([$assignmentRequest->transaction])
            ->merge($assignmentRequest->fulfillmentRequests->map(fn (AssignmentRequest $fulfillment): ?Transaction => $fulfillment->transaction))
            ->filter()
            ->unique('id')
            ->values();

        abort_if($transactions->isEmpty(), 409, 'No approved allocation is available for this request.');

        $lines = $transactions
            ->map(function (Transaction $transaction): ?array {
                $item = $transaction->item;
                if (! $item) {
                    return null;
                }

                $quantity = (int) ($transaction->issued_quantity ?? $transaction->quantity);
                $unitCost = (float) ($item->unit_cost ?? 0);

                return [
                    'item_id' => $item->item_id,
                    'item_no' => $item->inventory_item_no ?? (string) $item->item_id,
                    'description' => trim($item->item_name.($item->serial_number ? ' (SN: '.$item->serial_number.')' : '')),
                    'qty' => $quantity,
                    'unit_cost' => $unitCost,
                    'amount' => $unitCost * $quantity,
                ];
            })
            ->filter()
            ->values();
        abort_if($lines->isEmpty(), 409, 'No inventory lines are available for this request.');

        $recipient = $transactions->first()->user;
        $documentDate = $transactions->first()->transaction_date;
        $expectedReturnDate = $transactions
            ->pluck('expected_return_date')
            ->filter()
            ->sort()
            ->first();
        $actualReturnDate = $transactions
            ->pluck('return_date')
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'docNo' => sprintf('PRS-%s-%06d', now()->format('Y'), $assignmentRequest->id),
            'documentDate' => $documentDate?->format('Y-m-d') ?? now()->toDateString(),
            'recipientName' => $recipient
                ? trim($recipient->first_name.' '.$recipient->last_name) ?: $recipient->username
                : 'Unknown Recipient',
            'custodianName' => trim($custodian->first_name.' '.$custodian->last_name) ?: $custodian->username,
            'lines' => $lines->all(),
            'total' => $lines->sum('amount'),
            'expectedReturnDate' => $expectedReturnDate?->format('Y-m-d'),
            'actualReturnDate' => $actualReturnDate?->format('Y-m-d'),
            'slipFields' => [],
        ];
    }

    private function draftPropertyReleaseSlipLines(AssignmentRequest $assignmentRequest, array $inventoryIds): array
    {
        if ($assignmentRequest->status === 'draft') {
            $assignmentRequest->loadMissing('fulfillmentRequests.item');
            $fulfillments = $assignmentRequest->fulfillmentRequests;

            abort_if($fulfillments->isEmpty(), 409, 'No inventory lines are available for this draft.');

            return $fulfillments
                ->map(function (AssignmentRequest $fulfillment): array {
                    $item = $fulfillment->item;
                    abort_unless($item && (int) $fulfillment->quantity > 0, 409, 'Draft inventory details are unavailable.');

                    $unitCost = (float) ($item->unit_cost ?? 0);

                    return [
                        'item_id' => $item->item_id,
                        'item_no' => $item->inventory_item_no ?? (string) $item->item_id,
                        'description' => trim($item->item_name.($item->serial_number ? ' (SN: '.$item->serial_number.')' : '')),
                        'qty' => (int) $fulfillment->quantity,
                        'unit_cost' => $unitCost,
                        'amount' => $unitCost * (int) $fulfillment->quantity,
                    ];
                })
                ->all();
        }

        if ($assignmentRequest->item_id) {
            $item = Inventory::query()->with('category')->findOrFail($assignmentRequest->item_id);
            abort_unless(
                $item->status === 'available' && (int) $item->quantity >= (int) $assignmentRequest->quantity,
                409,
                'The requested inventory is no longer available.',
            );
            if ($item->category?->requires_serial_number) {
                abort_unless(
                    (int) $assignmentRequest->quantity === 1
                        && (int) $item->quantity === 1
                        && $item->serial_number,
                    409,
                    'The requested inventory does not meet serial-number requirements.',
                );
            }
            $items = collect([$item]);
        } else {
            abort_if($inventoryIds === [], 422, 'Select matching inventory before preparing a release slip.');
            $itemsById = Inventory::query()
                ->with('category')
                ->whereIn('item_id', $inventoryIds)
                ->get()
                ->keyBy('item_id');
            abort_unless($itemsById->count() === count($inventoryIds), 422, 'Selected inventory could not be found.');

            $category = Category::find($assignmentRequest->requested_category_id);
            abort_unless($category, 422, 'The requested category could not be found.');

            $items = collect($inventoryIds)
                ->map(fn ($itemId) => $itemsById->get((int) $itemId))
                ->values();

            foreach ($items as $item) {
                abort_unless(
                    RequestableItemMatcher::matches(
                        (string) $assignmentRequest->requested_item_name,
                        (int) $assignmentRequest->requested_category_id,
                        $assignmentRequest->requested_unit,
                        $item,
                    ) && $item->status === 'available' && (int) $item->quantity >= 1,
                    422,
                    'Selected inventory is unavailable or does not match the request.',
                );
                if ($category->requires_serial_number) {
                    abort_unless((int) $item->quantity === 1 && $item->serial_number, 422, 'Selected inventory does not meet serial-number requirements.');
                }
            }

            abort_unless(
                $items->sum(fn (Inventory $item): int => (int) $item->quantity) >= (int) $assignmentRequest->quantity,
                422,
                'Selected inventory does not cover the requested quantity.',
            );
            if ($category->requires_serial_number) {
                abort_unless(count($inventoryIds) === (int) $assignmentRequest->quantity, 422, 'Select one serialised unit for each requested unit.');
            }
        }

        $remaining = (int) $assignmentRequest->quantity;
        $lines = [];
        foreach ($items as $item) {
            if ($remaining === 0) {
                break;
            }

            $quantity = $item->category?->requires_serial_number
                ? 1
                : min($remaining, (int) $item->quantity);
            $unitCost = (float) ($item->unit_cost ?? 0);
            $lines[] = [
                'item_id' => $item->item_id,
                'item_no' => $item->inventory_item_no ?? (string) $item->item_id,
                'description' => trim($item->item_name.($item->serial_number ? ' (SN: '.$item->serial_number.')' : '')),
                'qty' => $quantity,
                'unit_cost' => $unitCost,
                'amount' => $unitCost * $quantity,
            ];
            $remaining -= $quantity;
        }

        abort_unless($remaining === 0, 422, 'Selected inventory does not cover the requested quantity.');

        return $lines;
    }

    public function cancelItemRequest(Request $request, int $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $cancelled = DB::transaction(function () use ($id, $validated): bool {
            $assignmentRequest = AssignmentRequest::query()->lockForUpdate()->findOrFail($id);
            if (
                ! in_array($assignmentRequest->status, ['waiting for approval', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT, 'draft'], true)
                || ! $assignmentRequest->requested_item_name
                || $assignmentRequest->item_id
            ) {
                return false;
            }

            $wasDraft = $assignmentRequest->status === 'draft';

            $assignmentRequest->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'],
                'responded_at' => now(),
            ]);

            // Draft holds live on the children: releasing the parent alone
            // would strand promised units, so the whole draft set cancels.
            if ($wasDraft) {
                AssignmentRequest::query()
                    ->where('parent_request_id', $assignmentRequest->id)
                    ->where('status', 'draft')
                    ->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => $validated['cancellation_reason'],
                        'responded_at' => now(),
                    ]);
            }

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

    public function storeTransferReleaseSlip(Request $request, int $id)
    {
        $validated = $request->validate($this->transferReleaseSlipRules());
        $slipFields = $validated['slip_fields'];

        $transfer = DB::transaction(function () use ($request, $id, $slipFields): AssignmentRequest {
            $transfer = AssignmentRequest::query()
                ->with(['user.role', 'targetUser.role', 'item'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                in_array($transfer->status, ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'], true)
                    && strtolower((string) $transfer->user?->role?->role_name) === 'end user'
                    && strtolower((string) $transfer->targetUser?->role?->role_name) === 'end user'
                    && $transfer->item,
                409,
                'This transfer is no longer available for drafting.',
            );

            TransferReleaseSlip::query()->updateOrCreate(
                ['request_id' => $transfer->id],
                [
                    'custodian_id' => $request->user()->id,
                    'recipient_name' => $slipFields['recipient_name'],
                    'department_unit' => $slipFields['department_unit'] ?? null,
                    'designation' => $slipFields['designation'] ?? null,
                    'expected_return_date' => $slipFields['expected_return_date'] ?? null,
                    'actual_return_date' => $slipFields['actual_return_date'] ?? null,
                    'purpose' => $slipFields['purpose'],
                    'condition_upon_release' => $slipFields['condition_upon_release'] ?? null,
                    'released_by_name' => $slipFields['released_by_name'] ?? null,
                    'released_by_date' => $slipFields['released_by_date'] ?? null,
                    'received_by_name' => $slipFields['received_by_name'] ?? null,
                    'received_by_date' => $slipFields['received_by_date'] ?? null,
                    'approved_by_name' => $slipFields['approved_by_name'] ?? null,
                    'approved_by_date' => $slipFields['approved_by_date'] ?? null,
                    'status' => 'draft',
                    'completed_at' => null,
                ],
            );

            $transfer->update(['status' => 'transfer draft']);

            return $transfer;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Release slip saved to Drafts. Inventory has not been allocated.',
                'transfer_id' => $transfer->id,
            ]);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Release slip saved to Drafts. Inventory has not been allocated.');
    }

    public function transferReleaseSlipPreview(Request $request, int $id)
    {
        $validated = $request->validate($this->transferReleaseSlipRules());
        $transfer = $this->transferRequestForSlip($id);
        $data = $this->transferReleaseSlipData(
            $transfer,
            $request->user(),
            $validated['slip_fields'],
        );

        return Pdf::loadView('pdfs.property-release-slip', $data)->stream($data['docNo'].'.pdf');
    }

    public function transferReleaseSlipPdf(int $id, Request $request)
    {
        $transfer = $this->transferRequestForSlip($id);
        $slip = $transfer->releaseSlip;
        abort_unless($slip, 404, 'No release slip has been saved for this transfer.');

        $data = $this->transferReleaseSlipData($transfer, $request->user(), $slip->toArray());

        $pdf = Pdf::loadView('pdfs.property-release-slip', $data);

        return $request->boolean('preview')
            ? $pdf->stream($data['docNo'].'.pdf')
            : $pdf->download($data['docNo'].'.pdf');
    }

    private function transferReleaseSlipRules(): array
    {
        return [
            'slip_fields' => ['required', 'array'],
            'slip_fields.recipient_name' => ['required', 'string', 'max:150'],
            'slip_fields.department_unit' => ['nullable', 'string', 'max:150'],
            'slip_fields.designation' => ['nullable', 'string', 'max:150'],
            'slip_fields.expected_return_date' => ['nullable', 'date'],
            'slip_fields.actual_return_date' => ['nullable', 'date'],
            'slip_fields.purpose' => ['required', 'string', Rule::in([
                'Official Use (Off-site)',
                'Transfer / Relocation',
                'Repair / Maintenance',
                'Disposal',
            ])],
            'slip_fields.condition_upon_release' => ['nullable', 'string', 'max:1000'],
            'slip_fields.released_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.released_by_date' => ['nullable', 'date'],
            'slip_fields.received_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.received_by_date' => ['nullable', 'date'],
            'slip_fields.approved_by_name' => ['nullable', 'string', 'max:150'],
            'slip_fields.approved_by_date' => ['nullable', 'date'],
        ];
    }

    private function transferRequestForSlip(int $id): AssignmentRequest
    {
        $transfer = $this->peerTransferRequest($id);

        abort_unless(
            in_array($transfer->status, ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft', 'approved'], true)
                && $transfer->item,
            409,
            'This transfer has no printable release slip.',
        );

        return $transfer;
    }

    private function peerTransferRequest(int $id): AssignmentRequest
    {
        $transfer = AssignmentRequest::query()
            ->with(['user.role', 'targetUser.role', 'item', 'releaseSlip'])
            ->findOrFail($id);

        abort_unless(
            strtolower((string) $transfer->user?->role?->role_name) === 'end user'
                && strtolower((string) $transfer->targetUser?->role?->role_name) === 'end user'
                && $transfer->item,
            404,
            'Peer transfer request not found.',
        );

        return $transfer;
    }

    private function transferReleaseSlipData(AssignmentRequest $transfer, User $custodian, array $fields): array
    {
        $item = $transfer->item;
        $senderName = trim((string) $transfer->user?->first_name.' '.(string) $transfer->user?->last_name)
            ?: (string) ($transfer->user?->username ?? 'Unknown Sender');
        $recipientName = $fields['recipient_name']
            ?? trim((string) $transfer->targetUser?->first_name.' '.(string) $transfer->targetUser?->last_name)
            ?: (string) ($transfer->targetUser?->username ?? 'Unknown Recipient');
        $custodianName = trim($custodian->first_name.' '.$custodian->last_name) ?: $custodian->username;
        $unitCost = (float) ($item->unit_cost ?? 0);

        return [
            'docNo' => sprintf('PRS-%s-%06d', now()->format('Y'), $transfer->id),
            'documentDate' => $transfer->requested_at?->format('Y-m-d') ?? now()->toDateString(),
            'recipientName' => $recipientName,
            'transferFromName' => $senderName,
            'custodianName' => $custodianName,
            'lines' => [[
                'item_id' => $item->item_id,
                'item_no' => $item->inventory_item_no ?? (string) $item->item_id,
                'description' => trim($item->item_name.($item->serial_number ? ' (SN: '.$item->serial_number.')' : '')),
                'qty' => (int) $transfer->quantity,
                'unit_cost' => $unitCost,
                'amount' => $unitCost * (int) $transfer->quantity,
                'condition' => $fields['condition_upon_release'] ?? null,
            ]],
            'total' => $unitCost * (int) $transfer->quantity,
            'expectedReturnDate' => $fields['expected_return_date'] ?? null,
            'actualReturnDate' => $fields['actual_return_date'] ?? null,
            'slipFields' => $fields,
        ];
    }

    /**
     * Finalizes a transfer only after a release-slip draft has been saved and
     * the custodian confirms that the physical handoff and signatures are complete.
     */
    public function allocateTransfer(Request $request, int $id)
    {
        return $this->approveTransfer($request, $id);
    }

    public function approveTransfer(Request $request, $id)
    {
        $request->validate(['confirmed' => ['required', 'accepted']]);
        $transferRequest = $this->peerTransferRequest((int) $id);

        if ($transferRequest->status !== 'transfer draft') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Save a release slip draft before allocating this transfer.'], 409);
            }

            return redirect()->back()->with('error', 'Save a release slip draft before allocating this transfer.');
        }

        $approved = $this->inventoryOperations->approveTransfer(
            $transferRequest->id,
            (int) $request->user()->id,
        );

        if (! $approved) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This transfer is no longer available for allocation.'], 409);
            }

            return redirect()->back()->with('error', 'This transfer is no longer available for allocation.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer allocated and release slip finalized.',
            ]);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Transfer allocated and release slip finalized.');
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

        $transferRequest = $this->peerTransferRequest((int) $id);

        if (! in_array($transferRequest->status, ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This transfer request has already been processed.'], 409);
            }

            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        if (! $this->inventoryOperations->declineTransferRequest($transferRequest->id, $validated['notes'] ?? null)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This transfer request has already been processed.'], 409);
            }

            return redirect()->back()->with('error', 'This transfer request has already been processed.');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Transfer declined and sender assignment restored.']);
        }

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Transfer request declined. Sender\'s item restored.');
    }

    public function assignItem(Request $request)
    {
        $assignmentType = $request->input('assignment_type', 'registered');

        if ($assignmentType === 'manual') {
            if (! is_array($request->input('items'))) {
                return $this->assignSingleManual($request);
            }

            $validated = $request->validate([
                'manual_recipient_name' => ['required', 'string', 'max:255'],
                'manual_department' => ['nullable', 'string', 'max:255'],
                'building' => ['nullable', 'string', 'max:255'],
                'room' => ['nullable', 'string', 'max:255'],
                'manual_recipient_type' => ['nullable', 'string', 'max:100'],
                'manual_contact' => ['nullable', 'string', 'max:255'],
                'manual_notes' => ['nullable', 'string', 'max:1000'],
                'transaction_date' => ['required', 'date'],
                'expected_return_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
                'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*.item_id' => ['required', 'exists:inventory,item_id'],
                'items.*.category_id' => ['required', 'exists:categories,category_id'],
                'items.*.unit' => ['required', 'string', 'max:255'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);

            $shared = collect($validated)->except('items')->all();
            $lines = array_values(array_map(
                fn ($line) => [...$shared, ...$line],
                $validated['items'],
            ));
            $result = $this->inventoryOperations->issueManuals($lines, (int) $request->user()->id);

            if (isset($result['errors'])) {
                return $this->assignmentLineErrors($request, $result['errors'], count($lines));
            }

            $count = count($result['transactions'] ?? []);

            return redirect()->route('propertyCustodian.transactions')->with(
                'success',
                $count === 1
                    ? 'Item issued manually and recorded in transaction history.'
                    : "{$count} items issued manually and recorded in transaction history.",
            );
        }

        if (! is_array($request->input('items'))) {
            return $this->assignSingleRegistered($request);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'transaction_date' => ['nullable', 'date'],
            'is_temporary' => ['sometimes', 'boolean'],
            'expected_return_date' => [
                Rule::requiredIf(fn () => $request->boolean('is_temporary')),
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $floor = now()->toDateString();
                    if ($request->filled('transaction_date')) {
                        try {
                            $floor = Carbon::parse($request->input('transaction_date'))->toDateString();
                        } catch (\Throwable) {
                        }
                    }
                    if ((string) $value < $floor) {
                        $fail('The expected return date must be on or after the assignment date.');
                    }
                },
            ],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'exists:inventory,item_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $endUser = User::with('role')->findOrFail($validated['user_id']);
        $userId = $request->user()?->getAuthIdentifier();

        if (strtolower((string) $endUser->role?->role_name) !== 'end user') {
            return redirect()->back()->withErrors(['user_id' => 'Items can only be assigned to an End User.'])->withInput();
        }

        $result = $this->inventoryOperations->submitRegisteredAssignments(
            array_values($validated['items']),
            (int) $userId,
            (int) $endUser->id,
            $validated['transaction_date'] ?? null,
            $validated['expected_return_date'] ?? null,
        );

        if (isset($result['errors'])) {
            return $this->assignmentLineErrors($request, $result['errors'], count($validated['items']));
        }

        $count = count($result['requests'] ?? []);

        return redirect()->route('propertyCustodian.transactions')->with(
            'success',
            $count === 1
                ? 'Assignment request submitted for approval.'
                : "{$count} assignment requests submitted for approval.",
        );
    }

    /**
     * Per-line failures in stockInImport shape: nothing was written,
     * errors keyed by line position for inline display.
     */
    private function assignmentLineErrors(Request $request, array $lineErrors, int $lineCount)
    {
        $message = $lineCount === 1
            ? 'The assignment could not be completed. Review the line below and try again.'
            : 'Some assignment lines could not be completed. Nothing was saved; review the marked lines and try again.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => $lineErrors], 422);
        }

        return redirect()->back()->withErrors(['items' => $message])->withInput();
    }

    private function assignSingleManual(Request $request)
    {
        $validated = $request->validate([
                'item_id' => ['required', 'exists:inventory,item_id'],
                'category_id' => ['required', 'exists:categories,category_id'],
                'unit' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'integer', 'min:1'],
                'manual_recipient_name' => ['required', 'string', 'max:255'],
                'manual_department' => ['nullable', 'string', 'max:255'],
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

    private function assignSingleRegistered(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
            'is_temporary' => ['sometimes', 'boolean'],
            'expected_return_date' => [
                Rule::requiredIf(fn () => $request->boolean('is_temporary')),
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $floor = now()->toDateString();
                    if ($request->filled('transaction_date')) {
                        try {
                            $floor = Carbon::parse($request->input('transaction_date'))->toDateString();
                        } catch (\Throwable) {
                        }
                    }
                    if ((string) $value < $floor) {
                        $fail('The expected return date must be on or after the assignment date.');
                    }
                },
            ],
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
            $validated['expected_return_date'] ?? null,
        );

        if ($assignmentResult === 'unavailable') {
            return redirect()->back()->withErrors(['item_id' => 'The selected item is not available for assignment.'])->withInput();
        }
        if ($assignmentResult === 'insufficient') {
            return redirect()->back()->withErrors(['quantity' => 'The requested quantity exceeds the free stock (available minus units already pending acceptance).'])->withInput();
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
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->building = $validated['building'] ?? null;
        $user->room = $validated['room'] ?? null;

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
            'quantity' => ['required', 'integer', 'min:1'],
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

    public function approvedIarsForStockIn()
    {
        $inspections = ArrivalInspection::query()
            ->where('status', 'approved')
            ->whereNull('posted_at')
            ->where('acceptance_status', '!=', 'rejected')
            ->whereNotNull('iar_number')
            ->with('items:id,arrival_inspection_id,stock_property_number,description,unit,quantity')
            ->orderBy('completed_at')
            ->get();

        return response()->json(['inspections' => $inspections]);
    }

    public function stockInApprovedIar(Request $request)
    {
        $validated = $request->validate([
            'arrival_inspection_id' => ['required', 'integer', 'exists:arrival_inspections,id'],
            'ics_no' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.arrival_inspection_item_id' => ['required', 'integer', 'distinct', 'exists:arrival_inspection_items,id'],
            'rows.*.category_id' => ['required', 'integer', 'exists:categories,category_id'],
            'rows.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'rows.*.lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'rows.*.serial_numbers' => ['nullable', 'array'],
            'rows.*.serial_numbers.*' => ['required', 'string', 'max:255', 'distinct', 'unique:inventory,serial_number'],
        ]);

        $userId = $request->user()->getAuthIdentifier();
        $stockInResult = DB::transaction(function () use ($validated, $userId): array {
            $inspection = ArrivalInspection::query()
                ->whereKey($validated['arrival_inspection_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $inspection->status === 'approved' && $inspection->posted_at === null,
                409,
                'This IAR is not available for Stock In. It may already be posted.',
            );
            abort_unless($inspection->iar_number, 409, 'An IAR number is required before Stock In.');
            abort_if($inspection->acceptance_status === 'rejected', 409, 'A fully rejected delivery cannot be stocked in.');

            $inspectionItems = $inspection->items()->orderBy('id')->get();
            $submittedRows = collect($validated['rows'])->keyBy('arrival_inspection_item_id');
            $expectedIds = $inspectionItems->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $submittedIds = $submittedRows->keys()->map(fn ($id) => (int) $id)->sort()->values();

            if ($expectedIds->all() !== $submittedIds->all()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'rows' => ['Stock In must include each accepted IAR item exactly once.'],
                ]);
            }

            $dateAcquired = $inspection->receipt_date?->toDateString()
                ?? $inspection->inspection_date?->toDateString()
                ?? now()->toDateString();
            if ($dateAcquired > now()->toDateString()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'arrival_inspection_id' => ['The IAR receipt date cannot be in the future for Stock In.'],
                ]);
            }
            $created = 0;

            foreach ($inspectionItems as $inspectionItem) {
                if (mb_strlen($inspectionItem->description) > 255) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'rows' => ["IAR item {$inspectionItem->id} exceeds the 255-character inventory item name limit."],
                    ]);
                }

                $row = $submittedRows->get($inspectionItem->id);
                $category = Category::query()->findOrFail($row['category_id']);
                $serialNumbers = array_values(array_map('trim', $row['serial_numbers'] ?? []));

                if ($category->requires_serial_number) {
                    if (count($serialNumbers) !== (int) $inspectionItem->quantity || in_array('', $serialNumbers, true)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            "rows.{$inspectionItem->id}.serial_numbers" => [
                                "Enter one unique serial number for each of the {$inspectionItem->quantity} accepted units.",
                            ],
                        ]);
                    }
                } elseif ($serialNumbers !== []) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "rows.{$inspectionItem->id}.serial_numbers" => ['This category does not use serial numbers.'],
                    ]);
                }

                $lifespanYears = $row['lifespan_years'] ?? $category->default_lifespan_years;
                $lifespanYears = $lifespanYears === null ? null : (int) $lifespanYears;
                $itemAttributes = [
                    'category_id' => $category->category_id,
                    'unit' => $inspectionItem->unit,
                    'user_id' => $userId,
                    'item_name' => $inspectionItem->description,
                    'description' => "Received from approved IAR {$inspection->iar_number}",
                    'ics_no' => $validated['ics_no'] ?? null,
                    'unit_cost' => $row['unit_cost'],
                    'date_acquired' => $dateAcquired,
                    'lifespan_years' => $lifespanYears,
                    'expected_end_date' => $lifespanYears === null
                        ? null
                        : Carbon::parse($dateAcquired)->addYears($lifespanYears)->toDateString(),
                    'arrival_inspection_id' => $inspection->id,
                    'arrival_inspection_item_id' => $inspectionItem->id,
                ];

                if ($category->requires_serial_number) {
                    foreach ($serialNumbers as $serialNumber) {
                        $this->inventoryOperations->stockIn([
                            ...$itemAttributes,
                            'quantity' => 1,
                            'serial_number' => $serialNumber,
                        ]);
                        $created++;
                    }

                    continue;
                }

                if ($category->requires_qr_code) {
                    for ($index = 0; $index < (int) $inspectionItem->quantity; $index++) {
                        $this->inventoryOperations->stockIn([
                            ...$itemAttributes,
                            'quantity' => 1,
                            'serial_number' => null,
                        ]);
                        $created++;
                    }

                    continue;
                }

                $this->inventoryOperations->stockIn([
                    ...$itemAttributes,
                    'quantity' => $inspectionItem->quantity,
                    'serial_number' => null,
                ]);
                $created++;
            }

            $inspection->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            return [
                'created_records' => $created,
                'iar_number' => $inspection->iar_number,
            ];
        });

        return response()->json([
            'message' => "Stock In completed from IAR {$stockInResult['iar_number']}.",
            'created_records' => $stockInResult['created_records'],
        ], 201);
    }

    public function stockInImport(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:100'],
        ]);

        $userId = $request->user()?->getAuthIdentifier();
        $seenSerials = [];
        $imported = 0;
        $failed = 0;
        $rowErrors = [];

        foreach ($validated['rows'] as $index => $row) {
            $row = is_array($row) ? $row : [];
            // CSV blanks arrive as empty strings; normalize nullable fields.
            foreach (['lifespan_years', 'ics_no'] as $nullable) {
                if (($row[$nullable] ?? null) === '') {
                    $row[$nullable] = null;
                }
            }
            if (($row['serial_numbers'] ?? null) === '') {
                $row['serial_numbers'] = [];
            }

            $validator = Validator::make($row, [
                'item_name' => ['required', 'string', 'max:255'],
                'category_id' => ['required', 'exists:categories,category_id'],
                'unit' => ['required', 'string', 'max:255'],
                'unit_cost' => ['required', 'numeric', 'min:0'],
                'date_acquired' => ['required', 'date', 'before_or_equal:today'],
                'lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'quantity' => ['required', 'integer', 'min:1'],
                'ics_no' => ['nullable', 'string', 'max:255'],
                'serial_numbers' => ['nullable', 'array'],
                'serial_numbers.*' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $failed++;
                $rowErrors[$index] = $validator->errors()->messages();
                continue;
            }

            $data = $validator->validated();
            $category = Category::find($data['category_id']);
            $lifespanYears = ($data['lifespan_years'] ?? null) ?? $category->default_lifespan_years;
            $lifespanYears = $lifespanYears === null ? null : (int) $lifespanYears;
            $expectedEndDate = $lifespanYears === null
                ? null
                : Carbon::parse($data['date_acquired'])->addYears($lifespanYears)->toDateString();

            $serialNumbers = [];
            if ($category->requires_serial_number) {
                $serialNumbers = array_values(array_filter(
                    array_map(fn ($serial) => trim((string) $serial), (array) ($data['serial_numbers'] ?? [])),
                    fn ($serial) => $serial !== '',
                ));

                $serialValidator = Validator::make(['serial_numbers' => $serialNumbers], [
                    'serial_numbers' => ['required', 'array', 'size:' . (int) $data['quantity']],
                    'serial_numbers.*' => ['required', 'string', 'max:255', 'distinct', 'unique:inventory,serial_number'],
                ]);

                $serialMessages = $serialValidator->fails() ? $serialValidator->errors()->messages() : [];
                $batchDuplicates = array_values(array_intersect($serialNumbers, $seenSerials));
                if ($batchDuplicates !== []) {
                    $serialMessages['serial_numbers'][] = 'Duplicate serial number(s) within this import: ' . implode(', ', array_unique($batchDuplicates)) . '.';
                }

                if ($serialMessages !== []) {
                    $failed++;
                    $rowErrors[$index] = $serialMessages;
                    continue;
                }

                foreach ($serialNumbers as $serialNumber) {
                    $seenSerials[] = $serialNumber;
                }
            }

            $itemAttributes = [
                'category_id' => $data['category_id'],
                'unit' => $data['unit'],
                'user_id' => $userId,
                'item_name' => $data['item_name'],
                'description' => null,
                'building' => null,
                'room' => null,
                'ics_no' => $data['ics_no'] ?? null,
                'unit_cost' => $data['unit_cost'],
                'date_acquired' => $data['date_acquired'],
                'lifespan_years' => $lifespanYears,
                'expected_end_date' => $expectedEndDate,
            ];

            DB::transaction(function () use ($itemAttributes, $serialNumbers, $category, $data): void {
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
                    for ($index = 0; $index < (int) $data['quantity']; $index++) {
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
                    'quantity' => $data['quantity'],
                    'serial_number' => null,
                ]);
            });

            $imported++;
        }

        $total = $imported + $failed;

        return response()->json([
            'status' => $failed === 0 ? 'success' : 'error',
            'message' => $failed === 0
                ? "Imported {$imported} of {$total} row(s)."
                : "Imported {$imported} of {$total} row(s); {$failed} failed.",
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $rowErrors,
        ]);
    }

    public function categories(Request $request)
    {
        $categories = Category::query()
            ->withCount('inventoryItems as items_count')
            ->orderBy('category_name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:255', 'unique:categories,category_name'],
            'default_lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'requires_serial_number' => ['sometimes', 'boolean'],
            'requires_qr_code' => ['sometimes', 'boolean'],
            'is_maintenance_eligible' => ['sometimes', 'boolean'],
        ]);

        $category = Category::create([
            'category_name' => $validated['category_name'],
            'default_lifespan_years' => $validated['default_lifespan_years'] ?? null,
            'requires_serial_number' => $validated['requires_serial_number'] ?? false,
            'requires_qr_code' => $validated['requires_qr_code'] ?? false,
            'is_maintenance_eligible' => $validated['is_maintenance_eligible'] ?? false,
        ]);

        $category = Category::query()
            ->withCount('inventoryItems as items_count')
            ->findOrFail($category->category_id);

        return response()->json([
            'message' => "Category '{$category->category_name}' created.",
            'category' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:255', Rule::unique('categories', 'category_name')->ignore($category->category_id, 'category_id')],
            'default_lifespan_years' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'requires_serial_number' => ['sometimes', 'boolean'],
            'requires_qr_code' => ['sometimes', 'boolean'],
            'is_maintenance_eligible' => ['sometimes', 'boolean'],
        ]);

        $category->update([
            'category_name' => $validated['category_name'],
            'default_lifespan_years' => $validated['default_lifespan_years'] ?? null,
            'requires_serial_number' => $validated['requires_serial_number'] ?? false,
            'requires_qr_code' => $validated['requires_qr_code'] ?? false,
            'is_maintenance_eligible' => $validated['is_maintenance_eligible'] ?? false,
        ]);

        $category = Category::query()
            ->withCount('inventoryItems as items_count')
            ->findOrFail($category->category_id);

        return response()->json([
            'message' => "Category '{$category->category_name}' updated.",
            'category' => $category,
        ]);
    }

    public function destroyCategory(Request $request, Category $category)
    {
        $itemsCount = $category->inventoryItems()->count();

        if ($itemsCount > 0) {
            return response()->json([
                'message' => "Cannot delete '{$category->category_name}': it is used by {$itemsCount} inventoried item(s).",
                'items' => $itemsCount,
            ], 409);
        }

        $name = $category->category_name;
        $category->delete();

        return response()->json(['message' => "Category '{$name}' deleted."]);
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
            'quantity' => ['required', 'integer', 'min:1'],
            'building' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
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
            $validated['reason'],
        );

        if ($error !== null) {
            return redirect()->back()->with('error', $error)->withInput();
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Inventory item updated successfully.');
    }

    public function destroyInventory(Request $request, Inventory $inventory)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $reason = $request->input('reason');
        $actorId = (int) $request->user()->id;
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
            $snapshots = $items->map(fn (Inventory $item) => $this->deletedItemLogAttributes($item, $actorId, $reason))->all();
            DB::transaction(function () use ($selectedIds, $snapshots): void {
                Inventory::whereIn('item_id', $selectedIds)->delete();
                foreach ($snapshots as $snapshot) {
                    InventoryItemLog::create($snapshot);
                }
            });

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

        $snapshot = $this->deletedItemLogAttributes($inventory, $actorId, $reason);
        DB::transaction(function () use ($inventory, $snapshot): void {
            $inventory->delete();
            InventoryItemLog::create($snapshot);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Inventory item deleted successfully.');
    }

    private function deletedItemLogAttributes(Inventory $item, int $actorId, ?string $reason): array
    {
        return [
            'item_id' => $item->item_id,
            'inventory_item_no' => $item->inventory_item_no,
            'item_name' => $item->item_name,
            'category_id' => $item->category_id,
            'status' => $item->status,
            'action' => InventoryItemLog::ACTION_DELETED,
            'changes' => [
                'item_name' => $item->item_name,
                'quantity' => (int) $item->quantity,
                'unit_cost' => $item->unit_cost,
                'unit' => $item->unit,
                'category_id' => $item->category_id,
                'ics_no' => $item->ics_no,
                'status' => $item->status,
            ],
            'reason' => $reason,
            'user_id' => $actorId,
        ];
    }

    public function inventoryLogs(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = InventoryItemLog::query()->with('user:id,first_name,last_name');
        $query->whereIn('action', [
            InventoryItemLog::ACTION_UPDATED,
            InventoryItemLog::ACTION_DELETED,
        ])->where(function ($logs): void {
            $logs->whereNull('reason')
                ->orWhere('reason', 'not like', 'Isolated incident #%');
        });

        $perPage = 20;
        $page = max(1, (int) ($validated['page'] ?? 1));
        $total = (clone $query)->count();
        $logs = $query->orderByDesc('id')->forPage($page, $perPage)->get();

        return response()->json([
            'logs' => $logs,
            'has_more' => $total > $page * $perPage,
        ]);
    }

    private function inventoryHasLinkedHistory(array $itemIds): bool    {
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
            $successMessage = count($itemIds) . ' inventory item(s) sent for repair.';
        } else {
            $inventory = Inventory::findOrFail($itemId);
            $error = $this->inventoryOperations->sendToMaintenance(
                $inventory->item_id,
                (int) $request->user()->id,
                $validated['issue_description'],
                $validated['notes'] ?? null,
            );
            $successMessage = 'Item sent for repair.';
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function markRepaired(
        Request $request,
        $itemId,
        IncidentProcessingService $incidentProcessing,
        IncidentLifecycleService $incidentLifecycle,
    )
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'repair_notes' => ['nullable', 'string', 'max:1000'],
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $selectedItemIds = isset($validated['inventory_ids'])
            ? array_map('intval', $validated['inventory_ids'])
            : [(int) $itemId];
        $returnToUserIds = [];

        if (isset($validated['inventory_ids']) && ! in_array((int) $itemId, $selectedItemIds, true)) {
            return redirect()->back()->with('error', 'The selected inventory records do not match the requested action.');
        }

        foreach ($selectedItemIds as $selectedItemId) {
            $incident = $incidentProcessing->linkedReportForProcessingInventory($selectedItemId);
            if ($incident) {
                if ($incident->repair_return_destination === 'assigned_user') {
                    $returnToUserIds[$selectedItemId] = (int) $incident->reported_by;
                }
                if (blank($validated['repair_notes'] ?? null)) {
                    $incidentProcessing->recordBlockedAction(
                        $incident,
                        (int) $request->user()->id,
                        'Repair completion requires a recorded repair result.',
                        'repair_completion',
                    );
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'repair_notes' => 'Record the repair result before completing this incident repair.',
                    ]);
                }
            }
        }

        $successMessage = isset($validated['inventory_ids'])
            ? count($selectedItemIds) . ' item(s) marked as repaired and returned to inventory.'
            : 'Item marked as repaired and returned to inventory.';
        $error = DB::transaction(function () use (
            $validated,
            $selectedItemIds,
            $itemId,
            $request,
            $incidentProcessing,
            $incidentLifecycle,
            $returnToUserIds,
        ): ?string {
            if (isset($validated['inventory_ids'])) {
                $operationError = $this->inventoryOperations->markItemsRepaired(
                    $selectedItemIds,
                    (int) $request->user()->id,
                    $validated['repair_notes'] ?? null,
                    isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
                    $returnToUserIds,
                );
            } else {
                $operationError = $this->inventoryOperations->markRepaired(
                    (int) $itemId,
                    (int) $request->user()->id,
                    $validated['repair_notes'] ?? null,
                    isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
                    $returnToUserIds[(int) $itemId] ?? null,
                );
            }

            if ($operationError !== null) {
                return $operationError;
            }

            foreach ($selectedItemIds as $processingInventoryId) {
                $incident = $incidentProcessing->linkedReportForProcessingInventory($processingInventoryId);
                if ($incident) {
                    $incidentProcessing->resolveRepairedInventory(
                        $incident,
                        $processingInventoryId,
                        (int) $request->user()->id,
                        $validated['repair_notes'] ?? 'Repair completed.',
                        isset($validated['maintenance_cost']) ? (float) $validated['maintenance_cost'] : null,
                        $incidentLifecycle,
                    );
                }
            }

            return null;
        });

        if ($error !== null) {
            foreach ($selectedItemIds as $selectedItemId) {
                $incident = $incidentProcessing->linkedReportForProcessingInventory($selectedItemId);
                if ($incident) {
                    $incidentProcessing->recordBlockedAction(
                        $incident,
                        (int) $request->user()->id,
                        $error,
                        'repair_completion',
                    );
                }
            }

            return redirect()->back()->with('error', $error);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMessage]);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', $successMessage);
    }

    public function markReadyToDispose(Request $request, $itemId, IncidentProcessingService $incidentProcessing)
    {
        $validated = $request->validate([
            'inventory_ids' => ['sometimes', 'array', 'min:1'],
            'inventory_ids.*' => ['required', 'integer', 'distinct', 'exists:inventory,item_id'],
            'notes' => ['required', 'string', 'max:500'],
        ]);
        $selectedItemIds = isset($validated['inventory_ids'])
            ? array_map('intval', $validated['inventory_ids'])
            : [(int) $itemId];

        foreach ($selectedItemIds as $selectedItemId) {
            if ($incidentProcessing->linkedReportForProcessingInventory($selectedItemId)) {
                $message = 'Use the incident review queue to record why these reported units are not repairable.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 422)
                    : redirect()->back()->with('error', $message);
            }
        }

        if (isset($validated['inventory_ids'])) {
            $itemIds = $selectedItemIds;
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

    public function incidentReports()
    {
        $reports = MaintenanceRecord::query()
            ->with([
                'inventory:item_id,item_name,inventory_item_no,unit',
                'reportedBy:id,first_name,last_name,username',
                'evidence:id,maintenance_record_id,original_filename',
                'events.actor:id,first_name,last_name,username',
            ])
            ->whereIn('diagnosis', MaintenanceRecord::INCIDENT_TYPES)
            ->whereIn('incident_status', [
                'reported',
                'under_review',
                'awaiting_reporter',
                'processing_repair',
                'processing_disposal',
                'under_investigation',
            ])
            ->latest('created_at')
            ->get()
            ->map(function (MaintenanceRecord $record): array {
                $inspection = $record->events
                    ->where('event_type', 'physical_inspection_recorded')
                    ->last();
                $handover = $record->events
                    ->where('event_type', 'incident_handover_confirmed')
                    ->last();

                return [
                    'id' => $record->id,
                    'item' => [
                        'id' => $record->inventory?->item_id,
                        'name' => $record->inventory?->item_name ?? 'Unknown item',
                        'inventory_item_no' => $record->inventory?->inventory_item_no,
                        'unit' => $record->inventory?->unit,
                    ],
                    'reporter' => [
                        'id' => $record->reportedBy?->id,
                        'name' => $record->reportedBy
                            ? ($record->reportedBy->full_name ?: $record->reportedBy->username)
                            : 'Unknown reporter',
                    ],
                    'type' => $record->diagnosis,
                    'quantity' => $record->quantity,
                    'details' => $record->issue_description,
                    'status' => $record->incident_status,
                    'submitted_at' => $record->created_at,
                    'inspection' => $inspection ? [
                        'findings' => $inspection->details,
                        'repairability' => $inspection->metadata['repairability'] ?? null,
                        'inspected_at' => $inspection->created_at,
                        'inspector' => $inspection->actor
                            ? ($inspection->actor->full_name ?: $inspection->actor->username)
                            : null,
                    ] : null,
                    'handover' => $handover ? [
                        'confirmed' => true,
                        'details' => $handover->details,
                        'confirmed_at' => $handover->created_at,
                        'processing_inventory_id' => $handover->metadata['processing_inventory_id'] ?? null,
                    ] : null,
                    'evidence' => $record->evidence->map(fn ($file): array => [
                        'id' => $file->id,
                        'filename' => $file->original_filename,
                        'download_url' => route('api.incident-evidence.download', $file->id),
                    ])->values(),
                    'timeline' => $record->events->map(fn ($event): array => [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'from_status' => $event->from_status,
                        'to_status' => $event->to_status,
                        'details' => $event->details,
                        'metadata' => $event->metadata,
                        'actor_id' => $event->actor_id,
                        'actor' => $event->actor
                            ? ($event->actor->full_name ?: $event->actor->username)
                            : null,
                        'created_at' => $event->created_at,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['reports' => $reports]);
    }

    public function startIncidentInvestigation(Request $request, $id, IncidentLifecycleService $incidentLifecycle)
    {
        $record = MaintenanceRecord::query()->findOrFail($id);
        if (! in_array($record->diagnosis, ['lost', 'stolen'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'type' => 'Only Lost or Stolen reports can enter investigation.',
            ]);
        }

        $record = $incidentLifecycle->transition(
            $record,
            'under_investigation',
            (int) $request->user()->id,
            'Lost/Stolen investigation started.',
            ['investigation_started_at' => now()->toISOString()],
        );

        return response()->json([
            'status' => 'success',
            'incident_status' => $record->incident_status,
            'message' => 'Investigation started. Inventory and assignment quantities are unchanged.',
        ]);
    }

    public function recordIncidentInvestigationAction(
        Request $request,
        $id,
        IncidentProcessingService $incidentProcessing,
    ) {
        $validated = $request->validate([
            'details' => ['required', 'string', 'min:3', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $files = $validated['evidence'] ?? [];
        $storedPaths = [];

        try {
            $event = DB::transaction(function () use ($record, $request, $validated, $files, $incidentProcessing, &$storedPaths): MaintenanceRecordEvent {
                $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->id);
                $incidentProcessing->assertActiveInvestigation($record);
                $evidenceIds = [];

                foreach ($files as $file) {
                    $path = $file->store("incident-evidence/{$record->id}", 'local');
                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('Investigation evidence could not be stored.');
                    }
                    $storedPaths[] = $path;

                    $evidence = $record->evidence()->create([
                        'uploaded_by' => $request->user()->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                    $evidenceIds[] = (int) $evidence->id;
                }

                return $incidentProcessing->recordInvestigationAction(
                    $record,
                    (int) $request->user()->id,
                    $validated['details'],
                    $evidenceIds,
                );
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Investigation update and evidence were added to the report history.',
            'event_id' => $event->id,
        ]);
    }

    public function resolveIncidentInvestigation(
        Request $request,
        $id,
        IncidentProcessingService $incidentProcessing,
        IncidentLifecycleService $incidentLifecycle,
    ) {
        $validated = $request->validate([
            'outcome' => ['required', 'in:recovered,confirmed_lost_stolen,unsubstantiated'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $outcome = $validated['outcome'];
        $details = trim($validated['details'] ?? '');

        $metadata = DB::transaction(function () use (
            $record,
            $request,
            $outcome,
            $details,
            $incidentProcessing,
            $incidentLifecycle,
        ): array {
            $record = MaintenanceRecord::query()->lockForUpdate()->findOrFail($record->id);
            $incidentProcessing->assertActiveInvestigation($record);
            $metadata = [
                'outcome' => $outcome,
                'affected_quantity' => (int) $record->quantity,
                'assignment_transaction_id' => $record->assignment_transaction_id,
            ];

            if ($outcome === 'confirmed_lost_stolen') {
                $loss = $this->inventoryOperations->recordIncidentLoss(
                    (int) $record->inventory_id,
                    (int) $record->reported_by,
                    $record->assignment_transaction_id ? (int) $record->assignment_transaction_id : null,
                    (int) $record->quantity,
                    (int) $request->user()->id,
                    (int) $record->id,
                    (string) $record->diagnosis,
                    $details !== '' ? $details : 'Confirmed missing during investigation.',
                );
                if (is_string($loss)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['outcome' => $loss]);
                }
                $metadata['inventory_effect'] = $loss;
            } else {
                $metadata['inventory_effect'] = $outcome === 'recovered'
                    ? 'Affected quantity remains assigned to the reporter.'
                    : 'Inventory and assignment quantities were not changed.';
            }

            $resolutionDetails = $details !== ''
                ? $details
                : match ($outcome) {
                    'recovered' => 'Affected units were confirmed recovered and remain assigned to the reporter.',
                    'confirmed_lost_stolen' => 'Affected units were confirmed lost or stolen.',
                    default => 'The report was unsubstantiated; inventory and assignment were unchanged.',
                };

            $incidentLifecycle->transition(
                $record,
                'resolved',
                (int) $request->user()->id,
                $resolutionDetails,
                $metadata,
            );

            return $metadata;
        });

        return response()->json([
            'status' => 'success',
            'incident_status' => 'resolved',
            'incident_outcome' => $outcome,
            'inventory_effect' => $metadata['inventory_effect'],
            'message' => 'Investigation closed with the confirmed outcome.',
        ]);
    }

    public function reviewIncidentReport(Request $request, $id, IncidentLifecycleService $incidentLifecycle)
    {
        $record = MaintenanceRecord::query()->findOrFail($id);

        if ($record->isIncidentReport() && $record->incident_status === 'under_review') {
            return response()->json([
                'status' => 'success',
                'message' => 'Incident is already accepted for custodian review.',
            ]);
        }

        $incidentLifecycle->transition(
            $record,
            'under_review',
            (int) $request->user()->id,
        );

        return response()->json(['status' => 'success', 'message' => 'Incident accepted for custodian review.']);
    }

    public function requestIncidentInformation(Request $request, $id, IncidentLifecycleService $incidentLifecycle)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $incidentLifecycle->transition(
            $record,
            'awaiting_reporter',
            (int) $request->user()->id,
            $validated['reason'],
        );

        return response()->json(['status' => 'success', 'message' => 'More information requested from the reporter.']);
    }

    public function recordIncidentInspection(Request $request, $id, IncidentProcessingService $incidentProcessing)
    {
        $validated = $request->validate([
            'findings' => ['required', 'string', 'min:3', 'max:2000'],
            'repairability' => ['required', 'in:repairable,not_repairable,pending'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $event = $incidentProcessing->recordInspection(
            $record,
            (int) $request->user()->id,
            $validated['findings'],
            $validated['repairability'],
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Physical inspection recorded. Inventory and assignment quantities are unchanged.',
            'inspection' => [
                'findings' => $event->details,
                'repairability' => $event->metadata['repairability'],
            ],
        ]);
    }

    public function confirmIncidentHandover(
        Request $request,
        $id,
        IncidentProcessingService $incidentProcessing,
        IncidentLifecycleService $incidentLifecycle,
    ) {
        $validated = $request->validate([
            'details' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);

        try {
            $result = DB::transaction(function () use ($record, $request, $validated, $incidentProcessing, $incidentLifecycle): array {
            $handover = $incidentProcessing->confirmHandover(
                $record,
                (int) $request->user()->id,
                $validated['details'],
                $this->inventoryOperations,
            );
            $inspection = $incidentProcessing->latestInspection($record);
            if (! $inspection) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'inspection' => 'Record a complete physical inspection before confirming handover.',
                ]);
            }
            $repairability = $inspection?->metadata['repairability'] ?? null;
            $message = 'Handover recorded. Only the reported quantity has been isolated.';

            if ($repairability === 'not_repairable') {
                $notes = trim(($inspection?->details ?? '')."\n".$validated['details']);
                $error = $this->inventoryOperations->markIncidentReadyToDispose(
                    (int) $handover['inventory']->item_id,
                    (int) $record->quantity,
                    (int) $request->user()->id,
                    (int) $record->id,
                    $notes,
                );
                if ($error !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['handover' => $error]);
                }

                $incidentLifecycle->transition(
                    $record,
                    'processing_disposal',
                    (int) $request->user()->id,
                    'Inspection confirmed the affected units are not repairable; units are ready for disposal review.',
                    [
                        'inspection_findings' => $inspection?->details,
                        'repairability' => 'not_repairable',
                        'handover_confirmed' => true,
                        'processing_inventory_id' => (int) $handover['inventory']->item_id,
                    ],
                );
                $message = 'Affected units are now ready for the existing disposal process.';
            } else {
                $error = $this->startIncidentRepairProcessing(
                    $record,
                    $inspection,
                    (int) $handover['inventory']->item_id,
                    (int) $request->user()->id,
                    $inspection->details ?? $record->issue_description ?? 'Incident repair',
                    $incidentLifecycle,
                );
                if ($error !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['repair' => $error]);
                }

                $message = 'Affected units have been handed over and are now under repair.';
            }

            return [
                'message' => $message,
                'incident_status' => $repairability === 'not_repairable' ? 'processing_disposal' : 'processing_repair',
                'processing_inventory_id' => (int) $handover['inventory']->item_id,
            ];
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            if ($record->isIncidentReport() && $record->incident_status === 'under_review') {
                $message = collect($exception->errors())->flatten()->first();
                $incidentProcessing->recordBlockedAction(
                    $record,
                    (int) $request->user()->id,
                    (string) ($message ?? 'Handover could not be completed.'),
                    'handover',
                );
            }

            throw $exception;
        }

        return response()->json(['status' => 'success'] + $result);
    }

    public function startIncidentRepair(
        Request $request,
        $id,
        IncidentProcessingService $incidentProcessing,
        IncidentLifecycleService $incidentLifecycle,
    ) {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $inspection = $incidentProcessing->latestInspection($record);
        $handover = $incidentProcessing->latestHandover($record);

        if (
            $record->incident_status !== 'under_review'
            || ! $inspection
            || ($inspection->metadata['repairability'] ?? null) !== 'repairable'
            || ! $handover
        ) {
            if ($record->isIncidentReport() && $record->incident_status === 'under_review') {
                $incidentProcessing->recordBlockedAction(
                    $record,
                    (int) $request->user()->id,
                    'Repair cannot start until inspection confirms repairability and affected units are handed over.',
                    'repair_start',
                );
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                'inspection' => 'A repairable inspection and confirmed handover are required before starting repair.',
            ]);
        }

        $processingInventoryId = (int) ($handover->metadata['processing_inventory_id'] ?? 0);
        $issue = $validated['notes'] ?? $inspection->details;
        $error = $this->startIncidentRepairProcessing(
            $record,
            $inspection,
            $processingInventoryId,
            (int) $request->user()->id,
            $issue,
            $incidentLifecycle,
        );
        if ($error !== null) {
            $incidentProcessing->recordBlockedAction(
                $record,
                (int) $request->user()->id,
                $error,
                'repair_start',
            );
            throw \Illuminate\Validation\ValidationException::withMessages(['repair' => $error]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Repair started for the isolated affected units.',
        ]);
    }

    private function startIncidentRepairProcessing(
        MaintenanceRecord $record,
        MaintenanceRecordEvent $inspection,
        int $processingInventoryId,
        int $actorId,
        string $issue,
        IncidentLifecycleService $incidentLifecycle,
    ): ?string {
        return DB::transaction(function () use (
            $record,
            $inspection,
            $processingInventoryId,
            $actorId,
            $issue,
            $incidentLifecycle,
        ): ?string {
            $error = $this->inventoryOperations->sendToMaintenance(
                $processingInventoryId,
                $actorId,
                $record->issue_description ?? 'Incident repair',
                $issue,
            );
            if ($error !== null) {
                return $error;
            }

            $repairRecord = MaintenanceRecord::query()
                ->where('inventory_id', $processingInventoryId)
                ->whereNull('diagnosis')
                ->where('reported_by', $actorId)
                ->latest('id')
                ->firstOrFail();

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $actorId,
                'event_type' => 'incident_repair_started',
                'from_status' => 'under_review',
                'to_status' => 'processing_repair',
                'details' => $issue,
                'metadata' => [
                    'processing_inventory_id' => $processingInventoryId,
                    'maintenance_record_id' => (int) $repairRecord->id,
                ],
            ]);

            $incidentLifecycle->transition(
                $record,
                'processing_repair',
                $actorId,
                'Repair started after physical inspection and handover.',
                [
                    'inspection_findings' => $inspection->details,
                    'repairability' => 'repairable',
                    'handover_confirmed' => true,
                    'processing_inventory_id' => $processingInventoryId,
                    'maintenance_record_id' => (int) $repairRecord->id,
                ],
            );

            return null;
        });
    }

    public function confirmIncidentRepairNotViable(
        Request $request,
        $id,
        IncidentProcessingService $incidentProcessing,
        IncidentLifecycleService $incidentLifecycle,
    ) {
        $validated = $request->validate([
            'findings' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        $record = MaintenanceRecord::query()->findOrFail($id);
        $handover = $incidentProcessing->latestHandover($record);
        if ($record->incident_status !== 'processing_repair' || ! $handover) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'This report must have an active repair and confirmed handover.',
            ]);
        }

        $processingInventoryId = (int) ($handover->metadata['processing_inventory_id'] ?? 0);
        $error = DB::transaction(function () use (
            $record,
            $request,
            $validated,
            $processingInventoryId,
            $incidentLifecycle,
        ): ?string {
            $error = $this->inventoryOperations->markReadyToDispose(
                $processingInventoryId,
                (int) $request->user()->id,
                $validated['findings'],
            );
            if ($error !== null) {
                return $error;
            }

            MaintenanceRecordEvent::create([
                'maintenance_record_id' => $record->id,
                'actor_id' => $request->user()->id,
                'event_type' => 'incident_repairability_reassessed',
                'from_status' => 'processing_repair',
                'to_status' => 'processing_disposal',
                'details' => $validated['findings'],
                'metadata' => [
                    'repairability' => 'not_repairable',
                    'handover_confirmed' => true,
                    'processing_inventory_id' => $processingInventoryId,
                    'affected_quantity' => (int) $record->quantity,
                ],
            ]);

            $incidentLifecycle->transition(
                $record,
                'processing_disposal',
                (int) $request->user()->id,
                'Repair could not proceed; affected units are ready for disposal review.',
                [
                    'inspection_findings' => $validated['findings'],
                    'repairability' => 'not_repairable',
                    'handover_confirmed' => true,
                    'processing_inventory_id' => $processingInventoryId,
                ],
            );

            return null;
        });

        if ($error !== null) {
            $incidentProcessing->recordBlockedAction(
                $record,
                (int) $request->user()->id,
                $error,
                'repairability_reassessment',
            );
            throw \Illuminate\Validation\ValidationException::withMessages(['findings' => $error]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Affected units are now ready for the existing disposal process.',
        ]);
    }

    /**
     * Triage closer for an end-user incident report. Repair-pipeline records
     * without an incident type continue to close through repair actions.
     */
    public function declineMaintenanceReport(Request $request, $id, IncidentLifecycleService $incidentLifecycle)
    {
        $record = MaintenanceRecord::whereKey($id)->firstOrFail();

        if (! in_array($record->status, ['reported', 'in_progress'], true) || ! $record->isIncidentReport()) {
            return redirect()->back()->with('error', 'This report has already been processed.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $incidentLifecycle->transition(
            $record,
            'declined',
            (int) $request->user()->id,
            $validated['reason'],
        );

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => 'Issue report declined.']);
        }

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Issue report declined.');
    }

    public function disposeInventory(Request $request, $itemId, IncidentProcessingService $incidentProcessing)
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
            $successMessage = count($itemIds) . ' inventory item(s) disposed successfully.';
        } else {
            $itemIds = [(int) $itemId];
            $successMessage = 'Item disposed successfully.';
        }

        $error = DB::transaction(function () use ($validated, $itemIds, $request, $incidentProcessing, $itemId): ?string {
            if (isset($validated['inventory_ids'])) {
                $operationError = $this->inventoryOperations->disposeItems(
                    $itemIds,
                    (int) $request->user()->id,
                    $validated['notes'] ?? null,
                );
            } else {
                $operationError = $this->inventoryOperations->dispose(
                    (int) $itemId,
                    (int) $request->user()->id,
                    $validated['notes'] ?? null,
                );
            }
            if ($operationError !== null) {
                return $operationError;
            }

            $incidentProcessing->resolveDisposedInventory(
                $itemIds,
                (int) $request->user()->id,
                $validated['notes'] ?? null,
            );

            return null;
        });

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $successMessage]);
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
