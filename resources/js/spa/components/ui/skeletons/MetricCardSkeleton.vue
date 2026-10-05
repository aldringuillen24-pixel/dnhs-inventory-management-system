<template>
  <div
    aria-hidden="true"
    class="flex min-h-24 items-center justify-between rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    :class="dense ? 'gap-2 p-3' : 'gap-3 p-4'"
  >
    <div class="w-full space-y-3">
      <div class="h-3 w-2/5 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
      <div class="h-6 w-1/4 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
      <div class="h-3 w-3/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
    </div>
    <div class="shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="dense ? 'h-8 w-8' : 'h-10 w-10'" />
  </div>
</template>

<script setup>
defineProps({
  dense: { type: Boolean, default: false },
});
</script>
