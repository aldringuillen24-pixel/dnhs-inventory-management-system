    @extends('layouts.app')

    @section('content')
        <x-common.page-breadcrumb pageTitle="Transactions" />

        {{-- Metric Summary Cards --}}
        <div class="grid gap-6 xl:grid-cols-12">
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Total Assigned Items"
                    value="{{ $totalAssignedCount }}"
                    subtitle="Items currently assigned"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M4 3a1 1 0 00-1 1v2a1 1 0 001 1h1v8a1 1 0 001 1h8a1 1 0 001-1V7h1a1 1 0 001-1V4a1 1 0 00-1-1H4z'/><path d='M5 7V5h10V7H5z'/></svg>"
                >
                    <span class="text-sm text-gray-500 dark:text-gray-400">Active assignments in circulation</span>
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Pending Requests"
                    value="{{ $pendingRequestsCount }}"
                    subtitle="Awaiting custodian action"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm1-11V5a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0V9h2a1 1 0 100-2h-2z' clip-rule='evenodd'/></svg>"
                    tone="{{ $pendingRequestsCount > 0 ? 'positive' : 'neutral' }}"
                >
                    {{ $pendingRequestsCount > 0 ? 'Requests require your review.' : 'All requests processed.' }}
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Overdue Returns"
                    value="{{ $overdueReturnsCount }}"
                    subtitle="Items past return date"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M10 2a8 8 0 100 16 8 8 0 000-16zm1 9H9V7a1 1 0 112 0v4z'/></svg>"
                    tone="{{ $overdueReturnsCount > 0 ? 'negative' : 'neutral' }}"
                >
                    Follow up with custodians for quick returns.
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Total Transactions"
                    value="{{ $totalTransactionsCount }}"
                    subtitle="Recorded movements"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M9 2a1 1 0 000 2h2a1 1 0 100-2H9z'/><path fill-rule='evenodd' d='M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z' clip-rule='evenodd'/></svg>"
                    tone="neutral"
                >
                    Full historical audit trail.
                </x-cards.metric-card>
            </div>
        </div>

        {{-- Main Management Card with Tabs --}}
        <div class="grid gap-6 xl:grid-cols-12 mt-6">
            <div class="col-span-12 xl:col-span-12">
                <x-cards.base-card title="Manage Transactions & Requisitions" subtitle="Process incoming end-user requests and view historical transaction records">
                    
                    <div x-data="{ activeTab: '{{ $pendingRequestsCount > 0 ? 'requests' : 'history' }}', searchRequests: '', searchHistory: '' }">
                        
                        {{-- Tab Navigation Bar --}}
                        <div class="mb-5 flex border-b border-gray-200 dark:border-gray-700">
                            <button type="button"
                                @click="activeTab = 'requests'"
                                :class="activeTab === 'requests' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm transition focus:outline-none">
                                <span>Incoming Requests</span>
                                @if ($pendingRequestsCount > 0)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                        {{ $pendingRequestsCount }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        0
                                    </span>
                                @endif
                            </button>

                            <button type="button"
                                @click="activeTab = 'history'"
                                :class="activeTab === 'history' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition focus:outline-none">
                                <span>Transaction History</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $totalTransactionsCount }}
                                </span>
                            </button>

                            <button type="button"
                                @click="activeTab = 'returns'"
                                :class="activeTab === 'returns' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition focus:outline-none">
                                <span>Pending Returns</span>
                                @php
                                    $returnCount = count($pendingReturns ?? []);
                                @endphp
                                @if ($returnCount > 0)
                                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
                                        {{ $returnCount }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        0
                                    </span>
                                @endif
                            </button>

                            <button type="button"
                                @click="activeTab = 'audit'"
                                :class="activeTab === 'audit' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition focus:outline-none">
                                <span>Audit Ledger</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $auditLedger->count() }}
                                </span>
                            </button>
                        </div>

                        {{-- TAB 1: INCOMING REQUESTS (END USER REQUISITIONS) --}}
                        <div x-show="activeTab === 'requests'">
                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3 text-left">Requester</th>
                                            <th class="px-4 py-3 text-left">Item Requested</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Qty Requested</th>
                                            <th class="px-4 py-3">Stock In Hand</th>
                                            <th class="px-4 py-3">Date Requested</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($incomingRequests as $request)
                                            @php
                                                $requesterName = $request->user?->full_name ?: ($request->user?->username ?? 'Unknown User');
                                                $itemName = optional($request->item)->item_name ?? 'Unknown item';
                                                $categoryName = optional(optional($request->item)->category)->category_name ?? 'General';
                                                $totalStock = $request->total_available_stock ?? (optional($request->item)->quantity ?? 0);
                                                $hasStock = $totalStock >= $request->quantity;
                                                $stockAfterApproval = max(0, $totalStock - $request->quantity);
                                            @endphp
                                            <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">
                                                    <div>{{ $requesterName }}</div>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $request->user?->email ?? '' }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $itemName }}</div>
                                                    @if(optional($request->item)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $request->item->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $categoryName }}
                                                </td>
                                                <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ $request->quantity }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $hasStock ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                                        {{ $totalStock }} available
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $request->requested_at ? $request->requested_at->format('M d, Y h:i A') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center justify-center gap-2">
                                                        {{-- Approve Modal Trigger --}}
                                                        <x-modals.base-modal title="Approve Item Request" subtitle="Confirm assignment of requested item to the requester." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true" @disabled(!$hasStock)
                                                                    class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Approve
                                                                </button>
                                                            </x-slot:trigger>

                                                            <form method="POST" action="{{ route('propertyCustodian.requests.approve', $request->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                                                    Are you sure you want to approve this request? This will deduct the item from inventory and assign it to <strong>{{ $requesterName }}</strong>.
                                                                </p>
                                                                <div class="space-y-1.5 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Item:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $itemName }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Requested Quantity:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $request->quantity }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Total Warehouse Stock:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $totalStock }} available</span>
                                                                    </div>
                                                                    <div class="flex justify-between border-t border-gray-200 pt-1.5 dark:border-gray-700">
                                                                        <span class="font-medium text-gray-700 dark:text-gray-300">Stock After Approval:</span>
                                                                        <span class="font-semibold {{ $stockAfterApproval > 0 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $stockAfterApproval }}</span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Confirm Approval</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>

                                                        {{-- Decline Modal Trigger --}}
                                                        <x-modals.base-modal title="Decline Request" subtitle="Provide a reason for declining this request." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true"
                                                                    class="inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Decline
                                                                </button>
                                                            </x-slot:trigger>

                                                            <form method="POST" action="{{ route('propertyCustodian.requests.decline', $request->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <div>
                                                                    <label for="decline-notes-{{ $request->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason / Notes (Optional)</label>
                                                                    <textarea id="decline-notes-{{ $request->id }}" name="notes" rows="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Specify why this request cannot be fulfilled..."></textarea>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">Confirm Decline</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                                    <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                    <p class="text-base font-medium text-gray-700 dark:text-gray-300">No Pending Requests</p>
                                                    <p class="text-xs text-gray-400">All incoming requisition requests have been reviewed.</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- PENDING TRANSFERS (peer-to-peer, awaiting custodian approval) --}}
                        @if($pendingTransfers->isNotEmpty())
                        <div x-show="activeTab === 'requests'" class="mt-6">
                            <div class="mb-3 flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Pending Peer-to-Peer Transfers</h3>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ $pendingTransfers->count() }} awaiting your approval
                                </span>
                            </div>
                            <div class="overflow-x-auto rounded-md border border-amber-200 bg-white dark:border-amber-800/40 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-amber-50 text-xs uppercase tracking-wide text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3 text-left">From (Sender)</th>
                                            <th class="px-4 py-3 text-left">To (Recipient)</th>
                                            <th class="px-4 py-3 text-left">Item</th>
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3">Requested On</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @foreach($pendingTransfers as $transfer)
                                            @php
                                                $senderName    = $transfer->user?->full_name ?: ($transfer->user?->username ?? 'Unknown');
                                                $recipientName = $transfer->targetUser?->full_name ?: ($transfer->targetUser?->username ?? 'Unknown');
                                                $itemName      = optional($transfer->item)->item_name ?? 'Unknown item';
                                            @endphp
                                            <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">{{ $senderName }}</td>
                                                <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">{{ $recipientName }}</td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $itemName }}</div>
                                                    @if(optional($transfer->item)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $transfer->item->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">{{ $transfer->quantity }}</td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $transfer->requested_at ? $transfer->requested_at->format('M d, Y h:i A') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center justify-center gap-2">
                                                        {{-- Approve Transfer Modal --}}
                                                        <x-modals.base-modal title="Approve Transfer" subtitle="Confirm peer-to-peer item transfer." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true"
                                                                    class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-green-700">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Approve
                                                                </button>
                                                            </x-slot:trigger>
                                                            <form method="POST" action="{{ route('propertyCustodian.transfers.approve', $transfer->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                                                    Are you sure you want to approve this transfer?
                                                                </p>
                                                                <div class="space-y-1.5 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Item:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $itemName }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Qty:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $transfer->quantity }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">From:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $senderName }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between border-t border-gray-200 pt-1.5 dark:border-gray-700">
                                                                        <span class="font-medium text-gray-700 dark:text-gray-300">To:</span>
                                                                        <span class="font-semibold text-green-600 dark:text-green-400">{{ $recipientName }}</span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Confirm Approval</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>

                                                        {{-- Decline Transfer Modal --}}
                                                        <x-modals.base-modal title="Decline Transfer" subtitle="Provide a reason for declining." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true"
                                                                    class="inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Decline
                                                                </button>
                                                            </x-slot:trigger>
                                                            <form method="POST" action="{{ route('propertyCustodian.transfers.decline', $transfer->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <div>
                                                                    <label for="decline-transfer-notes-{{ $transfer->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason / Notes (Optional)</label>
                                                                    <textarea id="decline-transfer-notes-{{ $transfer->id }}" name="notes" rows="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Specify why this transfer cannot be approved..."></textarea>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">Confirm Decline</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        {{-- TAB 3: PENDING RETURNS --}}
                        <div x-show="activeTab === 'returns'" x-cloak>
                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3 text-left">End User</th>
                                            <th class="px-4 py-3 text-left">Item</th>
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3">Requested</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($pendingReturns ?? [] as $returnRequest)
                                            @php
                                                $endUser = $returnRequest->user;
                                                $inventoryItem = $returnRequest->item;
                                            @endphp
                                            <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">
                                                    <div>{{ $endUser->full_name ?? 'Unknown User' }}</div>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $endUser->username ?? '' }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $inventoryItem->item_name ?? 'Unknown item' }}</div>
                                                    @if($inventoryItem?->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $inventoryItem->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 font-semibold text-gray-800 dark:text-gray-200">{{ $returnRequest->quantity }}</td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $returnRequest->requested_at ? $returnRequest->requested_at->format('M d, Y h:i A') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center justify-center gap-2">
                                                        {{-- Approve Return Modal --}}
                                                        <x-modals.base-modal title="Approve Return" subtitle="Accept item return from end user." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true"
                                                                    class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-green-700">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Approve
                                                                </button>
                                                            </x-slot:trigger>
                                                            <form method="POST" action="{{ route('propertyCustodian.returns.approve', $returnRequest->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                                                    Are you sure you want to approve this return? The item will be marked as available and removed from the end user's assignment.
                                                                </p>
                                                                <div class="space-y-1.5 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">End User:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $endUser->full_name ?? 'Unknown' }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between">
                                                                        <span class="text-gray-500 dark:text-gray-400">Item:</span>
                                                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $inventoryItem->item_name ?? 'Unknown' }}</span>
                                                                    </div>
                                                                    <div class="flex justify-between border-t border-gray-200 pt-1.5 dark:border-gray-700">
                                                                        <span class="font-medium text-gray-700 dark:text-gray-300">Quantity:</span>
                                                                        <span class="font-semibold text-green-600 dark:text-green-400">{{ $returnRequest->quantity }}</span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Confirm Approval</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>

                                                        {{-- Decline Return Modal --}}
                                                        <x-modals.base-modal title="Decline Return" subtitle="Reject end user's return request." maxWidth="max-w-md">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true"
                                                                    class="inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">
                                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                    Decline
                                                                </button>
                                                            </x-slot:trigger>
                                                            <form method="POST" action="{{ route('propertyCustodian.returns.decline', $returnRequest->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <div>
                                                                    <label for="decline-return-notes-{{ $returnRequest->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason / Notes (Optional)</label>
                                                                    <textarea id="decline-return-notes-{{ $returnRequest->id }}" name="notes" rows="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Specify why this return cannot be accepted..."></textarea>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">Confirm Decline</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="text-center">
                                                <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No pending return requests.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- TAB 4: AUDIT LEDGER --}}
                        <div x-show="activeTab === 'audit'" x-cloak>
                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Date</th>
                                            <th class="px-4 py-3 text-left">Item</th>
                                            <th class="px-4 py-3 text-left">Movement</th>
                                            <th class="px-4 py-3 text-left">Qty</th>
                                            <th class="px-4 py-3 text-left">Before</th>
                                            <th class="px-4 py-3 text-left">After</th>
                                            <th class="px-4 py-3 text-left">Recorded By</th>
                                            <th class="px-4 py-3 text-left">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($auditLedger as $entry)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $entry->created_at ? $entry->created_at->format('M d, Y h:i A') : 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ optional($entry->inventory)->item_name ?? 'Unknown item' }}</div>
                                                    @if(optional($entry->inventory)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $entry->inventory->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                        {{ str_replace('_', ' ', $entry->movement_type) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-200">{{ $entry->quantity }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry->quantity_before }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry->quantity_after }}</td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                                    {{ $entry->user?->full_name ?: ($entry->user?->username ?? 'System') }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry->notes ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                                    No stock movement entries recorded yet.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- TAB 2: TRANSACTION HISTORY --}}
                        <div x-show="activeTab === 'history'" x-cloak>
                            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div class="flex-1">
                                    <input type="search" placeholder="Search transactions..." class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <!-- Assign Inventory Item Modal -->
                                    <x-modals.base-modal title="Assign Inventory Item" subtitle="Record a new item assignment.">
                                        <x-slot:trigger>
                                            <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                                                </svg>
                                                Assign Item
                                            </button>
                                        </x-slot:trigger>

                                        <form method="POST" action="{{ route('propertyCustodian.transactions.assignItem') }}" class="space-y-4">
                                            @csrf
                                            <div>
                                                <label for="inventory-item" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Item</label>
                                                <select id="inventory-item" name="item_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                    <option value="">Select an item</option>
                                                    @foreach ($availableInventoryItems as $inventoryItem)
                                                        <option value="{{ $inventoryItem['item_id'] }}">
                                                            {{ $inventoryItem['item_name'] }} (Qty: {{ $inventoryItem['quantity'] }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label for="quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                                <input id="quantity" name="quantity" type="number" min="1" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter quantity"/>
                                            </div>
                                            <div>
                                                <label for="assign-to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Assign To</label>
                                                <select id="assign-to" name="user_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                    <option value="">Select an end user</option>
                                                    @foreach ($endUsers as $enduser)
                                                        <option value="{{ $enduser['id'] }}">
                                                            {{ $enduser['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>    
                                            </div>
                                            <div>
                                                <label for="date-assigned" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Assigned</label>
                                                <input id="date-assigned" name="transaction_date" type="date" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                            <div class="flex justify-end gap-3 pt-2">
                                                <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Assign Item</button>
                                            </div>
                                        </form>
                                    </x-modals.base-modal>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3 text-left">Item Name</th>
                                            <th class="px-4 py-3 text-left">Assigned To</th>
                                            <th class="px-4 py-3">Date Assigned</th>
                                            <th class="px-4 py-3">Date Returned</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse($transactions as $transaction)
                                            <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 font-semibold">{{ $transaction->quantity }}</td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium">{{ optional($transaction->item)->item_name ?? 'Unknown item' }}</div>
                                                    @if(optional($transaction->item)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $transaction->item->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-left">{{ $transaction->user?->full_name ?: ($transaction->user?->username ?? 'Unknown') }}</td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">{{ $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : 'N/A' }}</td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">{{ $transaction->return_date ? $transaction->return_date->format('Y-m-d') : 'N/A' }}</td>
                                                <td class="px-4 py-4">
                                                    @php
                                                        $s = strtolower($transaction->status ?? '');
                                                        $badgeClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ';
                                                        if (str_contains($s, 'assigned')) {
                                                            $badgeClasses .= 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                                                        } elseif (str_contains($s, 'transfer') || str_contains($s, 'transferred')) {
                                                            $badgeClasses .= 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
                                                        } elseif (str_contains($s, 'returned')) {
                                                            $badgeClasses .= 'bg-black text-white dark:bg-gray-700';
                                                        } else {
                                                            $badgeClasses .= 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                                                        }
                                                    @endphp

                                                    <span class="{{ $badgeClasses }}">
                                                        {{ ucfirst($transaction->status ?? 'N/A') }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    @php($isConsumable = false)
                                                    <div x-data="{ 
                                                        open: false,
                                                        menuX: 0,
                                                        menuY: 0,
                                                        toggleMenu(event) {
                                                            if (!this.open) {
                                                                const rect = event.currentTarget.getBoundingClientRect();
                                                                this.menuX = rect.right - 144;
                                                                this.menuY = rect.bottom + 8;
                                                            }
                                                            this.open = !this.open;
                                                        }
                                                    }" @click.outside="open = false" class="relative">
                                                        <button type="button" @click="toggleMenu($event)" class="rounded-full p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Transaction actions" :aria-expanded="open.toString()">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                                                            </svg>
                                                        </button>

                                                        <div x-show="open" x-cloak x-transition:enter="ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" @click.outside="open = false" @keydown.escape.window="open = false" :style="`position: fixed; left: ${menuX}px; top: ${menuY}px;`" class="z-[9999] w-48 rounded-lg border border-gray-200 bg-white py-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                                                            
                                                            <!-- Transaction Details Modal -->
                                                            <x-modals.base-modal title="Transaction Details" subtitle="View assignment details for this transaction." maxWidth="max-w-xl">
                                                                <x-slot:trigger>
                                                                    <button type="button" @click="open = true" @disabled($isConsumable) class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                        Details
                                                                    </button>
                                                                </x-slot:trigger>

                                                                <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                                        <div>
                                                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</h3>
                                                                            <p>{{ optional($transaction->item)->item_name ?? 'Unknown item' }}</p>
                                                                        </div>
                                                                        <div>
                                                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Assigned To</h3>
                                                                            <p>{{ $transaction->user?->full_name ?: ($transaction->user?->username ?? 'Unknown') }}</p>
                                                                        </div>
                                                                        <div>
                                                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quantity</h3>
                                                                            <p>{{ $transaction->quantity }}</p>
                                                                        </div>
                                                                        <div>
                                                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date Assigned</h3>
                                                                            <p>{{ $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : 'N/A' }}</p>
                                                                        </div>
                                                                        <div class="sm:col-span-2">
                                                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date Returned</h3>
                                                                            <p>{{ $transaction->return_date ? $transaction->return_date->format('Y-m-d') : 'N/A' }}</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex justify-end">
                                                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Close</button>
                                                                    </div>
                                                                </div>
                                                            </x-modals.base-modal>

                                                            <!-- Transfer Item Modal -->
                                                            <x-modals.base-modal title="Transfer Item" subtitle="Confirm the transfer details for this assignment." maxWidth="max-w-xl">
                                                                <x-slot:trigger>
                                                                    <button type="button" @click="open = true" @disabled($isConsumable) class="block w-full px-3 py-2 text-left text-sm transition {{ $isConsumable ? 'cursor-not-allowed text-gray-400 dark:text-gray-500' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}" title="{{ $isConsumable ? 'Consumable items cannot be transferred.' : 'Transfer this item.' }}">
                                                                        Transfer
                                                                    </button>
                                                                </x-slot:trigger>

                                                                <form method="POST" action="#" class="space-y-4">
                                                                    @csrf
                                                                    <input type="hidden" name="transaction_id" value="{{ $transaction->id }}" />
                                                                    <div>
                                                                        <label for="transfer-to-{{ $transaction->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Transfer To</label>
                                                                        <select id="transfer-to-{{ $transaction->id }}" name="transfer_to" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                                            <option value="">Select a user</option>
                                                                            @foreach ($endUsers as $enduser)
                                                                                @if ($enduser['id'] !== optional($transaction->user)->id)
                                                                                    <option value="{{ $enduser['id'] }}">
                                                                                        {{ $enduser['name'] }}
                                                                                    </option>
                                                                                @endif
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <label for="transfer-note-{{ $transaction->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Transfer Notes</label>
                                                                        <textarea id="transfer-note-{{ $transaction->id }}" name="notes" rows="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter transfer details"></textarea>
                                                                    </div>
                                                                    <div class="flex justify-end gap-3 pt-2">
                                                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                        <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Confirm Transfer</button>
                                                                    </div>
                                                                </form>
                                                            </x-modals.base-modal>

                                                            <!-- Confirm Return Modal -->
                                                            <x-modals.base-modal title="Confirm Return" subtitle="Confirm returning this assigned item." maxWidth="max-w-md">
                                                                <x-slot:trigger>
                                                                    <button type="button" @click="open = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                        Return
                                                                    </button>
                                                                </x-slot:trigger>

                                                                <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                                                    <p>Are you sure you want to mark this item as returned?</p>
                                                                    <div class="space-y-2 rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                                        <p><span class="font-semibold">Item:</span> {{ optional($transaction->item)->item_name ?? 'Unknown item' }}</p>
                                                                        <p><span class="font-semibold">Assigned To:</span> {{ $transaction->user?->full_name ?: ($transaction->user?->username ?? 'Unknown') }}</p>
                                                                        <p><span class="font-semibold">Assigned On:</span> {{ $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : 'N/A' }}</p>
                                                                    </div>
                                                                    <div class="flex justify-end gap-3 pt-2">
                                                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                        <button type="button" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Confirm Return</button>
                                                                    </div>
                                                                </div>
                                                            </x-modals.base-modal>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                    No transactions found.
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
