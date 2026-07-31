<div id="preloader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/95 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/95" aria-hidden="true">
    <div class="flex flex-col items-center gap-4">
        <div class="relative h-16 w-16">
            <div class="absolute inset-0 animate-spin rounded-full border-4 border-gray-200 border-t-brand-500 dark:border-gray-700 dark:border-t-brand-400"></div>
            <div class="absolute inset-2 animate-spin rounded-full border-4 border-gray-100 border-t-brand-400 dark:border-gray-800 dark:border-t-brand-300" style="animation-direction: reverse; animation-duration: 1.2s;"></div>
        </div>
        <div class="text-center">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Loading inventory system...</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Please wait while we prepare everything</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const preloader = document.getElementById('preloader');

        if (!preloader) {
            return;
        }

        const hidePreloader = () => {
            preloader.classList.add('opacity-0', 'pointer-events-none');
            window.setTimeout(() => preloader.classList.add('hidden'), 300);
        };

        if (document.readyState === 'complete') {
            hidePreloader();
            return;
        }

        window.addEventListener('load', hidePreloader, { once: true });
        window.setTimeout(hidePreloader, 2200);
    });
</script>
@endpush
