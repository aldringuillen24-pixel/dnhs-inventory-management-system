<div x-data="{
    open: false,
    showHistory: false,
    loading: false,
    inputMessage: '',
    conversations: [],
    messages: [
        { sender: 'ai', text: 'Hello! Ask me about stock, assignments, or procurement.', time: 'Now' }
    ],
    init() {
        this.conversations = JSON.parse(localStorage.getItem('dnhs-ai-conversations') || '[]');
    },
    saveConversation() {
        const userMessages = this.messages.filter(message => message.sender === 'user');
        if (!userMessages.length) return;

        const conversation = {
            id: Date.now(),
            title: userMessages[0].text.slice(0, 42),
            messages: this.messages,
            updatedAt: new Date().toLocaleString()
        };
        this.conversations = [conversation, ...this.conversations.filter(item => item.id !== conversation.id)].slice(0, 20);
        localStorage.setItem('dnhs-ai-conversations', JSON.stringify(this.conversations));
    },
    newChat() {
        this.saveConversation();
        this.messages = [
            { sender: 'ai', text: 'New chat started. How can I help with your inventory?', time: 'Now' }
        ];
        this.inputMessage = '';
        this.showHistory = false;
    },
    scrollToBottom() {
        this.$nextTick(() => {
            if (this.$refs.messagesContainer) {
                this.$refs.messagesContainer.scrollTo({
                    top: this.$refs.messagesContainer.scrollHeight,
                    behavior: 'smooth'
                });
            }
        });
    },
    loadConversation(conversation) {
        this.messages = conversation.messages;
        this.showHistory = false;
        this.scrollToBottom();
    },
    deleteConversation(id) {
        this.conversations = this.conversations.filter(conversation => conversation.id !== id);
        localStorage.setItem('dnhs-ai-conversations', JSON.stringify(this.conversations));
    },
    parseMarkdown(text) {
        if (!text) return '';
        if (typeof window.renderMarkdown === 'function') {
            return window.renderMarkdown(text);
        }
        if (typeof window.marked !== 'undefined') {
            const raw = window.marked.parse(text, { breaks: true, gfm: true });
            if (typeof window.DOMPurify !== 'undefined') {
                return window.DOMPurify.sanitize(raw);
            }
            return raw;
        }
        return text;
    },
    async sendMessage() {
        const message = this.inputMessage.trim();
        if (!message || this.loading) return;

        this.messages.push({ sender: 'user', text: message, time: 'Now' });
        this.inputMessage = '';
        this.loading = true;
        this.scrollToBottom();

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
            this.saveConversation();
            this.scrollToBottom();
        } catch (error) {
            this.messages.push({ sender: 'ai', text: 'Connection error. Please try again.', time: 'Now' });
            this.scrollToBottom();
        } finally {
            this.loading = false;
            this.scrollToBottom();
        }
    }
}" @keydown.escape.window="open = false" class="relative">
    <button
        type="button"
        @click="open = true; scrollToBottom()"
        class="relative flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        title="AI Inventory Assistant"
        aria-label="Open AI Inventory Assistant">
        <i data-lucide="bot" class="h-[18px] w-[18px]"></i>
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
            <div class="flex items-center gap-1">
                <button type="button" @click="newChat()" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-800 dark:hover:text-emerald-400" title="New chat" aria-label="New chat">
                    <i data-lucide="message-square-plus" class="h-4 w-4"></i>
                </button>
                <button type="button" @click="showHistory = !showHistory" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-800 dark:hover:text-emerald-400" title="Chat history" aria-label="Chat history">
                    <i data-lucide="history" class="h-4 w-4"></i>
                </button>
                <button type="button" @click="open = false" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" title="Close assistant" aria-label="Close assistant">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </div>

        <div x-show="showHistory" x-cloak class="flex-1 overflow-y-auto px-5 py-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Chat history</h3>
                <span class="text-xs text-gray-400" x-text="`${conversations.length} saved`"></span>
            </div>
            <div class="space-y-2">
                <template x-for="conversation in conversations" :key="conversation.id">
                    <div class="group flex items-center gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                        <button type="button" @click="loadConversation(conversation)" class="min-w-0 flex-1 text-left">
                            <span class="block truncate text-sm font-medium text-gray-700 dark:text-gray-200" x-text="conversation.title"></span>
                            <span class="mt-1 block text-[10px] text-gray-400" x-text="conversation.updatedAt"></span>
                        </button>
                        <button type="button" @click="deleteConversation(conversation.id)" class="rounded p-1 text-gray-400 hover:text-red-500" title="Delete conversation" aria-label="Delete conversation">
                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                        </button>
                    </div>
                </template>
                <p x-show="!conversations.length" class="py-10 text-center text-sm text-gray-400">No saved conversations.</p>
            </div>
        </div>

        <div x-ref="messagesContainer" x-show="!showHistory" class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-5">
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.sender === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="message.sender === 'user' ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-emerald-600 px-4 py-3 text-sm text-white' : 'max-w-[90%] rounded-2xl rounded-bl-sm border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200'">
                        <template x-if="message.sender === 'user'">
                            <p x-text="message.text" class="leading-6 whitespace-pre-wrap"></p>
                        </template>
                        <template x-if="message.sender !== 'user'">
                            <div x-html="parseMarkdown(message.text)" class="ai-markdown-content text-gray-800 dark:text-gray-200"></div>
                        </template>
                        <span x-text="message.time" class="mt-1.5 block text-[10px] opacity-60"></span>
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
                    <i data-lucide="send" class="h-4 w-4"></i>
                </button>
            </div>
        </form>
    </section>
</div>
