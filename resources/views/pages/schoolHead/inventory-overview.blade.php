@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory Overview" />

    @php
        $kpiMetrics = [
            ['title' => 'Total Units', 'value' => number_format($metrics['units']), 'subtitle' => 'Non-disposed stock', 'icon' => 'package', 'iconClass' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400'],
            ['title' => 'Available', 'value' => number_format($metrics['available']), 'subtitle' => 'Ready for assignment', 'icon' => 'package-check', 'iconClass' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400'],
            ['title' => 'Assigned', 'value' => number_format($metrics['assigned']), 'subtitle' => 'Currently in use', 'icon' => 'user-check', 'iconClass' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400'],
            ['title' => 'Low Stock Categories', 'value' => number_format($metrics['lowStock']), 'subtitle' => 'Three units or fewer', 'icon' => 'triangle-alert', 'iconClass' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400'],
            ['title' => 'Inventory Value', 'value' => 'PHP ' . number_format($metrics['value'], 2), 'subtitle' => 'Recorded value', 'icon' => 'coins', 'iconClass' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400'],
        ];
    @endphp

    <x-cards.kpi-summary :metrics="$kpiMetrics" />

    <div class="mt-6">
        <x-cards.base-card title="Stock by Category" subtitle="Compare total, available, and assigned units">
            <div id="schoolHeadInventoryCategoryChart" class="mb-6 min-h-[320px]" data-labels='@json($categories->pluck("name")->values())' data-total='@json($categories->pluck("total")->values())' data-available='@json($categories->pluck("available")->values())' data-assigned='@json($categories->pluck("assigned")->values())'></div>
            <div class="max-h-[32rem] overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
                <table class="min-w-[760px] w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Total Units</th>
                            <th class="px-4 py-3 text-right">Available</th>
                            <th class="px-4 py-3 text-right">Assigned</th>
                            <th class="px-4 py-3 text-right">Recorded Value</th>
                            <th class="px-4 py-3 text-center">Signal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($categories as $category)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $category['name'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($category['total']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($category['available']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($category['assigned']) }}</td>
                                <td class="px-4 py-3 text-right">PHP {{ number_format($category['value'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($category['available'] <= 3)
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Low stock</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">Healthy</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No inventory data available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-cards.base-card>
    </div>
@endsection
