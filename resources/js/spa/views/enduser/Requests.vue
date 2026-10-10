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

      <div v-if="activeTab === 'my-requests'" class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10">
            <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
              <th class="px-4 py-3.5">Item Requested</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Requested On</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5">Note</th>
            </tr>
          </thead>
          <tbody v-if="loading" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 5" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3 w-3/4" />
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="request in myRequests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
              <td class="px-4 py-3.5">
                <div class="font-semibold text-gray-900 dark:text-white">{{ request.requested_item_name ?? request.item?.item_name ?? 'Unknown item' }}</div>
                <span v-if="request.item?.inventory_item_no" class="text-xs text-gray-500 dark:text-gray-400">{{ request.item.inventory_item_no }}</span>
                <span v-else-if="request.requested_category" class="text-xs text-gray-500 dark:text-gray-400">{{ request.requested_category.category_name }}</span>
              </td>
              <td class="px-4 py-3.5 font-bold text-gray-900 dark:text-white">{{ request.quantity }}</td>
              <td class="px-4 py-3.5 text-md text-gray-500 dark:text-gray-400">{{ formatDate(request.requested_at) }}</td>
              <td class="px-4 py-3.5"><StatusBadge :status="request.status" /></td>
              <td class="px-4 py-3.5 text-md text-gray-500 dark:text-gray-400">{{ request.status === 'cancelled' ? request.cancellation_reason || '—' : request.notes || '—' }}</td>
            </tr>
            <tr v-if="!myRequests.length">
              <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500">No requests yet. Use “Request Item” to start one.</td>
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
            <tr v-for="request in incomingRequests" :key="request.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
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
                    class="h-4 w-4 inline-flex items-center justify-center whitespace-nowrap rounded-md bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'accept')"
                  >
                    Accept
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                    :disabled="workingId === request.id"
                    @click="respond(request.id, 'decline')"
                  >
                    Decline
                  </button>
                </div>
                <span v-else class="text-xs text-gray-400">No action needed</span>
              </td>
            </tr>
            <tr v-if="!incomingRequests.length">
              <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">No incoming requests.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <RequestItemModal :open="requestOpen" :categories="categories" @close="requestOpen = false" @saved="onRequestSaved" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import RequestItemModal from './RequestItemModal.vue';

const toast = useToastStore();

const activeTab = ref('my-requests');
const loading = ref(true);
const firstLoading = ref(true);
const error = ref('');
const workingId = ref(null);
const requestOpen = ref(false);

const myRequests = ref([]);
const incomingRequests = ref([]);
const categories = ref([]);
const pendingIncoming = ref(0);

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

onMounted(() => load());
</script>
