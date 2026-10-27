<template>
  <Modal
    :open="open"
    title="RCPI-PPE Format Preview"
    subtitle="Draft preview of the Report on the Physical Count of Inventories (Appendix 66). Sample data only; not an official count."
    max-width="max-w-7xl"
    :fixed-body="true"
    :close-on-escape="!selectionDrawerOpen"
    @close="handleClose"
  >
    <div
      class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900"
      @keydown.esc.window="selectionDrawerOpen = false"
    >
      <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-2 dark:border-gray-700 dark:bg-gray-900">
        <div>
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h2>
          <p class="text-[11px] text-amber-700 dark:text-amber-300">DRAFT FORMAT PREVIEW · illustrative sample data</p>
        </div>
        <div class="flex gap-1" aria-label="Preview format">
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-semibold"
            :class="previewMode === 'html' ? 'bg-white text-purple-700 shadow-sm dark:bg-gray-700 dark:text-purple-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
            :aria-pressed="previewMode === 'html'"
            @click="previewMode = 'html'"
          >
            HTML Preview
          </button>
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-semibold"
            :class="previewMode === 'pdf' ? 'bg-white text-purple-700 shadow-sm dark:bg-gray-700 dark:text-purple-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
            :aria-pressed="previewMode === 'pdf'"
            :disabled="generating"
            @click="selectPdfPreview"
          >
            PDF Preview
          </button>
        </div>
      </div>

      <main class="min-h-0 min-w-0 flex-1 overflow-auto p-4">
        <div v-if="previewMode === 'pdf'" class="space-y-2">
          <div class="relative h-[65vh] min-h-[24rem] overflow-hidden rounded bg-white">
            <iframe
              v-if="previewPdfUrl"
              :src="previewPdfUrl"
              class="h-full w-full border-0"
              title="RCPI-PPE PDF preview"
            />
            <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500" role="status">
              {{ generating ? 'Generating PDF preview…' : 'PDF preview will appear here.' }}
            </div>
            <div v-if="generating && previewPdfUrl" class="absolute right-2 top-2 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">
              Refreshing PDF preview…
            </div>
          </div>
        </div>

        <div v-else class="overflow-x-auto">
          <div class="mx-auto mb-3 flex max-w-[1400px] items-center justify-between gap-2 text-xs text-gray-600 dark:text-gray-300">
            <span>{{ selectedItems.length }} sample item(s) included</span>
            <span>Edit the fields to review the layout. Changes are not saved.</span>
          </div>

          <article class="mx-auto min-w-[1100px] max-w-[1400px] bg-white font-serif text-[11px] leading-tight text-black shadow-sm">
            <table class="w-full table-fixed border-collapse text-center">
              <colgroup>
                <col style="width: 10%" />
                <col style="width: 16%" />
                <col style="width: 10%" />
                <col style="width: 9%" />
                <col style="width: 8%" />
                <col style="width: 8%" />
                <col style="width: 8%" />
                <col style="width: 7%" />
                <col style="width: 7%" />
                <col style="width: 7%" />
                <col style="width: 10%" />
              </colgroup>
              <tbody>
                <tr>
                  <td colspan="11" class="rpci-td relative px-3 py-3 text-center">
                    <span class="absolute right-2 top-2 text-[10px] font-bold italic">Appendix 66</span>
                    <p class="text-[15px] font-bold uppercase">Report on the Physical Count of Inventories</p>
                    <p class="text-[14px] font-bold uppercase">Office Equipment (Low Valued)</p>
                    <label class="mt-1 block text-[12px] font-bold uppercase">
                      <input v-model="form.inventory_type" class="rpci-input w-56 text-center" aria-label="Inventory type" />
                    </label>
                    <p class="mt-1 text-[13px] font-bold">
                      As at
                      <input v-model="form.report_date" type="date" class="rpci-input w-36 text-center" aria-label="Count date" />
                    </p>
                    <label class="mt-3 flex max-w-[390px] items-center gap-2 text-left font-bold">
                      Fund Cluster:
                      <input v-model="form.fund_cluster" class="rpci-input flex-1" aria-label="Fund cluster" />
                    </label>
                    <p class="mt-3 text-left">
                      For which
                      <input v-model="form.accountable_person" class="rpci-input w-64 font-bold" aria-label="Accountable person" />
                      is accountable, having assumed such accountability on
                      <input v-model="form.accountability_date" type="date" class="rpci-input w-36 font-bold" aria-label="Accountability date" />
                    </p>
                  </td>
                </tr>
                  <tr>
                    <th rowspan="2" class="rpci-th">Article</th>
                    <th rowspan="2" class="rpci-th">Description</th>
                    <th rowspan="2" class="rpci-th">Stock Number</th>
                    <th rowspan="2" class="rpci-th">Unit of Measurement</th>
                    <th rowspan="2" class="rpci-th">Unit Value</th>
                    <th class="rpci-th">Balance Per Card</th>
                    <th class="rpci-th">On Hand Per Count</th>
                    <th colspan="2" class="rpci-th">Shortage / Overage</th>
                    <th colspan="2" class="rpci-th">Remarks</th>
                  </tr>
                  <tr>
                    <th class="rpci-th">Quantity</th>
                    <th class="rpci-th">Quantity</th>
                    <th class="rpci-th">Quantity</th>
                    <th class="rpci-th">Value</th>
                    <th class="rpci-th">Condition</th>
                    <th class="rpci-th">Whereabouts</th>
                  </tr>
                <tr v-for="item in selectedItems" :key="item.id">
                    <td class="rpci-td"><input v-model="item.article" class="rpci-cell" :aria-label="`${item.article} article`" /></td>
                    <td class="rpci-td"><input v-model="item.description" class="rpci-cell" :aria-label="`${item.article} description`" /></td>
                    <td class="rpci-td"><input v-model="item.stock_number" class="rpci-cell" :aria-label="`${item.article} stock number`" /></td>
                    <td class="rpci-td"><input v-model="item.unit" class="rpci-cell" :aria-label="`${item.article} unit of measurement`" /></td>
                    <td class="rpci-td"><input v-model.number="item.unit_value" type="number" min="0" step="0.01" class="rpci-cell text-right" :aria-label="`${item.article} unit value`" /></td>
                    <td class="rpci-td"><input v-model.number="item.book_quantity" type="number" min="0" class="rpci-cell text-right" :aria-label="`${item.article} balance per card`" /></td>
                    <td class="rpci-td"><input v-model.number="item.counted_quantity" type="number" min="0" class="rpci-cell text-right" :aria-label="`${item.article} on hand quantity`" /></td>
                    <td class="rpci-td text-right">{{ Math.abs(variance(item)) }}</td>
                    <td class="rpci-td text-right">{{ formatMoney(Math.abs(variance(item)) * Number(item.unit_value || 0)) }}</td>
                    <td class="rpci-td"><input v-model="item.condition" class="rpci-cell" :aria-label="`${item.article} condition`" /></td>
                    <td class="rpci-td"><input v-model="item.whereabouts" class="rpci-cell" :aria-label="`${item.article} whereabouts`" /></td>
                  </tr>
                <tr v-if="selectedItems.length === 0">
                  <td colspan="11" class="rpci-td py-6 text-gray-500">Choose sample rows from the drawer to preview the form.</td>
                </tr>
                <tr class="text-center">
                  <td colspan="4" class="rpci-td h-28 p-3 align-top">
                    <p class="text-left">Recommending Approval:</p>
                    <input v-model="form.recommending_name" class="rpci-input mt-5 w-full text-center font-bold" aria-label="Recommending official name" />
                    <input v-model="form.recommending_title" class="rpci-input mt-1 w-full text-center" aria-label="Recommending official title" />
                  </td>
                  <td colspan="4" class="rpci-td h-28 p-3 align-top">
                    <p class="text-left">Approved by:</p>
                    <input v-model="form.approving_name" class="rpci-input mt-5 w-full text-center font-bold" aria-label="Approving official name" />
                    <input v-model="form.approving_title" class="rpci-input mt-1 w-full text-center" aria-label="Approving official title" />
                  </td>
                  <td colspan="3" class="rpci-td h-28 p-3 align-top">
                    <p class="text-left">Verified by:</p>
                    <div class="mx-2 mt-8 border-b border-black" />
                    <input v-model="form.verifying_title" class="rpci-input mt-1 w-full text-center" aria-label="Verifier title" />
                  </td>
                </tr>
              </tbody>
            </table>
            <p class="mt-2 text-center text-[10px] font-bold text-amber-800">DRAFT FORMAT PREVIEW — NOT AN OFFICIAL COUNT OR CERTIFIED REPORT</p>
          </article>
        </div>
      </main>
    </div>

    <template #footer>
      <div class="flex items-center justify-between gap-2">
        <p v-if="error" class="text-xs text-rose-700 dark:text-rose-300" role="alert">{{ error }}</p>
        <span v-else class="text-xs text-gray-500 dark:text-gray-400">Preview-only component; no inventory records are read or changed.</span>
        <div class="ml-auto flex gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="handleClose"
          >
            Close
          </button>
          <button
            type="button"
            class="rounded-md border border-purple-200 bg-purple-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 disabled:cursor-wait disabled:opacity-60 dark:border-purple-500/30 dark:hover:bg-purple-700/80"
            :disabled="generating || selectedItems.length === 0"
            @click="downloadPdf"
          >
            {{ generating ? 'Generating…' : 'Download PDF' }}
          </button>
        </div>
      </div>
    </template>

    <template #overlay>
      <button
        type="button"
        class="absolute left-0 top-36 z-[100001] flex items-center rounded-r-md border border-l-0 border-purple-200 bg-white px-3 py-3 text-xs font-semibold text-purple-700 shadow-lg transition hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 dark:border-purple-500/30 dark:bg-gray-800 dark:text-purple-300 dark:hover:bg-purple-500/10"
        :aria-expanded="selectionDrawerOpen"
        :aria-label="selectionDrawerOpen ? 'Close sample item drawer' : 'Open sample item drawer'"
        aria-controls="rpci-selection-drawer"
        @click="selectionDrawerOpen = !selectionDrawerOpen"
      >
        <svg
          class="h-4 w-4 transition-transform duration-200"
          :class="{ 'rotate-180': selectionDrawerOpen }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
        </svg>
      </button>

      <Transition name="rpci-fade">
        <div
          v-if="selectionDrawerOpen"
          class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
          aria-hidden="true"
          @click="selectionDrawerOpen = false"
        />
      </Transition>

      <Transition name="rpci-drawer">
        <aside
          v-if="selectionDrawerOpen"
          id="rpci-selection-drawer"
          class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
          aria-label="Select sample RCPI-PPE items"
        >
          <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Sample report items</h3>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ selectedItems.length }} item(s) selected</p>
            </div>
            <button
              type="button"
              class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
              aria-label="Close sample item drawer"
              @click="selectionDrawerOpen = false"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </header>
          <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
            <label
              v-for="item in items"
              :key="item.id"
              class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-3 text-xs hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/5"
            >
              <input v-model="item.selected" type="checkbox" class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
              <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-800 dark:text-gray-100">{{ item.article }}</span>
                <span class="mt-0.5 block text-gray-500 dark:text-gray-400">{{ item.description }}</span>
                <span class="mt-1 block text-gray-500 dark:text-gray-400">Book {{ item.book_quantity }} · Count {{ item.counted_quantity }}</span>
              </span>
            </label>
          </div>
        </aside>
      </Transition>
    </template>
  </Modal>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import Modal from '../../../components/ui/dialogs/Modal.vue';
import api from '../../../lib/axios';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
const previewMode = ref('html');
const selectionDrawerOpen = ref(false);
const error = ref('');
const generating = ref(false);
const previewPdfUrl = ref('');
const previewPdfBlob = ref(null);
const previewCurrent = ref(false);
let previewRequestId = 0;
let previewRefreshTimer = null;

function today() {
  const date = new Date();
  const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return localDate.toISOString().slice(0, 10);
}

const form = ref({
  inventory_type: 'Sports Equipment',
  report_date: today(),
  fund_cluster: '',
  accountable_person: '',
  accountability_date: '',
  recommending_name: '',
  recommending_title: 'Property Custodian',
  approving_name: '',
  approving_title: 'School Head',
  verifying_title: 'Signature over Printed Name of COA Representative',
});

const items = ref([
  { id: 1, selected: true, article: 'Basketball', description: 'Rubber basketball, size 7', stock_number: 'SAMPLE-001', unit: 'Piece', unit_value: 850, book_quantity: 4, counted_quantity: 4, condition: 'Good', whereabouts: 'Sports room' },
  { id: 2, selected: true, article: 'Volleyball', description: 'Synthetic leather volleyball', stock_number: 'SAMPLE-002', unit: 'Piece', unit_value: 720, book_quantity: 6, counted_quantity: 5, condition: 'Fair', whereabouts: 'Sports room' },
  { id: 3, selected: true, article: 'Table Tennis Paddle', description: 'Table tennis paddle', stock_number: 'SAMPLE-003', unit: 'Piece', unit_value: 350, book_quantity: 8, counted_quantity: 9, condition: 'Good', whereabouts: 'PE storage' },
  { id: 4, selected: true, article: 'Skipping Rope', description: 'Nylon jump rope', stock_number: 'SAMPLE-004', unit: 'Piece', unit_value: 120, book_quantity: 10, counted_quantity: 10, condition: 'Good', whereabouts: 'PE storage' },
  { id: 5, selected: true, article: 'Whistle', description: 'Sports referee whistle', stock_number: 'SAMPLE-005', unit: 'Piece', unit_value: 95, book_quantity: 5, counted_quantity: 5, condition: 'Good', whereabouts: 'Sports room' },
]);

const selectedItems = computed(() => items.value.filter((item) => item.selected));

function variance(item) {
  return Number(item.counted_quantity || 0) - Number(item.book_quantity || 0);
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function generatePdf() {
  if (selectedItems.value.length === 0) {
    error.value = 'Select at least one sample item to generate the PDF.';
    return null;
  }

  const requestId = ++previewRequestId;
  generating.value = true;
  error.value = '';
  try {
    const response = await api.post('/custodian/inventory/rcpi-ppe-pdf', {
      ...form.value,
      items: selectedItems.value.map((item) => ({
        article: item.article,
        description: item.description,
        stock_number: item.stock_number,
        unit: item.unit,
        unit_value: item.unit_value,
        book_quantity: item.book_quantity,
        counted_quantity: item.counted_quantity,
        condition: item.condition,
        whereabouts: item.whereabouts,
      })),
    }, {
      responseType: 'blob',
      skipToast: true,
    });

    const blob = new Blob([response.data], { type: 'application/pdf' });
    if (requestId !== previewRequestId) return null;

    if (previewPdfUrl.value) window.URL.revokeObjectURL(previewPdfUrl.value);
    previewPdfBlob.value = blob;
    previewPdfUrl.value = window.URL.createObjectURL(blob);
    previewCurrent.value = true;

    return blob;
  } catch (requestError) {
    console.error('Failed to generate RCPI-PPE PDF', requestError);
    if (requestId === previewRequestId) {
      error.value = 'Failed to generate the RCPI-PPE PDF. Please review the form and try again.';
    }
    return null;
  } finally {
    if (requestId === previewRequestId) generating.value = false;
  }
}

function selectPdfPreview() {
  previewMode.value = 'pdf';
  if (!previewCurrent.value) generatePdf();
}

function schedulePreviewRefresh() {
  previewCurrent.value = false;
  if (previewMode.value !== 'pdf' || !props.open) return;
  clearTimeout(previewRefreshTimer);
  previewRefreshTimer = setTimeout(() => {
    previewRefreshTimer = null;
    generatePdf();
  }, 400);
}

async function downloadPdf() {
  const blob = previewCurrent.value && previewPdfBlob.value
    ? previewPdfBlob.value
    : await generatePdf();
  if (!blob) return;

  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `RCPI-PPE_DRAFT_${today()}.pdf`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}

function clearPreview() {
  clearTimeout(previewRefreshTimer);
  previewRefreshTimer = null;
  previewRequestId += 1;
  generating.value = false;
  previewCurrent.value = false;
  previewPdfBlob.value = null;
  if (previewPdfUrl.value) {
    window.URL.revokeObjectURL(previewPdfUrl.value);
    previewPdfUrl.value = '';
  }
}

function handleClose() {
  selectionDrawerOpen.value = false;
  clearPreview();
  emit('close');
}

watch(
  [form, items],
  schedulePreviewRefresh,
  { deep: true },
);

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    clearPreview();
    previewMode.value = 'html';
    selectionDrawerOpen.value = false;
    error.value = '';
  } else {
    clearPreview();
  }
});

onUnmounted(clearPreview);
</script>

<style scoped>
.rpci-input {
  min-width: 0;
  border: 0;
  border-bottom: 1px solid #000;
  border-radius: 0;
  background: transparent;
  padding: 1px 3px;
  color: inherit;
  font: inherit;
  line-height: inherit;
}

.rpci-input:focus,
.rpci-cell:focus {
  border-bottom-color: #7c3aed;
  outline: none;
  box-shadow: 0 1px 0 #7c3aed;
}

.rpci-th,
.rpci-td {
  border: 1px solid #111;
  padding: 4px;
}

.rpci-th {
  font-weight: 700;
}

.rpci-cell {
  width: 100%;
  min-width: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
}

.rpci-fade-enter-active,
.rpci-fade-leave-active,
.rpci-drawer-enter-active,
.rpci-drawer-leave-active {
  transition: all 0.2s ease;
}

.rpci-fade-enter-from,
.rpci-fade-leave-to {
  opacity: 0;
}

.rpci-drawer-enter-from,
.rpci-drawer-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}

@media print {
  .rpci-input,
  .rpci-cell {
    border-color: transparent;
  }
}
</style>
