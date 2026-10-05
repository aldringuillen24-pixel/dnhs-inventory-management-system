<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAuditLog;
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
            ['label' => 'Under Repair or Maintenance', 'value' => (int) $inventory->whereIn('status', ['under_repair', 'under_maintenance'])->sum('quantity')],
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
            ])
            ->get(['status', 'target_user_id']);
        $pendingRequestData = [
            [
                'label' => 'Item Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for approval' && $custodianIds->contains($request->target_user_id))->count(),
            ],
            [
                'label' => 'Transfer Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for transfer approval' || ($request->status === 'waiting for approval' && ! $custodianIds->contains($request->target_user_id)))->count(),
            ],
            [
                'label' => 'Return Requests',
                'value' => $pendingRequests->where('status', 'waiting for custodian approval')->count(),
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
                'attentionItems' => (int) $inventory->whereIn('status', ['under_inspection', 'under_maintenance', 'ready_to_dispose'])->sum('quantity'),
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
        $effective = fn ($item): int => in_array($item->status, ['assigned', 'under_inspection'], true)
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
            'metrics' => [
                'units' => (int) $inventory->sum($effective),
                'available' => (int) $inventory->where('status', 'available')->sum($effective),
                'assigned' => (int) $inventory->where('status', 'assigned')->sum($effective),
                'lowStock' => $categories->where('available', '<=', 3)->count(),
                'value' => (float) $inventory->sum(fn ($item) => $effective($item) * $item->unit_cost),
            ],
        ]);
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
            ->paginate(20);

        return response()->json([
            'title' => 'Audit Logs',
            'logs' => $logs,
        ]);
    }

    public function updateProfile(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail(Auth::id());
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        return redirect()->route('schoolHead.profile')->with('success', 'Profile updated successfully.');
    }
}