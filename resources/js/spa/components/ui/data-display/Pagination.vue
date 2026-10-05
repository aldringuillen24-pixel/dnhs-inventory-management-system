<template>
  <div v-if="lastPage > 1" class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      Showing <span class="font-medium text-gray-800 dark:text-white/90">{{ from ?? 0 }}</span>
      to <span class="font-medium text-gray-800 dark:text-white/90">{{ to ?? 0 }}</span>
      of <span class="font-medium text-gray-800 dark:text-white/90">{{ total }}</span> results
    </p>
    <div class="flex items-center gap-1">
      <button
        v-for="page in window"
        :key="page"
        type="button"
        class="min-w-9 rounded-md px-2 py-1.5 text-sm font-medium"
        :class="
          page === currentPage
            ? 'bg-brand-500 text-white'
            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5'
        "
        :disabled="page === '…'"
        @click="page !== '…' && $emit('page', page)"
      >
        {{ page }}
      </button>
    </div>
  </div>
  <p v-else class="text-sm text-gray-500 dark:text-gray-400">
    Showing <span class="font-medium text-gray-800 dark:text-white/90">{{ total }}</span> results
  </p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    from: { type: Number, default: null },
    to: { type: Number, default: null },
    total: { type: Number, default: 0 },
});

defineEmits(['page']);

// Compact window: 1 … c-1 c c+1 … last
const window = computed(() => {
    const { currentPage: current, lastPage: last } = props;
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, 2, current - 1, current, current + 1, last - 1, last]);
    const sorted = [...pages].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);
    const result = [];
    let previous = 0;

    for (const page of sorted) {
        if (page - previous > 1) {
            result.push('…');
        }
        result.push(page);
        previous = page;
    }

    return result;
});
</script>
