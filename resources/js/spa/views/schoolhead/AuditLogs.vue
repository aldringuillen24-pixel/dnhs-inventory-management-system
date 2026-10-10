<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Audit Logs</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Account and role changes plus item movement history, newest first. Read-only.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="mb-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Audit streams">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'accounts'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'accounts'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'accounts'"
        >
          <span>Account Activity</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ paginator.total ?? 0 }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'movements'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'movements'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'movements'"
        >
          <span>Item Movements</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ movements.length }}
          </span>
        </button>
      </div>

      <template v-if="activeTab === 'accounts'">
      <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <table class="w-full min-w-[52rem] text-left text-xs">
        <thead>
          <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
            <th class="px-4 py-3.5">Date</th>
            <th class="px-4 py-3.5">Action</th>
            <th class="px-4 py-3.5">Actor</th>
            <th class="px-4 py-3.5">Target</th>
          </tr>
        </thead>
        <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
          <tr v-for="row in 6" :key="row">
            <td v-for="column in 4" :key="column" class="px-4 py-3.5">
              <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'w-3/4'" />
            </td>
          </tr>
        </tbody>
        <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
            <td class="px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ label(log.action) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.actor_name ?? actorName(log.actor) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.target_name ?? actorName(log.target_user) }}</td>
          </tr>
          <tr v-if="!loading && !logs.length">
            <td colspan="4" class="px-4 py-8 text-center text-xs text-gray-500">No audit logs.</td>
          </tr>
        </tbody>
      </table>
      </div>
      <div class="mt-3 flex items-center justify-end">
        <Pagination :current-page="paginator.current_page ?? 1" :last-page="paginator.last_page ?? 1" :from="paginator.from" :to="paginator.to" :total="paginator.total ?? 0" @page="goToPage" />
      </div>
      </template>

      <template v-else>
      <div class="overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" style="max-height: 34rem;">
      <table class="w-full min-w-[64rem] table-fixed text-left text-xs">
        <thead class="sticky top-0 z-10">
          <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
            <th class="w-32 px-3 py-3.5">Timestamp</th>
            <th class="w-40 px-3 py-3.5">Item</th>
            <th class="w-32 px-3 py-3.5">Movement Type</th>
            <th class="w-20 px-3 py-3.5 text-center">Qty Diff</th>
            <th class="w-20 px-3 py-3.5 text-center">Stock Before</th>
            <th class="w-20 px-3 py-3.5 text-center">Stock After</th>
            <th class="w-36 px-3 py-3.5">Authorized By</th>
            <th class="w-56 px-3 py-3.5">Movement Details</th>
          </tr>
        </thead>
        <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
          <tr v-for="row in 6" :key="row">
            <td v-for="column in 8" :key="column" class="px-3 py-3.5">
              <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </td>
          </tr>
        </tbody>
        <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
          <template v-for="group in pagedMovementGroups" :key="group.key">
          <template v-if="group.count === 1" v-for="entry in group.entries" :key="entry.id">
          <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
            <td class="break-words px-3 py-3.5 text-xs text-gray-500 dark:text-gray-400">
              {{ formatDate(entry.created_at) }}
            </td>
            <td class="break-words px-3 py-3.5">
              <div class="font-medium text-gray-900 dark:text-white">{{ entry.inventory?.item_name ?? 'Unknown item' }}</div>
              <span v-if="entry.inventory?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ entry.inventory.inventory_item_no }}</span>
            </td>
            <td class="break-words px-3 py-3.5">
              <span
                class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="movementBadgeClass(entry.movement_type)"
              >
                {{ (entry.movement_type ?? '').replace(/_/g, ' ') }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-center font-bold">
              <span :class="entry.quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                {{ entry.quantity > 0 ? `+${entry.quantity}` : entry.quantity }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-center font-mono text-xs text-gray-500 dark:text-gray-400">
              {{ entry.quantity_before }}
            </td>
            <td class="px-3 py-3.5 text-center font-mono text-xs font-bold text-gray-900 dark:text-white">
              {{ entry.quantity_after }}
            </td>
            <td class="break-words px-3 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
              {{ entry.user ? movementActor(entry.user) : 'System Automated' }}
            </td>
            <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
              {{ entry.display_details || entry.notes || 'No additional details recorded.' }}
            </td>
          </tr>
          </template>
          <template v-else>
          <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
            <td class="break-words px-3 py-3.5 text-xs text-gray-500 dark:text-gray-400">
              {{ formatDate(group.first.created_at) }}
            </td>
            <td class="break-words px-3 py-3.5">
              <button
                type="button"
                class="flex items-center gap-1.5 text-left"
                :aria-expanded="isMovementGroupExpanded(group.key)"
                :title="isMovementGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                @click="toggleMovementGroup(group.key)"
              >
                <svg
                  class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                  :class="{ 'rotate-180': isMovementGroupExpanded(group.key) }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
                <span>
                  <span class="font-medium text-gray-900 dark:text-white">{{ group.first.inventory?.item_name ?? 'Unknown item' }}</span>
                  <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                </span>
              </button>
            </td>
            <td class="break-words px-3 py-3.5">
              <span
                class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="movementBadgeClass(group.first.movement_type)"
              >
                {{ (group.first.movement_type ?? '').replace(/_/g, ' ') }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-center font-bold" :title="`Sum of ${group.count} records`">
              <span :class="group.totalQuantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                {{ group.totalQuantity > 0 ? `+${group.totalQuantity}` : group.totalQuantity }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-center font-mono text-xs text-gray-500 dark:text-gray-400" title="Oldest record's stock before">
              {{ group.rangeBefore }}
            </td>
            <td class="px-3 py-3.5 text-center font-mono text-xs font-bold text-gray-900 dark:text-white" title="Newest record's stock after">
              {{ group.rangeAfter }}
            </td>
            <td class="break-words px-3 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
              {{ group.first.user ? movementActor(group.first.user) : 'System Automated' }}
            </td>
            <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
              {{ group.first.display_details || group.first.notes || 'No additional details recorded.' }}
            </td>
          </tr>
          <tr v-if="isMovementGroupExpanded(group.key)">
            <td colspan="8" class="bg-gray-50/40 px-3 py-2 dark:bg-white/[0.02]">
              <ul class="space-y-1">
                <li v-for="entry in group.entries" :key="entry.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                  <span class="font-semibold text-gray-700 dark:text-gray-300">{{ entry.inventory?.inventory_item_no ?? `#${entry.id}` }}</span>
                  <span :class="Number(entry.quantity ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    {{ Number(entry.quantity ?? 0) > 0 ? `+${entry.quantity}` : entry.quantity }}
                  </span>
                  <span>{{ entry.quantity_before }} → {{ entry.quantity_after }}</span>
                </li>
              </ul>
            </td>
          </tr>
          </template>
          </template>
          <tr v-if="!loading && !movements.length">
            <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-500">No item movements recorded.</td>
          </tr>
        </tbody>
      </table>
      </div>
      <div class="mt-3 flex items-center justify-between gap-3">
        <p class="text-[11px] text-gray-500 dark:text-gray-400">
          Showing {{ movementPageFrom }}–{{ movementPageTo }} of {{ movementGroups.length }} {{ movementGroups.length === 1 ? 'group' : 'groups' }}
        </p>
        <Pagination
          :current-page="Math.min(Math.max(1, movementsPage), movementLastPage)"
          :last-page="movementLastPage"
          :from="movementPageFrom"
          :to="movementPageTo"
          :total="movementGroups.length"
          @page="goToMovementsPage"
        />
      </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import { groupLedgerEntries } from '../../lib/auditLedger';
import Pagination from '../../components/ui/data-display/Pagination.vue';

const loading = ref(true);
const error = ref('');
const activeTab = ref('accounts');
const logs = ref([]);
const paginator = ref({ data: [] });
const page = ref(1);
const movements = ref([]);
const movementsPage = ref(1);
const expandedMovementGroups = ref(new Set());

// Ten groups per page, like the item monitor. Pagination applies after
// grouping so a batch is never split across pages.
const MOVEMENT_GROUPS_PER_PAGE = 10;

function label(action) {
  return String(action ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—';
}

function actorName(user) {
  if (!user) return '—';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || '—';
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function movementActor(user) {
  if (!user) return 'Unknown';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

function movementBadgeClass(type) {
  const t = String(type ?? '').toLowerCase();
  if (t.includes('stock_in') || t.includes('acquired')) {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
  }
  if (t.includes('assignment') || t.includes('issue')) {
    return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300';
  }
  if (t.includes('return')) {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
  }
  if (t.includes('transfer')) {
    return 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300';
  }
  if (t.includes('dispose') || t.includes('lost') || t.includes('damage')) {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
  }
  return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
}

// Same grouping rule as the custodian ledger, so both pages fold identical
// rows identically. Actor labels match this view's display exactly.
const movementGroups = computed(() => groupLedgerEntries(
  movements.value,
  (entry) => (entry.user ? movementActor(entry.user) : 'System Automated'),
));

const movementLastPage = computed(() => Math.max(1, Math.ceil(movementGroups.value.length / MOVEMENT_GROUPS_PER_PAGE)));

const pagedMovementGroups = computed(() => {
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  const start = (page - 1) * MOVEMENT_GROUPS_PER_PAGE;
  return movementGroups.value.slice(start, start + MOVEMENT_GROUPS_PER_PAGE);
});

const movementPageFrom = computed(() => {
  if (movementGroups.value.length === 0) {
    return 0;
  }
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  return (page - 1) * MOVEMENT_GROUPS_PER_PAGE + 1;
});

const movementPageTo = computed(() => {
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  return Math.min(movementGroups.value.length, page * MOVEMENT_GROUPS_PER_PAGE);
});

function goToMovementsPage(next) {
  movementsPage.value = Math.min(Math.max(1, next), movementLastPage.value);
}

function isMovementGroupExpanded(key) {
  return expandedMovementGroups.value.has(key);
}

function toggleMovementGroup(key) {
  if (expandedMovementGroups.value.has(key)) {
    expandedMovementGroups.value.delete(key);
  } else {
    expandedMovementGroups.value.add(key);
  }
}

async function load(options = {}) {
  const params = { page: page.value };
  await loadCachedPage({
    page: 'school-head-audit-logs',
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/audit-logs', { params });
      return data;
    },
    applyData: (data) => {
      const logPage = data.logs ?? {};
      logs.value = logPage.data ?? [];
      paginator.value = logPage;
      movements.value = Array.isArray(data.movements) ? data.movements : [];
      movementsPage.value = 1;
    },
    isCurrent: () => page.value === params.page,
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load audit logs.';
    },
  });
}

function goToPage(next) {
  page.value = next;
  load();
}

onMounted(() => load());
</script>
