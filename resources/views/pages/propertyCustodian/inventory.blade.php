@extends('layouts.app', ['title' => 'Inventory'])

@php
    $statusLabels = [
        'all' => 'All Inventory', 'available' => 'Available', 'assigned' => 'Assigned',
        'under_maintenance' => 'Under Maintenance', 'under_inspection' => 'Under Inspection',
        'ready_to_dispose' => 'Ready to Dispose', 'disposed' => 'Disposed',
    ];
    $statusCollections = collect(['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'])
        ->mapWithKeys(fn ($status) => [$status => $inventoryByStatus->get($status, collect())]);
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory" />

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card label="Total Inventory Units" value="{{ number_format($inventoryMetrics['total']) }}" subtitle="Non-disposed inventory" />
        <x-cards.metric-card label="Available Units" value="{{ number_format($inventoryMetrics['available']) }}" subtitle="Ready for assignment" tone="positive" />
        <x-cards.metric-card label="Assigned Units" value="{{ number_format($inventoryMetrics['assigned']) }}" subtitle="Currently assigned" />
        <x-cards.metric-card label="Items Needing Attention" value="{{ number_format($inventoryMetrics['attention']) }}" subtitle="Inspection or maintenance" />
    </div>

    <div class="mt-6">
        <x-cards.base-card title="Inventory Workspaces" subtitle="Manage each inventory lifecycle stage in its own workspace.">
            <div x-data="{
                activeTab: 'all', allSearch: '', allCategory: '', availableSearch: '', assignedSearch: '',
                underMaintenanceSearch: '', underInspectionSearch: '', readyToDisposeSearch: '', disposedSearch: '',
                scanOpen: false, scanFile: null, extractedName: '', maintenanceOpen: false, maintenanceItemId: '', maintenanceBase: @js(url('/property-custodian/inventory')),
                openMaintenance(itemId) { this.maintenanceItemId = String(itemId); this.maintenanceOpen = true; },
                chooseScanFile(event) { const file = event.target.files[0]; if (!file || !file.type.startsWith('image/')) return; this.scanFile = file; this.extractedName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' '); },
                matches(search, row, filterCategory = 'false') { const query = (search || '').toLowerCase().trim(); return (!query || row.dataset.search.includes(query)) && (filterCategory !== 'true' || !this.allCategory || row.dataset.category === this.allCategory); },
                hasMatches(search, panel, filterCategory = 'false') { return [...document.querySelectorAll('[data-inventory-panel]')].filter((row) => row.dataset.inventoryPanel === panel).some((row) => this.matches(search, row, filterCategory)); }
            }">
                <div class="mb-5 overflow-x-auto border-b border-gray-200 dark:border-gray-700">
                    <div class="flex min-w-max gap-1" role="tablist" aria-label="Inventory workspaces">
                        @foreach ($statusLabels as $tab => $label)
                            @php($count = $tab === 'all' ? $inventoryItems->where('status', '!=', 'disposed')->sum('quantity') : $inventoryStatusCounts->get($tab, 0))
                            <button type="button" role="tab" @click="activeTab = '{{ $tab }}'" :aria-selected="activeTab === '{{ $tab }}'" :class="activeTab === '{{ $tab }}' ? 'border-brand-500 text-brand-500 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="border-b-2 px-4 py-3 text-sm transition">
                                {{ $label }}
                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ number_format($count) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div x-show="activeTab === 'all'" x-cloak>
                    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <input x-model="allSearch" type="search" placeholder="Search the asset catalogue" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 lg:max-w-md" />
                        <div class="flex flex-wrap gap-2">
                            <select x-model="allCategory" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">All categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                            <button type="button" @click="scanOpen = true" class="inline-flex items-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                                <i data-lucide="scan-line" class="h-4 w-4"></i>
                                AI Document Scan
                            </button>

                            <x-modals.base-modal :open="$errors->any()" title="Stock In Item" subtitle="Add an item to available inventory." maxWidth="max-w-2xl">
                                <x-slot:trigger><button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="plus" class="h-4 w-4"></i>Stock In</button></x-slot:trigger>
                                @php($selectedCategory = $categories->firstWhere('category_id', (int) old('category_id')))
                                <form x-data="{ quantity: {{ old('quantity', 1) }}, requiresSerialNumber: {{ $selectedCategory?->requires_serial_number ? 'true' : 'false' }}, serialNumbers: @js(old('serial_numbers', [])), setQuantity(value) { const count = Math.max(1, Math.min(100, Number(value) || 1)); this.quantity = count; this.serialNumbers = Array.from({ length: count }, (_, index) => this.serialNumbers[index] ?? ''); }, setCategory(event) { this.requiresSerialNumber = event.target.selectedOptions[0]?.dataset.requiresSerialNumber === 'true'; if (this.requiresSerialNumber) this.setQuantity(this.quantity); else this.serialNumbers = []; } }" method="POST" action="{{ route('propertyCustodian.inventory.stock-in') }}" class="space-y-5">@csrf
                                    @if ($errors->any())
                                        <div class="rounded-md border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">Please correct the highlighted fields and try again.</div>
                                    @endif

                                    <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_14rem]">
                                        <div class="space-y-4">
                                            <div><label for="stock-in-item-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label><input id="stock-in-item-name" name="item_name" value="{{ old('item_name') }}" type="text" placeholder="Enter item name" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                                            <div><label for="stock-in-category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label><select id="stock-in-category" name="category_id" @change="setCategory($event)" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"><option value="">Select a category</option>@foreach ($categories as $category)<option value="{{ $category->category_id }}" data-requires-serial-number="{{ $category->requires_serial_number ? 'true' : 'false' }}" @selected(old('category_id') == $category->category_id)>{{ $category->category_name }}</option>@endforeach</select></div>
                                            <div><label for="stock-in-description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Description</label><input id="stock-in-description" name="description" value="{{ old('description') }}" type="text" placeholder="Enter item description" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                                            <div class="grid gap-4 sm:grid-cols-2"><div><label for="stock-in-ics" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ICS No.</label><input id="stock-in-ics" name="ics_no" value="{{ old('ics_no') }}" type="text" placeholder="Enter ICS number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="stock-in-unit" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label><input id="stock-in-unit" name="unit" value="{{ old('unit') }}" type="text" placeholder="e.g. piece" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div>
                                            <div class="grid gap-4 sm:grid-cols-2"><div><label for="stock-in-cost" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label><input id="stock-in-cost" name="unit_cost" value="{{ old('unit_cost') }}" type="number" min="0" step="0.01" placeholder="0.00" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="stock-in-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label><input id="stock-in-date" name="date_acquired" value="{{ old('date_acquired') }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div>
                                        </div>

                                        <aside :class="requiresSerialNumber && quantity >= 4 ? 'max-h-[24rem] overflow-y-auto' : ''" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"><label for="stock-in-quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label><input id="stock-in-quantity" name="quantity" x-model.number="quantity" @input="setQuantity($event.target.value)" type="number" min="1" max="100" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /><template x-if="requiresSerialNumber"><div class="mt-4 space-y-3"><p class="text-sm font-medium text-gray-700 dark:text-gray-300">Serial Numbers</p><template x-for="index in quantity" :key="index"><div><label :for="`serial-number-${index}`" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" x-text="`Serial Number ${index}`"></label><input :id="`serial-number-${index}`" :name="`serial_numbers[${index - 1}]`" x-model="serialNumbers[index - 1]" type="text" placeholder="Enter serial number" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></template></div></template><p x-show="!requiresSerialNumber" class="mt-4 text-xs text-gray-500 dark:text-gray-400">Serial numbers are not required for the selected category.</p></aside>
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2"><button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button><x-common.button-spinner text="Save Item" loadingText="Saving..." class="text-white" /></div>
                                </form>
                            </x-modals.base-modal>
                        </div>
                    </div>
                    <div class="max-h-[32rem] overflow-y-auto overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                            <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Unit / ICS</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Unit cost</th><th class="px-4 py-3">Total cost</th><th class="px-4 py-3">Date acquired</th><th class="px-4 py-3">Actions</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($inventoryItems->where('status', '!=', 'disposed')->take(10) as $inventoryItem)
                                    @php($source = $inventoryItem->sourceItems->first())
                                    @php($searchable = strtolower(implode(' ', [$inventoryItem->item_name, $inventoryItem->unit, $inventoryItem->category?->category_name ?? '', $inventoryItem->ics_no ?? ''])))
                                    <tr x-show="matches(allSearch, $el, 'true')" data-inventory-panel="all" data-search="{{ $searchable }}" data-category="{{ $inventoryItem->category_id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60"><td class="px-4 py-3"><div class="font-medium text-gray-900 dark:text-white">{{ $inventoryItem->item_name }}</div></td><td class="px-4 py-3">{{ $inventoryItem->category?->category_name ?? 'Uncategorized' }}</td><td class="px-4 py-3 font-semibold">{{ number_format($inventoryItem->quantity) }}</td><td class="px-4 py-3">{{ $inventoryItem->unit }}<br><span class="text-xs text-gray-400">{{ $inventoryItem->ics_no ?: 'No ICS' }}</span></td><td class="px-4 py-3"><span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ str_replace('_', ' ', $inventoryItem->status) }}</span></td><td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->unit_cost, 2) }}</td><td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->total_cost, 2) }}</td><td class="px-4 py-3">{{ optional($inventoryItem->date_acquired)->format('M d, Y') }}</td><td class="px-4 py-3"><div class="flex flex-wrap gap-2"><x-modals.base-modal title="Edit Inventory Item" subtitle="Update this available item." maxWidth="max-w-2xl"><x-slot:trigger><button type="button" @click="open = true" class="rounded-md border px-2.5 py-1.5 text-xs">Edit</button></x-slot:trigger><form method="POST" action="{{ route('propertyCustodian.inventory.update', $source?->item_id) }}" class="space-y-5">@csrf @method('PATCH')@if ($errors->any())<div class="rounded-md border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">Please correct the highlighted fields and try again.</div>@endif<div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_14rem]"><div class="space-y-4"><div><label for="edit-item-name-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label><input id="edit-item-name-{{ $source?->item_id }}" name="item_name" value="{{ old('item_name', $inventoryItem->item_name) }}" type="text" placeholder="Enter item name" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="edit-category-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label><select id="edit-category-{{ $source?->item_id }}" name="category_id" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">@foreach ($categories as $category)<option value="{{ $category->category_id }}" @selected(old('category_id', $inventoryItem->category_id) == $category->category_id)>{{ $category->category_name }}</option>@endforeach</select></div><div><label for="edit-description-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Description</label><input id="edit-description-{{ $source?->item_id }}" name="description" value="{{ old('description', $inventoryItem->description) }}" type="text" placeholder="Enter item description" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div class="grid gap-4 sm:grid-cols-2"><div><label for="edit-ics-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ICS No.</label><input id="edit-ics-{{ $source?->item_id }}" name="ics_no" value="{{ old('ics_no', $inventoryItem->ics_no) }}" type="text" placeholder="Enter ICS number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="edit-unit-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label><input id="edit-unit-{{ $source?->item_id }}" name="unit" value="{{ old('unit', $inventoryItem->unit) }}" type="text" placeholder="e.g. piece" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label for="edit-cost-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label><input id="edit-cost-{{ $source?->item_id }}" name="unit_cost" value="{{ old('unit_cost', $inventoryItem->unit_cost) }}" type="number" min="0" step="0.01" placeholder="0.00" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="edit-date-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label><input id="edit-date-{{ $source?->item_id }}" name="date_acquired" value="{{ old('date_acquired', optional($inventoryItem->date_acquired)->format('Y-m-d')) }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div></div><aside class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50"><label for="edit-quantity-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label><input id="edit-quantity-{{ $source?->item_id }}" name="quantity" value="{{ old('quantity', $inventoryItem->quantity) }}" type="number" min="1" max="100" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /><p class="mt-4 text-xs text-gray-500 dark:text-gray-400">For assigned items, quantity updates are restricted.</p></aside></div><div class="flex justify-end gap-3 pt-2"><button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button><x-common.button-spinner text="Save Changes" loadingText="Saving..." class="text-white" /></div></form></x-modals.base-modal><form method="POST" action="{{ route('propertyCustodian.inventory.destroy', $source?->item_id) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs text-red-700">Delete</button></form></div></td></tr>
                                @empty
                                    <tr><td colspan="9" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No inventory records found.</td></tr>
                                @endforelse
                                @if ($inventoryItems->where('status', '!=', 'disposed')->isNotEmpty())
                                    <tr x-show="!hasMatches(allSearch, 'all', 'true')" x-cloak><td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                @foreach ($statusCollections as $status => $rows)
                    @php($searchModel = match ($status) { 'under_maintenance' => 'underMaintenanceSearch', 'under_inspection' => 'underInspectionSearch', 'ready_to_dispose' => 'readyToDisposeSearch', default => $status . 'Search' })
                    <div x-show="activeTab === '{{ $status }}'" x-cloak>
                        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $statusLabels[$status] }} workspace</h3><p class="text-sm text-gray-500 dark:text-gray-400">{{ $status === 'available' ? 'Stock ready for controlled assignment.' : ($status === 'disposed' ? 'Historical records are read-only.' : 'Review records at this lifecycle stage.') }}</p></div><input x-model="{{ $searchModel }}" type="search" placeholder="Search {{ strtolower($statusLabels[$status]) }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:w-72" /></div>
                        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                            <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    <tr>
                                    @if ($status === 'assigned')
                                        <th class="px-4 py-3">Assignee</th>
                                        <th class="px-4 py-3">Assigned date
                                        </th>
                                    @endif

                                    @if (in_array($status, ['under_maintenance', 'under_inspection', 'ready_to_dispose'], true))
                                        <th class="px-4 py-3">Workflow detail</th>
                                    @endif

                                    @if ($status === 'disposed')
                                        <th class="px-4 py-3">Disposal record</th>
                                    @endif

                                        <th class="px-4 py-3">Asset</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Qty</th>
                                        <th class="px-4 py-3">Unit / ICS</th>
                                        <th class="px-4 py-3">Unit cost</th>
                                        <th class="px-4 py-3">Total cost</th>
                                        <th class="px-4 py-3">Date acquired</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($rows as $inventoryItem)
                                        @php($source = $inventoryItem->sourceItems->first())
                                        @php($searchable = strtolower(implode(' ', [$inventoryItem->item_name, $inventoryItem->unit, $inventoryItem->category?->category_name ?? '', $inventoryItem->ics_no ?? ''])))
                                        <tr x-show="matches({{ $searchModel }}, $el)" data-inventory-panel="{{ $status }}" data-search="{{ $searchable }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                            @if ($status === 'assigned')
                                                <td class="px-4 py-3">{{ $source?->assignedTo?->full_name ?? 'Recorded assignee' }}</td>
                                                <td class="px-4 py-3">{{ $source?->latestAssignment?->transaction_date?->format('M d, Y') ?? 'N/A' }}</td>
                                            @endif

                                            @if (in_array($status, ['under_maintenance', 'under_inspection', 'ready_to_dispose'], true))
                                                <td class="max-w-xs px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $source?->latestMaintenance?->issue_description ?? $source?->disposalMovement?->notes ?? 'Awaiting review' }}</td>
                                            @endif

                                            @if ($status === 'disposed')
                                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $source?->disposalMovement?->created_at?->format('M d, Y') ?? 'N/A' }}<br>{{ $source?->disposalMovement?->notes ?? 'No reason recorded' }}</td>
                                            @endif

                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $inventoryItem->item_name }}</div>
                
                                            </td>
                                            <td class="px-4 py-3">{{ $inventoryItem->category?->category_name ?? 'Uncategorized' }}</td>
                                            <td class="px-4 py-3 font-semibold">{{ number_format($inventoryItem->quantity) }}</td>
                                            <td class="px-4 py-3">{{ $inventoryItem->unit }}<br>
                                                <span class="text-xs text-gray-400">{{ $inventoryItem->ics_no ?: 'No ICS' }}</span>
                                            </td>
                                            <td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->unit_cost, 2) }}</td>
                                            <td class="px-4 py-3">₱{{ number_format((float) $inventoryItem->total_cost, 2) }}</td>
                                            <td class="px-4 py-3">{{ optional($inventoryItem->date_acquired)->format('M d, Y') }}</td>
                                            <td class="px-4 py-3"><div x-data="{ repairOpen: false }" class="flex flex-wrap gap-2">
                                                @if ($status === 'available')
                                                    <a href="{{ route('propertyCustodian.transactions') }}" class="rounded-md bg-brand-500 px-2.5 py-1.5 text-xs font-medium text-white">Assign</a>
                                                    @if ($inventoryItem->category?->is_maintenance_eligible === false)
                                                        <button type="button" disabled title="This category is not eligible for maintenance." class="cursor-not-allowed rounded-md bg-orange-500 px-2.5 py-1.5 text-xs font-medium text-white opacity-50">Send to Maintenance</button>
                                                    @else
                                                        <button type="button" @click="openMaintenance('{{ $source?->item_id }}')" title="Send this item to maintenance" class="rounded-md bg-orange-500 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-orange-600">Send to Maintenance</button>
                                                    @endif
                                                @elseif ($status === 'assigned')<form method="POST" action="{{ route('propertyCustodian.inventory.mark-returned', $source?->item_id) }}">@csrf<button class="rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white">Receive return</button></form><a href="{{ route('propertyCustodian.transactions') }}" class="rounded-md border px-2.5 py-1.5 text-xs">Transfer</a>
                                                @elseif ($status === 'under_maintenance')
                                                    <button type="button" @click="repairOpen = true" class="rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-700">Mark as Repaired</button>
                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.mark-ready-to-dispose', $source?->item_id) }}">@csrf<input type="hidden" name="notes" value="Marked for disposal after maintenance review"><button class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs text-red-700">Ready to dispose</button></form>
                                                    <div x-show="repairOpen" x-cloak x-transition class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" @keydown.escape.window="repairOpen = false">
                                                        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 text-left shadow-2xl dark:border-slate-700 dark:bg-slate-900" @click.outside="repairOpen = false">
                                                            <div class="mb-5 flex items-start justify-between gap-3"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Mark as Repaired</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Return this item to available inventory after recording the repair.</p></div><button type="button" @click="repairOpen = false" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Close repair modal"><i data-lucide="x" class="h-5 w-5"></i></button></div>
                                                            <form method="POST" action="{{ route('propertyCustodian.inventory.mark-repaired', $source?->item_id) }}" class="space-y-4">
                                                                @csrf
                                                                <div><label for="repair-notes-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Repair notes</label><textarea id="repair-notes-{{ $source?->item_id }}" name="repair_notes" rows="4" maxlength="1000" placeholder="Describe the repair performed" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea></div>
                                                                <div><label for="repair-cost-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Repair cost</label><input id="repair-cost-{{ $source?->item_id }}" name="maintenance_cost" type="number" min="0" step="0.01" placeholder="0.00" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                                                                <div class="flex justify-end gap-3"><button type="button" @click="repairOpen = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button><x-common.button-spinner text="Mark as Repaired" loadingText="Saving..." class="bg-green-600 text-white hover:bg-green-700" /></div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                @elseif ($status === 'under_inspection')<span class="rounded-md border border-blue-200 px-2.5 py-1.5 text-xs text-blue-700">Review outcome pending</span>
                                                @elseif ($status === 'ready_to_dispose')<form method="POST" action="{{ route('propertyCustodian.inventory.dispose', $source?->item_id) }}" onsubmit="return confirm('Dispose this item? This action cannot be undone.')">@csrf<button class="rounded-md bg-red-700 px-2.5 py-1.5 text-xs text-white">Dispose</button></form>
                                                @else<span class="text-xs text-gray-500">Read-only archive</span>@endif
                                            </div></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="12" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No {{ strtolower($statusLabels[$status]) }} records found.</td></tr>
                                    @endforelse
                                    @if ($rows->isNotEmpty())
                                        <tr x-show="!hasMatches({{ $searchModel }}, '{{ $status }}')" x-cloak><td colspan="12" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No items found.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                <div x-show="maintenanceOpen" x-cloak x-transition class="fixed inset-0 z-[1200] flex items-center justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" @keydown.escape.window="maintenanceOpen = false">
                    <div class="my-8 w-full max-w-lg rounded-lg border border-slate-200 bg-white p-6 text-left shadow-2xl dark:border-slate-700 dark:bg-slate-900" @click.outside="maintenanceOpen = false">
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Send to Maintenance</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the item and describe the issue before sending it for repair.</p></div>
                            <button type="button" @click="maintenanceOpen = false" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Close maintenance modal"><i data-lucide="x" class="h-5 w-5"></i></button>
                        </div>
                        <form method="POST" :action="`${maintenanceBase}/${maintenanceItemId}/send-to-maintenance`" class="space-y-5">
                            @csrf
                            <fieldset>
                                <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Select item</legend>
                                <div class="max-h-52 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700">
                                    @php($availableItems = $statusCollections->get('available', collect())->filter(fn ($availableItem) => $availableItem->category?->is_maintenance_eligible !== false)->flatMap(fn ($availableItem) => $availableItem->sourceItems->map(fn ($sourceItem) => [$availableItem, $sourceItem])))
                                    @forelse ($availableItems as [$availableItem, $availableSource])
                                        <label class="flex cursor-pointer items-start gap-3 rounded-md border border-transparent px-3 py-2 hover:border-orange-300 hover:bg-orange-50 dark:hover:border-orange-500/40 dark:hover:bg-orange-500/10">
                                            <input type="radio" name="maintenance_item_choice" value="{{ $availableSource->item_id }}" x-model="maintenanceItemId" required class="mt-1 border-gray-300 text-orange-500 focus:ring-orange-500">
                                            <span><span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $availableItem->item_name }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $availableSource->inventory_item_no ?? 'No inventory number' }} · {{ $availableSource->serial_number ?? 'No serial number' }} · {{ number_format($availableSource->quantity) }} {{ $availableSource->unit }}</span></span>
                                        </label>
                                    @empty
                                        <p class="px-3 py-4 text-sm text-gray-500">No available items found.</p>
                                    @endforelse
                                </div>
                            </fieldset>
                            <div><label for="maintenance-issue-shared" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Issue description</label><textarea id="maintenance-issue-shared" name="issue_description" rows="4" required maxlength="1000" placeholder="Describe the issue" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea></div>
                            <div class="flex justify-end gap-3"><button type="button" @click="maintenanceOpen = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button><x-common.button-spinner text="Send to Maintenance" loadingText="Sending..." class="bg-orange-500 text-white hover:bg-orange-600" /></div>
                        </form>
                    </div>
                </div>

                <div x-show="scanOpen" x-cloak class="fixed inset-0 z-[1400] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
                    <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">AI Document Scan</h2>
                            <button type="button" @click="scanOpen = false" aria-label="Close scan">
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Upload an image to prepare stock-in details for verification.</p>
                        <input type="file" accept="image/*" @change="chooseScanFile($event)" class="mt-5 block w-full text-sm" />
                        <div x-show="scanFile" x-cloak class="mt-4 rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                            Detected item name: <strong x-text="extractedName"></strong>
                            <p class="mt-1 text-xs text-gray-500">Open Stock In to verify and submit all required inventory fields.</p>
                        </div>
                        <div class="mt-6 flex justify-end gap-2">
                            <button type="button" @click="scanOpen = false" class="rounded-md border px-4 py-2 text-sm">Close</button>
                            <button type="button" @click="scanOpen = false" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white">Continue to Stock In</button>
                        </div>
                    </div>
                </div>
            </div>
        </x-cards.base-card>
    </div>
@endsection
