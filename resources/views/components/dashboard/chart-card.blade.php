@props([
    'title' => null,
    'subtitle' => null,
    'chartId' => null,
    'chartData' => [],
    'badge' => null,
    'emptyMessage' => 'No chart data available.',
    'chartClass' => 'min-h-[250px]',
])

@php
    $chartData = collect($chartData);
    $hasData = $chartData->contains(fn ($value) => is_countable($value) ? count($value) > 0 : filled($value));
    if ($chartData->has('labels') && $chartData->has('values')) {
        $hasData = collect($chartData->get('labels'))->isNotEmpty() && collect($chartData->get('values'))->sum() > 0;
    }
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    @if ($title || $subtitle || $badge || isset($actions))
        <div class="flex items-start justify-between gap-3">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
            @if ($badge || isset($actions))
                <div class="flex shrink-0 items-center gap-2">
                    @if ($badge)
                        <span class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">{{ $badge }}</span>
                    @endif
                    @if (isset($actions))
                        {{ $actions }}
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($hasData && $chartId)
        <div id="{{ $chartId }}" class="{{ $chartClass }}" data-chart-type="donut" @foreach ($chartData as $key => $value) data-{{ $key }}='@json($value)' @endforeach></div>
    @else
        <div class="flex min-h-[250px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</div>
    @endif
</section>
