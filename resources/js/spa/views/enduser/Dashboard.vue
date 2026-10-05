<template>
  <div class="compact-page gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">My Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your assigned items and pending work.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <RouterLink
          to="/end-user/requests"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-brand-600"
        >
          Request Item
        </RouterLink>
      </div>
    </div>

    <div v-if="error" class="compact-alert">
      <span>{{ error }}</span>
      <button type="button" class="compact-btn compact-btn--sm compact-btn--outline" @click="load">Retry</button>
    </div>

    <div v-else class="flex flex-col gap-6">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro title="Assigned Items" :value="format(metrics.assignedItems)" subtitle="Units in your care" :loading="loading" />
        <MetricCard compact dense micro title="Pending Requests" :value="format(metrics.pendingRequests)" subtitle="Awaiting action" :loading="loading" />
        <MetricCard compact dense micro title="Pending Returns" :value="format(metrics.pendingReturns)" subtitle="Return approvals" :loading="loading" />
        <MetricCard compact dense micro title="Due Soon" :value="format(metrics.dueSoon)" subtitle="Return within 30 days" :loading="loading" />
      </div>

      <div class="compact-grid-2 gap-5">
        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Assigned by Category</h2>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <apexchart v-else type="bar" height="240" :options="categoryOptions" :series="categorySeries" />
        </section>
        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Assigned by Status</h2>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <apexchart v-else type="donut" height="240" :options="statusOptions" :series="statusSeries" />
        </section>
      </div>

      <div class="compact-grid-2 gap-5">
        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pending Breakdown</h2>
          <div v-if="loading" class="compact-panel__body space-y-2" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="flex items-center justify-between">
              <div class="compact-skeleton h-3 w-1/3" />
              <div class="compact-skeleton h-3 w-12" />
            </div>
          </div>
          <template v-else>
            <ul class="compact-panel__body space-y-1.5">
              <li v-for="row in pendingRequestData" :key="row.label" class="flex items-center justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-400">{{ row.label }}</span>
                <span class="font-semibold text-gray-800 dark:text-white/90">{{ row.value }}</span>
              </li>
            </ul>
            <h2 class="mt-3.5 text-sm font-semibold text-gray-800 dark:text-white/90">Needs Attention</h2>
            <ul class="mt-2.5 space-y-1.5">
              <li v-if="!conditionData.length" class="text-xs text-gray-500">Nothing flagged.</li>
              <li v-for="row in conditionData" :key="row.label" class="flex items-center justify-between text-xs">
                <span class="text-gray-600 dark:text-gray-400">{{ row.label }}</span>
                <span class="font-semibold text-warning-600 dark:text-warning-400">{{ row.value }}</span>
              </li>
            </ul>
          </template>
        </section>

        <section class="compact-panel">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Activity</h2>
          <ul v-if="loading" class="compact-panel__body space-y-2" aria-hidden="true">
            <li v-for="row in 4" :key="row" class="flex items-center justify-between gap-2 py-1.5">
              <div class="min-w-0 flex-1 space-y-1.5">
                <div class="compact-skeleton h-3 w-2/3" />
                <div class="compact-skeleton h-2.5 w-1/3" />
              </div>
              <div class="compact-skeleton h-5 w-20" />
            </li>
          </ul>
          <ul v-else-if="activity.length" class="compact-panel__body divide-y divide-gray-100 dark:divide-white/5">
            <li v-for="entry in activity" :key="entry.id" class="flex items-center justify-between gap-2 py-2 text-xs">
              <div class="min-w-0">
                <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ entry.item?.item_name ?? 'Request update' }}</p>
                <p class="truncate text-[11px] text-gray-500">{{ formatDate(entry.updated_at) }}</p>
              </div>
              <StatusBadge :status="entry.status" />
            </li>
          </ul>
          <p v-else class="compact-empty">No recent activity.</p>
        </section>
      </div>
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
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';

const theme = useThemeStore();
const loading = ref(true);
const error = ref('');
const metrics = ref({});
const categoryData = ref([]);
const statusData = ref([]);
const pendingRequestData = ref([]);
const conditionData = ref([]);
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
  noData: { text: 'No assigned items' },
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
            label: 'Total Assigned',
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
  noData: { text: 'No assigned items' },
}));

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function formatDate(value) {
  if (!value) {
    return '';
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'end-user-dashboard',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/end-user/dashboard');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categoryData.value = data.categoryData ?? [];
      statusData.value = data.statusData ?? [];
      pendingRequestData.value = data.pendingRequestData ?? [];
      conditionData.value = data.conditionData ?? [];
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
