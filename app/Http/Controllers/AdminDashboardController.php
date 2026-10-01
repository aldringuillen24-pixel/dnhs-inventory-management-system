<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\UserAuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $users = User::query()->with('role')->get();

        $roleData = $users
            ->groupBy(fn ($user) => $user->role?->role_name ?? 'No role assigned')
            ->map(fn ($roleUsers, $role) => ['label' => $role, 'value' => $roleUsers->count()])
            ->values();
        $accountStatusData = $users
            ->groupBy('status')
            ->map(fn ($statusUsers, $status) => ['label' => ucfirst((string) $status), 'value' => $statusUsers->count()])
            ->values();
        $auditData = UserAuditLog::query()
            ->latest()
            ->limit(8)
            ->get()
            ->groupBy('action')
            ->map(fn ($logs, $action) => ['label' => ucwords(str_replace('_', ' ', (string) $action)), 'value' => $logs->count()])
            ->values();

        return view('pages.administrator.dashboard', [
            'title' => 'Admin Dashboard',
            'metrics' => [
                'activeUsers' => $users->where('status', 'active')->count(),
                'pendingOnboarding' => $users->whereNotNull('temporary_password')->count(),
            ],
            'roleData' => $roleData,
            'accountStatusData' => $accountStatusData,
            'auditData' => $auditData,
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
