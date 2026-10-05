<template>
  <Modal :open="open" :title="title" :max-width="'max-w-md'" @close="$emit('cancel')">
    <p class="text-sm text-gray-600 dark:text-gray-400">{{ message }}</p>
    <div class="mt-6 flex justify-end gap-3">
      <button
        type="button"
        class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
        @click="$emit('cancel')"
      >
        Cancel
      </button>
      <button
        type="button"
        class="rounded-md bg-error-600 px-4 py-2 text-sm font-medium text-white hover:bg-error-700 disabled:opacity-50"
        :disabled="busy"
        @click="$emit('confirm')"
      >
        {{ busy ? 'Working…' : confirmLabel }}
      </button>
    </div>
  </Modal>
</template>

<script setup>
import Modal from './Modal.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: 'Delete' },
    busy: { type: Boolean, default: false },
});

defineEmits(['cancel', 'confirm']);
</script>
