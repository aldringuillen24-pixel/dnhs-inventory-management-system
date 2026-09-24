@php
    use App\Helpers\MenuHelper;

    $user = request()->user();
    $roleName = $user?->role?->role_name;
    $mobileItems = collect(MenuHelper::getMenuGroups($roleName))
        ->flatMap(fn ($group) => $group['items'])
        ->filter(fn ($item) => isset($item['path']) && !isset($item['subItems']))
        ->values();
    $primaryItems = $mobileItems->take(4);
    $moreItems = $mobileItems->slice(4);
@endphp

@if ($mobileItems->isNotEmpty())
    <nav x-data="{ moreOpen: false }" class="fixed inset-x-0 bottom-0 z-[1000] border-t border-gray-200 bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] pt-2 shadow-lg backdrop-blur xl:hidden dark:border-gray-800 dark:bg-gray-900/95" aria-label="Mobile navigation">
        <div class="mx-auto flex max-w-lg items-end justify-around gap-1">
            @foreach ($primaryItems as $item)
                <a href="{{ $item['path'] }}" @if ($item['path'] === '/property-custodian/inventory') onclick="window.location.replace(this.href); return false;" @endif class="flex min-w-0 flex-1 flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[11px] font-medium {{ MenuHelper::isActive($item['path']) ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400' }}" aria-label="{{ $item['name'] }}">
                    <span class="flex h-6 w-6 items-center justify-center {{ MenuHelper::isActive($item['path']) ? 'rounded-md bg-brand-50 dark:bg-brand-500/15' : '' }}">
                        {!! MenuHelper::getIconSvg($item['icon']) !!}
                    </span>
                    <span class="max-w-full truncate">{{ $item['name'] }}</span>
                </a>
            @endforeach

            @if ($moreItems->isNotEmpty())
                <div class="relative min-w-0 flex-1">
                    <button type="button" @click="moreOpen = !moreOpen" class="flex w-full flex-col items-center gap-1 rounded-md px-1 py-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400" :class="moreOpen ? 'text-brand-600 dark:text-brand-400' : ''" aria-label="More navigation" :aria-expanded="moreOpen.toString()">
                        <span class="flex h-6 w-6 items-center justify-center"><i data-lucide="ellipsis" class="h-5 w-5"></i></span>
                        <span>More</span>
                    </button>
                    <div x-show="moreOpen" x-cloak x-transition @click.outside="moreOpen = false" class="absolute bottom-14 right-0 w-48 rounded-md border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                        @foreach ($moreItems as $item)
                            <a href="{{ $item['path'] }}" class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium {{ MenuHelper::isActive($item['path']) ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-700 dark:text-gray-200' }}">
                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                <span class="truncate">{{ $item['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </nav>
@endif
