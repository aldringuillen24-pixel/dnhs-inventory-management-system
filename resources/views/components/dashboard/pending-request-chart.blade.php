@props([
    'title' => 'Pending Requests',
    'subtitle' => 'Requests awaiting action by type',
    'requestData' => [],
    'labels' => [],
    'values' => [],
    'colors' => ['#2563eb', '#d97706', '#dc2626', '#0f766e', '#7c3aed', '#64748b'],
    'chartId' => 'pendingRequestChart',
    'chartClass' => 'min-h-[250px]',
    'emptyMessage' => 'No pending requests.',
    'detailsRoute' => null,
    'detailsLabel' => 'View details',
])

@php
    $requestData = collect($requestData);
    $colors = collect($colors)->filter()->values();

    if ($colors->isEmpty()) {
        $colors = collect(['#2563eb', '#d97706', '#dc2626', '#0f766e', '#7c3aed', '#64748b']);
    }

    if ($requestData->has('labels') || $requestData->has('values')) {
        $labels = $requestData->get('labels', $labels);
        $values = $requestData->get('values', $values);
    } elseif ($requestData->isNotEmpty() && $requestData->every(fn ($request) => is_scalar($request))) {
        $labels = $requestData->keys()->values();
        $values = $requestData->values();
    } elseif ($requestData->isNotEmpty()) {
        $labels = $requestData->map(fn ($request) => data_get($request, 'label', data_get($request, 'title', data_get($request, 'type'))))->values();
        $values = $requestData->map(fn ($request) => data_get($request, 'value', data_get($request, 'count', 0)))->values();
    }

    $labels = collect($labels)->values();
    $values = collect($values)->values();
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
        <div id="{{ $chartId }}" class="{{ $chartClass }}" data-pending-request-chart data-chart-type="donut" data-labels='@json($labels)' data-values='@json($values)' data-colors='@json($colors)'></div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2" aria-label="{{ $title }} legend">
            @foreach ($labels as $index => $label)
                <div class="flex items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-300">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $colors[$index % $colors->count()] }}"></span>
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
