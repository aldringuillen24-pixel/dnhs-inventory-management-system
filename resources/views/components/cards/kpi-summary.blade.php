@props(['metrics' => []])

@php
    $metrics = collect($metrics);
@endphp

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($metrics as $metric)
        @php
            $route = data_get($metric, 'route');
            $href = $route && (str_starts_with($route, '/') || str_starts_with($route, '#') || str_contains($route, '://'))
                ? $route
                : ($route ? route($route) : null);
            $iconClass = data_get($metric, 'iconClass', 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400');
            $accentClass = data_get($metric, 'accentClass');
            if (! $accentClass) {
                $accentClass = match (true) {
                    str_contains($iconClass, 'indigo') => 'border-l-indigo-500',
                    str_contains($iconClass, 'amber') => 'border-l-amber-500',
                    str_contains($iconClass, 'sky') => 'border-l-sky-500',
                    str_contains($iconClass, 'rose'), str_contains($iconClass, 'red') => 'border-l-rose-500',
                    str_contains($iconClass, 'emerald') => 'border-l-emerald-500',
                    default => 'border-l-brand-500',
                };
            }
            $cardClasses = 'rounded-md border border-gray-200 border-l-4 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 ' . $accentClass . ' ' . data_get($metric, 'class', '');
        @endphp

        @if ($href)
            <a href="{{ $href }}" aria-label="View {{ data_get($metric, 'title') }}" class="group {{ $cardClasses }} transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
        @else
            <div class="{{ $cardClasses }}">
        @endif
            <div class="flex items-start justify-between gap-3">
                <div>
                    @if (data_get($metric, 'title'))
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ data_get($metric, 'title') }}</p>
                    @endif
                    @if (data_get($metric, 'value') !== null && data_get($metric, 'value') !== '')
                        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ data_get($metric, 'value') }}</p>
                    @endif
                    @if (data_get($metric, 'subtitle'))
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ data_get($metric, 'subtitle') }}</p>
                    @endif
                </div>
                @if (data_get($metric, 'icon'))
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $iconClass }}">
                        <i data-lucide="{{ data_get($metric, 'icon') }}" class="h-4 w-4"></i>
                    </div>
                @endif
            </div>
        @if ($href)
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>
