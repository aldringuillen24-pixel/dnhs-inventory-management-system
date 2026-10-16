<template>
  <div ref="rootRef" class="relative" @keydown="onKeydown">
    <button
      ref="buttonRef"
      type="button"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-label="ariaLabel"
      :class="[
        'flex w-40 items-center justify-between gap-1 rounded border bg-white px-2 py-1.5 text-left text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:bg-gray-800 dark:text-gray-100',
        invalid
          ? 'border-rose-300 dark:border-rose-700'
          : 'border-gray-200 dark:border-gray-700',
      ]"
      @click="toggle"
    >
      <span class="flex min-w-0 flex-1 items-center gap-1">
        <span class="truncate" :class="selected ? '' : 'text-gray-400'">
          {{ selected ? selected.category_name : 'Select category' }}
        </span>
        <span v-if="selected?.requires_qr_code" class="shrink-0 rounded bg-emerald-50 px-1 text-[9px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">QR</span>
        <span v-if="selected?.requires_serial_number" class="shrink-0 rounded bg-amber-50 px-1 text-[9px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Serial</span>
      </span>
      <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="popoverRef"
        role="listbox"
        :aria-label="ariaLabel"
        class="fixed z-[100000] flex max-h-[19rem] min-w-56 flex-col overflow-hidden rounded-md border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
        :style="popoverStyle"
      >
        <div class="shrink-0 border-b border-gray-100 p-2 dark:border-gray-800">
          <input
            ref="searchRef"
            v-model="search"
            type="text"
            placeholder="Search categories…"
            aria-label="Search categories"
            class="block w-full rounded border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
        </div>
        <ul class="max-h-56 min-h-0 flex-1 overflow-auto p-1">
          <li v-if="!filtered.length" class="px-2 py-4 text-center text-xs text-gray-400">
            {{ categories.length ? 'No matching categories.' : 'No categories yet.' }}
          </li>
          <li v-for="category in filtered" :key="category.category_id">
            <button
              type="button"
              role="option"
              :aria-selected="String(category.category_id) === String(modelValue)"
              class="flex w-full items-center justify-between gap-2 rounded px-2 py-1.5 text-left text-xs hover:bg-emerald-50 dark:hover:bg-emerald-500/10"
              :class="String(category.category_id) === String(modelValue)
                ? 'font-semibold text-emerald-800 dark:text-emerald-200'
                : 'text-gray-700 dark:text-gray-300'"
              @click="choose(category)"
            >
              <span class="min-w-0 flex-1 truncate">{{ category.category_name }}</span>
              <span class="flex shrink-0 items-center gap-1">
                <span v-if="category.requires_qr_code" class="rounded bg-emerald-50 px-1 text-[9px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">QR</span>
                <span v-if="category.requires_serial_number" class="rounded bg-amber-50 px-1 text-[9px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Serial</span>
              </span>
            </button>
          </li>
        </ul>
        <div class="shrink-0 border-t border-gray-100 p-1 dark:border-gray-800">
          <button
            type="button"
            class="w-full rounded px-2 py-2 text-center text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
            @click="onManage"
          >
            Manage categories
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue';

const props = defineProps({
  modelValue: { type: [Number, String], default: '' },
  categories: { type: Array, default: () => [] },
  invalid: { type: Boolean, default: false },
  ariaLabel: { type: String, default: 'Category' },
});

const emit = defineEmits(['update:modelValue', 'manage']);

const open = ref(false);
const search = ref('');
const rootRef = ref(null);
const buttonRef = ref(null);
const popoverRef = ref(null);
const searchRef = ref(null);
const popoverStyle = ref({});

const selected = computed(() =>
  props.categories.find((category) => String(category.category_id) === String(props.modelValue)) ?? null,
);

const filtered = computed(() => {
  const needle = search.value.trim().toLowerCase();
  if (!needle) return props.categories;
  return props.categories.filter((category) =>
    String(category.category_name ?? '').toLowerCase().includes(needle),
  );
});

function position() {
  const button = buttonRef.value;
  if (!button) return;
  const rect = button.getBoundingClientRect();
  const width = Math.min(Math.max(rect.width, 224), window.innerWidth * 0.92);
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
  const below = window.innerHeight - rect.bottom;
  // Rough popover height; the flex column caps itself with max-h.
  const height = 304;
  const top = below >= height || below >= rect.top ? rect.bottom + 4 : Math.max(8, rect.top - height - 4);
  popoverStyle.value = { top: `${top}px`, left: `${left}px`, width: `${width}px` };
}

function attachGlobalListeners() {
  document.addEventListener('click', onDocumentClick, true);
  window.addEventListener('scroll', position, true);
  window.addEventListener('resize', position);
}

function detachGlobalListeners() {
  document.removeEventListener('click', onDocumentClick, true);
  window.removeEventListener('scroll', position, true);
  window.removeEventListener('resize', position);
}

function onDocumentClick(event) {
  const target = event.target;
  if (rootRef.value?.contains(target)) return;
  if (popoverRef.value?.contains(target)) return;
  close();
}

function toggle() {
  if (open.value) {
    close();
    return;
  }
  search.value = '';
  open.value = true;
  attachGlobalListeners();
  nextTick(() => {
    position();
    searchRef.value?.focus();
  });
}

function close() {
  if (!open.value) return;
  open.value = false;
  detachGlobalListeners();
}

function choose(category) {
  emit('update:modelValue', category.category_id);
  close();
  buttonRef.value?.focus();
}

function onManage() {
  close();
  emit('manage');
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    event.stopPropagation();
    close();
    buttonRef.value?.focus();
  }
}

function focusButton() {
  buttonRef.value?.focus();
}

onUnmounted(detachGlobalListeners);

defineExpose({ close, focusButton });
</script>
