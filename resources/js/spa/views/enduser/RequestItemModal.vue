<template>
  <Modal :open="open" title="New Request" subtitle="Submit an item requisition to the property custodian" max-width="max-w-md" @close="$emit('close')">
    <form class="compact-modal-body" @submit.prevent="submit">
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-item-type">Item Type</label>
        <select
          id="request-item-type"
          v-model="selectedIndex"
          required
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        >
          <option value="">Select an item</option>
          <option v-for="(item, index) in items" :key="`${item.item_name}-${item.category_id}-${item.unit}`" :value="index">
            {{ item.item_name }} · {{ item.category_name }} · {{ item.quantity }} {{ item.unit }} available
          </option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-quantity">Quantity</label>
        <input
          id="request-quantity"
          v-model.number="quantity"
          type="number"
          min="1"
          required
          placeholder="Enter quantity"
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
        />
        <FieldError :errors="errors" field="quantity" />
        <FieldError :errors="errors" field="item_name" />
      </div>
      <div>
        <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-notes">Purpose or notes <span class="font-normal text-gray-400">(optional)</span></label>
        <textarea
          id="request-notes"
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
          {{ busy ? 'Submitting…' : 'Submit Request' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const selectedIndex = ref('');
const quantity = ref(1);
const notes = ref('');
const busy = ref(false);
const errors = ref({});

watch(
    () => props.open,
    (open) => {
        if (open) {
            selectedIndex.value = '';
            quantity.value = 1;
            notes.value = '';
            errors.value = {};
            busy.value = false;
        }
    },
);

async function submit() {
    const item = props.items[Number(selectedIndex.value)];
    if (!item) {
        errors.value = { item_name: ['Select an item.'] };
        return;
    }

    busy.value = true;
    errors.value = {};

    try {
        await api.post('/end-user/requests', {
            item_name: item.item_name,
            category_id: Number(item.category_id),
            unit: item.unit,
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
