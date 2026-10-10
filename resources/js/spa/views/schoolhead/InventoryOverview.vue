<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory Overview</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-only category summary.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro mini title="Units" :value="format(metrics.units)" subtitle="Non-disposed" :loading="loading" />
        <MetricCard compact dense micro mini title="Available" :value="format(metrics.available)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro mini title="Assigned" :value="format(metrics.assigned)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro mini title="Value" :value="money(metrics.value)" subtitle="Pesos" :loading="loading" />
      </div>

      <!-- Item monitor: every status, read-only -->
      <div class="mt-6">
        <div class="mb-3 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Item Monitor</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">All inventory states, read-only. Showing {{ filteredMonitorRows.length }} of {{ monitorRows.length }} records.</p>
          </div>
          <div class="relative w-full sm:max-w-xs">
            <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
            </svg>
            <input
              v-model="monitorSearch"
              type="search"
              placeholder="Search items, holders, INV numbers…"
              aria-label="Search monitored items"
              class="w-full rounded-md border border-gray-200 bg-white pl-9 pr-3 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            />
          </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Filter by status">
          <button
            v-for="tab in monitorTabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="monitorStatus === tab.key"
            class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="monitorStatus === tab.key
              ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
              : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
            @click="monitorStatus = tab.key"
          >
            <span>{{ tab.label }}</span>
            <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
              {{ format(tab.count) }}
            </span>
          </button>
        </div>

        <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <table class="w-full min-w-[56rem] text-left text-xs">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                <th class="px-4 py-3.5">Item</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5 text-right">Qty</th>
                <th class="px-4 py-3.5">Condition / Status</th>
                <th class="px-4 py-3.5">Holder</th>
                <th class="px-4 py-3.5">Lifespan</th>
              </tr>
            </thead>
            <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
              <tr v-for="row in 5" :key="row">
                <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                  <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'ml-auto w-12'" />
                </td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-for="group in pagedMonitorGroups" :key="group.key">
              <template v-if="group.count === 1" v-for="row in group.entries" :key="row.item_id">
              <tr
                class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                @click="openMonitorDetails(row)"
              >
                <td class="px-4 py-3.5">
                  <div class="font-semibold text-gray-900 dark:text-white">{{ row.item_name }}</div>
                  <span v-if="row.inventory_item_no" class="font-mono text-[11px] text-gray-400">{{ row.inventory_item_no }}</span>
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ row.category }}</td>
                <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">{{ format(row.quantity) }}</td>
                <td class="px-4 py-3.5">
                  <StatusBadge :status="row.status" />
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ row.holder_name ?? '—' }}</td>
                <td class="px-4 py-3.5 text-[11px]" :class="lifespanTextClass(row)">
                  {{ lifespanText(row) }}
                </td>
              </tr>
              </template>
              <template v-else>
              <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                <td class="px-4 py-3.5">
                  <button
                    type="button"
                    class="flex items-center gap-1.5 text-left"
                    :aria-expanded="isMonitorGroupExpanded(group.key)"
                    :title="isMonitorGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                    @click="toggleMonitorGroup(group.key)"
                  >
                    <svg
                      class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                      :class="{ 'rotate-180': isMonitorGroupExpanded(group.key) }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span>
                      <span class="font-semibold text-gray-900 dark:text-white">{{ group.first.item_name }}</span>
                      <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                    </span>
                  </button>
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ group.first.category }}</td>
                <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white" :title="`Sum of ${group.count} records`">{{ format(group.totalQuantity) }}</td>
                <td class="px-4 py-3.5">
                  <StatusBadge :status="group.first.status" />
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ group.first.holder_name ?? '—' }}</td>
                <td class="px-4 py-3.5 text-[11px]" :class="lifespanTextClass(group.first)">
                  {{ lifespanText(group.first) }}
                </td>
              </tr>
              <tr v-if="isMonitorGroupExpanded(group.key)">
                <td colspan="6" class="bg-gray-50/40 px-4 py-2 dark:bg-white/[0.02]">
                  <ul class="space-y-1">
                    <li v-for="entry in group.entries" :key="entry.item_id">
                      <button
                        type="button"
                        class="flex w-full flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 text-left text-[11px] text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        @click="openMonitorDetails(entry)"
                      >
                        <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ entry.inventory_item_no ?? `#${entry.item_id}` }}</span>
                        <span class="font-bold text-gray-900 dark:text-white">×{{ entry.quantity }}</span>
                      </button>
                    </li>
                  </ul>
                </td>
              </tr>
              </template>
              </template>
              <tr v-if="!loading && !filteredMonitorRows.length">
                <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">No records match this filter.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3">
          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            Showing {{ monitorPageFrom }}–{{ monitorPageTo }} of {{ groupedMonitorRows.length }} {{ groupedMonitorRows.length === 1 ? 'group' : 'groups' }}
          </p>
          <Pagination
            :current-page="Math.min(Math.max(1, monitorPage), monitorLastPage)"
            :last-page="monitorLastPage"
            :from="monitorPageFrom"
            :to="monitorPageTo"
            :total="groupedMonitorRows.length"
            @page="goToMonitorPage"
          />
        </div>
      </div>
    </div>
  </div>

  <!-- Read-only details: facts only, no actions by design -->
  <Modal
    :open="monitorDetails !== null"
    :title="monitorDetails ? `${monitorDetails.item_name} details` : 'Item details'"
    :subtitle="monitorDetails?.inventory_item_no ?? ''"
    max-width="max-w-lg"
    @close="monitorDetails = null"
  >
    <div v-if="monitorDetails" class="space-y-2.5 text-sm">
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Category</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ monitorDetails.category }}</span>
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Status</span>
        <StatusBadge :status="monitorDetails.status" />
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Quantity</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ format(monitorDetails.quantity) }} {{ monitorDetails.unit }}</span>
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Holder</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ monitorDetails.holder_name ?? '—' }}</span>
      </div>
      <div class="flex justify-between gap-3">
        <span class="text-gray-500 dark:text-gray-400">Expected end of life</span>
        <span class="font-semibold" :class="lifespanTextClass(monitorDetails)">{{ lifespanText(monitorDetails) }}</span>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';

const loading = ref(true);
const error = ref('');
const metrics = ref({});
const monitorRows = ref([]);
const monitorStatus = ref('all');
const monitorSearch = ref('');
const monitorDetails = ref(null);
const monitorPage = ref(1);

// One page of groups: pagination applies after grouping so a collapsed batch
// is never split across two pages.
const MONITOR_PAGE_SIZE = 10;

const STATUS_LABELS = {
  all: 'All',
  available: 'Available',
  assigned: 'Assigned',
  under_maintenance: 'Under Maintenance',
  under_inspection: 'Under Inspection',
  ready_to_dispose: 'Ready to Dispose',
  disposed: 'Disposed',
};

const monitorTabs = computed(() => {
  const counts = {};
  monitorRows.value.forEach((row) => {
    counts[row.status] = (counts[row.status] ?? 0) + 1;
  });
  // Every state always shows, even at zero — the point of the monitor is
  // seeing all of inventory activity, not only states that have rows.
  return Object.keys(STATUS_LABELS).map((key) => ({
    key,
    label: STATUS_LABELS[key],
    count: key === 'all' ? monitorRows.value.length : (counts[key] ?? 0),
  }));
});

const filteredMonitorRows = computed(() => {
  const query = monitorSearch.value.trim().toLowerCase();
  return monitorRows.value.filter((row) => {
    if (monitorStatus.value !== 'all' && row.status !== monitorStatus.value) {
      return false;
    }
    if (query === '') {
      return true;
    }
    return [
      row.item_name,
      row.inventory_item_no,
      row.category,
      row.holder_name,
      row.status_label,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });
});

// Display-only grouping, mirroring the Transactions ledger: adjacent rows
// identical in item, category, quantity, status, holder and lifespan collapse
// into one expandable group. Raw rows stay untouched; anything differing
// stays its own row.
const expandedMonitorGroups = ref(new Set());

function monitorGroupKey(row) {
  return [
    row.item_name ?? '',
    row.category ?? '',
    Number(row.quantity ?? 0),
    row.status ?? '',
    row.holder_name ?? '',
    row.expected_end_date ?? '',
  ].join('|');
}

const groupedMonitorRows = computed(() => {
  const rows = filteredMonitorRows.value;
  const groups = [];
  rows.forEach((row, index) => {
    const key = monitorGroupKey(row);
    const current = groups[groups.length - 1];
    if (current && current.baseKey === key) {
      current.entries.push(row);
      return;
    }
    groups.push({ key: `${index}:${key}`, baseKey: key, entries: [row] });
  });
  return groups.map((group) => ({
    ...group,
    count: group.entries.length,
    totalQuantity: group.entries.reduce((sum, row) => sum + Number(row.quantity ?? 0), 0),
    first: group.entries[0],
  }));
});

function isMonitorGroupExpanded(key) {
  return expandedMonitorGroups.value.has(key);
}

function toggleMonitorGroup(key) {
  if (expandedMonitorGroups.value.has(key)) {
    expandedMonitorGroups.value.delete(key);
  } else {
    expandedMonitorGroups.value.add(key);
  }
}

const monitorLastPage = computed(() => Math.max(1, Math.ceil(groupedMonitorRows.value.length / MONITOR_PAGE_SIZE)));

const pagedMonitorGroups = computed(() => {
  const page = Math.min(Math.max(1, monitorPage.value), monitorLastPage.value);
  const start = (page - 1) * MONITOR_PAGE_SIZE;
  return groupedMonitorRows.value.slice(start, start + MONITOR_PAGE_SIZE);
});

const monitorPageFrom = computed(() => {
  if (groupedMonitorRows.value.length === 0) {
    return 0;
  }
  const page = Math.min(Math.max(1, monitorPage.value), monitorLastPage.value);
  return (page - 1) * MONITOR_PAGE_SIZE + 1;
});

const monitorPageTo = computed(() => {
  const page = Math.min(Math.max(1, monitorPage.value), monitorLastPage.value);
  return Math.min(groupedMonitorRows.value.length, page * MONITOR_PAGE_SIZE);
});

function goToMonitorPage(page) {
  monitorPage.value = Math.min(Math.max(1, page), monitorLastPage.value);
}

// A new filter restarts on the first page; the current page may no longer
// exist for the narrowed list.
watch([monitorStatus, monitorSearch], () => {
  monitorPage.value = 1;
});

function openMonitorDetails(row) {
  monitorDetails.value = row;
}

function lifespanEndDate(row) {
  if (!row?.expected_end_date) {
    return null;
  }
  const date = new Date(row.expected_end_date);
  return Number.isNaN(date.getTime()) ? null : date;
}

function lifespanText(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'No date';
  }
  return `Ends ${end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}`;
}

function lifespanTextClass(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'text-gray-400 dark:text-gray-500';
  }
  if (end.getTime() < Date.now()) {
    return 'font-semibold text-rose-600 dark:text-rose-400';
  }
  if (end.getTime() <= Date.now() + 365 * 24 * 60 * 60 * 1000) {
    return 'font-semibold text-amber-700 dark:text-amber-300';
  }
  return 'text-gray-500 dark:text-gray-400';
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-inventory',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/inventory/overview');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      monitorRows.value = Array.isArray(data.monitorRows) ? data.monitorRows : [];
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load the inventory overview.';
    },
  });
}

onMounted(() => load());
</script>
