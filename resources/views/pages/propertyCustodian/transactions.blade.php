    @extends('layouts.app', ['title' => 'Transactions'])

    @section('content')
        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Transactions</h2>
            <x-modals.base-modal title="Assign Inventory Item" subtitle="Record a new item assignment.">
                <x-slot:trigger>
                    <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                        </svg>
                        Assign Item
                    </button>
                </x-slot:trigger>

                <div x-data="{ assignmentMode: 'registered' }" class="space-y-4">
                    <div class="grid grid-cols-2 border-b border-gray-200 dark:border-gray-700" role="tablist" aria-label="Assignment method">
                        <button type="button" role="tab" :aria-selected="assignmentMode === 'registered'" @click="assignmentMode = 'registered'" :class="assignmentMode === 'registered' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="border-b-2 px-3 py-2.5 text-sm font-medium">Registered End User</button>
                        <button type="button" role="tab" :aria-selected="assignmentMode === 'manual'" @click="assignmentMode = 'manual'" :class="assignmentMode === 'manual' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="border-b-2 px-3 py-2.5 text-sm font-medium">Manual Issue</button>
                    </div>

                    <form x-show="assignmentMode === 'registered'" method="POST" action="{{ route('propertyCustodian.transactions.assignItem') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="assignment_type" value="registered">
                        <div>
                            <label for="registered-inventory-item" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Item</label>
                            <select id="registered-inventory-item" name="item_id" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">Select an item</option>
                                @foreach ($availableInventoryItems as $inventoryItem)
                                    <option value="{{ $inventoryItem['item_id'] }}">{{ $inventoryItem['item_name'] }} · {{ $inventoryItem['category_name'] ?? 'Uncategorized' }} · {{ $inventoryItem['unit'] ?? 'unit not set' }} · {{ $inventoryItem['serial_number'] ?? $inventoryItem['inventory_item_no'] ?? 'ID ' . $inventoryItem['item_id'] }} · Qty {{ $inventoryItem['quantity'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="registered-quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                            <input id="registered-quantity" name="quantity" type="number" min="1" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter quantity" />
                        </div>
                        <div>
                            <label for="registered-assign-to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Assign To</label>
                            <select id="registered-assign-to" name="user_id" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">Select an end user</option>
                                @foreach ($endUsers as $enduser)
                                    <option value="{{ $enduser['id'] }}">{{ $enduser['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="registered-transaction-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Assigned</label>
                            <input id="registered-transaction-date" name="transaction_date" type="date" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button>
                            <x-common.button-spinner text="Assign Item" loadingText="Submitting..." class="text-white" />
                        </div>
                    </form>

                    <form x-show="assignmentMode === 'manual'" x-data="{ syncManualItem(select) { const option = select.selectedOptions[0]; this.$refs.category.value = option?.dataset.categoryId ?? ''; this.$refs.unit.value = option?.dataset.unit ?? ''; } }" method="POST" action="{{ route('propertyCustodian.transactions.assignItem') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="assignment_type" value="manual">
                        <input type="hidden" name="category_id" x-ref="category">
                        <input type="hidden" name="unit" x-ref="unit">
                        <div>
                            <label for="manual-inventory-item" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Inventory Item</label>
                            <select id="manual-inventory-item" name="item_id" @change="syncManualItem($event.target)" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">Select an item</option>
                                @foreach ($availableInventoryItems as $inventoryItem)
                                    <option value="{{ $inventoryItem['item_id'] }}" data-category-id="{{ $inventoryItem['category_id'] }}" data-unit="{{ $inventoryItem['unit'] }}">{{ $inventoryItem['item_name'] }} · {{ $inventoryItem['category_name'] ?? 'Uncategorized' }} · {{ $inventoryItem['unit'] ?? 'unit not set' }} · {{ $inventoryItem['serial_number'] ?? $inventoryItem['inventory_item_no'] ?? 'ID ' . $inventoryItem['item_id'] }} · Qty {{ $inventoryItem['quantity'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="manual-quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                <input id="manual-quantity" name="quantity" type="number" min="1" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                            <div>
                                <label for="manual-transaction-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Issue Date</label>
                                <input id="manual-transaction-date" name="transaction_date" type="date" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="manual-recipient-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient Full Name</label>
                                <input id="manual-recipient-name" name="manual_recipient_name" type="text" maxlength="255" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                            <div>
                                <label for="manual-department" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Department / Office</label>
                                <input id="manual-department" name="manual_department" type="text" maxlength="255" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="manual-building" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Building</label><input id="manual-building" name="building" type="text" maxlength="255" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                            <div><label for="manual-room" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Room</label><input id="manual-room" name="room" type="text" maxlength="255" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="manual-recipient-type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Recipient Type <span class="font-normal text-gray-400">(optional)</span></label>
                                <select id="manual-recipient-type" name="manual_recipient_type" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">Select type</option>
                                    <option value="Staff">Staff</option>
                                    <option value="Student">Student</option>
                                    <option value="Visitor">Visitor</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="manual-contact" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Contact / ID Reference <span class="font-normal text-gray-400">(optional)</span></label>
                                <input id="manual-contact" name="manual_contact" type="text" maxlength="255" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="manual-expected-return-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Expected Return <span class="font-normal text-gray-400">(optional)</span></label>
                                <input id="manual-expected-return-date" name="expected_return_date" type="date" min="{{ now()->format('Y-m-d') }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                            <div>
                                <label for="manual-notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Purpose / Notes <span class="font-normal text-gray-400">(optional)</span></label>
                                <input id="manual-notes" name="manual_notes" type="text" maxlength="1000" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button>
                            <x-common.button-spinner text="Issue Item" loadingText="Issuing..." class="text-white" />
                        </div>
                    </form>
                </div>
            </x-modals.base-modal>
        </div>

        {{-- KPI Summary --}}
        @php
            $transactionKpiMetrics = [
                ['title' => 'Total Assigned Items', 'value' => number_format($totalAssignedCount), 'subtitle' => 'Items currently assigned', 'icon' => 'user-check', 'iconClass' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400', 'accentClass' => 'border-l-indigo-500'],
                ['title' => 'Pending Requests', 'value' => number_format($pendingRequestsCount), 'subtitle' => 'Awaiting custodian action', 'icon' => 'clipboard-list', 'iconClass' => $pendingRequestsCount > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400', 'accentClass' => $pendingRequestsCount > 0 ? 'border-l-amber-500' : 'border-l-gray-400'],
                ['title' => 'Overdue Returns', 'value' => number_format($overdueReturnsCount), 'subtitle' => 'Items past their return date', 'icon' => 'alert-triangle', 'iconClass' => $overdueReturnsCount > 0 ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400', 'accentClass' => $overdueReturnsCount > 0 ? 'border-l-red-500' : 'border-l-gray-400'],
                ['title' => 'Total Transactions', 'value' => number_format($totalTransactionsCount), 'subtitle' => 'Recorded inventory movements', 'icon' => 'file-text', 'iconClass' => 'bg-blue-50 text-brand-600 dark:bg-blue-500/10 dark:text-brand-400', 'accentClass' => 'border-l-brand-500'],
            ];
        @endphp
        <x-cards.kpi-summary :metrics="$transactionKpiMetrics" />

        {{-- Main Management Card with Tabs --}}
        <div class="grid gap-6 xl:grid-cols-12 mt-6">
            <div class="col-span-12 xl:col-span-12">
                <x-cards.base-card title="Manage Transactions & Requisitions" subtitle="Process incoming end-user requests and view historical transaction records">
                    
                    <div x-data="{ activeTab: '{{ $pendingRequestsCount > 0 ? 'requests' : 'history' }}', searchRequests: '', searchHistory: '', normalizeSearch(value) { return value.toLowerCase().trim() }, matchesTransaction(row) { const query = this.normalizeSearch(this.searchHistory); return !query || row.dataset.search.includes(query) }, hasTransactionMatches() { const query = this.normalizeSearch(this.searchHistory); return query !== '' && [...this.$refs.transactionRows.querySelectorAll('tr[data-search]')].some(row => this.matchesTransaction(row)) } }">
                        
                        {{-- Tab Navigation Bar --}}
                        <div class="mb-5 flex border-b border-gray-200 dark:border-gray-700">
                            <button type="button"
                                @click="activeTab = 'requests'"
                                :class="activeTab === 'requests' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm transition focus:outline-none">
                                <span>Incoming Item Requests</span>
                                @if ($incomingRequests->count() > 0)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                        {{ $incomingRequests->count() }}
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
                                @click="activeTab = 'assignments'"
                                :class="activeTab === 'assignments' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition focus:outline-none">
                                <span>Assignment Requests</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $assignmentRequests->count() }}
                                </span>
                            </button>

                            <button type="button"
                                @click="activeTab = 'transfers'"
                                :class="activeTab === 'transfers' 
                                    ? 'border-brand-500 text-brand-500 font-semibold border-b-2' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                                class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition focus:outline-none">
                                <span>Transfer Requests</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $pendingTransfers->count() }}
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
                                                $itemName = $request->requested_item_name ?? optional($request->item)->item_name ?? 'Unknown item';
                                                $categoryName = $request->requestedCategory?->category_name ?? optional(optional($request->item)->category)->category_name ?? 'General';
                                                $unitName = $request->requested_unit ?? optional($request->item)->unit;
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
                                                    @elseif($request->requested_item_name)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $categoryName }} · {{ $unitName }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $categoryName }}{{ $unitName ? ' · ' . $unitName : '' }}
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
                                                        @if($request->requested_item_name && !$request->item_id && $request->status === 'waiting for approval')
                                                            <x-modals.base-modal title="Review Item Request" subtitle="Select matching inventory records to issue." maxWidth="max-w-2xl">
                                                                <x-slot:trigger>
                                                                    <button type="button" @click="open = true" class="inline-flex items-center gap-1 rounded-md bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-brand-700">
                                                                        Open Request
                                                                    </button>
                                                                </x-slot:trigger>

                                                                <form method="POST" action="{{ route('propertyCustodian.requests.approve', $request->id) }}" class="space-y-4 text-left">
                                                                    @csrf
                                                                    <div class="grid gap-4 md:grid-cols-2">
                                                                        <section class="space-y-3 rounded-md bg-gray-50 p-4 text-sm dark:bg-gray-800">
                                                                            <h4 class="font-semibold text-gray-900 dark:text-white">Request details</h4>
                                                                            <div><span class="text-gray-500 dark:text-gray-400">Requester</span><div class="font-medium text-gray-900 dark:text-white">{{ $requesterName }}{{ $request->user?->email ? ' · ' . $request->user->email : '' }}</div></div>
                                                                            <div><span class="text-gray-500 dark:text-gray-400">Requested item</span><div class="font-medium text-gray-900 dark:text-white">{{ $itemName }} · {{ $categoryName }} · {{ $unitName }}</div></div>
                                                                            <div><span class="text-gray-500 dark:text-gray-400">Quantity</span><div class="font-medium text-gray-900 dark:text-white">{{ $request->quantity }}</div></div>
                                                                            <div><span class="text-gray-500 dark:text-gray-400">Available</span><div class="font-medium text-gray-900 dark:text-white">{{ $totalStock }} {{ \Illuminate\Support\Str::plural($unitName, $totalStock) }}</div></div>
                                                                            @if($request->notes)
                                                                                <div><span class="text-gray-500 dark:text-gray-400">Purpose / notes</span><div class="font-medium text-gray-900 dark:text-white">{{ $request->notes }}</div></div>
                                                                            @endif
                                                                        </section>

                                                                        <fieldset class="min-w-0">
                                                                            <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Select inventory</legend>
                                                                            <div class="max-h-80 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700">
                                                                                @forelse($request->matching_inventory_items as $stockItem)
                                                                                    <label class="flex cursor-pointer items-center gap-3 rounded-md px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                                                        <input type="checkbox" name="inventory_ids[]" value="{{ $stockItem->item_id }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                                                                        <span class="min-w-0">
                                                                                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $stockItem->inventory_item_no ?? 'Inventory #' . $stockItem->item_id }}{{ $stockItem->serial_number ? ' · ' . $stockItem->serial_number : '' }}</span>
                                                                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $stockItem->quantity }} {{ \Illuminate\Support\Str::plural($unitName, $stockItem->quantity) }} available</span>
                                                                                        </span>
                                                                                    </label>
                                                                                @empty
                                                                                    <p class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">No matching available inventory remains.</p>
                                                                                @endforelse
                                                                            </div>
                                                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                                                @if($request->requestedCategory?->requires_serial_number)
                                                                                    Select exactly {{ $request->quantity }} assets.
                                                                                @else
                                                                                    Select records with enough combined stock to fulfill {{ $request->quantity }} {{ \Illuminate\Support\Str::plural($unitName, $request->quantity) }}.
                                                                                @endif
                                                                            </p>
                                                                        </fieldset>
                                                                    </div>

                                                                    <div class="flex justify-end gap-3 pt-2">
                                                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Close</button>
                                                                        <button type="submit" @disabled(!$hasStock || $request->matching_inventory_items->isEmpty()) class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">Assign</button>
                                                                    </div>
                                                                </form>
                                                            </x-modals.base-modal>

                                                            <x-modals.base-modal title="Cancel Request" subtitle="Provide a reason for cancelling this request." maxWidth="max-w-sm">
                                                                <x-slot:trigger>
                                                                    <button type="button" @click="open = true" class="inline-flex items-center gap-1 rounded-md bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-red-700">Cancel Request</button>
                                                                </x-slot:trigger>
                                                                <form method="POST" action="{{ route('propertyCustodian.requests.cancel', $request->id) }}" class="space-y-4 text-left">
                                                                    @csrf
                                                                    <div>
                                                                        <label for="cancellation-reason-{{ $request->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Cancellation reason</label>
                                                                        <textarea id="cancellation-reason-{{ $request->id }}" name="cancellation_reason" rows="3" maxlength="1000" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-red-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea>
                                                                    </div>
                                                                    <div class="flex justify-end gap-3">
                                                                        <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Back</button>
                                                                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Confirm Cancellation</button>
                                                                    </div>
                                                                </form>
                                                            </x-modals.base-modal>
                                                        @elseif(!$request->requested_item_name)
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
                                                                    <x-common.button-spinner text="Confirm Approval" loadingText="Approving..." class="bg-green-600 hover:bg-green-700" />
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
                                                                    <x-common.button-spinner text="Confirm Decline" loadingText="Declining..." class="bg-red-600 hover:bg-red-700" />
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                        @endif
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

                        {{-- TAB 2: ASSIGNMENT REQUESTS CREATED BY THE CUSTODIAN --}}
                        <div x-show="activeTab === 'assignments'" x-cloak>
                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3 text-left">Assign To</th>
                                            <th class="px-4 py-3 text-left">Item</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3">Date Requested</th>
                                            <th class="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse ($assignmentRequests as $assignment)
                                            <tr class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 text-left font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $assignment->targetUser?->full_name ?: ($assignment->targetUser?->username ?? 'Unknown User') }}
                                                </td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ optional($assignment->item)->item_name ?? 'Unknown item' }}</div>
                                                    @if(optional($assignment->item)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $assignment->item->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">{{ optional(optional($assignment->item)->category)->category_name ?? 'General' }}</td>
                                                <td class="px-4 py-4 font-semibold">{{ $assignment->quantity }}</td>
                                                <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">{{ $assignment->requested_at ? $assignment->requested_at->format('M d, Y h:i A') : 'N/A' }}</td>
                                                <td class="px-4 py-4">
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Waiting for End User</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No pending assignment requests.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- PENDING TRANSFERS (peer-to-peer, awaiting custodian approval) --}}
                        <div x-show="activeTab === 'transfers'" x-cloak class="mt-6">
                            <div class="mb-3 flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Pending Peer-to-Peer Transfers</h3>
                            </div>
                            <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
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
                                        @forelse($pendingTransfers as $transfer)
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
                                                                    <x-common.button-spinner text="Confirm Approval" loadingText="Approving..." class="bg-green-600 hover:bg-green-700" />
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
                                                                    <x-common.button-spinner text="Confirm Decline" loadingText="Declining..." class="bg-red-600 hover:bg-red-700" />
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No pending transfer requests.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- TAB 5: PENDING RETURNS --}}
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
                                                                <div class="grid gap-3 sm:grid-cols-2">
                                                                    <div><label for="return-building-{{ $returnRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Storage Building</label><input id="return-building-{{ $returnRequest->id }}" name="building" value="{{ old('building', $returnRequest->building) }}" type="text" maxlength="255" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                                                                    <div><label for="return-room-{{ $returnRequest->id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Storage Room</label><input id="return-room-{{ $returnRequest->id }}" name="room" value="{{ old('room', $returnRequest->room) }}" type="text" maxlength="255" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                                                                </div>
                                                                <div class="flex justify-end gap-3 pt-2">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                    <x-common.button-spinner text="Confirm Approval" loadingText="Approving..." class="bg-green-600 hover:bg-green-700" />
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
                                                                    <x-common.button-spinner text="Confirm Decline" loadingText="Declining..." class="bg-red-600 hover:bg-red-700" />
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
                                <div class="w-full">
                                    <input type="search" x-model="searchHistory" placeholder="Search transactions..." aria-label="Search transaction history" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                </div>
                            </div>

                            <div class="max-h-[32rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                    <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <tr class="text-center">
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3 text-left">Item Name</th>
                                            <th class="px-4 py-3 text-left">From</th>
                                            <th class="px-4 py-3 text-left">To</th>
                                            <th class="px-4 py-3">Transaction Date</th>
                                            <th class="px-4 py-3">Date Returned</th>
                                            <th class="px-4 py-3">Activity</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody x-ref="transactionRows" class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        @forelse($transactions as $transaction)
                                            <tr
                                                x-show="matchesTransaction($el)"
                                                data-search="{{ strtolower(implode(' ', [
                                                    (string) $transaction->quantity,
                                                    optional($transaction->item)->item_name ?? '',
                                                    optional($transaction->item)->inventory_item_no ?? '',
                                                    $transaction->fromUser?->full_name ?? $transaction->fromUser?->username ?? 'Warehouse',
                                                    $transaction->manual_recipient_name ?? $transaction->user?->full_name ?? $transaction->user?->username ?? 'Unknown',
                                                    $transaction->manual_department ?? '',
                                                    $transaction->transaction_date?->format('Y-m-d') ?? '',
                                                    $transaction->return_date?->format('Y-m-d') ?? '',
                                                    $transaction->expected_return_date?->format('Y-m-d') ?? '',
                                                    $transaction->status ?? '',
                                                ])) }}"
                                                class="text-center hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-4 font-semibold">{{ $transaction->quantity }}</td>
                                                <td class="px-4 py-4 text-left">
                                                    <div class="font-medium">{{ optional($transaction->item)->item_name ?? 'Unknown item' }}</div>
                                                    @if(optional($transaction->item)->inventory_item_no)
                                                        <span class="text-xs text-gray-400">{{ $transaction->item->inventory_item_no }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4 text-left">{{ $transaction->fromUser?->full_name ?: ($transaction->fromUser?->username ?? 'Warehouse') }}</td>
                                                <td class="px-4 py-4 text-left">
                                                    <div>{{ $transaction->manual_recipient_name ?? $transaction->user?->full_name ?: ($transaction->user?->username ?? 'Unknown') }}</div>
                                                    @if($transaction->manual_recipient_name)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $transaction->manual_department }}{{ $transaction->manual_recipient_type ? ' · ' . $transaction->manual_recipient_type : '' }}</span>
                                                        @if($transaction->manual_contact)
                                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $transaction->manual_contact }}</span>
                                                        @endif
                                                    @endif
                                                </td>
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
                                                    @if($transaction->manual_recipient_name && $transaction->expected_return_date && $transaction->status === 'assigned')
                                                        <x-modals.base-modal title="Record Manual Return" subtitle="Confirm that this item has been received back." maxWidth="max-w-sm">
                                                            <x-slot:trigger>
                                                                <button type="button" @click="open = true" class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Record Return</button>
                                                            </x-slot:trigger>
                                                            <form method="POST" action="{{ route('propertyCustodian.transactions.manual-return', $transaction->id) }}" class="space-y-4 text-left">
                                                                @csrf
                                                                <p class="text-sm text-gray-600 dark:text-gray-300">Return {{ $transaction->item?->item_name ?? 'the issued item' }} from {{ $transaction->manual_recipient_name }}?</p>
                                                                <div>
                                                                    <label for="manual-return-notes-{{ $transaction->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Return notes <span class="font-normal text-gray-400">(optional)</span></label>
                                                                    <textarea id="manual-return-notes-{{ $transaction->id }}" name="notes" rows="2" maxlength="1000" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea>
                                                                </div>
                                                                <div class="flex justify-end gap-3">
                                                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Back</button>
                                                                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Confirm Return</button>
                                                                </div>
                                                            </form>
                                                        </x-modals.base-modal>
                                                    @else
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                    No transactions found.
                                                </td>
                                            </tr>
                                        @endforelse
                                        @if($transactions->isNotEmpty())
                                            <tr x-show="normalizeSearch(searchHistory) !== '' && !hasTransactionMatches()" x-cloak>
                                                <td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                                    Transaction not found.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </x-cards.base-card>
            </div>
        </div>
    @endsection
