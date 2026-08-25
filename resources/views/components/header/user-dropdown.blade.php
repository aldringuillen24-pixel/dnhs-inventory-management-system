@props([])

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-1 text-left transition hover:border-brand-500 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
        @click="open = !open" aria-haspopup="true" :aria-expanded="open">
        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">
            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
        </div>
        <div class="hidden sm:block">
            <p class="text-xs font-medium leading-tight text-gray-700 dark:text-gray-200">{{ auth()->user()?->first_name . ' ' . auth()->user()?->last_name ?? 'User' }}</p>
            <p class="text-[12px] leading-tight text-gray-500 dark:text-gray-400">{{ auth()->user()?->email ?? 'user@example.com' }}</p>
        </div>
        <svg class="hidden h-3.5 w-3.5 text-gray-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 11.586l3.293-4.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open" x-cloak style="display: none;" x-transition class="absolute right-0 mt-1 w-full min-w-[105px] rounded-md border border-gray-200 bg-white p-0.5 shadow-md dark:border-gray-700 dark:bg-gray-900" role="menu">
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded px-2 py-1 text-xs font-medium text-gray-700 transition hover:bg-red-50 hover:text-red-600 dark:text-gray-300 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                <svg class="h-3.5 w-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</div>
