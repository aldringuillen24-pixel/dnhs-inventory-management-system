<template>
  <Modal :open="open && !showSlip" title="Assign Stockroom Item" subtitle="Issue equipment to a registered school staff member or record a manual loan." max-width="max-w-2xl" @close="$emit('close')">
    <!-- Mode Switcher -->
    <div class="mb-5 flex rounded-md bg-gray-100 p-1 dark:bg-white/5 border border-gray-200/60 dark:border-gray-800">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="flex-1 rounded-md py-2 text-xs font-bold transition-all"
        :class="
          mode === tab.key
            ? 'bg-white text-emerald-900 shadow-sm dark:bg-gray-800 dark:text-emerald-300'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
        "
        @click="mode = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- 1. Registered User Mode -->
    <form v-if="mode === 'registered'" class="space-y-4" @submit.prevent="openSlipPreview">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-item">
            Available Inventory Item <span class="text-rose-500">*</span>
          </label>
          <select
            id="assign-item"
            v-model="registered.item_id"
            required
            :disabled="assignableItems.length === 0"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select available stock to issue</option>
            <option v-for="item in assignableItems" :key="item.item_id" :value="item.item_id">
              {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ freeQuantity(item) }} {{ item.unit }} free{{ (item.pending_hold ?? 0) > 0 ? `, ${item.pending_hold} pending` : '' }})
            </option>
          </select>
          <p v-if="assignableItems.length === 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            No inventory items have unreserved stock available.
          </p>
          <p v-else-if="staleNotice" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            {{ staleNotice }}
          </p>
          <FieldError :errors="errors" field="item_id" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-user">
            Registered Recipient <span class="text-rose-500">*</span>
          </label>
          <select
            id="assign-user"
            v-model="registered.user_id"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select staff recipient</option>
            <option v-for="person in endUsers" :key="person.id" :value="person.id">
              {{ person.name }}
            </option>
          </select>
          <FieldError :errors="errors" field="user_id" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-qty">
            Quantity to Issue <span class="text-rose-500">*</span>
          </label>
          <input
            id="assign-qty"
            v-model.number="registered.quantity"
            type="number"
            required
            min="1"
            :max="registeredFree || undefined"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="quantity" />
          <p v-if="registeredItem && (registeredItem.pending_hold ?? 0) > 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            {{ registeredItem.free_quantity ?? 0 }} free of {{ registeredItem.quantity }} ({{ registeredItem.pending_hold }} awaiting acceptance).
          </p>
        </div>

        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-date">
            Assignment Date
          </label>
          <input
            id="assign-date"
            v-model="registered.transaction_date"
            type="date"
            :max="today"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 sm:max-w-xs"
          />
          <FieldError :errors="errors" field="transaction_date" />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap justify-end gap-2 pt-3 border-t border-gray-100 dark:border-white/5">
        <button
          type="button"
          class="shrink-0 rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="shrink-0 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-300"
          :disabled="busy || drafting || !canDraftPreview"
        >
          {{ drafting ? 'Saving…' : 'Print & Draft' }}
        </button>
      </div>
    </form>

    <!-- 2. Manual Issue Mode -->
    <form v-else class="space-y-4" @submit.prevent="openManualSlip">
      <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-item">
            Available Inventory Item <span class="text-rose-500">*</span>
          </label>
          <select
            id="manual-item"
            v-model="manual.item_id"
            required
            :disabled="assignableItems.length === 0"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select available stock to issue</option>
            <option v-for="item in assignableItems" :key="item.item_id" :value="item.item_id">
              {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ freeQuantity(item) }} {{ item.unit }} free{{ (item.pending_hold ?? 0) > 0 ? `, ${item.pending_hold} pending` : '' }})
            </option>
          </select>
          <p v-if="assignableItems.length === 0" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            No inventory items have unreserved stock available.
          </p>
          <p v-else-if="staleNotice" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
            {{ staleNotice }}
          </p>
          <FieldError :errors="errors" field="item_id" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-name">
            Recipient Name <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-name"
            v-model="manual.manual_recipient_name"
            type="text"
            required
            maxlength="255"
            placeholder="e.g. Maria Santos"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="manual_recipient_name" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-qty">
            Quantity Issued <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-qty"
            v-model.number="manual.quantity"
            type="number"
            required
            min="1"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="quantity" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-date">
            Transaction Date <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-date"
            v-model="manual.transaction_date"
            type="date"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="transaction_date" />
        </div>

      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap justify-end gap-2 pt-3 border-t border-gray-100 dark:border-white/5">
        <button
          type="button"
          class="shrink-0 rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50"
          :disabled="busy || !manual.item_id"
        >
          {{ busy ? 'Saving…' : 'Preview Slip PDF' }}
        </button>
      </div>
    </form>
  </Modal>

  <SlipDocument
    v-if="slipData"
    :open="showSlip"
    title="Property Release Slip"
    subtitle="Preview only — nothing issued yet"
    :doc-no="slipData.docNo"
    :document-date="slipData.documentDate"
    :lines="slipData.lines"
    :purposes="purposes"
    :recipient-name="slipData.recipientName"
    :custodian-name="auth.displayName"
    :draft-mode="true"
    :primary-label="manualMode
      ? (busy ? 'Recording…' : 'Record Manual Assignment')
      : (drafting ? 'Saving…' : 'Save Draft')"
    :primary-disabled="busy || drafting"
    @back="backFromSlip"
    @primary="manualMode ? saveManualFromSlip($event) : saveDraftFromSlip()"
    @close="showSlip = false"
  />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import SlipDocument from '../../views/shared/SlipDocument.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    endUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved', 'drafted']);
const toast = useToastStore();
const auth = useAuthStore();

const tabs = [
    { key: 'registered', label: 'Registered Staff Member' },
    { key: 'manual', label: 'Manual Loan / Issuance' },
];

const mode = ref('registered');
const busy = ref(false);
const drafting = ref(false);
const errors = ref({});
const staleNotice = ref('');
const showSlip = ref(false);
const slipData = ref(null);
const slipMode = ref(null);
const purposes = [
    'Official Use (Off-site)',
    'Transfer / Relocation',
    'Repair / Maintenance',
    'Disposal',
];

const todayISO = () => new Date().toISOString().slice(0, 10);
const today = computed(todayISO);

function freeQuantity(item) {
    if (item.free_quantity !== undefined && item.free_quantity !== null) {
        return Math.max(0, Number(item.free_quantity));
    }

    return Math.max(0, Number(item.quantity ?? 0) - Number(item.pending_hold ?? 0));
}

const assignableItems = computed(() => props.items.filter((item) => freeQuantity(item) > 0));

const registered = reactive({ item_id: '', user_id: '', quantity: 1, transaction_date: todayISO() });

const manual = reactive({
    item_id: '',
    quantity: 1,
    manual_recipient_name: '',
    building: '',
    room: '',
    manual_recipient_type: '',
    manual_contact: '',
    manual_notes: '',
    transaction_date: todayISO(),
});

const manualItem = computed(() => props.items.find((item) => String(item.item_id) === String(manual.item_id)));
const registeredItem = computed(() => props.items.find((item) => String(item.item_id) === String(registered.item_id)));
const registeredFree = computed(() => registeredItem.value ? freeQuantity(registeredItem.value) : 0);
const draftRecipientName = computed(() => props.endUsers.find((person) => String(person.id) === String(registered.user_id))?.name ?? '');
const manualMode = computed(() => slipMode.value === 'manual');

const canDraftPreview = computed(() => {
    if (!registered.item_id || !registered.user_id) {
        return false;
    }
    const qty = Number(registered.quantity);
    if (!Number.isInteger(qty) || qty < 1 || qty > registeredFree.value) {
        return false;
    }
    return true;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            busy.value = false;
            drafting.value = false;
            staleNotice.value = '';
            showSlip.value = false;
            slipData.value = null;
            slipMode.value = null;
            registered.transaction_date = todayISO();
        }
    },
);

watch(assignableItems, (list) => {
    if (!props.open) {
        return;
    }
    const live = new Set(list.map((item) => String(item.item_id)));
    let cleared = false;
    if (registered.item_id !== '' && !live.has(String(registered.item_id))) {
        registered.item_id = '';
        cleared = true;
    }
    if (manual.item_id !== '' && !live.has(String(manual.item_id))) {
        manual.item_id = '';
        cleared = true;
    }
    if (cleared) {
        staleNotice.value = 'Previously available stock is now fully requested.';
    }
});

function resetAssignForm() {
    errors.value = {};
    staleNotice.value = '';
    showSlip.value = false;
    slipData.value = null;
    slipMode.value = null;
    registered.item_id = '';
    registered.user_id = '';
    registered.quantity = 1;
    registered.transaction_date = todayISO();
    manual.item_id = '';
    manual.quantity = 1;
    manual.manual_recipient_name = '';
    manual.building = '';
    manual.room = '';
    manual.manual_recipient_type = '';
    manual.manual_contact = '';
    manual.manual_notes = '';
    manual.transaction_date = todayISO();
}

function validateDraftStep1() {
    const stepErrors = {};
    if (!registered.item_id) {
        stepErrors.item_id = ['Select an available stock record.'];
    }
    if (!registered.user_id) {
        stepErrors.user_id = ['Select staff recipient.'];
    }
    const qty = Number(registered.quantity);
    if (!Number.isInteger(qty) || qty < 1) {
        stepErrors.quantity = ['Quantity must be at least 1.'];
    } else if (registeredItem.value && qty > registeredFree.value) {
        stepErrors.quantity = ['The requested quantity exceeds the free stock (available minus units already promised).'];
    }
    errors.value = stepErrors;
    return Object.keys(stepErrors).length === 0;
}

function buildDraftSlip() {
    const item = registeredItem.value ?? {};
    const qty = Number(registered.quantity);
    const unitCost = Number(item.unit_cost ?? 0);
    const line = {
        item_id: Number(registered.item_id),
        item_no: item.inventory_item_no ?? String(registered.item_id),
        description: [item.item_name ?? 'Unknown item', item.serial_number ? `(SN: ${item.serial_number})` : '']
            .filter(Boolean).join(' ').trim(),
        qty,
        unit_cost: unitCost,
        amount: unitCost * qty,
    };
    return {
        docNo: 'DRAFT-PREVIEW',
        documentDate: registered.transaction_date || todayISO(),
        recipientName: draftRecipientName.value,
        lines: [line],
    };
}

function openSlipPreview() {
    if (!validateDraftStep1()) {
        return;
    }
    slipMode.value = 'registered';
    slipData.value = buildDraftSlip();
    showSlip.value = true;
}

function openManualSlip() {
    const stepErrors = {};
    if (!manualItem.value) {
        stepErrors.item_id = ['Select an available stock record.'];
    }
    if (!manual.manual_recipient_name.trim()) {
        stepErrors.manual_recipient_name = ['Enter the recipient name.'];
    }
    const qty = Number(manual.quantity);
    const available = manualItem.value ? freeQuantity(manualItem.value) : 0;
    if (!Number.isInteger(qty) || qty < 1) {
        stepErrors.quantity = ['Quantity must be at least 1.'];
    } else if (qty > available) {
        stepErrors.quantity = ['The requested quantity exceeds the available stock.'];
    }
    errors.value = stepErrors;
    if (Object.keys(stepErrors).length > 0) {
        return;
    }

    const item = manualItem.value;
    const unitCost = Number(item.unit_cost ?? 0);
    slipMode.value = 'manual';
    slipData.value = {
        docNo: `MANUAL-${item.inventory_item_no ?? item.item_id}`,
        documentDate: manual.transaction_date || todayISO(),
        recipientName: manual.manual_recipient_name.trim(),
        lines: [{
            item_id: Number(manual.item_id),
            item_no: item.inventory_item_no ?? String(manual.item_id),
            description: [item.item_name ?? 'Unknown item', item.serial_number ? `(SN: ${item.serial_number})` : '']
                .filter(Boolean).join(' ').trim(),
            qty,
            unit_cost: unitCost,
            amount: unitCost * qty,
        }],
    };
    showSlip.value = true;
}

function backFromSlip() {
    showSlip.value = false;
}

function printSlip() {
    window.print();
}

function buildDraftPayload() {
    const payload = {
        item_id: Number(registered.item_id),
        user_id: Number(registered.user_id),
        quantity: Number(registered.quantity),
        transaction_date: registered.transaction_date || todayISO(),
    };
    return payload;
}

async function saveDraftFromSlip() {
    drafting.value = true;

    const payload = buildDraftPayload();

    try {
        const { data } = await api.post('/custodian/assignments/draft', payload, { skipToast: true });
        toast.success('Draft saved', data.message ?? 'Print the slip, then allocate once the signed slip is collected.');
        resetAssignForm();
        emit('drafted');
    } catch (requestError) {
        const fieldErrors = requestError?.response?.data?.errors ?? {};
        errors.value = fieldErrors;
        showSlip.value = false;
        if (Object.keys(fieldErrors).length === 0) {
            toast.error('Draft could not be saved', requestError?.response?.data?.message ?? 'The draft could not be saved.');
        }
    } finally {
        drafting.value = false;
    }
}

async function submitManual() {
    busy.value = true;
    errors.value = {};

    if (!manualItem.value) {
        errors.value = { item_id: ['Select an available stock record.'] };
        busy.value = false;
        showSlip.value = false;
        return;
    }

    const payload = {
        assignment_type: 'manual',
        item_id: Number(manual.item_id),
        category_id: Number(manualItem.value.category_id),
        unit: manualItem.value.unit,
        quantity: Number(manual.quantity),
        manual_recipient_name: manual.manual_recipient_name,
        transaction_date: manual.transaction_date,
    };
    for (const key of ['building', 'room', 'manual_recipient_type', 'manual_contact', 'manual_notes']) {
        if (manual[key]) {
            payload[key] = manual[key];
        }
    }

    try {
        await api.post('/custodian/transactions/assign', payload);
        resetAssignForm();
        emit('saved');
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            toast.error('Assignment failed', requestError?.response?.data?.message ?? 'The manual assignment could not be recorded.');
        }
        showSlip.value = false;
    } finally {
        busy.value = false;
    }
}

async function saveManualFromSlip(slipFields) {
    manual.manual_recipient_name = String(slipFields?.recipient_name ?? manual.manual_recipient_name).trim();
    await submitManual();
}
</script>
