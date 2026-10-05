<template>
  <svg
    v-bind="$attrs"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <component
      :is="element[0]"
      v-for="(element, index) in icon"
      :key="index"
      v-bind="element[1]"
    />
  </svg>
</template>

<script setup>
defineProps({
  icon: { type: Array, required: true },
});
</script>
