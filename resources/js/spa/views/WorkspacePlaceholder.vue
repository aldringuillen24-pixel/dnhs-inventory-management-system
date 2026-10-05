<template>
  <div class="mx-auto max-w-2xl py-16">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">New app shell</p>
    <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white/90">This workspace hasn't moved yet</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
      You're signed in as <span class="font-medium">{{ auth.displayName }}</span> ({{ auth.role }}).
      Workspaces move into this app one at a time; yours still lives in the classic view for now.
    </p>
    <div class="mt-6 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Continue in the classic view</p>
      <ul class="mt-3 space-y-1">
        <li v-for="item in classicItems" :key="item.name">
          <a
            :href="item.classic"
            class="menu-dropdown-item menu-dropdown-item-inactive"
          >
            <span class="menu-dropdown-item-inactive" v-html="icon(item.icon)" />
            {{ item.name }}
          </a>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { ICONS, menuForRole } from '../config/menu';

const auth = useAuthStore();

const classicItems = computed(() =>
  menuForRole(auth.role).flatMap((group) => group.items.filter((item) => item.classic)),
);

function icon(name) {
  return ICONS[name] ?? ICONS.dashboard;
}
</script>
