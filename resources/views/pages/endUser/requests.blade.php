@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Requests" />

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-12">
            <x-cards.base-card title="Requests" subtitle="Manage your item requisitions and incoming custodian assignments">
                
                <div x-data="{ activeTab: '{{ $pendingIncomingCount > 0 ? 'incoming' : 'my-requests' }}' }">
                    
                    {{-- Tab Navigation Bar & Request Item Action --}}
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 dark:border-gray-700 pb-3">
                        <div class="flex gap-2">
                            <button type="button"
                                @click="activeTab = 'my-requests'"
                                :class="activeTab === 'my-requests'
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm transition focus:outline-none -mb-3.5">
                                <span>My Requests</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $myRequests->count() }}
                                </span>
                            </button>

                            <button type="button"
                                @click="activeTab = 'incoming'"
                                :class="activeTab === 'incoming'
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-4 py-2.5 text-sm transition focus:outline-none -mb-3.5">
                                <span>Incoming Requests</span>
                                @if ($pendingIncomingCount > 0)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                        {{ $pendingIncomingCount }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        {{ $incomingRequests->count() }}
                                    </span>
                                @endif
                            </button>
                        </div>

                        {{-- Request Item Modal Trigger --}}
                        <div class="flex justify-end">
                            <x-modals.base-modal title="New Request" subtitle="Submit an item requisition to the property custodian" maxWidth="max-w-md">
                                <x-slot:trigger>
                                    <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3.5 py-2 text-sm font-medium text-white shadow-xs transition hover:bg-brand-600">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                        </svg>
                                        Request Item
                                    </button>
                                </x-slot:trigger>

                                <form method="POST" action="{{ route('endUser.requests.store') }}" class="space-y-4 text-left">
                                    @csrf
                                    <div>
                                        <label for="inventory-item" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Item</label>
                                        <select id="inventory-item" name="item_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                            <option value="">Select an item</option>
                                            @foreach (($availableItems ?? []) as $inventoryItem)
                                                <option value="{{ $inventoryItem['item_id'] }}">{{ $inventoryItem['item_name'] }} ({{ $inventoryItem['quantity'] }} available)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input id="quantity" name="quantity" type="number" min="1" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter quantity" required />
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2">
                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                        <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Submit Request</button>
                                    </div>
                                </form>
                            </x-modals.base-modal>
                        </div>
                    </div>

                    {{-- TAB 1: MY REQUESTS (OUTGOING REQUISITIONS) --}}
                    <div x-show="activeTab === 'my-requests'" class="mt-4">
                        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    <tr class="text-center">
                                        <th class="px-4 py-3 text-left">Item Requested</th>
                                        <th class="px-4 py-3">Qty</th>
                                        <th class="px-4 py-3">Requested On</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Transaction Ref</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @forelse($myRequests as $req)
                                        @php
                                            $s = strtolower((string) $req->status);
                                            $badgeClass = match (true) {
                                                str_contains($s, 'approved') || str_contains($s, 'accepted') => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                str_contains($s, 'waiting') || str_contains($s, 'pending') => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                                str_contains($s, 'declined') || str_contains($s, 'rejected') => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                default => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            };
                                        @endphp
                                        <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">
                                                <div>{{ optional($req->item)->item_name ?? 'Unknown item' }}</div>
                                                @if(optional($req->item)->inventory_item_no)
                                                    <span class="text-xs text-gray-400">{{ $req->item->inventory_item_no }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">{{ $req->quantity }}</td>
                                            <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $req->requested_at ? $req->requested_at->format('M d, Y h:i A') : 'N/A' }}
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                                    {{ ucfirst($req->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                @if($req->transaction_id)
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-mono text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                        #TX-{{ str_pad($req->transaction_id, 5, '0', STR_PAD_LEFT) }}
                                                @else
                                                    <span class="text-xs text-gray-400">--</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                No item requests created yet. Click <strong>"Request Item"</strong> above to make a request.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- TAB 2: INCOMING REQUESTS (CUSTODIAN ASSIGNMENTS / REQUISITIONS) --}}
                    <div x-show="activeTab === 'incoming'" class="mt-4">
                        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    <tr class="text-center">
                                        <th class="px-4 py-3 text-left">Item</th>
                                        <th class="px-4 py-3">Qty</th>
                                        <th class="px-4 py-3 text-left">Requested By</th>
                                        <th class="px-4 py-3">Requested On</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @forelse($incomingRequests as $req)
                                        @php
                                            $s = strtolower((string) $req->status);
                                            $isTransfer = $s === 'waiting for transfer approval' || $s === 'waiting for custodian approval';
                                            $badgeClass = match (true) {
                                                str_contains($s, 'approved') || str_contains($s, 'accepted') => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                str_contains($s, 'waiting') || str_contains($s, 'pending') => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                                str_contains($s, 'declined') || str_contains($s, 'rejected') => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                default => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            };
                                        @endphp
                                        <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">
                                                <div>{{ optional($req->item)->item_name ?? 'Unknown item' }}</div>
                                                @if(optional($req->item)->inventory_item_no)
                                                    <span class="text-xs text-gray-400">{{ $req->item->inventory_item_no }}</span>
                                                @endif
                                                {{-- Type badge --}}
                                                <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $isTransfer ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }}">
                                                    {{ $isTransfer ? 'Transfer Request' : 'Assignment' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">{{ $req->quantity }}</td>
                                            <td class="px-4 py-4 text-left">
                                                <div class="font-medium text-gray-800 dark:text-gray-200">{{ optional($req->user)->full_name ?: ($req->user?->username ?? ($isTransfer ? 'End User' : 'Custodian')) }}</div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $req->user?->email ?? '' }}</span>
                                            </td>
                                            <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $req->requested_at ? $req->requested_at->format('M d, Y h:i A') : 'N/A' }}
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                                    {{ ucfirst($req->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                @if($req->status === 'waiting for approval' || $req->status === 'waiting for transfer approval')
                                                    <div class="flex items-center justify-center gap-2">
                                                        <form method="POST" action="{{ route('endUser.requests.respond', $req->id) }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="accept" />
                                                            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-green-700">Accept</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('endUser.requests.respond', $req->id) }}">
                                                            @csrf
                                                            <input type="hidden" name="action" value="decline" />
                                                            <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">Decline</button>
                                                        </form>
                                                    </div>
                                                @elseif($req->status === 'waiting for custodian approval')
                                                    <span class="text-xs text-amber-600 dark:text-amber-400">Awaiting Custodian</span>
                                                @else
                                                    <span class="text-xs text-gray-400">Processed</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                No incoming requests found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
