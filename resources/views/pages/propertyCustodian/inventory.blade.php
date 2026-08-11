@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory" />

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
            <x-cards.base-card title="Manage Inventory" subtitle="Search and manage assigned assets">
                <div x-data="{
                    search: '',
                    category: '',
                    items: @js($inventoryItems->map(fn ($item) => [
                        'categoryId' => (string) $item->category_id,
                        'searchable' => \Illuminate\Support\Str::lower(implode(' ', [$item->item_name, $item->unit, $item->category?->category_name ?? '', $item->ics_no ?? ''])),
                    ])->values()),
                    matches(item) {
                        return item.searchable.includes(this.search.toLowerCase()) && (!this.category || item.categoryId === this.category);
                    },
                    hasMatches() {
                        return this.items.some((item) => this.matches(item));
                    },
                }">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex-1">
                        <input x-model="search" type="search" placeholder="Search assets" aria-label="Search assets" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <select x-model="category" aria-label="Filter by category" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                        <x-modals.base-modal title="Stock In Item" subtitle="Add an item to the inventory." maxWidth="max-w-2xl" :open="$errors->any()">
                            <x-slot:trigger>
                                <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                                    </svg>
                                    Stock In
                                </button>
                            </x-slot:trigger>

                            @php($selectedCategory = $categories->firstWhere('category_id', (int) old('category_id')))
                            <form x-data="{ quantity: {{ old('quantity', 1) }}, requiresSerialNumber: {{ $selectedCategory?->requires_serial_number ? 'true' : 'false' }}, serialNumbers: @js(old('serial_numbers', [])), setQuantity(value) { const count = Math.max(1, Math.min(100, Number(value) || 1)); this.quantity = count; this.serialNumbers = Array.from({ length: count }, (_, index) => this.serialNumbers[index] ?? ''); }, setCategory(event) { this.requiresSerialNumber = event.target.selectedOptions[0]?.dataset.requiresSerialNumber === 'true'; if (this.requiresSerialNumber) { this.setQuantity(this.quantity); } else { this.serialNumbers = []; } } }" method="POST" action="{{ route('propertyCustodian.inventory.stock-in') }}" class="space-y-4">
                                @csrf

                                @if ($errors->any())
                                    <div class="rounded-md border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                                        Please correct the highlighted fields and try again.
                                    </div>
                                @endif

                                <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_14rem]">
                                    <div class="space-y-4">
                                        <div>
                                            <label for="item-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                                            <input id="item-name" name="item_name" value="{{ old('item_name') }}" type="text" placeholder="Enter item name" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                        </div>
                                        <div>
                                            <label for="category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                            <select id="category" name="category_id" @change="setCategory($event)" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                <option value="">Select a category</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->category_id }}" data-requires-serial-number="{{ $category->requires_serial_number ? 'true' : 'false' }}" @selected(old('category_id') == $category->category_id)>{{ $category->category_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="item-description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Description</label>
                                            <input id="item-description" name="description" value="{{ old('description') }}" type="text" placeholder="Enter item description" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label for="ics-number" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ICS No.</label>
                                                <input id="ics-number" name="ics_no" value="{{ old('ics_no') }}" type="text" placeholder="Enter ICS number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                            <div>
                                                <label for="unit" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label>
                                                <input id="unit" name="unit" value="{{ old('unit') }}" type="text" placeholder="e.g. piece" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                        </div>
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label for="unit-cost" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label>
                                                <input id="unit-cost" name="unit_cost" value="{{ old('unit_cost') }}" type="number" min="0" step="0.01" placeholder="0.00" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                            <div>
                                                <label for="date-acquired" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label>
                                                <input id="date-acquired" name="date_acquired" value="{{ old('date_acquired') }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                        </div>
                                    </div>

                                    <aside :class="requiresSerialNumber && quantity >= 4 ? 'max-h-[24rem] overflow-y-auto' : ''" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                                        <label for="quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input id="quantity" name="quantity" x-model.number="quantity" @input="setQuantity($event.target.value)" type="number" min="1" max="100" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />

                                        <template x-if="requiresSerialNumber">
                                            <div class="mt-4 space-y-3">
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Serial Numbers</p>
                                                <template x-for="index in quantity" :key="index">
                                                    <div>
                                                        <label :for="`serial-number-${index}`" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" x-text="`Serial Number ${index}`"></label>
                                                        <input :id="`serial-number-${index}`" :name="`serial_numbers[${index - 1}]`" x-model="serialNumbers[index - 1]" type="text" placeholder="Enter serial number" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <p x-show="!requiresSerialNumber" class="mt-4 text-xs text-gray-500 dark:text-gray-400">Serial numbers are not required for the selected category.</p>
                                    </aside>
                                </div>
                                <div class="flex justify-end gap-3 pt-2">
                                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                    <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Save Item</button>
                                </div>
                            </form>
                        </x-modals.base-modal>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">ICS No.</th>
                                <th class="px-4 py-3">Unit Cost</th>
                                <th class="px-4 py-3">Total Cost</th>
                                <th class="px-4 py-3">Date Acquired</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @forelse ($inventoryItems as $inventoryItem)
                                @php($firstSourceItem = $inventoryItem->sourceItems->first())
                                <tr data-category-id="{{ $inventoryItem->category_id }}" data-search="{{ \Illuminate\Support\Str::lower(implode(' ', [$inventoryItem->item_name, $inventoryItem->unit, $inventoryItem->category?->category_name ?? '', $inventoryItem->ics_no ?? ''])) }}" x-show="matches({ categoryId: $el.dataset.categoryId, searchable: $el.dataset.search })">
                                    <td class="px-4 py-3">{{ $inventoryItem->quantity }}</td>
                                    <td class="px-4 py-3">{{ $inventoryItem->unit }}</td>
                                    <td class="px-4 py-3">{{ $inventoryItem->category?->category_name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $inventoryItem->item_name }}</td>
                                    <td class="px-4 py-3">{{ $inventoryItem->ics_no ?: '—' }}</td>
                                    <td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->unit_cost, 2) }}</td>
                                    <td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->total_cost, 2) }}</td>
                                    <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($inventoryItem->date_acquired)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3">
                                        <div x-data="{ actionOpen: false, modalOpen: false, menuStyle: '', toggleMenu(event) { if (!this.actionOpen) { const rect = event.currentTarget.getBoundingClientRect(); this.menuStyle = `top: ${rect.bottom + 4}px; left: ${rect.right - 144}px;`; } this.actionOpen = !this.actionOpen; } }">
                                            <button type="button" @click="toggleMenu($event)" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Actions for {{ $inventoryItem->item_name }}" :aria-expanded="actionOpen.toString()">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M5 10a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm6.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm6.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                                </svg>
                                            </button>

                                            <div x-show="actionOpen" x-cloak x-transition @click.outside="actionOpen = false" @keydown.escape.window="actionOpen = false" :style="menuStyle" class="fixed z-[1200] w-36 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                                <button type="button" @click="actionOpen = false; modalOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Show Items
                                                </button>
                                            </div>

                                            <div x-show="modalOpen" x-cloak x-transition @keydown.escape.window="modalOpen = false" class="fixed inset-0 z-[1300] flex items-center justify-center bg-black/5 px-4" role="dialog" aria-modal="true">
                                                <div class="ml-4 w-full max-w-4xl rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700">
                                                    <div class="mb-4 flex items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $inventoryItem->item_name }} Items</h3>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Individual records included in this inventory group.</p>
                                                        </div>
                                                        <button type="button" @click="modalOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close item list">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                                                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                                <tr class="text-center">
                                                                    <th class="px-4 py-3">Qty</th>
                                                                    <th class="px-4 py-3">Unit Cost</th>
                                                                    <th class="px-4 py-3">ICS No.</th>
                                                                    <th class="px-4 py-3">Serial No.</th>
                                                                    <th class="px-4 py-3">Inventory Item No.</th>
                                                                    <th class="px-4 py-3">Date Acquired</th>
                                                                    <th class="px-4 py-3">Assigned To</th>
                                                                    <th class="px-4 py-3">Status</th>
                                                                    <th class="px-4 py-3">QR Code</th>
                                                                </tr>
                                                            </thead>





                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                                @foreach ($inventoryItem->sourceItems as $sourceItem)
                                                                    <tr class="text-center">
                                                                        <td class="px-4 py-3">{{ $sourceItem->quantity }}</td>
                                                                        <td class="px-4 py-3">₱{{ number_format((float) $sourceItem->unit_cost, 2) }}</td>
                                                                        <td class="px-4 py-3">{{ $sourceItem->ics_no ?: '—' }}</td>
                                                                        <td class="px-4 py-3">{{ $sourceItem->serial_number ?: '—' }}</td>
                                                                        <td class="px-4 py-3">{{ $firstSourceItem?->inventory_item_no ?? 'N/A' }}</td>
                                                                        <td class="px-4 py-3">{{ $sourceItem->date_acquired->format('M d, Y') }}</td>
                                                                        <td class="px-4 py-3">{{ $firstSourceItem?->assignedTo?->name ?? 'Unassigned' }}</td>
                                                                        <td class="px-4 py-3">
                                                                            @if ($sourceItem->status === 'available')
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-800/30 dark:text-green-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Available
                                                                                </span>
                                                                            @elseif ($sourceItem->status === 'assigned')
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-800/30 dark:text-yellow-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Assigned
                                                                                </span>
                                                                            @elseif ($sourceItem->status === 'under_inspection')
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800 dark:bg-blue-800/30 dark:text-blue-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Under Inspection
                                                                                </span>
                                                                            @elseif ($sourceItem->status === 'under_maintenance')
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-800 dark:bg-purple-800/30 dark:text-purple-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Under Maintenance
                                                                                </span>
                                                                            @elseif ($sourceItem->status === 'disposed')
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800 dark:bg-red-800/30 dark:text-red-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Disposed
                                                                                </span>
                                                                            @else
                                                                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800/30 dark:text-gray-200">
                                                                                    <svg class="h-2 w-2 fill-current" viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="3" /></svg>
                                                                                    Unknown
                                                                                </span>
                                                                            @endif
                                                                            </td>
                                                                            <td class="px-4 py-3"></td>                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No inventory items have been stocked in yet.</td>
                                </tr>
                            @endforelse
                            <tr x-show="items.length && !hasMatches()" x-cloak>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
