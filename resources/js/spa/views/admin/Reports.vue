<template>
  <div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2.5">
      <div>
        <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">System Reports</h1>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Cross-role inventory and account summary.</p>
      </div>
      <a
        href="/api/admin/reports/download"
        class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-800"
        title="Download this report as a PDF"
      >
        Export PDF
      </a>
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

      <div class="mt-4">
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Users by Role</h2>
          <div v-if="loading" class="mt-3 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else type="bar" height="240" :options="barOptions(roleData)" :series="barSeries(roleData)" />
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

      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="min-w-0 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pending Onboarding</h2>
            <span v-if="staleOnboardingCount > 0" class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
              {{ staleOnboardingCount }} stale
            </span>
          </div>
          <div v-if="loading" class="mt-2.5 space-y-2" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="flex items-center justify-between border-b border-gray-100 py-1.5 dark:border-white/5">
              <div class="h-3 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
          </div>
          <ul v-else-if="onboardingQueue.length" class="mt-2.5 divide-y divide-gray-100 dark:divide-white/5">
            <li v-for="row in onboardingQueue" :key="row.username" class="flex items-center justify-between gap-2 py-1.5 text-xs">
              <div class="min-w-0">
                <span class="block truncate font-semibold text-gray-800 dark:text-white/90">{{ row.username }}</span>
                <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ row.role_name }} · waiting {{ row.waiting_days }} {{ row.waiting_days === 1 ? 'day' : 'days' }}</span>
              </div>
              <span
                class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold"
                :class="row.stale ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300'"
              >
                {{ row.stale ? 'Stale' : 'Waiting' }}
              </span>
            </li>
          </ul>
          <p v-if="!loading && !onboardingQueue.length" class="mt-2.5 text-xs text-gray-500">No accounts awaiting onboarding.</p>
        </section>

        <section class="min-w-0 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Account Activity</h2>
          <div v-if="loading" class="mt-2.5 space-y-2" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="flex items-center justify-between border-b border-gray-100 py-1.5 dark:border-white/5">
              <div class="h-3 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
          </div>
          <ul v-else-if="recentActivity.length" class="mt-2.5 divide-y divide-gray-100 dark:divide-white/5">
            <li v-for="entry in recentActivity" :key="entry.id" class="py-1.5 text-xs">
              <span class="font-semibold text-gray-800 dark:text-white/90">{{ prettyAction(entry.action) }}</span>
              <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">
                {{ entry.actor_name || 'System' }} → {{ entry.target_name || '—' }} · {{ formatDateTime(entry.created_at) }}
              </span>
            </li>
          </ul>
          <p v-if="!loading && !recentActivity.length" class="mt-2.5 text-xs text-gray-500">No account activity recorded.</p>
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

const theme = useThemeStore();

const loading = ref(true);
const error = ref('');
const metrics = ref({});
const roleData = ref([]);
const requestStatusData = ref([]);
const maintenanceStatusData = ref([]);
const onboardingQueue = ref([]);
const recentActivity = ref([]);

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

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function prettyAction(action) {
  return String(action ?? 'Activity').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDateTime(value) {
  if (!value) {
    return 'Unknown time';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

const staleOnboardingCount = computed(() => onboardingQueue.value.filter((row) => row.stale).length);

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
  onboardingQueue.value = data.onboardingQueue ?? [];
  recentActivity.value = data.recentActivity ?? [];
}

onMounted(() => load());
</script>
