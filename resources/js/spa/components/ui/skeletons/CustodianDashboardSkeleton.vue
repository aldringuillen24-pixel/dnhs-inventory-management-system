<template>
  <div class="space-y-6" aria-label="Loading dashboard" aria-busy="true">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
      <MetricCardSkeleton v-for="index in 6" :key="`metric-${index}`" dense />
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <section
        v-for="chart in charts"
        :key="chart"
        class="rounded-md border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
      >
        <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-white/5">
          <div class="space-y-2">
            <div class="h-4 w-36 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
            <div class="h-3 w-48 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
          <div class="h-6 w-28 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
        </div>
        <div class="mt-4 flex h-[330px] items-end gap-3 px-2 pb-2">
          <div
            v-for="bar in 8"
            :key="bar"
            class="flex-1 animate-pulse rounded-md bg-gray-100 dark:bg-white/5"
            :style="{ height: `${24 + ((bar * 19) % 68)}%` }"
          />
        </div>
      </section>
    </div>

    <section class="rounded-md border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-white/5">
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          <div class="space-y-2">
            <div class="h-4 w-44 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
            <div class="h-3 w-64 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <div class="h-3 w-32 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
      </div>
      <div class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
        <div v-for="row in 5" :key="`activity-${row}`" class="flex items-center justify-between gap-4 px-2 py-3">
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <div class="h-10 w-10 shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="min-w-0 flex-1 space-y-2">
              <div class="flex items-center gap-2">
                <div class="h-3 w-1/3 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
                <div class="h-5 w-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              </div>
              <div class="h-3 w-3/4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </div>
          </div>
          <div class="h-6 w-14 shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import MetricCardSkeleton from './MetricCardSkeleton.vue';

const charts = ['category', 'request-status'];
</script>
