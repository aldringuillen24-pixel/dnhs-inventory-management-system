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

          <div v-if="canReceiveNotifications" class="relative" ref="notificationRef">
            <button
              type="button"
              class="relative flex h-9 w-9 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800"
              aria-label="Notifications"
              :aria-expanded="notificationsOpen"
              @click="toggleNotifications"
            >
              <LucideIcon :icon="Bell" class="h-4 w-4" />
              <span
                v-if="unreadCount > 0"
                class="absolute -right-1 -top-1 min-w-4 rounded-full bg-rose-600 px-1 text-center text-[10px] font-semibold leading-4 text-white"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </button>
            <div
              v-show="notificationsOpen"
              class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-1.5rem)] rounded-md border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
            >
              <div class="flex items-center justify-between border-b border-gray-100 px-2 pb-2 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">Notifications</h2>
                <button type="button" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-white" @click="refreshNotifications">
                  Refresh
                </button>
              </div>
              <p v-if="notificationError" class="px-2 py-3 text-xs text-rose-600 dark:text-rose-400">
                {{ notificationError }}
              </p>
              <p v-else-if="notifications.length === 0" class="px-2 py-4 text-center text-xs text-gray-500 dark:text-gray-400">
                No notifications yet.
              </p>
              <div v-else class="max-h-96 overflow-y-auto py-1">
                <button
                  v-for="notification in notifications"
                  :key="notification.id"
                  type="button"
                  class="w-full rounded-md px-2 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                  @click="openNotification(notification)"
                >
                  <span class="flex items-start gap-2">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="notification.read_at ? 'bg-gray-300 dark:bg-gray-700' : 'bg-emerald-500'"></span>
                    <span class="min-w-0">
                      <span class="block text-xs font-semibold text-gray-800 dark:text-gray-200">{{ notification.title }}</span>
                      <span class="mt-0.5 block text-xs text-gray-600 dark:text-gray-400">{{ notification.message }}</span>
                      <span v-if="notification.created_at" class="mt-1 block text-[10px] text-gray-400">{{ formatNotificationDate(notification.created_at) }}</span>
                    </span>
                  </span>
                </button>
              </div>
            </div>
          </div>

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
import { useRoute, useRouter } from 'vue-router';
import { Bell } from 'lucide';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

const auth = useAuthStore();
const ui = useUiStore();
const route = useRoute();
const router = useRouter();

// Same UX-only mirror as the AI panel: the server stays authoritative.
const canUseAssistant = computed(() => auth.role === 'Property Custodian');
const canReceiveNotifications = computed(() => auth.isAuthenticated);

const open = ref(false);
const menuRef = ref(null);
const notificationsOpen = ref(false);
const notificationRef = ref(null);
const notifications = ref([]);
const unreadCount = ref(0);
const notificationError = ref('');
let notificationTimer;
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const title = computed(() => route.meta.title ?? 'Workspace');
const initials = computed(() =>
  auth.displayName.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase() || '?',
);

function onClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    open.value = false;
  }
  if (notificationRef.value && !notificationRef.value.contains(event.target)) {
    notificationsOpen.value = false;
  }
}

async function refreshNotifications() {
  notificationError.value = '';
  try {
    const { data } = await api.get('/notifications');
    notifications.value = data.notifications ?? [];
    unreadCount.value = Number(data.unread_count ?? 0);
  } catch (requestError) {
    notificationError.value = requestError?.response?.data?.message ?? 'Could not load notifications.';
  }
}

async function toggleNotifications() {
  notificationsOpen.value = !notificationsOpen.value;
  if (notificationsOpen.value) await refreshNotifications();
}

async function openNotification(notification) {
  notificationError.value = '';
  try {
    if (!notification.read_at) {
      await api.patch(`/notifications/${encodeURIComponent(notification.id)}/read`);
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    notificationsOpen.value = false;
    if (typeof notification.url === 'string' && notification.url.startsWith('/') && !notification.url.startsWith('//')) {
      await router.push(notification.url);
    }
  } catch (requestError) {
    notificationError.value = requestError?.response?.data?.message ?? 'Could not open this notification.';
  }
}

function formatNotificationDate(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
}

onMounted(() => {
  document.addEventListener('click', onClickOutside);
  if (canReceiveNotifications.value) {
    refreshNotifications();
    notificationTimer = window.setInterval(refreshNotifications, 60000);
  }
});
onUnmounted(() => {
  document.removeEventListener('click', onClickOutside);
  if (notificationTimer) window.clearInterval(notificationTimer);
});
</script>
