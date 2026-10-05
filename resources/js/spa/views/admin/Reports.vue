<template>
  <div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2.5">
      <div>
        <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">System Reports</h1>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cross-role inventory and account summary.</p>
      </div>
      <button type="button" class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5" :disabled="downloading" @click="download">
        {{ downloading ? 'Loading…' : 'Refresh Report Data' }}
      </button>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro :loading="loading" title="Active Users" :value="format(metrics.activeUsers)" subtitle="Active accounts" />
        <MetricCard compact dense micro :loading="loading" title="Pending Onboarding" :value="format(metrics.pendingOnboarding)" subtitle="Temporary passwords" />
        <MetricCard compact dense micro :loading="loading" title="Pending Requests" :value="format(metrics.pendingRequests)" subtitle="Awaiting approval" />
        <MetricCard compact dense micro :loading="loading" title="Open Maintenance" :value="format(metrics.openMaintenance)" subtitle="Not completed" />
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Users by Role</h2>
          <div v-if="loading" class="mt-3 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else type="bar" height="240" :options="barOptions(roleData)" :series="barSeries(roleData)" />
        </section>
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Requests by Status</h2>
          <div v-if="loading" class="mt-3 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else type="donut" height="240" :options="donutOptions(requestStatusData)" :series="donutSeries(requestStatusData)" />
        </section>
      </div>

      <section class="mt-4 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Maintenance by Status</h2>
        <div v-if="loading" class="mt-2.5 space-y-2" aria-hidden="true">
          <div v-for="row in 4" :key="row" class="flex items-center justify-between border-b border-gray-100 py-1.5 dark:border-white/5">
            <div class="h-3 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <ul v-else class="mt-2.5 divide-y divide-gray-100 dark:divide-white/5">
          <li v-for="(row, index) in maintenanceStatusData" :key="index" class="flex items-center justify-between py-1.5 text-xs">
            <span class="text-gray-600 dark:text-gray-400">{{ row.label }}</span>
            <span class="font-semibold text-gray-800 dark:text-white/90">{{ format(row.value) }}</span>
          </li>
        </ul>
        <p v-if="!loading && !maintenanceStatusData.length" class="mt-2.5 text-xs text-gray-500">No maintenance records.</p>
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
const downloading = ref(false);
const error = ref('');
const metrics = ref({});
const roleData = ref([]);
const requestStatusData = ref([]);
const maintenanceStatusData = ref([]);

const foreColor = computed(() => (theme.theme === 'dark' ? '#98a2b3' : '#475467'));

function barOptions(rows) {
  return {
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
      categories: rows.map((row) => row.label),
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
  };
}

function barSeries(rows) {
  return [{ name: 'Count', data: rows.map((row) => row.value) }];
}

function donutOptions(rows) {
  return {
    chart: {
      type: 'donut',
      foreColor: foreColor.value,
      fontFamily: 'Outfit, sans-serif',
    },
    labels: rows.map((row) => row.label),
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
              label: 'Total Requests',
              color: '#98a2b3',
              fontSize: '10px',
              fontWeight: 600,
            },
          },
        },
      },
    },
    colors: ['#059669', '#d97706', '#0284c7', '#be123c', '#65a30d', '#9333ea'],
    stroke: { colors: [theme.theme === 'dark' ? '#111827' : '#ffffff'], width: 2 },
    tooltip: { theme: theme.theme === 'dark' ? 'dark' : 'light' },
    noData: { text: 'No data' },
  };
}

function donutSeries(rows) {
  return rows.map((row) => row.value);
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'admin-reports',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/admin/reports');
      return data;
    },
    applyData: applyPayload,
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

function applyPayload(data) {
  metrics.value = data.metrics ?? {};
  roleData.value = data.roleData ?? [];
  requestStatusData.value = data.requestStatusData ?? [];
  maintenanceStatusData.value = data.maintenanceStatusData ?? [];
}

async function download() {
  downloading.value = true;
  try {
    const { data } = await api.get('/admin/reports/download');
    applyPayload(data);
  } finally {
    downloading.value = false;
  }
}

onMounted(() => load());
</script>
