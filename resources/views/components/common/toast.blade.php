@props([
    'type' => 'info',
    'title' => '',
    'message' => '',
    'icon' => null,
])

@php
    $typeClasses = match ($type) {
        'success' => 'border-success-200 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400',
        'error' => 'border-error-200 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400',
        'warning' => 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400',
        default => 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400',
    };

    $iconMarkup = $icon ?? match ($type) {
        'success' => '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 1.667a8.333 8.333 0 100 16.666A8.333 8.333 0 0010 1.667zm4.175 6.508l-4.992 5.175a.833.833 0 01-1.233-.03L5.825 9.167a.833.833 0 011.25-1.1l1.608 1.825 4.4-4.558a.833.833 0 011.092 1.241z" fill="currentColor"/></svg>',
        'error' => '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 1.667a8.333 8.333 0 100 16.666A8.333 8.333 0 0010 1.667zm1.25 12.5a1.25 1.25 0 11-2.5 0 1.25 1.25 0 012.5 0zm-.833-2.5a.833.833 0 01-1.667 0V6.667a.833.833 0 011.667 0v5z" fill="currentColor"/></svg>',
        'warning' => '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 1.667l8.333 15H1.667L10 1.667zm0 3.333L4.583 14.167h10.834L10 5zm-.833 2.5v4.167h1.666V7.5H9.167zm0 6.667v1.666h1.666v-1.666H9.167z" fill="currentColor"/></svg>',
        default => '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 1.667a8.333 8.333 0 100 16.666A8.333 8.333 0 0010 1.667zm.833 12.5h-1.666v-1.666h1.666v1.666zm0-3.333h-1.666V5.833h1.666v5z" fill="currentColor"/></svg>',
    };
@endphp

<div x-data="{ show: true }" x-show="show" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1" x-init="setTimeout(() => show = false, 3000)" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-2xl border px-4 py-4 shadow-lg shadow-gray-900/10 backdrop-blur-sm ' . $typeClasses]) }}>
    <div class="mt-0.5 flex-shrink-0">
        {!! $iconMarkup !!}
    </div>

    <div class="min-w-0 flex-1">
        @if($title)
            <p class="text-sm font-semibold">{{ $title }}</p>
        @endif
        @if($message)
            <p class="text-sm leading-5 {{ $title ? 'mt-1' : '' }}">{{ $message }}</p>
        @endif
        @if($slot->isNotEmpty())
            <div class="mt-2 text-sm leading-5">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
