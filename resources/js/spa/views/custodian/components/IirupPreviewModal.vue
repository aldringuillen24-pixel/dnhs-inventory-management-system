<template>
  <Modal
    :open="open"
    title="IIRUP Preview"
    max-width="max-w-[1000px]"
    @close="$emit('close')"
  >
    <div class="flex min-h-0 flex-col space-y-3">
      <div class="h-[65vh] max-h-[65vh] w-full overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
        <iframe
          v-if="pdfUrl"
          :src="pdfUrl"
          class="h-full w-full"
          title="IIRUP Preview"
        />
        <div v-else class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
          Loading preview…
        </div>
      </div>

    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
          @click="$emit('close')"
        >
          Close
        </button>
        <button
          type="button"
          class="rounded-md border border-purple-200 bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-purple-500/30 dark:hover:bg-purple-700/80"
          :disabled="!pdfUrl"
          @click="$emit('download')"
        >
          Download IIRUP PDF
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import Modal from '../../../components/ui/dialogs/Modal.vue';

defineProps({
  open: { type: Boolean, default: false },
  pdfUrl: { type: String, default: '' },
});

defineEmits(['close', 'download']);
</script>