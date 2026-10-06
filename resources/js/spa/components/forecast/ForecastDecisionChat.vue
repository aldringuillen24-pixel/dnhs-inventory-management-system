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

          <!-- Only shown when a provider was genuinely involved and its answer was
               not used. Messages written by this UI itself (greetings, out-of-scope
               guidance) carry no source and are never labelled a fallback. -->
          <p v-if="sourceNote(message)" class="mt-1.5 text-[10px] italic opacity-70">
            {{ sourceNote(message) }}
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
            return 'Deterministic summary — the AI reply cited figures the forecast did not calculate, so the verified numbers are shown instead.';
        case 'not_attempted':
            return 'Deterministic summary — the AI provider was not contacted.';
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

async function run(promptType, label, contextIds = null) {
    busy.value = true;
    messages.value = [...messages.value, { key: nextKey(), role: 'user', text: label }];
    scrollToBottom();

    const ids = contextIds === null ? [] : contextIds;

    try {
        const { data } = await api.post('/custodian/reports/forecast/decision-support', {
            prompt_type: promptType,
            ...(ids.length ? { inventory_ids: ids } : {}),
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

        pushAssistant(data.explanation, { source: data.source ?? 'local' });
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
function submitQuestion() {
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

    const promptType = classify(lowered);

    if (promptType !== null) {
        run(promptType, text, followUpIds);

        return;
    }

    if (followUpIds !== null) {
        // A bare reference such as "the second one" carries no question of its
        // own, so it is answered against the same prompt as the previous turn.
        run(lastPromptType.value, text, followUpIds);

        return;
    }

    {
        guidance(text, 'I can only answer procurement questions about this forecast. Try: '
            + '"what should we purchase first?", "what can wait?", "which rows need verification?", '
            + '"what step should I do next?", or name an item ("how many Bond Paper do I need?").');
    }
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
    const ordinals = [...lowered.matchAll(/\b(\d{1,2})(?:st|nd|rd|th)?\b/g)].map((match) => parseInt(match[1], 10));
    ordinals.forEach((position) => {
        const item = items[position - 1];
        if (item && !ids.includes(item.inventory_id)) ids.push(item.inventory_id);
    });

    // "the first", "the last one"
    if (/\bfirst\b/.test(lowered) && !ordinals.length) {
        const item = items[0];
        if (item) ids.push(item.inventory_id);
    }
    if (/\b(last|final)\b/.test(lowered)) {
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

    if (ids.length === 0) return null;

    // "all of them", "the rest", "everything else" means the whole prior list.
    if (/\b(all|rest|others|everything|them|these|those)\b/.test(lowered)) {
        return items.map((item) => item.inventory_id);
    }

    return ids;
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

defineExpose({ clearThread });
</script>
