<template>
  <Modal :open="open" title="Assign Stockroom Item" subtitle="Issue equipment to a registered school staff member or record a manual loan." max-width="max-w-2xl" @close="$emit('close')">
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
    <form v-if="mode === 'registered'" class="space-y-4" @submit.prevent="submitRegistered">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-item">
            Available Inventory Item <span class="text-rose-500">*</span>
          </label>
          <select
            id="assign-item"
            v-model="registered.item_id"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select available stock to issue</option>
            <option v-for="item in items" :key="item.item_id" :value="item.item_id">
              {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ item.quantity }} {{ item.unit }} available)
            </option>
          </select>
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
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="quantity" />
        </div>

        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-date">
            Assignment Date
          </label>
          <input
            id="assign-date"
            v-model="registered.transaction_date"
            type="date"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 sm:max-w-xs"
          />
          <FieldError :errors="errors" field="transaction_date" />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 dark:border-white/5">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Submitting…' : 'Issue for User Acceptance' }}
        </button>
      </div>
    </form>

    <!-- 2. Manual Issue Mode -->
    <form v-else class="space-y-4" @submit.prevent="submitManual">
      <div class="grid gap-3.5 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-item">
            Available Inventory Item <span class="text-rose-500">*</span>
          </label>
          <select
            id="manual-item"
            v-model="manual.item_id"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select available stock to issue</option>
            <option v-for="item in items" :key="item.item_id" :value="item.item_id">
              {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ item.quantity }} {{ item.unit }} available)
            </option>
          </select>
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
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-dept">
            Department / Office <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-dept"
            v-model="manual.manual_department"
            type="text"
            required
            maxlength="255"
            placeholder="e.g. Science Department"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="manual_department" />
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

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-return">
            Expected Return Date
          </label>
          <input
            id="manual-return"
            v-model="manual.expected_return_date"
            type="date"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="expected_return_date" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-type">
            Recipient Designation (optional)
          </label>
          <input
            id="manual-type"
            v-model="manual.manual_recipient_type"
            type="text"
            maxlength="100"
            placeholder="e.g. Teacher, Laboratory Head"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-building">
            Location Building
          </label>
          <input
            id="manual-building"
            v-model="manual.building"
            type="text"
            maxlength="255"
            placeholder="e.g. Science Laboratory"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-room">
            Room
          </label>
          <input
            id="manual-room"
            v-model="manual.room"
            type="text"
            maxlength="255"
            placeholder="e.g. Room 101"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>

        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-notes">
            Purpose / Notes
          </label>
          <input
            id="manual-notes"
            v-model="manual.manual_notes"
            type="text"
            maxlength="1000"
            placeholder="Reason for issuance, class use, etc…"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-3 pt-3 border-t border-gray-100 dark:border-white/5">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Saving…' : 'Record Manual Assignment' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    endUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const tabs = [
    { key: 'registered', label: 'Registered Staff Member' },
    { key: 'manual', label: 'Manual Loan / Issuance' },
];

const mode = ref('registered');
const busy = ref(false);
const errors = ref({});

const registered = reactive({ item_id: '', user_id: '', quantity: 1, transaction_date: '' });
const manual = reactive({
    item_id: '',
    quantity: 1,
    manual_recipient_name: '',
    manual_department: '',
    building: '',
    room: '',
    manual_recipient_type: '',
    manual_contact: '',
    manual_notes: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    expected_return_date: '',
});

const manualItem = computed(() => props.items.find((item) => String(item.item_id) === String(manual.item_id)));

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            busy.value = false;
        }
    },
);

function resetAssignForm() {
    errors.value = {};
    registered.item_id = '';
    registered.user_id = '';
    registered.quantity = 1;
    registered.transaction_date = '';
    manual.item_id = '';
    manual.quantity = 1;
    manual.manual_recipient_name = '';
    manual.manual_department = '';
    manual.building = '';
    manual.room = '';
    manual.manual_recipient_type = '';
    manual.manual_contact = '';
    manual.manual_notes = '';
    manual.transaction_date = new Date().toISOString().slice(0, 10);
    manual.expected_return_date = '';
}

async function submitRegistered() {
    busy.value = true;
    errors.value = {};

    const payload = {
        item_id: Number(registered.item_id),
        user_id: Number(registered.user_id),
        quantity: Number(registered.quantity),
    };
    if (registered.transaction_date) {
        payload.transaction_date = registered.transaction_date;
    }

    try {
        await api.post('/custodian/transactions/assign', payload);
        resetAssignForm();
        emit('saved');
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
    } finally {
        busy.value = false;
    }
}

async function submitManual() {
    busy.value = true;
    errors.value = {};

    if (!manualItem.value) {
        errors.value = { item_id: ['Select an available stock record.'] };
        busy.value = false;
        return;
    }

    const payload = {
        assignment_type: 'manual',
        item_id: Number(manual.item_id),
        category_id: Number(manualItem.value.category_id),
        unit: manualItem.value.unit,
        quantity: Number(manual.quantity),
        manual_recipient_name: manual.manual_recipient_name,
        manual_department: manual.manual_department,
        transaction_date: manual.transaction_date,
    };
    for (const key of ['building', 'room', 'manual_recipient_type', 'manual_contact', 'manual_notes', 'expected_return_date']) {
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
    } finally {
        busy.value = false;
    }
}
</script>
