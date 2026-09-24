@php
    use App\Helpers\MenuHelper;

    $user = request()->user();
    $roleName = $user?->role?->role_name;
    $menuGroups = MenuHelper::getMenuGroups($roleName);
    $currentPath = request()->path();
@endphp

<aside
    id="sidebar"
    x-cloak
    x-data="{
        openSubmenus: {},
        toggleSubmenu(groupIndex, itemIndex) {
            const key = `${groupIndex}-${itemIndex}`;
            this.openSubmenus = this.openSubmenus[key] ? {} : { [key]: true };
        },
        isSubmenuOpen(groupIndex, itemIndex) {
            return Boolean(this.openSubmenus[`${groupIndex}-${itemIndex}`]);
        },
        isActive(path) {
            return window.location.pathname === path || '{{ $currentPath }}' === path.replace(/^\//, '');
        }
    }"
    class="fixed inset-y-0 left-0 z-99999 flex flex-col border-r border-gray-200 bg-white px-5 text-gray-900 shadow-sm transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
    :class="{
        'w-[240px]': $store.sidebar.isExpanded || $store.sidebar.isMobileOpen,
        'w-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isMobileOpen,
        'translate-x-0': $store.sidebar.isMobileOpen,
        '-translate-x-full xl:translate-x-0': !$store.sidebar.isMobileOpen
    }">
    <div class="flex-1 overflow-y-auto py-6 no-scrollbar">
        <div class="mb-6 border-b border-gray-200 pb-4 dark:border-gray-800">
            <a href="/" class="flex items-center gap-3 transition-all duration-200 hover:opacity-90"
               :class="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen ? 'justify-start' : 'justify-center'">
                <img src="{{ asset('images/logo/dnhs_school_logo.svg') }}" alt="DNHS Logo" class="h-8 w-8 shrink-0 object-contain" />
                <span x-show="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen"
                      class="text-sm  tracking-wide text-slate-800 dark:text-white">
                    DNHS Inventory Management System
                </span>
            </a>
        </div>
        <nav aria-label="Main navigation">
            <div class="space-y-7">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <section>
                        <h2 x-show="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen" class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            {{ $menuGroup['title'] }}
                        </h2>
                        <ul class="space-y-1">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                <li>
                                    @if (isset($item['subItems']))
                                        <button
                                            type="button"
                                            @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                            title="{{ $item['name'] }}"
                                            aria-label="{{ $item['name'] }}"
                                            class="menu-item group"
                                            :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'menu-item-active' : 'menu-item-inactive'">
                                            <span :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>
                                            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen" class="menu-item-text flex flex-1 items-center gap-2">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">New</span>
                                                @endif
                                            </span>
                                            <svg x-show="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen" class="h-4 w-4 transition-transform" :class="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) && ($store.sidebar.isExpanded || $store.sidebar.isMobileOpen)" x-transition>
                                            <ul class="ml-8 mt-1 space-y-1 border-l border-gray-200 pl-3 dark:border-gray-700">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <li>
                                                        <a href="{{ $subItem['path'] }}" class="menu-dropdown-item" :class="isActive('{{ $subItem['path'] }}') ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive'">
                                                            {{ $subItem['name'] }}
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <a href="{{ $item['path'] }}" @if ($item['path'] === '/property-custodian/inventory') onclick="window.location.replace(this.href); return false;" @endif title="{{ $item['name'] }}" aria-label="{{ $item['name'] }}" class="menu-item group" :class="isActive('{{ $item['path'] }}') ? 'menu-item-active' : 'menu-item-inactive'">
                                            <span :class="isActive('{{ $item['path'] }}') ? 'menu-item-icon-active' : 'menu-item-icon-inactive'">
                                                {!! MenuHelper::getIconSvg($item['icon']) !!}
                                            </span>
                                            <span x-show="$store.sidebar.isExpanded || $store.sidebar.isMobileOpen" class="menu-item-text flex items-center gap-2">
                                                {{ $item['name'] }}
                                                @if (!empty($item['new']))
                                                    <span class="rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">New</span>
                                                @endif
                                            </span>
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </nav>
    </div>
</aside>

<div x-show="$store.sidebar.isMobileOpen" x-transition.opacity @click="$store.sidebar.setMobileOpen(false)" class="fixed inset-0 z-50 bg-gray-900/50 xl:hidden"></div>
