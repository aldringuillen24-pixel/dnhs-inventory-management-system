<template>
  <Modal :open="open" title="Transfer Item" subtitle="Send units to another end user." max-width="max-w-md" @close="$emit('close')">
    <form v-if="row" class="flex flex-col gap-2.5" @submit.prevent="submit">
      <p class="rounded-md bg-gray-50 p-2.5 text-xs dark:bg-white/5">
        <span class="font-medium">{{ row.item_name }}</span>
        <span class="text-gray-500"> · up to {{ row.quantity }} units</span>
      </p>
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" :for="`transfer-qty-${rowKey}`">
          Quantity <span class="text-red-500">*</span>
        </label>
        <input
          :id="`transfer-qty-${rowKey}`"
          v-model.number="quantity"
          type="number"
          required
          min="1"
          :max="row.quantity"
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        />
        <FieldError :errors="errors" field="quantity" />
      </div>
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" :for="`transfer-user-${rowKey}`">
          Recipient <span class="text-red-500">*</span>
        </label>
        <select
          :id="`transfer-user-${rowKey}`"
          v-model="recipientId"
          required
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        >
          <option value="">Select an end user</option>
          <option v-for="person in endUsers" :key="person.id" :value="person.id">
            {{ person.first_name }} {{ person.last_name }} ({{ person.username }})
          </option>
        </select>
        <FieldError :errors="errors" field="transfer_user_id" />
        <p v-if="!endUsers.length" class="mt-1 text-[11px] text-gray-500">No other end users available.</p>
      </div>
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" :for="`transfer-notes-${rowKey}`">Notes (optional)</label>
        <textarea
          :id="`transfer-notes-${rowKey}`"
          v-model="notes"
          rows="2"
          maxlength="1000"
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        />
        <FieldError :errors="errors" field="notes" />
      </div>
      <div class="flex justify-end gap-2 pt-1.5">
        <button
          type="button"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Sending…' : 'Send Transfer' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    row: { type: Object, default: null },
    endUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const quantity = ref(1);
const recipientId = ref('');
const notes = ref('');
const busy = ref(false);
const errors = ref({});

const rowKey = computed(() => props.row?.item_id ?? props.row?.item_name ?? 'row');

watch(
    () => props.open,
    (open) => {
        if (open) {
            quantity.value = 1;
            recipientId.value = '';
            notes.value = '';
            errors.value = {};
            busy.value = false;
        }
    },
);

async function submit() {
    if (!props.row?.request?.id || !props.row?.item_id) {
        errors.value = { request_id: ['This record cannot be transferred.'] };
        return;
    }

    busy.value = true;
    errors.value = {};

    try {
        await api.post('/end-user/transfer', {
            request_id: props.row.request.id,
            transfer_user_id: Number(recipientId.value),
            item_id: props.row.item_id,
            quantity: Number(quantity.value),
            notes: notes.value || null,
        });
        emit('saved');
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
    } finally {
        busy.value = false;
    }
}
</script>
