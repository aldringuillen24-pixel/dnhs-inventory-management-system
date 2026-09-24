@extends('layouts.app', ['title' => 'Inventory'])

@php
    $statusLabels = [
        'all' => 'All Inventory',
        'available' => 'Available',
        'assigned' => 'Assigned',
        'under_maintenance' => 'Under Maintenance',
        'under_inspection' => 'Under Inspection',
        'ready_to_dispose' => 'Ready to Dispose',
        'disposed' => 'Disposed',
    ];
    $allInventoryItems = $allInventoryPage->getCollection();
    $statusCollections = $inventoryPages->mapWithKeys(fn ($page, $status) => [$status => $page->getCollection()]);
    $attentionTotal = ($inventoryStatusCounts['under_maintenance'] ?? 0)
        + ($inventoryStatusCounts['under_inspection'] ?? 0)
        + ($inventoryStatusCounts['ready_to_dispose'] ?? 0);
@endphp

@section('content')
    <div x-data="{
        activeTab: @js($activeWorkspace),
        allSearch: @js($inventoryFilters['search']),
        allCategory: @js($inventoryFilters['category']),
        allStatus: @js($inventoryFilters['status']),
        allCondition: @js($inventoryFilters['condition']),
        submittedSearch: @js($inventoryFilters['search']),
        submittedCategory: @js($inventoryFilters['category']),
        availableSearch: '',
        assignedSearch: '',
        underMaintenanceSearch: '',
        underInspectionSearch: '',
        readyToDisposeSearch: '',
        disposedSearch: '',
        scanOpen: false,
        scanFile: null,
        extractedName: '',
        maintenanceItemId: '',
        maintenanceBase: @js(url('/property-custodian/inventory')),
        repairItemId: '',
        repairBase: @js(url('/property-custodian/inventory')),
        openMaintenance(itemId) {
            this.maintenanceItemId = String(itemId);
            this.$dispatch('open-modal', 'send-maintenance');
        },
        openRepairModal(itemId) {
            this.repairItemId = String(itemId);
            this.$dispatch('open-modal', 'repair-modal');
        },
        applyFilters() {
            const params = new URLSearchParams();
            const search = (this.allSearch || '').trim();
            this.submittedSearch = search;
            this.submittedCategory = this.allCategory || '';
            params.set('workspace', 'all');
            if (search) params.set('search', search);
            if (this.allCategory) params.set('category', this.allCategory);
            if (this.allStatus) params.set('status', this.allStatus);
            if (this.allCondition) params.set('condition', this.allCondition);
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },
        switchWorkspace(workspace) {
            const params = new URLSearchParams(window.location.search);
            params.set('workspace', workspace);
            params.delete('all_page');
            params.delete('status');
            params.delete('condition');
            ['available', 'assigned', 'under_maintenance', 'under_inspection', 'ready_to_dispose', 'disposed'].forEach((status) => params.delete(`${status}_page`));
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },
        init() {
            const searchInput = [...this.$root.querySelectorAll('input')].find((input) => input.placeholder && input.placeholder.includes('Search inventory'));
            searchInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.applyFilters();
                }
            });
        },
        chooseScanFile(event) {
            const file = event.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            this.scanFile = file;
            this.extractedName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
        },
        matches(search, row, filterCategory = 'false') {
            const queryValue = filterCategory === 'true' ? this.submittedSearch : search;
            const categoryValue = filterCategory === 'true' ? this.submittedCategory : this.allCategory;
            const query = (queryValue || '').toLowerCase().trim();
            return (!query || row.dataset.search.includes(query)) && (filterCategory !== 'true' || !categoryValue || row.dataset.category === categoryValue);
        },
        hasMatches(search, panel, filterCategory = 'false') {
            return [...document.querySelectorAll('[data-inventory-panel]')].filter((row) => row.dataset.inventoryPanel === panel).some((row) => this.matches(search, row, filterCategory));
        },
        qrScannerMode: 'scan',
        qrResult: null,
        qrError: null,
        qrLoading: false,
        qrManualToken: '',
        qrCameraActive: false,
        _html5QrScanner: null,
        openQrScanner() {
            this.qrScannerMode = 'scan';
            this.qrResult = null;
            this.qrError = null;
            this.qrManualToken = '';
            this.qrCameraActive = false;
            this.$dispatch('open-modal', 'qr-scanner-modal');
        },
        closeQrScanner() {
            this.stopCamera();
            this.$dispatch('close-modal', 'qr-scanner-modal');
            this.resetQrScannerState();
        },
        resetQrScannerState() {
            this.qrScannerMode = 'scan';
            this.qrResult = null;
            this.qrError = null;
            this.qrLoading = false;
        },
        handleQrModalOpened() {
            this.$nextTick(() => this.startCamera());
        },
        handleQrModalClosed() {
            this.stopCamera();
            this.resetQrScannerState();
        },
        scanAnotherQrCode() {
            this.qrScannerMode = 'scan';
            this.qrResult = null;
            this.qrError = null;
            this.qrManualToken = '';
            this.$nextTick(() => this.startCamera());
        },
        async startCamera() {
            this.stopCamera();
            if (typeof Html5Qrcode === 'undefined') {
                this.qrError = 'Camera scanner library failed to load. Use the manual token field below.';
                return;
            }
            try {
                const scanner = new Html5Qrcode('qr-camera-viewport');
                this._html5QrScanner = scanner;
                this.qrCameraActive = true;
                await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } }, (decodedText) => {
                    this.stopCamera();
                    this.lookupQrToken(decodedText.trim());
                }, () => {});
            } catch (err) {
                this.qrCameraActive = false;
                this.qrError = 'Could not start camera: ' + (err.message || err) + '. Use the manual token field instead.';
            }
        },
        stopCamera() {
            if (this._html5QrScanner) {
                try { this._html5QrScanner.stop().catch(() => {}); } catch (e) {}
                this._html5QrScanner = null;
            }
            this.qrCameraActive = false;
        },
        async lookupQrToken(token) {
            const t = (token || '').trim();
            if (!t) {
                this.qrError = 'Please enter a QR token.';
                return;
            }
            this.qrLoading = true;
            this.qrResult = null;
            this.qrError = null;
            try {
                const url = @js(route('propertyCustodian.inventory.qr.lookup'));
                const res = await fetch(`${url}?token=${encodeURIComponent(t)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.found) {
                    this.stopCamera();
                    this.qrResult = data;
                    this.qrScannerMode = 'result';
                } else {
                    this.qrError = data.message || 'Item not found.';
                }
            } catch (e) {
                this.qrError = 'Network error. Please try again.';
            } finally {
                this.qrLoading = false;
            }
        },
        openScannedItemDetails() {
            if (!this.qrResult) return;

            const item = this.qrResult;
            const quantity = Number(item.quantity ?? 0);
            const unitCost = Number(item.unit_cost ?? 0);
            const formatAmount = (amount) => amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            this.closeQrScanner();
            this.openViewModal({
                ...item,
                status: (item.status || '').replace(/_/g, ' '),
                quantity,
                unit: item.unit || 'unit',
                unit_cost: formatAmount(unitCost),
                total_cost: formatAmount(quantity * unitCost),
                ics_no: item.ics_no || 'Not recorded',
                date_acquired: item.date_acquired || 'Not recorded',
                expected_end_date: item.expected_end_date || 'Not recorded',
                lifespan_years: item.lifespan_years ?? 'N/A',
                lifespan_status: (item.lifespan_status || 'Not tracked').replace(/_/g, ' '),
                assigned_to: item.assigned_to?.name || 'None (In stockroom)',
                serial_numbers: item.serial_number ? [item.serial_number] : [],
                qr_code: true,
            });
        },

        /* View Modal */
        viewModalItem: null,
        openViewModal(itemData) {
            this.viewModalItem = itemData;
            this.$dispatch('open-modal', 'item-view-modal');
        },

        /* Print QR Modal */
        printModalOpen: false,
        printModalLoading: false,
        printModalError: null,
        printModalActiveItem: null,
        printModalItems: [],
        printModalSelectedIds: [],

        async openPrintModal(itemId) {
            this.printModalOpen = true;
            this.$dispatch('open-modal', 'print-qr-modal');
            this.printModalLoading = true;
            this.printModalError = null;
            this.printModalActiveItem = null;
            this.printModalItems = [];
            this.printModalSelectedIds = [];

            if (!itemId) {
                this.printModalError = 'Unable to print this label because the inventory item could not be identified.';
                this.printModalLoading = false;
                return;
            }

            try {
                const url = `${this.maintenanceBase}/${itemId}/print-qr`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || data.error || `Failed to load item QR information (HTTP ${res.status}).`);
                }
                this.printModalActiveItem = data.activeItem;
                this.printModalItems = data.items || [data.activeItem];
                this.printModalSelectedIds = this.printModalItems.map(i => i.item_id);

                this.$nextTick(() => {
                    this.renderPrintModalQr();
                });
            } catch (err) {
                this.printModalError = err.message || 'Error loading QR data.';
            } finally {
                this.printModalLoading = false;
            }
        },

        closePrintModal() {
            this.printModalOpen = false;
            this.printModalActiveItem = null;
            this.printModalItems = [];
            this.printModalSelectedIds = [];
        },

        selectPrintUnit(item) {
            this.printModalActiveItem = item;
            this.$nextTick(() => {
                this.renderPrintModalQr();
            });
        },

        renderPrintModalQr() {
            const canvas = document.getElementById('qr-modal-preview-canvas');
            if (!canvas || !this.printModalActiveItem || !this.printModalActiveItem.qr_code) return;

            if (typeof QRCode !== 'undefined') {
                QRCode.toCanvas(canvas, this.printModalActiveItem.qr_code, {
                    width: 140,
                    margin: 1,
                    color: { dark: '#1d4ed8', light: '#ffffff' }
                });
            }
        },

        isAllPrintUnitsSelected() {
            return this.printModalItems.length > 0 && this.printModalSelectedIds.length === this.printModalItems.length;
        },

        toggleSelectAllPrintUnits(checked) {
            if (checked) {
                this.printModalSelectedIds = this.printModalItems.map(i => i.item_id);
            } else {
                this.printModalSelectedIds = [];
            }
        },

        togglePrintUnitSelection(id) {
            const idx = this.printModalSelectedIds.indexOf(id);
            if (idx > -1) {
                this.printModalSelectedIds.splice(idx, 1);
            } else {
                this.printModalSelectedIds.push(id);
            }
        },

        getSelectedPrintUnits() {
            return this.printModalItems.filter(i => this.printModalSelectedIds.includes(i.item_id));
        },

        printCurrentModalLabel() {
            if (this.printModalActiveItem) {
                window.dnhsPrintStickers([this.printModalActiveItem]);
            }
        },

        printBatchModalLabels() {
            const selected = this.getSelectedPrintUnits();
            if (selected.length > 0) {
                window.dnhsPrintStickers(selected);
            }
        }
    }" x-init="init()" class="space-y-5">

        {{-- 1. PAGE HEADER --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Manage stock, assignments, maintenance, and disposal records.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" @click="openQrScanner()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i data-lucide="scan-line" class="h-4 w-4 text-gray-500 dark:text-gray-400"></i>
                    <span>Scan QR</span>
                </button>
                <button type="button" @click="$dispatch('open-modal', 'stock-in-modal')" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-medium text-white shadow-xs hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    <span>Stock In</span>
                </button>
                <a href="{{ route('propertyCustodian.inventory.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i data-lucide="download" class="h-4 w-4 text-gray-500 dark:text-gray-400"></i>
                    <span>Export</span>
                </a>
            </div>
        </div>

        {{-- 2. KPI CARD --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="grid md:grid-cols-4">
            {{-- Total Inventory Units --}}
            <div class="flex items-center gap-4 p-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-600 dark:bg-blue-500/10 dark:text-brand-400">
                    <i data-lucide="package" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Inventory Units</p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($inventoryMetrics['total']) }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Non-disposed inventory</p>
                </div>
            </div>

            {{-- Available Units --}}
            <div class="flex items-center gap-4 border-t border-gray-200 p-4 dark:border-gray-800 md:border-l md:border-t-0">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <i data-lucide="package-check" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Available Units</p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($inventoryMetrics['available']) }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Ready for assignment</p>
                </div>
            </div>

            {{-- Assigned Units --}}
            <div class="flex items-center gap-4 border-t border-gray-200 p-4 dark:border-gray-800 md:border-l md:border-t-0">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                    <i data-lucide="user-check" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Assigned Units</p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($inventoryMetrics['assigned']) }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Currently assigned</p>
                </div>
            </div>

            {{-- Items Needing Attention --}}
            <div class="flex items-center gap-4 border-t border-gray-200 p-4 dark:border-gray-800 md:border-l md:border-t-0">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Items Needing Attention</p>
                    <p class="mt-0.5 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($inventoryMetrics['attention']) }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Inspection or maintenance</p>
                </div>
            </div>
        </div>
        </div>

        {{-- 3. STATUS NAVIGATION & 4. FILTER TOOLBAR CARD --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
            {{-- Horizontal Status Tab Row --}}
            <div class="flex flex-wrap items-center justify-between border-b border-gray-200 px-4 pt-2 dark:border-gray-800 sm:px-6">
                <div class="flex items-center gap-1 py-1" role="tablist" aria-label="Inventory status tabs">
                    {{-- All --}}
                    <button type="button" @click="switchWorkspace('all')"
                        :class="activeTab === 'all' ? 'border-brand-500 text-brand-600 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm transition-colors whitespace-nowrap">
                        <span>All</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ number_format($activeWorkspace === 'all' ? $allInventoryPage->total() : $inventoryStatusCounts->sum()) }}
                        </span>
                    </button>

                    {{-- Available --}}
                    <button type="button" @click="switchWorkspace('available')"
                        :class="activeTab === 'available' ? 'border-brand-500 text-brand-600 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm transition-colors whitespace-nowrap">
                        <span>Available</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            {{ number_format($inventoryStatusCounts->get('available', 0)) }}
                        </span>
                    </button>

                    {{-- Assigned --}}
                    <button type="button" @click="switchWorkspace('assigned')"
                        :class="activeTab === 'assigned' ? 'border-brand-500 text-brand-600 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm transition-colors whitespace-nowrap">
                        <span>Assigned</span>
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                            {{ number_format($inventoryStatusCounts->get('assigned', 0)) }}
                        </span>
                    </button>

                    {{-- Attention Dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" @click.outside="open = false"
                            :class="['under_maintenance', 'under_inspection', 'ready_to_dispose'].includes(activeTab) ? 'border-brand-500 text-brand-600 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                            class="inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm transition-colors whitespace-nowrap">
                            <span>Attention</span>
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                {{ number_format($attentionTotal) }}
                            </span>
                            <i data-lucide="chevron-down" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" x-cloak
                            class="absolute left-0 z-30 mt-1 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <button type="button" @click="open = false; switchWorkspace('under_maintenance')"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                <span>Under Maintenance</span>
                                <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ number_format($inventoryStatusCounts->get('under_maintenance', 0)) }}
                                </span>
                            </button>
                            <button type="button" @click="open = false; switchWorkspace('under_inspection')"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                <span>Under Inspection</span>
                                <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ number_format($inventoryStatusCounts->get('under_inspection', 0)) }}
                                </span>
                            </button>
                            <button type="button" @click="open = false; switchWorkspace('ready_to_dispose')"
                                class="flex w-full items-center justify-between px-4 py-2 text-left text-xs font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50">
                                <span>Ready to Dispose</span>
                                <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ number_format($inventoryStatusCounts->get('ready_to_dispose', 0)) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Disposed --}}
                    <button type="button" @click="switchWorkspace('disposed')"
                        :class="activeTab === 'disposed' ? 'border-brand-500 text-brand-600 dark:text-brand-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="inline-flex items-center gap-2 border-b-2 px-3.5 py-2.5 text-sm transition-colors whitespace-nowrap">
                        <span>Disposed</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ number_format($inventoryStatusCounts->get('disposed', 0)) }}
                        </span>
                    </button>
                </div>
            </div>

            {{-- 4. FILTER TOOLBAR (Single horizontal bar) --}}
            <div class="p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Search --}}
                    <div class="relative min-w-[200px] flex-1 sm:max-w-xs">
                        <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                        <input x-model="allSearch" type="search" placeholder="Search inventory..."
                            @keydown.enter.prevent="applyFilters()"
                            class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>

                    {{-- Category --}}
                    <div class="min-w-[140px]">
                        <select x-model="allCategory"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Status --}}
                    <div class="min-w-[130px]">
                        <select x-model="allStatus"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="assigned">Assigned</option>
                            <option value="under_maintenance">Under Maintenance</option>
                            <option value="under_inspection">Under Inspection</option>
                            <option value="ready_to_dispose">Ready to Dispose</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>

                    {{-- Condition --}}
                    <div class="min-w-[130px]">
                        <select x-model="allCondition"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">All Conditions</option>
                            <option value="good">Good Condition</option>
                            <option value="fair">Fair Condition</option>
                            <option value="poor">Needs Repair / Damaged</option>
                        </select>
                    </div>

                    {{-- Buttons --}}
                    <button type="button" @click="applyFilters()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-brand-600 focus:outline-none">
                        <span>Apply</span>
                    </button>
                    <button type="button" @click="allSearch = ''; allCategory = ''; allStatus = ''; allCondition = ''; applyFilters()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        <span>Clear</span>
                    </button>
                </div>
            </div>

            {{-- 5. INVENTORY TABLE (Dominant Section) --}}
            {{-- ALL WORKSPACE TABLE --}}
            <div x-show="activeTab === 'all'" x-cloak class="px-4 pb-4 sm:px-5 sm:pb-5">
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between bg-gray-50/70 px-4 py-3 dark:bg-gray-800/40 sm:px-6">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Inventory Records</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Showing {{ $allInventoryPage->firstItem() ?? 0 }}–{{ $allInventoryPage->lastItem() ?? 0 }} of {{ $allInventoryPage->total() }} records
                    </p>
                </div>

                <div class="max-h-[36rem] overflow-y-auto overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-800 dark:text-gray-200">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Asset</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Unit / ICS</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Unit Cost</th>
                                <th class="px-4 py-3">Total Cost</th>
                                <th class="px-4 py-3">Date Acquired</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($allInventoryItems as $inventoryItem)
                                @php($source = ($inventoryItem->sourceItems ?? collect())->first() ?: \App\Models\Inventory::find($inventoryItem->source_item_id))
                                @php($editSerialNumbers = ($inventoryItem->sourceItems ?? collect())->pluck('serial_number')->filter()->values())
                                @if ($editSerialNumbers->isEmpty() && $source)
                                    @php($editSerialNumbers = collect($editItemDetails->get($source->item_id, [])['serialNumbers'] ?? []))
                                @endif
                                @php($searchable = strtolower(implode(' ', [$inventoryItem->item_name, $inventoryItem->unit, $inventoryItem->category?->category_name ?? '', $inventoryItem->ics_no ?? ''])))

                                <tr x-show="matches(allSearch, $el, 'true')" data-inventory-panel="all" data-search="{{ $searchable }}" data-category="{{ $inventoryItem->category_id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-800/60 transition-colors">
                                    {{-- Asset --}}
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $inventoryItem->item_name }}</div>
                                        @if ($inventoryItem->expected_end_date)
                                            <div class="mt-1.5 max-w-xs text-xs">
                                                <div class="flex items-center justify-between gap-2 text-gray-500 dark:text-gray-400 text-[11px]">
                                                    <span>Lifespan · {{ $inventoryItem->lifespan_years }}y</span>
                                                    <span class="font-medium tabular-nums">{{ $inventoryItem->lifespan_remaining_percentage }}%</span>
                                                </div>
                                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-full rounded-full {{ $inventoryItem->lifespan_status === 'end_of_useful_life' ? 'bg-red-500' : ($inventoryItem->lifespan_status === 'approaching_end_of_life' ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $inventoryItem->lifespan_remaining_percentage }}%"></div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Category --}}
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $inventoryItem->category?->category_name ?? 'Uncategorized' }}</td>

                                    {{-- Qty --}}
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white tabular-nums">{{ number_format($inventoryItem->quantity) }}</td>

                                    {{-- Unit / ICS --}}
                                    <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300">
                                        <div>{{ $inventoryItem->unit }}</div>
                                        <div class="font-mono text-[11px] text-gray-400">{{ $inventoryItem->ics_no ?: 'No ICS' }}</div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        @php($statusClasses = match ($inventoryItem->status) {
                                            'available' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30',
                                            'assigned' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30',
                                            'under_maintenance', 'under_inspection' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
                                            'ready_to_dispose' => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-500/30',
                                            default => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                                        })
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize {{ $statusClasses }}">
                                            {{ str_replace('_', ' ', $inventoryItem->status) }}
                                        </span>
                                    </td>

                                    {{-- Unit Cost --}}
                                    <td class="px-4 py-3 tabular-nums text-xs text-gray-800 dark:text-gray-200">₱{{ number_format((float) $inventoryItem->unit_cost, 2) }}</td>

                                    {{-- Total Cost --}}
                                    <td class="px-4 py-3 tabular-nums text-xs font-medium text-gray-900 dark:text-white">₱{{ number_format((float) $inventoryItem->total_cost, 2) }}</td>

                                    {{-- Date Acquired --}}
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ optional($inventoryItem->date_acquired)->format('M d, Y') ?? '—' }}</td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            {{-- View Button --}}
                                            <button type="button" @click="openViewModal({
                                                item_id: {{ $source?->item_id ?? $inventoryItem->item_id }},
                                                item_name: @js($inventoryItem->item_name),
                                                category: @js($inventoryItem->category?->category_name ?? 'Uncategorized'),
                                                quantity: @js($inventoryItem->quantity),
                                                unit: @js($inventoryItem->unit),
                                                unit_cost: @js(number_format((float)$inventoryItem->unit_cost, 2)),
                                                total_cost: @js(number_format((float)$inventoryItem->total_cost, 2)),
                                                status: @js(str_replace('_', ' ', $inventoryItem->status)),
                                                ics_no: @js($inventoryItem->ics_no ?? 'None'),
                                                date_acquired: @js(optional($inventoryItem->date_acquired)->format('M d, Y') ?? 'Not recorded'),
                                                expected_end_date: @js(optional($inventoryItem->expected_end_date)->format('M d, Y') ?? 'Not recorded'),
                                                lifespan_years: @js($inventoryItem->lifespan_years ?? 'N/A'),
                                                lifespan_status: @js(str_replace('_', ' ', $inventoryItem->lifespan_status ?? 'healthy')),
                                                lifespan_percentage: @js($inventoryItem->lifespan_remaining_percentage ?? 100),
                                                assigned_to: @js($source?->assignedTo?->full_name ?? 'None (In stockroom)'),
                                                serial_numbers: @js($editSerialNumbers->all()),
                                                qr_code: @js($source?->qr_code ?? '')
                                            })" class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-2xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                <span>View</span>
                                            </button>

                                            {{-- Print QR --}}
                                            @if ($source?->qr_code)
                                                <button type="button" @click="openPrintModal({{ $source->item_id }})" title="Print QR Code" class="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50/50 px-2 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                                                    <i data-lucide="qr-code" class="h-3.5 w-3.5"></i>
                                                </button>
                                            @endif

                                            @if ($inventoryItem->status === 'available')
                                                {{-- Edit Modal Trigger --}}
                                                <div class="inline-block">
                                                <x-modals.base-modal title="Edit Inventory Item" subtitle="Update this available item." maxWidth="max-w-4xl">
                                                    <x-slot:trigger>
                                                        <button type="button" @click="open = true" title="Edit Item" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 text-start">Edit</button>
                                                    </x-slot:trigger>

                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.update', $source?->item_id) }}" class="space-y-5">
                                                        @csrf 
                                                        @method('PATCH')
                                                        @if ($errors->any())
                                                            <div class="rounded-md border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">Please correct the highlighted fields and try again.</div>
                                                        @endif

                                                        <div class="grid gap-6 md:grid-cols-2">
                                                            <div class="space-y-4">
                                                                <div>
                                                                    <label for="edit-item-name-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Item Name</label>
                                                                    <input id="edit-item-name-{{ $source?->item_id }}" name="item_name" value="{{ old('item_name', $inventoryItem->item_name) }}" type="text" placeholder="Enter item name" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                </div>

                                                                <div>
                                                                    <label for="edit-category-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Category</label>
                                                                    <select id="edit-category-{{ $source?->item_id }}" name="category_id" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                                        @foreach ($categories as $category)
                                                                            <option value="{{ $category->category_id }}" @selected(old('category_id', $inventoryItem->category_id) == $category->category_id)>{{ $category->category_name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>

                                                                <div>
                                                                    <label for="edit-description-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Item Description</label>
                                                                    <textarea id="edit-description-{{ $source?->item_id }}" name="description" rows="3" placeholder="Enter item description or specifications" class="h-24 w-full resize-none rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ old('description', $inventoryItem->description ?? $source?->description) }}</textarea>
                                                                </div>

                                                                <div>
                                                                    <label for="edit-lifespan-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Expected lifespan (years)</label>
                                                                    <input id="edit-lifespan-{{ $source?->item_id }}" name="lifespan_years" value="{{ old('lifespan_years', $inventoryItem->lifespan_years ?? $source?->lifespan_years) }}" type="number" min="1" max="65535" placeholder="Optional category default" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-start">Leave blank to use the category default.</p>
                                                                </div>
                                                            </div>

                                                            <div class="space-y-4">
                                                                <div class="grid gap-4 sm:grid-cols-2">
                                                                    <div>
                                                                        <label for="edit-unit-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Unit</label>
                                                                        <input id="edit-unit-{{ $source?->item_id }}" name="unit" value="{{ old('unit', $inventoryItem->unit) }}" type="text" placeholder="e.g. piece" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                    </div>

                                                                    <div>
                                                                        <label for="edit-cost-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Unit Cost</label>
                                                                        <input id="edit-cost-{{ $source?->item_id }}" name="unit_cost" value="{{ old('unit_cost', $inventoryItem->unit_cost) }}" type="number" min="0" step="0.01" placeholder="0.00" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                    </div>
                                                                </div>

                                                                <div class="grid gap-4 sm:grid-cols-2">
                                                                    <div>
                                                                        <label for="edit-ics-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">ICS No.</label>
                                                                        <input id="edit-ics-{{ $source?->item_id }}" name="ics_no" value="{{ old('ics_no', $inventoryItem->ics_no) }}" type="text" placeholder="Enter ICS number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                    </div>

                                                                    <div>
                                                                        <label for="edit-date-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Date Acquired</label>
                                                                        <input id="edit-date-{{ $source?->item_id }}" name="date_acquired" value="{{ old('date_acquired', optional($inventoryItem->date_acquired)->format('Y-m-d')) }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                    </div>
                                                                </div>

                                                                <aside class="flex h-64 flex-col overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                                                                    <label for="edit-quantity-{{ $source?->item_id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Quantity</label>
                                                                    <input id="edit-quantity-{{ $source?->item_id }}" name="quantity" value="{{ old('quantity', $inventoryItem->quantity) }}" type="number" min="1" max="100" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />

                                                                    @if ($editSerialNumbers->isNotEmpty())
                                                                        <div class="mt-4 flex flex-1 flex-col space-y-3 overflow-hidden">
                                                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 text-start">Serial Numbers</p>
                                                                            <div class="flex-1 space-y-2.5 overflow-y-auto pr-1">
                                                                                @foreach ($editSerialNumbers as $index => $serialNumber)
                                                                                    <div>
                                                                                        <label for="edit-serial-number-{{ $source?->item_id }}-{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400 text-start">Serial Number {{ $index + 1 }}</label>
                                                                                        <input id="edit-serial-number-{{ $source?->item_id }}-{{ $index }}" name="serial_numbers[{{ $index }}]" value="{{ old('serial_numbers.' . $index, $serialNumber) }}" type="text" placeholder="Enter serial number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                                                                    </div>
                                                                                @endforeach
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </aside>
                                                            </div>
                                                        </div>

                                                        <div class="flex justify-end gap-3 pt-2">
                                                            <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button>
                                                            <x-common.button-spinner text="Save Changes" loadingText="Saving..." class="text-white" />
                                                        </div>
                                                    </form>
                                                </x-modals.base-modal>
                                                </div>

                                                {{-- Delete Modal Trigger --}}
                                                <div class="inline-block">
                                                @php($deletableItems = ($inventoryItem->sourceItems ?? collect())->filter(fn ($item) => $item->status !== 'assigned'))
                                                @php($hasSerializedItems = $deletableItems->contains(fn ($item) => !empty($item->serial_number)))
                                                @php($allItemIds = $deletableItems->pluck('item_id')->map(fn ($id) => (string) $id)->values())
                                                <x-modals.base-modal title="Delete Inventory Item" subtitle="Select the item(s) you wish to remove." maxWidth="max-w-md">
                                                    <x-slot:trigger>
                                                        <button type="button" @click="open = true" title="Delete Item" class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-800/60 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                                    </x-slot:trigger>

                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.destroy', $source?->item_id ?? $inventoryItem->item_id) }}" 
                                                        x-data="{
                                                            allIds: @js($allItemIds),
                                                            selectedIds: @js($allItemIds),
                                                            toggleAll() {
                                                                if (this.selectedIds.length === this.allIds.length) {
                                                                    this.selectedIds = [];
                                                                } else {
                                                                    this.selectedIds = [...this.allIds];
                                                                }
                                                            }
                                                        }" 
                                                        class="space-y-4">
                                                        @csrf
                                                        @method('DELETE')

                                                        <div class="rounded-md border border-red-100 bg-red-50/60 p-3 dark:border-red-900/30 dark:bg-red-950/20">
                                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $inventoryItem->item_name }}</p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                Category: {{ $inventoryItem->category?->category_name ?? 'Uncategorized' }} · Status: <span class="capitalize">{{ str_replace('_', ' ', $inventoryItem->status) }}</span>
                                                            </p>
                                                        </div>

                                                        @if ($hasSerializedItems)
                                                            <div>
                                                                <div class="mb-2 flex items-center justify-between">
                                                                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Choose items to delete</label>
                                                                    <button type="button" @click="toggleAll()" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400" x-text="selectedIds.length === allIds.length ? 'Deselect All' : 'Select All'"></button>
                                                                </div>

                                                                <div class="max-h-48 space-y-2 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                                                                    @foreach ($deletableItems as $subItem)
                                                                        <label class="flex items-center gap-2.5 rounded px-2 py-1.5 hover:bg-white dark:hover:bg-gray-700/60">
                                                                            <input type="checkbox" name="selected_item_ids[]" value="{{ $subItem->item_id }}" x-model="selectedIds" class="rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-700" />
                                                                            <span class="flex-1 font-mono text-xs text-gray-800 dark:text-gray-200">{{ $subItem->serial_number ?: 'Item #' . $subItem->item_id }}</span>
                                                                            @if ($subItem->ics_no)
                                                                                <span class="text-[10px] text-gray-400">ICS: {{ $subItem->ics_no }}</span>
                                                                            @endif
                                                                        </label>
                                                                    @endforeach
                                                                </div>

                                                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-text="`Selected ${selectedIds.length} of ${allIds.length} item(s)`"></p>
                                                            </div>
                                                        @else
                                                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                                                Are you sure you want to delete this inventory record? This action will remove all recorded units (Qty: {{ $inventoryItem->quantity }}).
                                                            </p>
                                                        @endif

                                                        <p class="text-xs font-medium text-red-600 dark:text-red-400">This action cannot be undone.</p>

                                                        <div class="flex justify-end gap-3 pt-2">
                                                            <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button>
                                                            <x-common.button-spinner 
                                                                text="Delete Selected" 
                                                                loadingText="Deleting..." 
                                                                :disabled="$hasSerializedItems ? 'loading || selectedIds.length === 0' : 'loading'"
                                                                class="!bg-red-600 !text-white hover:!bg-red-700 focus:!ring-red-500" 
                                                            />
                                                        </div>
                                                    </form>
                                                </x-modals.base-modal>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No inventory records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination for All --}}
                @if ($allInventoryPage->hasPages())
                    <div class="flex flex-col gap-2 border-t border-gray-200 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 sm:px-6">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Showing <span class="font-medium text-gray-900 dark:text-white">{{ $allInventoryPage->firstItem() }}</span> to <span class="font-medium text-gray-900 dark:text-white">{{ $allInventoryPage->lastItem() }}</span> of <span class="font-medium text-gray-900 dark:text-white">{{ $allInventoryPage->total() }}</span> results
                        </p>
                        <div>
                            {{ $allInventoryPage->withQueryString()->links() }}
                        </div>
                    </div>
                @endif
                </section>
            </div>

            {{-- SPECIFIC STATUS WORKSPACE TABLES --}}
            @foreach ($statusCollections as $status => $rows)
                @php($searchModel = match ($status) { 'under_maintenance' => 'underMaintenanceSearch', 'under_inspection' => 'underInspectionSearch', 'ready_to_dispose' => 'readyToDisposeSearch', default => $status . 'Search' })
                <div x-show="activeTab === '{{ $status }}'" x-cloak class="px-4 pb-4 sm:px-5 sm:pb-5">
                        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between bg-gray-50/70 px-4 py-3 dark:bg-gray-800/40 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $statusLabels[$status] }} Records</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $status === 'available' ? 'Stock ready for controlled assignment.' : ($status === 'disposed' ? 'Historical records are read-only.' : 'Review records at this lifecycle stage.') }}</p>
                        </div>
                        <input x-model="{{ $searchModel }}" type="search" placeholder="Search {{ strtolower($statusLabels[$status]) }}..." class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:w-64" />
                    </div>

                    <div class="max-h-[36rem] overflow-y-auto overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-800 dark:text-gray-200">
                            <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    @if ($status === 'assigned')
                                        <th class="px-4 py-3">Assignee</th>
                                        <th class="px-4 py-3">Assigned Date</th>
                                    @endif

                                    @if (in_array($status, ['under_maintenance', 'under_inspection', 'ready_to_dispose'], true))
                                        <th class="px-4 py-3">Workflow Detail</th>
                                    @endif

                                    @if ($status === 'disposed')
                                        <th class="px-4 py-3">Disposal Record</th>
                                    @endif

                                    <th class="px-4 py-3">Asset</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Qty</th>
                                    <th class="px-4 py-3">Unit / ICS</th>
                                    <th class="px-4 py-3">Unit Cost</th>
                                    <th class="px-4 py-3">Total Cost</th>
                                    <th class="px-4 py-3">Date Acquired</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @forelse ($rows as $inventoryItem)
                                    @php($source = ($inventoryItem->sourceItems ?? collect())->first() ?: \App\Models\Inventory::find($inventoryItem->source_item_id))
                                    @php($searchable = strtolower(implode(' ', [$inventoryItem->item_name, $inventoryItem->unit, $inventoryItem->category?->category_name ?? '', $inventoryItem->ics_no ?? ''])))

                                    <tr x-show="matches({{ $searchModel }}, $el)" data-inventory-panel="{{ $status }}" data-search="{{ $searchable }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-800/60 transition-colors">
                                        @if ($status === 'assigned')
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $source?->assignedTo?->full_name ?? 'Recorded assignee' }}</td>
                                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $source?->latestAssignment?->transaction_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        @endif

                                        @if (in_array($status, ['under_maintenance', 'under_inspection', 'ready_to_dispose'], true))
                                            <td class="max-w-xs px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $source?->latestMaintenance?->issue_description ?? $source?->disposalMovement?->notes ?? 'Awaiting review' }}</td>
                                        @endif

                                        @if ($status === 'disposed')
                                            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                                <div>{{ $source?->disposalMovement?->created_at?->format('M d, Y') ?? 'N/A' }}</div>
                                                <div class="text-[11px] text-gray-400">{{ $source?->disposalMovement?->notes ?? 'No reason recorded' }}</div>
                                            </td>
                                        @endif

                                        {{-- Asset --}}
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-gray-900 dark:text-white">{{ $inventoryItem->item_name }}</div>
                                            @if ($inventoryItem->expected_end_date)
                                                <div class="mt-1.5 max-w-xs text-xs">
                                                    <div class="flex items-center justify-between gap-2 text-gray-500 dark:text-gray-400 text-[11px]">
                                                        <span>Lifespan · {{ $inventoryItem->lifespan_years }}y</span>
                                                        <span class="font-medium tabular-nums">{{ $inventoryItem->lifespan_remaining_percentage }}%</span>
                                                    </div>
                                                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                        <div class="h-full rounded-full {{ $inventoryItem->lifespan_status === 'end_of_useful_life' ? 'bg-red-500' : ($inventoryItem->lifespan_status === 'approaching_end_of_life' ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $inventoryItem->lifespan_remaining_percentage }}%"></div>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Category --}}
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $inventoryItem->category?->category_name ?? 'Uncategorized' }}</td>

                                        {{-- Qty --}}
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white tabular-nums">{{ number_format($inventoryItem->quantity) }}</td>

                                        {{-- Unit / ICS --}}
                                        <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-300">
                                            <div>{{ $inventoryItem->unit }}</div>
                                            <div class="font-mono text-[11px] text-gray-400">{{ $inventoryItem->ics_no ?: 'No ICS' }}</div>
                                        </td>

                                        {{-- Unit Cost --}}
                                        <td class="px-4 py-3 tabular-nums text-xs text-gray-800 dark:text-gray-200">₱{{ number_format((float) $inventoryItem->unit_cost, 2) }}</td>

                                        {{-- Total Cost --}}
                                        <td class="px-4 py-3 tabular-nums text-xs font-medium text-gray-900 dark:text-white">₱{{ number_format((float) $inventoryItem->total_cost, 2) }}</td>

                                        {{-- Date Acquired --}}
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ optional($inventoryItem->date_acquired)->format('M d, Y') ?? '—' }}</td>

                                        {{-- Actions --}}
                                        <td class="px-4 py-3 text-right">
                                            <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                                {{-- View Button --}}
                                                <button type="button" @click="openViewModal({
                                                    item_id: {{ $source?->item_id ?? $inventoryItem->item_id }},
                                                    item_name: @js($inventoryItem->item_name),
                                                    category: @js($inventoryItem->category?->category_name ?? 'Uncategorized'),
                                                    quantity: @js($inventoryItem->quantity),
                                                    unit: @js($inventoryItem->unit),
                                                    unit_cost: @js(number_format((float)$inventoryItem->unit_cost, 2)),
                                                    total_cost: @js(number_format((float)$inventoryItem->total_cost, 2)),
                                                    status: @js(str_replace('_', ' ', $inventoryItem->status)),
                                                    ics_no: @js($inventoryItem->ics_no ?? 'None'),
                                                    date_acquired: @js(optional($inventoryItem->date_acquired)->format('M d, Y') ?? 'Not recorded'),
                                                    expected_end_date: @js(optional($inventoryItem->expected_end_date)->format('M d, Y') ?? 'Not recorded'),
                                                    lifespan_years: @js($inventoryItem->lifespan_years ?? 'N/A'),
                                                    lifespan_status: @js(str_replace('_', ' ', $inventoryItem->lifespan_status ?? 'healthy')),
                                                    lifespan_percentage: @js($inventoryItem->lifespan_remaining_percentage ?? 100),
                                                    assigned_to: @js($source?->assignedTo?->full_name ?? ($status === 'assigned' ? 'Recorded assignee' : 'None (In stockroom)')),
                                                    serial_numbers: [],
                                                    qr_code: @js($source?->qr_code ?? '')
                                                })" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">View</button>

                                                @if ($source?->qr_code)
                                                    <button type="button" @click="openPrintModal({{ $source->item_id }})" title="Print QR Code" class="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50/50 px-2 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                                                        <i data-lucide="qr-code" class="h-3.5 w-3.5"></i>
                                                    </button>
                                                @endif

                                                @if ($status === 'available')
                                                    <a href="{{ route('propertyCustodian.transactions') }}" class="rounded-md bg-brand-500 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-brand-600">Assign</a>
                                                    @if ($inventoryItem->category?->is_maintenance_eligible === false)
                                                        <button type="button" disabled title="This category is not eligible for maintenance." class="cursor-not-allowed rounded-md bg-orange-500 px-2.5 py-1.5 text-xs font-medium text-white opacity-50">Maintenance</button>
                                                    @else
                                                        <button type="button" @click="openMaintenance('{{ $source?->item_id }}')" title="Send this item to maintenance" class="rounded-md bg-orange-500 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-orange-600">Maintenance</button>
                                                    @endif
                                                @elseif ($status === 'assigned')
                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.mark-returned', $source?->item_id) }}" class="inline">
                                                        @csrf
                                                        <button class="rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-700">Receive Return</button>
                                                    </form>
                                                    <a href="{{ route('propertyCustodian.transactions') }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Transfer</a>
                                                @elseif ($status === 'under_maintenance')
                                                    <div x-data="{
                                                        menuOpen: false,
                                                        btnRect: {},
                                                        toggle(event) {
                                                            this.btnRect = event.currentTarget.getBoundingClientRect();
                                                            this.menuOpen = !this.menuOpen;
                                                        }
                                                    }" class="relative inline-block">
                                                        <button type="button" @click="toggle($event)" class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                            Actions
                                                            <i data-lucide="chevron-down" class="h-3 w-3"></i>
                                                        </button>

                                                        <template x-teleport="body">
                                                            <div x-show="menuOpen"
                                                                @click.outside="menuOpen = false"
                                                                x-transition.origin.top.right
                                                                :style="`position:fixed;z-index:9999;top:${btnRect.bottom + 4}px;right:${window.innerWidth - btnRect.right}px;`"
                                                                class="w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-xl ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-800"
                                                                style="display:none;">
                                                                <button type="button" @click="openRepairModal('{{ $source?->item_id }}'); menuOpen = false" class="flex w-full items-center gap-2 px-4 py-2 text-left text-xs font-medium text-green-700 transition hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20">
                                                                    <i data-lucide="wrench" class="h-3.5 w-3.5"></i>
                                                                    Mark as Repaired
                                                                </button>

                                                                <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>

                                                                <form method="POST" action="{{ route('propertyCustodian.inventory.mark-ready-to-dispose', $source?->item_id) }}">
                                                                    @csrf
                                                                    <input type="hidden" name="notes" value="Marked for disposal after maintenance review">
                                                                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-xs font-medium text-red-700 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                                        Ready to Dispose
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </template>
                                                    </div>
                                                @elseif ($status === 'under_inspection')
                                                    {{-- Under Inspection: Review pending badge (no workflow action yet) --}}
                                                    {{-- Under Inspection: Review pending badge --}}
                                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 px-2.5 py-1.5 text-xs text-blue-700 dark:border-blue-800/60 dark:text-blue-400">
                                                        <i data-lucide="clock" class="h-3 w-3"></i>
                                                        Review pending
                                                    </span>
                                                @elseif ($status === 'ready_to_dispose')
                                                    <form method="POST" action="{{ route('propertyCustodian.inventory.dispose', $source?->item_id) }}" onsubmit="return confirm('Dispose this item? This action cannot be undone.')" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                                                            <i data-lucide="trash" class="h-3.5 w-3.5"></i>
                                                            Dispose
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-gray-400">Archived</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">No {{ strtolower($statusLabels[$status]) }} records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Workspace Pagination --}}
                    @if ($inventoryPages->get($status)?->hasPages())
                        @php($statusPage = $inventoryPages->get($status))
                        <div class="flex flex-col gap-2 border-t border-gray-200 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 sm:px-6">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Showing <span class="font-medium text-gray-900 dark:text-white">{{ $statusPage->firstItem() }}</span> to <span class="font-medium text-gray-900 dark:text-white">{{ $statusPage->lastItem() }}</span> of <span class="font-medium text-gray-900 dark:text-white">{{ $statusPage->total() }}</span> results
                            </p>
                            <div>
                                {{ $statusPage->withQueryString()->links() }}
                            </div>
                        </div>
                    @endif
                        </section>
                </div>
            @endforeach
        </div>

        {{-- ── IN-PAGE ITEM VIEW MODAL ── --}}
        <x-modals.base-modal modalId="item-view-modal" title="Inventory Item Details" subtitle="Comprehensive asset information and lifecycle overview." maxWidth="max-w-2xl" :closeButton="true">
            <template x-if="viewModalItem">
                <div class="space-y-5 text-sm">
                    {{-- Header Banner --}}
                    <div class="flex items-start justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white" x-text="viewModalItem.item_name"></h4>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Category: <span class="font-medium text-gray-700 dark:text-gray-300" x-text="viewModalItem.category"></span>
                                &bull; ICS No: <span class="font-mono" x-text="viewModalItem.ics_no || 'None'"></span>
                            </p>
                        </div>
                        <span class="inline-flex rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300" x-text="viewModalItem.status"></span>
                    </div>

                    {{-- Metrics & Quantities Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-lg border border-gray-100 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40">
                        <div>
                            <span class="block text-[11px] font-bold uppercase text-gray-500 dark:text-gray-400">Quantity</span>
                            <span class="text-base font-bold text-gray-900 dark:text-white" x-text="`${viewModalItem.quantity} ${viewModalItem.unit}`"></span>
                        </div>
                        <div>
                            <span class="block text-[11px] font-bold uppercase text-gray-500 dark:text-gray-400">Unit Cost</span>
                            <span class="text-base font-bold text-gray-900 dark:text-white" x-text="`₱${viewModalItem.unit_cost}`"></span>
                        </div>
                        <div>
                            <span class="block text-[11px] font-bold uppercase text-gray-500 dark:text-gray-400">Total Value</span>
                            <span class="text-base font-bold text-brand-600 dark:text-brand-400" x-text="`₱${viewModalItem.total_cost}`"></span>
                        </div>
                        <div>
                            <span class="block text-[11px] font-bold uppercase text-gray-500 dark:text-gray-400">Date Acquired</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="viewModalItem.date_acquired"></span>
                        </div>
                    </div>

                    {{-- Lifespan & Assignment Details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 p-3.5 dark:border-gray-800">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Lifespan Tracking</h5>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Expected End:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="viewModalItem.expected_end_date"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Duration:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="`${viewModalItem.lifespan_years} years`"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Status:</span>
                                    <span class="font-medium capitalize text-gray-900 dark:text-white" x-text="viewModalItem.lifespan_status"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 p-3.5 dark:border-gray-800">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Current Assignment</h5>
                            <div class="mt-2 space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Assigned To:</span>
                                    <span class="font-medium text-gray-900 dark:text-white" x-text="viewModalItem.assigned_to"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">QR Code:</span>
                                    <span class="font-mono text-[11px] text-brand-600 dark:text-brand-400" x-text="viewModalItem.qr_code ? 'Generated' : 'None'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Serial Numbers --}}
                    <template x-if="viewModalItem.serial_numbers && viewModalItem.serial_numbers.length > 0">
                        <div class="rounded-lg border border-gray-200 p-3.5 dark:border-gray-800">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Registered Serial Numbers</h5>
                            <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                                <template x-for="sn in viewModalItem.serial_numbers" :key="sn">
                                    <span class="rounded bg-gray-100 px-2 py-1 font-mono text-xs font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200" x-text="sn"></span>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Footer Actions --}}
                    <div class="flex items-center justify-between border-t border-gray-100 pt-4 dark:border-gray-800">
                        <template x-if="viewModalItem.qr_code">
                            <button type="button" @click="open = false; openPrintModal(viewModalItem.item_id)" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
                                <i data-lucide="qr-code" class="h-3.5 w-3.5"></i>
                                <span>Print QR Sticker</span>
                            </button>
                        </template>
                        <div x-show="!viewModalItem.qr_code"></div>
                        <button type="button" @click="open = false" class="rounded-lg border border-gray-300 px-4 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Close</button>
                    </div>
                </div>
            </template>
        </x-modals.base-modal>

        {{-- ── IN-PAGE PRINT QR MODAL (using base-modal component) ── --}}
        <div class="print:hidden">
            <x-modals.base-modal modalId="print-qr-modal" title="Print QR Code" subtitle="Preview sticker and select item units to print" maxWidth="max-w-4xl" :closeButton="true">
                <div class="space-y-4">
                    {{-- Top actions inside modal --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-brand-50 p-2 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                                <i data-lucide="qr-code" class="h-4 w-4"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="printModalActiveItem?.item_name || 'Loading item...'"></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="printCurrentModalLabel()" :disabled="!printModalActiveItem" class="inline-flex items-center gap-1.5 rounded-md bg-brand-500 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                Print This Label
                            </button>

                            <template x-if="printModalItems.length > 1">
                                <button type="button" @click="printBatchModalLabels()" :disabled="printModalSelectedIds.length === 0" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 disabled:opacity-50">
                                    <i data-lucide="layers" class="h-3.5 w-3.5"></i>
                                    <span x-text="'Print Checked (' + printModalSelectedIds.length + ')'"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Body: Loading / Error / Two-Column Workspace --}}
                    <div>
                        <template x-if="printModalLoading">
                            <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                                <i data-lucide="loader-circle" class="h-8 w-8 animate-spin text-brand-500"></i>
                                <span class="mt-3 text-sm font-medium">Loading QR label data...</span>
                            </div>
                        </template>

                        <template x-if="printModalError">
                            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400" x-text="printModalError"></div>
                        </template>

                        <template x-if="!printModalLoading && !printModalError && printModalActiveItem">
                            <div class="grid gap-6 md:grid-cols-12 items-start">
                                
                                {{-- LEFT COLUMN: Unit & Serial list --}}
                                <div class="md:col-span-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400" x-text="'Units (' + printModalItems.length + ')'"></span>
                                        <span class="text-xs text-brand-600 dark:text-brand-400 font-medium" x-text="printModalActiveItem?.category?.category_name || 'General'"></span>
                                    </div>

                                    <template x-if="printModalItems.length > 1">
                                        <div class="flex items-center justify-between rounded-md border border-gray-100 bg-gray-50 px-3 py-1.5 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                            <label class="flex items-center gap-2 cursor-pointer font-medium">
                                                <input type="checkbox" :checked="isAllPrintUnitsSelected()" @change="toggleSelectAllPrintUnits($event.target.checked)" class="rounded border-gray-300 text-brand-500 focus:ring-brand-400">
                                                <span>Select all units</span>
                                            </label>
                                            <span x-text="printModalSelectedIds.length + ' of ' + printModalItems.length + ' selected'"></span>
                                        </div>
                                    </template>

                                    <div class="max-h-[360px] overflow-y-auto space-y-2 pr-1">
                                        <template x-for="(item, index) in printModalItems" :key="item.item_id">
                                            <div @click="selectPrintUnit(item)"
                                                 :class="printModalActiveItem?.item_id === item.item_id 
                                                     ? 'border-brand-500 bg-brand-50/40 ring-1 ring-brand-500 dark:border-brand-500 dark:bg-brand-500/10' 
                                                     : 'border-gray-200 bg-white hover:border-brand-200 dark:border-gray-700 dark:bg-gray-800/60'"
                                                 class="flex cursor-pointer items-start justify-between rounded-lg border p-3 transition">
                                                <div class="flex items-start gap-2.5">
                                                    <template x-if="printModalItems.length > 1">
                                                        <input type="checkbox"
                                                               :checked="printModalSelectedIds.includes(item.item_id)"
                                                               @click.stop="togglePrintUnitSelection(item.item_id)"
                                                               class="mt-0.5 rounded border-gray-300 text-brand-500 focus:ring-brand-400">
                                                    </template>
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs font-bold text-gray-900 dark:text-white" x-text="'Unit ' + (index + 1)"></span>
                                                            <span class="font-mono text-[11px] font-semibold text-brand-600 dark:text-brand-400" x-text="item.inventory_item_no"></span>
                                                        </div>
                                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                            SN: <span class="font-mono font-medium text-gray-800 dark:text-gray-200" x-text="item.serial_number || 'None recorded'"></span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <span x-show="printModalActiveItem?.item_id === item.item_id" class="text-[11px] font-bold text-brand-600 dark:text-brand-400">Previewing</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- RIGHT COLUMN: Authentic DNHS QR Sticker Preview --}}
                                <div class="md:col-span-7 flex flex-col items-center">
                                    <div class="w-full max-w-sm rounded-xl border-2 border-brand-500 bg-white p-4 shadow-md text-gray-900">
                                        <div class="border-b border-blue-100 pb-2 text-center">
                                            <div class="text-xs font-bold uppercase tracking-wider text-brand-700">Dian-ay National High School (NIR)</div>
                                            <div class="text-[10px] text-gray-500 font-medium">Division of Negros Occidental</div>
                                        </div>

                                        <div class="mt-3 flex items-start gap-3">
                                            <div class="flex flex-col items-center shrink-0">
                                                <canvas id="qr-modal-preview-canvas" class="h-32 w-32 rounded border border-gray-200 bg-white p-1"></canvas>
                                                <span class="mt-1 max-w-[130px] break-all font-mono text-[9px] leading-tight text-gray-400 text-center" x-text="printModalActiveItem?.qr_code"></span>
                                            </div>

                                            <div class="flex-1 space-y-2">
                                                <div class="text-sm font-extrabold uppercase text-gray-900 leading-snug" x-text="printModalActiveItem?.item_name"></div>

                                                <div>
                                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-gray-500">Item No.</span>
                                                    <span class="font-mono text-xs font-bold text-brand-600" x-text="printModalActiveItem?.inventory_item_no"></span>
                                                </div>

                                                <div>
                                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-gray-500">Serial No.</span>
                                                    <span class="font-mono text-[11px] font-semibold text-gray-800" x-text="printModalActiveItem?.serial_number || 'Not recorded'"></span>
                                                </div>

                                                <div>
                                                    <span class="block text-[9px] font-bold uppercase tracking-wider text-gray-500">Category</span>
                                                    <span class="text-[11px] font-medium text-gray-700" x-text="printModalActiveItem?.category?.category_name || 'Uncategorized'"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3 border-t border-dashed border-blue-100 pt-2 text-center text-[9px] text-gray-400 font-medium">
                                            Scan this QR code to view item details &bull; DNHS Property Management System
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>
            </x-modals.base-modal>
        </div>

        {{-- ── QR SCANNER MODAL ── --}}
        <div @modal-opened.window="if ($event.detail === 'qr-scanner-modal') handleQrModalOpened()"
             @modal-closed.window="if ($event.detail === 'qr-scanner-modal') handleQrModalClosed()">
            <x-modals.base-modal modalId="qr-scanner-modal" title="Scan QR Code" subtitle="Point the camera at a DNHS item QR sticker" maxWidth="max-w-md">
                <div>
                    <div x-show="qrScannerMode === 'scan'" x-cloak class="space-y-4">
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                        <div id="qr-camera-viewport" class="w-full" style="min-height:240px;"></div>
                        <p x-show="!qrCameraActive && !qrError" class="px-4 py-2 text-center text-xs text-gray-400">Starting camera…</p>
                    </div>

                    <div x-show="qrError" x-cloak class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300" x-text="qrError"></div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">Or enter QR token manually</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="qrManualToken"
                                   placeholder="dnhs_qr_…"
                                   @keydown.enter.prevent="lookupQrToken(qrManualToken)"
                                   class="flex-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:border-brand-500 focus:outline-none" />
                            <button type="button" @click="lookupQrToken(qrManualToken)"
                                    :disabled="qrLoading"
                                    class="rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">
                                <span x-show="!qrLoading">Look up</span>
                                <span x-show="qrLoading" class="inline-flex items-center gap-1"><i data-lucide="loader-circle" class="h-3.5 w-3.5 animate-spin"></i> …</span>
                            </button>
                        </div>
                    </div>

                    </div>

                    <div x-show="qrScannerMode === 'result'" x-cloak class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-500/30 dark:bg-green-500/10">
                        <div class="mb-2 flex items-center gap-2">
                            <i data-lucide="package-check" class="h-4 w-4 text-green-600 dark:text-green-400"></i>
                            <span class="text-sm font-semibold text-green-800 dark:text-green-300">Item Found</span>
                        </div>
                        <dl class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Item No.</dt>
                                <dd class="font-mono font-semibold text-gray-900 dark:text-white" x-text="qrResult?.inventory_item_no"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                                <dd class="font-medium text-gray-900 dark:text-white" x-text="qrResult?.item_name"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Category</dt>
                                <dd class="text-gray-700 dark:text-gray-300" x-text="qrResult?.category"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="capitalize text-gray-700 dark:text-gray-300" x-text="(qrResult?.status || '').replace(/_/g, ' ')"></dd>
                            </div>
                            <template x-if="qrResult?.serial_number">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500 dark:text-gray-400">Serial No.</dt>
                                    <dd class="font-mono text-gray-700 dark:text-gray-300" x-text="qrResult?.serial_number"></dd>
                                </div>
                            </template>
                        </dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" @click="openScannedItemDetails()"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-600">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                View Item
                            </button>
                            <button type="button" @click="const itemId = qrResult?.item_id; closeQrScanner(); openPrintModal(itemId)"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-brand-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/30 dark:bg-gray-900 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                Print Label
                            </button>
                            <button type="button" @click="scanAnotherQrCode()"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                <i data-lucide="scan-line" class="h-3.5 w-3.5"></i>
                                Scan Another
                            </button>
                        </div>
                    </div>
                </div>
            </x-modals.base-modal>
        </div>

        {{-- ── STOCK IN MODAL ── --}}
        <x-modals.base-modal modalId="stock-in-modal" :open="$errors->any()" title="Stock In Item" subtitle="Add an item to available inventory." maxWidth="max-w-4xl">
            @php($selectedCategory = $categories->firstWhere('category_id', (int) old('category_id')))
            
            <form x-data="{ quantity: {{ old('quantity', 1) }}, requiresSerialNumber: {{ $selectedCategory?->requires_serial_number ? 'true' : 'false' }}, serialNumbers: @js(old('serial_numbers', [])), setQuantity(value) { const count = Math.max(1, Math.min(100, Number(value) || 1)); this.quantity = count; this.serialNumbers = Array.from({ length: count }, (_, index) => this.serialNumbers[index] ?? ''); }, setCategory(event) { this.requiresSerialNumber = event.target.selectedOptions[0]?.dataset.requiresSerialNumber === 'true'; if (this.requiresSerialNumber) this.setQuantity(this.quantity); else this.serialNumbers = []; } }" method="POST" action="{{ route('propertyCustodian.inventory.stock-in') }}" class="space-y-5">
                @csrf
                @if ($errors->any())
                    <div class="rounded-md border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">Please correct the highlighted fields and try again.</div>
                @endif

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <label for="stock-in-item-name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                            <input id="stock-in-item-name" name="item_name" value="{{ old('item_name') }}" type="text" placeholder="Enter item name" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                        <div>
                            <label for="stock-in-category" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                            <select id="stock-in-category" name="category_id" @change="setCategory($event)" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->category_id }}" data-requires-serial-number="{{ $category->requires_serial_number ? 'true' : 'false' }}" @selected(old('category_id') == $category->category_id)>{{ $category->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="stock-in-description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item Description</label>
                            <textarea id="stock-in-description" name="description" rows="3" placeholder="Enter item description or specifications" class="h-24 w-full resize-none rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ old('description') }}</textarea>
                        </div>
                        <div>
                            <label for="stock-in-lifespan" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Expected lifespan (years)</label>
                            <input id="stock-in-lifespan" name="lifespan_years" value="{{ old('lifespan_years', $selectedCategory?->default_lifespan_years) }}" type="number" min="1" max="65535" placeholder="Optional category default" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave blank to use the category default.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="stock-in-unit" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit</label>
                                <input id="stock-in-unit" name="unit" value="{{ old('unit') }}" type="text" placeholder="e.g. piece" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                            <div>
                                <label for="stock-in-cost" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label>
                                <input id="stock-in-cost" name="unit_cost" value="{{ old('unit_cost') }}" type="number" min="0" step="0.01" placeholder="0.00" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="stock-in-ics" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">ICS No.</label>
                                <input id="stock-in-ics" name="ics_no" value="{{ old('ics_no') }}" type="text" placeholder="Enter ICS number" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                            <div>
                                <label for="stock-in-date" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Acquired</label>
                                <input id="stock-in-date" name="date_acquired" value="{{ old('date_acquired') }}" type="date" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            </div>
                        </div>

                        <aside class="flex h-64 flex-col overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <label for="stock-in-quantity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                            <input id="stock-in-quantity" name="quantity" x-model.number="quantity" @input="setQuantity($event.target.value)" type="number" min="1" max="100" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />

                            <template x-if="requiresSerialNumber">
                                <div class="mt-4 flex flex-1 flex-col space-y-3 overflow-hidden">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Serial Numbers</p>
                                    <div class="flex-1 space-y-2.5 overflow-y-auto pr-1">
                                        <template x-for="index in quantity" :key="index">
                                            <div>
                                                <label :for="`serial-number-${index}`" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400" x-text="`Serial Number ${index}`"></label>
                                                <input :id="`serial-number-${index}`" :name="`serial_numbers[${index - 1}]`" x-model="serialNumbers[index - 1]" type="text" placeholder="Enter serial number" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!requiresSerialNumber" class="mt-3 text-xs text-gray-500 dark:text-gray-400">Serial numbers are not required for the selected category.</p>
                        </aside>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Cancel</button>
                    <x-common.button-spinner text="Save Item" loadingText="Saving..." class="text-white" />
                </div>
            </form>
        </x-modals.base-modal>

        {{-- ── SEND MAINTENANCE MODAL ── --}}
        <x-modals.base-modal modalId="send-maintenance" title="Send to Maintenance" subtitle="Choose the item and describe the issue before sending it for repair." maxWidth="max-w-lg">
            <form method="POST" :action="`${maintenanceBase}/${maintenanceItemId}/send-to-maintenance`" class="space-y-5">
                @csrf
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Select item</legend>
                    <div class="max-h-52 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700">
                        @php($availableItems = $maintenanceItems->flatMap(fn ($sourceItems) => $sourceItems->map(fn ($sourceItem) => [$sourceItem, $sourceItem])))
                        @forelse ($availableItems as [$availableItem, $availableSource])
                            <label class="flex cursor-pointer items-start gap-3 rounded-md border border-transparent px-3 py-2 hover:border-orange-300 hover:bg-orange-50 dark:hover:border-orange-500/40 dark:hover:bg-orange-500/10">
                                <input type="radio" name="maintenance_item_choice" value="{{ $availableSource->item_id }}" x-model="maintenanceItemId" required class="mt-1 border-gray-300 text-orange-500 focus:ring-orange-500">
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $availableItem->item_name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $availableSource->inventory_item_no ?? 'No inventory number' }} · {{ $availableSource->serial_number ?? 'No serial number' }} · {{ number_format($availableSource->quantity) }} {{ $availableSource->unit }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="px-3 py-4 text-sm text-gray-500">No available items found.</p>
                        @endforelse
                    </div>
                </fieldset>
                <div>
                    <label for="maintenance-issue-shared" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Issue description</label>
                    <textarea id="maintenance-issue-shared" name="issue_description" rows="4" required maxlength="1000" placeholder="Describe the issue" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                    <x-common.button-spinner text="Send to Maintenance" loadingText="Sending..." class="bg-orange-500 text-white hover:bg-orange-600" />
                </div>
            </form>
        </x-modals.base-modal>

        {{-- ── MARK AS REPAIRED MODAL ── --}}
        <x-modals.base-modal modalId="repair-modal" title="Mark as Repaired" subtitle="Return this item to available inventory after recording the repair." maxWidth="max-w-md">
            <form method="POST" :action="`${repairBase}/${repairItemId}/mark-repaired`" class="space-y-4">
                @csrf
                <div>
                    <label for="repair-notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Repair notes</label>
                    <textarea id="repair-notes" name="repair_notes" rows="4" maxlength="1000" placeholder="Describe the repair performed" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"></textarea>
                </div>
                <div>
                    <label for="repair-cost" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Repair cost</label>
                    <input id="repair-cost" name="maintenance_cost" type="number" min="0" step="0.01" placeholder="0.00" class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                    <x-common.button-spinner text="Mark as Repaired" loadingText="Saving..." class="bg-green-600 text-white hover:bg-green-700" />
                </div>
            </form>
        </x-modals.base-modal>
    </div>

    @push('scripts')
        <script>
            (() => {
                const editItemDetails = @json($editItemDetails);

                const attachEditSerialNumbers = () => {
                    document.querySelectorAll('form').forEach((form) => {
                        if (form.querySelector('input[name="_method"]')?.value !== 'PATCH' || form.dataset.serialNumbersAttached === 'true') {
                            return;
                        }

                        const itemId = new URL(form.action, window.location.origin).pathname.match(/(\d+)\/?$/)?.[1];
                        const quantityInput = form.querySelector('input[name="quantity"]');
                        const quantityPanel = quantityInput?.closest('aside');
                        const itemDetails = editItemDetails[String(itemId)] ?? editItemDetails[itemId];

                        if (!itemId || !quantityPanel || !itemDetails) {
                            return;
                        }

                        form.dataset.serialNumbersAttached = 'true';

                        if ((itemDetails.serialNumbers ?? []).length > 0) {
                            quantityInput.value = itemDetails.quantity;
                            quantityInput.disabled = true;

                            const hiddenQuantity = document.createElement('input');
                            hiddenQuantity.type = 'hidden';
                            hiddenQuantity.name = 'quantity';
                            hiddenQuantity.value = itemDetails.quantity;
                            form.append(hiddenQuantity);
                        }

                    });
                };

                const start = () => {
                    attachEditSerialNumbers();
                    window.setTimeout(attachEditSerialNumbers, 0);
                    new MutationObserver(attachEditSerialNumbers).observe(document.body, { childList: true, subtree: true });
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', start, { once: true });
                } else {
                    start();
                }
            })();
        </script>
    @endpush

    @push('scripts')
        {{-- qrcode and html5-qrcode CDN scripts --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
            window.dnhsPrintStickers = async function (itemsToPrint) {
                if (!itemsToPrint || itemsToPrint.length === 0) return;

                var printWindow = window.open('', '_blank');
                if (!printWindow) {
                    window.alert('Please allow pop-ups for this site to print QR labels.');
                    return;
                }

                // Build sticker HTML for each item
                function escapeHtml(str) {
                    if (!str) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function toDataUrl(qrCode, opts) {
                    return new Promise(function (resolve) {
                        if (typeof QRCode === 'undefined' || !qrCode) { resolve(''); return; }
                        try {
                            QRCode.toDataURL(qrCode, opts, function (err, url) {
                                resolve(err ? '' : url);
                            });
                        } catch (e) { resolve(''); }
                    });
                }

                var stickersHtml = '';
                for (var i = 0; i < itemsToPrint.length; i++) {
                    var item = itemsToPrint[i];
                    var qrDataUrl = await toDataUrl(item.qr_code, { width: 320, margin: 0, color: { dark: '#1d4ed8', light: '#ffffff' } });
                    var itemName = escapeHtml(item.item_name || 'INVENTORY ITEM');
                    var invNo    = escapeHtml(item.inventory_item_no || 'N/A');
                    var serialNo = escapeHtml(item.serial_number || 'Not recorded');
                    var category = escapeHtml((item.category && item.category.category_name) ? item.category.category_name : 'Uncategorized');
                    var qrText   = escapeHtml(item.qr_code || '');
                    var qrImgHtml = qrDataUrl
                        ? '<img src="' + qrDataUrl + '" alt="QR Code" style="width:115px;height:115px;display:block;" />'
                        : '<div style="width:115px;height:115px;display:flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;font-size:10px;color:#9ca3af;">NO QR</div>';

                    stickersHtml += '<div style="display:block;width:9cm;margin:0;border:2px solid #1d4ed8;background:#fff;padding:14px;page-break-inside:avoid;break-inside:avoid;">'
                        + '<div style="border-bottom:1.5px solid #dbeafe;padding-bottom:6px;text-align:center;">'
                        + '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1d4ed8;">Dian-ay National High School (NIR)</div>'
                        + '<div style="font-size:9px;font-weight:600;color:#6b7280;margin-top:1px;">Division of Negros Occidental</div>'
                        + '</div>'
                        + '<div style="display:flex;align-items:flex-start;gap:12px;margin-top:10px;">'
                        + '<div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:120px;">'
                        + qrImgHtml
                        + '<div style="margin-top:4px;font-family:monospace;font-size:8px;line-height:1.2;color:#9ca3af;text-align:center;word-break:break-all;">' + qrText + '</div>'
                        + '</div>'
                        + '<div style="flex:1;min-width:0;">'
                        + '<div style="font-size:13px;font-weight:800;text-transform:uppercase;color:#111827;line-height:1.25;margin-bottom:8px;word-break:break-word;">' + itemName + '</div>'
                        + '<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">ITEM NO.</span><span style="display:block;font-family:monospace;font-size:11px;font-weight:700;color:#2563eb;">' + invNo + '</span></div>'
                        + '<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">SERIAL NO.</span><span style="display:block;font-family:monospace;font-size:10px;font-weight:600;color:#1f2937;">' + serialNo + '</span></div>'
                        + '<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">CATEGORY</span><span style="display:block;font-size:10px;font-weight:600;color:#374151;">' + category + '</span></div>'
                        + '</div>'
                        + '</div>'
                        + '<div style="margin-top:10px;border-top:1px dashed #dbeafe;padding-top:6px;text-align:center;font-size:8px;font-weight:500;color:#9ca3af;">Scan this QR code to view item details &bull; DNHS Property Management System</div>'
                        + '</div>';
                }

                var doc = printWindow.document;
                doc.open();
                doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print QR - DNHS</title>'
                    + '<style>@page{size:auto;margin:0;}*{box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}body{display:grid;grid-template-columns:repeat(auto-fill,9cm);grid-auto-flow:row;gap:6mm;margin:0;padding:0;background:#fff;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;color:#111827;}</style>'
                    + '</head><body>' + stickersHtml + '</body></html>');
                doc.close();

                var printDocument = function () {
                    printWindow.focus();
                    printWindow.print();
                };

                printWindow.onload = function () {
                    setTimeout(function () {
                        printDocument();
                    }, 200);
                };

                setTimeout(function () {
                    try {
                        if (printWindow.document.readyState === 'complete') {
                            printDocument();
                        }
                    } catch (e) {}
                }, 500);
            };
        </script>
    @endpush

@endsection
