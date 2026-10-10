<template>
  <section
    class="flex h-full min-h-0 flex-col border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/60 dark:bg-emerald-950/20 lg:border-r"
    aria-label="AI decision support"
  >
    <!-- Header -->
    <header class="shrink-0 border-b border-emerald-200/80 px-3.5 py-2.5 dark:border-emerald-900/60">
      <div class="flex items-center gap-2">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-emerald-600 text-white">
          <LucideIcon :icon="Sparkles" class="h-3.5 w-3.5" />
        </span>
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-sm font-bold text-emerald-950 dark:text-emerald-50">AI Decision Support</h2>
          <p class="text-[11px] leading-tight text-emerald-800/80 dark:text-emerald-300/80">
            {{ forecastPeriod || 'Current forecast' }} · all {{ totalItems }} items
            <span v-if="tableFiltered" class="block text-amber-700 dark:text-amber-400">
              ignores the table filter
            </span>
          </p>
        </div>
        <button
          v-if="messages.length"
          type="button"
          class="shrink-0 rounded p-1 text-emerald-700/60 hover:bg-emerald-200/60 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300/60 dark:hover:bg-emerald-900/50 dark:hover:text-emerald-100"
          :disabled="busy"
          title="Clear conversation"
          aria-label="Clear conversation"
          @click="clearThread"
        >
          <LucideIcon :icon="RotateCcw" class="h-3.5 w-3.5" />
        </button>
        <button
          type="button"
          class="shrink-0 rounded p-1 text-emerald-700/60 hover:bg-emerald-200/60 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300/60 dark:hover:bg-emerald-900/50 dark:hover:text-emerald-100"
          :disabled="busy"
          title="New chat"
          aria-label="New chat"
          @click="newChat"
        >
          <LucideIcon :icon="MessageSquarePlus" class="h-3.5 w-3.5" />
        </button>
        <button
          v-if="conversations.length > 0"
          type="button"
          class="shrink-0 rounded p-1 text-emerald-700/60 hover:bg-emerald-200/60 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300/60 dark:hover:bg-emerald-900/50 dark:hover:text-emerald-100"
          :disabled="busy"
          title="Chat history"
          aria-label="Chat history"
          @click="showHistory = !showHistory"
        >
          <LucideIcon :icon="History" class="h-3.5 w-3.5" />
        </button>
      </div>
    </header>

    <!-- Thread -->
    <!-- Saved chats -->
    <div v-if="showHistory" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-3">
      <div class="flex items-center justify-between">
        <h3 class="text-xs font-bold text-emerald-950 dark:text-emerald-50">Chat history</h3>
        <span class="text-[10px] text-emerald-700/70 dark:text-emerald-400/70">{{ conversations.length }} saved</span>
      </div>
      <div v-if="conversations.length === 0" class="text-xs text-emerald-700/70 dark:text-emerald-300/70">No saved conversations.</div>
      <div v-else class="space-y-2">
        <div v-for="conversation in conversations" :key="conversation.id" class="group flex cursor-pointer items-center gap-2 rounded-md border border-emerald-200 bg-white p-2.5 transition hover:border-emerald-400 hover:bg-emerald-100/60 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:hover:border-emerald-500/60">
          <button type="button" :disabled="busy" @click="loadConversation(conversation)" class="min-w-0 flex-1 text-left disabled:opacity-50">
            <span class="block truncate text-xs font-semibold text-emerald-900 dark:text-emerald-100">{{ conversation.title }}</span>
            <span class="mt-0.5 block text-[10px] text-emerald-700/70 dark:text-emerald-400/70">{{ conversation.updatedAt }}</span>
          </button>
          <button type="button" :disabled="busy" @click="deleteConversation(conversation.id)" class="rounded p-1 text-emerald-700/60 hover:text-red-500 disabled:opacity-50 dark:text-emerald-300/60" title="Delete conversation" aria-label="Delete conversation">
            <LucideIcon :icon="Trash2" class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>
    <div v-else ref="logRef" class="min-h-0 flex-1 space-y-2.5 overflow-y-auto px-3 py-3" aria-live="polite">
      <!-- Intro: flags risk now that the Confidence column is off the table -->
      <div class="rounded-md border border-emerald-200 bg-white px-3 py-2.5 text-xs leading-relaxed text-emerald-800 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
        <p class="font-semibold text-emerald-900 dark:text-emerald-100">{{ openerHeadline }}</p>
        <p class="mt-1">{{ openerDetail }}</p>
      </div>

      <div
        v-for="message in messages"
        :key="message.key"
        class="flex"
        :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
      >
        <div
          class="max-w-[92%] rounded-md px-3 py-2 text-xs leading-relaxed shadow-sm"
          :class="message.role === 'user'
            ? 'bg-emerald-600 text-white'
            : 'border border-emerald-200 bg-white text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-100'"
        >
          <p class="whitespace-pre-line" v-html="renderMarkdown(message.text)"></p>

          <!-- Only shown when a provider was genuinely involved and its answer was
               not used. Messages written by this UI itself (greetings, out-of-scope
               guidance) carry no source and are never labelled a fallback. -->
          <p v-if="sourceNote(message)" class="mt-1.5 text-[10px] italic opacity-70">
            {{ sourceNote(message) }}
          </p>

          <!-- Only when a requested count exceeded the chat ceiling. -->
          <p v-if="message.notice" class="mt-1.5 text-[10px] italic opacity-70">
            {{ message.notice }}
          </p>

          <!-- Export: the AI orders, the forecast supplies the numbers. -->
          <div
            v-if="message.role === 'ai' && message.inventoryIds.length"
            class="mt-2 border-t pt-2 dark:border-white/10"
          >
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded border border-emerald-300 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-950/70"
              :disabled="exportingKey === message.key"
              @click="exportList(message)"
            >
              <LucideIcon
                :icon="FileDown"
                class="h-3 w-3"
                :class="{ 'animate-pulse': exportingKey === message.key }"
              />
              {{ exportingKey === message.key ? 'Preparing…' : `Export list as PDF (${message.inventoryIds.length})` }}
            </button>
          </div>
        </div>
      </div>

      <!-- Typing indicator -->
      <div v-if="busy" class="flex justify-start">
        <div class="rounded-md border border-emerald-200 bg-white px-3 py-2 text-xs text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
          <span class="inline-flex items-center gap-1">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600"></span>
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600 [animation-delay:150ms]"></span>
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600 [animation-delay:300ms]"></span>
          </span>
        </div>
      </div>
    </div>

    <!-- Quick prompts -->
    <div v-if="!hasForecast" class="shrink-0 px-3 py-3 text-xs text-emerald-800/80 dark:text-emerald-300/80">
      Decision support needs a trained forecast. Refresh model training, then ask again.
    </div>
    <div v-else class="shrink-0 border-t border-emerald-200/80 px-3 py-2.5 dark:border-emerald-900/60">
      <div class="mb-2 flex flex-wrap gap-1.5">
        <button
          v-for="prompt in quickPrompts"
          :key="prompt.type"
          type="button"
          class="rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-[11px] font-medium text-emerald-700 hover:border-emerald-400 hover:bg-emerald-100 hover:text-emerald-900 disabled:opacity-50 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:border-emerald-500/60 dark:hover:bg-emerald-900/50 dark:hover:text-emerald-100"
          :disabled="busy"
          @click="askPrompt(prompt)"
        >
          {{ prompt.label }}
        </button>
      </div>

      <form class="flex items-end gap-1.5" @submit.prevent="submitQuestion">
        <textarea
          v-model="question"
          rows="2"
          placeholder="Ask about the current forecast…"
          aria-label="Ask about the current forecast"
          class="min-h-[2.5rem] w-full resize-none rounded-md border border-emerald-200 bg-white px-2.5 py-1.5 text-xs text-emerald-900 placeholder:text-emerald-500 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100 dark:placeholder:text-emerald-500/80"
          @keydown.enter.exact.prevent="submitQuestion"
        ></textarea>
        <button
          type="submit"
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="busy || !question.trim()"
          aria-label="Send question"
          title="Send question"
        >
          <LucideIcon :icon="Send" class="h-4 w-4" />
        </button>
      </form>
      <p class="mt-1.5 text-[10px] leading-snug text-emerald-700/70 dark:text-emerald-400/70">
        Advisory only. Nothing here creates orders or changes stock.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { FileDown, History, MessageSquarePlus, RotateCcw, Send, Sparkles, Trash2 } from 'lucide';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

const props = defineProps({
    forecastPeriod: { type: String, default: null },
    hasForecast: { type: Boolean, default: false },
    itemsNeedingProcurement: { type: Number, default: 0 },
    lowConfidenceCount: { type: Number, default: 0 },
    // Suggestions (rows with a procurement gap) resting on weak history, and
    // rows with no estimate at all. Both are scoped so the warning describes
    // actionable work rather than every row in the forecast.
    gapsNeedingVerification: { type: Number, default: 0 },
    insufficientHistoryCount: { type: Number, default: 0 },
    totalItems: { type: Number, default: 0 },
    // True when the table's filter panel has narrowed the visible rows. The
    // assistant still reads the whole forecast, and says so rather than
    // silently answering a different question than the user expects.
    tableFiltered: { type: Boolean, default: false },
    // Only identity is needed, to route "why is <item> …" to the single-item
    // explanation endpoint. Quantities always come from the server.
    items: { type: Array, default: () => [] },
});

const quickPrompts = [
    { type: 'next_steps', label: 'What next?' },
    { type: 'purchase_first', label: 'Purchase first?' },
    { type: 'deferrable', label: 'What can wait?' },
    { type: 'verify_first', label: 'Needs verification?' },
];

const questions = {
    next_steps: 'What step should I do next?',
    purchase_first: 'What should we purchase first?',
    deferrable: 'What can wait?',
    verify_first: 'Which rows need verification first?',
};

const logRef = ref(null);
const messages = ref([]);
const question = ref('');
const busy = ref(false);
const exportingKey = ref(null);
let keySeed = 0;

const authStore = useAuthStore();

// Saved threads, same model as the inventory assistant: per-user localStorage,
// at most 20, titled by the first user message. Unlike the assistant, the live
// thread is never restored — a page refresh always starts empty.
const showHistory = ref(false);
const conversations = ref([]);

function historyKey() {
    return `dnhs-forecast-conversations.${authStore.user?.id ?? 'guest'}`;
}

function loadConversations() {
    try {
        conversations.value = JSON.parse(localStorage.getItem(historyKey()) || '[]');
    } catch {
        conversations.value = [];
    }
}

function persistConversations() {
    localStorage.setItem(historyKey(), JSON.stringify(conversations.value));
}

// Archive the current thread, if it asked anything. Tab switches clear
// without archiving (see clearThread), so the history holds conversations,
// not fragments.
function archiveConversation() {
    const userMessages = messages.value.filter((message) => message.role === 'user');

    if (userMessages.length === 0) return;

    const conversation = {
        id: Date.now(),
        title: String(userMessages[0].text ?? '').slice(0, 42),
        messages: messages.value,
        updatedAt: new Date().toLocaleString(),
    };

    conversations.value = [conversation, ...conversations.value.filter((item) => item.id !== conversation.id)].slice(0, 20);
    persistConversations();
}

async function newChat() {
    if (busy.value) return;

    busy.value = true;

    try {
        // Clears the server-side forecast context, so the archived thread's
        // follow-up state cannot leak into the next conversation.
        await api.post('/ai/chat/reset', {}, { skipToast: true });
        archiveConversation();
        messages.value = [];
        lastAnswerItems.value = [];
        lastPromptType.value = 'purchase_first';
        question.value = '';
        showHistory.value = false;
    } catch {
        pushAssistant('Unable to start a new chat. Please try again.');
    } finally {
        busy.value = false;
        scrollToBottom();
    }
}

async function loadConversation(conversation) {
    if (busy.value) return;

    busy.value = true;

    try {
        await api.post('/ai/chat/reset', {}, { skipToast: true });
        // Re-key so restored messages can never collide with new ones.
        messages.value = (conversation.messages ?? []).map((message) => ({ ...message, key: nextKey() }));

        // Rebuild the follow-up context from the replayed answer, so "the
        // second one" keeps working on a reopened thread. Names come from the
        // current forecast, never from the saved text.
        const lastAnswered = [...messages.value].reverse().find(
            (message) => message.role === 'ai' && Array.isArray(message.inventoryIds) && message.inventoryIds.length > 0,
        );

        if (lastAnswered) {
            lastAnswerItems.value = lastAnswered.inventoryIds.map((id) => ({
                inventory_id: id,
                item_name: props.items.find((row) => Number(row.inventory_id) === Number(id))?.item_name ?? '',
            }));
            lastPromptType.value = typeof lastAnswered.promptType === 'string' && lastAnswered.promptType !== ''
                ? lastAnswered.promptType
                : 'purchase_first';
        } else {
            lastAnswerItems.value = [];
            lastPromptType.value = 'purchase_first';
        }

        showHistory.value = false;
    } catch {
        pushAssistant('Unable to open this chat. Please try again.');
    } finally {
        busy.value = false;
        scrollToBottom();
    }
}

function deleteConversation(id) {
    conversations.value = conversations.value.filter((conversation) => conversation.id !== id);
    persistConversations();
}

onMounted(() => {
    loadConversations();
});

// Reload per-user history when the signed-in user changes.
watch(
    () => authStore.user?.id,
    () => {
        loadConversations();
    },
);

// Returning from the history list re-creates the log element, so re-anchor to
// the latest message the same way the inventory assistant does.
watch(showHistory, (historyVisible) => {
    if (!historyVisible) {
        scrollToBottom();
    }
});

const openerHeadline = computed(() => {
    if (! props.hasForecast) {
        return 'No forecast loaded.';
    }

    return props.itemsNeedingProcurement > 0
        ? `${props.itemsNeedingProcurement} item${props.itemsNeedingProcurement === 1 ? '' : 's'} show a procurement gap.`
        : 'No procurement gap in this cycle.';
});

const openerDetail = computed(() => {
    if (! props.hasForecast) {
        return 'Decision support answers only from a trained ML forecast.';
    }

    // Counted only over rows that would actually be ordered. Counting every
    // Low/Medium row, including items nobody is buying, produced a warning about
    // all 99 rows and read as a fault rather than guidance.
    const parts = [];

    if (props.gapsNeedingVerification > 0) {
        parts.push(`${props.gapsNeedingVerification} of the suggestions rest on fewer verified months — confirm usage before ordering`);
    }

    if (props.insufficientHistoryCount > 0) {
        parts.push(`${props.insufficientHistoryCount} item${props.insufficientHistoryCount === 1 ? '' : 's'} have too little history to estimate and are excluded from procurement`);
    }

    if (parts.length === 0) {
        return 'Every suggestion rests on sufficient verified history. Pick a question below, or ask about a specific item.';
    }

    return `${parts.join('. ')}. Pick a question below, or ask about a specific item.`;
});

watch(
    () => props.forecastPeriod,
    () => {
        // A retrained model invalidates the previous thread, which is still
        // describing the old period. Its follow-up context is stale too.
        messages.value = [];
        question.value = '';
        lastAnswerItems.value = [];
    },
);

function nextKey() {
    keySeed += 1;

    return `msg-${keySeed}`;
}

/**
 * Gemini answers come back as markdown ("**Cartridge Pen Black**", numbered
 * lists), so they are rendered and sanitized exactly as the main AI Assistant
 * renders its replies. Sanitizing matters because this is provider-authored
 * HTML injected into the page.
 */
function renderMarkdown(text) {
    if (!text) return '';

    try {
        return DOMPurify.sanitize(marked.parse(text, { breaks: true, gfm: true }));
    } catch {
        return text;
    }
}

/**
 * Names the real reason an answer came from the deterministic summary instead of
 * the provider. The previous single sentence covered three unrelated causes and
 * was also printed under text this UI wrote itself, which made a working Gemini
 * integration look broken.
 */
function sourceNote(message) {
    if (message.role !== 'ai' || !message.source || message.source === 'provider') {
        return '';
    }

    switch (message.providerStatus) {
        case 'no_key':
            return 'Deterministic summary — no AI provider key is configured in this environment.';
        case 'request_failed':
            return 'Deterministic summary — the AI provider could not be reached.';
        case 'reply_rejected':
            // A rejection can now mean an unapproved figure, a claimed cost, or a
            // contradiction of the item's priority, so this wording covers all
            // three rather than naming only the first.
            return 'Deterministic summary — the AI reply went beyond what the forecast calculated, so the verified answer is shown instead.';
        case 'not_attempted':
            return 'Deterministic summary — the AI provider was not contacted, because there is no estimate to explain.';
        default:
            return 'Deterministic summary — calculated directly from the forecast.';
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (logRef.value) {
            logRef.value.scrollTop = logRef.value.scrollHeight;
        }
    });
}

/**
 * The previous answer's items, kept as context for a follow-up.
 *
 * Only ids travel back to the server, never the reply prose, so the backend can
 * narrow to items the forecast already supplied and cannot be talked into
 * introducing anything new.
 */
const lastAnswerItems = ref([]);
const lastPromptType = ref('purchase_first');

async function run(promptType, label, contextIds = null, maxItems = null) {
    busy.value = true;
    messages.value = [...messages.value, { key: nextKey(), role: 'user', text: label }];
    scrollToBottom();

    const ids = contextIds === null ? [] : contextIds;

    try {
        const { data } = await api.post('/custodian/reports/forecast/decision-support', {
            prompt_type: promptType,
            ...(ids.length ? { inventory_ids: ids } : {}),
            // A named count narrows the same server ranking. The backend owns
            // the ceiling: it clamps and discloses the cap in `notice`.
            ...(maxItems ? { max_items: maxItems } : {}),
        }, { skipToast: true });

        lastAnswerItems.value = (data.items ?? []).map((item) => ({
            inventory_id: item.inventory_id,
            item_name: item.item_name,
        }));
        lastPromptType.value = data.prompt_type ?? promptType;

        messages.value = [
            ...messages.value,
            {
                key: nextKey(),
                role: 'ai',
                text: data.answer,
                source: data.source,
                providerStatus: data.provider_status,
                promptType: data.prompt_type,
                scope: data.scope,
                inventoryIds: data.inventory_ids ?? [],
                // Set only when the server clamped a requested count: the cap
                // is disclosed under the answer instead of applied silently.
                notice: data.notice ?? null,
            },
        ];
    } catch (requestError) {
        messages.value = [
            ...messages.value,
            {
                key: nextKey(),
                role: 'ai',
                text: requestError?.response?.data?.message ?? 'Could not answer that question from the current forecast.',
                source: 'local',
                providerStatus: 'request_failed',
                inventoryIds: [],
            },
        ];
    } finally {
        busy.value = false;
        scrollToBottom();
    }
}

function askPrompt(prompt) {
    if (busy.value) return;

    run(prompt.type, questions[prompt.type] ?? prompt.label);
}

/**
 * Appends a message this panel composed itself. No `source` is set, because no
 * AI provider was involved — these must never be captioned as a provider
 * fallback. Pass `source`/`providerStatus` when relaying a backend answer.
 */
function pushAssistant(text, options = {}) {
    messages.value = [
        ...messages.value,
        {
            key: nextKey(),
            role: 'ai',
            text,
            inventoryIds: [],
            ...options,
        },
    ];
    scrollToBottom();
}

/**
 * Explains one item through the existing forecast explanation endpoint rather
 * than the decision-support endpoint, so "why is Toner urgent?" gets that
 * item's arithmetic instead of a ranked list of ten.
 */
async function askAboutItem(item) {
    busy.value = true;
    messages.value = [...messages.value, { key: nextKey(), role: 'user', text: `Why is ${item.item_name} ${item.priority ?? ''}?`.trim() }];
    scrollToBottom();

    try {
        const { data } = await api.post('/custodian/reports/forecast/explanation', {
            inventory_id: item.inventory_id,
        }, { skipToast: true });

        pushAssistant(data.explanation, {
            source: data.source ?? 'local',
            providerStatus: data.provider_status,
        });
    } catch (requestError) {
        pushAssistant(requestError?.response?.data?.message ?? 'Could not explain that item from the current forecast.');
    } finally {
        busy.value = false;
    }
}

const GREETING = /^(hi|hey|hello|hola|good\s+(morning|afternoon|evening)|kumusta|howdy|thanks|thank\s+you|ty|ok|okay|test|ping)\b/i;

// A question word with no subject. "why?", "what?", "how" cannot be answered,
// and previously fell through to the ranked-list branch, so a bare "why?"
// returned ten purchase recommendations instead of asking what was meant.
const BARE_QUESTION = /^(why|what|how|when|which|who|whom|whose|where)\b[\s?.!]*$/i;

const ASKS_WHY = /why|explain|how come|reason|justify/i;

function guidance(text, message) {
    messages.value = [...messages.value, { key: nextKey(), role: 'user', text }];
    pushAssistant(message);
}

/**
 * Free text is classified, then answered by the same forecast-grounded
 * endpoints the quick prompts use. There is deliberately no general chat path:
 * anything not recognisably about this forecast gets guidance rather than a
 * guessed answer, so a greeting never returns a procurement list.
 */
async function submitQuestion() {
    const text = question.value.trim();

    if (text === '' || busy.value) return;

    question.value = '';

    if (BARE_QUESTION.test(text)) {
        guidance(text, 'What would you like explained? Name an item (for example "why is Bond Paper urgent?"), '
            + 'or pick a question below.');

        return;
    }

    if (GREETING.test(text) || text.length < 4) {
        guidance(text, 'I answer procurement questions from the current demand forecast only. '
            + 'Tap a question below, or name an item (for example "why is Bond Paper urgent?").');

        return;
    }

    const lowered = text.toLowerCase();

    const named = props.items.find((item) => (
        item.item_name && lowered.includes(String(item.item_name).toLowerCase())
    ));

    // A named item plus any question about its figure or rationale is answered
    // for that item alone. "How many bond papers do I need?" should not return
    // a ten-item ranking.
    if (named && /why|explain|how come|reason|justify|urgent|high|priority|how many|how much|do i need|do we need|how much should/.test(lowered)) {
        messages.value = [...messages.value, { key: nextKey(), role: 'user', text }];
        askAboutItem(named);

        return;
    }

    // A follow-up narrows the previous answer instead of re-ranking the whole
    // forecast. The ids sent are only ever ones the server already returned, so
    // "the second one" cannot reach an item the custodian was never shown.
    const followUpIds = resolveFollowUp(lowered);

    // "Why?" needs a subject. With no named item and nothing to refer back to,
    // ask which item rather than answering with a ranked list.
    if (ASKS_WHY.test(lowered) && followUpIds === null) {
        guidance(text, 'Which item should I explain? Name one from the list above, or refer to it '
            + '(for example "why is the second one urgent?").');

        return;
    }

    // A bare "why" over the whole previous list ("why those?", "why these?",
    // "why all of them?") asks what those items have in common. The why_these
    // prompt answers exactly that in one request, instead of re-rendering the
    // same ranking. A why about a subset ("why is the second one urgent?")
    // keeps the existing path below.
    if (ASKS_WHY.test(lowered) && followUpIds !== null && isWholePriorList(followUpIds)) {
        run('why_these', text, followUpIds);

        return;
    }

    const promptType = classify(lowered);

    if (promptType !== null) {
        // A named count ("give me 5 items") narrows the same ranking rather
        // than redefining it. The server clamps to its ceiling and discloses
        // the cap, so a large number is never silently cut.
        run(promptType, text, followUpIds, extractRequestedCount(lowered));

        return;
    }

    if (followUpIds !== null) {
        // A bare reference such as "the second one" carries no question of its
        // own, so it is answered against the same prompt as the previous turn.
        run(lastPromptType.value, text, followUpIds);

        return;
    }

    // Patterns missed entirely: ask the fallback parser what was meant. One
    // small call that returns a strict form only — never an answer — and the
    // form is resolved against this forecast before anything runs. When the
    // parse is unusable, the guidance below is unchanged.
    if (await runParsedQuestion(text, lowered, followUpIds)) {
        return;
    }

    {
        guidance(text, 'I can only answer procurement questions about this forecast. Try: '
            + '"what should we purchase first?", "what can wait?", "which rows need verification?", '
            + '"what step should I do next?", or name an item ("how many Bond Paper do I need?").');
    }
}

/**
 * A requested list size ("give me 5 items", "top 20"), or null when the
 * question names none. Positional references ("item 10", "the 10th") are not
 * counts and are left for the follow-up resolver.
 */
function extractRequestedCount(lowered) {
    const direct = lowered.match(/(?:give|show|list|send|display)\s+(?:me\s+|us\s+)?(\d{1,3})\s+items?\b/)
        || lowered.match(/(?:top|first)\s+(\d{1,3})\b/);

    if (!direct) return null;

    const count = parseInt(direct[1], 10);

    return Number.isInteger(count) && count > 0 ? count : null;
}

/**
 * Runs a fallback parse of an unrecognised question.
 *
 * Returns true when the parse produced an answer, false when the caller should
 * fall through to its guidance message. A why about one resolved item uses
 * the single-item explanation endpoint; a why about the whole prior list uses
 * the explanation prompt; everything else runs its parsed prompt with its
 * parsed count. Unresolvable parses are never executed.
 */
async function runParsedQuestion(text, lowered, followUpIds) {
    const parse = await tryParseQuestion(text, followUpIds);

    if (!parse) return false;

    let ids = Array.isArray(followUpIds) ? [...followUpIds] : [];

    if (parse.inventory_id) {
        ids = [parse.inventory_id];
    } else if (parse.item_name) {
        const hit = matchParsedItem(parse.item_name);

        if (!hit) return false;

        ids = [hit.inventory_id];
    }

    if (ASKS_WHY.test(lowered)) {
        if (ids.length === 1) {
            const item = props.items.find((row) => Number(row.inventory_id) === Number(ids[0]));

            if (!item) return false;

            askAboutItem(item);

            return true;
        }

        if (ids.length > 1 && isWholePriorList(ids)) {
            run('why_these', text, ids);

            return true;
        }

        return false;
    }

    run(parse.prompt_type, text, ids.length ? ids : null, parse.count);

    return true;
}

/**
 * Asks the server what an unrecognised question meant.
 *
 * One small provider call returning a strict form only. Null on any failure —
 * no key, no response, unusable parse — so the chat degrades to the same
 * guidance it showed before this fallback existed.
 */
async function tryParseQuestion(text, contextIds) {
    busy.value = true;

    try {
        const { data } = await api.post('/custodian/reports/forecast/parse-question', {
            message: text,
            ...((Array.isArray(contextIds) && contextIds.length) ? {
                inventory_ids: contextIds,
                item_names: lastAnswerItems.value
                    .filter((item) => contextIds.includes(item.inventory_id))
                    .map((item) => item.item_name),
            } : {}),
        }, { skipToast: true });

        return data?.status === 'ok' && data.parse ? data.parse : null;
    } catch {
        return null;
    } finally {
        busy.value = false;
    }
}

/**
 * Resolves a parsed item name against this forecast.
 *
 * The full forecast list first (complete objects for the explanation path),
 * then the previous answer's identities. A name found in neither resolves to
 * nothing, and the caller falls back to guidance.
 */
function matchParsedItem(name) {
    const needle = String(name ?? '').toLowerCase();

    if (needle === '') return null;

    const full = props.items.find((row) => String(row.item_name ?? '').toLowerCase().includes(needle))
        ?? props.items.find((row) => needle.includes(String(row.item_name ?? '').toLowerCase()));

    if (full) return full;

    return lastAnswerItems.value.find((item) => String(item.item_name ?? '').toLowerCase().includes(needle))
        ?? lastAnswerItems.value.find((item) => needle.includes(String(item.item_name ?? '').toLowerCase()))
        ?? null;
}

/**
 * Maps free text onto one of the supported question kinds, or null when it is
 * not recognisably a decision question.
 *
 * Matched generously but on word boundaries, because custodians phrase the same
 * four questions many ways ("what is you suggestions?", "any advice?", "which
 * ones?", "give me a list"). Genuinely unrelated questions still fall through
 * to guidance rather than being forced into a ranking.
 */
function classify(lowered) {
    const t = lowered.trim();

    // Checked first: "what should I do next?" is asked before any list exists,
    // so it must be answered from the whole forecast rather than a follow-up.
    if (/\b(?:what'?s?\s+next|next\s+steps?|what\s+steps?|what\s+(?:should|do|can)\s+(?:i|we)\s+(?:do|buy|order)|where\s+do\s+i\s+(?:start|begin)|how\s+do\s+i\s+(?:start|begin|proceed|go\s+about)|what\s+are\s+my\s+(?:options|next)|guide\s+me|help\s+me\s+(?:start|decide)|procedure|workflow|what'?s?\s+the\s+plan|what\s+is\s+the\s+plan|give\s+me\s+a\s+plan|step\s+by\s+step|how\s+do\s+i\s+proceed)\b/.test(t)) {
        return 'next_steps';
    }

    if (/\b(?:wait|waits|defer|deferred|deferable|later|skip|skipping|postpone|delay|hold\s+off|not\s+urgent|leave\s+(?:it\s+)?(?:for\s+)?next|can\s+wait|anything\s+else)\b/.test(t)) {
        return 'deferrable';
    }

    if (/\b(?:verif\w*|confirm|trust\w*|reliab\w*|uncertain\w*|insufficient|not\s+sure|safe\s+to\s+buy|check\s+(?:these|it|first)|double[\s-]?check|risks?|weak)\b/.test(t)) {
        return 'verify_first';
    }

    if (/\b(?:suggest\w*|recommend\w*|advice|guidance|advise|priorit\w*|urgent|top\s*\d*|most\s+(?:needed|important|urgent|critical)|biggest\s+gaps?|start\s+with|focus\s+on|first\s+on\s+the\s+list|which\s+(?:items?|ones?|should|to)|what\s+(?:to|do\s+i\s+to|should\s+i\s+to|do\s+we\s+to)\s+(?:buy|order|get|purchase|procure|restock)|what\s+do\s+we\s+need|list\s+of\s+items|give\s+me\s+a\s+list|summary|overview|tell\s+me\s+what)\b/.test(t)) {
        return 'purchase_first';
    }

    // Count-plus-ranking phrasing the main pattern misses: "give me 10 items to
    // procure first" never says "recommend", "list", or "buy", so it fell
    // through to guidance even though it asks for the ranked purchase list.
    // Kept in this branch (not a new one) so nothing else in the flow changes:
    // one classify() hit still means one run() and one provider call.
    // "to procure" / "procure first" cannot collide with the earlier branches,
    // which are checked first and contain none of these words.
    if (/\b(?:items?|ones?)\s+to\s+(?:procure|buy|order|get|purchase|restock)\b/.test(t)
        || /\b(?:buy|order|purchase|procure|restock)\s+first\b/.test(t)
        || /\b(?:top|first)\s+\d+\b/.test(t)
        || /\bgive\s+me\s+(?:the\s+)?\d+\s+items?\b/.test(t)) {
        return 'purchase_first';
    }

    return null;
}

/**
 * Resolves a reference back to specific items from the previous answer.
 *
 * Returns null when the text names no prior item (so the whole forecast is in
 * scope), and an empty array when it names nothing that can be resolved, which
 * the caller treats as "no useful narrowing".
 */
function resolveFollowUp(lowered) {
    if (lastAnswerItems.value.length === 0) return null;

    const items = lastAnswerItems.value;
    const ids = [];

    // "the second one", "item 3", "the first and third"
    const ordinals = [...lowered.matchAll(/\b(\d{1,2})(?:st|nd|rd|th)?\b/g)]
        .filter((match) => !isRequestedCount(lowered, match))
        .map((match) => parseInt(match[1], 10));
    ordinals.forEach((position) => {
        const item = items[position - 1];
        if (item && !ids.includes(item.inventory_id)) ids.push(item.inventory_id);
    });

    // "the first", "the last one" — but not the ranking sense of the word:
    // "first" in "procure first" or "first 10" asks for the ranked list, it does
    // not mean "the first one". Without this, filtering the requested count
    // above merely moved the misread from the 10th item to the 1st one.
    const firstMatch = lowered.match(/\bfirst\b/);
    if (firstMatch && !ordinals.length && !isRankingSense(lowered, firstMatch.index, firstMatch[0].length)) {
        const item = items[0];
        if (item) ids.push(item.inventory_id);
    }
    const lastMatch = lowered.match(/\b(last|final)\b/);
    if (lastMatch && !isRankingSense(lowered, lastMatch.index, lastMatch[0].length)) {
        const item = items[items.length - 1];
        if (item) ids.push(item.inventory_id);
    }

    // Named items from the previous list.
    items.forEach((item) => {
        const name = String(item.item_name ?? '').toLowerCase();
        if (name && lowered.includes(name) && !ids.includes(item.inventory_id)) {
            ids.push(item.inventory_id);
        }
    });

    if (ids.length === 0 && !/\b(all|rest|others|everything|them|these|those)\b/.test(lowered)) return null;

    // "all of them", "the rest", "everything else" means the whole prior list.
    // Checked before the empty return: these phrasings name no ordinal or item,
    // so ids is empty here by construction, and this check could never run
    // after it — which is why "why those?" fell through to guidance.
    if (/\b(all|rest|others|everything|them|these|those)\b/.test(lowered)) {
        return items.map((item) => item.inventory_id);
    }

    return ids;
}

/**
 * Whether the resolved ids are exactly the previous answer's whole list.
 *
 * Only then is a bare "why those?" a question about what the listed items
 * have in common. A subset ("the second one") keeps the narrower path.
 */
function isWholePriorList(ids) {
    if (!Array.isArray(ids) || lastAnswerItems.value.length === 0) return false;
    if (ids.length !== lastAnswerItems.value.length) return false;

    const prior = new Set(lastAnswerItems.value.map((item) => item.inventory_id));

    return ids.every((id) => prior.has(id));
}

/**
 * Whether a digit in the question is a quantity being asked for, not a position
 * in the previous answer.
 *
 * "give me 10 items" asks for ten items; only ordinal forms ("the 10th",
 * "item 10", "number 10") refer back to a listed position. Without this, the
 * 10 in "give me 10 items to procure first" was read as "the 10th item" and the
 * question was answered about a single item instead of the ranked list.
 */
function isRequestedCount(lowered, match) {
    const after = lowered.slice(match.index + match[0].length);

    // "10 items", "first 10 items"
    if (/^\s+items\b/.test(after)) return true;

    const before = lowered.slice(0, match.index);

    // "give me 10", "show 5", "top 10" (without "items" after it)
    if (/(?:give|show|list|get|send|display)\s+(?:me\s+|us\s+)?$/.test(before)) return true;
    if (/(?:^|\s)(?:top|first)\s+$/.test(before)) return true;

    return false;
}

/**
 * Whether "first"/"last"/"final" is used in the ranking sense ("procure
 * first", "first 10", "last 5") rather than as a reference ("the first one").
 *
 * Only the ranking sense is excluded. A standalone "the first", "first one",
 * or "the last one" still narrows to that position, exactly as before.
 */
function isRankingSense(text, index, length) {
    const before = text.slice(0, index);
    const after = text.slice(index + length);

    // "procure first", "buy first", "to order first"
    if (/(?:procure|buy|order|purchase|restock|get)\s+(?:the\s+)?$/.test(before)) return true;

    // "first 10", "last 5"
    if (/^\s*\d/.test(after)) return true;

    return false;
}

async function exportList(message) {
    exportingKey.value = message.key;

    try {
        const response = await api.post(
            '/custodian/reports/forecast/procurement-list-pdf',
            { inventory_ids: message.inventoryIds, prompt_type: message.promptType },
            { responseType: 'blob', skipToast: true },
        );

        const blobUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');

        link.href = blobUrl;
        link.download = `procurement-priority-list-${(props.forecastPeriod ?? 'forecast').replace(/\s+/g, '-').toLowerCase()}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (requestError) {
        if (requestError?.response?.data instanceof Blob) {
            const text = await requestError.response.data.text();

            try {
                const payload = JSON.parse(text);

                // A server-side refusal (stale ids, role, missing forecast) is
                // not a provider fallback, so it is reported without a caption.
                pushAssistant(payload.message);

                return;
            } catch {
                // Fall through to the generic message below.
            }
        }

        pushAssistant('Could not produce the procurement list PDF. Refresh model training, then ask again.');
    } finally {
        exportingKey.value = null;
    }
}

function clearThread() {
    messages.value = [];
    question.value = '';
    // The follow-up context belongs to the cleared thread, so it must not leak
    // into the next conversation.
    lastAnswerItems.value = [];
}

// askAboutItem is exposed so the Recommendations tab can hand an item straight
// to the single-item explanation endpoint. It was already the path for "why is
// <item> urgent?" inside this chat; it simply was not reachable from outside.
defineExpose({ clearThread, askAboutItem });
</script>
