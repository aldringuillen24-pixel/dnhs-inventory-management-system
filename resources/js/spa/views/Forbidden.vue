<template>
  <div class="mx-auto max-w-lg py-24 text-center">
    <p class="text-5xl font-bold text-gray-300 dark:text-gray-700">403</p>
    <h1 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white/90">Not authorized</h1>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
      Your {{ auth.role ?? 'current' }} account cannot open this workspace.
    </p>
    <RouterLink
      :to="auth.homePath"
      class="mt-6 inline-block rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
    >
      Back to my workspace
    </RouterLink>
  </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
</script>
