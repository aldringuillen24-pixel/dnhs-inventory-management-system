<div x-data="{
    open: false,
    loading: false,
    inputMessage: '',
    messages: [
        { sender: 'ai', text: 'Hello! Ask me about stock, assignments, or procurement.', time: 'Now' }
    ],
    async sendMessage() {
        const message = this.inputMessage.trim();
        if (!message || this.loading) return;

        this.messages.push({ sender: 'user', text: message, time: 'Now' });
        this.inputMessage = '';
        this.loading = true;

        try {
            const response = await fetch('{{ route('ai.chat') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message, history: this.messages })
            });
            const data = await response.json();
            this.messages.push({
                sender: 'ai',
                text: data.reply || data.message || 'I could not process that request.',
                time: 'Now'
            });
        } catch (error) {
            this.messages.push({ sender: 'ai', text: 'Connection error. Please try again.', time: 'Now' });
        } finally {
            this.loading = false;
        }
    }
}" @keydown.escape.window="open = false" class="relative">
    <button
        type="button"
        @click="open = true"
        class="relative flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        title="AI Inventory Assistant"
        aria-label="Open AI Inventory Assistant">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M18.75 2.42969V7.70424M9.42261 13.673C10.0259 14.4307 10.9562 14.9164 12 14.9164C13.0438 14.9164 13.9742 14.4307 14.5775 13.673M20 12V18.5C20 19.3284 19.3284 20 18.5 20H5.5C4.67157 20 4 19.3284 4 18.5V12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M18.75 2.42969V2.43969M9.50391 9.875L9.50391 9.885M14.4961 9.875V9.885" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div x-show="open" x-cloak style="display: none;" x-transition.opacity class="fixed inset-0 z-[1000] bg-black/40" @click="open = false"></div>

    <section
        x-show="open"
        x-cloak
        style="display: none;"
        x-transition:enter="transform transition duration-300 ease-out"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition duration-200 ease-in"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-[1050] flex w-full max-w-xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <div>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">AI Inventory Assistant</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Inventory support</p>
            </div>
            <button
                type="button"
                @click="open = false"
                class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                title="Close assistant"
                aria-label="Close assistant">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-5">
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.sender === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="message.sender === 'user' ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-emerald-600 px-4 py-3 text-sm text-white' : 'max-w-[85%] rounded-2xl rounded-bl-sm border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200'">
                        <p x-text="message.text" class="leading-6"></p>
                        <span x-text="message.time" class="mt-1 block text-[10px] opacity-60"></span>
                    </div>
                </div>
            </template>
            <div x-show="loading" class="flex items-center gap-2 text-xs text-gray-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
                Thinking...
            </div>
        </div>

        <form @submit.prevent="sendMessage()" class="border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-1.5 focus-within:border-emerald-500 dark:border-gray-700 dark:bg-gray-950">
                <input x-model="inputMessage" :disabled="loading" type="text" placeholder="Ask about inventory..." class="min-w-0 flex-1 border-0 bg-transparent px-2 text-sm text-gray-900 outline-none focus:ring-0 dark:text-white" />
                <button type="submit" :disabled="loading || !inputMessage.trim()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Send message">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>
                </button>
            </div>
        </form>
    </section>
</div>
