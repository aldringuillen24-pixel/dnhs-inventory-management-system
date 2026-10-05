<template>
  <div class="space-y-6">
    <!-- Hero / Welcome Banner -->
    <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
            Administrator
          </div>
          <h1 class="mt-0.5 text-lg font-bold tracking-tight text-gray-900 dark:text-white">Admin Dashboard</h1>
          <p class="mt-0.5 max-w-2xl text-xs text-gray-500 dark:text-gray-400">Users, roles, and account health.</p>
        </div>

        <nav aria-label="Dashboard shortcuts" class="flex flex-wrap items-center gap-2.5">
          <RouterLink
            to="/admin/users"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="UsersRound" class="h-4 w-4" />
            Manage Users
          </RouterLink>

          <RouterLink
            to="/admin/reports"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="ChartColumn" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
            System Reports
          </RouterLink>

          <RouterLink
            to="/admin/settings"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="Settings" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
            Settings
          </RouterLink>
        </nav>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <div class="flex items-center gap-3">
        <svg class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ error }}</span>
      </div>
      <button
        type="button"
        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <!-- Dashboard Content -->
    <div v-else class="space-y-6">
      <!-- Key Metrics -->
      <div class="grid gap-4 sm:grid-cols-2">
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Active Users"
          :value="format(metrics.activeUsers)"
          subtitle="Active accounts"
          accent-class="border-l-4 border-l-emerald-500"
          icon-class="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
        >
          <template #icon>
            <LucideIcon :icon="UsersRound" class="h-4 w-4" />
          </template>
        </MetricCard>

        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Pending Onboarding"
          :value="format(metrics.pendingOnboarding)"
          subtitle="Temporary passwords"
          accent-class="border-l-4 border-l-amber-500"
          icon-class="bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
        >
          <template #badge>
            <span v-if="metrics.pendingOnboarding > 0" class="inline-flex rounded bg-amber-100 px-1.5 py-px text-[10px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
              Action needed
            </span>
          </template>
          <template #icon>
            <LucideIcon :icon="KeyRound" class="h-4 w-4" />
          </template>
        </MetricCard>
      </div>

      <!-- Charts Panel -->
      <div class="custodian-panel overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200/80 bg-gray-50/50 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-900/50">
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">Accounts Overview</h2>
          <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Role distribution and account status.</p>
        </div>
        <div v-if="loading" class="grid gap-4 p-3.5 xl:grid-cols-2 xl:gap-0" aria-hidden="true">
          <section v-for="chart in 2" :key="chart" class="space-y-3" :class="{ 'xl:border-l xl:border-gray-200 xl:pl-5 dark:xl:border-gray-800': chart === 2 }">
            <div class="h-3 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </section>
        </div>
        <div v-else class="grid gap-4 p-3.5 xl:grid-cols-2 xl:gap-0">
          <section class="xl:pr-5">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Users by Role</h3>
            <apexchart type="bar" height="240" :options="barOptions(roleData, 'label')" :series="barSeries(roleData)" />
          </section>
          <section class="xl:border-l xl:border-gray-200 xl:pl-5 dark:xl:border-gray-800">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Accounts by Status</h3>
            <apexchart type="donut" height="240" :options="donutOptions(accountStatusData)" :series="donutSeries(accountStatusData)" />
          </section>
        </div>
      </div>

      <!-- Audit Activity Panel -->
      <div class="custodian-panel overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200/80 bg-gray-50/50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/50 sm:px-5">
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Audit Activity</h2>
          <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Latest recorded account events.</p>
        </div>
        <ul v-if="auditData.length" class="divide-y divide-gray-100 px-4 dark:divide-white/5 sm:px-5">
          <li v-for="(row, index) in auditData" :key="index" class="flex items-center justify-between gap-3 py-2.5 text-sm">
            <span class="flex min-w-0 items-center gap-2.5">
              <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
              <span class="truncate font-medium text-gray-800 dark:text-white/90">{{ row.label }}</span>
            </span>
            <span class="shrink-0 font-semibold tabular-nums text-gray-600 dark:text-gray-300">{{ format(row.value) }}</span>
          </li>
        </ul>
        <p v-else class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-5">No audit activity.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ChartColumn, KeyRound, Settings, UsersRound } from 'lucide';
import api from '../../lib/axios';
import apexchart from 'vue3-apexcharts';
import { loadCachedPage } from '../../lib/pageCache';
import { useThemeStore } from '../../stores/theme';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';

const theme = useThemeStore();
const loading = ref(true);
const error = ref('');
const metrics = ref({});
const roleData = ref([]);
const accountStatusData = ref([]);
const auditData = ref([]);

const foreColor = computed(() => (theme.theme === 'dark' ? '#98a2b3' : '#475467'));

function barOptions(rows, labelKey) {
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
      categories: rows.map((row) => row[labelKey] ?? row.label),
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
              fontSize: '12px',
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
              label: 'Total Accounts',
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
    page: 'admin-dashboard',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/admin/dashboard');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      roleData.value = data.roleData ?? [];
      accountStatusData.value = data.accountStatusData ?? [];
      auditData.value = data.auditData ?? [];
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load the admin dashboard.';
    },
  });
}

onMounted(() => load());
</script>
