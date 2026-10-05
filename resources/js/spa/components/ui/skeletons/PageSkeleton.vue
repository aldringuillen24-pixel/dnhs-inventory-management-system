<template>
  <div class="space-y-6" aria-label="Loading page content" aria-busy="true">
    <div v-if="metrics" class="grid gap-4 sm:grid-cols-2" :class="metricColumns === 2 ? '' : metricColumns === 3 ? 'xl:grid-cols-3' : 'xl:grid-cols-4'">
      <MetricCardSkeleton v-for="index in metrics" :key="`metric-${index}`" />
    </div>

    <div v-if="cards" class="grid gap-6 md:grid-cols-2">
      <section
        v-for="index in cards"
        :key="`card-${index}`"
        class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        :class="{ 'md:col-span-2': wideLastCard && index === cards }"
      >
        <div class="h-4 w-2/5 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
        <div v-if="cardType(index) === 'table'" class="mt-5 space-y-3">
          <div class="flex gap-3 border-b border-gray-100 pb-3 dark:border-white/5">
            <span v-for="column in tableColumns" :key="column" class="h-3 flex-1 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
          <div v-for="row in 5" :key="row" class="flex gap-3 py-1">
            <span v-for="column in tableColumns" :key="column" class="h-4 flex-1 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <div v-else-if="cardType(index) === 'list'" class="mt-5 divide-y divide-gray-100 dark:divide-white/5">
          <div v-for="row in 4" :key="row" class="flex items-center justify-between gap-4 py-3">
            <div class="flex-1 space-y-2">
              <div class="h-3 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-3 w-3/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
            <div class="h-4 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <div v-else class="mt-5 flex h-48 items-end gap-3">
          <div v-for="bar in 7" :key="bar" class="flex-1 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :style="{ height: `${24 + ((bar * 19) % 68)}%` }" />
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import MetricCardSkeleton from './MetricCardSkeleton.vue';

const props = defineProps({
  metrics: { type: Number, default: 0 },
  metricColumns: { type: Number, default: 4 },
  cards: { type: Number, default: 0 },
  cardType: { type: String, default: 'chart' },
  tableColumns: { type: Number, default: 4 },
  wideLastCard: { type: Boolean, default: false },
  lastCardType: { type: String, default: 'chart' },
});

function cardType(index) {
  return props.wideLastCard && index === props.cards ? props.lastCardType : props.cardType;
}
</script>
