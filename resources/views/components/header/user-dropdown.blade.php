@props([])

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" class="flex items-center gap-3 rounded-md border border-gray-200 bg-white px-2 py-2 text-left transition hover:border-brand-500 dark:border-gray-700 dark:bg-gray-900"
        @click="open = !open" aria-haspopup="true" :aria-expanded="open">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">
            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
        </div>
        <div class="hidden sm:block">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ auth()->user()?->username ?? 'User' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()?->email ?? 'user@example.com' }}</p>
        </div>
        <svg class="hidden h-4 w-4 text-gray-500 sm:block dark:text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 11.586l3.293-4.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open" x-transition class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-900" role="menu">
        <a href="{{ route('admin.profile') }}" class="flex items-center rounded-md px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-200 dark:hover:bg-gray-800">
            Profile
        </a>
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-200 dark:hover:bg-gray-800">
                Logout
            </button>
        </form>
    </div>
</div>
