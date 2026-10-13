<template>
  <div class="compact-page">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Requests</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your item requisitions and incoming assignments.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-brand-600"
          @click="requestOpen = true"
        >
          + Request Item
        </button>
      </div>
    </div>

    <div v-if="error" class="compact-alert">
      <span>{{ error }}</span>
      <button type="button" class="compact-btn compact-btn--sm compact-btn--outline" @click="load">Retry</button>
    </div>

    <div v-else class="compact-panel">
      <div class="flex flex-wrap gap-1.5 border-b border-gray-200/80 bg-gray-50/50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
        <button
          type="button"
          class="group inline-flex min-w-0 items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold transition-all sm:justify-start sm:px-3.5"
          :class="activeTab === 'my-requests' ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30' : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          :aria-pressed="activeTab === 'my-requests'"
          @click="activeTab = 'my-requests'"
        >
          <span>My Requests</span>
          <span
            class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold transition-colors"
            :class="activeTab === 'my-requests' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'"
          >
            {{ myRequests.length }}
          </span>
        </button>
        <button
          type="button"
          class="group inline-flex min-w-0 items-center justify-between gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold transition-all sm:justify-start sm:px-3.5"
          :class="activeTab === 'incoming' ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30' : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          :aria-pressed="activeTab === 'incoming'"
          @click="activeTab = 'incoming'"
        >
          <span>Incoming Requests</span>
          <span
            class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold transition-colors"
            :class="pendingIncoming > 0
              ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
              : activeTab === 'incoming'
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'"
          >
            {{ incomingRequests.length }}
          </span>
        </button>
      </div>

      <div class="flex flex-wrap items-center gap-2 border-b border-gray-200/80 bg-gray-50/50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
        <div class="relative w-full sm:max-w-xs">
          <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="filterSearch"
            type="search"
            placeholder="Search item, category…"
            aria-label="Filter requests"
            class="w-full rounded-md border border-gray-200 bg-white py-1.5 pl-8 pr-3 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:py-2 min-[480px]:text-sm [&::-webkit-search-cancel-button]:hidden"
          />
        </div>
        <select
          v-model="filterStatus"
          aria-label="Filter requests by status"
          class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:py-2 min-[480px]:text-sm"
        >
          <option value="">All Statuses</option>
          <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
        </select>
        <a
          :href="exportRequestsHref"
          class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/5 min-[480px]:py-2 min-[480px]:text-sm"
        >
          <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
          Export PDF
        </a>
        <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
          Showing {{ activeTab === 'my-requests' ? filteredMyRequests.length : filteredIncomingRequests.length }} of {{ activeTab === 'my-requests' ? myRequests.length : incomingRequests.length }}
        </span>
      </div>

      <div v-if="activeTab === 'my-requests'" class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10">
            <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
              <th class="px-4 py-3.5">Item Requested</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Requested On</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5">Note</th>
              <th class="px-4 py-3.5 text-center">Actions</th>
            </tr>
          </thead>
          <tbody v-if="loading" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3 w-3/4" />
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="request in filteredMyRequests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3.5">
                <div class="font-semibold text-gray-900 dark:text-white">{{ request.requested_item_name ?? request.item?.item_name ?? 'Unknown item' }}</div>
                <span v-if="request.item?.inventory_item_no" class="text-xs text-gray-500 dark:text-gray-400">{{ request.item.inventory_item_no }}</span>
                <span v-else-if="request.requested_category" class="text-xs text-gray-500 dark:text-gray-400">{{ request.requested_category.category_name }}</span>
              </td>
              <td class="px-4 py-3.5 font-bold text-gray-900 dark:text-white">{{ request.quantity }}</td>
              <td class="px-4 py-3.5 text-md text-gray-500 dark:text-gray-400">{{ formatDate(request.requested_at) }}</td>
              <td class="px-4 py-3.5"><StatusBadge :status="request.status" /></td>
              <td class="px-4 py-3.5 text-md text-gray-500 dark:text-gray-400">{{ request.status === 'cancelled' ? request.cancellation_reason || '—' : request.notes || '—' }}</td>
              <td class="px-4 py-3.5 text-center">
                <button
                  v-if="['waiting for approval', 'waiting for procurement', 'waiting for transfer approval'].includes(request.status)"
                  type="button"
                  class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-rose-950/30"
                  :disabled="cancellingId === request.id"
                  @click="cancelTarget = request"
                >
                  Cancel
                </button>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
            </tr>
            <tr v-if="!filteredMyRequests.length">
              <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">{{ myRequests.length ? 'No requests match the current filters.' : 'No requests yet. Use “Request Item” to start one.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10">
            <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
              <th class="px-4 py-3.5">Item</th>
              <th class="px-4 py-3.5">From</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Requested On</th>
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
            <tr v-for="request in filteredIncomingRequests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3.5">
                <div class="font-semibold text-gray-900 dark:text-white">{{ request.item?.item_name ?? request.requested_item_name ?? 'Unknown item' }}</div>
                <span v-if="request.item?.inventory_item_no" class="text-xs text-gray-500 dark:text-gray-400">{{ request.item.inventory_item_no }}</span>
              </td>
              <td class="px-4 py-3.5">
                <div class="font-medium text-gray-900 dark:text-white">{{ senderName(request) }}</div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ request.user?.email ?? '' }}</span>
              </td>
              <td class="px-4 py-3.5 font-bold text-gray-900 dark:text-white">{{ request.quantity }}</td>
              <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(request.requested_at) }}</td>
              <td class="px-4 py-3.5"><StatusBadge :status="request.status" /></td>
              <td class="px-4 py-3.5">
                <div v-if="['waiting for approval', 'waiting for transfer approval'].includes(request.status)" class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'accept')"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'decline')"
                  >
                    Decline
                  </button>
                </div>
                <span v-else class="text-xs text-gray-400">No action needed</span>
              </td>
            </tr>
            <tr v-if="!filteredIncomingRequests.length">
              <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">{{ incomingRequests.length ? 'No requests match the current filters.' : 'No incoming requests.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <RequestItemModal :open="requestOpen" :categories="categories" @close="requestOpen = false" @saved="onRequestSaved" />

    <ConfirmDialog
      :open="cancelTarget !== null"
      title="Cancel this request?"
      :message="cancelTarget?.status === 'waiting for transfer approval'
        ? `Withdraw your transfer of ${cancelTarget?.requested_item_name ?? cancelTarget?.item?.item_name ?? 'this item'} (${cancelTarget?.quantity ?? ''}). The recipient will no longer see it and your assignment hold is restored.`
        : `Withdraw your request for ${cancelTarget?.requested_item_name ?? cancelTarget?.item?.item_name ?? 'this item'} (${cancelTarget?.quantity ?? ''}). The custodian will no longer see it in the approval queue.`"
      :busy="cancelling"
      confirm-label="Yes, Cancel Request"
      @cancel="cancelTarget = null"
      @confirm="cancelRequest"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import RequestItemModal from './RequestItemModal.vue';

const toast = useToastStore();

const activeTab = ref('my-requests');
const loading = ref(true);
const firstLoading = ref(true);
const error = ref('');
const workingId = ref(null);
const requestOpen = ref(false);
const cancelTarget = ref(null);
const cancelling = ref(false);

const myRequests = ref([]);
const incomingRequests = ref([]);
const categories = ref([]);
const pendingIncoming = ref(0);

const filterSearch = ref('');
const filterStatus = ref('');

const statusOptions = computed(() => [...new Set(
  [...myRequests.value, ...incomingRequests.value]
    .map((request) => request.status)
    .filter(Boolean),
)].sort());

function requestMatches(request) {
  if (filterStatus.value && request.status !== filterStatus.value) {
    return false;
  }
  const query = filterSearch.value.toLowerCase().trim();
  if (!query) {
    return true;
  }
  return [
    request.requested_item_name,
    request.item?.item_name,
    request.item?.inventory_item_no,
    request.requested_category?.category_name,
    request.requestedCategory?.category_name,
  ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query));
}

const filteredMyRequests = computed(() => myRequests.value.filter(requestMatches));
const filteredIncomingRequests = computed(() => incomingRequests.value.filter(requestMatches));

const exportRequestsHref = computed(() => {
  const params = new URLSearchParams({
    tab: activeTab.value === 'incoming' ? 'incoming' : 'my',
    status: filterStatus.value,
    search: filterSearch.value.trim(),
  });

  return `/end-user/requests/export-pdf?${params.toString()}`;
});

const pendingStatuses = ['waiting for approval', 'waiting for transfer approval'];

function senderName(request) {
  const user = request.user;
  if (!user) {
    return 'Unknown';
  }
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

function formatDate(value) {
  if (!value) {
    return 'N/A';
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'end-user-requests',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/end-user/requests');
      return data;
    },
    applyData: (data) => {
      myRequests.value = data.myRequests ?? [];
      incomingRequests.value = data.incomingRequests ?? [];
      categories.value = data.categories ?? [];
      pendingIncoming.value = data.pendingIncomingCount ?? 0;

      if (firstLoading.value && pendingIncoming.value > 0) {
        activeTab.value = 'incoming';
      }
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
      firstLoading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load requests.';
    },
  });
}

async function respond(id, action) {
  workingId.value = id;

  try {
    await api.post(`/end-user/requests/${id}/respond`, { action });
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not process the response.');
    }
  } finally {
    workingId.value = null;
  }
}

function onRequestSaved() {
  requestOpen.value = false;
  forgetPageCache();
  load();
}

async function cancelRequest() {
  if (!cancelTarget.value || cancelling.value) return;

  cancelling.value = true;
  try {
    if (cancelTarget.value.status === 'waiting for transfer approval') {
      await api.post(`/end-user/transfer/${cancelTarget.value.id}/cancel`);
    } else {
      await api.delete(`/end-user/requests/${cancelTarget.value.id}`);
    }
    cancelTarget.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not cancel the request.');
    }
  } finally {
    cancelling.value = false;
  }
}

onMounted(() => load());
</script>
