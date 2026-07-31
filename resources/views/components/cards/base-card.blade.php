@props(['title' => null, 'subtitle' => null, 'headerClass' => '', 'bodyClass' => '', 'class' => ''])

<div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 ' . $class]) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="mb-4 flex items-start justify-between gap-3 {{ $headerClass }}">
            <div>
                @if ($title)
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
            @if (isset($actions))
                <div>{{ $actions }}</div>
            @endif
        </div>
    @endif

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</div>
