@props([
    'text' => 'Submit',
    'loadingText' => 'Loading...',
    'type' => 'submit',
    'loadOnClick' => false,
    'resetEvent' => null,
])

<button
    type="{{ $type }}"
    x-data="{ loading: false }"
    @if ($type === 'submit' || $loadOnClick)
        @click="if (!$el.form || $el.form.checkValidity()) setTimeout(() => loading = true, 0)"
    @endif
    @if ($resetEvent)
        x-on:{{ $resetEvent }}.window="loading = false"
    @endif
    :disabled="loading"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-md bg-brand-500 px-4 py-2 text-sm font-medium transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60']) }}
>
    <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" />
        <path class="opacity-75" fill="currentColor" d="M18 10a8 8 0 01-8 8v-2a6 6 0 006-6h2z" />
    </svg>
    <span x-text="loading ? @js($loadingText) : @js($text)">{{ $text }}</span>
</button>
