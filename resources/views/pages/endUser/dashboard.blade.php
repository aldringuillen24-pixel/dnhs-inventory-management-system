@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="My Dashboard" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-cards.metric-card title="Assigned Items" value="{{ number_format($metrics['assignedItems']) }}" subtitle="Items currently in your custody" />
        <x-cards.metric-card title="Pending Requests" value="{{ number_format($metrics['pendingRequests']) }}" subtitle="Requests awaiting processing" />
        <x-cards.metric-card title="Incoming Requests" value="{{ number_format($metrics['incomingRequests']) }}" subtitle="Requests needing your response" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-12">
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
                                    <td class="py-3">
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ ucfirst($activity->status) }}</span>
                                    </td>
                                    <td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($activity->quantity) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-500">No activity recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-4">
            <x-cards.base-card title="Quick Actions" subtitle="Common inventory tasks">
                <div class="space-y-3">
                    <a href="{{ route('endUser.requests') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                        <span>View my requests</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('endUser.my-assigned-items') }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800">
                        <span>View assigned items</span>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
