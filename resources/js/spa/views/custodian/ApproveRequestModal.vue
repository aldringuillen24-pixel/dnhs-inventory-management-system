<template>
  <Modal :open="open" title="Review Item Request" subtitle="Select matching stockroom units to fulfill this user request." max-width="max-w-2xl" @close="$emit('close')">
    <div v-if="request" class="grid gap-5 md:grid-cols-2">
      <!-- Left Column: Request Details -->
      <section class="space-y-3.5 rounded-md bg-gray-50/80 p-4 text-sm border border-gray-100 dark:bg-white/[0.03] dark:border-white/5">
        <div class="flex items-center justify-between pb-2 border-b border-gray-200/60 dark:border-white/5">
          <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">
            Request Summary
          </h4>
        </div>

        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400">Requester</span>
          <div class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ requesterName }}</div>
          <span class="text-xs text-gray-400">{{ request.user?.email || 'Registered Staff' }}</span>
        </div>

        <div>
          <span class="text-xs text-gray-500 dark:text-gray-400">Item Requested</span>
          <div class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ itemName }}</div>
          <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            <span>{{ categoryName }}</span>
            <span v-if="unitName" class="ml-1 text-gray-400">· {{ unitName }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1">
          <div class="rounded-md bg-white p-2.5 shadow-xs dark:bg-gray-800/60 border border-gray-100 dark:border-white/5">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">Requested</span>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ request.quantity }}</p>
          </div>
          <div class="rounded-md bg-white p-2.5 shadow-xs dark:bg-gray-800/60 border border-gray-100 dark:border-white/5">
            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">Stockroom Has</span>
            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ totalStock }}</p>
          </div>
        </div>

        <div v-if="request.notes" class="pt-1">
          <span class="text-xs text-gray-500 dark:text-gray-400">Purpose / Notes</span>
          <p class="mt-1 rounded-md bg-white p-2.5 text-xs text-gray-700 shadow-xs dark:bg-gray-800/60 dark:text-gray-300 border border-gray-100 dark:border-white/5 italic">
            "{{ request.notes }}"
          </p>
        </div>
      </section>

      <!-- Right Column: Stock Match Selection -->
      <div class="flex flex-col justify-between">
        <fieldset v-if="isItemType" class="min-w-0">
          <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center justify-between">
            <span>Select Matching Stock Unit(s)</span>
            <span class="text-[10px] text-gray-400 font-normal">Check units to allocate</span>
          </legend>

          <div class="max-h-72 space-y-2 overflow-y-auto rounded-md border border-gray-200/80 p-2 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/40">
            <label
              v-for="stock in matches"
              :key="stock.item_id"
              class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200/60 bg-white p-3 shadow-xs transition-all hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:border-gray-800 dark:bg-gray-800 dark:hover:border-emerald-500/50"
              :class="{ 'ring-2 ring-emerald-500/30 border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20': selected.includes(stock.item_id) }"
            >
              <input
                v-model="selected"
                :value="stock.item_id"
                type="checkbox"
                class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
              />
              <div class="min-w-0 flex-1">
                <span class="block truncate font-mono text-xs font-bold text-gray-900 dark:text-white">
                  {{ stock.inventory_item_no ?? `Inventory #${stock.item_id}` }}
                </span>
                <span v-if="stock.serial_number" class="block text-xs font-mono text-gray-500 dark:text-gray-400">
                  SN: {{ stock.serial_number }}
                </span>
                <span class="mt-0.5 inline-block text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                  {{ stock.quantity }} available in stock
                </span>
              </div>
            </label>

            <div v-if="!matches.length" class="p-6 text-center text-xs text-gray-500 dark:text-gray-400">
              No matching available inventory units remain in stock.
            </div>
          </div>
          <FieldError :errors="errors" field="inventory_ids" />
        </fieldset>

        <div v-else class="space-y-3 rounded-md bg-emerald-50/50 p-4 text-xs text-gray-700 dark:bg-emerald-950/20 dark:text-gray-300 border border-emerald-100 dark:border-emerald-900/40">
          <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="font-bold text-emerald-900 dark:text-emerald-300 text-sm">
              Direct Unit Allocation
            </p>
          </div>
          <p>
            Approving this request will immediately deduct <strong>{{ request.quantity }} unit(s)</strong> of <strong>{{ itemName }}</strong> from stockroom availability and assign custody to <strong>{{ requesterName }}</strong>.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="mt-5 flex justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-white/5">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="busy || !canApprove"
            @click="approve"
          >
            <svg v-if="busy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{ busy ? 'Allocating…' : isItemType ? 'Allocate & Approve' : 'Confirm & Approve' }}
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    request: { type: Object, default: null },
});

const emit = defineEmits(['close', 'approved']);

const selected = ref([]);
const busy = ref(false);
const errors = ref({});

const isItemType = computed(() => Boolean(props.request?.requested_item_name) && !props.request?.item_id);
const matches = computed(() => props.request?.matching_inventory_items ?? []);
const requesterName = computed(() => props.request?.user?.first_name
    ? `${props.request.user.first_name} ${props.request.user.last_name ?? ''}`.trim()
    : (props.request?.user?.username ?? 'Unknown User'));
const itemName = computed(() => props.request?.requested_item_name ?? props.request?.item?.item_name ?? 'Unknown item');
// Relation keys are snake_cased by Laravel on serialization, so this is
// `requested_category`, never `requestedCategory`. Reading the camelCase name
// made every item-type request fall back to 'General'.
const categoryName = computed(() => props.request?.requested_category?.category_name ?? props.request?.item?.category?.category_name ?? 'General');
const unitName = computed(() => props.request?.requested_unit ?? props.request?.item?.unit ?? '');
const totalStock = computed(() => props.request?.total_available_stock ?? props.request?.item?.quantity ?? 0);
const canApprove = computed(() => {
    if (!props.request) {
        return false;
    }
    if (totalStock.value < Number(props.request.quantity)) {
        return false;
    }
    return isItemType.value ? selected.value.length > 0 : true;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            selected.value = [];
            errors.value = {};
            busy.value = false;
        }
    },
);

async function approve() {
    busy.value = true;
    errors.value = {};

    try {
        await api.post(`/custodian/requests/${props.request.id}/approve`, isItemType.value ? { inventory_ids: selected.value } : {});
        emit('approved');
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
    } finally {
        busy.value = false;
    }
}
</script>
