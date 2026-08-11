@props([])

<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        @click="open = !open"
        :aria-expanded="open.toString()"
        :class="open ? 'relative flex items-center justify-center text-white bg-brand-600 border border-brand-600 hover:bg-brand-700 dark:bg-brand-500 dark:border-brand-500' : 'relative flex items-center justify-center text-gray-500 bg-white border border-gray-200 hover:text-dark-900 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white'"
        class="transition-colors rounded-full h-11 w-11"
        aria-label="Open AI Assistant">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18.75 2.42969V7.70424M9.42261 13.673C10.0259 14.4307 10.9562 14.9164 12 14.9164C13.0438 14.9164 13.9742 14.4307 14.5775 13.673M20 12V18.5C20 19.3284 19.3284 20 18.5 20H5.5C4.67157 20 4 19.3284 4 18.5V12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M18.75 2.42969V2.43969M9.50391 9.875L9.50391 9.885M14.4961 9.875V9.885" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div x-show="open"
        class="fixed inset-0 z-40 bg-black/30"
        x-transition.opacity
        @click="open = false"></div>

    <section x-show="open"
        x-transition:enter="transition duration-300 transform"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-300 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col overflow-hidden border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950">
        <div class="flex items-center justify-between gap-3 px-4 py-4 border-b border-gray-200 dark:border-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">AI Inventory Assistant</p>
            </div>
            <button type="button" @click="open = false"
                class="rounded-full px-3 py-2 text-sm font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Close</button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
            <div class="space-y-3">
                <div class="rounded-md bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    <p class="font-medium">Welcome back!</p>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Ask anything about inventory transfers, stock status, or item details.</p>
                </div>
                <div class="rounded-md bg-brand-500/10 p-4 text-sm text-brand-700 dark:bg-brand-300/10 dark:text-brand-200">
                    <p class="font-medium">Example prompts</p>
                    <ul class="mt-2 list-disc space-y-1 pl-4 text-gray-600 dark:text-gray-300">
                        <li>"Show assets pending transfer"</li>
                        <li>"What is the status of serial number 12345?"</li>
                        <li>"Create a transfer request for laptops."</li>
                    </ul>
                </div>
            </div>
        </div>

        <form class="border-t border-gray-200 px-4 py-4 dark:border-gray-800">
            <label for="assistant-message" class="sr-only">Message</label>
            <div class="flex gap-2">
                <input id="assistant-message" type="text" placeholder="Type your message..."
                    class="min-w-0 flex-1 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-800 dark:bg-gray-900 dark:text-white" />
                <button type="button"
                    class="inline-flex items-center justify-center rounded-md bg-brand-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">Send</button>
            </div>
        </form>
    </section>
</div>

