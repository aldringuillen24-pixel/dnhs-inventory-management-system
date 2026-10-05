<template>
  <span
    class="inline-flex items-center rounded-md text-xs font-medium transition-colors"
    :class="[classes, compact ? 'gap-1 px-1.5 py-0.5 text-[10px] min-[480px]:gap-1.5 min-[480px]:px-2.5 min-[480px]:py-1 min-[480px]:text-xs' : 'gap-1.5 px-2.5 py-1']"
  >
    <span class="rounded-md" :class="[dotClass, compact ? 'h-1 w-1 min-[480px]:h-1.5 min-[480px]:w-1.5' : 'h-1.5 w-1.5']" />
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const label = computed(() => {
    const raw = (props.status ?? '').trim();
    if (!raw) return 'Unknown';
    return raw
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
});

const statusKey = computed(() => (props.status ?? '').toLowerCase().trim());

const classes = computed(() => {
    switch (statusKey.value) {
        case 'available':
        case 'approved':
        case 'accepted':
        case 'completed':
        case 'active':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/20';
        case 'assigned':
            return 'bg-indigo-50 text-indigo-700 border border-indigo-200/60 dark:bg-indigo-500/10 dark:text-indigo-300 dark:border-indigo-500/20';
        case 'under_maintenance':
            return 'bg-amber-50 text-amber-700 border border-amber-200/60 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20';
        case 'under_inspection':
            return 'bg-purple-50 text-purple-700 border border-purple-200/60 dark:bg-purple-500/10 dark:text-purple-300 dark:border-purple-500/20';
        case 'ready_to_dispose':
            return 'bg-orange-50 text-orange-700 border border-orange-200/60 dark:bg-orange-500/10 dark:text-orange-300 dark:border-orange-500/20';
        case 'waiting for approval':
        case 'waiting for transfer approval':
        case 'waiting for custodian approval':
        case 'pending':
            return 'bg-amber-50 text-amber-700 border border-amber-200/60 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20';
        case 'disposed':
        case 'declined':
        case 'cancelled':
        case 'canceled':
        case 'inactive':
        case 'lost':
        case 'damaged':
            return 'bg-rose-50 text-rose-700 border border-rose-200/60 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20';
        case 'transferred':
            return 'bg-cyan-50 text-cyan-700 border border-cyan-200/60 dark:bg-cyan-500/10 dark:text-cyan-300 dark:border-cyan-500/20';
        default:
            return 'bg-gray-100 text-gray-700 border border-gray-200 dark:bg-white/5 dark:text-gray-300 dark:border-white/10';
    }
});

const dotClass = computed(() => {
    switch (statusKey.value) {
        case 'available':
        case 'approved':
        case 'accepted':
        case 'completed':
        case 'active':
            return 'bg-emerald-500';
        case 'assigned':
            return 'bg-indigo-500';
        case 'under_maintenance':
            return 'bg-amber-500';
        case 'under_inspection':
            return 'bg-purple-500';
        case 'ready_to_dispose':
            return 'bg-orange-500';
        case 'waiting for approval':
        case 'waiting for transfer approval':
        case 'waiting for custodian approval':
        case 'pending':
            return 'bg-amber-500 animate-pulse';
        case 'disposed':
        case 'declined':
        case 'cancelled':
        case 'canceled':
        case 'inactive':
        case 'lost':
        case 'damaged':
            return 'bg-rose-500';
        case 'transferred':
            return 'bg-cyan-500';
        default:
            return 'bg-gray-400';
    }
});
</script>
