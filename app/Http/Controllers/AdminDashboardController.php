<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
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

        $recentTransactions = Transaction::query()
            ->with(['item:item_id,item_name', 'user:id,first_name,last_name'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('pages.administrator.dashboard', [
            'title' => 'Admin Dashboard',
            'metrics' => [
                'inventory' => Inventory::where('status', '!=', 'disposed')->sum('quantity'),
                'available' => Inventory::where('status', 'available')->sum('quantity'),
                'transactions' => Transaction::whereDate('transaction_date', today())->count(),
                'users' => User::where('status', 'active')->count(),
            ],
            'categoryData' => $categoryData,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function reports(): View
    {
        return view('pages.administrator.reports', $this->reportData());
    }

    public function downloadReports()
    {
        return Pdf::loadView('pdfs.administrator-system-report', $this->reportData())
            ->download('administrator-system-report.pdf');
    }

    private function reportData(): array
    {
        $roleData = User::query()
            ->with('role')
            ->get()
            ->groupBy(fn ($user) => $user->role?->role_name ?? 'No role assigned')
            ->map(fn ($users, $role) => ['label' => $role, 'value' => $users->count()])
            ->values();

        $requestStatusData = AssignmentRequest::query()
            ->get()
            ->groupBy('status')
            ->map(fn ($requests, $status) => [
                'label' => ucfirst(str_replace('_', ' ', (string) $status)),
                'value' => $requests->count(),
            ])
            ->values();

        $maintenanceStatusData = MaintenanceRecord::query()
            ->get()
            ->groupBy('status')
            ->map(fn ($records, $status) => [
                'label' => ucfirst(str_replace('_', ' ', (string) $status)),
                'value' => $records->count(),
            ])
            ->values();

        return [
            'title' => 'System Reports',
            'metrics' => [
                'activeUsers' => User::where('status', 'active')->count(),
                'pendingOnboarding' => User::whereNotNull('temporary_password')->count(),
                'pendingRequests' => AssignmentRequest::whereIn('status', ['waiting for approval', 'waiting for transfer approval'])->count(),
                'openMaintenance' => MaintenanceRecord::whereNotIn('status', ['completed', 'cancelled'])->count(),
            ],
            'roleData' => $roleData,
            'requestStatusData' => $requestStatusData,
            'maintenanceStatusData' => $maintenanceStatusData,
            'recentActivity' => UserAuditLog::query()
                ->latest()
                ->limit(8)
                ->get(),
            ];
    }
}
