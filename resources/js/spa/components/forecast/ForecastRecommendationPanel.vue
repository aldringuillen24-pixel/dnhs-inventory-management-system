<template>
  <section
    class="flex h-full min-h-0 flex-col bg-emerald-50/50 dark:bg-emerald-950/20"
    aria-label="Forecast recommendations"
  >
    <!-- Header -->
    <header class="shrink-0 border-b border-emerald-200/80 px-3.5 py-2.5 dark:border-emerald-900/60">
      <div class="flex items-center gap-2">
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-emerald-600 text-white">
          <LucideIcon :icon="ClipboardList" class="h-3.5 w-3.5" />
        </span>
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-sm font-bold text-emerald-950 dark:text-emerald-50">Recommendations</h2>
          <p class="text-[11px] leading-tight text-emerald-800/80 dark:text-emerald-300/80">
            {{ forecastPeriod || 'Current forecast' }} · {{ itemCount }} item{{ itemCount === 1 ? '' : 's' }}
            <span v-if="cached" class="block text-emerald-700/70 dark:text-emerald-400/70">
              Saved for this forecast cycle
            </span>
          </p>
        </div>
        <button
          type="button"
          class="shrink-0 rounded p-1 text-emerald-700/60 hover:bg-emerald-200/60 hover:text-emerald-900 disabled:opacity-50 dark:text-emerald-300/60 dark:hover:bg-emerald-900/50 dark:hover:text-emerald-100"
          :disabled="busy"
          title="Regenerate recommendations"
          aria-label="Regenerate recommendations"
          @click="load(true)"
        >
          <LucideIcon :icon="RefreshCw" class="h-3.5 w-3.5" :class="{ 'animate-spin': busy }" />
        </button>
      </div>
    </header>

    <!-- Body -->
    <div ref="logRef" class="min-h-0 flex-1 space-y-2.5 overflow-y-auto px-3 py-3" aria-live="polite">
      <!-- No forecast: same rule as the chat. An absent or unreadable model
           result is stated plainly rather than replaced by an error panel that
           would hide the rest of this card. -->
      <div
        v-if="!hasForecast"
        class="rounded-md border border-emerald-200 bg-white px-3 py-2.5 text-xs leading-relaxed text-emerald-800 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200"
      >
        <p class="font-semibold text-emerald-900 dark:text-emerald-100">No forecast loaded.</p>
        <p class="mt-1">
          Recommendations are written from a trained ML forecast. Refresh model training, then reload this panel.
        </p>
      </div>

      <!-- Generating: shows the shape of what is coming so the card does not
           jump when seven provider calls resolve. -->
      <template v-else-if="busy && !payload">
        <div
          v-for="block in 5"
          :key="`skeleton-${block}`"
          class="rounded-md border border-emerald-200/80 bg-white px-3 py-2.5 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/40"
        >
          <span class="block h-3 w-full animate-pulse rounded bg-emerald-200/60 dark:bg-white/10" />
          <span
            class="mt-1.5 block h-3 animate-pulse rounded bg-emerald-200/40 dark:bg-white/5"
            :class="block % 2 ? 'w-2/3' : 'w-full'"
          />
        </div>
      </template>

      <div
        v-else-if="error"
        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs leading-relaxed text-rose-800 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
      >
        <p class="font-semibold">Recommendations are unavailable.</p>
        <p class="mt-1">{{ error }}</p>
      </div>

      <template v-else-if="payload">
        <!-- Cycle brief -->
        <div class="rounded-md border border-emerald-200 bg-white px-3 py-2.5 text-xs leading-relaxed text-emerald-800 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
          <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-700/70 dark:text-emerald-400/70">
            Cycle brief
          </p>
          <p class="whitespace-pre-line" v-html="renderMarkdown(payload.brief)"></p>
          <p v-if="sourceNote" class="mt-1.5 text-[10px] italic opacity-70">{{ sourceNote }}</p>
        </div>

        <!-- Work queues. Every forecast row appears in exactly one of these,
             so the three counts always sum to the row total. -->
        <section
          v-for="queue in payload.queues"
          :key="queue.key"
          class="overflow-hidden rounded-md border border-emerald-200/80 bg-white shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/40"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-emerald-50/70 dark:hover:bg-emerald-900/30"
            :aria-expanded="isExpanded(queue.key)"
            :aria-controls="`forecast-queue-${queue.key}`"
            @click="toggleQueue(queue.key)"
          >
            <LucideIcon
              :icon="ChevronRight"
              class="h-3.5 w-3.5 shrink-0 text-emerald-700/60 transition-transform duration-200 dark:text-emerald-400/60"
              :class="{ 'rotate-90': isExpanded(queue.key) }"
            />
            <h3 class="min-w-0 flex-1 text-xs font-bold text-emerald-900 dark:text-emerald-100">
              {{ queue.label }}
            </h3>
            <span class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
              {{ queue.item_count }}
            </span>
          </button>

          <!-- Buy now opens by default because it is the work the custodian
               came here to do. The other two stay closed so 100 rows do not
               bury the brief on open. -->
          <ul
            v-show="isExpanded(queue.key)"
            :id="`forecast-queue-${queue.key}`"
            class="divide-y divide-emerald-100 border-t border-emerald-100 dark:divide-white/5 dark:border-white/5"
          >
            <li v-for="item in queue.items" :key="item.inventory_id" class="px-3 py-2">
              <div class="flex items-start justify-between gap-2">
                <span class="min-w-0 text-xs font-medium text-emerald-950 dark:text-emerald-50">
                  {{ item.item_name }}
                </span>
                <span class="shrink-0 text-xs font-semibold tabular-nums text-emerald-900 dark:text-white">
                  <template v-if="item.suggested_procurement === null">No estimate</template>
                  <template v-else>{{ item.suggested_procurement }} {{ item.unit }}</template>
                </span>
              </div>

              <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[10px] text-emerald-800/70 dark:text-emerald-300/70">
                <span
                  class="inline-flex rounded-full border px-1.5 py-px text-[9px] font-semibold"
                  :class="priorityClass(item.priority)"
                >
                  {{ item.priority }}
                </span>
                <span v-if="item.status !== 'success'">No verified history</span>
                <span v-else-if="item.confidence !== 'High'">{{ item.confidence }} confidence</span>
                <span v-if="item.forecast_demand !== null" class="tabular-nums">{{ figures(item) }}</span>
                <span v-if="item.unmet_demand > 0" class="font-semibold text-amber-700 dark:text-amber-400">
                  · {{ item.unmet_demand }} requested
                </span>
                <!-- The ratio is shown rather than a bare "unusual" flag, because a
                     flag with no number attached reads as an accusation. Beside it,
                     the custodian can see it is 3.1x a 4-a-month baseline. -->
                <span
                  v-if="item.usage_spike"
                  class="font-semibold text-amber-700 dark:text-amber-400"
                  :title="spikeTitle(item)"
                >
                  · usage {{ item.usage_ratio }}× usual
                </span>
              </p>

              <p class="mt-1 text-[11px] leading-relaxed text-emerald-800/90 dark:text-emerald-200/90">
                {{ item.action }}
              </p>

              <button
                type="button"
                class="mt-1.5 inline-flex items-center gap-1 rounded border border-emerald-300 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 transition-colors hover:bg-emerald-100 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-900/50"
                @click="askAbout(item)"
              >
                <LucideIcon :icon="MessageSquare" class="h-3 w-3" />
                Ask why
              </button>
            </li>
          </ul>
        </section>
      </template>
    </div>

    <!-- Footer: the handoff back to the chat. Switching tabs does not fire a
         request on purpose — the chat opens on its own quick prompts, and
         nothing is asked of the provider until the custodian asks it. -->
    <footer
      v-if="hasForecast"
      class="shrink-0 border-t border-emerald-200/80 px-3 py-2.5 dark:border-emerald-900/60"
    >
      <button
        type="button"
        class="flex w-full items-center justify-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-100/70 px-3 py-2 text-xs font-semibold text-emerald-900 transition-colors hover:bg-emerald-200/70 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-900/50"
        @click="$emit('switch-tab')"
      >
        <LucideIcon :icon="MessageSquare" class="h-3.5 w-3.5" />
        Have questions? Open AI Decision Support
        <LucideIcon :icon="ChevronRight" class="h-3.5 w-3.5" />
      </button>
      <p class="mt-1.5 text-center text-[10px] leading-snug text-emerald-700/70 dark:text-emerald-400/70">
        Advisory only. Nothing here creates orders or changes stock.
      </p>
    </footer>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { ChevronRight, ClipboardList, MessageSquare, RefreshCw } from 'lucide';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import api from '../../lib/axios';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

const props = defineProps({
    forecastPeriod: { type: String, default: null },
    // The cycle key. A retrained model produces a different one, which
    // invalidates the saved recommendations for the previous cycle.
    forecastGeneratedAt: { type: String, default: null },
    hasForecast: { type: Boolean, default: false },
    totalItems: { type: Number, default: 0 },
});

const payload = ref(null);
const busy = ref(false);
const error = ref('');
const logRef = ref(null);

const emit = defineEmits(['switch-tab', 'ask-about-item']);

const itemCount = computed(() => payload.value?.item_count ?? 0);

const cached = computed(() => payload.value?.cached === true);

// Buy now is the reason the custodian opened the tab, so it is expanded by
// default. But it can legitimately be empty — a forecast where every suggestion
// rests on thin history puts all of them in Verify first — and expanding an
// empty section on open wastes the one screen the user actually came for. The
// defaults are therefore recomputed from whatever came back.
const expanded = ref({ buy_now: true, verify_first: false, no_action: false });

function defaultExpanded(received) {
    const queues = received?.queues ?? [];
    const firstWithItems = queues.find((queue) => queue.item_count > 0);

    return Object.fromEntries(
        queues.map((queue) => [queue.key, queue.key === (firstWithItems?.key ?? 'buy_now')]),
    );
}

/**
 * Same two-line shape the AI Decision Support chat renders, so a figure read
 * here and a figure read there are recognisably the same number.
 */
function figures(item) {
    return `${item.forecast_demand} + ${item.safety_stock} - ${item.available_stock} - ${item.pending_demand} + ${item.unmet_demand}`;
}

/**
 * The full comparison behind the "usage N× usual" badge, so the claim can be
 * checked rather than taken on faith. Built from server-computed figures only.
 */
function spikeTitle(item) {
    return `Latest verified month (${item.usage_latest_month}) came in at `
        + `${item.usage_ratio}x its usual rate of ${item.usage_baseline} ${item.unit}, `
        + `compared across ${item.usage_months_compared} verified months.`;
}

function isExpanded(key) {
    return expanded.value[key] === true;
}

function toggleQueue(key) {
    expanded.value = { ...expanded.value, [key]: !expanded.value[key] };
}

function askAbout(item) {
    emit('ask-about-item', item);
}

/**
 * Provider text is markdown and is sanitized before injection, exactly as the
 * chat does. Sanitizing is not optional: this is provider-authored HTML.
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
 * Names the real reason a brief came from the deterministic summary instead of
 * the provider, rather than one blanket sentence that makes a working Gemini
 * integration look broken.
 */
const sourceNote = computed(() => {
    if (! payload.value || payload.value.source === 'provider') return '';

    switch (payload.value.provider_status) {
        case 'no_key':
            return 'Deterministic summary — no AI provider key is configured in this environment.';
        case 'request_failed':
            return 'Deterministic summary — the AI provider could not be reached.';
        case 'reply_rejected':
            return 'Deterministic summary — the AI reply cited figures the forecast did not calculate.';
        default:
            return 'Deterministic summary — calculated directly from the forecast.';
    }
});

// Pill styles match ForecastRecommendations.vue's PRIORITY_TIERS, so the same
// tier wears the same colour here as it does in the table beside this card.
function priorityClass(priority) {
    return PRIORITY_TIERS[priority] ?? PRIORITY_TIERS.Normal;
}

const PRIORITY_TIERS = {
    Urgent: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
    High: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
    Medium: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
    Normal: 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

async function load(refresh = false) {
    if (busy.value || ! props.hasForecast) return;

    busy.value = true;
    error.value = '';

    try {
        const { data } = await api.post(
            '/custodian/reports/forecast/ai-recommendations',
            refresh ? { refresh: true } : {},
            { skipToast: true },
        );

        payload.value = data;
        expanded.value = defaultExpanded(data);
    } catch (requestError) {
        // A refusal here (no trained forecast, role, provider unavailable) is
        // reported in place rather than replacing the tab with an error page,
        // so the chat beside it stays reachable.
        error.value = requestError?.response?.data?.message
            ?? 'Could not build recommendations from the current forecast.';

        // Rows shown before this failed came from an earlier cycle. Keeping them
        // would present last cycle's advice against this cycle's numbers.
        payload.value = null;
    } finally {
        busy.value = false;
        nextTick(() => {
            if (logRef.value) logRef.value.scrollTop = 0;
        });
    }
}

watch(
    () => props.hasForecast,
    (ready) => {
        // This panel mounts before the parent finishes loading the forecast, so
        // the first load() is a no-op and would leave the tab permanently empty.
        // Generating only once a forecast actually exists keeps the empty state
        // honest without a second round trip on every mount.
        if (ready && ! payload.value && ! busy.value) {
            load();
        }
    },
);

watch(
    () => props.forecastGeneratedAt,
    () => {
        // A retrained model invalidates the saved recommendations, the same way
        // it invalidates the chat thread.
        payload.value = null;
        expanded.value = { buy_now: true, verify_first: false, no_action: false };
        load();    },
);

onMounted(load);
</script>
