@extends('layouts.app')

@section('content')
	<x-common.page-breadcrumb pageTitle="My Requests" />

	<div class="grid gap-6 xl:grid-cols-12 mt-6">
		<div class="col-span-12 xl:col-span-12">
			<x-cards.base-card title="My Requests" subtitle="Requests you created">
				<div class="mb-4 flex justify-end gap-3">

				<!-- Transfer Request Modal -->
					<x-modals.base-modal title="Request Transfer" subtitle="Request a transfer">
						<x-slot:trigger>
							<button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md border border-brand-500 bg-white px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-brand-50">
								Request Transfer
							</button>
						</x-slot:trigger>
						<div class="space-y-4">
							<p class="text-sm text-gray-600 dark:text-gray-400">Submit a transfer request for an assigned item. A property custodian will review this request.</p>
							<div class="flex justify-end gap-3 pt-2">
								<button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Close</button>
								<button type="button" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Request Transfer</button>
							</div>
						</div>
					</x-modals.base-modal>

					<!-- New Request Modal -->
					<x-modals.base-modal title="New Request" subtitle="Request an item">
						<x-slot:trigger>
							<button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
								Request Item
							</button>
						</x-slot:trigger>

						<form method="POST" action="{{ route('endUser.requests.store') }}" class="space-y-4">
							@csrf
							<div>
								<label for="inventory-item" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Item</label>
								<select id="inventory-item" name="item_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
									<option value="">Select an item</option>
									@foreach (($availableItems ?? []) as $inventoryItem)
										<option value="{{ $inventoryItem['item_id'] }}">{{ $inventoryItem['item_name'] }} (Qty: {{ $inventoryItem['quantity'] }})</option>
									@endforeach
								</select>
							</div>
							<label for="quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
							<input id="quantity" name="quantity" type="number" min="1" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter quantity"/>
							<div class="flex justify-end gap-3 pt-2">
								<button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
								<button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Submit Request</button>
							</div>
						</form>
					</x-modals.base-modal>
				</div>
				<div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
					<table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
						<thead class="bg-gray-50 text-xs uppecase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
							<tr class="text-center">
								<th class="px-4 py-3">Qty</th>
								<th class="px-4 py-3">Item</th>
								<th class="px-4 py-3">Requested On</th>
								<th class="px-4 py-3">Status</th>
								<th class="px-4 py-3">Transaction</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
							@forelse($requests as $req)
								<tr class="text-center">
									<td class="px-4 py-4">{{ $req->quantity }}</td>
									<td class="px-4 py-4">{{ optional($req->item)->item_name ?? 'Unknown item' }}</td>									<td class="px-4 py-4">{{ $req->requested_at->format('Y-m-d') }}</td>
									<td class="px-4 py-4">
										<span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
											{{ ucfirst($req->status) }}
										</span>
									</td>
									<td class="px-4 py-4">
										@if($req->transaction_id)
											<a href="#" class="text-sm text-brand-600">View (#{{ $req->transaction_id }})</a>
										@else
											<span class="text-sm text-gray-500">N/A</span>
										@endif
									</td>
								</tr>
							@empty
								<tr>
									<td colspan="6" class="px-4 py-6 text-center">No requests found.</td>
								</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</x-cards.base-card>
		</div>
	</div>

@endsection