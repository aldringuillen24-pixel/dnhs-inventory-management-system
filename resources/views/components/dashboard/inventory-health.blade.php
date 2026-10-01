@props([
    'title' => 'Inventory Health',
    'subtitle' => null,
    'items' => [],
    'actionLabel' => null,
    'actionRoute' => null,
    'emptyMessage' => 'No health data available.',
])

@php
    $actionHref = $actionRoute && (str_starts_with($actionRoute, '/') || str_starts_with($actionRoute, '#') || str_contains($actionRoute, '://'))
        ? $actionRoute
        : ($actionRoute ? route($actionRoute) : null);
@endphp

<section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($actionHref && $actionLabel)
            <a href="{{ $actionHref }}" class="shrink-0 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">{{ $actionLabel }} <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>

    @forelse ($items as $item)
        @php
            $itemRoute = data_get($item, 'route');
            $itemHref = $itemRoute && (str_starts_with($itemRoute, '/') || str_starts_with($itemRoute, '#') || str_contains($itemRoute, '://'))
                ? $itemRoute
                : ($itemRoute ? route($itemRoute) : null);
            $itemClasses = 'flex items-center gap-3 px-4 py-3';
        @endphp
        @if ($itemHref)
            <a href="{{ $itemHref }}" class="{{ $itemClasses }} transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
        @else
            <div class="{{ $itemClasses }}">
        @endif
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ data_get($item, 'iconClass', 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400') }}">
                <i data-lucide="{{ data_get($item, 'icon', 'activity') }}" class="h-4 w-4"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ data_get($item, 'value') }} <span class="font-normal text-gray-500 dark:text-gray-400">{{ data_get($item, 'title') }}</span></p>
                @if (data_get($item, 'subtitle'))
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ data_get($item, 'subtitle') }}</p>
                @endif
            </div>
        @if ($itemHref)
            </a>
        @else
            </div>
        @endif
    @empty
        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</div>
    @endforelse
</section>
