@props([
    'position' => 'center',
])

@php
    $positionClasses = match ($position) {
        'top-left' => 'top-4 left-4',
        'top-center' => 'top-4 left-1/2 -translate-x-1/2',
        'bottom-right' => 'bottom-4 right-4',
        'bottom-left' => 'bottom-4 left-4',
        'center' => 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
        default => 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
    };
@endphp

<div {{ $attributes->merge(['class' => 'fixed z-[60] mt-8 flex w-full max-w-sm flex-col gap-3 ' . $positionClasses]) }}>
    {{ $slot }}
</div>
