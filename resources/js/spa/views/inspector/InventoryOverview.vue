<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inspection</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Items flagged by the property custodian and awaiting inspection.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <template v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro title="Units" :value="format(metrics.units)" subtitle="Non-disposed" :loading="loading" />
        <MetricCard compact dense micro title="Available" :value="format(metrics.available)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro title="Assigned" :value="format(metrics.assigned)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro title="Value" :value="money(metrics.value)" subtitle="Pesos" :loading="loading" />
      </div>

      <!-- Inspection queue -->
      <div class="mt-4">
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2.5">
          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            {{ queueTotal }} item record(s) flagged by the property custodian and awaiting inspection.
          </p>
          <button
            type="button"
            class="compact-btn compact-btn--primary"
            @click="openScanner"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0z" />
            </svg>
            Scan Item
          </button>
        </div>

        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <table class="w-full min-w-[56rem] text-left text-xs">
            <thead>
              <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-800">
                <th class="px-4 py-3.5">Item</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5">Serial</th>
                <th class="px-4 py-3.5 text-right">Quantity</th>
                <th class="px-4 py-3.5">Returns to</th>
                <th class="px-4 py-3.5">Flagged</th>
                <th class="px-4 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody v-if="queueLoading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
              <tr v-for="row in 5" :key="row">
                <td v-for="column in 7" :key="column" class="px-4 py-3.5">
                  <div class="compact-skeleton h-3" :class="column === 1 ? 'w-2/3' : 'ml-auto w-16'" />
                </td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="row in queue" :key="row.inspection_id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3.5">
                  <p class="font-medium text-gray-800 dark:text-white/90">{{ row.item_name }}</p>
                  <p class="font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ row.inventory_item_no ?? `#${row.inventory_id}` }}</p>
                </td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ row.category }}</td>
                <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">{{ row.serial_number || '—' }}</td>
                <td class="px-4 py-3.5 text-right text-gray-700 dark:text-gray-300">{{ format(row.quantity) }} {{ row.unit }}</td>
                <td class="px-4 py-3.5">
                  <span class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-[11px] font-medium capitalize text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                    {{ (row.status_before ?? '').replace(/_/g, ' ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                  <p>{{ row.flagged_by }}</p>
                  <p v-if="row.flagged_at">{{ row.flagged_at }}</p>
                  <p v-if="row.reason" class="mt-1 max-w-[18rem] truncate" :title="row.reason">{{ row.reason }}</p>
                </td>
                <td class="px-4 py-3.5 text-right">
                  <button
                    type="button"
                    class="compact-btn compact-btn--sm border border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                    @click="openScanner"
                  >
                    Inspect
                  </button>
                </td>
              </tr>
              <tr v-if="!queueLoading && !queue.length">
                <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500">
                  Nothing is awaiting inspection right now.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <InspectItemModal :open="scannerOpen" @close="scannerOpen = false" @inspected="onInspected" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import InspectItemModal from './InspectItemModal.vue';

const loading = ref(true);
const error = ref('');
const metrics = ref({});

const queue = ref([]);
const queueTotal = ref(0);
const queueLoading = ref(true);
const scannerOpen = ref(false);

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadQueue(options = {}) {
  await loadCachedPage({
    page: 'inspector-inspection-queue',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/inspector/inspection/queue');
      return data;
    },
    applyData: (data) => {
      queue.value = data.records ?? [];
      queueTotal.value = Number(data.total ?? queue.value.length);
    },
    onStart: () => {
      queueLoading.value = true;
    },
    onDone: () => {
      queueLoading.value = false;
    },
    onError: (requestError) => {
      queue.value = [];
      queueTotal.value = 0;
      error.value = requestError?.response?.data?.message ?? 'Could not load the inspection queue.';
    },
  });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'inspector-inventory',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/inspector/inventory/overview');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load the inventory overview.';
    },
  });

  await loadQueue(options);
}

function openScanner() {
  scannerOpen.value = true;
}

async function onInspected(data) {
  scannerOpen.value = false;
  useToastStore().success('Inspection recorded', data?.message ?? 'The item was marked as inspected.');
  forgetPageCache();
  await load({ background: true });
}

onMounted(() => load());
</script>