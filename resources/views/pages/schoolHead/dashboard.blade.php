@extends('layouts.app')

@section('content')
	<x-common.page-breadcrumb pageTitle="School Head Dashboard" />
	<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
		<x-cards.metric-card title="Total Units" value="{{ number_format($metrics['totalUnits']) }}" subtitle="Non-disposed assets" />
		<x-cards.metric-card title="Available" value="{{ number_format($metrics['availableUnits']) }}" subtitle="Ready for assignment" />
		<x-cards.metric-card title="Assigned" value="{{ number_format($metrics['assignedUnits']) }}" subtitle="Currently in use" />
		<x-cards.metric-card title="Inventory Value" value="PHP {{ number_format($metrics['totalValue'], 2) }}" subtitle="Recorded asset value" />
		<x-cards.metric-card title="Pending Requests" value="{{ number_format($metrics['pendingRequests']) }}" subtitle="Awaiting action" />
	</div>

	<div class="mt-6 grid gap-6 xl:grid-cols-12">
		<div class="xl:col-span-5">
			<x-cards.base-card title="Inventory by Category" subtitle="Current non-disposed units">
				<div id="schoolHeadDashboardCategoryChart" class="min-h-[280px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("quantity")->values())'></div>
				<div class="space-y-4">
					@forelse ($categoryData as $category)
						@php($percentage = $metrics['totalUnits'] > 0 ? min(100, round(($category['quantity'] / $metrics['totalUnits']) * 100)) : 0)
						<div>
							<div class="mb-1 flex items-center justify-between gap-3 text-sm">
								<span class="truncate font-medium text-gray-700 dark:text-gray-200">{{ $category['label'] }}</span>
								<span class="shrink-0 text-gray-500 dark:text-gray-400">{{ number_format($category['quantity']) }}</span>
							</div>
							<div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
								<div class="h-full rounded-full bg-brand-500" style="width: {{ $percentage }}%"></div>
							</div>
						</div>
					@empty
						<div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">No inventory data available.</div>
					@endforelse
				</div>
			</x-cards.base-card>
		</div>

		<div class="xl:col-span-7">
			<x-cards.base-card title="Recent Activity" subtitle="Latest recorded inventory transactions">
				<div class="overflow-x-auto">
					<table class="w-full min-w-[560px] text-left text-sm">
						<thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-700">
							<tr>
								<th class="pb-3 font-medium">Item</th>
								<th class="pb-3 font-medium">Assigned To</th>
								<th class="pb-3 text-right font-medium">Qty</th>
								<th class="pb-3 text-right font-medium">Date</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 dark:divide-gray-700">
							@forelse ($recentTransactions as $transaction)
								<tr>
									<td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $transaction->item?->item_name ?? 'Unknown item' }}</td>
									<td class="py-3 text-gray-500 dark:text-gray-400">{{ $transaction->user?->full_name ?? 'Unknown user' }}</td>
									<td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($transaction->quantity) }}</td>
									<td class="py-3 text-right text-gray-500 dark:text-gray-400">{{ $transaction->transaction_date?->format('M d, Y') ?? 'N/A' }}</td>
								</tr>
							@empty
								<tr><td colspan="4" class="py-8 text-center text-sm text-gray-500">No transactions recorded yet.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</x-cards.base-card>
		</div>
	</div>
@endsection
