@extends('layouts.app', ['title' => 'Reports' ])

@section('content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Operational inventory report</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor stock position, movement, lifecycle risk, and items requiring action.</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="date_from" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                <input id="date_from" name="date_from" type="date" value="{{ $reportFilters['date_from'] }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <div>
                <label for="date_to" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                <input id="date_to" name="date_to" type="date" value="{{ $reportFilters['date_to'] }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="filter" class="h-4 w-4"></i>Apply</button>
            <a href="{{ route('propertyCustodian.reports') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"><i data-lucide="rotate-ccw" class="h-4 w-4"></i>Reset</a>
        </form>
    </div>

    @php
        $kpiMetrics = [
            ['title' => 'Total Units', 'value' => number_format($metrics['totalUnits']), 'subtitle' => 'Active inventory', 'icon' => 'package', 'iconClass' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400', 'accentClass' => 'border-l-brand-500'],
            ['title' => 'Available', 'value' => number_format($metrics['availableUnits']), 'subtitle' => 'Ready for assignment', 'icon' => 'circle-check', 'iconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400', 'accentClass' => 'border-l-emerald-500'],
            ['title' => 'Assigned', 'value' => number_format($metrics['assignedUnits']), 'subtitle' => 'Currently in use', 'icon' => 'clipboard-check', 'iconClass' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400', 'accentClass' => 'border-l-indigo-500'],
            ['title' => 'Inventory Value', 'value' => 'PHP ' . number_format($metrics['totalValue'], 2), 'subtitle' => 'Recorded active value', 'icon' => 'banknote', 'iconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400', 'accentClass' => 'border-l-emerald-500'],
            ['title' => 'Needs Attention', 'value' => number_format($metrics['attentionUnits']), 'subtitle' => 'Maintenance or disposal', 'icon' => 'triangle-alert', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'accentClass' => 'border-l-amber-500'],
            ['title' => 'Low Stock', 'value' => number_format($metrics['lowStockGroups']), 'subtitle' => 'Groups at 3 units or less', 'icon' => 'package-search', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400', 'accentClass' => 'border-l-amber-500'],
        ];
    @endphp
    <div class="mt-6">
        <x-cards.kpi-summary :metrics="$kpiMetrics"  />
    </div>
    
    @php($liveForecastRows = collect($liveForecastResult['rows'] ?? [])->sortBy([['priority_rank', 'desc'], ['suggested_procurement', 'desc']]))
    <section class="mt-6 overflow-hidden rounded-xl border border-emerald-200 bg-white dark:border-emerald-900 dark:bg-gray-900" aria-labelledby="live-forecast-heading">
        <header class="flex flex-col gap-3 border-b border-emerald-100 p-4 dark:border-emerald-900 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="live-forecast-heading" class="text-lg font-semibold text-gray-900 dark:text-white">Production ML Demand Forecast</h2>
                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">ADVISORY ONLY</span>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Validated stored regression output with Laravel-calculated procurement priorities. No inventory is changed and no purchase order is created.</p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>Forecast period: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $liveForecastResult['forecast_period'] ?? 'Not available' }}</strong></span>
                    <span>Model: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $liveForecastResult['model_version'] ?? 'Not available' }}</strong></span>
                    <span>Generated: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ isset($liveForecastResult['generated_at']) ? \Illuminate\Support\Carbon::parse($liveForecastResult['generated_at'])->format('M j, Y g:i A') : 'Not available' }}</strong></span>
                </div>
            </div>
            <a href="{{ route('propertyCustodian.reports.forecast.recommendations') }}" class="inline-flex shrink-0 items-center gap-2 rounded-md border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950">View forecast details<i data-lucide="arrow-right" class="h-4 w-4"></i></a>
        </header>
        @if (in_array($liveForecastResult['status'] ?? null, ['error', 'stale', 'failed'], true))
            <p class="p-4 text-sm text-red-700 dark:text-red-300">{{ ($liveForecastResult['status'] ?? null) === 'stale' ? 'The stored ML forecast is stale. Refresh training before relying on it.' : 'The latest ML output is invalid or training failed. No alternative forecast was substituted.' }}</p>
        @elseif ($liveForecastRows->isEmpty())
            <p class="p-4 text-sm text-gray-600 dark:text-gray-300">{{ ($liveForecastResult['status'] ?? null) === 'missing' ? 'No trained production forecast is available yet.' : 'No eligible items have enough verified history for an ML forecast.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm">
                    <caption class="sr-only">Production ML demand forecasts and advisory procurement priorities</caption>
                    <thead class="bg-emerald-50 text-xs font-medium uppercase text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                        <tr><th scope="col" class="px-3 py-3">Item</th><th scope="col" class="px-3 py-3 text-right">Predicted demand</th><th scope="col" class="px-3 py-3 text-right">Available stock</th><th scope="col" class="px-3 py-3 text-right">Pending demand</th><th scope="col" class="px-3 py-3 text-right">Safety stock</th><th scope="col" class="px-3 py-3 text-right">Suggested quantity</th><th scope="col" class="px-3 py-3">Priority</th><th scope="col" class="px-3 py-3">Confidence</th><th scope="col" class="px-3 py-3">Advisory status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($liveForecastRows->take(10) as $row)
                            <tr>
                                <th scope="row" class="px-3 py-3 font-medium text-gray-900 dark:text-white">{{ $row['item_name'] }} <span class="block text-xs font-normal text-gray-500">Inventory ID {{ $row['inventory_id'] }} · {{ $row['category'] }}</span></th>
                                <td class="px-3 py-3 text-right">{{ $row['forecast_demand'] === null ? 'N/A' : $row['forecast_demand'].' '.$row['unit'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $row['available_stock'] }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $row['pending_demand'] }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right">{{ $row['safety_stock'] === null ? 'N/A' : $row['safety_stock'].' '.$row['unit'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ $row['suggested_procurement'] === null ? 'N/A' : $row['suggested_procurement'].' '.$row['unit'] }}</td>
                                <td class="px-3 py-3">{{ $row['priority'] }}</td>
                                <td class="px-3 py-3">{{ $row['confidence'] }}</td>
                                <td class="px-3 py-3">{{ $row['advisory_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @php($demoForecastRows = collect($demoForecastResult['rows'] ?? []))
    <section class="mt-6 overflow-hidden rounded-xl border border-sky-200 bg-white dark:border-sky-900 dark:bg-gray-900" aria-labelledby="demo-forecast-heading">
        <header class="flex flex-col gap-3 border-b border-sky-100 p-4 dark:border-sky-900 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="demo-forecast-heading" class="text-lg font-semibold text-gray-900 dark:text-white">Sample/Demo Demand Forecast</h2>
                    <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200">SAMPLE DATA ONLY</span>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Python regression output from sample stock-out history. Not live inventory; no stock or procurement recommendations are calculated.</p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>Forecast period: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $demoForecastResult['forecast_period'] ? \Illuminate\Support\Carbon::createFromFormat('!Y-m', $demoForecastResult['forecast_period'])->format('F Y') : 'Not available' }}</strong></span>
                    <span>Generated: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $demoForecastResult['generated_at'] ? \Illuminate\Support\Carbon::parse($demoForecastResult['generated_at'])->format('M j, Y g:i A') : 'Not available' }}</strong></span>
                </div>
            </div>
            @if ($demoForecastRows->isNotEmpty())
                <a href="{{ route('propertyCustodian.reports.forecast.recommendations', ['source' => 'demo']) }}" class="inline-flex shrink-0 items-center gap-2 rounded-md border border-sky-200 px-3 py-2 text-sm font-medium text-sky-800 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-200 dark:hover:bg-sky-950">View sample details<i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            @endif
        </header>
        @if (($demoForecastResult['status'] ?? null) === 'error')
            <p class="p-4 text-sm text-red-700 dark:text-red-300">Sample/Demo forecast output is invalid or unavailable. No live forecast was substituted.</p>
        @elseif (($demoForecastResult['status'] ?? null) === 'missing')
            <p class="p-4 text-sm text-gray-600 dark:text-gray-300">No Sample/Demo forecast has been generated yet.</p>
        @elseif ($demoForecastRows->isEmpty())
            <p class="p-4 text-sm text-gray-600 dark:text-gray-300">No eligible sample stock-out records were available for forecasting.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="bg-sky-50 text-xs font-medium uppercase text-sky-900 dark:bg-sky-950 dark:text-sky-200">
                        <tr><th scope="col" class="px-4 py-3">Sample identity</th><th scope="col" class="px-4 py-3">Category</th><th scope="col" class="px-4 py-3">Forecast month</th><th scope="col" class="px-4 py-3 text-right">Forecast demand</th><th scope="col" class="px-4 py-3">Verified history</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($demoForecastRows->take(5) as $row)
                            <tr>
                                <th scope="row" class="px-4 py-3 font-medium text-gray-900 dark:text-white">ID {{ $row['inventory_id'] }}: {{ $row['item_name'] }} <span class="block text-xs font-normal text-gray-500">Category ID {{ $row['category_id'] }}</span></th>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['category'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['forecast_month'])->format('F Y') }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $row['status'] === 'success' ? $row['forecast_demand'].' '.$row['unit'] : 'Insufficient history' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                    {{ $row['historical_months_used'] }} of {{ $row['required_months'] }} minimum months
                                    @if ($row['unknown_months'] !== [])
                                        <span class="block text-xs text-amber-700 dark:text-amber-300">Unknown: {{ collect($row['unknown_months'])->map(fn (string $month): string => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('F Y'))->implode(', ') }}</span>
                                    @else
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['history_window']['start_month'])->format('M Y') }} to {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['history_window']['end_month'])->format('M Y') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-6">
        <x-cards.base-card title="Stock movement trend" subtitle="Movement activity for the selected date range">
            @if ($movementData->isNotEmpty())
                <div id="custodianReportMovementChart" class="min-h-[300px]" data-labels='@json($movementData->pluck("label")->values())' data-stock-in='@json($movementData->pluck("stock_in")->values())' data-stock-out='@json($movementData->pluck("stock_out")->values())' data-disposals='@json($movementData->pluck("disposals")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No movement data available.</div>
            @endif
        </x-cards.base-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Stock status" subtitle="Current units by lifecycle state">
            @if ($statusData->isNotEmpty())
                <div id="custodianReportStatusChart" class="min-h-[300px]" data-labels='@json($statusData->pluck("label")->values())' data-values='@json($statusData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No status data available.</div>
            @endif
        </x-cards.base-card>

        <x-cards.base-card title="Units by category" subtitle="Largest active stock groups">
            @if ($categoryData->isNotEmpty())
                <div id="custodianReportCategoryChart" class="min-h-[300px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No category data available.</div>
            @endif
        </x-cards.base-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Lifecycle attention" subtitle="Units approaching or beyond expected useful life">
            @if ($lifecycleData->sum('quantity') > 0)
                <div id="custodianReportLifecycleChart" class="min-h-[300px]" data-labels='@json($lifecycleData->pluck("label")->values())' data-values='@json($lifecycleData->pluck("quantity")->values())'></div>
            @else
                <div class="flex min-h-[300px] items-center justify-center text-sm text-gray-500">No lifespan dates recorded.</div>
            @endif
        </x-cards.base-card>

        <x-cards.base-card title="Report highlights" subtitle="Items that may need follow-up">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Approaching end of life</p><p class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-200">{{ number_format($metrics['approachingLifespan']) }}</p><p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Units within the next 12 months</p></div>
                <div class="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10"><p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-300">Past expected end date</p><p class="mt-2 text-2xl font-semibold text-red-900 dark:text-red-200">{{ number_format($metrics['expiredLifespan']) }}</p><p class="mt-1 text-xs text-red-700 dark:text-red-300">Units requiring assessment</p></div>
                <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50 sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Disposed units</p><p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($metrics['disposedUnits']) }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Excluded from active inventory value and availability</p></div>
            </div>
        </x-cards.base-card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Low-stock watchlist" subtitle="Available groups with three units or fewer">
            <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Category</th><th class="pb-3 text-right font-medium">Available</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($lowStockData as $item)
                    <tr><td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item['item_name'] }}</td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $item['category'] }}</td><td class="py-3 text-right font-semibold text-amber-600 dark:text-amber-400">{{ number_format($item['quantity']) }} {{ $item['unit'] }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-gray-500">No low-stock groups found.</td></tr>
                @endforelse
            </tbody></table></div>
        </x-cards.base-card>

        <x-cards.base-card title="Attention queue" subtitle="Maintenance, inspection, and disposal candidates">
            <div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Status</th><th class="pb-3 text-right font-medium">Qty</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($attentionData as $item)
                    <tr><td class="py-3"><p class="font-medium text-gray-800 dark:text-gray-200">{{ $item['item_name'] }}</p><span class="text-xs text-gray-400">{{ $item['inventory_item_no'] ?: 'No item number' }}</span></td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $item['status'] }}</td><td class="py-3 text-right font-semibold text-gray-700 dark:text-gray-300">{{ number_format($item['quantity']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-gray-500">No attention items found.</td></tr>
                @endforelse
            </tbody></table></div>
        </x-cards.base-card>
    </div>

    <div class="mt-6"><x-cards.base-card title="Recent inventory activity" subtitle="Transactions in the selected date range"><div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm"><thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-400 dark:border-gray-800"><tr><th class="pb-3 font-medium">Item</th><th class="pb-3 font-medium">Inventory no.</th><th class="pb-3 font-medium">User</th><th class="pb-3 text-right font-medium">Qty</th><th class="pb-3 text-right font-medium">Date</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($recentTransactions as $transaction)
            <tr><td class="py-3 font-medium text-gray-800 dark:text-gray-200">{{ $transaction->item?->item_name ?? 'Unknown item' }}</td><td class="py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $transaction->item?->inventory_item_no ?? 'N/A' }}</td><td class="py-3 text-gray-500 dark:text-gray-400">{{ $transaction->user?->full_name ?? 'Unknown user' }}</td><td class="py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($transaction->quantity) }}</td><td class="py-3 text-right text-gray-500 dark:text-gray-400">{{ $transaction->transaction_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
        @empty
            <tr><td colspan="5" class="py-8 text-center text-gray-500">No transactions recorded in this date range.</td></tr>
        @endforelse
    </tbody></table></div></x-cards.base-card></div>
@endsection
