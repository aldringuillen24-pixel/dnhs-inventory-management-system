<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
          Demand Forecast Recommendations
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Next-month supply demand based on approved stock-out history.
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
          type="button"
          :disabled="reloading"
          class="mt-3 inline-flex items-center gap-2 rounded-md bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
          @click="reloadForecast"
        >
          <LucideIcon :icon="RefreshCw" class="h-4 w-4" :class="{ 'animate-spin': reloading }" />
          {{ reloading ? 'Reloading…' : 'Reload latest result' }}
        </button>
      </div>

      <!-- Empty: only when the stored forecast genuinely has no rows. A filter that
           matches nothing keeps the panel (and the filters) on screen and shows
           "no recommendations match" inside the table instead. -->
      <div
        v-else-if="!hasForecastRows"
        class="rounded-md border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900"
      >
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">No demand forecast available</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">No trained production ML forecast is available. Model training runs separately from chat and reports.</p>
        <button
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

        <!-- Filter bar: collapsed by default so the recommendations load immediately.
             The panel stays reachable, and opens itself whenever a filter is
             active so a narrowed result is never hidden behind a closed panel. -->
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-4 py-2.5 dark:border-white/5 sm:px-5">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            :aria-expanded="showFilters"
            aria-controls="forecast-filters"
            @click="showFilters = !showFilters"
          >
            <svg
              class="h-3.5 w-3.5 transition-transform duration-200"
              :class="{ 'rotate-180': showFilters }"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
              aria-hidden="true"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
            Filters
            <span
              v-if="activeFilterCount"
              class="rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white"
            >
              {{ activeFilterCount }}
            </span>
          </button>

          <button
            v-if="activeFilterCount"
            type="button"
            class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            @click="resetFilters"
          >
            Clear
          </button>

          <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
            Showing <strong class="text-gray-800 dark:text-white">{{ format(paginator.total) }}</strong> recommendation(s)
          </span>
        </div>

        <!-- Filters -->
        <div
          v-show="showFilters"
          id="forecast-filters"
          class="grid gap-3 border-b border-gray-100 p-4 dark:border-white/5 sm:grid-cols-2 sm:px-5 xl:grid-cols-5"
        >
          <div class="sm:col-span-2 xl:col-span-1">
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
          <div class="flex items-end gap-2 xl:col-span-5">
            <button
              type="button"
              class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
              @click="resetFilters"
            >
              Reset
            </button>
          </div>
        </div>

        <!-- Recommendations table -->
        <div class="max-h-[70vh] overflow-auto">
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
            <tbody
              class="divide-y divide-gray-100 transition-opacity duration-150 dark:divide-white/5"
              :class="{ 'opacity-60': rowsLoading }"
              aria-busy="rowsLoading ? 'true' : 'false'"
            >
              <!-- Filter/pagination refresh: keep the table and its headers in
                   place and skeleton only the cells that are being replaced. -->
              <template v-if="rowsLoading">
                <tr v-for="placeholder in skeletonRows" :key="`skeleton-${placeholder}`" class="align-top">
                  <td class="px-4 py-3">
                    <span class="block h-4 w-40 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td v-for="column in 9" :key="`skeleton-cell-${column}`" class="px-3 py-3">
                    <span
                      class="block h-4 animate-pulse rounded bg-gray-200/70 dark:bg-white/10"
                      :class="column === 1 ? 'w-28' : 'w-16 ml-auto'"
                    />
                  </td>
                </tr>
              </template>

              <template v-else>
              <template v-for="row in rows" :key="row.inventory_id">
                <tr class="align-top transition-colors hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10">
                  <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.item_name }}</td>
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
              </template>
              <tr v-if="!rows.length">
                <td colspan="10" class="px-4 py-12 text-center">
                  <span
                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500"
                  >
                    <LucideIcon :icon="PackageSearch" class="h-5 w-5" />
                  </span>
                  <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">No recommendations match these filters.</p>
                </td>
              </tr>
              </template>
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

      <p class="text-xs text-gray-500 dark:text-gray-400">Forecasts are recommendations only. They do not automatically change inventory or create procurement orders.</p>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { ArrowLeft, PackageSearch, RefreshCw } from 'lucide';
import api from '../../lib/axios';
import InventoryTableSkeleton from '../../components/ui/skeletons/InventoryTableSkeleton.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';

const loading = ref(true);
const reloading = ref(false);
// Filter, sort and page changes only replace the rows. Keeping `loading` false
// for those requests holds the table and its column headers on screen, so the
// page does not flash a full skeleton each time a filter is applied.
const rowsLoading = ref(false);
let loadedOnce = false;
const skeletonRows = [1, 2, 3, 4, 5, 6, 7, 8];
const error = ref('');
const forecastResult = ref({});
const rows = ref([]);
const paginator = ref({});
const categories = ref([]);
const page = ref(1);
// The recommendations are the point of this page, so the filter panel starts
// closed and the table loads straight away.
const showFilters = ref(false);
const filters = ref({ search: '', category: '', priority: '', confidence: '', sort: 'suggested' });
let searchTimer = null;

const activeFilterCount = computed(() => ['search', 'category', 'priority', 'confidence']
    .filter((key) => filters.value[key] !== '').length);

// `forecastResult.rows` is the unfiltered forecast, while `rows` is the current
// page after filtering. Comparing them lets the page tell "there is no forecast"
// apart from "your filters matched nothing".
const hasForecastRows = computed(() => (forecastResult.value?.rows ?? []).length > 0);

const forecastStatus = computed(() => forecastResult.value?.status ?? null);
const availabilityWarning = computed(() => forecastResult.value?.summary?.availability_warning === true);

const forecastPeriod = computed(() => forecastResult.value?.forecast_period || 'Not available');

const generatedAt = computed(() => {
  const generated = forecastResult.value?.generated_at;
  if (!generated) return 'Not available';
  const date = new Date(generated);
  return Number.isNaN(date.getTime()) ? String(generated) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
});

const statusTitle = computed(() => (
  forecastStatus.value === 'stale' ? 'Stored forecast is stale' : 'Unable to load demand forecast'
));

const statusMessage = computed(() => {
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

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

async function load() {
  // The very first visit has no table to preserve, so it shows the full page
  // skeleton. Every later request (filter, sort, page, reload) refreshes only
  // the rows.
  if (loadedOnce) {
    rowsLoading.value = true;
  } else {
    loading.value = true;
  }
  error.value = '';
  try {
    const params = { page: page.value };
    if (filters.value.search) params.search = filters.value.search;
    if (filters.value.category) params.category = filters.value.category;
    if (filters.value.priority) params.priority = filters.value.priority;
    if (filters.value.confidence) params.confidence = filters.value.confidence;
    if (filters.value.sort) params.sort = filters.value.sort;

    const { data } = await api.get('/custodian/reports/forecast/recommendations', { params });
    forecastResult.value = data.forecastResult ?? {};
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
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not load forecast recommendations.';
  } finally {
    loading.value = false;
    rowsLoading.value = false;
    loadedOnce = true;
  }
}

function onFilterChange() {
  page.value = 1;
  load();
}

function resetFilters() {
  filters.value = { search: '', category: '', priority: '', confidence: '', sort: 'suggested' };
  showFilters.value = false;
  page.value = 1;
  load();
}

function goToPage(next) {
  page.value = next;
  load();
}

async function reloadForecast() {
  if (reloading.value) return;
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

// A narrowed result must never sit behind a closed panel, so applying any
// filter reopens it. Clearing every filter closes it again via resetFilters().
watch(activeFilterCount, (count) => {
  if (count > 0) {
    showFilters.value = true;
  }
});

watch(() => filters.value.search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    load();
  }, 400);
});

onMounted(load);
</script>
