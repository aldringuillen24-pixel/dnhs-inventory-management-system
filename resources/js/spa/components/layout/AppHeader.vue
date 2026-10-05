<template>
  <header class="sticky top-0 z-99999 flex w-full border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 xl:border-b">
    <div class="flex grow flex-col items-center justify-between xl:flex-row xl:px-4">
      <div class="flex w-full items-center justify-between gap-2 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:gap-3 xl:justify-normal xl:border-b-0 xl:px-0 xl:py-2.5">
        <button
          class="hidden h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 xl:flex"
          :class="{ 'bg-gray-100 dark:bg-white/[0.03]': !ui.isExpanded }"
          aria-label="Toggle Sidebar"
          @click="ui.toggleExpanded()"
        >
          <svg width="15" height="11" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              fill-rule="evenodd"
              clip-rule="evenodd"
              d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
              fill="currentColor"
            />
          </svg>
        </button>

        <button
          class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 dark:text-gray-400 xl:hidden"
          :class="{ 'bg-gray-100 dark:bg-white/[0.03]': ui.isMobileOpen }"
          aria-label="Toggle Mobile Menu"
          @click="ui.toggleMobileOpen()"
        >
          <svg width="15" height="11" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              fill-rule="evenodd"
              clip-rule="evenodd"
              d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
              fill="currentColor"
            />
          </svg>
        </button>

        <h1 class="flex-1 truncate text-center text-sm font-semibold text-gray-800 dark:text-white/90 xl:text-left">
          {{ title }}
        </h1>

        <div class="flex items-center gap-2">
          <button
            v-if="canUseAssistant"
            type="button"
            aria-label="Open AI assistant"
            title="AI assistant"
            class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50"
            @click="ui.setAiOpen(true)"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
          </button>

          <div class="relative" ref="menuRef">
            <button
              type="button"
              class="flex items-center gap-2 rounded-md border border-gray-200 px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-800"
              @click="open = !open"
              aria-label="User menu"
            >
              <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-500 text-xs font-semibold text-white">
                {{ initials }}
              </span>
              <span class="hidden max-w-32 truncate font-medium sm:block">{{ auth.displayName }}</span>
            </button>
            <div
              v-show="open"
              class="absolute right-0 mt-2 w-56 rounded-md border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
            >
              <p class="truncate px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ auth.role }}</p>
              <form method="POST" action="/logout">
                <input type="hidden" name="_token" :value="csrf" />
                <button
                  type="submit"
                  class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                >
                  Sign out
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';

const auth = useAuthStore();
const ui = useUiStore();
const route = useRoute();

// Same UX-only mirror as the AI panel: the server stays authoritative.
const canUseAssistant = computed(() => auth.role === 'Property Custodian');

const open = ref(false);
const menuRef = ref(null);
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const title = computed(() => route.meta.title ?? 'Workspace');
const initials = computed(() =>
  auth.displayName.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase() || '?',
);

function onClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>
