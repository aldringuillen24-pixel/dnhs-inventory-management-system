<template>
  <Modal :open="open" :title="title" :subtitle="subtitle" max-width="max-w-md" @close="$emit('cancel')">
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" :for="inputId">{{ label }}</label>
      <textarea
        :id="inputId"
        v-model="text"
        rows="3"
        :required="required"
        :maxlength="maxlength"
        :placeholder="placeholder"
        class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
      />
      <FieldError :errors="errors" field="notes" />
      <FieldError :errors="errors" field="cancellation_reason" />
    </div>
    <div class="mt-4 flex justify-end gap-3">
      <button
        type="button"
        class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
        @click="$emit('cancel')"
      >
        Back
      </button>
      <button
        type="button"
        class="rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        :class="danger ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-500 hover:bg-brand-600'"
        :disabled="busy || (required && !text.trim())"
        @click="$emit('submit', text)"
      >
        {{ busy ? 'Working…' : confirmLabel }}
      </button>
    </div>
  </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Modal from './Modal.vue';
import FieldError from '../feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    label: { type: String, default: 'Notes' },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    maxlength: { type: Number, default: 1000 },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
});

defineEmits(['cancel', 'submit']);

const text = ref('');
const inputId = `prompt-${Math.random().toString(36).slice(2)}`;

watch(
    () => props.open,
    (open) => {
        if (open) {
            text.value = '';
        }
    },
);
</script>
