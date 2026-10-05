<template>
  <div class="space-y-6" aria-label="Loading transactions" aria-busy="true">
    <div v-if="fullPage" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <MetricCardSkeleton v-for="index in 4" :key="`metric-${index}`" />
    </div>

    <section
      :class="fullPage ? 'overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900' : ''"
    >
      <div v-if="fullPage" class="flex gap-2 overflow-hidden border-b border-gray-200/80 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
        <div v-for="tab in 5" :key="`tab-${tab}`" class="h-8 w-28 shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
      </div>

      <div :class="fullPage ? 'space-y-8 p-6' : 'space-y-8'">
        <section v-for="(group, index) in tableGroups" :key="`${group.title}-${index}`">
          <div v-if="group.title" class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3 dark:border-white/5">
            <div class="h-6 w-6 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-4 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>

          <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
            <table class="w-full text-left text-xs" :class="group.minWidth">
              <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
                <tr class="border-b border-gray-200 dark:border-gray-800">
                  <th v-for="(column, columnIndex) in group.columns" :key="`head-${columnIndex}`" class="px-4 py-3.5">
                    <div class="h-3 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" :class="headerWidth(column)" />
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                <tr v-for="row in 5" :key="`row-${row}`" class="h-12">
                  <td v-for="(column, columnIndex) in group.columns" :key="`cell-${columnIndex}`" class="px-4 py-3.5">
                    <div v-if="column === 'item'" class="space-y-2">
                      <div class="h-3 w-4/5 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
                      <div class="h-2.5 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                    </div>
                    <div v-else-if="column === 'flow'" class="space-y-2">
                      <div class="h-3 w-3/4 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
                      <div class="h-2.5 w-2/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                    </div>
                    <div v-else-if="column === 'details'" class="space-y-2">
                      <div class="h-3 w-full animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
                      <div class="h-2.5 w-3/4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                    </div>
                    <div
                      v-else
                      class="h-6 animate-pulse rounded-md"
                      :class="[cellClass(column), column === 'details' ? 'w-full max-w-56' : '']"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import MetricCardSkeleton from './MetricCardSkeleton.vue';

const props = defineProps({
  activeTab: { type: String, default: 'requests' },
  fullPage: { type: Boolean, default: false },
});

const tabTables = {
  requests: [
    { title: 'Incoming Requests Awaiting Custodian Approval', minWidth: 'min-w-[64rem]', columns: ['person', 'item', 'category', 'qty', 'stock', 'date', 'action'] },
    { title: 'Assignments Awaiting End-User Acceptance', minWidth: 'min-w-[48rem]', columns: ['person', 'item', 'qty', 'date', 'status'] },
  ],
  transfers: [
    { minWidth: 'min-w-[56rem]', columns: ['flow', 'item', 'qty', 'date', 'action'] },
  ],
  returns: [
    { minWidth: 'min-w-[52rem]', columns: ['person', 'item', 'qty', 'date', 'action'] },
  ],
  history: [
    { minWidth: 'min-w-[64rem]', columns: ['qty', 'item', 'person', 'person', 'date', 'status', 'action'] },
  ],
  ledger: [
    { minWidth: 'min-w-[64rem] table-fixed', columns: ['date', 'item', 'status', 'qty', 'qty', 'qty', 'person', 'details'] },
  ],
};

const tableGroups = computed(() => tabTables[props.activeTab] ?? tabTables.requests);

function headerWidth(column) {
  return {
    person: 'w-20',
    item: 'w-24',
    category: 'w-16',
    qty: 'ml-auto w-10',
    stock: 'w-20',
    date: 'w-24',
    action: 'ml-auto w-16',
    status: 'w-20',
    flow: 'w-24',
    details: 'w-32',
  }[column] ?? 'w-20';
}

function cellClass(column) {
  const base = 'bg-gray-100 dark:bg-white/5';
  if (column === 'status' || column === 'stock') return `${base} w-24 rounded-md`;
  if (column === 'action') return `${base} ml-auto w-16 rounded-md`;
  if (column === 'qty') return `${base} ml-auto w-10 rounded-md`;
  if (column === 'date') return `${base} w-24 rounded-md`;
  if (column === 'person') return `${base} w-4/5 rounded-md`;
  if (column === 'category') return `${base} w-24 rounded-md`;
  return `${base} w-3/4 rounded-md`;
}
</script>
