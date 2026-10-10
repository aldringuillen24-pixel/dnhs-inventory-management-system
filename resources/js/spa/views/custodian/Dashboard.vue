<template>
  <div class="space-y-6">
    <!-- Hero / Welcome Banner -->
    <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
            Property Custodian
          </div>
          <h1 class="mt-0.5 text-lg font-bold tracking-tight text-gray-900 dark:text-white">Stockroom Overview</h1>
          <p class="mt-0.5 max-w-2xl text-xs text-gray-500 dark:text-gray-400">Monitor inventory, review pending approvals, and track stock movements.</p>
        </div>

        <nav aria-label="Dashboard shortcuts" class="flex flex-wrap items-center gap-2.5">
          <RouterLink
            to="/inventory"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-all hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="Package" class="h-4 w-4" />
            View Inventory
          </RouterLink>

          <RouterLink
            to="/transactions"
            class="relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="ClipboardList" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
            Review Requests
            <span
              v-if="metrics.pendingRequests > 0"
              class="inline-flex min-w-4 items-center justify-center rounded bg-amber-100 px-1 py-px text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-300"
            >
              {{ metrics.pendingRequests }}
            </span>
          </RouterLink>

          <RouterLink
            to="/reports"
            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900"
          >
            <LucideIcon :icon="ChartColumn" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
            Reports
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
        <LucideIcon :icon="TriangleAlert" class="h-5 w-5 text-rose-500" />
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
      <!-- 6 Key Metrics -->
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <!-- 1. Total Inventory Units -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Inventory Units"
          :value="format(metrics.inventory)"
          subtitle="Total non-disposed items"
          accent-class="border-l-4 border-l-emerald-500 hover:border-emerald-500/80"
          icon-class="bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"
        >
          <template #icon>
            <LucideIcon :icon="Boxes" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 2. Available Units -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Available Units"
          :value="format(metrics.available)"
          subtitle="Ready for assignment"
          accent-class="border-l-4 border-l-teal-500 hover:border-teal-500/80"
          icon-class="bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400"
        >
          <template #icon>
            <LucideIcon :icon="PackageCheck" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 3. Low Stock Groups -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Low Stock Groups"
          :value="format(metrics.lowStock)"
          subtitle="≤ 3 units available"
          accent-class="border-l-4 border-l-amber-500 hover:border-amber-500/80"
          icon-class="bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
        >
          <template #badge>
            <span v-if="metrics.lowStock > 0" class="inline-flex rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
              Needs Attention
            </span>
          </template>
          <template #icon>
            <LucideIcon :icon="TriangleAlert" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 4. Pending Requests -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Pending Requests"
          :value="format(metrics.pendingRequests)"
          subtitle="Awaiting custodian action"
          accent-class="border-l-4 border-l-sky-500 hover:border-sky-500/80"
          icon-class="bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400"
        >
          <template #badge>
            <span v-if="metrics.pendingRequests > 0" class="inline-flex items-center gap-1 rounded-md bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-800 dark:bg-sky-900/40 dark:text-sky-300">
              <span class="h-1.5 w-1.5 rounded-md bg-sky-500 animate-pulse" />
              Action Required
            </span>
          </template>
          <template #icon>
            <LucideIcon :icon="ClipboardList" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 5. Approaching End of Life -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Approaching End of Life"
          :value="format(metrics.approachingLifespan)"
          subtitle="Within 12 months"
          accent-class="border-l-4 border-l-orange-500 hover:border-orange-500/80"
          icon-class="bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400"
        >
          <template #icon>
            <LucideIcon :icon="CalendarClock" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 6. Past Expected End Date -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Past Expected End Date"
          :value="format(metrics.expiredLifespan)"
          subtitle="Requires inspection / disposal"
          accent-class="border-l-4 border-l-rose-500 hover:border-rose-500/80"
          icon-class="bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400"
        >
          <template #badge>
            <span v-if="metrics.expiredLifespan > 0" class="inline-flex rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
              Review
            </span>
          </template>
          <template #icon>
            <LucideIcon :icon="CalendarX2" class="h-4 w-4" />
          </template>
        </MetricCard>
      </div>

      <!-- Charts Row -->
      <div class="grid gap-6 xl:grid-cols-3">
        <!-- Units by Category Bar Chart -->
        <section class="custodian-panel compact-panel flex flex-col justify-between transition-all">
          <div class="compact-panel__header">
            <div class="min-w-0">
              <h2 class="compact-panel__title">Units by Category</h2>
              <p class="compact-panel__subtitle">Top inventory categories by quantity</p>
            </div>
            <span class="compact-chip compact-chip--emerald shrink-0">
              <span class="h-1.5 w-1.5 rounded-md bg-emerald-500" />
              Stock Volume
            </span>
          </div>

          <div class="compact-panel__body">
            <apexchart
              v-if="!loading && categoryData.length"
              type="bar"
              height="240"
              :options="categoryOptions"
              :series="categorySeries"
            />
            <div v-else-if="loading" class="compact-chart-skeleton" aria-hidden="true" />
            <div v-else class="compact-empty flex h-40 flex-col items-center justify-center">
              <LucideIcon :icon="ChartColumn" class="mb-1.5 h-7 w-7 text-gray-300 dark:text-gray-600" />
              No category data available
            </div>
          </div>
        </section>

        <!-- Requests by Status Donut Chart -->
        <section class="custodian-panel compact-panel flex flex-col justify-between transition-all">
          <div class="compact-panel__header">
            <div class="min-w-0">
              <h2 class="compact-panel__title">Requests by Status</h2>
              <p class="compact-panel__subtitle">Overall distribution of item requests</p>
            </div>
            <span class="compact-chip compact-chip--sky shrink-0">
              <span class="h-1.5 w-1.5 rounded-md bg-sky-500" />
              Workflow Distribution
            </span>
          </div>

          <div class="compact-panel__body">
            <apexchart
              v-if="!loading && requestStatusData.length"
              type="donut"
              height="240"
              :options="statusOptions"
              :series="statusSeries"
            />
            <div v-else-if="loading" class="compact-chart-skeleton" aria-hidden="true" />
            <div v-else class="compact-empty flex h-40 flex-col items-center justify-center">
              <LucideIcon :icon="ChartPie" class="mb-1.5 h-7 w-7 text-gray-300 dark:text-gray-600" />
              No request status data available
            </div>
          </div>
        </section>

        <!-- Lifespan Mix Donut Chart. Derived from the metrics the endpoint
             already returns (healthy = total − approaching − expired), so no
             backend change. The item-level view stays on Reports. -->
        <section class="custodian-panel compact-panel flex flex-col justify-between transition-all">
          <div class="compact-panel__header">
            <div class="min-w-0">
              <h2 class="compact-panel__title">Lifespan Mix</h2>
              <p class="compact-panel__subtitle">Healthy, approaching and expired units</p>
            </div>
            <span class="compact-chip compact-chip--emerald shrink-0">
              <span class="h-1.5 w-1.5 rounded-md bg-emerald-500" />
              Lifecycle
            </span>
          </div>

          <div class="compact-panel__body">
            <apexchart
              v-if="!loading && lifecycleTotal > 0"
              type="donut"
              height="240"
              :options="lifecycleOptions"
              :series="lifecycleSeries"
            />
            <div v-else-if="loading" class="compact-chart-skeleton" aria-hidden="true" />
            <div v-else class="compact-empty flex h-40 flex-col items-center justify-center">
              <LucideIcon :icon="ChartPie" class="mb-1.5 h-7 w-7 text-gray-300 dark:text-gray-600" />
              No lifespan data available
            </div>
          </div>
        </section>
      </div>

      <!-- Recent Activity Section -->
      <section class="custodian-panel compact-panel transition-all">
        <div class="compact-panel__header flex-wrap">
          <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
              <LucideIcon :icon="Zap" class="h-4 w-4" />
            </div>
            <div>
              <h2 class="compact-panel__title">Recent Stock Movements</h2>
              <p class="compact-panel__subtitle">Latest entries and transactions in Dian-ay stockroom</p>
            </div>
          </div>

          <RouterLink
            to="/transactions"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline dark:text-emerald-400"
          >
            View all transactions
            <LucideIcon :icon="ChevronRight" class="h-3.5 w-3.5" />
          </RouterLink>
        </div>

        <div v-if="loading" class="mt-3 space-y-2" aria-hidden="true">
          <div v-for="row in 5" :key="row" class="flex items-center gap-3 border-b border-gray-100 py-2 dark:border-white/5">
            <div class="h-8 w-8 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="flex-1 space-y-2">
              <div class="h-3.5 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="h-3 w-3/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
            <div class="h-5 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>

        <div v-else-if="activity.length" class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
          <div
            v-for="(entry, index) in activity"
            :key="index"
            class="group flex min-w-0 items-center justify-between gap-4 py-2 px-2 rounded-md transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.02]"
          >
            <div class="flex items-center gap-3 min-w-0">
              <!-- Movement Direction Icon -->
              <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md font-bold text-xs"
                :class="entry.quantity >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400'"
              >
                <LucideIcon v-if="entry.quantity >= 0" :icon="ArrowDownToLine" class="h-4 w-4" />
                <LucideIcon v-else :icon="ArrowUpFromLine" class="h-4 w-4" />
              </div>

              <div class="min-w-0 flex-1 overflow-hidden">
                <div class="flex items-center gap-2">
                  <p class="truncate font-semibold text-gray-800 dark:text-white/90 text-sm">{{ entry.item }}</p>
                  <span
                    class="rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                    :class="activityBadgeClass(entry.type)"
                  >
                    {{ entry.type }}
                  </span>
                </div>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  <span>{{ entry.remarks || 'Stock transaction' }}</span>
                  <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
                  <span class="font-medium text-gray-600 dark:text-gray-300">{{ entry.user }}</span>
                </p>
              </div>
            </div>

            <!-- Quantity delta badge -->
            <div class="shrink-0 text-right">
              <span
                class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-bold"
                :class="entry.quantity >= 0 ? 'bg-emerald-100/70 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-rose-100/70 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'"
              >
                {{ entry.quantity > 0 ? `+${entry.quantity}` : entry.quantity }}
              </span>
            </div>
          </div>
        </div>

        <div v-else class="py-8 text-center text-xs text-gray-500 dark:text-gray-400">
          <LucideIcon :icon="Clock" class="mx-auto mb-1.5 h-7 w-7 text-gray-300 dark:text-gray-600" />
          No recent movements recorded.
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowDownToLine, ArrowUpFromLine, Boxes, CalendarClock, CalendarX2, ChartColumn, ChartPie, ChevronRight, ClipboardList, Clock, Package, PackageCheck, TriangleAlert, Zap } from 'lucide';
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
const categoryData = ref([]);
const requestStatusData = ref([]);
const activity = ref([]);

const isDark = computed(() => theme.theme === 'dark');
const foreColor = computed(() => (isDark.value ? '#98a2b3' : '#475467'));

const categorySeries = computed(() => [
  { name: 'Units', data: categoryData.value.slice(0, 8).map((row) => row.value) },
]);

const categoryOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    foreColor: foreColor.value,
    fontFamily: 'Outfit, sans-serif',
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 6,
      barHeight: '55%',
      distributed: true,
    },
  },
  colors: ['#059669', '#0d9488', '#0284c7', '#6366f1', '#8b5cf6', '#d97706', '#e11d48', '#14b8a6'],
  // Value labels removed entirely: bar lengths already encode the quantities
  // and the hover tooltip states the exact figure. No in-chart text left to
  // misrender.
  dataLabels: {
    enabled: false,
  },
  xaxis: {
    categories: categoryData.value.slice(0, 8).map((row) => row.label),
    labels: { style: { fontSize: '11px', fontWeight: 500 } },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', fontWeight: 500 } },
  },
  grid: {
    borderColor: isDark.value ? '#1f2937' : '#f3f4f6',
    strokeDashArray: 4,
  },
  legend: { show: false },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
    y: { formatter: (val) => `${val} units in stock` },
  },
  noData: { text: 'No category data available' },
}));

const statusSeries = computed(() => requestStatusData.value.map((row) => row.value));

const statusOptions = computed(() => ({
  chart: {
    type: 'donut',
    foreColor: foreColor.value,
    fontFamily: 'Outfit, sans-serif',
  },
  labels: requestStatusData.value.map((row) => row.label),
  legend: {
    position: 'bottom',
    horizontalAlign: 'center',
    fontSize: '11px',
    markers: { radius: 4 },
    itemMargin: { horizontal: 6, vertical: 2 },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '66%',
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
            offsetY: -2,
            fontSize: '12px',
            fontWeight: 700,
            color: isDark.value ? '#38bdf8' : '#0284c7',
          },
          total: {
            show: true,
            label: 'Total Requests',
            color: '#98a2b3',
            fontSize: '12px',
            fontWeight: 600,
            formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
          },
        },
      },
    },
  },
  colors: ['#059669', '#f59e0b', '#0284c7', '#e11d48', '#8b5cf6', '#10b981'],
  stroke: { colors: [isDark.value ? '#111827' : '#ffffff'], width: 2 },
  // Segment percentage labels ("92.9%") are on by default and add nothing next
  // to the center total, legend and tooltip — and render in unstyled dark text.
  dataLabels: { enabled: false },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
  },
  noData: { text: 'No request data available' },
}));

// Lifespan mix, derived from the metrics payload: healthy units are the total
// minus the two dated states. No backend change; the item-level lifespan view
// stays on Reports.
const lifecycleCounts = computed(() => {
  const total = Number(metrics.value.inventory ?? 0);
  const approaching = Number(metrics.value.approachingLifespan ?? 0);
  const expired = Number(metrics.value.expiredLifespan ?? 0);
  return {
    healthy: Math.max(0, total - approaching - expired),
    approaching: Math.max(0, approaching),
    expired: Math.max(0, expired),
  };
});

const lifecycleTotal = computed(
  () => lifecycleCounts.value.healthy + lifecycleCounts.value.approaching + lifecycleCounts.value.expired,
);

const lifecycleSeries = computed(() => [
  lifecycleCounts.value.healthy,
  lifecycleCounts.value.approaching,
  lifecycleCounts.value.expired,
]);

const lifecycleOptions = computed(() => ({
  chart: {
    type: 'donut',
    foreColor: foreColor.value,
    fontFamily: 'Outfit, sans-serif',
  },
  labels: ['Healthy', 'Approaching', 'Expired'],
  legend: {
    position: 'bottom',
    horizontalAlign: 'center',
    fontSize: '11px',
    markers: { radius: 4 },
    itemMargin: { horizontal: 6, vertical: 2 },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '66%',
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
            offsetY: -2,
            fontSize: '12px',
            fontWeight: 700,
            color: isDark.value ? '#38bdf8' : '#0284c7',
          },
          total: {
            show: true,
            label: 'Total Units',
            color: '#98a2b3',
            fontSize: '12px',
            fontWeight: 600,
            formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
          },
        },
      },
    },
  },
  colors: ['#059669', '#f97316', '#e11d48'],
  stroke: { colors: [isDark.value ? '#111827' : '#ffffff'], width: 2 },
  // Same as the requests donut: default slice percentages off, center total on.
  dataLabels: { enabled: false },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
    y: { formatter: (val) => `${val} units` },
  },
  noData: { text: 'No lifespan data available' },
}));

function activityBadgeClass(type) {
  const t = String(type ?? '').toLowerCase();
  if (t.includes('stock in') || t.includes('acquired')) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';
  }
  if (t.includes('assign') || t.includes('issue')) {
    return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300';
  }
  if (t.includes('return')) {
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
  }
  if (t.includes('transfer')) {
    return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-300';
  }
  return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'custodian-dashboard',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/custodian/dashboard');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categoryData.value = data.categoryData ?? [];
      requestStatusData.value = data.requestStatusData ?? [];
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
