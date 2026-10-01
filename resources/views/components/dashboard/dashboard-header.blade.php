@props([
    'title' => null,
    'description' => null,
    'breadcrumbTitle' => 'Dashboard',
    'date' => now(),
    'updatedAt' => null,
    'userName' => null,
    'roleLabel' => null,
    'showDate' => true,
    'showRefresh' => false,
    'refreshLabel' => 'Refresh dashboard',
    'showNotifications' => false,
    'notificationCount' => 0,
])

<header class="flex flex-col gap-4 border-b border-gray-200 pb-5 dark:border-gray-800 xl:flex-row xl:items-end xl:justify-between">
    <div>
        @if ($breadcrumbTitle)
            <x-common.page-breadcrumb :page-title="$breadcrumbTitle" />
        @elseif ($title)
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
        @endif
        @if ($description)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
        @if ($showDate)
            <div class="flex items-center gap-2">
                <i data-lucide="calendar-days" class="h-4 w-4"></i>
                <span>{{ $date instanceof \DateTimeInterface ? $date->format('F d, Y') : $date }}</span>
            </div>
        @endif
        @if ($updatedAt)
            <span class="hidden h-4 w-px bg-gray-200 dark:bg-gray-700 sm:block"></span>
            <span>{{ $updatedAt }}</span>
        @endif
        @if ($showRefresh)
            <button type="button" onclick="window.location.reload()" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:hover:border-brand-500" aria-label="{{ $refreshLabel }}" title="{{ $refreshLabel }}">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
            </button>
        @endif
        @if ($showNotifications)
            <div class="relative flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-500 dark:border-gray-700" aria-label="Notifications">
                <i data-lucide="bell" class="h-4 w-4"></i>
                @if ($notificationCount > 0)
                    <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">{{ $notificationCount }}</span>
                @endif
            </div>
        @endif
        @if ($userName || $roleLabel)
            <div class="hidden border-l border-gray-200 pl-3 dark:border-gray-700 sm:block">
                @if ($userName)
                    <p class="font-semibold text-gray-700 dark:text-gray-200">{{ $userName }}</p>
                @endif
                @if ($roleLabel)
                    <p>{{ $roleLabel }}</p>
                @endif
            </div>
        @endif
        @if (isset($actions))
            <div>{{ $actions }}</div>
        @endif
    </div>
</header>
