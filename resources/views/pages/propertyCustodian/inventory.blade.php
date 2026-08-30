@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory" />

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                    label="Total Inventory Units"
                    value="{{ number_format($inventoryMetrics['total']) }}"
                    subtitle="Non-disposed inventory"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M4 3a1 1 0 00-1 1v2a1 1 0 001 1h1v8a1 1 0 001 1h8a1 1 0 001-1V7h1a1 1 0 001-1V4a1 1 0 00-1-1H4z'/><path d='M5 7V5h10V7H5z'/></svg>"
            >
                    <span class="text-sm text-gray-500 dark:text-gray-400">Live inventory quantity.</span>
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                    label="Available Units"
                    value="{{ number_format($inventoryMetrics['available']) }}"
                    subtitle="Ready for assignment"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm1-11V5a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0V9h2a1 1 0 100-2h-2z' clip-rule='evenodd'/></svg>"
                tone="positive"
            >
                    Ready to be assigned.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                    label="Assigned Units"
                    value="{{ number_format($inventoryMetrics['assigned']) }}"
                    subtitle="Currently assigned"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M10 2a8 8 0 100 16 8 8 0 000-16zm1 9H9V7a1 1 0 112 0v4z'/></svg>"
                    tone="neutral"
            >
                    Currently in use.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                    label="Items Needing Attention"
                    value="{{ number_format($inventoryMetrics['attention']) }}"
                    subtitle="Inspection or maintenance"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M9.049 2.927a1 1 0 011.902 0l.518 2.128a8.04 8.04 0 013.054 1.771l1.996-.39a1 1 0 011.149 1.254l-.901 2.93a8.072 8.072 0 010 2.356l.9 2.929a1 1 0 01-1.15 1.255l-1.995-.39a8.04 8.04 0 01-3.056 1.772l-.516 2.128a1 1 0 01-1.902 0l-.517-2.128a8.04 8.04 0 01-3.055-1.772l-1.996.39a1 1 0 01-1.15-1.255l.901-2.93a8.072 8.072 0 010-2.356l-.9-2.929a1 1 0 011.15-1.255l1.995.39a8.04 8.04 0 013.055-1.771l.517-2.128z'/></svg>"
                tone="neutral"
            >
                    {{ $inventoryMetrics['attention'] > 0 ? 'Review these inventory items.' : 'No items need attention.' }}
            </x-cards.metric-card>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-12">
            <x-cards.base-card title="Manage Inventory" subtitle="Search and manage assigned assets">
                <div x-data="{
                    search: '',
                    category: '',
                    scanOpen: false,
                    scanProcessing: false,
                    scanFile: null,
                    scanPreview: '',
                    extractedItems: [],
                    chooseScanFile(event) {
                        const file = event.target.files[0];
                        if (!file || !file.type.startsWith('image/')) return;
                        this.scanFile = file;
                        this.scanPreview = URL.createObjectURL(file);
                        this.extractedItems = [];
                    },
                    processScan() {
                        if (!this.scanFile) return;
                        this.scanProcessing = true;
                        setTimeout(() => {
                            this.extractedItems = [{
                                item_name: this.scanFile.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' '),
                                category_id: '',
                                unit: 'pcs',
                                unit_cost: '0.00',
                                date_acquired: new Date().toISOString().slice(0, 10),
                                quantity: 1,
                                serial_numbers: []
                            }];
                            this.scanProcessing = false;
                        }, 1200);
                    },
                    addScanItem() {
                        this.extractedItems.push({ item_name: '', category_id: '', unit: 'pcs', unit_cost: '0.00', date_acquired: new Date().toISOString().slice(0, 10), quantity: 1, serialized: false, serial_numbers: [] });
                    },
                    removeScanItem(index) {
                        this.extractedItems.splice(index, 1);
                    },
                    fillSingleForm() {
                        const item = this.extractedItems[0];
                        const form = document.querySelector('[data-stock-in-form]');
                        if (!item || !form) return;
                        ['item_name', 'unit', 'unit_cost', 'date_acquired', 'quantity'].forEach((field) => {
                            const input = form.elements[field];
                            if (input) input.value = item[field] ?? '';
                        });
                        const categoryInput = form.elements.category_id;
                        if (categoryInput) {
                            categoryInput.value = item.category_id;
                            categoryInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        document.getElementById('stock-in-trigger')?.click();
                        this.scanOpen = false;
                    },
                    async saveAllScan() {
                        if (!this.extractedItems.length) return;
                        this.scanProcessing = true;
                        for (const item of this.extractedItems) {
                            const formData = new FormData();
                            formData.append('_token', '{{ csrf_token() }}');
                            Object.entries(item).forEach(([key, value]) => {
                                if (key !== 'serial_numbers') formData.append(key, value ?? '');
                            });
                            item.serial_numbers.forEach((serial, index) => formData.append(`serial_numbers[${index}]`, serial));
                            await fetch('{{ route('propertyCustodian.inventory.stock-in') }}', { method: 'POST', body: formData });
                        }
                        window.location.reload();
                    },
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
                        <button type="button" @click="scanOpen = true" class="inline-flex items-center gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300" title="Scan an inventory document">
                            <i data-lucide="scan-line" class="h-4 w-4" aria-hidden="true"></i>
                            AI Document Scan
                        </button>
                        <x-modals.base-modal title="Stock In Item" subtitle="Add an item to the inventory." maxWidth="max-w-2xl" :open="$errors->any()">
                            <x-slot:trigger>
                                <button id="stock-in-trigger" type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 5a1 1 0 0 1 1 1v3h3a1 1 0 1 1 0 2h-3v3a1 1 0 1 1-2 0v-3H6a1 1 0 1 1 0-2h3V6a1 1 0 0 1 1-1Z" clip-rule="evenodd" />
                                    </svg>
                                    Stock In
                                </button>
                            </x-slot:trigger>

                            @php($selectedCategory = $categories->firstWhere('category_id', (int) old('category_id')))
                            <form data-stock-in-form x-data="{ quantity: {{ old('quantity', 1) }}, requiresSerialNumber: {{ $selectedCategory?->requires_serial_number ? 'true' : 'false' }}, serialNumbers: @js(old('serial_numbers', [])), setQuantity(value) { const count = Math.max(1, Math.min(100, Number(value) || 1)); this.quantity = count; this.serialNumbers = Array.from({ length: count }, (_, index) => this.serialNumbers[index] ?? ''); }, setCategory(event) { this.requiresSerialNumber = event.target.selectedOptions[0]?.dataset.requiresSerialNumber === 'true'; if (this.requiresSerialNumber) { this.setQuantity(this.quantity); } else { this.serialNumbers = []; } } }" method="POST" action="{{ route('propertyCustodian.inventory.stock-in') }}" class="space-y-4">
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
                        <div x-show="scanOpen" x-cloak x-transition class="fixed inset-0 z-[1400] flex items-center justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true">
                            <div
                                :style="window.innerWidth >= 1280 ? { marginLeft: $store.sidebar.isExpanded ? '240px' : '90px', width: 'calc(100% - ' + ($store.sidebar.isExpanded ? '240px' : '90px') + ')' } : {}"
                                class="my-8 flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900">
                                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                                    <div><h2 class="text-base font-semibold text-gray-900 dark:text-white">AI Document Scan</h2><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Upload and verify inventory details before saving.</p></div>
                                    <button type="button" @click="scanOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Close document scan"><i data-lucide="x" class="h-5 w-5"></i></button>
                                </div>
                                <div class="flex-1 overflow-y-auto p-5">
                                    <div x-show="!scanFile" @dragover.prevent @drop.prevent="const file = $event.dataTransfer.files[0]; if (file) { scanFile = file; scanPreview = URL.createObjectURL(file); }" class="flex min-h-48 flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center dark:border-gray-700 dark:bg-gray-950"><i data-lucide="upload-cloud" class="h-8 w-8 text-brand-500"></i><p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">Drop an inventory image here</p><label class="mt-4 cursor-pointer rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Choose image<input type="file" accept="image/*" class="hidden" @change="chooseScanFile($event)"></label></div>
                                    <div x-show="scanFile && !extractedItems.length && !scanProcessing" x-cloak class="mt-5 grid gap-5 md:grid-cols-[minmax(0,1fr)_14rem]"><img :src="scanPreview" alt="Document preview" class="max-h-72 w-full rounded-xl border border-gray-200 bg-gray-50 object-contain dark:border-gray-800 dark:bg-gray-950"><div class="flex flex-col justify-between"><p class="break-all text-sm text-gray-600 dark:text-gray-300" x-text="scanFile?.name"></p><button type="button" @click="processScan()" class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="scan-line" class="h-4 w-4"></i>Process document</button></div></div>
                                    <div x-show="scanProcessing" x-cloak class="mt-5 space-y-3"><div class="h-4 w-40 animate-pulse rounded bg-gray-200 dark:bg-gray-800"></div><div class="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"></div><div class="h-12 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"></div><div class="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800"><div class="h-full w-2/3 animate-pulse rounded-full bg-brand-500"></div></div><p class="text-xs text-gray-500">Reading document fields...</p></div>
                                    <div x-show="extractedItems.length && !scanProcessing" x-cloak class="mt-5"><div class="mb-4 flex items-center justify-between"><div><h3 class="text-sm font-semibold text-gray-900 dark:text-white">Verify extracted items</h3><p class="mt-1 text-xs text-gray-500">Edit fields before saving.</p></div><button type="button" @click="addScanItem()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-700 dark:text-gray-200"><i data-lucide="plus" class="h-4 w-4"></i>Add item</button></div><div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"><table class="min-w-[950px] w-full text-left text-xs"><thead class="bg-gray-50 uppercase text-gray-500 dark:bg-gray-800"><tr><th class="p-3">Item name</th><th class="p-3">Category</th><th class="p-3">Unit</th><th class="p-3">Unit cost</th><th class="p-3">Date</th><th class="p-3">Qty</th><th class="p-3">Serial numbers</th><th class="p-3"></th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><template x-for="(item, index) in extractedItems" :key="index"><tr class="align-top"><td class="p-2"><input x-model="item.item_name" class="w-36 rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td><td class="p-2"><select x-model="item.category_id" @change="item.serialized = $event.target.selectedOptions[0]?.dataset.serialized === 'true'; item.serial_numbers = item.serialized ? Array.from({ length: item.quantity }, (_, serialIndex) => item.serial_numbers[serialIndex] || '') : []" class="w-32 rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Select</option>@foreach ($categories as $category)<option value="{{ $category->category_id }}" data-serialized="{{ $category->requires_serial_number ? 'true' : 'false' }}">{{ $category->category_name }}</option>@endforeach</select></td><td class="p-2"><input x-model="item.unit" class="w-16 rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td><td class="p-2"><input x-model="item.unit_cost" type="number" min="0" step=".01" class="w-20 rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td><td class="p-2"><input x-model="item.date_acquired" type="date" class="rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td><td class="p-2"><input x-model.number="item.quantity" min="1" type="number" @change="if (item.serialized) item.serial_numbers = Array.from({ length: item.quantity }, (_, serialIndex) => item.serial_numbers[serialIndex] || '')" class="w-14 rounded border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"></td><td class="p-2"><div class="flex max-w-56 flex-wrap gap-1"><template x-for="(serial, serialIndex) in item.serial_numbers" :key="serialIndex"><span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 dark:bg-gray-800"><input x-model="item.serial_numbers[serialIndex]" class="w-16 border-0 bg-transparent p-0 text-[10px] dark:text-white"><button type="button" @click="item.serial_numbers.splice(serialIndex, 1)" aria-label="Remove serial">&times;</button></span></template><button type="button" @click="item.serial_numbers.push('')" class="rounded-full border border-dashed px-2 py-1 text-[10px] text-gray-500">+ serial</button></div></td><td class="p-2"><button type="button" @click="removeScanItem(index)" class="text-gray-400 hover:text-red-500" aria-label="Remove item"><i data-lucide="trash-2" class="h-4 w-4"></i></button></td></tr></template></tbody></table></div></div>
                                </div>
                                <div x-show="extractedItems.length && !scanProcessing" x-cloak class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-800 dark:bg-gray-950"><button type="button" @click="fillSingleForm()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"><i data-lucide="file-input" class="h-4 w-4"></i>Fill Single Form</button><button type="button" @click="saveAllScan()" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="save-all" class="h-4 w-4"></i>Save All to Inventory</button></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="max-h-[21rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
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
                                        <div x-data="{ actionOpen: false, modalOpen: false, editOpen: false, deleteOpen: false, returnOpen: false }" @click.outside="actionOpen = false" class="relative">
                                            <button type="button" @click="actionOpen = !actionOpen" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Actions for {{ $inventoryItem->item_name }}" :aria-expanded="actionOpen.toString()">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M5 10a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm6.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm6.5 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                                </svg>
                                            </button>

                                            <div x-show="actionOpen" x-cloak x-transition @click.outside="actionOpen = false" @keydown.escape.window="actionOpen = false" class="absolute right-0 top-full z-[1200] mt-1 w-36 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                                <button type="button" @click="actionOpen = false; modalOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Show Items
                                                </button>
                                                <button type="button" @click="actionOpen = false; editOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Edit
                                                </button>
                                                @if($firstSourceItem->status === 'assigned')
                                                    <button type="button" @click="actionOpen = false; returnOpen = true" class="block w-full px-3 py-2 text-left text-sm text-green-600 transition hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20">
                                                        Receive Return
                                                    </button>
                                                @endif
                                                <button type="button" @click="actionOpen = false; deleteOpen = true" class="block w-full px-3 py-2 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                    Delete
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
                                                                            <td class="px-4 py-3"></td>                                                                        
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                </div>
                                            </div>

                                            <div x-show="editOpen" x-cloak x-transition @keydown.escape.window="editOpen = false" class="fixed inset-0 z-[1300] flex items-center justify-center overflow-y-auto bg-black/50 px-4 py-6" role="dialog" aria-modal="true">
                                                <div class="ml-4 w-full max-w-3xl rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                    <div class="mb-5 flex items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Inventory Item</h3>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the inventory record details.</p>
                                                        </div>
                                                        <button type="button" @click="editOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close edit inventory modal">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 1 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 1 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.update', $firstSourceItem) }}" class="space-y-5">
                                                        @csrf
                                                        @method('PATCH')
                                                        <div class="grid gap-5 md:grid-cols-2">
                                                            <div>
                                                                <label for="edit-item-name-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                                                                <input id="edit-item-name-{{ $firstSourceItem->item_id }}" name="item_name" value="{{ $firstSourceItem->item_name }}" type="text" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-category-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                                                <select id="edit-category-{{ $firstSourceItem->item_id }}" name="category_id" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                                    @foreach ($categories as $category)
                                                                        <option value="{{ $category->category_id }}" @selected($firstSourceItem->category_id == $category->category_id)>{{ $category->category_name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label for="edit-unit-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label>
                                                                <input id="edit-unit-{{ $firstSourceItem->item_id }}" name="unit" value="{{ $firstSourceItem->unit }}" type="text" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-quantity-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                                                <input id="edit-quantity-{{ $firstSourceItem->item_id }}" name="quantity" value="{{ $firstSourceItem->quantity }}" type="number" min="1" max="100" required @disabled($firstSourceItem->status === 'assigned') class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:disabled:bg-gray-900" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-unit-cost-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label>
                                                                <input id="edit-unit-cost-{{ $firstSourceItem->item_id }}" name="unit_cost" value="{{ $firstSourceItem->unit_cost }}" type="number" min="0" step="0.01" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-date-acquired-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label>
                                                                <input id="edit-date-acquired-{{ $firstSourceItem->item_id }}" name="date_acquired" value="{{ optional($firstSourceItem->date_acquired)->format('Y-m-d') }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-ics-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ICS No.</label>
                                                                <input id="edit-ics-{{ $firstSourceItem->item_id }}" name="ics_no" value="{{ $firstSourceItem->ics_no }}" type="text" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                            <div>
                                                                <label for="edit-description-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                                                <input id="edit-description-{{ $firstSourceItem->item_id }}" name="description" value="{{ $firstSourceItem->description }}" type="text" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                            </div>
                                                        </div>
                                                        <div class="flex justify-end gap-3 pt-2">
                                                            <button type="button" @click="editOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                            <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                            <div x-show="deleteOpen" x-cloak x-transition @keydown.escape.window="deleteOpen = false" class="fixed inset-0 z-[1300] flex items-center justify-center bg-black/50 px-4" role="dialog" aria-modal="true">
                                                <div class="ml-4 w-full max-w-md rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Inventory Item</h3>
                                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This action affects only the first source record for <strong>{{ $firstSourceItem->item_name }}</strong>.</p>
                                                    <div class="mt-6 flex justify-end gap-3">
                                                        <button type="button" @click="deleteOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                        <form method="POST" action="{{ route('propertyCustodian.inventory.destroy', $firstSourceItem) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            @if($firstSourceItem->status === 'assigned')
                                                <div x-show="returnOpen" x-cloak x-transition @keydown.escape.window="returnOpen = false" class="fixed inset-0 z-[1300] flex items-center justify-center bg-black/50 px-4" role="dialog" aria-modal="true">
                                                    <div class="ml-4 w-full max-w-md rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Receive Item Return</h3>
                                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Mark <strong>{{ $firstSourceItem->item_name }}</strong> as returned from the end user.</p>
                                                        <form method="POST" action="{{ route('propertyCustodian.inventory.mark-returned', $firstSourceItem->item_id) }}" class="mt-4 space-y-4">
                                                            @csrf
                                                            <div>
                                                                <label for="return-notes-{{ $firstSourceItem->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes (Optional)</label>
                                                                <textarea id="return-notes-{{ $firstSourceItem->item_id }}" name="notes" rows="3" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Any additional notes about this return..."></textarea>
                                                            </div>
                                                            <div class="flex justify-end gap-3 pt-2">
                                                                <button type="button" @click="returnOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
                                                                <button type="submit" class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Receive Return</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
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
