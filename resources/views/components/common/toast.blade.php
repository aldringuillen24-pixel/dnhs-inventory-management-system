@props(['type' => 'info', 'title' => null, 'message' => null, 'autoHide' => true, 'visible' => true])

@php
    $styles = [
        'success' => [
            'container' => 'border-green-200 bg-green-50 text-green-800 dark:border-green-800/60 dark:bg-green-900/30 dark:text-green-200',
            'icon' => 'text-green-600 dark:text-green-300',
        ],
        'error' => [
            'container' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-800/60 dark:bg-red-900/30 dark:text-red-200',
            'icon' => 'text-red-600 dark:text-red-300',
        ],
        'warning' => [
            'container' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/30 dark:text-amber-200',
            'icon' => 'text-amber-600 dark:text-amber-300',
        ],
        'info' => [
            'container' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/60 dark:bg-sky-900/30 dark:text-sky-200',
            'icon' => 'text-sky-600 dark:text-sky-300',
        ],
    ];

    $config = $styles[$type] ?? $styles['info'];
@endphp

<div x-data="{ show: @js($visible) }" @if($autoHide) x-init="setTimeout(() => show = false, 3000)" @endif x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" {{ $attributes->merge(['class' => 'pointer-events-auto w-80 max-w-full rounded-lg border px-4 py-3 shadow-lg ' . $config['container']]) }}>
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex-shrink-0">
            @if($type === 'success')
                <svg class="h-5 w-5 {{ $config['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            @elseif($type === 'error')
                <svg class="h-5 w-5 {{ $config['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            @else
                <svg class="h-5 w-5 {{ $config['icon'] }}" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 3a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            @if($title)
                <p class="text-sm font-semibold">{{ $title }}</p>
            @endif
            @if($message)
                <p class="mt-1 text-sm opacity-90">{{ $message }}</p>
            @endif
        </div>
    </div>
</div>
