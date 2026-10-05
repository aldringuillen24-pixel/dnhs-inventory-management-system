<template>
  <div
    v-if="!auth.isResolved"
    class="flex min-h-screen items-center justify-center bg-gray-50 px-6 dark:bg-gray-900"
    role="status"
    aria-live="polite"
    aria-busy="true"
  >
    <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
      <span class="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-600 dark:border-t-brand-400" aria-hidden="true" />
      <span>Loading workplace…</span>
    </div>
  </div>
  <div
    v-else-if="$route.meta.guest || $route.meta.onboarding"
    class="min-h-screen bg-[#f6f7f2] font-outfit"
  >
    <!-- Guest and onboarding routes render bare: no sidebar, header, or AI panel.
         An onboarding user has no workspace yet, so showing app navigation would
         offer links the router guard immediately redirects away from. -->
    <Toasts />
    <RouterView />
  </div>
  <div v-else class="min-h-screen bg-gray-50 font-outfit dark:bg-gray-900" :class="{ 'custodian-theme': auth.role === 'Property Custodian' }">
    <Toasts />
    <div class="min-h-screen xl:flex">
      <AppSidebar />
      <div
        class="min-w-0 flex-1 transition-all duration-300 ease-in-out"
        :class="{
          'xl:ml-[240px]': ui.isExpanded,
          'xl:ml-[90px]': !ui.isExpanded,
        }"
      >
        <AppHeader />
        <div class="mx-auto max-w-(--breakpoint-2xl) p-4 pb-24 md:p-6 md:pb-24 xl:pb-6">
          <RouterView />
        </div>
        <AiAssistantPanel />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useAuthStore } from './stores/auth';
import { useThemeStore } from './stores/theme';
import { useUiStore } from './stores/ui';
import AppSidebar from './components/layout/AppSidebar.vue';
import AppHeader from './components/layout/AppHeader.vue';
import Toasts from './components/layout/Toasts.vue';
import AiAssistantPanel from './components/ai/AiAssistantPanel.vue';

const auth = useAuthStore();
const theme = useThemeStore();
const ui = useUiStore();

function onResize() {
  ui.syncWithViewport();
}

onMounted(() => {
  theme.init();
  window.addEventListener('resize', onResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', onResize);
});
</script>
