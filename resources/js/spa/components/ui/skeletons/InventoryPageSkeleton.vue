<template>
  <div class="space-y-6" aria-label="Loading inventory" aria-busy="true">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <MetricCardSkeleton v-for="index in 4" :key="index" />
    </div>

    <div class="overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex gap-2 overflow-hidden border-b border-gray-200/80 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
        <div v-for="tab in 5" :key="tab" class="h-8 w-24 shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
      </div>
      <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-white/5">
        <div class="h-9 w-full max-w-xs animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
        <div class="h-9 w-40 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
        <div class="ml-auto h-4 w-36 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
      </div>
      <InventoryTableSkeleton />
    </div>
  </div>
</template>

<script setup>
import InventoryTableSkeleton from './InventoryTableSkeleton.vue';
import MetricCardSkeleton from './MetricCardSkeleton.vue';
</script>
