<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/dnhs_school_logo.svg') }}">

    <title>{{ $title ?? 'Dashboard' }} | Dian-ay Inventory</title>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                        'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: window.innerWidth >= 1280 && localStorage.getItem('sidebar-expanded') !== 'false',
                isMobileOpen: false,
                isHovered: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    localStorage.setItem('sidebar-expanded', this.isExpanded ? 'true' : 'false');
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark', 'bg-gray-900');
            }
        })();
    </script>
    
</head>

<body
    x-data="{ loaded: false }"
    x-init="const finishLoading = () => loaded = true;
    window.addEventListener('load', finishLoading, { once: true });
    if (document.readyState === 'complete') finishLoading();
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = localStorage.getItem('sidebar-expanded') !== 'false';
        }
    };
    window.addEventListener('resize', checkMobile);">

    <x-common.toast-stack class="top-4 right-4">
        @if (session('success'))
            <x-common.toast type="success" title="Success" :message="session('success')" />
        @endif
        @if (session('error'))
            <x-common.toast type="error" title="Error" :message="session('error')" />
        @endif
        <x-common.toast x-cloak x-on:export-slips-success.window="show = true; setTimeout(() => show = false, 3000)" :visible="false" :autoHide="false" type="success" title="Success" message="User account slips downloaded successfully." />
    </x-common.toast-stack>

    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div x-cloak class="min-w-0 flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[240px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div x-show="!loaded" x-cloak class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6" aria-hidden="true">
                <div class="mb-6 flex items-center justify-between">
                    <div class="space-y-3">
                        <div class="h-7 w-48 animate-pulse rounded-lg bg-gray-200 dark:bg-gray-800"></div>
                        <div class="h-4 w-64 max-w-full animate-pulse rounded bg-gray-100 dark:bg-gray-900"></div>
                    </div>
                    <div class="hidden h-10 w-28 animate-pulse rounded-lg bg-gray-200 sm:block dark:bg-gray-800"></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @for ($index = 0; $index < 4; $index++)
                        <div class="h-28 animate-pulse rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-900"></div>
                    @endfor
                </div>
                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    <div class="h-80 animate-pulse rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-900"></div>
                    <div class="h-80 animate-pulse rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-800 dark:bg-gray-900"></div>
                </div>
            </div>
            <div x-show="loaded" x-cloak class="mx-auto max-w-(--breakpoint-2xl) p-4 pb-24 md:p-6 md:pb-24 xl:pb-6">
                @yield('content')
            </div>
        </div>

    </div>

</body>

@stack('scripts')

</html>
