<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory Overview</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-only category summary.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro title="Units" :value="format(metrics.units)" subtitle="Non-disposed" :loading="loading" />
        <MetricCard compact dense micro title="Available" :value="format(metrics.available)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro title="Assigned" :value="format(metrics.assigned)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro title="Value" :value="money(metrics.value)" subtitle="Pesos" :loading="loading" />
      </div>

      <div class="mt-4 overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <table class="w-full min-w-[48rem] text-left text-xs">
          <thead>
            <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
              <th class="px-4 py-3.5">Category</th>
              <th class="px-4 py-3.5 text-right">Total</th>
              <th class="px-4 py-3.5 text-right">Available</th>
              <th class="px-4 py-3.5 text-right">Assigned</th>
              <th class="px-4 py-3.5 text-right">Value</th>
            </tr>
          </thead>
          <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 5" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'ml-auto w-12'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
            <tr v-for="row in categories" :key="row.name" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ row.name }}</td>
              <td class="px-4 py-3.5 text-right">{{ format(row.total) }}</td>
              <td class="px-4 py-3.5 text-right">{{ format(row.available) }}</td>
              <td class="px-4 py-3.5 text-right">{{ format(row.assigned) }}</td>
              <td class="px-4 py-3.5 text-right">₱{{ money(row.value) }}</td>
            </tr>
            <tr v-if="!loading && !categories.length">
              <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500">No inventory to show.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';

const loading = ref(true);
const error = ref('');
const metrics = ref({});
const categories = ref([]);

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-inventory',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/inventory/overview');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categories.value = data.categories ?? [];
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
}

onMounted(() => load());
</script>
