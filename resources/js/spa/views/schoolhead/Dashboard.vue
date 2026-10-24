<template>
  <div>
    <div class="mb-4">
      <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">School Head Dashboard</h1>
      <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">A quick view of inventory and recent system logs.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro title="Total Units" :value="format(metrics.units)" subtitle="Non-disposed inventory" :loading="loading" />
        <MetricCard compact dense micro title="Available" :value="format(metrics.available)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro title="Assigned" :value="format(metrics.assigned)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro title="Inventory Value" :value="money(metrics.value)" subtitle="Pesos" :loading="loading" />
      </div>

      <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Inventory by Category</h2>
          <div v-if="loading" class="mt-2.5 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else-if="categoryData.length" type="bar" height="240" :options="categoryOptions" :series="categorySeries" />
          <p v-else class="mt-2.5 flex h-[240px] items-center justify-center text-xs text-gray-500">No inventory by category.</p>
        </section>
        <section class="min-w-0 overflow-hidden rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Inventory by Status</h2>
          <div v-if="loading" class="mt-2.5 h-[240px] animate-pulse rounded-md bg-gray-100 dark:bg-white/5" aria-hidden="true" />
          <apexchart v-else-if="statusData.length" type="donut" height="240" :options="statusOptions" :series="statusSeries" />
          <p v-else class="mt-2.5 flex h-[240px] items-center justify-center text-xs text-gray-500">No inventory status to display.</p>
        </section>
      </div>

      <section class="mt-4">
        <div class="mb-2 flex items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Logs Overview</h2>
          <router-link to="/school-head/audit-logs" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Open Logs</router-link>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="card in logCards"
            :key="card.key"
            class="rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
          >
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ card.title }}</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
              {{ loading ? '—' : format(card.count) }}
            </p>
            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ card.description }}</p>
          </article>
        </div>
      </section>

      <section class="mt-4 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Logs</h2>
            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Latest activity from the Logs page.</p>
          </div>
          <router-link to="/school-head/audit-logs" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">View all logs</router-link>
        </div>
        <ul v-if="loading" class="mt-2.5 space-y-2" aria-hidden="true">
          <li v-for="row in 4" :key="row" class="flex items-center justify-between gap-2 py-1.5">
            <div class="min-w-0 flex-1 space-y-1.5">
              <div class="h-3 w-2/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-2.5 w-1/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
            <div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </li>
        </ul>
        <ul v-else-if="recentActivity.length" class="mt-2.5 divide-y divide-gray-100 dark:divide-white/5">
          <li v-for="entry in recentActivity" :key="entry.id" class="flex items-center justify-between gap-3 py-2 text-xs">
            <div class="min-w-0">
              <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ entry.title }}</p>
              <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">{{ entry.details }}<span v-if="entry.actor"> · {{ entry.actor }}</span></p>
            </div>
            <div class="shrink-0 text-right">
              <span class="block font-semibold text-gray-600 dark:text-gray-300">{{ entry.type }}</span>
              <span class="text-[10px] text-gray-400">{{ formatDate(entry.date) }}</span>
            </div>
          </li>
        </ul>
        <p v-else class="mt-2.5 text-xs text-gray-500 dark:text-gray-400">No recent logs.</p>
      </section>
      <div class="mt-3 flex justify-end">
        <router-link to="/school-head/inventory" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">Open Inventory Overview</router-link>
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
const categoryData = ref([]);
const statusData = ref([]);
const logData = ref({});

const foreColor = computed(() => (theme.theme === 'dark' ? '#98a2b3' : '#475467'));

const categorySeries = computed(() => [{ name: 'Units', data: categoryData.value.slice(0, 10).map((row) => row.total) }]);
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
    categories: categoryData.value.slice(0, 10).map((row) => row.name),
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

const recentActivity = computed(() => {
  const entries = [];
  const actorName = (user) => {
    if (!user) return '';
    const name = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
    return name || user.username || '';
  };
  const describe = (type, action, item, details, actor, date, id) => ({
    id: `${type}-${id}`,
    date,
    type,
    title: `${String(action ?? 'activity').replaceAll('_', ' ')}${item ? ` · ${item}` : ''}`,
    details: details || 'No additional details.',
    actor: typeof actor === 'string' ? actor : actorName(actor),
  });

  (logData.value.logs?.data ?? []).forEach((log) => entries.push(describe(
    'Account',
    log.action,
    log.target_name ?? actorName(log.target_user),
    '',
    actorName(log.actor),
    log.created_at,
    log.id,
  )));
  (logData.value.movements ?? []).forEach((log) => entries.push(describe(
    'Movement',
    log.movement_type,
    log.inventory?.item_name,
    log.display_details || log.notes,
    actorName(log.user),
    log.created_at,
    log.id,
  )));
  (logData.value.itemLogs ?? []).forEach((log) => entries.push(describe(
    'Item',
    log.action,
    log.item_name,
    log.reason,
    actorName(log.user),
    log.created_at,
    log.id,
  )));
  ['incidentLogs', 'requestLogs', 'arrivalLogs'].forEach((key) => {
    (logData.value[key] ?? []).forEach((log) => entries.push(describe(
      log.type,
      log.action,
      log.item_name,
      log.details,
      log.actor,
      log.occurred_at,
      log.id,
    )));
  });

  return entries
    .filter((entry) => entry.date)
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
    .slice(0, 8);
});

const logCards = computed(() => [
  {
    key: 'accounts',
    title: 'Account Activity',
    count: logData.value.logs?.total ?? 0,
    description: 'Account audit records',
  },
  {
    key: 'movements',
    title: 'Item Movements',
    count: logData.value.movements?.length ?? 0,
    description: 'Latest movement records',
  },
  {
    key: 'item-logs',
    title: 'Item Logs',
    count: logData.value.itemLogs?.length ?? 0,
    description: 'Item edits and deletions',
  },
  {
    key: 'incidents',
    title: 'Incident & Maintenance',
    count: logData.value.incidentLogs?.length ?? 0,
    description: 'Incident and maintenance updates',
  },
  {
    key: 'requests',
    title: 'Request Decisions',
    count: logData.value.requestLogs?.length ?? 0,
    description: 'Recorded request decisions',
  },
  {
    key: 'arrivals',
    title: 'Arrival Inspections',
    count: logData.value.arrivalLogs?.length ?? 0,
    description: 'Arrival inspection events',
  },
]);

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-dashboard-v2',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const [overviewResponse, logsResponse] = await Promise.all([
        api.get('/school-head/inventory/overview'),
        api.get('/school-head/audit-logs'),
      ]);
      return { overview: overviewResponse.data, logs: logsResponse.data };
    },
    applyData: ({ overview, logs }) => {
      metrics.value = overview.metrics ?? {};
      categoryData.value = overview.categories ?? [];
      statusData.value = (overview.monitorRows ?? []).reduce((rows, item) => {
        const current = rows.find((row) => row.status === item.status);
        if (current) {
          current.quantity += Number(item.quantity ?? 0);
        } else {
          rows.push({
            status: item.status,
            label: String(item.status_label ?? item.status).replaceAll('_', ' '),
            quantity: Number(item.quantity ?? 0),
          });
        }
        return rows;
      }, []);
      logData.value = logs ?? {};
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
