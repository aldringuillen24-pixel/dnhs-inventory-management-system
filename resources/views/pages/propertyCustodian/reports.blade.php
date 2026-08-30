@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory Reports" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card title="Total Units" value="{{ number_format($metrics['totalUnits']) }}" subtitle="Non-disposed inventory" />
        <x-cards.metric-card title="Available Units" value="{{ number_format($metrics['availableUnits']) }}" subtitle="Ready for assignment" />
        <x-cards.metric-card title="Assigned Units" value="{{ number_format($metrics['assignedUnits']) }}" subtitle="Currently in use" />
        <x-cards.metric-card title="Inventory Value" value="PHP {{ number_format($metrics['totalValue'], 2) }}" subtitle="Current recorded value" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Units by Category" subtitle="Inventory distribution by category">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                        <tr>
                            <th class="pb-3 font-medium">Category</th>
                            <th class="pb-3 text-right font-medium">Units</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($categoryData as $category)
                            <tr>
                                <td class="py-3 text-gray-700 dark:text-gray-300">{{ $category['label'] }}</td>
                                <td class="py-3 text-right font-semibold text-gray-900 dark:text-white">{{ number_format($category['quantity']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-8 text-center text-gray-500">No category data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.base-card>

        <x-cards.base-card title="Stock Status" subtitle="Current inventory condition">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                        <tr>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 text-right font-medium">Units</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($statusData as $status)
                            <tr>
                                <td class="py-3 text-gray-700 dark:text-gray-300">{{ $status['label'] }}</td>
                                <td class="py-3 text-right font-semibold text-gray-900 dark:text-white">{{ number_format($status['quantity']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-8 text-center text-gray-500">No stock data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.base-card>
    </div>

    <div class="mt-6">
        <x-cards.base-card title="Recent Transactions" subtitle="Latest inventory activity">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                        <tr>
                            <th class="pb-3 font-medium">Item</th>
                            <th class="pb-3 font-medium">User</th>
                            <th class="pb-3 text-right font-medium">Quantity</th>
                            <th class="pb-3 text-right font-medium">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($recentTransactions as $transaction)
                            <tr>
                                <td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $transaction->item?->item_name ?? 'Unknown item' }}</td>
                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $transaction->user?->full_name ?? 'Unknown user' }}</td>
                                <td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($transaction->quantity) }}</td>
                                <td class="py-3 text-right text-gray-500 dark:text-gray-400">{{ $transaction->transaction_date?->format('M d, Y') ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-gray-500">No transactions recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.base-card>
    </div>
@endsection
