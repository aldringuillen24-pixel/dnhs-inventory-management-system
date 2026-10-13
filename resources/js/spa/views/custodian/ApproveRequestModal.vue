<template>
  <Modal
    :open="open && !showSlip"
    title="Review Item Request"
    subtitle="Select matching stockroom units to fulfill this user request."
    max-width="max-w-4xl"
    @close="closeModal"
  >
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
            <p v-if="pendingHold > 0" class="text-[11px] font-semibold text-amber-600 dark:text-amber-300">{{ freeStock }} free ({{ pendingHold }} pending)</p>
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
            <span class="flex items-center gap-2">
              <button
                v-if="hasRemembered"
                type="button"
                class="text-[10px] font-semibold normal-case tracking-normal text-amber-700 underline hover:text-amber-800 dark:text-amber-300"
                @click="clearRemembered"
              >
                Clear last selection
              </button>
              <span class="text-[10px] text-gray-400 font-normal">Check units to allocate</span>
            </span>
          </legend>

          <div class="max-h-72 space-y-2 overflow-y-auto rounded-md border border-gray-200/80 p-2 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-900/40">
            <label
              v-for="stock in matches"
              :key="stock.item_id"
              class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200/60 bg-white p-3 shadow-xs transition-all hover:border-emerald-500/50 hover:bg-emerald-50/20 dark:border-gray-800 dark:bg-gray-800 dark:hover:border-emerald-500/50"
              :class="{
                'ring-2 ring-emerald-500/30 border-emerald-500 bg-emerald-50/30 dark:bg-emerald-950/20': selected.includes(stock.item_id),
                'ring-2 ring-amber-500/40 border-amber-500': isRemembered(stock.item_id) && !selected.includes(stock.item_id),
              }"
            >
              <input
                v-model="selected"
                :value="stock.item_id"
                type="checkbox"
                :disabled="!selected.includes(stock.item_id) && selectedQuantity >= Number(request.quantity)"
                class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
              />
              <div class="min-w-0 flex-1">
                <span class="block truncate font-mono text-xs font-bold text-gray-900 dark:text-white">
                  {{ stock.inventory_item_no ?? `Inventory #${stock.item_id}` }}
                </span>
                <span v-if="isRemembered(stock.item_id)" class="mt-1 inline-block rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                  Previously slip-selected
                </span>
                <span v-if="stock.serial_number" class="block text-xs font-mono text-gray-500 dark:text-gray-400">
                  SN: {{ stock.serial_number }}
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

        <p v-if="slipError" class="text-sm text-red-600" role="alert">{{ slipError }}</p>

        <!-- Action Buttons -->
        <div class="mt-5 flex justify-end gap-2.5 pt-3 border-t border-gray-100 dark:border-white/5">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            @click="closeModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="min-w-28 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300"
            :disabled="busy || slipBusy || approvalComplete || !canApprove"
            @click="openSlipDraft"
          >
            {{ slipBusy ? 'Preparing…' : 'PDF Slip' }}
          </button>
          <button
            type="button"
            class="inline-flex min-w-48 items-center justify-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="busy || approvalComplete || !canApprove"
            @click="approve"
          >
            <svg v-if="busy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{ busy ? 'Preparing…' : isItemType ? 'Allocate & Approve' : 'Confirm & Approve' }}
          </button>
        </div>
      </div>
    </div>

  </Modal>

  <Modal
    :open="open && showSlip"
    title="Property Release Slip"
    subtitle="Review the release details and prepare the PDF."
    max-width="max-w-6xl"
    @close="backFromSlip"
  >
    <div v-if="request" class="space-y-4">
      <SlipDocument
        v-if="slipData"
        v-model:fields="slipFields"
        :doc-no="slipData.docNo"
        :document-date="slipData.documentDate"
        :lines="slipData.lines"
        :purposes="purposes"
      />
      <p v-if="slipError" class="text-sm text-red-600" role="alert">{{ slipError }}</p>
      <div class="flex justify-end gap-2 border-t border-gray-100 pt-3 dark:border-white/5">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
          :disabled="slipBusy"
          @click="backFromSlip"
        >
          {{ approvalComplete ? 'Finish without PDF' : 'Back to Request' }}
        </button>
        <button
          v-if="slipData"
          type="button"
          class="rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-md hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="slipBusy"
          @click="downloadSlip"
        >
          {{ slipBusy ? 'Preparing PDF…' : 'Download PDF' }}
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import SlipDocument from './SlipDocument.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    request: { type: Object, default: null },
});

const emit = defineEmits(['close', 'approved']);

const selected = ref([]);
// Last slip-fed allocation per request id. In-memory only: it survives modal
// reopen within the session, never leaks across requests, and dies on reload.
const lastAllocation = ref(new Map());
const busy = ref(false);
const slipBusy = ref(false);
const errors = ref({});
const approvalComplete = ref(false);
const showSlip = ref(false);
const slipData = ref(null);
const slipFields = ref({});
const slipError = ref('');
const purposes = [
    'Official Use (Off-site)',
    'Transfer / Relocation',
    'Repair / Maintenance',
    'Disposal',
];

const isItemType = computed(() => Boolean(props.request?.requested_item_name) && !props.request?.item_id);
const matches = computed(() => props.request?.matching_inventory_items ?? []);
const selectedQuantity = computed(() => matches.value
    .filter((stock) => selected.value.includes(stock.item_id))
    .reduce((total, stock) => total + Number(stock.quantity ?? 0), 0));
// Remembered allocation for the open request, pruned against the live match
// list so vanished or restocked rows never resurrect as checked rows.
const rememberedIds = computed(() => {
    if (!props.request || !isItemType.value) {
        return [];
    }
    const live = new Set(matches.value.map((stock) => Number(stock.item_id)));

    return (lastAllocation.value.get(Number(props.request.id)) ?? [])
        .map(Number)
        .filter((id) => live.has(id));
});
const rememberedSet = computed(() => new Set(rememberedIds.value));
const hasRemembered = computed(() => rememberedIds.value.length > 0);

function isRemembered(itemId) {
    return rememberedSet.value.has(Number(itemId));
}

function rememberSelection() {
    if (!props.request || !isItemType.value) {
        return;
    }
    const ids = selected.value.map(Number);
    if (ids.length === 0) {
        lastAllocation.value.delete(Number(props.request.id));

        return;
    }
    lastAllocation.value.set(Number(props.request.id), ids);
}

function clearRemembered() {
    if (props.request) {
        lastAllocation.value.delete(Number(props.request.id));
    }
}
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
const pendingHold = computed(() => Number(props.request?.pending_hold_stock ?? 0));
const freeStock = computed(() => {
    if (props.request?.total_free_stock !== undefined && props.request?.total_free_stock !== null) {
        return Number(props.request.total_free_stock);
    }
    return Math.max(0, totalStock.value - pendingHold.value);
});
const canApprove = computed(() => {
    if (!props.request) {
        return false;
    }
    if (freeStock.value < Number(props.request.quantity)) {
        return false;
    }
    return isItemType.value ? selected.value.length > 0 : true;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            selected.value = [...rememberedIds.value];
            errors.value = {};
            busy.value = false;
            slipBusy.value = false;
            approvalComplete.value = false;
            showSlip.value = false;
            slipData.value = null;
            slipFields.value = {};
            slipError.value = '';
        }
    },
);

async function approve() {
    busy.value = true;
    errors.value = {};

    try {
        await api.post(`/custodian/requests/${props.request.id}/approve`, isItemType.value ? { inventory_ids: selected.value } : {});
        clearRemembered();
        approvalComplete.value = true;
        showSlip.value = true;
        const response = await api.get(`/custodian/requests/${props.request.id}/slip`);
        slipData.value = response.data;
        slipFields.value = {
            recipient_name: response.data.recipientName,
            department_unit: '',
            designation: '',
            purpose: '',
            conditions: Object.fromEntries(response.data.lines.map((line) => [line.item_id, ''])),
            expected_return_date: response.data.expectedReturnDate ?? '',
            actual_return_date: response.data.actualReturnDate ?? '',
            released_by_signature: '',
            released_by_name: response.data.custodianName,
            released_by_date: '',
            received_by_signature: '',
            received_by_name: response.data.recipientName,
            received_by_date: '',
            approved_by_signature: '',
            approved_by_name: '',
            approved_by_date: '',
        };
    } catch (requestError) {
        if (approvalComplete.value) {
            slipError.value = 'The request was approved, but release slip details could not be loaded. Finish and refresh to continue.';
        } else {
            errors.value = requestError?.response?.data?.errors ?? {};
        }
    } finally {
        busy.value = false;
    }
}

async function downloadSlip() {
    slipBusy.value = true;
    slipError.value = '';

    try {
        const response = await api.post(
            `/custodian/requests/${props.request.id}/slip-pdf`,
            {
                slip_fields: slipFields.value,
                inventory_ids: isItemType.value ? selected.value : [],
            },
            { responseType: 'blob' },
        );
        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${slipData.value.docNo}.pdf`;
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        finish();
    } catch {
        slipError.value = 'The release slip PDF could not be generated. Please try again.';
    } finally {
        slipBusy.value = false;
    }
}

function closeModal() {
    if (approvalComplete.value) {
        finish();
        return;
    }

    emit('close');
}

async function openSlipDraft() {
    slipBusy.value = true;
    errors.value = {};
    slipError.value = '';

    try {
        const response = await api.get(`/custodian/requests/${props.request.id}/slip`, {
            params: isItemType.value ? { inventory_ids: selected.value } : {},
        });
        slipData.value = response.data;
        rememberSelection();
        slipFields.value = {
            recipient_name: response.data.recipientName,
            department_unit: '',
            designation: '',
            purpose: '',
            conditions: Object.fromEntries(response.data.lines.map((line) => [line.item_id, ''])),
            expected_return_date: response.data.expectedReturnDate ?? '',
            actual_return_date: response.data.actualReturnDate ?? '',
            released_by_signature: '',
            released_by_name: response.data.custodianName,
            released_by_date: '',
            received_by_signature: '',
            received_by_name: response.data.recipientName,
            received_by_date: '',
            approved_by_signature: '',
            approved_by_name: '',
            approved_by_date: '',
        };
        showSlip.value = true;
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
        slipError.value = requestError?.response?.data?.message ?? 'The draft release slip could not be prepared.';
    } finally {
        slipBusy.value = false;
    }
}

function finish() {
    if (approvalComplete.value) {
        emit('approved');
    } else {
        emit('close');
    }
}

function backFromSlip() {
    if (approvalComplete.value) {
        finish();
        return;
    }

    showSlip.value = false;
}
</script>
