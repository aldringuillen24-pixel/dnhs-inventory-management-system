@extends('layouts.app')

@section('content')
    <div class="space-y-5 pb-6">
        <x-dashboard.dashboard-header
            title="Property Custodian Dashboard"
            description="Overview of inventory health and actions requiring attention."
            breadcrumb-title="Property Custodian Dashboard"
            :updated-at="'Updated ' . now()->format('h:i A')"
            :user-name="auth()->user()?->full_name ?? 'Property Custodian'"
            role-label="Property Custodian"
            :show-refresh="true"
            :show-notifications="true"
            :notification-count="$metrics['pendingRequests']"
        />

        @php
            $primaryMetrics = [
                ['title' => 'Inventory Units', 'value' => number_format($metrics['inventory']), 'subtitle' => 'Total registered inventory', 'icon' => 'package', 'iconClass' => 'text-brand-600 bg-brand-50 dark:bg-brand-500/10 dark:text-brand-400', 'route' => 'propertyCustodian.inventory'],
                ['title' => 'Available Units', 'value' => number_format($metrics['available']), 'subtitle' => 'Ready for use / unassigned', 'icon' => 'circle-check', 'iconClass' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400', 'route' => 'propertyCustodian.inventory'],
                ['title' => 'Low-Stock Items', 'value' => number_format($metrics['lowStock']), 'subtitle' => 'Below minimum threshold', 'icon' => 'triangle-alert', 'iconClass' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'propertyCustodian.inventory'],
                ['title' => 'Pending Requests', 'value' => number_format($metrics['pendingRequests']), 'subtitle' => 'Awaiting approval or action', 'icon' => 'clipboard-list', 'iconClass' => 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400', 'route' => 'propertyCustodian.transactions'],
            ];
        @endphp
        <x-cards.kpi-summary :metrics="$primaryMetrics" />

            @php
                $healthItems = [
                    ['value' => number_format($metrics['approachingLifespan']), 'title' => 'End of Life Soon', 'subtitle' => 'Items within the next 12 months', 'icon' => 'clock-3', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'propertyCustodian.inventory'],
                    ['value' => number_format($metrics['expiredLifespan']), 'title' => 'Past End Date', 'subtitle' => 'Items requiring assessment', 'icon' => 'calendar-clock', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'route' => 'propertyCustodian.inventory'],
                ];
            @endphp
            <x-dashboard.inventory-health
                title="Inventory Health"
                subtitle="Lifecycle items that may require planning."
                action-label="View details"
                action-route="propertyCustodian.inventory"
                :items="$healthItems"
            />

            @php
                $attentionItems = [
                    ['title' => 'Pending assignment requests', 'subtitle' => number_format($metrics['pendingRequests']) . ' requests awaiting your review', 'icon' => 'file-clock', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'route' => 'propertyCustodian.transactions'],
                    ['title' => 'Low-stock inventory', 'subtitle' => number_format($metrics['lowStock']) . ' items are below minimum threshold', 'icon' => 'package-search', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'propertyCustodian.inventory'],
                    ['title' => 'Items past expected end date', 'subtitle' => number_format($metrics['expiredLifespan']) . ' items are overdue', 'icon' => 'calendar-x-2', 'iconClass' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'route' => 'propertyCustodian.inventory'],
                ];
            @endphp
            <x-dashboard.inventory-health
                title="Needs Your Attention"
                subtitle="Priority work based on current inventory state."
                :items="$attentionItems"
            />

        <div class="grid gap-5 xl:grid-cols-2">
            <x-dashboard.chart-card
                title="Inventory Overview"
                subtitle="Distribution of active units by category."
                chart-id="custodianCategoryChart"
                badge="By Category"
                empty-message="No inventory data available."
                :chart-data="['labels' => $categoryData->pluck('label')->values(), 'values' => $categoryData->pluck('value')->values()]"
            />
            <x-dashboard.chart-card
                title="Request Workflow"
                subtitle="Current assignment request distribution."
                chart-id="custodianRequestChart"
                badge="By Status"
                empty-message="No request data available."
                :chart-data="['labels' => $requestStatusData->pluck('label')->values(), 'values' => $requestStatusData->pluck('value')->values()]"
            />
        </div>

        <x-dashboard.recent-activity-table
            title="Recent Inventory Activity"
            subtitle="Latest stock and assignment movements."
            :activities="$recentActivity"
            empty-message="No recent inventory activity."
        >
            <x-slot:actions>
                <a href="{{ route('propertyCustodian.transactions') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View all activity <span aria-hidden="true">&rarr;</span></a>
            </x-slot:actions>
        </x-dashboard.recent-activity-table>
    </div>
@endsection
