<template>
  <div>
    <div class="mb-4">
      <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">School Head Dashboard</h1>
      <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Read-only inventory oversight.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro title="Total Units" :value="format(metrics.totalUnits)" subtitle="Non-disposed" :loading="loading" />
        <MetricCard compact dense micro title="Available" :value="format(metrics.availableUnits)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro title="Assigned" :value="format(metrics.assignedUnits)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro title="Pending Requests" :value="format(metrics.pendingRequests)" subtitle="All workflows" :loading="loading" />
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Units by Category</h2>
          <div v-if="loading" class="mt-2.5 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else type="bar" height="240" :options="categoryOptions" :series="categorySeries" />
        </section>
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Units by Status</h2>
          <div v-if="loading" class="mt-2.5 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else type="donut" height="240" :options="statusOptions" :series="statusSeries" />
        </section>
      </div>

      <section class="mt-4 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Activity</h2>
        <ul v-if="loading" class="mt-2.5 space-y-2" aria-hidden="true">
          <li v-for="row in 4" :key="row" class="flex items-center justify-between gap-2 py-1.5">
            <div class="min-w-0 flex-1 space-y-1.5">
              <div class="h-3 w-2/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-2.5 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
            <div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </li>
        </ul>
        <ul v-else-if="activity.length" class="mt-2.5 divide-y divide-gray-100 dark:divide-white/5">
          <li v-for="(entry, index) in activity" :key="index" class="flex items-center justify-between gap-2 py-1.5 text-xs">
            <div class="min-w-0">
              <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ entry.item }} · {{ entry.type }}</p>
              <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">{{ entry.remarks }} — {{ entry.user }}</p>
            </div>
            <span class="shrink-0 font-semibold text-gray-600 dark:text-gray-300">{{ format(entry.quantity) }}</span>
          </li>
        </ul>
        <p v-else class="mt-2.5 text-xs text-gray-500 dark:text-gray-400">No recent movements.</p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
import apexchart from 'vue3-apexcharts';
import { loadCachedPage } from '../../lib/pageCache';
import { useThemeStore } from '../../stores/theme';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';

const theme = useThemeStore();
const loading = ref(true);
const error = ref('');
const metrics = ref({});
const categoryData = ref([]);
const statusData = ref([]);
const activity = ref([]);

const foreColor = computed(() => (theme.theme === 'dark' ? '#98a2b3' : '#475467'));

const categorySeries = computed(() => [{ name: 'Units', data: categoryData.value.slice(0, 10).map((row) => row.quantity) }]);
const categoryOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    foreColor: foreColor.value,
    fontFamily: 'Outfit, sans-serif',
  },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: {
    enabled: false,
    style: { colors: ['#ffffff'], fontSize: '10px', fontWeight: 600 },
  },
  colors: ['#465fff'],
  xaxis: {
    categories: categoryData.value.slice(0, 10).map((row) => row.label),
    labels: { style: { fontSize: '11px', fontWeight: 500 } },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', fontWeight: 500 } },
  },
  grid: {
    borderColor: theme.theme === 'dark' ? '#1d2939' : '#e4e7ec',
    strokeDashArray: 4,
  },
  tooltip: { theme: theme.theme === 'dark' ? 'dark' : 'light' },
  noData: { text: 'No data' },
}));

const statusSeries = computed(() => statusData.value.map((row) => row.quantity));
const statusOptions = computed(() => ({
  chart: {
    type: 'donut',
    foreColor: foreColor.value,
    fontFamily: 'Outfit, sans-serif',
  },
  labels: statusData.value.map((row) => row.label),
  legend: {
    position: 'bottom',
    fontSize: '11px',
    markers: { radius: 4 },
    itemMargin: { horizontal: 6, vertical: 2 },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '72%',
        labels: {
          show: true,
          name: {
            show: true,
            offsetY: -12,
            fontSize: '10px',
            fontWeight: 600,
            color: '#98a2b3',
          },
          value: {
            show: true,
            offsetY: -5,
            fontSize: '15px',
            fontWeight: 700,
            color: theme.theme === 'dark' ? '#38bdf8' : '#0284c7',
          },
          total: {
            show: true,
            label: 'Total Units',
            color: '#98a2b3',
            fontSize: '10px',
            fontWeight: 600,
          },
        },
      },
    },
  },
  colors: ['#059669', '#0284c7', '#d97706', '#be123c', '#65a30d', '#9333ea'],
  stroke: { colors: [theme.theme === 'dark' ? '#111827' : '#ffffff'], width: 2 },
  // Default slice percentages off: the center total, legend and tooltip
  // already carry the figures.
  dataLabels: { enabled: false },
  tooltip: { theme: theme.theme === 'dark' ? 'dark' : 'light' },
  noData: { text: 'No data' },
}));

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-dashboard',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/dashboard');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categoryData.value = data.categoryData ?? [];
      statusData.value = data.statusData ?? [];
      activity.value = data.recentActivity ?? [];
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load the dashboard.';
    },
  });
}

onMounted(() => load());
</script>
