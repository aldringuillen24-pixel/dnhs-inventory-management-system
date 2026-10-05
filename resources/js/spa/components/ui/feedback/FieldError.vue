<template>
  <p v-if="message" class="mt-1 text-xs text-error-600 dark:text-error-400">{{ message }}</p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    field: { type: String, required: true },
});

const message = computed(() => {
    const entry = props.errors?.[props.field];
    if (Array.isArray(entry)) {
        return entry[0];
    }
    return entry ?? '';
});
</script>
