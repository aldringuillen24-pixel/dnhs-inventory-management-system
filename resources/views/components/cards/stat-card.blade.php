@props(['label' => null, 'value' => null, 'icon' => null, 'trend' => null, 'tone' => 'neutral', 'class' => ''])

@php
    $toneClasses = [
        'positive' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400',
        'negative' => 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400',
        'neutral' => 'bg-brand-50 text-brand-600 dark:bg-brand-900/20 dark:text-brand-400',
    ];

    $toneClass = $toneClasses[$tone] ?? $toneClasses['neutral'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 ' . $class]) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            @if ($label)
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
            @endif
            @if ($value)
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $toneClass }}">
                {!! $icon !!}
            </div>
        @endif
    </div>

    @if ($trend)
        <p class="mt-4 text-sm font-medium {{ $trend === 'up' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
            {{ $trend === 'up' ? '▲' : '▼' }} {{ $slot }}
        </p>
    @else
        @if (trim($slot)->isNotEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ $slot }}</p>
        @endif
    @endif
</div>
