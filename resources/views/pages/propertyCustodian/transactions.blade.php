    @extends('layouts.app')

    @section('content')
        <x-common.page-breadcrumb pageTitle="Transactions" />

        <div class="grid gap-6 xl:grid-cols-12">
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Total Assigned Items"
                    value="84"
                    subtitle="Items currently assigned"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M4 3a1 1 0 00-1 1v2a1 1 0 001 1h1v8a1 1 0 001 1h8a1 1 0 001-1V7h1a1 1 0 001-1V4a1 1 0 00-1-1H4z'/><path d='M5 7V5h10V7H5z'/></svg>"
                >
                    <span class="text-sm text-gray-500 dark:text-gray-400">Updated just now.</span>
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Pending Inspections"
                    value="12"
                    subtitle="Awaiting review"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm1-11V5a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0V9h2a1 1 0 100-2h-2z' clip-rule='evenodd'/></svg>"
                    tone="positive"
                >
                    New inspection tasks were added today.
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Overdue Returns"
                    value="6"
                    subtitle="Items past due date"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M10 2a8 8 0 100 16 8 8 0 000-16zm1 9H9V7a1 1 0 112 0v4z'/></svg>"
                    tone="negative"
                >
                    Follow up with custodians for quick returns.
                </x-cards.metric-card>
            </div>
            <div class="col-span-12 md:col-span-6 xl:col-span-3">
                <x-cards.metric-card
                    label="Maintenance Due"
                    value="18"
                    subtitle="Items requiring service"
                    icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M9.049 2.927a1 1 0 011.902 0l.518 2.128a8.04 8.04 0 013.054 1.771l1.996-.39a1 1 0 011.149 1.254l-.901 2.93a8.072 8.072 0 010 2.356l.9 2.929a1 1 0 01-1.15 1.255l-1.995-.39a8.04 8.04 0 01-3.056 1.772l-.516 2.128a1 1 0 01-1.902 0l-.517-2.128a8.04 8.04 0 01-3.055-1.772l-1.996.39a1 1 0 01-1.15-1.255l.901-2.93a8.072 8.072 0 010-2.356l-.9-2.929a1 1 0 011.15-1.255l1.995.39a8.04 8.04 0 013.055-1.771l.517-2.128z'/></svg>"
                    tone="neutral"
                >
                    Keep the maintenance schedule up to date.
                </x-cards.metric-card>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12 mt-6">
            <div class="col-span-12 xl:col-span-12">
                <x-cards.base-card title="Manage Transactions" subtitle="Search and manage assigned assets">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="flex-1">
                            <input type="search" placeholder="Search assets" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Category</button>
                            <!-- Assign Inventory Item Modal -->
                            <x-modals.base-modal title="Assign Inventory Item" subtitle="Record a new item assignment.">
                                <x-slot:trigger>
                                    <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                                        </svg>
                                        Assign
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
                                    <label for="quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                    <input id="quantity" name="quantity" type="number" min="1" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter quantity"/>
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
                            <thead class="bg-gray-50 text-xs uppecase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr class="text-center">
                                    <th class="px-4 py-3">Qty</th>
                                    <th class="px-4 py-3">Item Name</th>
                                    <th class="px-4 py-3">Assign To</th>
                                    <th class="px-4 py-3">Date Assigned</th>
                                    <th class="px-4 py-3">Date Returned</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($transactions as $transaction)
                                    <tr class="text-center">
                                        <td class="px-4 py-4">{{ $transaction->quantity }}</td>
                                        <td class="px-4 py-4">{{ optional($transaction->item)->item_name ?? 'Unknown item' }}</td>
                                        <td class="px-4 py-4">{{ $transaction->user->full_name }}</td>
                                        <td class="px-4 py-4">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                        <td class="px-4 py-4">{{ $transaction->return_date ? $transaction->return_date->format('Y-m-d') : 'N/A' }}</td>
                                        <td class="px-4 py-4">
                                            @php
                                                $s = strtolower($transaction->status ?? '');
                                                $badgeClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ';
                                                if (str_contains($s, 'waiting')) {
                                                    $badgeClasses .= 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
                                                } elseif (str_contains($s, 'assigned')) {
                                                    $badgeClasses .= 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                                                } elseif (str_contains($s, 'transfer') || str_contains($s, 'transferred')) {
                                                    $badgeClasses .= 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300';
                                                } elseif (str_contains($s, 'returned')) {
                                                    $badgeClasses .= 'bg-black text-white';
                                                } else {
                                                    $badgeClasses .= 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                                                }
                                            @endphp

                                            <span class="{{ $badgeClasses }}">
                                                {{ $transaction->status ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            @php($isConsumable = false) <!-- Replace with actual logic to determine if the item is consumable -->
                                            <div x-data="{ open: false, menuStyle: '', toggleMenu(event) { if (!this.open) { const rect = event.currentTarget.getBoundingClientRect(); this.menuStyle = `top: ${rect.bottom + 4}px; left: ${rect.right - 144}px;`; } this.open = !this.open; } }">
                                                <button type="button" @click="toggleMenu($event)" class="rounded-full p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Transaction actions" :aria-expanded="open.toString()">
                                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                                                    </svg>
                                                </button>

                                                <div x-show="open" x-cloak x-transition @click.outside="open = false" @keydown.escape.window="open = false" :style="menuStyle" class="fixed z-[1200] w-36 rounded-md border border-gray-200 bg-white py-1 text-left shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                                    
                                                
                                                <!-- Transaction Details Modal -->
                                                <x-modals.base-modal title="Transaction Details" subtitle="View assignment details for this transaction." maxWidth="max-w-xl">
                                                        <x-slot:trigger>
                                                            <button type="button" @click="open = true" @disabled($isConsumable) class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                Details
                                                            </button>
                                                        </x-slot:trigger>

                                                        <div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
                                                            <div class="grid gap-2 sm:grid-cols-2">
                                                                <div class="mt1">
                                                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</h3>
                                                                    <p>{{ optional($transaction->item)->item_name ?? 'Unknown item' }}</p>
                                                                </div>
                                                                <div  class="mt1">
                                                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Assigned To</h3>
                                                                    <p>{{ $transaction->user->full_name }}</p>
                                                                </div>
                                                                <div  class="mt1">
                                                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quantity</h3>
                                                                    <p>{{ $transaction->quantity }}</p>
                                                                </div>
                                                                <div  class="mt1">
                                                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Date Assigned</h3>
                                                                    <p>{{ $transaction->transaction_date->format('Y-m-d') }}</p>
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
                                                                        @if ($enduser['id'] !== $transaction->user->id)
                                                                            <option value="{{ $enduser['id'] }}">
                                                                                {{ $enduser['name'] }}
                                                                            </option>
                                                                        @endif
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label for="transfer-note-{{ $transaction->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Transfer Notes</label>
                                                                <textarea id="transfer-note-{{ $transaction->id }}" name="notes" rows="3" cols="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter transfer details"></textarea>
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
                                                                <p><span class="font-semibold">Assigned To:</span> {{ $transaction->user->full_name }}</p>
                                                                <p><span class="font-semibold">Assigned On:</span> {{ $transaction->transaction_date->format('Y-m-d') }}</p>
                                                            </div>
                                                            <div class="flex justify-end gap-3 pt-2">
                                                                <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                <button type="button" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Confirm Return</button>
                                                            </div>
                                                        </div>
                                                    </x-modals.base-modal>
                                                </div>
                                            </div>          
                                    </tr>
                                @endforeach
                            </tbody>
                            @empty($transaction)
                                <tfoot class="bg-gray-50 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400 ">
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center">No transactions found.</td>
                                    </tr>
                                </tfoot>
                            @endempty
                        </table>
                    </div>
                </x-cards.base-card>
            </div>
        </div>
    @endsection
