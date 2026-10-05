<template>
  <div class="space-y-4">

    <!-- Page Header -->
    <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
            Administrator
          </div>
          <h1 class="mt-0.5 text-lg font-bold tracking-tight text-gray-900 dark:text-white">System Settings</h1>
          <p class="mt-0.5 max-w-2xl text-xs text-gray-500 dark:text-gray-400">
            Manage policies, diagnostics, and AI configuration.
          </p>
        </div>

        <button
          type="button"
          :disabled="pinging"
          class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-900"
          @click="pingAi"
        >
          <LucideIcon :icon="Wifi" class="h-4 w-4" />
          {{ pinging ? 'Checking…' : 'Ping AI Provider' }}
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <div class="flex items-center gap-2.5">
        <svg class="h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ error }}</span>
      </div>
      <button
        type="button"
        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <!-- Main Tabbed Card -->
    <div v-else class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">

      <!-- Ping Result Banner (inside card, above tabs) -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="pingResult"
          class="flex items-center gap-2 border-b border-emerald-100 bg-emerald-50 px-4 py-2 text-xs text-emerald-800 dark:border-emerald-800/30 dark:bg-emerald-950/20 dark:text-emerald-300"
        >
          <LucideIcon :icon="CheckCircle" class="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
          {{ pingResult }}
        </div>
      </transition>

      <!-- Tab Bar -->
      <div class="flex border-b border-gray-200 bg-gray-50/70 dark:border-white/5 dark:bg-white/[0.02]">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :aria-selected="activeTab === tab.key"
          :class="[
            'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-xs font-medium transition-colors focus:outline-none',
            activeTab === tab.key
              ? 'border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
          ]"
          @click="activeTab = tab.key"
        >
          <span
            :class="[
              'flex h-5 w-5 items-center justify-center rounded text-[10px]',
              activeTab === tab.key ? tab.activeIconClass : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400',
            ]"
          >
            <LucideIcon :icon="tab.icon" class="h-3 w-3" />
          </span>
          <span class="hidden sm:inline">{{ tab.label }}</span>
        </button>
      </div>

      <!-- Tab Panels -->
      <div class="p-4">
        <transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          mode="out-in"
        >

          <!-- Diagnostics Panel -->
          <div v-if="activeTab === 'diagnostics'" key="diagnostics" class="space-y-3.5">
            <dl class="grid gap-x-6 gap-y-0 sm:grid-cols-2">
              <div
                v-for="(value, key) in diagnosticsList"
                :key="key"
                class="flex items-center justify-between gap-2 border-b border-gray-100 py-2 last:border-0 dark:border-white/5"
              >
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ key }}</dt>
                <dd class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ value }}</dd>
              </div>
            </dl>

            <!-- Cache Controls -->
            <div class="rounded-md border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/[0.03]">
              <div class="mb-2.5 flex items-center gap-1.5">
                <LucideIcon :icon="Trash2" class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Cache Management</h3>
              </div>
              <form class="flex flex-wrap items-center gap-2" @submit.prevent="clearCache">
                <select
                  v-model="cacheType"
                  class="flex-1 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                  <option value="all">All caches</option>
                  <option value="config">Config</option>
                  <option value="routes">Routes</option>
                  <option value="views">Views</option>
                  <option value="optimize">Optimize</option>
                </select>
                <button
                  type="submit"
                  :disabled="working"
                  class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/10"
                >
                  <LucideIcon :icon="RefreshCw" class="h-3.5 w-3.5" />
                  {{ working ? 'Working…' : 'Clear Cache' }}
                </button>
              </form>
            </div>
          </div>

          <!-- Inventory Policy Panel -->
          <form v-else-if="activeTab === 'inventory'" key="inventory" @submit.prevent="saveSection('inventory', inventoryForm)">
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label for="low_stock_threshold" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Low stock threshold
                </label>
                <input
                  id="low_stock_threshold"
                  v-model.number="inventoryForm.low_stock_threshold"
                  type="number" min="1" max="1000" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <FieldError :errors="formErrors" field="low_stock_threshold" />
              </div>

              <div>
                <label for="default_lifespan_years" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Default lifespan (years)
                </label>
                <input
                  id="default_lifespan_years"
                  v-model.number="inventoryForm.default_lifespan_years"
                  type="number" min="1" max="30" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="return_due_days" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Return due (days)
                </label>
                <input
                  id="return_due_days"
                  v-model.number="inventoryForm.return_due_days"
                  type="number" min="1" max="365" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="auto_reminder_days" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Reminder (days before due)
                </label>
                <input
                  id="auto_reminder_days"
                  v-model.number="inventoryForm.auto_reminder_days"
                  type="number" min="1" max="30" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>
            </div>

            <div class="mt-4 flex justify-end border-t border-gray-100 pt-3.5 dark:border-white/5">
              <button
                type="submit"
                :disabled="working"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 disabled:opacity-50"
              >
                <LucideIcon :icon="Save" class="h-3.5 w-3.5" />
                {{ working ? 'Saving…' : 'Save Inventory Policy' }}
              </button>
            </div>
          </form>

          <!-- AI Configuration Panel -->
          <form v-else-if="activeTab === 'ai'" key="ai" @submit.prevent="saveSection('ai', aiForm)">
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label for="primary_model" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Primary model
                </label>
                <input
                  id="primary_model"
                  v-model="aiForm.primary_model"
                  required maxlength="100"
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 font-mono text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="fallback_model" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Fallback model
                </label>
                <input
                  id="fallback_model"
                  v-model="aiForm.fallback_model"
                  required maxlength="100"
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 font-mono text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="max_history_messages" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Max history messages
                </label>
                <input
                  id="max_history_messages"
                  v-model.number="aiForm.max_history_messages"
                  type="number" min="2" max="20" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">How many past messages the AI retains per session (2–20).</p>
              </div>
            </div>

            <div class="mt-4 flex justify-end border-t border-gray-100 pt-3.5 dark:border-white/5">
              <button
                type="submit"
                :disabled="working"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 disabled:opacity-50"
              >
                <LucideIcon :icon="Save" class="h-3.5 w-3.5" />
                {{ working ? 'Saving…' : 'Save AI Config' }}
              </button>
            </div>
          </form>

        </transition>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Activity, BrainCircuit, CheckCircle, PackageOpen, RefreshCw, Save, Trash2, Wifi } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';

const toast = useToastStore();
const working = ref(false);
const pinging = ref(false);
const error = ref('');
const pingResult = ref('');
const cacheType = ref('all');
const diagnostics = ref({});
const inventoryForm = reactive({ low_stock_threshold: 5, default_lifespan_years: 5, return_due_days: 14, auto_reminder_days: 3 });
const aiForm = reactive({ primary_model: '', fallback_model: '', max_history_messages: 6 });
const formErrors = ref({});

const activeTab = ref('diagnostics');

const tabs = [
  {
    key: 'diagnostics',
    label: 'Diagnostics',
    icon: Activity,
    activeIconClass: 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
  },
  {
    key: 'inventory',
    label: 'Inventory Policy',
    icon: PackageOpen,
    activeIconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
  },
  {
    key: 'ai',
    label: 'AI Config',
    icon: BrainCircuit,
    activeIconClass: 'bg-purple-100 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400',
  },
];

const diagnosticsList = computed(() => {
  const d = diagnostics.value ?? {};
  return {
    PHP: d.phpVersion ?? '—',
    Laravel: d.laravelVersion ?? '—',
    Environment: d.appEnv ?? '—',
    Database: d.dbConnected ? `Connected (${d.dbDriver ?? ''})` : 'Disconnected',
    Users: Number(d.totalUsers ?? 0).toLocaleString(),
    Inventory: Number(d.totalInventory ?? 0).toLocaleString(),
    'AI key set': d.geminiKeySet ? 'Yes' : 'No',
    'AI model': d.activeAiModel ?? '—',
  };
});

async function load(options = {}) {
  await loadCachedPage({
    page: 'admin-settings',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/admin/settings');
      return data;
    },
    applyData: (data) => {
      diagnostics.value = data.systemDiagnostics ?? {};
      Object.assign(inventoryForm, data.inventoryPolicy ?? {});
      Object.assign(aiForm, data.aiConfig ?? {});
    },
    onStart: () => {
      error.value = '';
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load settings.';
    },
  });
}

async function saveSection(section, form) {
  working.value = true;
  formErrors.value = {};
  try {
    await api.post('/admin/settings', { section, ...form });
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      formErrors.value = requestError?.response?.data?.errors ?? {};
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not save settings.');
    }
  } finally {
    working.value = false;
  }
}

async function clearCache() {
  working.value = true;
  try {
    await api.post('/admin/settings/clear-cache', { cache_type: cacheType.value });
  } finally {
    working.value = false;
  }
}

async function pingAi() {
  pinging.value = true;
  pingResult.value = '';
  try {
    const { data } = await api.post('/admin/settings/ping-ai');
    pingResult.value = data.message ?? (data.ok ? 'AI provider responded.' : 'AI provider did not respond.');
  } catch (requestError) {
    pingResult.value = requestError?.response?.data?.message ?? 'AI connectivity check failed.';
  } finally {
    pinging.value = false;
  }
}

onMounted(() => load());
</script>
