<template>
  <div class="contents" @keydown.escape.window="onEscape">
    <Transition name="log-drawer">
      <aside
        v-if="open"
        id="item-logs-drawer"
        ref="asideRef"
        role="dialog"
        aria-label="Item logs"
        class="absolute inset-y-0 right-0 z-50 flex w-[min(100%,24rem)] flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950 xl:w-1/3"
        @keydown.tab="trapTab"
      >
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 p-4 dark:border-gray-800">
          <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Item logs</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">All inventory edits and deletions</p>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
            aria-label="Close item logs"
            @click="closeDrawer"
          >
            ✕
          </button>
        </div>

        <div ref="scrollRef" class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3" @scroll="onScroll">
          <div v-if="loading && !entries.length" class="space-y-2" aria-hidden="true">
            <div v-for="row in 4" :key="row" class="h-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>

          <p v-else-if="error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
            {{ error }}
            <button type="button" class="mt-1 block font-bold underline" @click="reload">Try again</button>
          </p>

          <p v-else-if="!entries.length" class="rounded-md border border-dashed border-gray-200 px-3 py-8 text-center text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
            No inventory edits or deletions have been recorded yet.
          </p>

          <article
            v-for="log in entries"
            :key="log.id"
            class="rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="min-w-0 flex-1 truncate text-xs font-bold text-gray-900 dark:text-white" :title="log.item_name">
                {{ log.item_name }}
                <span v-if="log.inventory_item_no" class="ml-1 font-mono font-normal text-[10px] text-gray-400">{{ log.inventory_item_no }}</span>
              </p>
              <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold" :class="actionClass(log.action)">
                {{ actionLabel(log.action) }}
              </span>
            </div>

            <dl class="mt-1.5 space-y-1 text-[11px]">
              <div v-for="line in describe(log)" :key="line.key" class="flex items-start justify-between gap-2">
                <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ line.label }}</dt>
                <dd class="min-w-0 break-words text-right font-medium text-gray-800 dark:text-gray-200" v-html="line.html"></dd>
              </div>
            </dl>

            <div class="mt-1.5 border-t border-gray-100 pt-1.5 text-[10px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <p>{{ actorName(log) }} · {{ formatWhen(log.created_at) }}</p>
              <p v-if="log.reason" class="mt-0.5 break-words italic">“{{ log.reason }}”</p>
            </div>
          </article>

          <p v-if="loadingMore" class="py-2 text-center text-xs text-gray-400">Loading more…</p>
          <p v-else-if="!hasMore && entries.length" class="py-2 text-center text-[10px] text-gray-400">End of logs for this view.</p>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/axios';

const props = defineProps({
  open: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const entries = ref([]);
const page = ref(1);
const hasMore = ref(false);
const loading = ref(false);
const loadingMore = ref(false);
const error = ref('');
const scrollRef = ref(null);
const asideRef = ref(null);

function onDocumentClick(event) {
  const target = event.target;
  if (target?.closest?.('[aria-controls="item-logs-drawer"]')) return;
  if (asideRef.value && !asideRef.value.contains(target)) {
    closeDrawer();
  }
}

function focusToggle() {
  const toggles = [...document.querySelectorAll('[aria-controls="item-logs-drawer"]')];
  const visible = toggles.find((el) => el.offsetParent !== null) ?? toggles[0];
  visible?.focus?.();
}

function closeDrawer() {
  focusToggle();
  emit('close');
}

function trapTab(event) {
  const root = asideRef.value;
  if (!root) return;
  const focusables = [...root.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
  )].filter((el) => !el.disabled && el.offsetParent !== null);
  if (!focusables.length) return;
  const first = focusables[0];
  const last = focusables[focusables.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function attachDismissListener() {
  document.addEventListener('click', onDocumentClick);
}

function detachDismissListener() {
  document.removeEventListener('click', onDocumentClick);
}

onUnmounted(() => {
  detachDismissListener();
});

function categoryName(id) {
  if (id == null || id === '') return '—';
  return props.categories.find((item) => String(item.category_id) === String(id))?.category_name ?? `#${id}`;
}

function escapeHtml(value) {
  return String(value ?? '—')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');
}

function formatValue(field, value) {
  if (value == null || value === '') return '—';
  if (field === 'category_id') return escapeHtml(categoryName(value));
  if (field === 'unit_cost') return escapeHtml(formatCurrency(value));
  return escapeHtml(value);
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount) || 0);
}

const FIELD_LABELS = {
  item_name: 'Name',
  category_id: 'Category',
  description: 'Description',
  ics_no: 'ICS no.',
  unit: 'Unit',
  unit_cost: 'Unit cost',
  date_acquired: 'Acquired',
  lifespan_years: 'Lifespan',
  building: 'Building',
  room: 'Room',
  serial_number: 'Serial',
  quantity: 'Qty',
  status: 'Status',
  category: 'Category',
};

function fieldLabel(field) {
  return FIELD_LABELS[field] ?? String(field).replace(/_/g, ' ');
}

function describe(log) {
  const changes = log.changes && typeof log.changes === 'object' ? log.changes : {};
  if (log.action === 'updated') {
    return Object.entries(changes).map(([field, pair]) => ({
      key: field,
      label: fieldLabel(field),
      html: `${formatValue(field, pair?.old)} <span class="text-gray-400">→</span> ${formatValue(field, pair?.new)}`,
    }));
  }
  const snapshotFields = ['quantity', 'unit_cost', 'unit', 'category', 'category_id', 'ics_no', 'status'];
  return snapshotFields
    .filter((field) => changes[field] !== undefined && changes[field] !== null && changes[field] !== '')
    .map((field) => ({ key: field, label: fieldLabel(field), html: formatValue(field, changes[field]) }));
}

function actionLabel(action) {
  return { created: 'Created', updated: 'Updated', deleted: 'Deleted', disposed: 'Disposed' }[action] ?? action;
}

function actionClass(action) {
  return {
    created: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    updated: 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    deleted: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    disposed: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
  }[action] ?? 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400';
}

function actorName(log) {
  const user = log.user;
  if (!user) return 'System';
  const name = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return name || 'System';
}

function formatWhen(value) {
  if (!value) return '';
  return new Date(value).toLocaleString();
}

function onEscape() {
  if (props.open) closeDrawer();
}

async function fetchPage(targetPage, { reset = false } = {}) {
  if (reset) {
    loading.value = true;
    error.value = '';
  } else {
    loadingMore.value = true;
  }
  try {
    const { data } = await api.get('/custodian/inventory-logs', {
      params: {
        page: targetPage,
      },
    });
    const logs = Array.isArray(data?.logs) ? data.logs : [];
    entries.value = reset ? logs : [...entries.value, ...logs];
    page.value = targetPage;
    hasMore.value = Boolean(data?.has_more);
  } catch (requestError) {
    if (reset) {
      error.value = requestError?.response?.data?.message ?? 'Could not load item logs. Check your connection and try again.';
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function reload() {
  page.value = 1;
  hasMore.value = false;
  if (scrollRef.value) scrollRef.value.scrollTop = 0;
  fetchPage(1, { reset: true });
}

function onScroll() {
  const el = scrollRef.value;
  if (!el || loading.value || loadingMore.value || !hasMore.value) return;
  if (el.scrollHeight - el.scrollTop - el.clientHeight < 200) {
    fetchPage(page.value + 1);
  }
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    nextTick(() => {
      reload();
      attachDismissListener();
    });
  } else {
    detachDismissListener();
  }
});

</script>

<style scoped>
.log-drawer-enter-active,
.log-drawer-leave-active {
  transition: transform 0.3s ease;
}
.log-drawer-enter-from,
.log-drawer-leave-to {
  transform: translateX(100%);
}
</style>
