<template>
  <section class="flex h-full min-h-0 flex-col" aria-label="Forecast assistant">
    <!-- Tab strip. Underline style, matching the two-tab bar in Settings.vue. -->
    <div
      role="tablist"
      aria-label="Forecast assistant views"
      class="flex shrink-0 border-b border-emerald-200/80 dark:border-emerald-900/60"
    >
      <button
        v-for="tab in tabs"
        :id="`forecast-tab-${tab.key}`"
        :key="tab.key"
        type="button"
        role="tab"
        class="flex flex-1 items-center justify-center gap-1.5 border-b-2 px-2 py-2 text-[11px] font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
        :class="activeTab === tab.key
          ? 'border-emerald-600 text-emerald-800 dark:border-emerald-400 dark:text-emerald-300'
          : 'border-transparent text-emerald-700/60 hover:border-emerald-400/60 hover:text-emerald-900 dark:text-emerald-400/60 dark:hover:border-emerald-600 dark:hover:text-emerald-100'"
        :aria-selected="activeTab === tab.key"
        :aria-controls="`forecast-panel-${tab.key}`"
        @click="activeTab = tab.key"
      >
        <LucideIcon :icon="tab.icon" class="h-3.5 w-3.5" />
        <span class="truncate">{{ tab.label }}</span>
      </button>
    </div>

    <!-- Both panels stay MOUNTED behind v-show.
         v-if would destroy the chat thread and the generated recommendations on
         every tab switch, so returning to a tab would cost a full regeneration
         and lose the conversation the custodian was in the middle of. -->
    <div
      v-show="activeTab === 'decision'"
      id="forecast-panel-decision"
      role="tabpanel"
      aria-labelledby="forecast-tab-decision"
      class="min-h-0 flex-1"
    >
      <ForecastDecisionChat
        ref="chatRef"
        class="h-full"
        :forecast-period="forecastPeriod"
        :has-forecast="hasForecast"
        :items-needing-procurement="itemsNeedingProcurement"
        :low-confidence-count="lowConfidenceCount"
        :gaps-needing-verification="gapsNeedingVerification"
        :insufficient-history-count="insufficientHistoryCount"
        :total-items="totalItems"
        :table-filtered="tableFiltered"
        :items="items"
      />
    </div>

    <div
      v-show="activeTab === 'recommendations'"
      id="forecast-panel-recommendations"
      role="tabpanel"
      aria-labelledby="forecast-tab-recommendations"
      class="min-h-0 flex-1"
    >
      <ForecastRecommendationPanel
        class="h-full"
        :forecast-period="forecastPeriod"
        :forecast-generated-at="forecastGeneratedAt"
        :has-forecast="hasForecast"
        :total-items="totalItems"
        @switch-tab="activeTab = 'decision'"
        @ask-about-item="askAboutItem"
      />
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { ClipboardList, Sparkles } from 'lucide';
import ForecastDecisionChat from './ForecastDecisionChat.vue';
import ForecastRecommendationPanel from './ForecastRecommendationPanel.vue';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

// The wrapper is the single owner of tab state. Children never mutate it; they
// emit upward, so there is one place that decides what is visible.
const activeTab = ref('decision');
const chatRef = ref(null);

const tabs = [
    { key: 'decision', label: 'AI Decision Support', icon: Sparkles },
    { key: 'recommendations', label: 'Recommendations', icon: ClipboardList },
];

defineProps({
    forecastPeriod: { type: String, default: null },
    // The cycle key for saved recommendations. A retrained model changes it.
    forecastGeneratedAt: { type: String, default: null },
    hasForecast: { type: Boolean, default: false },
    itemsNeedingProcurement: { type: Number, default: 0 },
    lowConfidenceCount: { type: Number, default: 0 },
    gapsNeedingVerification: { type: Number, default: 0 },
    insufficientHistoryCount: { type: Number, default: 0 },
    totalItems: { type: Number, default: 0 },
    tableFiltered: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
});

/**
 * A per-item "Ask why" from the Recommendations tab.
 *
 * Switches to the chat and asks it to explain that one item, which routes to
 * the single-item explanation endpoint rather than returning another ranked
 * list. The item object carries identity only — every quantity still comes
 * from the server, so nothing the client holds can reach the prompt.
 */
function askAboutItem(item) {
    activeTab.value = 'decision';

    if (typeof chatRef.value?.askAboutItem === 'function') {
        chatRef.value.askAboutItem(item);
    }
}
</script>
