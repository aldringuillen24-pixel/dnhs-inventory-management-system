<template>
  <Modal
    :open="open"
    title="Manage Categories"
    subtitle="Categories apply instantly to every stock-in row once saved."
    max-width="max-w-4xl"
    z-class="z-[100001]"
    :close-on-escape="!formOpen"
    @close="$emit('close')"
  >
    <div class="space-y-3">
      <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
        <input
          v-model="search"
          type="text"
          placeholder="Search categories…"
          aria-label="Search categories"
          class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 sm:max-w-xs"
        />
        <button
          type="button"
          class="inline-flex items-center gap-1.5 self-start rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 sm:self-auto"
          @click="openNew"
        >
          <span class="text-base leading-none">+</span> New
        </button>
      </div>

      <p v-if="error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
        {{ error }}
      </p>
      <p v-if="notice" role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200">
        {{ notice }}
      </p>

      <div class="max-h-[46vh] min-h-40 overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
        <table class="w-max min-w-full border-collapse text-left text-xs">
          <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            <tr>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Name</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Lifespan</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Serial</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">QR</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Repair</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Items</th>
              <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
            <tr v-if="loading && !list.length">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Loading categories…</td>
            </tr>
            <tr v-else-if="!filtered.length">
              <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ search ? 'No categories match this search.' : 'No categories yet. Create the first one.' }}
              </td>
            </tr>
            <tr v-for="category in filtered" :key="category.category_id">
              <td class="whitespace-nowrap px-3 py-2 font-semibold text-gray-800 dark:text-gray-200">{{ category.category_name }}</td>
              <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400">
                {{ category.default_lifespan_years != null ? `${category.default_lifespan_years} yrs` : '—' }}
              </td>
              <td class="px-3 py-2">
                <span :class="badgeClass(category.requires_serial_number)">{{ category.requires_serial_number ? 'Yes' : '—' }}</span>
              </td>
              <td class="px-3 py-2">
                <span :class="badgeClass(category.requires_qr_code)">{{ category.requires_qr_code ? 'Yes' : '—' }}</span>
              </td>
              <td class="px-3 py-2">
                <span :class="badgeClass(category.is_maintenance_eligible)">{{ category.is_maintenance_eligible ? 'Yes' : '—' }}</span>
              </td>
              <td class="whitespace-nowrap px-3 py-2 text-right font-semibold text-gray-800 dark:text-gray-200">
                {{ category.items_count ?? 0 }}
              </td>
              <td class="whitespace-nowrap px-3 py-2">
                <div class="flex items-center justify-end gap-1">
                  <button
                    type="button"
                    class="rounded px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                    @click="openEdit(category)"
                  >
                    Edit
                  </button>
                  <button
                    v-if="pendingDeleteId === category.category_id"
                    type="button"
                    :disabled="deleting"
                    class="rounded px-2 py-1 text-xs font-bold text-white disabled:opacity-50"
                    :class="deleting ? 'bg-rose-400' : 'bg-rose-600 hover:bg-rose-700'"
                    @click="confirmDelete(category)"
                  >
                    {{ deleting ? 'Deleting…' : 'Confirm?' }}
                  </button>
                  <button
                    v-else
                    type="button"
                    :disabled="(category.items_count ?? 0) > 0"
                    :title="(category.items_count ?? 0) > 0 ? `In use by ${category.items_count} inventoried item(s)` : 'Delete category'"
                    class="rounded px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-rose-300 dark:hover:bg-rose-500/10"
                    @click="pendingDeleteId = category.category_id"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="deleteError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
        {{ deleteError }}
      </p>
    </div>

    <CategoryFormModal
      :open="formOpen"
      :category="editing"
      @close="formOpen = false"
      @saved="onFormSaved"
    />
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import CategoryFormModal from './CategoryFormModal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

function badgeClass(on) {
  return [
    'inline-block rounded px-1.5 py-0.5 text-[10px] font-bold',
    on
      ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
      : 'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500',
  ];
}

const list = ref([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const search = ref('');
const formOpen = ref(false);
const editing = ref(null);
const pendingDeleteId = ref(null);
const deleting = ref(false);
const deleteError = ref('');

const filtered = computed(() => {
  const needle = search.value.trim().toLowerCase();
  if (!needle) return list.value;
  return list.value.filter((category) =>
    String(category.category_name ?? '').toLowerCase().includes(needle),
  );
});

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  error.value = '';
  notice.value = '';
  deleteError.value = '';
  pendingDeleteId.value = null;
  fetchList();
});

async function fetchList() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/custodian/categories');
    list.value = Array.isArray(data?.categories) ? data.categories : [];
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not load categories. Check your connection and try again.';
  } finally {
    loading.value = false;
  }
}

function openNew() {
  editing.value = null;
  deleteError.value = '';
  formOpen.value = true;
}

function openEdit(category) {
  editing.value = { ...category };
  deleteError.value = '';
  formOpen.value = true;
}

async function onFormSaved(savedCategory) {
  formOpen.value = false;
  await fetchList();
  notice.value = '';
  emit('saved', { categories: list.value, category: savedCategory ?? null });
}

async function confirmDelete(category) {
  if (deleting.value) return;
  deleting.value = true;
  deleteError.value = '';
  try {
    await api.delete(`/custodian/categories/${category.category_id}`, { skipToast: true });
    pendingDeleteId.value = null;
    await fetchList();
    notice.value = `Category '${category.category_name}' deleted.`;
    emit('saved', { categories: list.value, category: null });
  } catch (requestError) {
    if (requestError?.response?.status === 409) {
      deleteError.value = requestError.response.data?.message
        ?? `'${category.category_name}' is in use and cannot be deleted.`;
    } else {
      deleteError.value = requestError?.response?.data?.message ?? 'Could not delete this category. Check your connection and try again.';
    }
  } finally {
    deleting.value = false;
  }
}
</script>
