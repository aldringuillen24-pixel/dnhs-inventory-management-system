@extends('layouts.app', ['title' => 'Reports' ])

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory Reports" />

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Operational inventory report</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor stock position, movement, lifecycle risk, and items requiring action.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="date_from" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                <input id="date_from" name="date_from" type="date" value="{{ $reportFilters['date_from'] }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                <input id="date_to" name="date_to" type="date" value="{{ $reportFilters['date_to'] }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="filter" class="h-4 w-4"></i>Apply</button>
            <a href="{{ route('propertyCustodian.reports') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"><i data-lucide="rotate-ccw" class="h-4 w-4"></i>Reset</a>
        </form>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <x-cards.metric-card title="Total Units" value="{{ number_format($metrics['totalUnits']) }}" subtitle="Active inventory" />
        <x-cards.metric-card title="Available" value="{{ number_format($metrics['availableUnits']) }}" subtitle="Ready for assignment" />
        <x-cards.metric-card title="Assigned" value="{{ number_format($metrics['assignedUnits']) }}" subtitle="Currently in use" />
        <x-cards.metric-card title="Inventory Value" value="PHP {{ number_format($metrics['totalValue'], 2) }}" subtitle="Recorded active value" />
        <x-cards.metric-card title="Needs Attention" value="{{ number_format($metrics['attentionUnits']) }}" subtitle="Maintenance or disposal" />
        <x-cards.metric-card title="Low Stock" value="{{ number_format($metrics['lowStockGroups']) }}" subtitle="Groups at 3 units or less" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Stock status" subtitle="Current units by lifecycle state">
            @if ($statusData->isNotEmpty())
                <div id="custodianReportStatusChart" class="min-h-[300px]" data-labels='@json($statusData->pluck("label")->values())' data-values='@json($statusData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No status data available.</div>
            @endif
        </x-cards.base-card>

        <x-cards.base-card title="Units by category" subtitle="Largest active stock groups">
            @if ($categoryData->isNotEmpty())
                <div id="custodianReportCategoryChart" class="min-h-[300px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No category data available.</div>
            @endif
        </x-cards.base-card>
    </div>

    <div class="mt-6">
        <x-cards.base-card title="Stock movement trend" subtitle="Movement activity for the selected date range">
            @if ($movementData->isNotEmpty())
                <div id="custodianReportMovementChart" class="min-h-[300px]" data-labels='@json($movementData->pluck("label")->values())' data-stock-in='@json($movementData->pluck("stock_in")->values())' data-stock-out='@json($movementData->pluck("stock_out")->values())' data-disposals='@json($movementData->pluck("disposals")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No movement data available.</div>
            @endif
        </x-cards.base-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Lifecycle attention" subtitle="Units approaching or beyond expected useful life">
            @if ($lifecycleData->sum('quantity') > 0)
                <div id="custodianReportLifecycleChart" class="min-h-[300px]" data-labels='@json($lifecycleData->pluck("label")->values())' data-values='@json($lifecycleData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No lifespan dates recorded.</div>
            @endif
        </x-cards.base-card>

        <x-cards.base-card title="Report highlights" subtitle="Items that may need follow-up">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Approaching end of life</p><p class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-200">{{ number_format($metrics['approachingLifespan']) }}</p><p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Units within the next 12 months</p></div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10"><p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Past expected end date</p><p class="mt-2 text-2xl font-semibold text-red-900 dark:text-red-200">{{ number_format($metrics['expiredLifespan']) }}</p><p class="mt-1 text-xs text-red-700 dark:text-red-300">Units requiring assessment</p></div>
                <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50 sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disposed units</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['disposedUnits']) }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Excluded from active inventory value and availability</p></div>
            </div>
        </x-cards.base-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Low-stock watchlist" subtitle="Available groups with three units or fewer">
            <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Category</th><th class="pb-3 text-right font-medium">Available</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($lowStockData as $item)
                    <tr><td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item['item_name'] }}</td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $item['category'] }}</td><td class="py-3 text-right font-semibold text-amber-600 dark:text-amber-400">{{ number_format($item['quantity']) }} {{ $item['unit'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-gray-500">No low-stock groups found.</td></tr>
                @endforelse
            </tbody></table></div>
        </x-cards.base-card>

        <x-cards.base-card title="Attention queue" subtitle="Maintenance, inspection, and disposal candidates">
            <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Status</th><th class="pb-3 text-right font-medium">Qty</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($attentionData as $item)
                    <tr><td class="py-3"><p class="font-medium text-gray-800 dark:text-gray-200">{{ $item['item_name'] }}</p><span class="text-xs text-gray-400">{{ $item['inventory_item_no'] ?: 'No item number' }}</span></td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $item['status'] }}</td><td class="py-3 text-right font-semibold text-gray-700 dark:text-gray-300">{{ number_format($item['quantity']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-gray-500">No attention items found.</td></tr>
                @endforelse
            </tbody></table></div>
        </x-cards.base-card>
    </div>

    <div class="mt-6"><x-cards.base-card title="Recent inventory activity" subtitle="Transactions in the selected date range"><div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Inventory no.</th><th class="pb-3 font-medium">User</th><th class="pb-3 text-right font-medium">Qty</th><th class="pb-3 text-right font-medium">Date</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($recentTransactions as $transaction)
            <tr><td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $transaction->item?->item_name ?? 'Unknown item' }}</td><td class="py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $transaction->item?->inventory_item_no ?? 'N/A' }}</td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $transaction->user?->full_name ?? 'Unknown user' }}</td><td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($transaction->quantity) }}</td><td class="py-3 text-right text-gray-500 dark:text-gray-400">{{ $transaction->transaction_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
        @empty
            <tr><td colspan="5" class="py-8 text-center text-gray-500">No transactions recorded in this date range.</td></tr>
        @endforelse
    </tbody></table></div></x-cards.base-card></div>
@endsection
