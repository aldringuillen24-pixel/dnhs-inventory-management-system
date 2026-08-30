@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reports" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card title="Total Units" value="{{ number_format($metrics['totalUnits']) }}" subtitle="Non-disposed inventory" />
        <x-cards.metric-card title="Available Units" value="{{ number_format($metrics['availableUnits']) }}" subtitle="Ready for assignment" />
        <x-cards.metric-card title="Assigned Units" value="{{ number_format($metrics['assignedUnits']) }}" subtitle="Currently in use" />
        <x-cards.metric-card title="Inventory Value" value="PHP {{ number_format($metrics['totalValue'], 2) }}" subtitle="Recorded asset value" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Units by Category" subtitle="Inventory distribution">
            <div id="schoolHeadCategoryChart" class="min-h-[280px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("quantity")->values())'></div>
            <div class="space-y-3">
                @forelse ($categoryData as $category)
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 text-sm last:border-0 dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-300">{{ $category['label'] }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($category['quantity']) }}</span>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500">No category data available.</p>
                @endforelse
            </div>
        </x-cards.base-card>

        <x-cards.base-card title="Stock Status" subtitle="Current inventory condition">
            <div id="schoolHeadStatusChart" class="min-h-[280px]" data-labels='@json($statusData->pluck("label")->values())' data-values='@json($statusData->pluck("quantity")->values())'></div>
            <div class="space-y-3">
                @forelse ($statusData as $status)
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 text-sm last:border-0 dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-300">{{ $status['label'] }}</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($status['quantity']) }}</span>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500">No status data available.</p>
                @endforelse
            </div>
        </x-cards.base-card>
    </div>

    <div class="mt-6">
        <x-cards.base-card title="Recent Transactions" subtitle="Latest inventory activity">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-700"><tr><th class="pb-3">Item</th><th class="pb-3">User</th><th class="pb-3 text-right">Quantity</th><th class="pb-3 text-right">Date</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($recentTransactions as $transaction)
                            <tr><td class="py-3 font-medium">{{ $transaction->item?->item_name ?? 'Unknown item' }}</td><td class="py-3 text-gray-500">{{ $transaction->user?->full_name ?? 'Unknown user' }}</td><td class="py-3 text-right">{{ number_format($transaction->quantity) }}</td><td class="py-3 text-right text-gray-500">{{ $transaction->transaction_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-sm text-gray-500">No transactions recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.base-card>
    </div>
@endsection