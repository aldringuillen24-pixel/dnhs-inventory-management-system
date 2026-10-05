<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Audit Logs</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Account and role changes, newest first.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <table class="w-full min-w-[52rem] text-left text-xs">
        <thead>
          <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
            <th class="px-4 py-3.5">Date</th>
            <th class="px-4 py-3.5">Action</th>
            <th class="px-4 py-3.5">Actor</th>
            <th class="px-4 py-3.5">Target</th>
          </tr>
        </thead>
        <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
          <tr v-for="row in 6" :key="row">
            <td v-for="column in 4" :key="column" class="px-4 py-3.5">
              <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'w-3/4'" />
            </td>
          </tr>
        </tbody>
        <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
            <td class="px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ label(log.action) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.actor_name ?? actorName(log.actor) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.target_name ?? actorName(log.target_user) }}</td>
          </tr>
          <tr v-if="!loading && !logs.length">
            <td colspan="4" class="px-4 py-8 text-center text-xs text-gray-500">No audit logs.</td>
          </tr>
        </tbody>
      </table>
      <div class="flex items-center justify-end border-t border-gray-200 px-4 py-3 dark:border-gray-800">
        <Pagination :current-page="paginator.current_page ?? 1" :last-page="paginator.last_page ?? 1" :from="paginator.from" :to="paginator.to" :total="paginator.total ?? 0" @page="goToPage" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import Pagination from '../../components/ui/data-display/Pagination.vue';

const loading = ref(true);
const error = ref('');
const logs = ref([]);
const paginator = ref({ data: [] });
const page = ref(1);

function label(action) {
  return String(action ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—';
}

function actorName(user) {
  if (!user) return '—';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || '—';
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

async function load(options = {}) {
  const params = { page: page.value };
  await loadCachedPage({
    page: 'school-head-audit-logs',
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/audit-logs', { params: { page: params.page } });
      return data;
    },
    applyData: (data) => {
      const logPage = data.logs ?? {};
      logs.value = logPage.data ?? [];
      paginator.value = logPage;
    },
    isCurrent: () => page.value === params.page,
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load audit logs.';
    },
  });
}

function goToPage(next) {
  page.value = next;
  load();
}

onMounted(() => load());
</script>
