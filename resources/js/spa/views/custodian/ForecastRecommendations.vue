<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            {{ isDemoForecast ? 'Sample/Demo Demand Forecast' : 'Demand Forecast Recommendations' }}
          </h1>
          <span
            v-if="isDemoForecast"
            class="rounded-md bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
          >
            SAMPLE DATA ONLY
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ isDemoForecast ? 'Sample-trained Python forecast, not live inventory. No available stock or procurement recommendation is included.' : 'Next-month supply demand based on approved stock-out history.' }}
        </p>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Forecast period: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ forecastPeriod }}</strong>
          <span class="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
          Generated: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ generatedAt }}</strong>
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <RouterLink
          to="/reports"
          class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        >
          <LucideIcon :icon="ArrowLeft" class="h-4 w-4" />
          Reports
        </RouterLink>
        <button
          v-if="!isDemoForecast"
          type="button"
          :disabled="reloading"
          class="inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
          @click="reloadForecast"
        >
          <LucideIcon :icon="RefreshCw" class="h-4 w-4" :class="{ 'animate-spin': reloading }" />
          {{ reloading ? 'Reloading…' : 'Reload latest result' }}
        </button>
      </div>
    </div>

    <!-- Source toggle -->
    <div class="flex items-center gap-2">
      <button
        type="button"
        :disabled="source === 'live'"
        class="rounded-md px-3 py-1.5 text-xs font-semibold"
        :class="source === 'live' ? 'bg-emerald-700 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'"
        @click="switchSource('live')"
      >
        Live forecast
      </button>
      <button
        type="button"
        :disabled="source === 'demo'"
        class="rounded-md px-3 py-1.5 text-xs font-semibold"
        :class="source === 'demo' ? 'bg-sky-700 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'"
        @click="switchSource('demo')"
      >
        Sample / Demo
      </button>
    </div>

    <!-- Loading -->
    <InventoryTableSkeleton v-if="loading" />

    <!-- Error -->
    <div
      v-else-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <span>{{ error }}</span>
      <button
        type="button"
        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <template v-else>
      <!-- Forecast status error -->
      <div
        v-if="['error', 'stale', 'failed'].includes(forecastStatus)"
        class="rounded-md border border-rose-200 bg-rose-50 p-5 dark:border-rose-900/50 dark:bg-rose-950/20"
      >
        <h2 class="text-base font-semibold text-rose-800 dark:text-rose-200">{{ statusTitle }}</h2>
        <p class="mt-1 text-sm text-rose-700 dark:text-rose-300">{{ statusMessage }}</p>
        <button
          v-if="!isDemoForecast"
          type="button"
          :disabled="reloading"
          class="mt-3 inline-flex items-center gap-2 rounded-md bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
          @click="reloadForecast"
        >
          <LucideIcon :icon="RefreshCw" class="h-4 w-4" :class="{ 'animate-spin': reloading }" />
          {{ reloading ? 'Reloading…' : 'Reload latest result' }}
        </button>
      </div>

      <!-- Empty -->
      <div
        v-else-if="!rows.length"
        class="rounded-md border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900"
      >
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ isDemoForecast ? 'No Sample/Demo forecast available' : 'No demand forecast available' }}</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ isDemoForecast ? 'No demo result has been generated yet.' : 'No trained production ML forecast is available. Model training runs separately from chat and reports.' }}</p>
        <button
          v-if="!isDemoForecast"
          type="button"
          :disabled="reloading"
          class="mt-4 inline-flex items-center gap-2 rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
          @click="reloadForecast"
        >
          <LucideIcon :icon="RefreshCw" class="h-4 w-4" :class="{ 'animate-spin': reloading }" />
          {{ reloading ? 'Reloading…' : 'Reload latest result' }}
        </button>
      </div>

      <!-- Results panel -->
      <div v-else class="custodian-panel overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div
          v-if="availabilityWarning"
          class="border-b border-amber-200/60 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 dark:border-amber-800/40 dark:bg-amber-950/30 dark:text-amber-200 sm:px-5"
        >
          Inventory availability appears incomplete or unrecorded. Verify current stock levels before using these procurement recommendations.
        </div>

        <!-- Filters (live only) -->
        <div v-if="!isDemoForecast" class="grid gap-3 border-b border-gray-100 p-4 dark:border-white/5 sm:grid-cols-2 sm:px-5 xl:grid-cols-6">
          <div class="sm:col-span-2">
            <label for="forecast-search" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Search item</label>
            <input
              id="forecast-search"
              v-model="filters.search"
              type="search"
              placeholder="Item name"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            />
          </div>
          <div>
            <label for="forecast-category" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Category</label>
            <select
              id="forecast-category"
              v-model="filters.category"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
              @change="onFilterChange"
            >
              <option value="">All categories</option>
              <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
            </select>
          </div>
          <div>
            <label for="forecast-priority" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Priority</label>
            <select
              id="forecast-priority"
              v-model="filters.priority"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
              @change="onFilterChange"
            >
              <option value="">All priorities</option>
              <option v-for="priority in ['Urgent', 'High', 'Medium', 'Normal']" :key="priority" :value="priority">{{ priority }}</option>
            </select>
          </div>
          <div>
            <label for="forecast-confidence" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Confidence</label>
            <select
              id="forecast-confidence"
              v-model="filters.confidence"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
              @change="onFilterChange"
            >
              <option value="">All confidence</option>
              <option v-for="confidence in ['High', 'Medium', 'Low']" :key="confidence" :value="confidence">{{ confidence }}</option>
            </select>
          </div>
          <div>
            <label for="forecast-sort" class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Sort by</label>
            <select
              id="forecast-sort"
              v-model="filters.sort"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
              @change="onFilterChange"
            >
              <option value="suggested">Suggested quantity</option>
              <option value="demand">Forecast demand</option>
              <option value="available">Available stock</option>
              <option value="name">Item name</option>
            </select>
          </div>
          <div class="flex items-end gap-2 xl:col-span-6">
            <button
              type="button"
              class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
              @click="resetFilters"
            >
              Reset
            </button>
            <span class="pb-2 text-xs text-gray-500 dark:text-gray-400">
              Showing <strong class="text-gray-800 dark:text-white">{{ format(paginator.total) }}</strong> recommendation(s)
            </span>
          </div>
        </div>

        <!-- Live table -->
        <div v-if="!isDemoForecast" class="max-h-[70vh] overflow-auto">
          <table class="w-full min-w-[72rem] text-left text-sm">
            <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              <tr>
                <th class="px-4 py-3">Item</th>
                <th class="px-3 py-3">Category</th>
                <th class="px-3 py-3 text-right">Predicted demand</th>
                <th class="px-3 py-3 text-right">Available stock</th>
                <th class="px-3 py-3 text-right">Pending demand</th>
                <th class="px-3 py-3 text-right">Safety stock</th>
                <th class="px-3 py-3 text-right">Suggested quantity</th>
                <th class="px-3 py-3">Priority</th>
                <th class="px-3 py-3">Confidence</th>
                <th class="px-3 py-3">Advisory status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-for="row in rows" :key="row.inventory_id">
                <tr class="align-top transition-colors hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-gray-900 dark:text-white">{{ row.item_name }}</span>
                      <button
                        type="button"
                        class="flex h-6 w-6 items-center justify-center rounded text-gray-500 hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-800"
                        :aria-expanded="expandedId === row.inventory_id"
                        :aria-label="`Why is ${row.item_name} recommended?`"
                        :title="`Why is ${row.item_name} recommended?`"
                        @click="expandedId = expandedId === row.inventory_id ? null : row.inventory_id"
                      >
                        <LucideIcon :icon="Info" class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                  <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ row.category }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.forecast_demand ?? 'N/A' }} {{ row.unit }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.available_stock }} {{ row.unit }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.pending_demand }} {{ row.unit }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.safety_stock ?? 'N/A' }} {{ row.unit }}</td>
                  <td class="px-3 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ row.suggested_procurement ?? 'N/A' }} {{ row.unit }}</td>
                  <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="priorityClass(row.priority)">{{ row.priority }}</span></td>
                  <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="confidenceClass(row.confidence)">{{ row.confidence }}</span></td>
                  <td class="px-3 py-3 text-xs text-gray-600 dark:text-gray-300">{{ row.advisory_status }}</td>
                </tr>
                <tr v-if="expandedId === row.inventory_id" :key="`${row.inventory_id}-detail`">
                  <td colspan="10" class="bg-gray-50/70 px-6 py-4 dark:bg-gray-800/30">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Recommendation details</p>
                    <dl class="mt-2 grid max-w-xl grid-cols-2 gap-1 text-xs text-gray-600 dark:text-gray-300">
                      <dt>Forecast demand</dt><dd class="text-right tabular-nums">{{ row.forecast_demand ?? 'N/A' }} {{ row.unit }}</dd>
                      <dt>Available stock</dt><dd class="text-right tabular-nums">{{ row.available_stock }} {{ row.unit }}</dd>
                      <dt>Safety stock</dt><dd class="text-right tabular-nums">{{ row.safety_stock ?? 'N/A' }} {{ row.unit }}</dd>
                      <dt>Pending demand</dt><dd class="text-right tabular-nums">{{ row.pending_demand }} {{ row.unit }}</dd>
                      <dt class="font-semibold">Suggested</dt><dd class="text-right font-semibold tabular-nums">{{ row.suggested_procurement ?? 'N/A' }} {{ row.unit }}</dd>
                    </dl>
                    <p class="mt-2 max-w-xl text-xs text-gray-500 dark:text-gray-400">Calculation: {{ row.calculation_basis }}</p>
                    <p class="mt-1 max-w-xl text-xs text-gray-500 dark:text-gray-400">{{ row.explanation }}</p>
                  </td>
                </tr>
              </template>
              <tr v-if="!rows.length">
                <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No recommendations match these filters.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Demo table -->
        <div v-else class="max-h-[70vh] overflow-auto">
          <table class="w-full min-w-[52rem] text-left text-sm">
            <thead class="sticky top-0 z-10 bg-sky-50 text-[11px] font-bold uppercase tracking-wider text-sky-900 dark:bg-sky-950 dark:text-sky-200">
              <tr>
                <th class="px-4 py-3">Sample identity</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">History window</th>
                <th class="px-4 py-3 text-right">Verified months</th>
                <th class="px-4 py-3 text-right">Forecast demand</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="row in rows" :key="row.inventory_id">
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                  ID {{ row.inventory_id }}: {{ row.item_name }}
                  <span class="block text-xs font-normal text-gray-500">Category ID {{ row.category_id }}</span>
                </td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ row.category }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                  {{ formatMonth(row.history_window?.start_month) }} to {{ formatMonth(row.history_window?.end_month) }}
                  <span v-if="(row.unknown_months ?? []).length" class="block text-xs text-amber-700 dark:text-amber-300">
                    Unknown: {{ (row.unknown_months ?? []).map(formatMonth).join(', ') }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.historical_months_used }} / {{ row.required_months }} required</td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">
                  {{ row.status === 'success' ? `${row.forecast_demand} ${row.unit}` : 'Insufficient history' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="border-t border-gray-200/80 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
          <Pagination
            class="ml-auto"
            :current-page="paginator.current_page ?? 1"
            :last-page="paginator.last_page ?? 1"
            :from="paginator.from"
            :to="paginator.to"
            :total="paginator.total ?? 0"
            @page="goToPage"
          />
        </div>
      </div>

      <p class="text-xs text-gray-500 dark:text-gray-400">{{ isDemoForecast ? 'Sample/Demo outputs are not live inventory forecasts and do not calculate procurement recommendations.' : 'Forecasts are recommendations only. They do not automatically change inventory or create procurement orders.' }}</p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Info, RefreshCw } from 'lucide';
import api from '../../lib/axios';
import InventoryTableSkeleton from '../../components/ui/skeletons/InventoryTableSkeleton.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const reloading = ref(false);
const error = ref('');
const forecastResult = ref({});
const isDemoForecast = ref(false);
const rows = ref([]);
const paginator = ref({});
const categories = ref([]);
const source = ref(route.query.source === 'demo' ? 'demo' : 'live');
const expandedId = ref(null);
const page = ref(1);
const filters = ref({ search: '', category: '', priority: '', confidence: '', sort: 'suggested' });
let searchTimer = null;

const forecastStatus = computed(() => forecastResult.value?.status ?? null);
const availabilityWarning = computed(() => forecastResult.value?.summary?.availability_warning === true);

const forecastPeriod = computed(() => {
  const period = forecastResult.value?.forecast_period;
  if (!period) return 'Not available';
  if (isDemoForecast.value && /^\d{4}-\d{2}$/.test(period)) {
    return new Date(`${period}-01T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  }
  return period;
});

const generatedAt = computed(() => {
  const generated = forecastResult.value?.generated_at;
  if (!generated) return 'Not available';
  const date = new Date(generated);
  return Number.isNaN(date.getTime()) ? String(generated) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
});

const statusTitle = computed(() => isDemoForecast.value
  ? 'Unable to load Sample/Demo forecast'
  : (forecastStatus.value === 'stale' ? 'Stored forecast is stale' : 'Unable to load demand forecast'));

const statusMessage = computed(() => {
  if (isDemoForecast.value) return 'The demo output is invalid or unavailable. No live forecast was substituted.';
  if (forecastStatus.value === 'stale') return 'No stale forecast was used. Refresh model training before relying on a new result.';
  if (forecastStatus.value === 'failed') return 'The latest model training failed or was interrupted. No previous result was substituted.';
  return 'The latest forecast result could not be read. A valid refresh is required before displaying results.';
});

function priorityClass(priority) {
  switch (priority) {
    case 'Urgent': return 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300';
    case 'High': return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300';
    case 'Medium': return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
    default: return 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300';
  }
}

function confidenceClass(confidence) {
  switch (confidence) {
    case 'High': return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300';
    case 'Medium': return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300';
    default: return 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300';
  }
}

function formatMonth(value) {
  if (!value || !/^\d{4}-\d{2}$/.test(value)) return value ?? '—';
  return new Date(`${value}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const params = { page: page.value };
    if (source.value === 'demo') {
      params.source = 'demo';
    } else {
      if (filters.value.search) params.search = filters.value.search;
      if (filters.value.category) params.category = filters.value.category;
      if (filters.value.priority) params.priority = filters.value.priority;
      if (filters.value.confidence) params.confidence = filters.value.confidence;
      if (filters.value.sort) params.sort = filters.value.sort;
    }
    const { data } = await api.get('/custodian/reports/forecast/recommendations', { params });
    forecastResult.value = data.forecastResult ?? {};
    isDemoForecast.value = data.isDemoForecast === true;
    rows.value = data.recommendations?.data ?? [];
    paginator.value = data.recommendations ?? {};
    categories.value = data.categories ?? [];
    if (data.filters) {
      filters.value = {
        search: data.filters.search ?? '',
        category: data.filters.category ?? '',
        priority: data.filters.priority ?? '',
        confidence: data.filters.confidence ?? '',
        sort: data.filters.sort ?? 'suggested',
      };
    }
    expandedId.value = null;
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not load forecast recommendations.';
  } finally {
    loading.value = false;
  }
}

function onFilterChange() {
  page.value = 1;
  load();
}

function resetFilters() {
  filters.value = { search: '', category: '', priority: '', confidence: '', sort: 'suggested' };
  page.value = 1;
  load();
}

function goToPage(next) {
  page.value = next;
  load();
}

function switchSource(next) {
  if (source.value === next) return;
  source.value = next;
  page.value = 1;
  resetFiltersSilent();
  router.replace({ path: route.path, query: next === 'demo' ? { source: 'demo' } : {} });
  load();
}

function resetFiltersSilent() {
  filters.value = { search: '', category: '', priority: '', confidence: '', sort: 'suggested' };
}

async function reloadForecast() {
  if (reloading.value || isDemoForecast.value) return;
  reloading.value = true;
  try {
    await api.post('/custodian/reports/forecast');
    await load();
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not reload the forecast.';
  } finally {
    reloading.value = false;
  }
}

watch(() => filters.value.search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    load();
  }, 400);
});

onMounted(load);
</script>
