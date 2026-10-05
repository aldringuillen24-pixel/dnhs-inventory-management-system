<template>
  <div class="compact-page">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">My Assigned Items</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Items in your care, transferable or returnable from here.</p>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      <span>{{ error }}</span>
      <button
        type="button"
        class="ml-2 rounded-md bg-error-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <div v-else class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10 bg-white dark:bg-gray-900">
            <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <th class="px-4 py-3.5">Type</th>
              <th class="px-4 py-3.5">Item</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Date</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5 text-center">Actions</th>
            </tr>
          </thead>
          <tbody v-if="loading" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3" :class="column === 6 ? 'mx-auto w-20' : 'w-3/4'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <template v-for="(row, index) in rows" :key="`${row.type}-${row.item_id ?? row.item_name}-${index}`">
              <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3.5">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium"
                    :class="row.type === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'"
                  >
                    {{ row.type }}
                  </span>
                </td>
                <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ row.item_name }}</td>
                <td class="px-4 py-3.5">{{ row.quantity }}</td>
                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(row.date) }}</td>
                <td class="px-4 py-3.5"><StatusBadge :status="row.status" /></td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                      :aria-expanded="expanded.has(index)"
                      :aria-label="expanded.has(index) ? `Hide details for ${row.item_name}` : `Show details for ${row.item_name}`"
                      :title="expanded.has(index) ? 'Hide details' : 'Details'"
                      @click="toggleExpand(index)"
                    >
                      <LucideIcon
                        :icon="expanded.has(index) ? ChevronUp : ChevronDown"
                        class="h-4 w-4 transition-transform duration-200"
                      />
                    </button>
                    <button
                      v-if="canTransfer(row)"
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white transition-colors hover:bg-brand-700"
                      :aria-label="`Transfer ${row.item_name} to another end user`"
                      title="Transfer"
                      @click="transferRow = row"
                    >
                      <LucideIcon :icon="ArrowLeftRight" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canRequestReturn(row)"
                      type="button"
                      :disabled="workingId === rowKey(row)"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                      :aria-label="`Request return of ${row.item_name}`"
                      title="Request Return"
                      @click="requestReturn(row)"
                    >
                      <LucideIcon :icon="Undo2" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="isPendingReturn(row)"
                      type="button"
                      :disabled="workingId === rowKey(row)"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-error-200 text-error-700 transition-colors hover:bg-error-50 disabled:opacity-50 dark:border-error-800 dark:text-error-400 dark:hover:bg-error-500/10"
                      :aria-label="`Cancel pending return for ${row.item_name}`"
                      title="Cancel Return"
                      @click="cancelReturn(row)"
                    >
                      <LucideIcon :icon="X" class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="expanded.has(index)">
                <td colspan="6" class="bg-gray-50 px-4 py-3.5 dark:bg-white/[0.02]">
                  <dl class="grid gap-3 text-xs text-gray-600 sm:grid-cols-3 dark:text-gray-400">
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Requested</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(row.request?.requested_at) }}</dd>
                    </div>
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Responded</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(row.request?.responded_at) }}</dd>
                    </div>
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Notes</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ row.request?.notes ?? '—' }}</dd>
                    </div>
                  </dl>
                </td>
              </tr>
            </template>
            <tr v-if="!loading && !rows.length">
              <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">No assigned or requested items.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <TransferModal :open="transferRow !== null" :row="transferRow" :end-users="endUsers" @close="transferRow = null" @saved="onTransferSaved" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { ArrowLeftRight, ChevronDown, ChevronUp, Undo2, X } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import TransferModal from './TransferModal.vue';

const toast = useToastStore();

const loading = ref(true);
const error = ref('');
const workingId = ref(null);
const transferRow = ref(null);
const expanded = ref(new Set());

const rows = ref([]);
const endUsers = ref([]);

const TRANSFERABLE = ['approved', 'accepted'];

function statusOf(row) {
  return String(row.status ?? '').toLowerCase();
}

function rowKey(row) {
  return `${row.type}|${row.item_id ?? row.item_name}`;
}

function requestItemId(row) {
  return row.item_id ?? row.request?.item_id ?? null;
}

function canTransfer(row) {
  return Boolean(row.request?.id) && TRANSFERABLE.includes(statusOf(row));
}

function isPendingReturn(row) {
  return statusOf(row).includes('waiting for return approval');
}

function canRequestReturn(row) {
  return ['approved', 'accepted', 'waiting for return approval'].includes(statusOf(row))
    && !isPendingReturn(row)
    && requestItemId(row) !== null;
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function toggleExpand(index) {
  const next = new Set(expanded.value);
  if (next.has(index)) {
    next.delete(index);
  } else {
    next.add(index);
  }
  expanded.value = next;
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'end-user-assigned',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/end-user/assigned-items');
      return data;
    },
    applyData: (data) => {
      rows.value = data.activityRows ?? [];
      endUsers.value = (data.endUsers ?? []).filter((person) => person.status !== 'inactive');
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load assigned items.';
    },
  });
}

async function requestReturn(row) {
  const itemId = requestItemId(row);
  if (!itemId) {
    return;
  }
  workingId.value = rowKey(row);

  try {
    await api.post(`/end-user/inventory/${itemId}/request-return`);
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not request the return.');
    }
  } finally {
    workingId.value = null;
  }
}

async function cancelReturn(row) {
  const itemId = requestItemId(row);
  if (!itemId) {
    return;
  }
  workingId.value = rowKey(row);

  try {
    await api.post(`/end-user/inventory/${itemId}/cancel-return`);
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not cancel the return.');
    }
  } finally {
    workingId.value = null;
  }
}

function onTransferSaved() {
  transferRow.value = null;
  forgetPageCache();
  load();
}

onMounted(() => load());
</script>
