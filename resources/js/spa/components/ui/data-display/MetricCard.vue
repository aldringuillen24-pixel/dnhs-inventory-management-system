<template>
  <div
    class="custodian-metric group relative overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
    :class="[accentClass, mini ? 'p-1.5 min-[480px]:p-2' : micro ? 'p-2 min-[480px]:p-2.5' : dense ? 'p-2 min-[480px]:p-3' : compact ? 'p-4' : 'p-5']"
  >
    <!-- Subtle color sheen on hover -->
    <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-md bg-current opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-5" />

    <div class="flex items-center justify-between gap-1.5 min-[480px]:gap-2">
      <div class="min-w-0">
        <p
          class="truncate font-semibold text-gray-500 dark:text-gray-400"
          :class="mini ? 'text-[10px] tracking-wide' : micro ? 'text-[10px] tracking-wide min-[480px]:text-[11px]' : dense ? 'text-[11px] tracking-normal min-[480px]:text-xs min-[480px]:uppercase min-[480px]:tracking-wider' : 'text-xs uppercase tracking-wider'"
        >
          {{ title }}
        </p>
        <div v-if="loading" class="mt-1.5 space-y-2" aria-hidden="true">
          <div class="h-5 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          <div class="h-3 w-24 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
        </div>
        <template v-else>
          <p class="font-bold tracking-tight text-gray-800 dark:text-white/95" :class="mini ? 'mt-0.5 text-sm' : micro ? 'mt-0.5 text-base min-[480px]:text-lg' : dense ? 'mt-1 text-lg min-[480px]:text-xl' : compact ? 'mt-1 text-xl' : 'mt-1.5 text-2xl'">{{ value }}</p>
          <div class="flex items-center gap-1.5" :class="compact ? 'mt-1' : 'mt-1.5'">
            <slot name="badge" />
            <p v-if="subtitle" class="line-clamp-2 text-[10px] leading-tight text-gray-400 dark:text-gray-500 min-[480px]:truncate min-[480px]:text-xs min-[480px]:leading-normal">{{ subtitle }}</p>
          </div>
        </template>
      </div>
      <div
        v-if="$slots.icon || iconClass"
        class="flex shrink-0 items-center justify-center rounded-md transition-transform duration-200 group-hover:scale-105"
        :class="[mini ? 'h-6 w-6' : micro ? 'h-7 w-7' : dense ? 'h-7 w-7 min-[480px]:h-8 min-[480px]:w-8' : compact ? 'h-10 w-10' : 'h-12 w-12', iconClass || 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300']"
      >
        <slot name="icon" />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, default: '' },
    iconClass: { type: String, default: '' },
    accentClass: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    dense: { type: Boolean, default: false },
    micro: { type: Boolean, default: false },
    mini: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});
</script>
