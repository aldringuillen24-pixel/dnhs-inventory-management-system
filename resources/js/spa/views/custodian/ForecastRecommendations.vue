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
      <!-- No forecast-availability card here.

           Two panels used to sit in this slot and both replaced the results
           panel: the severe one ("Unable to load demand forecast" / "The latest
           forecast result could not be read") and a softer "No demand forecast
           available" one. Either way an empty or unreadable stored forecast hid
           the decision support and the table entirely, so an explained state read
           as a broken page.

           Both are gone. The panel below always renders, and the header's
           "Reload latest result" button remains the single place that action
           lives. An empty table and the chat's own "No forecast loaded." note
           already say what is missing. -->
      <div class="custodian-panel overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-1 lg:grid-cols-[25rem_minmax(0,1fr)] xl:grid-cols-[27rem_minmax(0,1fr)]">
          <div
            id="forecast-decision-panel"
            class="min-h-0 max-h-[86vh] border-b border-gray-200/80 dark:border-gray-800 lg:border-b-0 lg:border-r"
            :class="showChatMobile ? 'block' : 'hidden lg:block'"
          >
            <ForecastDecisionPanel
              class="h-full"
              :forecast-period="forecastPeriod === 'Not available' ? null : forecastPeriod"
              :forecast-generated-at="forecastGeneratedAt || null"
              :has-forecast="hasForecastRows"
              :items-needing-procurement="itemsNeedingProcurement"
              :low-confidence-count="lowConfidenceCount"
              :gaps-needing-verification="gapsNeedingVerification"
              :insufficient-history-count="insufficientHistoryCount"
              :total-items="forecastRowCount"
              :table-filtered="activeFilterCount > 0"
              :items="forecastItemIdentity"
            />
          </div>

          <div class="flex min-w-0 flex-col">
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
          <!-- Mobile only: the split view is a desktop layout, so decision
               support stays behind a toggle until the user asks for it. -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 lg:hidden"
            :aria-expanded="showChatMobile"
            aria-controls="forecast-decision-panel"
            @click="showChatMobile = !showChatMobile"
          >
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" class="h-3.5 w-3.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
            </svg>
            AI Assistant
          </button>

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
          <table class="w-full min-w-[46rem] text-left text-sm">
            <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              <tr>
                <th class="px-4 py-3">Item</th>
                <th class="px-3 py-3">Category</th>
                <th class="px-3 py-3 text-right" title="Expected usage next month from verified stock-out history">Predicted demand</th>
                <th class="px-3 py-3 text-right" :title="`Buffer added on top of predicted demand (${safetyStockRateLabel})`">Safety stock</th>
                <th class="px-3 py-3 text-right" title="Quantity staff requested while the item was unavailable">Unmet demand</th>
                <th class="px-3 py-3 text-right" title="Predicted demand + safety stock, minus available stock and pending demand, plus unmet demand">Suggested quantity</th>
                <th class="px-3 py-3" title="How urgent the gap is, based on available stock against predicted demand">Priority</th>
              </tr>
            </thead>
            <tbody
              class="divide-y divide-gray-100 dark:divide-white/5"
              aria-busy="rowsLoading ? 'true' : 'false'"
            >
              <!-- Filter/pagination refresh: keep the table and its headers in
                   place and skeleton only the cells that are being replaced.

                   Each placeholder repeats the cell class of the row it stands in
                   for, so alignment, padding and the priority pill shape match the
                   loaded rows and nothing shifts when the data arrives. -->
              <template v-if="rowsLoading">
                <tr v-for="placeholder in skeletonRows" :key="`skeleton-${placeholder}`" class="align-top">
                  <td class="px-4 py-3">
                    <span class="block h-4 w-44 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3">
                    <span class="block h-4 w-28 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3 text-right">
                    <span class="inline-block h-4 w-20 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3 text-right">
                    <span class="inline-block h-4 w-16 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3 text-right">
                    <span class="inline-block h-4 w-16 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3 text-right">
                    <span class="inline-block h-4 w-20 animate-pulse rounded bg-gray-200/70 dark:bg-white/10" />
                  </td>
                  <td class="px-3 py-3">
                    <span class="inline-block h-5 w-16 animate-pulse rounded-full bg-gray-200/70 dark:bg-white/10" />
                  </td>
                </tr>
              </template>

              <template v-else>
              <template v-for="row in rows" :key="row.inventory_id">
                <tr class="align-top transition-colors hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10">
                  <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ row.item_name }}</td>
                  <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ row.category }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.forecast_demand ?? 'N/A' }} {{ row.unit }}</td>
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ row.safety_stock ?? 'N/A' }} {{ row.unit }}</td>
                  <!-- Only shown when staff actually requested this item while
                       it was unavailable. A zero here would suggest every item
                       has unmet demand, which is not the case. -->
                  <td class="px-3 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                    <template v-if="(row.unmet_demand ?? 0) > 0">
                      <span class="font-semibold text-amber-700 dark:text-amber-300">{{ row.unmet_demand }}</span>
                      <span class="block text-[10px] text-gray-400">
                        {{ row.unmet_requesters }} {{ row.unmet_requesters === 1 ? 'person' : 'people' }}
                      </span>
                    </template>
                    <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                  </td>
                  <td class="px-3 py-3 text-right font-semibold tabular-nums text-gray-900 dark:text-white">{{ row.suggested_procurement ?? 'N/A' }} {{ row.unit }}</td>
                  <td class="px-3 py-3"><span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium" :class="priorityClass(row.priority)">{{ row.priority }}</span></td>
                </tr>
              </template>
              <tr v-if="!rows.length">
                <td colspan="7" class="px-4 py-12 text-center">
                  <span
                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500"
                  >
                    <LucideIcon :icon="PackageSearch" class="h-5 w-5" />
                  </span>
                  <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ hasForecastRows
                      ? 'No recommendations match these filters.'
                      : 'No forecast rows are available yet.' }}
                  </p>
                  <p v-if="!hasForecastRows" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Run a valid model refresh to populate this table.
                  </p>
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
        </div>
      </div>

      <p class="text-xs text-gray-500 dark:text-gray-400">Forecasts are recommendations only. They do not automatically change inventory or create procurement orders.</p>

      <!-- Priority legend. Sits below the results panel rather than inside it,
           so it reads as a footnote to the whole page instead of another strip
           of the table. Collapsed by default to keep the page compact. -->
      <div class="custodian-panel overflow-hidden rounded-md border border-emerald-200/80 bg-emerald-50/50 dark:border-emerald-900/60 dark:bg-emerald-950/20">
        <button
          type="button"
          class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-xs font-semibold text-emerald-800 transition-colors hover:bg-emerald-100/60 sm:px-5 dark:text-emerald-200 dark:hover:bg-emerald-900/40"
          :aria-expanded="showLegend"
          aria-controls="forecast-legend"
          @click="showLegend = !showLegend"
        >
          <svg
            class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
            :class="{ 'rotate-180': showLegend }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
          </svg>
          <LucideIcon :icon="Info" class="h-3.5 w-3.5 shrink-0" />
          Legend
          <span class="font-normal text-emerald-700/80 dark:text-emerald-300/80">
            — what each priority tier means
          </span>
        </button>

        <div v-show="showLegend" id="forecast-legend" class="border-t border-emerald-200/60 px-4 py-3 sm:px-5 dark:border-emerald-900/50">
          <dl class="text-xs">
            <dt class="font-semibold text-emerald-900 dark:text-emerald-100">Priority</dt>
            <dd class="mt-1.5 grid gap-x-6 gap-y-2 sm:grid-cols-2">
              <div
                v-for="tier in priorityLegend"
                :key="tier.name"
                class="flex items-start gap-2"
              >
                <span class="inline-flex shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium" :class="tier.class">{{ tier.name }}</span>
                <span class="leading-snug text-emerald-800/90 dark:text-emerald-300/90">{{ tier.meaning }}</span>
              </div>
            </dd>
          </dl>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Info, PackageSearch, RefreshCw } from 'lucide';
import api from '../../lib/axios';
import InventoryTableSkeleton from '../../components/ui/skeletons/InventoryTableSkeleton.vue';
import ForecastDecisionPanel from '../../components/forecast/ForecastDecisionPanel.vue';
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
// Decision support is a side-by-side desktop layout; below `lg` it is opt-in so
// the table keeps the full width on a phone.
const showChatMobile = ref(false);
// Column legend, collapsed by default so the table keeps its height.
const showLegend = ref(false);
const filters = ref({ search: '', category: '', priority: '', confidence: '', sort: 'suggested' });
let searchTimer = null;

const activeFilterCount = computed(() => ['search', 'category', 'priority', 'confidence']
    .filter((key) => filters.value[key] !== '').length);

// `forecastResult.rows` is the unfiltered forecast, while `rows` is the current
// page after filtering. Comparing them lets the page tell "there is no forecast"
// apart from "your filters matched nothing".
const hasForecastRows = computed(() => (forecastResult.value?.rows ?? []).length > 0);

const forecastRowCount = computed(() => (forecastResult.value?.rows ?? []).length);

const availabilityWarning = computed(() => forecastResult.value?.summary?.availability_warning === true);

const itemsNeedingProcurement = computed(() => forecastResult.value?.summary?.items_needing_procurement ?? 0);

// Confidence is no longer a table column, so the chat opener is where the
// "verify before ordering" risk gets surfaced. Scoped to rows with a
// procurement gap, because a warning about rows nobody is buying is noise.
const lowConfidenceCount = computed(() => (forecastResult.value?.rows ?? []).filter((row) => (
  row?.status !== 'success' || row?.confidence === 'Low' || row?.confidence === 'Medium'
)).length);

const gapsNeedingVerification = computed(() => (forecastResult.value?.rows ?? []).filter((row) => (
  row?.needs_procurement === true
  && (row?.status !== 'success' || row?.confidence === 'Low' || row?.confidence === 'Medium')
)).length);

const insufficientHistoryCount = computed(() => (forecastResult.value?.rows ?? [])
  .filter((row) => row?.status !== 'success').length);

// Item identity only, so the chat can recognise "why is <item> urgent?" and
// send it to the single-item explanation endpoint.
const forecastItemIdentity = computed(() => (forecastResult.value?.rows ?? []).map((row) => ({
  inventory_id: row?.inventory_id,
  item_name: row?.item_name,
  priority: row?.priority,
})));

const forecastPeriod = computed(() => forecastResult.value?.forecast_period || 'Not available');

// Raw training stamp, kept separate from the display-formatted `generatedAt`.
// This is the cycle key the Recommendations tab uses to know when saved
// recommendations belong to the current model run.
const forecastGeneratedAt = computed(() => forecastResult.value?.generated_at || null);

const generatedAt = computed(() => {
  const generated = forecastResult.value?.generated_at;
  if (!generated) return 'Not available';
  const date = new Date(generated);
  return Number.isNaN(date.getTime()) ? String(generated) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
});

function priorityClass(priority) {
  return PRIORITY_TIERS[priority] ?? PRIORITY_TIERS.Normal;
}

// One source for the pill styles, so the legend shows exactly the colours the
// table renders. `Normal` doubles as the fallback for rows without a tier.
const PRIORITY_TIERS = {
  Urgent: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
  High: 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300',
  Medium: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
  Normal: 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

// Wording matches the rules in StoredDemandForecastService::priority(): a tier
// is only assigned when a procurement gap exists, and it is decided purely by
// how far available stock plus pending demand falls short of predicted demand.
const priorityLegend = [
  {
    name: 'Urgent',
    class: PRIORITY_TIERS.Urgent,
    meaning: 'Nothing available on hand, and pending requests still fall short of predicted demand.',
  },
  {
    name: 'High',
    class: PRIORITY_TIERS.High,
    meaning: 'Available stock plus pending demand still does not cover predicted demand.',
  },
  {
    name: 'Medium',
    class: PRIORITY_TIERS.Medium,
    meaning: 'Stock and pending demand cover demand, but only once unmet requests are added.',
  },
  {
    name: 'Normal',
    class: PRIORITY_TIERS.Normal,
    meaning: 'No procurement gap, or not enough verified history to estimate one.',
  },
];

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

// Derived from the rows the forecast actually returned, rather than a hardcoded
// percentage, so the legend keeps describing the real buffer if the backend's
// safety_stock_rate is ever changed. Rounded because the server ceils the value,
// which can push a row a percent or two above the configured rate.
const safetyStockRateLabel = computed(() => {
  const measured = (forecastResult.value?.rows ?? []).reduce((rate, row) => {
    const demand = Number(row?.forecast_demand);
    const safety = Number(row?.safety_stock);

    if (demand > 0 && safety > 0) return Math.max(rate, safety / demand);

    return rate;
  }, 0);

  return `${Math.round((measured || 0.25) * 100)}%`;
});

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
    // These rows came from an earlier successful load. Once the refresh has
    // failed they can no longer be trusted: an item deleted since then is
    // still rendered as a clickable link, and following it 404s. Clear them so
    // the table never shows entries the server has just disowned.
    rows.value = [];
    paginator.value = {};
    categories.value = [];
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
