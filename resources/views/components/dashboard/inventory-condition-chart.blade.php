@props([
    'title' => 'Inventory Condition Summary',
    'subtitle' => 'Items requiring inspection, maintenance, or review',
    'labels' => [],
    'values' => [],
    'colors' => [],
    'chartId' => 'inventoryConditionChart',
    'chartClass' => 'min-h-[250px]',
    'emptyMessage' => 'No condition items require attention.',
    'detailsRoute' => null,
    'detailsLabel' => 'View details',
])

@php
    $labels = collect($labels)->values();
    $values = collect($values)->values();
    $providedColors = collect($colors)->filter()->values();
    $fallbackColors = ['#dc2626', '#d97706', '#475569', '#ea580c', '#7c3aed', '#64748b'];
    $chartColors = $labels->map(function ($label, $index) use ($providedColors, $fallbackColors) {
        if ($providedColors->has($index)) {
            return $providedColors->get($index);
        }

        $normalizedLabel = strtolower((string) $label);

        return match (true) {
            str_contains($normalizedLabel, 'damaged') => '#dc2626',
            str_contains($normalizedLabel, 'repair'), str_contains($normalizedLabel, 'maintenance') => '#d97706',
            str_contains($normalizedLabel, 'lost') => '#475569',
            str_contains($normalizedLabel, 'disposal') => '#ea580c',
            default => $fallbackColors[$index % count($fallbackColors)],
        };
    })->values();
    $hasData = $labels->isNotEmpty() && $values->sum() > 0;
    $detailsHref = $detailsRoute && (str_starts_with($detailsRoute, '/') || str_starts_with($detailsRoute, '#') || str_contains($detailsRoute, '://'))
        ? $detailsRoute
        : ($detailsRoute ? route($detailsRoute) : null);
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($detailsHref)
            <a href="{{ $detailsHref }}" class="shrink-0 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">{{ $detailsLabel }} <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>

    @if ($hasData)
        <div id="{{ $chartId }}" class="{{ $chartClass }}" data-inventory-condition-chart data-chart-type="donut" data-labels='@json($labels)' data-values='@json($values)' data-colors='@json($chartColors)'></div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2" aria-label="{{ $title }} legend">
            @foreach ($labels as $index => $label)
                <div class="flex items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-300">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $chartColors[$index] }}"></span>
                        <span class="truncate">{{ $label }}</span>
                    </span>
                    <span class="font-semibold tabular-nums text-gray-800 dark:text-gray-200">{{ number_format($values[$index] ?? 0) }}</span>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex min-h-[250px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</div>
    @endif
</section>
