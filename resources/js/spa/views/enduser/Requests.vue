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

    <div v-else class="compact-panel !p-0">
      <div class="relative z-0 flex gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50" role="tablist" aria-label="My and incoming requests">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'my-requests'"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="activeTab === 'my-requests'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'my-requests'"
        >
          My Requests
          <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ activeMyRequestsCount }}</span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'incoming-requests'"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="activeTab === 'incoming-requests'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'incoming-requests'"
        >
          Incoming Requests
          <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ activeIncomingCount }}</span>
        </button>
      </div>
      <div class="relative z-0 flex gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50">
        <button
          type="button"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="requestView === 'active'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="requestView = 'active'"
        >
          Active
        </button>
        <button
          type="button"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="requestView === 'history'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="requestView = 'history'"
        >
          History
        </button>
      </div>
      <div class="flex flex-wrap items-center gap-2 border-b border-gray-200/80 bg-gray-50/50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50">
        <div class="relative w-full sm:max-w-xs">
          <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="filterSearch"
            type="search"
            placeholder="Search item, category…"
            aria-label="Filter requests"
            class="h-[30px] w-full rounded-md border border-gray-200 bg-white py-0 pl-8 pr-3 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 [&::-webkit-search-cancel-button]:hidden"
          />
        </div>
        <select
          v-model="filterStatus"
          aria-label="Filter requests by status"
          class="h-[30px] rounded-md border border-gray-200 bg-white px-2.5 py-0 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        >
          <option value="">All Statuses</option>
          <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
        </select>
        <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
          Showing {{ activeTab === 'incoming-requests' ? filteredIncomingRequests.length : filteredMyRequests.length }} of {{ activeTab === 'incoming-requests' ? incomingRequests.length : myRequests.length }}
        </span>
      </div>

      <Transition name="request-tab" mode="out-in">
        <div :key="`${activeTab}-${requestView}`" class="max-h-[28rem] overflow-x-auto overflow-y-auto">
          <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10 bg-white dark:bg-gray-900">
            <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
              <th class="px-4 py-3.5">Item Requested</th>
              <th class="px-4 py-3.5">Request Type</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Requested On</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5">{{ activeTab === 'incoming-requests' ? 'From' : 'Note' }}</th>
              <th class="px-4 py-3.5 text-center">Actions</th>
            </tr>
          </thead>
          <tbody v-if="loading" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 7" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3 w-3/4" />
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="request in (activeTab === 'incoming-requests' ? filteredIncomingRequests : filteredMyRequests)" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-2">
                <div class="flex items-start gap-2">
                  <LucideIcon :icon="requestItemIcon(request)" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600 dark:text-brand-400" aria-hidden="true" />
                  <div class="min-w-0">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ request.requested_item_name ?? request.item?.item_name ?? 'Unknown item' }}</div>
                    <span v-if="request.item?.inventory_item_no" class="text-[11px] text-gray-500 dark:text-gray-400">{{ request.item.inventory_item_no }}</span>
                    <span v-else-if="request.requested_category" class="text-[11px] text-gray-500 dark:text-gray-400">{{ request.requested_category.category_name }}</span>
                  </div>
                </div>
                <div v-if="activeTab === 'my-requests' && requestType(request) === 'Item Request'" class="mt-1">
                  <span
                    class="inline-flex items-center rounded-md border px-1.5 py-0.5 text-[10px] font-semibold"
                    :class="request.priority === 'urgent'
                      ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300'
                      : request.priority === 'needed_soon'
                        ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'
                        : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'"
                  >
                    {{ request.priority === 'urgent' ? 'Urgent' : request.priority === 'needed_soon' ? 'Needed soon' : 'Normal' }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-2">
                <span
                  class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold"
                  :class="activeTab === 'incoming-requests' ? incomingRequestTypeClass(request) : requestTypeClass(request)"
                >
                  {{ activeTab === 'incoming-requests' ? incomingRequestType(request) : requestType(request) }}
                </span>
              </td>
              <td class="px-4 py-2 font-bold text-gray-900 dark:text-white">{{ request.quantity }}</td>
              <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(request.requested_at) }}</td>
              <td class="px-4 py-2">
                <span
                  v-if="request.status === 'draft'"
                  class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                  Item is ready — visit your custodian
                </span>
                <StatusBadge v-else :status="request.status" compact />
              </td>
              <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                <template v-if="activeTab === 'incoming-requests'">
                  {{ senderName(request) }}
                </template>
                <template v-else>
                  {{ request.status === 'cancelled' ? request.cancellation_reason || '—' : request.notes || '—' }}
                </template>
              </td>
              <td class="px-4 py-2 text-center">
                <template v-if="activeTab === 'my-requests'">
                  <button
                    v-if="['waiting for approval', 'waiting for procurement', 'waiting for transfer approval'].includes(request.status) || (request.status === 'waiting for custodian approval' && ['End User', 'Property Custodian'].includes(request.target_user?.role?.role_name))"
                    type="button"
                    class="inline-flex min-w-16 items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-rose-950/30"
                    :disabled="cancelling"
                    @click="cancelTarget = request"
                  >
                    Cancel
                  </button>
                  <span v-else class="text-xs text-gray-400">—</span>
                </template>
                <div v-else-if="requestView === 'active' && request.status === 'waiting for approval' && !request.source_assignment_id" class="flex items-center justify-center gap-1.5">
                  <button
                    type="button"
                    class="rounded-md bg-emerald-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'accept')"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    class="rounded-md border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'decline')"
                  >
                    Decline
                  </button>
                </div>
                <button
                  v-else-if="requestView === 'active'"
                  type="button"
                  class="inline-flex min-w-24 items-center justify-center whitespace-nowrap rounded-md border border-emerald-200 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                  :disabled="workingId === request.id || !canConfirmReceipt(request) || Boolean(request.received_at)"
                  :title="request.received_at ? 'Receipt confirmed' : canConfirmReceipt(request) ? 'Confirm receipt' : 'Available after custodian allocation or transfer approval'"
                  @click="confirmReceipt(request)"
                >
                  {{ request.received_at ? 'Received' : workingId === request.id ? 'Saving…' : 'Mark Received' }}
                </button>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>
            </tr>
            <tr v-if="!(activeTab === 'incoming-requests' ? filteredIncomingRequests : filteredMyRequests).length">
              <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500">{{ activeTab === 'incoming-requests' ? (incomingRequests.length ? 'No incoming requests match the current filters.' : (requestView === 'active' ? 'No incoming requests.' : 'No incoming request history.')) : (myRequests.length ? 'No requests match the current filters.' : (requestView === 'active' ? 'No active requests.' : 'No history yet.')) }}</td>
            </tr>
          </tbody>
          </table>
        </div>
      </Transition>
    </div>

    <RequestItemModal :open="requestOpen" :categories="categories" @close="requestOpen = false" @saved="onRequestSaved" />

    <ConfirmDialog
      :open="cancelTarget !== null"
      title="Cancel this request?"
      :message="cancelTarget?.status === 'waiting for custodian approval' && cancelTarget?.target_user?.role?.role_name === 'Property Custodian'
        ? `Cancel your return request for ${cancelTarget?.item?.item_name ?? 'this item'} (${cancelTarget?.quantity ?? ''}). The custodian will no longer process this return.`
        : ['waiting for transfer approval', 'waiting for custodian approval'].includes(cancelTarget?.status) && cancelTarget?.target_user?.role?.role_name === 'End User'
          ? `Withdraw your transfer of ${cancelTarget?.requested_item_name ?? cancelTarget?.item?.item_name ?? 'this item'} (${cancelTarget?.quantity ?? ''}). The custodian will no longer process it and your assignment is restored.`
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
import {
  Armchair,
  BookOpen,
  Dumbbell,
  FileStack,
  FlaskConical,
  Monitor,
  Package,
  Printer,
  Projector,
  ShieldCheck,
  Wrench,
  Zap,
} from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import RequestItemModal from './RequestItemModal.vue';

const toast = useToastStore();

const activeTab = ref('my-requests');
const requestView = ref('active');
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

const activeMyRequestsCount = computed(() =>
  myRequests.value.filter((request) => activeStatuses.includes(request.status)).length,
);
const activeIncomingCount = computed(() =>
  incomingRequests.value.filter((request) => activeStatuses.includes(request.status)).length,
);

function requestItemIcon(request) {
  const category = String(
    request.requested_category?.category_name
      ?? request.requestedCategory?.category_name
      ?? request.item?.category?.category_name
      ?? '',
  ).toLowerCase();
  const itemName = String(request.requested_item_name ?? request.item?.item_name ?? '').toLowerCase();
  if (itemName.includes('projector') || category.includes('audio-visual')) return Projector;
  if (category.includes('classroom') || category.includes('library') || category.includes('resource')) return BookOpen;
  if (category.includes('cleaning') || category.includes('sanitation') || category.includes('maintenance')) return Wrench;
  if (category.includes('consumable') || category.includes('office suppl')) return FileStack;
  if (category.includes('electrical')) return Zap;
  if (category.includes('furniture')) return Armchair;
  if (category.includes('ict') || category.includes('computer')) return Monitor;
  if (category.includes('laboratory') || category.includes('science')) return FlaskConical;
  if (category.includes('office')) return Printer;
  if (category.includes('safety') || category.includes('security') || category.includes('emergency')) return ShieldCheck;
  if (category.includes('sports')) return Dumbbell;
  return Package;
}

function requestType(request) {
  if (request.source_assignment_id) return 'Transfer';
  if (
    request.item_id
    && !String(request.requested_item_name ?? '').trim()
    && request.target_user?.role?.role_name === 'Property Custodian'
  ) {
    return 'Return';
  }
  return 'Item Request';
}

function requestTypeClass(request) {
  const type = requestType(request);
  if (type === 'Return') return 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300';
  if (type === 'Transfer') return 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
  return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
}

function incomingRequestType(request) {
  return request.source_assignment_id
    || ['waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'].includes(request.status)
    ? 'Transfer'
    : 'Custodian Assignment';
}

function incomingRequestTypeClass(request) {
  return incomingRequestType(request) === 'Transfer'
    ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
    : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
}

function canConfirmReceipt(request) {
  return ['approved', 'accepted'].includes(String(request.status ?? '').toLowerCase())
    && Boolean(request.transaction_id);
}

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

const activeStatuses = ['waiting for approval', 'waiting for procurement', 'waiting for transfer approval', 'waiting for custodian approval', 'transfer draft', 'draft'];
const historyStatuses = ['cancelled', 'approved', 'fulfilled', 'rejected', 'declined'];
const requestPillStyle = computed(() => {
  const left = requestView.value === 'active' ? '0.5rem' : '50%';
  return { left, width: 'calc(50% - 0.5rem)' };
});

const filteredMyRequests = computed(() => myRequests.value.filter((request) => {
  if (requestView.value === 'active' && !activeStatuses.includes(request.status)) return false;
  if (requestView.value === 'history' && !historyStatuses.includes(request.status)) return false;
  return requestMatches(request);
}));
const filteredIncomingRequests = computed(() => incomingRequests.value.filter((request) => {
  if (requestView.value === 'active' && !activeStatuses.includes(request.status)) return false;
  if (requestView.value === 'history' && !historyStatuses.includes(request.status)) return false;
  return requestMatches(request);
}));

const pendingStatuses = ['waiting for approval', 'waiting for transfer approval', 'waiting for custodian approval', 'transfer draft'];

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
        activeTab.value = 'incoming-requests';
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

async function confirmReceipt(request) {
  if (!canConfirmReceipt(request) || request.received_at || workingId.value !== null) return;
  workingId.value = request.id;

  try {
    await api.post(`/end-user/requests/${request.id}/received`);
    toast.success('Receipt confirmed', 'The item has been marked as received.');
    forgetPageCache();
    await load();
  } catch (requestError) {
    toast.error(
      'Could not confirm receipt',
      requestError?.response?.data?.message ?? 'Refresh the request and try again.',
    );
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
    const targetRole = cancelTarget.value.target_user?.role?.role_name;
    if (cancelTarget.value.status === 'waiting for custodian approval' && targetRole === 'Property Custodian') {
      await api.post(`/end-user/inventory/${cancelTarget.value.item_id}/cancel-return`);
    } else if (
      ['waiting for transfer approval', 'waiting for custodian approval'].includes(cancelTarget.value.status)
      && targetRole === 'End User'
    ) {
      await api.post(`/end-user/transfer/${cancelTarget.value.id}/cancel`);
    } else {
      await api.delete(`/end-user/requests/${cancelTarget.value.id}`);
    }
    cancelTarget.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    toast.error('Error', requestError?.response?.data?.message ?? 'Could not cancel the request.');
  } finally {
    cancelling.value = false;
  }
}

onMounted(() => load());
</script>

<style scoped>
.request-tab-enter-active,
.request-tab-leave-active {
  will-change: opacity, transform;
  transition:
    opacity 220ms cubic-bezier(0.22, 1, 0.36, 1),
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.request-tab-enter-from {
  opacity: 0;
  transform: translateY(6px) scale(0.995);
}

.request-tab-leave-to {
  opacity: 0;
  transform: translateY(-3px) scale(0.995);
}

@media (prefers-reduced-motion: reduce) {
  .request-tab-enter-active,
  .request-tab-leave-active {
    transition: none;
  }
}
</style>
