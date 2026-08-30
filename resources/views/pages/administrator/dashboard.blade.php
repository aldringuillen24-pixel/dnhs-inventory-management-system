@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Administrator Dashboard" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card title="Inventory Units" value="{{ number_format($metrics['inventory']) }}" subtitle="Tracked, non-disposed units" />
        <x-cards.metric-card title="Available Units" value="{{ number_format($metrics['available']) }}" subtitle="Ready for assignment" />
        <x-cards.metric-card title="Today's Transactions" value="{{ number_format($metrics['transactions']) }}" subtitle="Recorded today" />
        <x-cards.metric-card title="Active Users" value="{{ number_format($metrics['users']) }}" subtitle="Enabled accounts" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-5">
            <x-cards.base-card title="Inventory by Category" subtitle="Current non-disposed units">
                <div
                    id="adminInventoryChart"
                    class="min-h-[280px]"
                    data-labels='@json($categoryData->pluck("label")->values())'
                    data-values='@json($categoryData->pluck("value")->values())'></div>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-7">
            <x-cards.base-card title="Recent Transactions" subtitle="Latest recorded inventory activity">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
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
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-gray-500">No transactions recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
