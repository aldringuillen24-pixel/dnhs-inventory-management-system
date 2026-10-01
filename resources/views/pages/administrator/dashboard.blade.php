@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Administrator Dashboard" />

    @php
        $kpiMetrics = [
                    ['title' => 'Active Users', 'value' => number_format($metrics['activeUsers']), 'subtitle' => 'Currently enabled accounts', 'icon' => 'users', 'iconClass' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400'],
                    ['title' => 'Pending Onboarding', 'value' => number_format($metrics['pendingOnboarding']), 'subtitle' => 'Accounts awaiting setup', 'icon' => 'user-plus', 'iconClass' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400'],
        ];
    @endphp
    <div class="mt-6">
        <x-cards.kpi-summary :metrics="$kpiMetrics" />
    </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-2">
            <x-dashboard.chart-card
                title="User Role Distribution"
                subtitle="Accounts grouped by assigned role"
                chart-id="adminUserRoleChart"
                badge="By Role"
                empty-message="No user role data available."
                :chart-data="['labels' => $roleData->pluck('label')->values(), 'values' => $roleData->pluck('value')->values(), 'colors' => ['#4f46e5', '#0f766e', '#d97706', '#dc2626', '#64748b']]"
            >
                <x-slot:actions>
                    <a href="{{ route('admin.users-management') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View users</a>
                </x-slot:actions>
            </x-dashboard.chart-card>

            <x-dashboard.chart-card
                title="Account Status Distribution"
                subtitle="Current account availability"
                chart-id="adminAccountStatusChart"
                badge="By Status"
                empty-message="No account status data available."
                :chart-data="['labels' => $accountStatusData->pluck('label')->values(), 'values' => $accountStatusData->pluck('value')->values(), 'colors' => ['#16a34a', '#64748b']]"
            />

            <div class="xl:col-span-2">
                <x-dashboard.chart-card
                    title="Recent Administrative Activity"
                    subtitle="Latest recorded administrative actions"
                    chart-id="adminAuditActivityChart"
                    badge="Latest Activity"
                    empty-message="No administrative activity available."
                    :chart-data="['labels' => $auditData->pluck('label')->values(), 'values' => $auditData->pluck('value')->values(), 'colors' => ['#4f46e5', '#0f766e', '#d97706', '#dc2626', '#64748b']]"
                />
            </div>
        </div>

@endsection
