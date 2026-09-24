@extends('layouts.app')

@section('content')
    <div class="space-y-5 pb-6">
        <header class="flex flex-col gap-4 border-b border-gray-200 pb-5 dark:border-gray-800 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <x-common.page-breadcrumb pageTitle="Property Custodian Dashboard" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Overview of inventory health and actions requiring attention.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-2"><i data-lucide="calendar-days" class="h-4 w-4"></i><span>{{ now()->format('F d, Y') }}</span></div>
                <span class="hidden h-4 w-px bg-gray-200 dark:bg-gray-700 sm:block"></span>
                <span>Updated {{ now()->format('h:i A') }}</span>
                <button type="button" onclick="window.location.reload()" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:hover:border-brand-500" aria-label="Refresh dashboard" title="Refresh dashboard">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                </button>
                <div class="relative flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-500 dark:border-gray-700" aria-label="Pending notifications">
                    <i data-lucide="bell" class="h-4 w-4"></i>
                    @if ($metrics['pendingRequests'] > 0)
                        <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $metrics['pendingRequests'] }}</span>
                    @endif
                </div>
                <div class="hidden border-l border-gray-200 pl-3 dark:border-gray-700 sm:block">
                    <p class="font-semibold text-gray-700 dark:text-gray-200">{{ auth()->user()?->full_name ?? 'Property Custodian' }}</p>
                    <p>Property Custodian</p>
                </div>
            </div>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $primaryMetrics = [
                    ['title' => 'Inventory Units', 'value' => $metrics['inventory'], 'subtitle' => 'Total registered inventory', 'icon' => 'package', 'class' => 'text-brand-600 bg-brand-50 dark:bg-brand-500/10 dark:text-brand-400', 'route' => 'propertyCustodian.inventory'],
                    ['title' => 'Available Units', 'value' => $metrics['available'], 'subtitle' => 'Ready for use / unassigned', 'icon' => 'circle-check', 'class' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10 dark:text-emerald-400', 'route' => 'propertyCustodian.inventory'],
                    ['title' => 'Low-Stock Items', 'value' => $metrics['lowStock'], 'subtitle' => 'Below minimum threshold', 'icon' => 'triangle-alert', 'class' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400', 'route' => 'propertyCustodian.inventory'],
                    ['title' => 'Pending Requests', 'value' => $metrics['pendingRequests'], 'subtitle' => 'Awaiting approval or action', 'icon' => 'clipboard-list', 'class' => 'text-red-600 bg-red-50 dark:bg-red-500/10 dark:text-red-400', 'route' => 'propertyCustodian.transactions'],
                ];
            @endphp
            @foreach ($primaryMetrics as $metric)
                <a href="{{ route($metric['route']) }}" class="group rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-brand-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $metric['title'] }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ number_format($metric['value']) }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $metric['subtitle'] }}</p>
                        </div>
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $metric['class'] }}"><i data-lucide="{{ $metric['icon'] }}" class="h-4 w-4"></i></span>
                    </div>
                    <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 opacity-0 transition group-hover:opacity-100 dark:text-brand-400">View details <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i></span>
                </a>
            @endforeach
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Inventory Health</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Lifecycle items that may require planning.</p></div>
                <a href="{{ route('propertyCustodian.inventory') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View details <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="grid divide-y divide-gray-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 dark:divide-gray-800">
                <div class="flex items-center gap-3 px-4 py-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><i data-lucide="clock-3" class="h-4 w-4"></i></span><div><p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['approachingLifespan']) }} <span class="font-normal text-gray-500">End of Life Soon</span></p><p class="text-xs text-gray-500 dark:text-gray-400">Items within the next 12 months</p></div></div>
                <div class="flex items-center gap-3 px-4 py-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400"><i data-lucide="calendar-clock" class="h-4 w-4"></i></span><div><p class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['expiredLifespan']) }} <span class="font-normal text-gray-500">Past End Date</span></p><p class="text-xs text-gray-500 dark:text-gray-400">Items requiring assessment</p></div></div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Needs Your Attention</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Priority work based on current inventory state.</p></div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <a href="{{ route('propertyCustodian.transactions') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800/50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400"><i data-lucide="file-clock" class="h-4 w-4"></i></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Pending assignment requests</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ number_format($metrics['pendingRequests']) }} requests awaiting your review</p></div><span class="hidden text-xs font-semibold text-brand-600 sm:inline dark:text-brand-400">Review requests <span aria-hidden="true">&rarr;</span></span><i data-lucide="chevron-right" class="h-4 w-4 text-gray-400 sm:hidden"></i></a>
                <a href="{{ route('propertyCustodian.inventory') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800/50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><i data-lucide="package-search" class="h-4 w-4"></i></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Low-stock inventory</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ number_format($metrics['lowStock']) }} items are below minimum threshold</p></div><span class="hidden text-xs font-semibold text-brand-600 sm:inline dark:text-brand-400">View low stock <span aria-hidden="true">&rarr;</span></span><i data-lucide="chevron-right" class="h-4 w-4 text-gray-400 sm:hidden"></i></a>
                <a href="{{ route('propertyCustodian.inventory') }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-gray-800/50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400"><i data-lucide="calendar-x-2" class="h-4 w-4"></i></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Items past expected end date</p><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ number_format($metrics['expiredLifespan']) }} items are overdue</p></div><span class="hidden text-xs font-semibold text-brand-600 sm:inline dark:text-brand-400">Assess items <span aria-hidden="true">&rarr;</span></span><i data-lucide="chevron-right" class="h-4 w-4 text-gray-400 sm:hidden"></i></a>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Inventory Overview</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Distribution of active units by category.</p></div><span class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">By Category</span></div>
                @if ($categoryData->isEmpty())
                    <div class="flex min-h-[250px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">No inventory data available.</div>
                @else
                    <div id="custodianCategoryChart" class="min-h-[250px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("value")->values())'></div>
                @endif
            </section>
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Request Workflow</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Current assignment request distribution.</p></div><span class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">By Status</span></div>
                @if ($requestStatusData->isEmpty())
                    <div class="flex min-h-[250px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">No request data available.</div>
                @else
                    <div id="custodianRequestChart" class="min-h-[250px]" data-labels='@json($requestStatusData->pluck("label")->values())' data-values='@json($requestStatusData->pluck("value")->values())'></div>
                @endif
            </section>
        </div>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800"><div><h2 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Inventory Activity</h2><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Latest stock and assignment movements.</p></div><a href="{{ route('propertyCustodian.transactions') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View all activity <span aria-hidden="true">&rarr;</span></a></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400"><tr><th class="px-4 py-2.5 font-semibold">Date &amp; Time</th><th class="px-4 py-2.5 font-semibold">Type</th><th class="px-4 py-2.5 font-semibold">Item</th><th class="px-4 py-2.5 text-right font-semibold">Quantity</th><th class="px-4 py-2.5 font-semibold">User</th><th class="px-4 py-2.5 font-semibold">Remarks</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recentActivity as $activity)
                            @php
                                $badgeClass = match ($activity['type']) {
                                    'Stock In', 'Return' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                    'Stock Out' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
                                    'Assignment' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
                                    'Transfer' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                };
                            @endphp
                            <tr class="whitespace-nowrap text-gray-600 dark:text-gray-300"><td class="px-4 py-3">{{ $activity['date']?->format('M d, Y h:i A') }}</td><td class="px-4 py-3"><span class="inline-flex rounded-md px-2 py-1 text-[11px] font-semibold {{ $badgeClass }}">{{ $activity['type'] }}</span></td><td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $activity['item'] }}</td><td class="px-4 py-3 text-right font-semibold {{ in_array($activity['type'], ['Stock In', 'Return'], true) ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-300' }}">{{ $activity['quantity'] }}</td><td class="px-4 py-3">{{ $activity['user'] }}</td><td class="max-w-xs truncate px-4 py-3" title="{{ $activity['remarks'] }}">{{ $activity['remarks'] }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No recent inventory activity.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
