<template>
  <Modal
    :open="open"
    title="Stock In New Items"
    subtitle="Prepare a receiving batch and review each inventory line before saving."
    max-width="max-w-[84rem]"
    :close-on-escape="!managerOpen"
    @close="$emit('close')"
  >
    <div class="space-y-3">
      <div class="grid w-full max-w-xs grid-cols-2 gap-0.5 rounded-md bg-gray-100 p-0.5 dark:bg-white/5" role="tablist" aria-label="Stock-in method">
        <button
          v-for="tab in stockInTabs"
          :key="tab.key"
          type="button"
          role="tab"
          class="rounded px-3 py-2 text-xs font-semibold transition-all"
          :class="activeTab === tab.key
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          :aria-selected="activeTab === tab.key"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
        <div class="w-full sm:max-w-xs">
          <label for="stock-batch-ics" class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Inventory Custodian Slip (ICS) No.
          </label>
          <input
            id="stock-batch-ics"
            v-model="icsNo"
            type="text"
            maxlength="255"
            placeholder="e.g. ICS-2026-001"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Applied to every saved line in this batch.</p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2">
          <template v-if="activeTab === 'csv'">
            <button
              type="button"
              class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
              @click="exportCsv"
            >
              Download CSV template
            </button>
            <label class="cursor-pointer rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
              {{ csvFileName || 'Choose CSV file' }}
              <input ref="csvFileInput" type="file" accept=".csv,text/csv" class="sr-only" @change="handleCsvFile" />
            </label>
          </template>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="lineItems.length >= 100"
            @click="addLine"
          >
            <span class="text-base leading-none">+</span> Add row
          </button>
        </div>
      </div>

        <p v-if="csvError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
          {{ csvError }}
        </p>
        <p v-if="batchMessage" role="status" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
          {{ batchMessage }}
        </p>

        <div class="max-h-[352px] min-h-[352px] overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
          <table class="w-max min-w-full border-collapse text-left text-xs">
            <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              <tr>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Qty</th>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Unit</th>
                <th colspan="2" class="border-b border-gray-200 px-3 py-2 text-center dark:border-gray-700">Amount</th>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Description</th>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Category</th>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Inventory Item No.</th>
                <th rowspan="2" class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Estimated Useful Life</th>
                <th rowspan="2" class="border-b border-gray-200 px-3 py-2 dark:border-gray-700"><span class="sr-only">Actions</span></th>
              </tr>
              <tr>
                <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Unit Cost</th>
                <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Total Cost</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
              <tr v-if="!lineItems.length">
                <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                  {{ activeTab === 'manual' ? 'Select Add item to create an editable row.' : 'Choose a CSV file to load editable item rows.' }}
                </td>
              </tr>
              <template v-for="(row, index) in lineItems" :key="row.id">
                <tr :class="row.errors.length || row.serverErrors.length ? 'bg-rose-50/50 dark:bg-rose-950/20' : ''">
                  <td class="px-3 py-2 align-top text-right">
                    <input
                      v-model.number="row.quantity"
                      type="number"
                      min="1"
                      aria-label="Quantity"
                      class="w-20 rounded border border-gray-200 bg-white px-2 py-1.5 text-right text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                      @input="refreshLine(row)"
                    />
                    <p v-if="row.fieldErrors.quantity" class="mt-1 max-w-20 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.quantity }}</p>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <input
                      v-model.trim="row.unit"
                      type="text"
                      maxlength="255"
                      aria-label="Unit"
                      class="w-24 rounded border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                      @input="refreshLine(row)"
                    />
                    <p v-if="row.fieldErrors.unit" class="mt-1 max-w-24 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.unit }}</p>
                  </td>
                  <td class="px-3 py-2 align-top text-right">
                    <input
                      v-model.number="row.unit_cost"
                      type="number"
                      min="0"
                      step="0.01"
                      aria-label="Unit cost"
                      class="w-28 rounded border border-gray-200 bg-white px-2 py-1.5 text-right text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                      @input="refreshLine(row)"
                    />
                    <p v-if="row.fieldErrors.unit_cost" class="mt-1 max-w-28 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.unit_cost }}</p>
                  </td>
                  <td class="whitespace-nowrap px-3 py-2 align-top text-right font-semibold text-gray-800 dark:text-gray-200">
                    {{ formatCurrency(rowTotal(row)) }}
                  </td>
                  <td class="min-w-52 px-3 py-2 align-top">
                    <textarea
                      v-model.trim="row.description"
                      rows="2"
                      maxlength="255"
                      aria-label="Description"
                      class="w-52 resize-none rounded border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                      @input="refreshLine(row)"
                    />
                    <p v-if="row.fieldErrors.description" class="mt-1 text-[10px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.description }}</p>
                  </td>
                  <td class="min-w-40 px-3 py-2 align-top">
                    <CategoryCombobox
                      :ref="(el) => setComboboxRef(row.id, el)"
                      :model-value="row.category_id"
                      :categories="categories"
                      :invalid="Boolean(row.fieldErrors.category_id)"
                      :aria-label="`Category for line ${index + 1}`"
                      @update:model-value="onComboboxSelect(row, $event)"
                      @manage="openManager(row.id)"
                    />
                    <textarea
                      v-if="rowRequiresSerial(row)"
                      v-model="row.serial_numbers_text"
                      rows="2"
                      aria-label="Serial numbers"
                      class="mt-1 w-40 rounded border border-amber-200 bg-white px-2 py-1 text-[10px] font-mono text-gray-700 focus:border-emerald-500 focus:outline-none dark:border-amber-900 dark:bg-gray-800 dark:text-gray-300"
                      placeholder="Enter serial numbers"
                      @input="updateRowSerials(row)"
                    />
                    <p v-if="row.fieldErrors.category_id || row.fieldErrors.serial_numbers" class="mt-1 max-w-40 break-words text-[10px] text-rose-700 dark:text-rose-300">
                      {{ row.fieldErrors.category_id || row.fieldErrors.serial_numbers }}
                      <button
                        v-if="row.unknownCategoryName"
                        type="button"
                        class="mt-0.5 block font-bold text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300 dark:hover:text-emerald-200"
                        @click="openManager(row.id)"
                      >
                        Manage categories
                      </button>
                    </p>
                  </td>
                  <td class="whitespace-nowrap px-3 py-2 align-top font-mono text-[11px] text-gray-500 dark:text-gray-400">
                    {{ row.inventory_item_no || 'Generated on save' }}
                  </td>
                  <td class="px-3 py-2 align-top">
                    <div class="flex items-center gap-1">
                      <input
                        v-model="row.lifespan_years"
                        type="number"
                        min="1"
                        max="65535"
                        aria-label="Estimated useful life in years"
                        class="w-20 rounded border border-gray-200 bg-white px-2 py-1.5 text-right text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        @input="refreshLine(row)"
                      />
                      <span class="text-[10px] text-gray-500 dark:text-gray-400">years</span>
                    </div>
                    <p v-if="row.fieldErrors.lifespan_years" class="mt-1 max-w-32 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.lifespan_years }}</p>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <button
                      type="button"
                      class="rounded px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                      :aria-label="`Remove line ${index + 1}`"
                      @click="removeLine(row.id)"
                    >
                      Remove
                    </button>
                  </td>
                </tr>
                <tr v-if="row.serverErrors.length" :key="`${row.id}-errors`">
                  <td colspan="9" class="max-w-0 px-3 pb-2 pt-0 text-[11px] text-rose-700 dark:text-rose-300">
                    <ul class="list-inside list-disc break-words">
                      <li v-for="(error, errorIndex) in row.serverErrors" :key="errorIndex">{{ error }}</li>
                    </ul>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
    </div>

    <template #footer>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Serial- or QR-tracked units are created separately when required by the category.
        </p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
            @click="$emit('close')"
          >
            Cancel
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-md hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="saving || !validRows.length"
            @click="submitBatch"
          >
            <svg v-if="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ saving ? 'Saving stock-in…' : `Save ${validRows.length} line(s)` }}
          </button>
        </div>
      </div>
    </template>

    <CategoryManager
      :open="managerOpen"
      @close="onManagerClose"
      @saved="onManagerSaved"
    />
  </Modal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Papa from 'papaparse';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import CategoryCombobox from '../../components/ui/inputs/CategoryCombobox.vue';
import CategoryManager from './CategoryManager.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved', 'categories-updated']);
const stockInTabs = [
  { key: 'manual', label: 'Manual' },
  { key: 'csv', label: 'Through CSV' },
];
const activeTab = ref('manual');
const saving = ref(false);
const icsNo = ref('');
const batchMessage = ref('');
const csvError = ref('');
const csvFileName = ref('');
const csvFileInput = ref(null);
const today = new Date().toISOString().slice(0, 10);
let nextLineId = 1;
const lineItems = ref([]);
const validRows = computed(() => lineItems.value.filter((row) => row.errors.length === 0 && row.serverErrors.length === 0));
const grandTotal = computed(() => lineItems.value.reduce((sum, row) => sum + rowTotal(row), 0));

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    saving.value = false;
    batchMessage.value = '';
    csvError.value = '';
    if (activeTab.value === 'manual' && lineItems.value.length === 0) {
      lineItems.value.push(createLine());
    }
  }
});

function categoryRequiresSerial(category) {
  return category?.requires_serial_number === true || category?.requires_serial_number === 1;
}

function categoryForCsv(name) {
  const normalized = String(name ?? '').trim().toLowerCase();
  return props.categories.find((category) => String(category.category_name ?? '').trim().toLowerCase() === normalized) ?? null;
}

function parseSerialNumbers(value) {
  return String(value ?? '')
    .split(/[;\r\n]+/)
    .map((serial) => serial.trim())
    .filter(Boolean);
}

function createLine(values = {}) {
  const row = {
    id: nextLineId++,
    description: String(values.description ?? ''),
    category_id: values.category_id === '' || values.category_id == null ? '' : Number(values.category_id),
    unit: String(values.unit ?? ''),
    quantity: values.quantity == null || values.quantity === '' ? 1 : Number(values.quantity),
    unit_cost: values.unit_cost == null ? '' : Number(values.unit_cost),
    date_acquired: String(values.date_acquired ?? today),
    lifespan_years: values.lifespan_years === '' || values.lifespan_years == null ? '' : Number(values.lifespan_years),
    serial_numbers: Array.isArray(values.serial_numbers) ? values.serial_numbers : [],
    serial_numbers_text: Array.isArray(values.serial_numbers) ? values.serial_numbers.join('\n') : '',
    inventory_item_no: '',
    errors: [],
    fieldErrors: {},
    serverErrors: [],
    unknownCategoryName: '',
  };
  Object.assign(row, validateLine(row));
  return row;
}

function validateLine(row) {
  const fieldErrors = {};
  const fail = (field, message) => {
    fieldErrors[field] = message;
  };
  const quantity = Number(row.quantity);
  const cost = Number(row.unit_cost);
  const category = props.categories.find((item) => String(item.category_id) === String(row.category_id));

  if (!String(row.description ?? '').trim()) fail('description', 'Required.');
  else if (String(row.description).length > 255) fail('description', 'Max 255 characters.');
  if (!category) fail('category_id', 'Choose category.');
  if (!String(row.unit ?? '').trim()) fail('unit', 'Required.');
  if (!Number.isSafeInteger(quantity) || quantity < 1) fail('quantity', 'Enter a whole number above 0.');
  if (row.unit_cost === '' || row.unit_cost == null || !Number.isFinite(cost) || cost < 0) fail('unit_cost', 'Enter a cost of 0 or more.');
  if (!row.date_acquired || Number.isNaN(Date.parse(row.date_acquired))) fail('date_acquired', 'Enter a valid date.');
  else if (row.date_acquired > today) fail('date_acquired', 'Date must not be in the future.');
  if (row.lifespan_years !== '' && row.lifespan_years != null) {
    const lifespan = Number(row.lifespan_years);
    if (!Number.isInteger(lifespan) || lifespan < 1 || lifespan > 65535) fail('lifespan_years', 'Enter 1–65535 years.');
  }

  const serials = Array.isArray(row.serial_numbers) ? row.serial_numbers : [];
  if (categoryRequiresSerial(category)) {
    if (serials.length !== quantity) fail('serial_numbers', `Need ${quantity} serial number(s).`);
    else if (new Set(serials).size !== serials.length) fail('serial_numbers', 'Serial numbers must be unique.');
    else if (serials.some((serial) => !serial || serial.length > 255)) fail('serial_numbers', 'Required; max 255 chars each.');
  }

  return { fieldErrors, errors: Object.values(fieldErrors) };
}

function refreshLine(row) {
  row.serverErrors = [];
  Object.assign(row, validateLine(row));
}

function addLine() {
  if (lineItems.value.length >= 100) return;
  lineItems.value.push(createLine());
  batchMessage.value = '';
}

function setRowCategory(row) {
  const category = props.categories.find((item) => String(item.category_id) === String(row.category_id));
  if (!categoryRequiresSerial(category)) {
    row.serial_numbers = [];
    row.serial_numbers_text = '';
  }
  refreshLine(row);
}

const managerOpen = ref(false);
const originRowId = ref(null);
const comboboxRefs = new Map();

function setComboboxRef(rowId, el) {
  if (el) comboboxRefs.set(rowId, el);
  else comboboxRefs.delete(rowId);
}

function onComboboxSelect(row, categoryId) {
  row.category_id = categoryId;
  setRowCategory(row);
}

function openManager(rowId) {
  comboboxRefs.forEach((combobox) => combobox?.close?.());
  originRowId.value = rowId;
  managerOpen.value = true;
}

function onManagerClose() {
  managerOpen.value = false;
  comboboxRefs.get(originRowId.value)?.focusButton?.();
  originRowId.value = null;
}

async function onManagerSaved({ categories, category }) {
  emit('categories-updated', categories ?? []);
  // Wait for the refreshed list to reach this modal's props so rematching
  // and re-validation run against the new flags, not the stale ones.
  await nextTick();
  // New/renamed flags apply immediately: re-validate every typed row
  // without discarding input. Rows waiting on a CSV category name rematch.
  for (const row of lineItems.value) {
    if (row.unknownCategoryName) {
      const match = categoryForCsv(row.unknownCategoryName);
      if (match) {
        row.category_id = match.category_id;
        row.unknownCategoryName = '';
      }
    }
    refreshLine(row);
  }
  // Preselect the new/edited category in the row that opened the manager.
  if (category?.category_id != null) {
    const origin = lineItems.value.find((row) => row.id === originRowId.value);
    if (origin) {
      origin.category_id = category.category_id;
      origin.unknownCategoryName = '';
      setRowCategory(origin);
    }
  }
  batchMessage.value = '';
}

function rowRequiresSerial(row) {
  const category = props.categories.find((item) => String(item.category_id) === String(row.category_id));
  return categoryRequiresSerial(category);
}

function updateRowSerials(row) {
  row.serial_numbers = parseSerialNumbers(row.serial_numbers_text);
  refreshLine(row);
}

function removeLine(id) {
  lineItems.value = lineItems.value.filter((row) => row.id !== id);
  batchMessage.value = '';
}

function isUntouchedBlankLine(row) {
  return !row.description
    && !row.category_id
    && !row.unit
    && row.unit_cost === ''
    && !row.lifespan_years
    && row.serial_numbers.length === 0;
}

function rowTotal(row) {
  const quantity = Number(row.quantity);
  const cost = Number(row.unit_cost);
  return Number.isFinite(quantity) && Number.isFinite(cost) ? quantity * cost : 0;
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount) || 0);
}

const CSV_HEADERS = ['description', 'category_name', 'unit', 'quantity', 'unit_cost', 'date_acquired', 'lifespan_years', 'serial_numbers'];
const LEGACY_CSV_HEADERS = ['item_name', 'category_name', 'unit', 'quantity', 'unit_cost', 'date_acquired', 'lifespan_years', 'ics_no', 'serial_numbers'];
const CSV_EXPECTED_LABEL = CSV_HEADERS.join(',');

function exportCsv() {
  const csv = String.fromCharCode(0xFEFF) + `${CSV_HEADERS.join(',')}\r\n`;
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'stock-in-template.csv';
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function csvLine(raw) {
  const category = categoryForCsv(raw.category_name);
  const row = createLine({
    description: raw.description ?? raw.item_name ?? '',
    category_id: category?.category_id ?? '',
    unit: String(raw.unit ?? '').trim(),
    quantity: raw.quantity,
    unit_cost: String(raw.unit_cost ?? '').trim() === '' ? '' : Number(raw.unit_cost),
    date_acquired: String(raw.date_acquired ?? '').trim(),
    lifespan_years: String(raw.lifespan_years ?? '').trim(),
    serial_numbers: parseSerialNumbers(raw.serial_numbers),
  });
  if (!category) {
    row.fieldErrors.category_id = `Unknown category '${String(raw.category_name ?? '').trim()}'. Choose an active category.`;
    row.errors = Object.values(row.fieldErrors);
    row.unknownCategoryName = String(raw.category_name ?? '').trim();
  }
  return row;
}

function handleCsvFile(event) {
  const file = event?.target?.files?.[0];
  csvError.value = '';
  csvFileName.value = file?.name ?? '';
  if (!file) return;

  Papa.parse(file, {
    header: true,
    skipEmptyLines: true,
    complete: (result) => {
      const fields = Array.isArray(result?.meta?.fields)
        ? result.meta.fields.map((field) => String(field ?? '').replace(/^\uFEFF/, '').trim())
        : [];
      const currentFormat = CSV_HEADERS.every((header) => fields.includes(header)) && fields.length === CSV_HEADERS.length;
      const legacyFormat = LEGACY_CSV_HEADERS.every((header) => fields.includes(header)) && fields.length === LEGACY_CSV_HEADERS.length;
      if (!currentFormat && !legacyFormat) {
        csvError.value = `Invalid CSV header. Expected: ${CSV_EXPECTED_LABEL}.`;
        return;
      }
      if (result.errors?.length) {
        csvError.value = `CSV parsing reported an issue: ${result.errors[0]?.message ?? 'malformed CSV'}.`;
      }
      const records = Array.isArray(result.data) ? result.data : [];
      const hasBlankStarter = lineItems.value.length === 1 && isUntouchedBlankLine(lineItems.value[0]);
      const existingRows = hasBlankStarter ? 0 : lineItems.value.length;
      if (!records.length) {
        csvError.value = 'No item rows were found in this file.';
        return;
      }
      if (records.length > 100 || existingRows + records.length > 100) {
        csvError.value = 'A stock-in batch can contain at most 100 lines.';
        return;
      }
      if (hasBlankStarter) {
        lineItems.value = [];
      }
      lineItems.value.push(...records.map(csvLine));
      if (legacyFormat) {
        csvError.value = 'Older template recognized. Item names were loaded as descriptions; the shared ICS number above will be used.';
      }
    },
    error: (error) => {
      csvError.value = error?.message ?? 'Could not read this CSV file.';
    },
  });
}

function flattenMessages(messages) {
  if (Array.isArray(messages)) {
    return messages.filter((message) => typeof message === 'string' && message);
  }
  if (messages && typeof messages === 'object') {
    return Object.entries(messages).flatMap(([field, fieldMessages]) => {
      const list = Array.isArray(fieldMessages) ? fieldMessages : [fieldMessages];
      return list.filter((message) => typeof message === 'string' && message).map((message) => `${field}: ${message}`);
    });
  }
  return typeof messages === 'string' && messages ? [messages] : [];
}

async function submitBatch() {
  if (saving.value || !validRows.value.length) return;
  if (String(icsNo.value).length > 255) {
    batchMessage.value = 'ICS number must not exceed 255 characters.';
    return;
  }

  saving.value = true;
  batchMessage.value = '';
  const sentRows = [...validRows.value];
  try {
    const { data } = await api.post('/custodian/inventory/stock-in/import', {
      rows: sentRows.map((row) => ({
        item_name: row.description,
        category_id: Number(row.category_id),
        unit: row.unit,
        quantity: Number(row.quantity),
        unit_cost: Number(row.unit_cost),
        date_acquired: row.date_acquired,
        lifespan_years: row.lifespan_years === '' ? null : Number(row.lifespan_years),
        ics_no: icsNo.value.trim() || null,
        serial_numbers: row.serial_numbers,
      })),
    });

    const serverErrors = data?.errors && typeof data.errors === 'object' ? data.errors : {};
    const failedIndexes = new Set();
    for (const [position, messages] of Object.entries(serverErrors)) {
      const row = sentRows[Number(position)];
      if (!row) continue;
      row.serverErrors = flattenMessages(messages);
      failedIndexes.add(Number(position));
    }

    if ((data?.imported ?? 0) > 0) {
      lineItems.value = lineItems.value.filter((row) => {
        const index = sentRows.indexOf(row);
        return index === -1 || failedIndexes.has(index);
      });
    }

    if ((data?.failed ?? 0) === 0 && (data?.imported ?? 0) > 0) {
      lineItems.value = [];
      icsNo.value = '';
      emit('saved');
      return;
    }
    batchMessage.value = data?.message ?? 'Some lines could not be saved. Review the rows marked with errors and try again.';
  } catch (error) {
    batchMessage.value = error?.response?.data?.message
      ?? 'Stock-in could not be completed. Your item lines are still here; check your connection and try again.';
  } finally {
    saving.value = false;
  }
}
</script>
