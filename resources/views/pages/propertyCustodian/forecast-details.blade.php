@extends('layouts.app', ['title' => 'Demand Forecast Recommendations'])

@section('content')
    <x-common.page-breadcrumb :pageTitle="$isDemoForecast ? 'Sample/Demo Demand Forecast' : 'Demand Forecast Recommendations'" />

    @php
        $forecastStatus = $forecastResult['status'] ?? null;
        $forecastSummary = $forecastResult['summary'] ?? [];
        $priorityClasses = [
            'Urgent' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
            'High' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
            'Medium' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
            'Normal' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        ];
        $confidenceClasses = [
            'High' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
            'Medium' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
            'Low' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            @if ($isDemoForecast)
                <span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200">SAMPLE DATA ONLY</span>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sample-trained Python forecast, not live inventory. No available stock or procurement recommendation is included.</p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Next-month supply demand based on approved stock-out history.</p>
            @endif
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                <span>Forecast period: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ ($forecastResult['forecast_period'] ?? null) && $isDemoForecast ? \Illuminate\Support\Carbon::createFromFormat('!Y-m', $forecastResult['forecast_period'])->format('F Y') : ($forecastResult['forecast_period'] ?? 'Not available') }}</strong></span>
                <span>Generated: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ isset($forecastResult['generated_at']) ? \Illuminate\Support\Carbon::parse($forecastResult['generated_at'])->format('M j, Y g:i A') : 'Not available' }}</strong></span>
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('propertyCustodian.reports') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"><i data-lucide="arrow-left" class="h-4 w-4"></i>Reports</a>
            @unless ($isDemoForecast)
                <form method="POST" action="{{ route('propertyCustodian.reports.forecast') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="refresh-cw" class="h-4 w-4"></i>Reload latest result</button>
                </form>
            @endunless
        </div>
    </div>

    @if (in_array($forecastStatus, ['error', 'stale', 'failed'], true))
        <div class="rounded-lg border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10">
            <h2 class="text-base font-semibold text-red-800 dark:text-red-200">{{ $isDemoForecast ? 'Unable to load Sample/Demo forecast' : (($forecastStatus === 'stale') ? 'Stored forecast is stale' : 'Unable to load demand forecast') }}</h2>
            <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $isDemoForecast ? 'The demo output is invalid or unavailable. No live forecast was substituted.' : (($forecastStatus === 'stale') ? 'No stale forecast was used. Refresh model training before relying on a new result.' : (($forecastStatus === 'failed') ? 'The latest model training failed or was interrupted. No previous result was substituted.' : 'The latest forecast result could not be read. A valid refresh is required before displaying results.')) }}</p>
            @unless ($isDemoForecast)
                <form method="POST" action="{{ route('propertyCustodian.reports.forecast') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700"><i data-lucide="refresh-cw" class="h-4 w-4"></i>Reload latest result</button>
                </form>
            @endunless
        </div>
    @elseif (! is_array($forecastResult) || empty($forecastResult['rows']))
        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $isDemoForecast ? 'No Sample/Demo forecast available' : 'No demand forecast available' }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $isDemoForecast ? 'No demo result has been generated yet.' : 'No trained production ML forecast is available. Model training runs separately from chat and reports.' }}</p>
            @unless ($isDemoForecast)
                <form method="POST" action="{{ route('propertyCustodian.reports.forecast') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="refresh-cw" class="h-4 w-4"></i>Reload latest result</button>
                </form>
            @endunless
        </div>
    @else
        @if ($isDemoForecast)
            <div class="overflow-x-auto rounded-xl border border-sky-200 bg-white dark:border-sky-900 dark:bg-gray-900">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <caption class="sr-only">Sample/demo forecast results by stable inventory and category identity</caption>
                    <thead class="bg-sky-50 text-xs font-medium uppercase text-sky-900 dark:bg-sky-950 dark:text-sky-200">
                        <tr><th scope="col" class="px-4 py-3">Sample identity</th><th scope="col" class="px-4 py-3">Category</th><th scope="col" class="px-4 py-3">History window</th><th scope="col" class="px-4 py-3 text-right">Verified months</th><th scope="col" class="px-4 py-3 text-right">Forecast demand</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($recommendations as $row)
                            <tr>
                                <th scope="row" class="px-4 py-3 font-medium text-gray-900 dark:text-white">ID {{ $row['inventory_id'] }}: {{ $row['item_name'] }} <span class="block text-xs font-normal text-gray-500">Category ID {{ $row['category_id'] }}</span></th>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['category'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['history_window']['start_month'])->format('M Y') }} to {{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $row['history_window']['end_month'])->format('M Y') }}
                                    @if ($row['unknown_months'] !== [])<span class="block text-xs text-amber-700 dark:text-amber-300">Unknown: {{ collect($row['unknown_months'])->map(fn (string $month): string => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->format('M Y'))->implode(', ') }}</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-200">{{ $row['historical_months_used'] }} / {{ $row['required_months'] }} required</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $row['status'] === 'success' ? $row['forecast_demand'].' '.$row['unit'] : 'Insufficient history' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                {{ $recommendations->links() }}
            </div>
        @else
        @if ($forecastSummary['availability_warning'] ?? false)
            <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">Inventory availability appears incomplete or unrecorded. Verify current stock levels before using these procurement recommendations.</div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-2 xl:grid-cols-6">
                <div class="sm:col-span-2">
                    <label for="forecast-search" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Search item</label>
                    <input id="forecast-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Item name" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                </div>
                <div>
                    <label for="forecast-category" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Category</label>
                    <select id="forecast-category" name="category" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="forecast-priority" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Priority</label>
                    <select id="forecast-priority" name="priority" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">All priorities</option>
                        @foreach (['Urgent', 'High', 'Medium', 'Normal'] as $priority)
                            <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ $priority }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="forecast-confidence" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Confidence</label>
                    <select id="forecast-confidence" name="confidence" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="">All confidence</option>
                        @foreach (['High', 'Medium', 'Low'] as $confidence)
                            <option value="{{ $confidence }}" @selected(($filters['confidence'] ?? '') === $confidence)>{{ $confidence }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="forecast-sort" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Sort by</label>
                    <select id="forecast-sort" name="sort" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <option value="suggested" @selected(($filters['sort'] ?? 'suggested') === 'suggested')>Suggested quantity</option>
                        <option value="demand" @selected(($filters['sort'] ?? '') === 'demand')>Forecast demand</option>
                        <option value="available" @selected(($filters['sort'] ?? '') === 'available')>Available stock</option>
                        <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Item name</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 xl:col-span-6">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600"><i data-lucide="filter" class="h-4 w-4"></i>Apply filters</button>
                    <a href="{{ route('propertyCustodian.reports.forecast.recommendations') }}" class="inline-flex items-center rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Reset</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-medium uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr><th scope="col" class="px-3 py-3">Item</th><th scope="col" class="px-3 py-3">Category</th><th scope="col" class="px-3 py-3 text-right">Predicted demand</th><th scope="col" class="px-3 py-3 text-right">Available stock</th><th scope="col" class="px-3 py-3 text-right">Pending demand</th><th scope="col" class="px-3 py-3 text-right">Safety stock</th><th scope="col" class="px-3 py-3 text-right">Suggested quantity</th><th scope="col" class="px-3 py-3">Priority</th><th scope="col" class="px-3 py-3">Confidence</th><th scope="col" class="px-3 py-3">Advisory status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($recommendations as $row)
                            @php
                                $priorityClass = $priorityClasses[$row['priority']] ?? $priorityClasses['Normal'];
                                $confidenceClass = $confidenceClasses[$row['confidence']] ?? $confidenceClasses['Low'];
                            @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $row['item_name'] }}</span>
                                        <details class="relative">
                                            <summary class="flex h-6 w-6 cursor-pointer list-none items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:hover:bg-gray-800" aria-label="Why is {{ $row['item_name'] }} recommended?" title="Why is this recommended?"><i data-lucide="info" class="h-4 w-4"></i></summary>
                                            <div class="absolute left-0 top-7 z-20 w-72 rounded-md border border-gray-200 bg-white p-3 text-xs shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                                <p class="font-semibold text-gray-900 dark:text-white">Recommendation details</p>
                                                <dl class="mt-2 grid grid-cols-2 gap-1 text-gray-600 dark:text-gray-300">
                                                    <dt>Forecast demand</dt><dd class="text-right">{{ $row['forecast_demand'] ?? 'N/A' }} {{ $row['unit'] }}</dd>
                                                    <dt>Available stock</dt><dd class="text-right">{{ $row['available_stock'] }} {{ $row['unit'] }}</dd>
                                                    <dt>Safety stock</dt><dd class="text-right">{{ $row['safety_stock'] ?? 'N/A' }} {{ $row['unit'] }}</dd>
                                                    <dt>Pending demand</dt><dd class="text-right">{{ $row['pending_demand'] }} {{ $row['unit'] }}</dd>
                                                    <dt class="font-medium">Suggested</dt><dd class="text-right font-medium">{{ $row['suggested_procurement'] ?? 'N/A' }} {{ $row['unit'] }}</dd>
                                                </dl>
                                                <p class="mt-2 text-gray-500 dark:text-gray-400">Calculation: {{ $row['calculation_basis'] }}</p>
                                                <p class="mt-2 text-gray-500 dark:text-gray-400">{{ $row['explanation'] }}</p>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $row['category'] }}</td>
                                <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-200">{{ $row['forecast_demand'] ?? 'N/A' }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-200">{{ $row['available_stock'] }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-200">{{ $row['pending_demand'] }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right text-gray-700 dark:text-gray-200">{{ $row['safety_stock'] ?? 'N/A' }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ $row['suggested_procurement'] ?? 'N/A' }} {{ $row['unit'] }}</td>
                                <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $priorityClass }}">{{ $row['priority'] }}</span></td>
                                <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $confidenceClass }}">{{ $row['confidence'] }}</span></td>
                                <td class="px-3 py-3">{{ $row['advisory_status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No recommendations match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">
                {{ $recommendations->links() }}
            </div>
        </div>
        @endif
    @endif

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $isDemoForecast ? 'Sample/Demo outputs are not live inventory forecasts and do not calculate procurement recommendations.' : 'Forecasts are recommendations only. They do not automatically change inventory or create procurement orders.' }}</p>
@endsection