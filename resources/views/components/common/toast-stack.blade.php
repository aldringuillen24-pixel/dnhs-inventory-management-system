<div {{ $attributes->merge(['class' => 'fixed inset-x-0 top-4 z-[1000] flex justify-center pointer-events-none']) }}>
    <div class="flex flex-col items-center gap-3">
        {{ $slot }}
    </div>
</div>
