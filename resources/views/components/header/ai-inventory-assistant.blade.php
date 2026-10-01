@php
    $assistantPolicy = app(\App\Services\AiCapabilityPolicy::class);
    $assistantUser = auth()->user();
    $assistantCapabilities = $assistantPolicy->capabilitiesForRole($assistantUser?->role?->role_name);
    $assistantWelcomeMessage = $assistantPolicy->assistantHelpText($assistantUser);
    $assistantQuestionSuggestions = collect([
        [\App\Services\AiCapabilityPolicy::VIEW_WAREHOUSE_AVAILABILITY, 'How many items are available?'],
        [\App\Services\AiCapabilityPolicy::VIEW_INVENTORY_STOCK, 'Show inventory stock'],
        [\App\Services\AiCapabilityPolicy::VIEW_PENDING_REQUESTS, 'How many pending requests?'],
        [\App\Services\AiCapabilityPolicy::VIEW_LOW_STOCK, 'Which items are low in stock?'],
        [\App\Services\AiCapabilityPolicy::VIEW_DEMAND_FORECAST, 'Explain the demand forecast'],
        [\App\Services\AiCapabilityPolicy::VIEW_PROCUREMENT_PRIORITIES, 'What should we purchase first?'],
        [\App\Services\AiCapabilityPolicy::VIEW_INVENTORY_LOCATION, 'Where is an item stored?'],
        [\App\Services\AiCapabilityPolicy::VIEW_MAINTENANCE, 'Show items needing maintenance'],
    ])->filter(fn (array $suggestion): bool => in_array($suggestion[0], $assistantCapabilities, true))
        ->take(8)
        ->pluck(1)
        ->values()
        ->all();
@endphp

@if ($assistantCapabilities !== [])
<div x-data="{
    open: false,
    showHistory: false,
    suggestionsExpanded: false,
    loading: false,
    requestController: null,
    comparisonCharts: {},
    activeConversationKey: @js('dnhs-ai-active-conversation.' . ($assistantUser?->id ?? 'guest')),
    inputMessage: '',
    welcomeMessage: @js($assistantWelcomeMessage),
    questionSuggestions: @js($assistantQuestionSuggestions),
    conversations: [],
    messages: [
        { sender: 'ai', text: @js($assistantWelcomeMessage), time: 'Now' }
    ],
    init() {
        this.conversations = JSON.parse(localStorage.getItem('dnhs-ai-conversations') || '[]');
        const activeMessages = JSON.parse(localStorage.getItem(this.activeConversationKey) || 'null');
        if (Array.isArray(activeMessages) && activeMessages.length) {
            this.messages = activeMessages;
        }
        this.$nextTick(() => this.messages.forEach((message, index) => {
            if (message.comparisonResult) this.renderComparisonChart(index, message.comparisonResult);
        }));
    },
    persistActiveConversation() {
        localStorage.setItem(this.activeConversationKey, JSON.stringify(this.messages));
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
        this.persistActiveConversation();
    },
    async resetServerContext() {
        const response = await fetch('{{ route('ai.chat.reset') }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        if (!response.ok) throw new Error('Unable to reset assistant context.');
    },
    async newChat() {
        if (this.loading) return;
        this.loading = true;
        try {
            await this.resetServerContext();
            this.saveConversation();
            this.messages = [
                { sender: 'ai', text: this.welcomeMessage, time: 'Now' }
            ];
            localStorage.removeItem(this.activeConversationKey);
            this.inputMessage = '';
            this.showHistory = false;
        } catch (error) {
            this.messages.push({ sender: 'ai', text: 'Unable to start a new chat. Please try again.', time: 'Now' });
        } finally {
            this.loading = false;
        }
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
    async loadConversation(conversation) {
        if (this.loading) return;
        this.loading = true;
        try {
            await this.resetServerContext();
            this.messages = conversation.messages;
            this.showHistory = false;
            this.$nextTick(() => this.messages.forEach((message, index) => {
                if (message.comparisonResult) this.renderComparisonChart(index, message.comparisonResult);
            }));
            this.scrollToBottom();
        } catch (error) {
            this.messages.push({ sender: 'ai', text: 'Unable to open this chat. Please try again.', time: 'Now' });
        } finally {
            this.loading = false;
        }
    },
    askSuggestion(question) {
        this.inputMessage = question;
        this.sendMessage();
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
    cancelRequest() {
        if (!this.loading || !this.requestController) return;

        this.requestController.abort();
        this.requestController = null;
        this.loading = false;
        this.scrollToBottom();
    },
    comparisonStatus(status, value) {
        if (status === 'unavailable') return 'history unavailable';
        if (status === 'verified_zero') return `${value} (verified zero activity)`;
        return `${value} (activity recorded)`;
    },
    comparisonPercentageStatus(row) {
        if (row.percentage_change_status === 'zero_denominator') return 'Unavailable (first period is zero)';
        if (row.percentage_change === null) return 'Unavailable';
        return `${row.percentage_change}%`;
    },
    comparisonMetricNeedsUnit(metric) {
        return ['stock_in_quantity', 'stock_out_quantity', 'request_quantity', 'assignment_quantity', 'transfer_quantity', 'disposal_quantity'].includes(metric);
    },
    cancelComparison(message) {
        message.comparisonForm = null;
        this.persistActiveConversation();
    },
    renderComparisonChart(index, result) {
        this.$nextTick(() => {
            const element = document.getElementById(`ai-comparison-chart-${index}`);
            if (!element || typeof window.renderAiComparisonChart !== 'function') return;
            this.comparisonCharts[index]?.destroy();
            this.comparisonCharts[index] = window.renderAiComparisonChart(element, result.chart, result.metric_label);
        });
    },
    async submitComparison(message, index) {
        if (this.loading) return;
        message.comparisonForm.error = '';
        this.loading = true;
        try {
            const response = await fetch('{{ route('ai.comparison') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    confirmed: true,
                    metric: message.comparisonForm.metric,
                    scope: message.comparisonForm.scope,
                    item_id: message.comparisonForm.item_id,
                    item_name: message.comparisonForm.item_name,
                    unit: message.comparisonForm.unit,
                    month_one: message.comparisonForm.month_one,
                    month_two: message.comparisonForm.month_two
                })
            });
            const data = await response.json();
            if (!response.ok) {
                message.comparisonForm.error = Object.values(data.errors || {}).flat()[0]
                    || data.message || 'Unable to calculate this comparison.';
                return;
            }
            message.comparisonForm.completed = true;
            this.messages.push({
                sender: 'ai',
                text: 'Here are the calculated results.',
                time: 'Now',
                comparisonResult: data.result
            });
            this.persistActiveConversation();
            this.saveConversation();
            this.renderComparisonChart(this.messages.length - 1, data.result);
            this.scrollToBottom();
        } catch (error) {
            message.comparisonForm.error = 'Connection error. Please try again.';
        } finally {
            this.loading = false;
        }
    },
    async sendMessage() {
        const message = this.inputMessage.trim();
        if (!message || this.loading) return;

        this.messages.push({ sender: 'user', text: message, time: 'Now' });
        this.inputMessage = '';
        this.persistActiveConversation();
        this.loading = true;
        const requestController = new AbortController();
        this.requestController = requestController;
        this.scrollToBottom();

        try {
            const response = await fetch('{{ route('ai.chat') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message }),
                signal: requestController.signal
            });
            const data = await response.json();
            this.messages.push({
                sender: 'ai',
                text: data.reply || data.message || 'I could not process that request.',
                time: 'Now',
                comparisonForm: data.comparison_form || null
            });
            this.saveConversation();
            this.scrollToBottom();
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.messages.push({ sender: 'ai', text: 'Connection error. Please try again.', time: 'Now' });
                this.scrollToBottom();
            }
        } finally {
            if (this.requestController === requestController) {
                this.requestController = null;
            }
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
                        <template x-if="message.comparisonForm && !message.comparisonForm.completed">
                            <form @submit.prevent="submitComparison(message, index)" class="mt-4 space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <label class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Metric
                                        <select x-model="message.comparisonForm.metric" @change="if (!comparisonMetricNeedsUnit(message.comparisonForm.metric)) message.comparisonForm.unit = null" required class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                            <option value="">Choose a metric</option>
                                            <template x-for="metric in message.comparisonForm.metrics" :key="metric.value">
                                                <option :value="metric.value" x-text="metric.label"></option>
                                            </template>
                                        </select>
                                    </label>
                                    <label class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Inventory scope
                                        <select x-model="message.comparisonForm.scope" @change="message.comparisonForm.unit = null; message.comparisonForm.item_id = null; message.comparisonForm.item_name = null" required class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                            <option value="">Choose a scope</option>
                                            <option value="all">All inventory</option>
                                            <option value="item">One item</option>
                                        </select>
                                    </label>
                                </div>
                                <label x-show="message.comparisonForm.scope === 'item' && message.comparisonForm.item_options.length" class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Matching inventory records
                                    <select x-model="message.comparisonForm.item_id" :required="message.comparisonForm.scope === 'item' && message.comparisonForm.item_options.length > 0" class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                        <option value="">Choose the exact record</option>
                                        <template x-for="item in message.comparisonForm.item_options" :key="item.value">
                                            <option :value="item.value" x-text="item.label"></option>
                                        </template>
                                    </select>
                                </label>
                                <label x-show="message.comparisonForm.scope === 'item' && !message.comparisonForm.item_options.length" class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Exact item name
                                    <input x-model="message.comparisonForm.item_name" @input="message.comparisonForm.item_id = null" :required="message.comparisonForm.scope === 'item'" maxlength="255" class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                </label>
                                <label x-show="message.comparisonForm.scope === 'all' && comparisonMetricNeedsUnit(message.comparisonForm.metric)" class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Inventory unit
                                    <input x-model="message.comparisonForm.unit" :list="`ai-comparison-units-${index}`" required maxlength="64" autocomplete="off" class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                    <datalist :id="`ai-comparison-units-${index}`">
                                        <template x-for="unit in message.comparisonForm.units" :key="unit"><option :value="unit"></option></template>
                                    </datalist>
                                </label>
                                <p x-show="message.comparisonForm.scope === 'item' && comparisonMetricNeedsUnit(message.comparisonForm.metric) && message.comparisonForm.item_unit" class="border-l-2 border-emerald-500 pl-2 text-xs text-gray-600 dark:text-gray-300" x-text="`Unit: ${message.comparisonForm.item_unit}`"></p>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <label class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">First month
                                        <input x-model="message.comparisonForm.month_one" @change="message.comparisonForm.month_one_label = null" type="month" max="{{ now()->subMonth()->format('Y-m') }}" required class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                        <span x-show="message.comparisonForm.month_one_label" x-text="message.comparisonForm.month_one_label" class="block text-xs font-normal text-gray-500 dark:text-gray-400"></span>
                                    </label>
                                    <label class="block space-y-1 text-xs font-semibold text-gray-700 dark:text-gray-200">Second month
                                        <input x-model="message.comparisonForm.month_two" @change="message.comparisonForm.month_two_label = null" type="month" max="{{ now()->subMonth()->format('Y-m') }}" required class="h-10 w-full rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                        <span x-show="message.comparisonForm.month_two_label" x-text="message.comparisonForm.month_two_label" class="block text-xs font-normal text-gray-500 dark:text-gray-400"></span>
                                    </label>
                                </div>
                                <p x-show="message.comparisonForm.error" x-text="message.comparisonForm.error" role="alert" aria-live="assertive" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"></p>
                                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-3 dark:border-gray-700">
                                    <button type="button" :disabled="loading" @click="cancelComparison(message)" class="h-10 rounded-md px-3 text-sm font-medium text-gray-600 transition hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                                    <button type="submit" :disabled="loading" :aria-busy="loading" class="h-10 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus-visible:ring-offset-gray-900" x-text="loading ? 'Calculating...' : 'Confirm and calculate'"></button>
                                </div>
                            </form>
                        </template>
                        <template x-if="message.comparisonResult">
                            <div role="status" aria-live="polite" class="mt-3 w-full border-t border-gray-200 pt-3 dark:border-gray-700">
                                <p class="text-xs font-semibold" x-text="`${message.comparisonResult.metric_label}: ${message.comparisonResult.scope_label}`"></p>
                                <div :id="`ai-comparison-chart-${index}`" aria-hidden="true" class="mt-2 min-h-[240px]"></div>
                                <table class="mt-2 w-full border-collapse text-left text-xs">
                                    <caption class="sr-only">Comparison totals and changes for each unit</caption>
                                    <thead><tr><th scope="col" class="p-1">Unit</th><th scope="col" class="p-1" x-text="message.comparisonResult.months[0].label"></th><th scope="col" class="p-1" x-text="message.comparisonResult.months[1].label"></th><th scope="col" class="p-1">Difference</th><th scope="col" class="p-1">Change</th></tr></thead>
                                    <tbody>
                                        <template x-for="row in message.comparisonResult.series" :key="row.unit">
                                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                                <th scope="row" class="p-1 font-medium" x-text="row.unit"></th>
                                                <td class="p-1" x-text="comparisonStatus(row.period_one_status, row.period_one)"></td>
                                                <td class="p-1" x-text="comparisonStatus(row.period_two_status, row.period_two)"></td>
                                                <td class="p-1" x-text="row.difference === null ? 'Unavailable' : row.difference"></td>
                                                <td class="p-1" x-text="comparisonPercentageStatus(row)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <div class="mt-5 border-l-2 border-emerald-600 pl-3 dark:border-emerald-500">
                                    <p class="text-[10px] font-semibold uppercase text-emerald-800 dark:text-emerald-300">AI insight</p>
                                    <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-200" x-text="message.comparisonResult.explanation"></p>
                                </div>
                            </div>
                        </template>
                        <span x-text="message.time" class="mt-1.5 block text-[10px] opacity-60"></span>
                    </div>
                </div>
            </template>
            <div x-show="loading" x-transition role="status" aria-live="polite" aria-label="Assistant is preparing a response" class="flex justify-start">
                <div class="flex items-center gap-3 rounded-2xl rounded-bl-sm border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    <span aria-hidden="true" class="flex items-center gap-1">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.3s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.15s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500"></span>
                    </span>
                    <span>Thinking...</span>
                </div>
            </div>
        </div>

        <form @submit.prevent="sendMessage()" class="border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div x-show="questionSuggestions.length" class="mb-3">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Try asking</p>
                    <button type="button" @click="suggestionsExpanded = !suggestionsExpanded" :aria-expanded="suggestionsExpanded" :aria-label="suggestionsExpanded ? 'Hide suggested questions' : 'Show suggested questions'" :title="suggestionsExpanded ? 'Hide suggested questions' : 'Show suggested questions'" class="flex h-6 w-6 items-center justify-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                        <i data-lucide="chevron-down" :class="{ 'rotate-180': suggestionsExpanded }" class="h-4 w-4 transition-transform"></i>
                    </button>
                </div>
                <div x-show="suggestionsExpanded" x-transition class="flex flex-wrap gap-2" role="group" aria-label="Suggested questions">
                    <template x-for="question in questionSuggestions" :key="question">
                        <button type="button" @click="askSuggestion(question)" :disabled="loading" x-text="question" class="shrink-0 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-200"></button>
                    </template>
                </div>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-1.5 focus-within:border-emerald-500 dark:border-gray-700 dark:bg-gray-950">
                <input x-model="inputMessage" :disabled="loading" type="text" placeholder="Ask about inventory..." class="min-w-0 flex-1 border-0 bg-transparent px-2 text-sm text-gray-900 outline-none focus:ring-0 dark:text-white" />
                <button x-show="!loading" type="submit" :disabled="!inputMessage.trim()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Send message" title="Send message">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                </button>
                <button x-show="loading" type="button" @click="cancelRequest()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white transition hover:bg-red-700" aria-label="Cancel chat request" title="Cancel chat request">
                    <i data-lucide="square" class="h-3.5 w-3.5 fill-current"></i>
                </button>
            </div>
        </form>
    </section>
</div>
@endif
