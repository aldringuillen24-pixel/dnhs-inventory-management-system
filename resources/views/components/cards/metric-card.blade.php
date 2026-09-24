@props(['title' => null, 'value' => null, 'subtitle' => null, 'icon' => null, 'iconClass' => 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-400', 'class' => ''])

<div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 ' . $class]) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            @if ($title)
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
            @endif
            @if ($value !== null && $value !== '')
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
            @endif
            @if ($subtitle)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $iconClass }}">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
