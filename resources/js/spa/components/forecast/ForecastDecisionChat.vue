<template>
  <section
    class="flex h-full min-h-0 flex-col border-gray-200/80 bg-gray-50/40 dark:border-gray-800 dark:bg-gray-950/40 lg:border-r"
    aria-label="AI decision support"
  >
    <!-- Header -->
    <header class="shrink-0 border-b border-gray-200/80 px-3.5 py-2.5 dark:border-gray-800">
      <div class="flex items-center gap-2">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-emerald-700 text-white">
          <LucideIcon :icon="Sparkles" class="h-3.5 w-3.5" />
        </span>
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-sm font-bold text-gray-900 dark:text-white">AI Decision Support</h2>
          <p class="text-[11px] leading-tight text-gray-500 dark:text-gray-400">
            {{ forecastPeriod || 'Current forecast' }} · all {{ totalItems }} items
            <span v-if="tableFiltered" class="block text-amber-700 dark:text-amber-400">
              ignores the table filter
            </span>
          </p>
        </div>
        <button
          v-if="messages.length"
          type="button"
          class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-200/70 hover:text-gray-700 disabled:opacity-50 dark:hover:bg-white/5 dark:hover:text-gray-200"
          :disabled="busy"
          title="Clear conversation"
          aria-label="Clear conversation"
          @click="clearThread"
        >
          <LucideIcon :icon="RotateCcw" class="h-3.5 w-3.5" />
        </button>
      </div>
    </header>

    <!-- Thread -->
    <div ref="logRef" class="min-h-0 flex-1 space-y-2.5 overflow-y-auto px-3 py-3" aria-live="polite">
      <!-- Intro: flags risk now that the Confidence column is off the table -->
      <div class="rounded-md border border-gray-200 bg-white px-3 py-2.5 text-xs leading-relaxed text-gray-600 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ openerHeadline }}</p>
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
            ? 'bg-emerald-700 text-white'
            : 'border border-gray-200 bg-white text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200'"
        >
          <p class="whitespace-pre-line" v-html="renderMarkdown(message.text)"></p>

          <p v-if="message.source === 'local'" class="mt-1.5 text-[10px] italic opacity-70">
            Deterministic summary — provider unavailable, unconfigured, or not grounded in the forecast.
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
        <div class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
          <span class="inline-flex items-center gap-1">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600"></span>
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600 [animation-delay:150ms]"></span>
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-600 [animation-delay:300ms]"></span>
          </span>
        </div>
      </div>
    </div>

    <!-- Quick prompts -->
    <div v-if="!hasForecast" class="shrink-0 px-3 py-3 text-xs text-gray-500 dark:text-gray-400">
      Decision support needs a trained forecast. Refresh model training, then ask again.
    </div>
    <div v-else class="shrink-0 border-t border-gray-200/80 px-3 py-2.5 dark:border-gray-800">
      <div class="mb-2 flex flex-wrap gap-1.5">
        <button
          v-for="prompt in quickPrompts"
          :key="prompt.type"
          type="button"
          class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-600 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-emerald-600/60 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-200"
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
          class="min-h-[2.5rem] w-full resize-none rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          @keydown.enter.exact.prevent="submitQuestion"
        ></textarea>
        <button
          type="submit"
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-emerald-700 text-white hover:bg-emerald-800 disabled:opacity-50"
          :disabled="busy || !question.trim()"
          aria-label="Send question"
          title="Send question"
        >
          <LucideIcon :icon="Send" class="h-4 w-4" />
        </button>
      </form>
      <p class="mt-1.5 text-[10px] leading-snug text-gray-400 dark:text-gray-500">
        Advisory only. Nothing here creates orders or changes stock.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { FileDown, RotateCcw, Send, Sparkles } from 'lucide';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import api from '../../lib/axios';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

const props = defineProps({
    forecastPeriod: { type: String, default: null },
    hasForecast: { type: Boolean, default: false },
    itemsNeedingProcurement: { type: Number, default: 0 },
    lowConfidenceCount: { type: Number, default: 0 },
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
    { type: 'purchase_first', label: 'Purchase first?' },
    { type: 'deferrable', label: 'What can wait?' },
    { type: 'verify_first', label: 'Needs verification?' },
];

const questions = {
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

    if (props.lowConfidenceCount > 0) {
        return `${props.lowConfidenceCount} row${props.lowConfidenceCount === 1 ? '' : 's'} rest on limited verified history — confirm before ordering. Pick a question below.`;
    }

    return 'Pick a question below, or ask about a specific item. Every answer cites forecast values only.';
});

watch(
    () => props.forecastPeriod,
    () => {
        // A retrained model invalidates the previous thread, which is still
        // describing the old period.
        messages.value = [];
        question.value = '';
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

function scrollToBottom() {
    nextTick(() => {
        if (logRef.value) {
            logRef.value.scrollTop = logRef.value.scrollHeight;
        }
    });
}

async function run(promptType, label) {
    busy.value = true;
    messages.value = [...messages.value, { key: nextKey(), role: 'user', text: label }];
    scrollToBottom();

    try {
        const { data } = await api.post('/custodian/reports/forecast/decision-support', {
            prompt_type: promptType,
        }, { skipToast: true });

        messages.value = [
            ...messages.value,
            {
                key: nextKey(),
                role: 'ai',
                text: data.answer,
                source: data.source,
                promptType: data.prompt_type,
                inventoryIds: data.inventory_ids ?? [],
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

function pushAssistant(text, options = {}) {
    messages.value = [
        ...messages.value,
        {
            key: nextKey(),
            role: 'ai',
            text,
            source: 'local',
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

        pushAssistant(data.explanation);
    } catch (requestError) {
        pushAssistant(requestError?.response?.data?.message ?? 'Could not explain that item from the current forecast.');
    } finally {
        busy.value = false;
    }
}

const GREETING = /^(hi|hey|hello|hola|good\s+(morning|afternoon|evening)|kumusta|howdy|thanks|thank\s+you|ty|ok|okay|test|ping)\b/i;

/**
 * Free text is classified, then answered by the same forecast-grounded
 * endpoints the quick prompts use. There is deliberately no general chat path:
 * anything that is not recognisably about this forecast gets guidance rather
 * than a guessed answer, so a greeting never returns a procurement list.
 */
function submitQuestion() {
    const text = question.value.trim();

    if (text === '' || busy.value) return;

    question.value = '';

    if (GREETING.test(text.trim()) || text.trim().length < 4) {
        messages.value = [...messages.value, { key: nextKey(), role: 'user', text }];
        pushAssistant(
            'I answer procurement questions from the current demand forecast only. '
            + 'Tap a question below, or name an item (for example "why is Bond Paper urgent?").',
        );

        return;
    }

    const named = props.items.find((item) => (
        item.item_name && text.toLowerCase().includes(String(item.item_name).toLowerCase())
    ));

    if (named && /why|explain|how come|reason|justify|urgent|high|priority/.test(text.toLowerCase())) {
        messages.value = [...messages.value, { key: nextKey(), role: 'user', text }];
        askAboutItem(named);

        return;
    }

    const lowered = text.toLowerCase();

    if (/wait|defer|later|skip|postpone|can i delay/.test(lowered)) {
        run('deferrable', text);
    } else if (/verif|trust|confiden|reliable|uncertain|insufficient/.test(lowered)) {
        run('verify_first', text);
    } else if (/first|priorit|urgent|top|buy|purchase|need to order|recommend/.test(lowered)) {
        run('purchase_first', text);
    } else {
        messages.value = [...messages.value, { key: nextKey(), role: 'user', text }];
        pushAssistant(
            'That question is outside what this panel can answer. I can rank what to purchase first, '
            + 'show what can wait, flag rows that need verification, or explain one named item.',
        );
    }
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

                messages.value = [
                    ...messages.value,
                    { key: nextKey(), role: 'ai', text: payload.message, source: 'local', inventoryIds: [] },
                ];
                scrollToBottom();

                return;
            } catch {
                // Fall through to the generic message below.
            }
        }

        messages.value = [
            ...messages.value,
            {
                key: nextKey(),
                role: 'ai',
                text: 'Could not produce the procurement list PDF. Refresh model training, then ask again.',
                source: 'local',
                inventoryIds: [],
            },
        ];
        scrollToBottom();
    } finally {
        exportingKey.value = null;
    }
}

function clearThread() {
    messages.value = [];
    question.value = '';
}

defineExpose({ clearThread });
</script>
