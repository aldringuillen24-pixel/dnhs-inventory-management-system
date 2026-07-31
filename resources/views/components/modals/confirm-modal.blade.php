@props(['title' => 'Confirm Action', 'message' => 'Are you sure you want to continue?', 'confirmText' => 'Confirm', 'cancelText' => 'Cancel', 'open' => false])

<x-modals.base-modal :title="$title" :subtitle="null" :open="$open" :closeButton="true">
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $message }}</p>
        <div class="flex justify-end gap-3">
            <button type="button" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                {{ $cancelText }}
            </button>
            <button type="button" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                {{ $confirmText }}
            </button>
        </div>
    </div>
</x-modals.base-modal>
