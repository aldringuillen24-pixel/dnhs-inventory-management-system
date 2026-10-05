<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Reports</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-only movement and value summary.</p>
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
        <MetricCard compact dense micro title="Value" :value="money(metrics.totalValue)" subtitle="Pesos" :loading="loading" />
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Units by Category</h2>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <apexchart v-else type="bar" height="240" :options="categoryOptions" :series="categorySeries" />
        </section>
        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Units by Status</h2>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <apexchart v-else type="donut" height="240" :options="statusOptions" :series="statusSeries" />
        </section>
      </div>

      <section class="compact-panel mt-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Transactions</h2>
        <ul v-if="loading" class="compact-panel__body space-y-2" aria-hidden="true">
          <li v-for="row in 4" :key="row" class="flex items-center justify-between gap-2 py-1.5">
            <div class="min-w-0 flex-1 space-y-1.5">
              <div class="compact-skeleton h-3 w-2/3" />
              <div class="compact-skeleton h-2.5 w-1/3" />
            </div>
            <div class="compact-skeleton h-3 w-12" />
          </li>
        </ul>
        <ul v-else-if="transactions.length" class="compact-panel__body divide-y divide-gray-100 dark:divide-white/5">
          <li v-for="entry in transactions" :key="entry.id" class="flex items-center justify-between gap-2 py-1.5 text-xs">
            <div class="min-w-0">
              <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ entry.item?.item_name ?? 'Transaction' }}</p>
              <p class="truncate text-[11px] text-gray-500">{{ personName(entry.user) }} · {{ formatDate(entry.transaction_date) }}</p>
            </div>
            <span class="font-semibold">{{ format(entry.quantity) }}</span>
          </li>
        </ul>
        <p v-else class="compact-empty">No recent transactions.</p>
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
const transactions = ref([]);

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
  tooltip: { theme: theme.theme === 'dark' ? 'dark' : 'light' },
  noData: { text: 'No data' },
}));

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function personName(user) {
  if (!user) return 'Unknown';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-reports',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/reports');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categoryData.value = data.categoryData ?? [];
      statusData.value = data.statusData ?? [];
      transactions.value = data.recentTransactions ?? [];
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load reports.';
    },
  });
}

onMounted(() => load());
</script>
