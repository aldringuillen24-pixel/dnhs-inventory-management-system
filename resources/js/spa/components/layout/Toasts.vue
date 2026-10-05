<template>
  <div class="pointer-events-none fixed right-4 top-4 z-999999 flex w-80 flex-col gap-2">
    <div
      v-for="toast in toasts.toasts"
      :key="toast.id"
      class="pointer-events-auto flex items-start gap-3 rounded-md border bg-white p-3 shadow-lg"
      :class="toast.type === 'success' ? 'border-success-200' : 'border-error-200'"
    >
      <span
        class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white"
        :class="toast.type === 'success' ? 'bg-success-500' : 'bg-error-500'"
      >
        {{ toast.type === 'success' ? '✓' : '!' }}
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-800">{{ toast.title }}</p>
        <p class="truncate-2 text-sm text-gray-600">{{ toast.message }}</p>
      </div>
      <button
        type="button"
        class="text-gray-400 hover:text-gray-600"
        aria-label="Dismiss notification"
        @click="toasts.dismiss(toast.id)"
      >
        ✕
      </button>
    </div>
  </div>
</template>

<script setup>
import { useToastStore } from '../../stores/toast';

const toasts = useToastStore();
</script>

<style scoped>
.truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
