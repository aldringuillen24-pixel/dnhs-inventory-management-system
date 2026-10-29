<template>
  <div class="compact-page gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">My Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview of your assigned items, pending requests, and custody activity.</p>
      </div>
    </div>

    <div v-if="error" class="compact-alert">
      <span>{{ error }}</span>
      <button type="button" class="compact-btn compact-btn--sm compact-btn--outline" @click="load">Retry</button>
    </div>

    <div v-else class="flex flex-col gap-6">
      <!-- 4 Interactive Metric Cards with Lucide Icons -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <!-- Assigned Items -->
        <MetricCard
          compact dense micro
          title="Assigned Items"
          :value="format(metrics.assignedItems)"
          subtitle="Units in your care"
          :loading="loading"
          icon-class="bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400"
          class="cursor-pointer transition-all hover:border-indigo-300 dark:hover:border-indigo-700"
          @click="router.push('/end-user/assigned')"
        >
          <template #icon>
            <LucideIcon :icon="PackageCheck" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- Pending Requests -->
        <MetricCard
          compact dense micro
          title="Pending Requests"
          :value="format(metrics.pendingRequests)"
          subtitle="Awaiting action"
          :loading="loading"
          icon-class="bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"
          class="cursor-pointer transition-all hover:border-amber-300 dark:hover:border-amber-700"
          @click="router.push('/end-user/requests')"
        >
          <template #icon>
            <LucideIcon :icon="Clock" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- Pending Returns -->
        <MetricCard
          compact dense micro
          title="Pending Returns"
          :value="format(metrics.pendingReturns)"
          subtitle="Return approvals"
          :loading="loading"
          icon-class="bg-purple-50 text-purple-600 dark:bg-purple-500/15 dark:text-purple-400"
          class="cursor-pointer transition-all hover:border-purple-300 dark:hover:border-purple-700"
          @click="router.push('/end-user/requests')"
        >
          <template #icon>
            <LucideIcon :icon="Undo2" class="h-4 w-4" />
          </template>
        </MetricCard>

        <!-- Due Soon / Good Standing -->
        <MetricCard
          compact dense micro
          title="Due Soon"
          :value="format(metrics.dueSoon)"
          :subtitle="Number(metrics.dueSoon) > 0 ? 'Return within 30 days' : 'All items in good standing'"
          :loading="loading"
          :icon-class="Number(metrics.dueSoon) > 0 ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400'"
          class="cursor-pointer transition-all hover:border-emerald-300 dark:hover:border-emerald-700"
          @click="router.push('/end-user/assigned')"
        >
          <template #icon>
            <LucideIcon :icon="Number(metrics.dueSoon) > 0 ? AlertCircle : ShieldCheck" class="h-4 w-4" />
          </template>
        </MetricCard>
      </div>

      <!-- Charts Row -->
      <div class="compact-grid-2 gap-5">
        <section class="compact-panel">
          <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Assigned by Category</h2>
            <button
              type="button"
              class="text-[11px] font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
              @click="router.push('/end-user/assigned')"
            >
              View items →
            </button>
          </div>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <div v-else-if="!categoryData.length" class="flex flex-col items-center justify-center py-10 text-center">
            <LucideIcon :icon="Layers" class="mb-2 h-7 w-7 text-gray-300 dark:text-gray-600" />
            <p class="text-xs text-gray-400">No assigned categories found.</p>
          </div>
          <apexchart v-else type="bar" height="240" :options="categoryOptions" :series="categorySeries" />
        </section>

        <section class="compact-panel">
          <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Assigned by Status</h2>
            <button
              type="button"
              class="text-[11px] font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
              @click="router.push('/end-user/assigned')"
            >
              View items →
            </button>
          </div>
          <div v-if="loading" class="compact-panel__body compact-chart-skeleton" aria-hidden="true" />
          <div v-else-if="!statusData.length" class="flex flex-col items-center justify-center py-10 text-center">
            <LucideIcon :icon="CheckCircle2" class="mb-2 h-7 w-7 text-gray-300 dark:text-gray-600" />
            <p class="text-xs text-gray-400">No assigned items to display.</p>
          </div>
          <apexchart v-else type="donut" height="240" :options="statusOptions" :series="statusSeries" />
        </section>
      </div>

      <!-- Pending Breakdown & Activity Row -->
      <div class="compact-grid-2 gap-5">
        <!-- Left: Pending Breakdown + Needs Attention -->
        <section class="compact-panel flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Pending Breakdown</h2>
              <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                Live queue
              </span>
            </div>

            <!-- Loading Skeleton -->
            <div v-if="loading" class="compact-panel__body space-y-2.5 pt-3" aria-hidden="true">
              <div v-for="row in 4" :key="row" class="flex items-center justify-between">
                <div class="compact-skeleton h-3.5 w-1/3" />
                <div class="compact-skeleton h-4 w-10 rounded-full" />
              </div>
            </div>

            <!-- Pending Breakdown Items -->
            <ul v-else class="compact-panel__body space-y-2 pt-3">
              <li
                v-for="row in pendingRequestData"
                :key="row.label"
                class="group flex cursor-pointer items-center justify-between rounded-lg border border-transparent p-2 text-xs transition-colors hover:border-gray-200 hover:bg-gray-50/80 dark:hover:border-gray-700/80 dark:hover:bg-gray-800/50"
                @click="onPendingRowClick(row.label)"
              >
                <div class="flex items-center gap-2">
                  <div class="flex h-6 w-6 items-center justify-center rounded-md bg-gray-100 text-gray-500 transition-colors group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-gray-800 dark:text-gray-400 dark:group-hover:bg-brand-500/20 dark:group-hover:text-brand-300">
                    <LucideIcon :icon="getPendingIcon(row.label)" class="h-3.5 w-3.5" />
                  </div>
                  <span class="font-medium text-gray-700 dark:text-gray-300">{{ row.label }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span
                    class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="Number(row.value) > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                  >
                    {{ row.value }}
                  </span>
                  <LucideIcon :icon="ChevronRight" class="h-3 w-3 text-gray-400 opacity-0 transition-opacity group-hover:opacity-100" />
                </div>
              </li>
            </ul>
          </div>

          <!-- Needs Attention Section -->
          <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Needs Attention</h2>
            <div v-if="!conditionData.length" class="flex items-center gap-2 rounded-lg border border-emerald-200/60 bg-emerald-50/60 p-2.5 text-xs text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/20 dark:text-emerald-300">
              <LucideIcon :icon="ShieldCheck" class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
              <span>All assigned units are in good working condition.</span>
            </div>
            <ul v-else class="space-y-1.5">
              <li
                v-for="row in conditionData"
                :key="row.label"
                class="flex cursor-pointer items-center justify-between rounded-lg border border-warning-200/60 bg-warning-50/40 p-2 text-xs transition-colors hover:bg-warning-50 dark:border-warning-800/40 dark:bg-warning-950/20 dark:hover:bg-warning-950/40"
                @click="router.push('/end-user/assigned')"
              >
                <div class="flex items-center gap-2">
                  <LucideIcon :icon="AlertTriangle" class="h-3.5 w-3.5 text-warning-600 dark:text-warning-400" />
                  <span class="font-medium text-gray-700 dark:text-gray-300">{{ row.label }}</span>
                </div>
                <span class="font-bold text-warning-700 dark:text-warning-400">{{ row.value }} unit{{ row.value === 1 ? '' : 's' }}</span>
              </li>
            </ul>
          </div>
        </section>

        <!-- Right: Recent Activity -->
        <section class="compact-panel flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
              <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Recent Activity</h2>
              <button
                type="button"
                class="text-[11px] font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                @click="router.push('/end-user/assigned')"
              >
                View all →
              </button>
            </div>

            <!-- Activity Skeleton -->
            <ul v-if="loading" class="compact-panel__body space-y-3 pt-3" aria-hidden="true">
              <li v-for="row in 4" :key="row" class="flex items-center justify-between gap-3 py-1">
                <div class="flex items-center gap-2.5">
                  <div class="compact-skeleton h-7 w-7 rounded-lg" />
                  <div class="space-y-1.5">
                    <div class="compact-skeleton h-3.5 w-28" />
                    <div class="compact-skeleton h-2.5 w-16" />
                  </div>
                </div>
                <div class="compact-skeleton h-5 w-20 rounded-full" />
              </li>
            </ul>

            <!-- Activity Feed Items -->
            <ul v-else-if="activity.length" class="compact-panel__body divide-y divide-gray-100 pt-1 dark:divide-white/5">
              <li
                v-for="entry in activity"
                :key="entry.id"
                class="group flex cursor-pointer items-center justify-between gap-3 py-2.5 transition-colors hover:bg-gray-50/50 dark:hover:bg-white/[0.02]"
                @click="onActivityClick(entry)"
              >
                <div class="flex min-w-0 items-center gap-2.5">
                  <div
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                    :class="getActivityIconBg(entry.status)"
                  >
                    <LucideIcon :icon="getActivityIcon(entry.status)" class="h-3.5 w-3.5" :class="getActivityIconColor(entry.status)" />
                  </div>
                  <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-gray-800 group-hover:text-brand-600 dark:text-white/90 dark:group-hover:text-brand-400">
                      {{ entry.item?.item_name ?? 'Request update' }}
                    </p>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500">
                      {{ formatTimeAgo(entry.updated_at) }} · {{ formatDate(entry.updated_at) }}
                    </p>
                  </div>
                </div>
                <StatusBadge :status="entry.status" compact />
              </li>
            </ul>
            <div v-else class="flex flex-col items-center justify-center py-10 text-center">
              <LucideIcon :icon="Activity" class="mb-2 h-7 w-7 text-gray-300 dark:text-gray-600" />
              <p class="text-xs text-gray-400">No recent activity recorded.</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
  Activity,
  AlertCircle,
  AlertTriangle,
  ArrowLeftRight,
  CheckCircle2,
  ChevronRight,
  Clock,
  Layers,
  Package,
  PackageCheck,
  ShieldCheck,
  ShoppingCart,
  Undo2,
} from 'lucide';
import api from '../../lib/axios';
import apexchart from 'vue3-apexcharts';
import { loadCachedPage } from '../../lib/pageCache';
import { useThemeStore } from '../../stores/theme';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';

const router = useRouter();
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
  dataLabels: { enabled: false },
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

function formatTimeAgo(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const seconds = Math.floor((new Date() - date) / 1000);
  if (seconds < 60) return 'Just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return formatDate(value);
}

function getPendingIcon(label) {
  const l = String(label ?? '').toLowerCase();
  if (l.includes('transfer')) return ArrowLeftRight;
  if (l.includes('return')) return Undo2;
  if (l.includes('procurement')) return ShoppingCart;
  return Package;
}

function onPendingRowClick(label) {
  const l = String(label ?? '').toLowerCase();
  if (l.includes('return')) {
    router.push('/end-user/assigned');
  } else {
    router.push('/end-user/requests');
  }
}

function getActivityIcon(status) {
  const s = String(status ?? '').toLowerCase();
  if (s.includes('return') || s === 'returned') return Undo2;
  if (s.includes('approved') || s.includes('accepted') || s === 'completed') return CheckCircle2;
  if (s.includes('transfer')) return ArrowLeftRight;
  return Clock;
}

function getActivityIconBg(status) {
  const s = String(status ?? '').toLowerCase();
  if (s.includes('return') || s === 'returned') return 'bg-purple-50 dark:bg-purple-500/15';
  if (s.includes('approved') || s.includes('accepted') || s === 'completed') return 'bg-emerald-50 dark:bg-emerald-500/15';
  if (s.includes('transfer')) return 'bg-indigo-50 dark:bg-indigo-500/15';
  return 'bg-amber-50 dark:bg-amber-500/15';
}

function getActivityIconColor(status) {
  const s = String(status ?? '').toLowerCase();
  if (s.includes('return') || s === 'returned') return 'text-purple-600 dark:text-purple-400';
  if (s.includes('approved') || s.includes('accepted') || s === 'completed') return 'text-emerald-600 dark:text-emerald-400';
  if (s.includes('transfer')) return 'text-indigo-600 dark:text-indigo-400';
  return 'text-amber-600 dark:text-amber-400';
}

function onActivityClick(entry) {
  const s = String(entry.status ?? '').toLowerCase();
  if (s.includes('return')) {
    router.push('/end-user/assigned');
  } else {
    router.push('/end-user/requests');
  }
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
      categoryData.value = (data.categoryData ?? []).filter(
        (row) => String(row.label ?? '').trim().toLowerCase() !== 'uncategorized',
      );
      statusData.value = (data.statusData ?? []).filter(
        (row) => String(row.label ?? '').trim().toLowerCase() !== 'unknown',
      );
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
