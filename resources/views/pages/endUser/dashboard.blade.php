@extends('layouts.app')

@section('content')
    <div class="space-y-5 pb-6">
                @php
                    $kpiMetrics = [
                        ['title' => 'My Assigned Items', 'value' => number_format($metrics['assignedItems']), 'subtitle' => 'Items currently assigned to you', 'icon' => 'package-check', 'iconClass' => 'text-brand-600 bg-brand-50 dark:bg-brand-500/10 dark:text-brand-400', 'accentClass' => 'border-l-brand-500', 'route' => 'endUser.my-assigned-items'],
                        ['title' => 'Pending Requests', 'value' => number_format($metrics['pendingRequests']), 'subtitle' => 'Requests awaiting custodian review', 'icon' => 'clipboard-clock', 'iconClass' => 'text-sky-600 bg-sky-50 dark:bg-sky-500/10 dark:text-sky-400', 'accentClass' => 'border-l-sky-500', 'route' => 'endUser.requests'],
                        ['title' => 'Pending Returns', 'value' => number_format($metrics['pendingReturns']), 'subtitle' => 'Return requests awaiting action', 'icon' => 'undo-2', 'iconClass' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400', 'accentClass' => 'border-l-amber-500', 'route' => 'endUser.requests'],
                        ['title' => 'Items Due Soon', 'value' => number_format($metrics['dueSoon']), 'subtitle' => 'Assigned items nearing their expected return date', 'icon' => 'calendar-clock', 'iconClass' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 dark:text-indigo-400', 'accentClass' => 'border-l-indigo-500', 'route' => 'endUser.my-assigned-items'],
                        ['title' => 'Items Requiring Attention', 'value' => number_format($metrics['attentionItems']), 'subtitle' => 'Damaged, under maintenance, or unavailable assigned items', 'icon' => 'triangle-alert', 'iconClass' => 'text-rose-600 bg-rose-50 dark:bg-rose-500/10 dark:text-rose-400', 'accentClass' => 'border-l-rose-500', 'route' => 'endUser.my-assigned-items'],
                    ];
                @endphp

                <x-common.page-breadcrumb pageTitle="My Dashboard" />
                <x-cards.kpi-summary :metrics="$kpiMetrics" />

                <div class="grid gap-5 xl:grid-cols-2">
                    <x-dashboard.chart-card
                        title="My Items by Category"
                        subtitle="Assigned items grouped by category"
                        chart-id="endUserCategoryChart"
                        badge="By Category"
                        empty-message="No assigned items by category."
                        :chart-data="['labels' => $categoryData->pluck('label')->values(), 'values' => $categoryData->pluck('quantity')->values(), 'colors' => ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#64748b']]"
                    >
                        <x-slot:actions>
                            <a href="{{ route('endUser.my-assigned-items') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View items</a>
                        </x-slot:actions>
                    </x-dashboard.chart-card>

                    <x-dashboard.chart-card
                        title="My Assigned Item Status"
                        subtitle="Current status of your assigned items"
                        chart-id="endUserStatusChart"
                        badge="By Status"
                        empty-message="No assigned item status data available."
                        :chart-data="['labels' => $statusData->pluck('label')->values(), 'values' => $statusData->pluck('quantity')->values(), 'colors' => ['#0f766e', '#d97706', '#dc2626', '#475569']]"
                    />
                </div>

                <div class="grid gap-5 xl:grid-cols-2">
                    <x-dashboard.pending-request-chart
                        title="My Request Overview"
                        subtitle="Your requests awaiting action by workflow type"
                        chart-id="endUserPendingRequestChart"
                        details-route="endUser.requests"
                        details-label="View requests"
                        empty-message="No pending requests or returns."
                        :request-data="$pendingRequestData"
                    />

                    <x-dashboard.inventory-condition-chart
                        title="My Item Condition Summary"
                        subtitle="Your assigned items requiring attention"
                        chart-id="endUserConditionChart"
                        details-route="endUser.my-assigned-items"
                        details-label="View items"
                        empty-message="No assigned condition records require attention."
                        :labels="$conditionData->pluck('label')->values()"
                        :values="$conditionData->pluck('value')->values()"
                    />
                </div>

                <div class="grid gap-6 xl:grid-cols-12">
                    <div class="xl:col-span-8">
                        <x-cards.base-card title="Recent Activity" subtitle="Your latest requests and assignments">
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[520px] text-left text-sm">
                                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                                        <tr>
                                            <th class="pb-3 font-medium">Item</th>
                                            <th class="pb-3 font-medium">Type</th>
                                            <th class="pb-3 font-medium">Status</th>
                                            <th class="pb-3 text-right font-medium">Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse ($recentActivity as $activity)
                                            <tr>
                                                <td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $activity->item?->item_name ?? 'Unknown item' }}</td>
                                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $activity->target_user_id === auth()->id() ? 'Assignment' : 'Request' }}</td>
                                                <td class="py-3"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ ucfirst($activity->status) }}</span></td>
                                                <td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($activity->quantity) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="py-8 text-center text-gray-500 dark:text-gray-400">No activity recorded yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </x-cards.base-card>
                    </div>

                    <div class="xl:col-span-4">
                        <x-cards.base-card title="Quick Links" subtitle="Your inventory and request pages">
                            <div class="space-y-3">
                                <a href="{{ route('endUser.requests') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                                    <span>View my requests</span><span aria-hidden="true">&rarr;</span>
                                </a>
                                <a href="{{ route('endUser.my-assigned-items') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                                    <span>View assigned items</span><span aria-hidden="true">&rarr;</span>
                                </a>
                            </div>
                        </x-cards.base-card>
                    </div>
                </div>
            </div>
    </div>
@endsection
