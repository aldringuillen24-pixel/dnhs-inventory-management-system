<template>
  <div class="space-y-6">
    <!-- Header with Quick Date Presets & Filter Bar -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Reports & Demand Forecast</h1>
          <span class="rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            Analytics
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stock movements, condition breakdown, low-inventory alerts, and machine learning demand predictions.</p>
      </div>

      <!-- Date Presets + Form -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Quick Presets -->
        <div class="inline-flex rounded-md bg-gray-100 p-1 dark:bg-white/5">
          <button
            type="button"
            class="rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors"
            :class="preset === '7d' ? 'bg-white text-emerald-900 shadow-sm dark:bg-gray-800 dark:text-emerald-300' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setPreset('7d')"
          >
            Last 7D
          </button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors"
            :class="preset === '30d' ? 'bg-white text-emerald-900 shadow-sm dark:bg-gray-800 dark:text-emerald-300' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setPreset('30d')"
          >
            Last 30D
          </button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1.5 text-xs font-semibold transition-colors"
            :class="preset === 'year' ? 'bg-white text-emerald-900 shadow-sm dark:bg-gray-800 dark:text-emerald-300' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
            @click="setPreset('year')"
          >
            This Year
          </button>
        </div>

        <!-- Custom Date Range Form -->
        <form class="grid w-full grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] gap-2 sm:flex sm:w-auto sm:items-center" @submit.prevent="applyCustomDates">
          <input
            v-model="dateFrom"
            type="date"
            aria-label="From date"
            class="w-full min-w-0 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:w-auto sm:px-3"
          />
          <span class="text-xs text-gray-400">to</span>
          <input
            v-model="dateTo"
            type="date"
            aria-label="To date"
            class="w-full min-w-0 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:w-auto sm:px-3"
          />
          <div class="col-span-3 flex gap-2 sm:contents">
            <button
              type="submit"
              class="rounded-md bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
              Apply
            </button>
            <button
              v-if="dateFrom || dateTo"
              type="button"
              class="rounded-md px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
              @click="clearDateRange"
            >
              Reset
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
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

    <div v-else class="space-y-6">
      <!-- Top 4 Analytics Metrics -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <!-- 1. Total Units -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Total Units"
          :value="format(metrics.totalUnits)"
          subtitle="Non-disposed stockroom units"
          accent-class="border-l-4 border-l-emerald-500"
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
          title="Available Stock"
          :value="format(metrics.availableUnits)"
          subtitle="Ready for immediate issuance"
          accent-class="border-l-4 border-l-teal-500"
          icon-class="bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400"
        >
          <template #icon>
            <LucideIcon :icon="PackageCheck" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 3. Assigned Units -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Assigned Units"
          :value="format(metrics.assignedUnits)"
          subtitle="In end-user care"
          accent-class="border-l-4 border-l-indigo-500"
          icon-class="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400"
        >
          <template #icon>
            <LucideIcon :icon="UsersRound" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- 4. Estimated Valuation -->
        <MetricCard
          compact
          dense
          micro
          :loading="loading"
          title="Stockroom Value"
          :value="`₱${money(metrics.totalValue)}`"
          subtitle="Total inventory capital"
          accent-class="border-l-4 border-l-amber-500"
          icon-class="bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
        >
          <template #icon>
            <LucideIcon :icon="BadgeDollarSign" class="h-4 w-4" />
          </template>
        </MetricCard>
      </div>

      <!-- Charts Section -->
      <div class="compact-grid-2">
        <!-- Units by Category Bar Chart -->
        <section class="custodian-panel compact-panel flex flex-col justify-between">
          <div class="compact-panel__header">
            <div class="min-w-0">
              <h2 class="compact-panel__title">Volume by Category</h2>
              <p class="compact-panel__subtitle">Total units logged per equipment category</p>
            </div>
            <span class="compact-chip compact-chip--emerald shrink-0">
              <span class="h-1.5 w-1.5 rounded-md bg-emerald-500" />
              Units
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
            <div v-else class="compact-empty flex h-40 items-center justify-center">
              No category data recorded.
            </div>
          </div>
        </section>

        <!-- Units by Status Donut Chart -->
        <section class="custodian-panel compact-panel flex flex-col justify-between">
          <div class="compact-panel__header">
            <div class="min-w-0">
              <h2 class="compact-panel__title">Status Breakdown</h2>
              <p class="compact-panel__subtitle">Proportion of units by physical and assignment state</p>
            </div>
            <span class="compact-chip compact-chip--sky shrink-0">
              <span class="h-1.5 w-1.5 rounded-md bg-sky-500" />
              Condition
            </span>
          </div>

          <div class="compact-panel__body">
            <apexchart
              v-if="!loading && statusData.length"
              type="donut"
              height="240"
              :options="statusOptions"
              :series="statusSeries"
            />
            <div v-else-if="loading" class="compact-chart-skeleton" aria-hidden="true" />
            <div v-else class="compact-empty flex h-40 items-center justify-center">
              No status data recorded.
            </div>
          </div>
        </section>
      </div>

      <!-- Alert Lists: Low Stock & Needs Attention -->
      <div class="grid gap-6 xl:grid-cols-2">
        <!-- Low Stock Alert Card -->
        <section class="custodian-panel rounded-md border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-white/5">
            <div class="flex items-center gap-2">
              <div class="flex h-7 w-7 items-center justify-center rounded-md bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h2 class="text-base font-bold text-gray-800 dark:text-white/95">Low Stock Groups (≤ 3 Units)</h2>
            </div>
            <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
              {{ lowStock.length }} items
            </span>
          </div>

          <div v-if="loading" class="mt-3 max-h-72 space-y-3 overflow-y-auto" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="flex items-center justify-between border-b border-gray-100 py-2.5 dark:border-white/5">
              <div class="flex-1 space-y-2">
                <div class="h-4 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                <div class="h-3 w-1/4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              </div>
              <div class="h-6 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
          </div>
          <div v-else class="mt-3 max-h-72 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
            <div v-if="!lowStock.length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
              <svg class="mx-auto h-8 w-8 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              All item groups have adequate stock.
            </div>

            <div
              v-for="row in lowStock"
              :key="row.item_id"
              class="flex items-center justify-between py-2.5 px-1 hover:bg-gray-50/60 dark:hover:bg-white/[0.02] rounded-md transition-colors"
            >
              <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900 dark:text-white text-sm">{{ row.item_name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ row.category || 'General' }}</p>
              </div>
              <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                {{ format(row.quantity) }} {{ row.unit || 'units' }}
              </span>
            </div>
          </div>
        </section>

        <!-- Needs Attention Card -->
        <section class="custodian-panel rounded-md border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-white/5">
            <div class="flex items-center gap-2">
              <div class="flex h-7 w-7 items-center justify-center rounded-md bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h2 class="text-base font-bold text-gray-800 dark:text-white/95">Items Needing Attention</h2>
            </div>
            <span class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950/40 dark:text-rose-300">
              {{ attention.length }} items
            </span>
          </div>

          <div v-if="loading" class="mt-3 max-h-72 space-y-3 overflow-y-auto" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="flex items-center justify-between border-b border-gray-100 py-2.5 dark:border-white/5">
              <div class="flex-1 space-y-2">
                <div class="h-4 w-2/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                <div class="h-3 w-1/4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              </div>
              <div class="h-6 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
          </div>
          <div v-else class="mt-3 max-h-72 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
            <div v-if="!attention.length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
              <svg class="mx-auto h-8 w-8 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              No items currently flagged for maintenance or inspection.
            </div>

            <div
              v-for="row in attention"
              :key="row.item_id"
              class="flex items-center justify-between py-2.5 px-1 hover:bg-gray-50/60 dark:hover:bg-white/[0.02] rounded-md transition-colors"
            >
              <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900 dark:text-white text-sm">{{ row.item_name }}</p>
                <div class="mt-0.5">
                  <StatusBadge :status="row.status" />
                </div>
              </div>
              <span class="font-bold text-gray-900 dark:text-white text-sm">
                {{ format(row.quantity) }} units
              </span>
            </div>
          </div>
        </section>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { BadgeDollarSign, Boxes, PackageCheck, UsersRound } from 'lucide';
import api from '../../lib/axios';
import apexchart from 'vue3-apexcharts';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useThemeStore } from '../../stores/theme';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';

const theme = useThemeStore();
const loading = ref(true);
const error = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const preset = ref('');
const metrics = ref({});
const categoryData = ref([]);
const statusData = ref([]);
const lowStock = ref([]);
const attention = ref([]);

const isDark = computed(() => theme.theme === 'dark');
const foreColor = computed(() => (isDark.value ? '#98a2b3' : '#475467'));

const categorySeries = computed(() => [
  { name: 'Units', data: categoryData.value.slice(0, 10).map((row) => row.quantity) },
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
  dataLabels: {
    enabled: true,
    textAnchor: 'start',
    style: { colors: ['#ffffff'], fontSize: '10px', fontWeight: 600 },
    formatter: (val) => `${val}`,
    offsetX: 6,
  },
  xaxis: {
    categories: categoryData.value.slice(0, 10).map((row) => row.label),
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
    y: { formatter: (val) => `${val} units logged` },
  },
  noData: { text: 'No category data recorded' },
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
    horizontalAlign: 'center',
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
            color: isDark.value ? '#38bdf8' : '#0284c7',
          },
          total: {
            show: true,
            label: 'Total Units',
            color: '#98a2b3',
            fontSize: '10px',
            fontWeight: 600,
            formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0),
          },
        },
      },
    },
  },
  colors: ['#059669', '#0d9488', '#6366f1', '#f59e0b', '#8b5cf6', '#e11d48'],
  stroke: { colors: [isDark.value ? '#111827' : '#ffffff'], width: 2 },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
  },
  noData: { text: 'No status data recorded' },
}));

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setPreset(p) {
  preset.value = p;
  const now = new Date();
  dateTo.value = now.toISOString().slice(0, 10);

  if (p === '7d') {
    const d = new Date();
    d.setDate(d.getDate() - 7);
    dateFrom.value = d.toISOString().slice(0, 10);
  } else if (p === '30d') {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    dateFrom.value = d.toISOString().slice(0, 10);
  } else if (p === 'year') {
    const yearStart = new Date(now.getFullYear(), 0, 1);
    dateFrom.value = yearStart.toISOString().slice(0, 10);
  }
  load();
}

function applyCustomDates() {
  preset.value = '';
  load();
}

function clearDateRange() {
  dateFrom.value = '';
  dateTo.value = '';
  preset.value = '';
  load();
}

async function load(options = {}) {
  const params = { date_from: dateFrom.value || '', date_to: dateTo.value || '' };
  await loadCachedPage({
    page: 'custodian-reports',
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/custodian/reports', {
        params: { date_from: params.date_from || undefined, date_to: params.date_to || undefined },
      });
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categoryData.value = data.categoryData ?? [];
      statusData.value = data.statusData ?? [];
      lowStock.value = data.lowStockData ?? [];
      attention.value = data.attentionData ?? [];
      if (data.reportFilters) {
        dateFrom.value = data.reportFilters.date_from ?? dateFrom.value;
        dateTo.value = data.reportFilters.date_to ?? dateTo.value;
      }
    },
    isCurrent: () => (dateFrom.value || '') === params.date_from && (dateTo.value || '') === params.date_to,
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
