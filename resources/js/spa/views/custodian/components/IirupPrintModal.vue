<template>
  <Modal
    :open="open"
    title="Print IIRUP"
    subtitle="Inventory and Inspection Report of Unserviceable Property"
    max-width="max-w-7xl"
    :fixed-body="true"
    :close-on-escape="!selectionDrawerOpen"
    @close="handleClose"
  >
    <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900" @keydown.esc.window="selectionDrawerOpen = false">
      <main class="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4">
        <div class="mx-auto mb-3 flex max-w-6xl items-center justify-between text-xs text-gray-600 dark:text-gray-300">
          <span>{{ selectedCount }} item(s) selected for disposal report</span>
          <span>Edit directly in the report preview</span>
        </div>

        <article class="mx-auto w-full max-w-6xl bg-white p-6 font-serif text-[12px] leading-tight text-black shadow-sm">
          <header class="mb-3">
            <p class="text-right italic">Appendix 74</p>
            <h1 class="mt-1 text-center text-[16px] font-bold uppercase">Inventory and Inspection Report of Unserviceable Property</h1>
            <p class="mt-1 text-center">
              As at
              <input v-model="form.report_date" type="date" class="iirup-preview-input w-36 text-center" aria-label="Report date" />
            </p>
          </header>

          <section class="grid grid-cols-[3fr_1fr] gap-4">
            <label class="iirup-info-line">
              <strong>Entity Name:</strong>
              <input v-model="form.entity_name" class="iirup-preview-input flex-1" aria-label="Entity name" />
            </label>
            <label class="iirup-info-line">
              <strong>Fund Cluster:</strong>
              <input v-model="form.fund_cluster" class="iirup-preview-input flex-1" aria-label="Fund cluster" />
            </label>
          </section>

          <section class="mt-3 grid grid-cols-3 gap-3 text-center">
            <label class="iirup-officer-field">
              <input v-model="form.accountable_officer_name" class="iirup-preview-input w-full text-center font-bold" aria-label="Name of accountable officer" />
              <span>(Name of Accountable Officer)</span>
            </label>
            <label class="iirup-officer-field">
              <input v-model="form.accountable_officer_designation" class="iirup-preview-input w-full text-center" aria-label="Accountable officer designation" />
              <span>(Designation)</span>
            </label>
            <label class="iirup-officer-field">
              <input v-model="form.accountable_officer_station" class="iirup-preview-input w-full text-center" aria-label="Accountable officer station" />
              <span>(Station)</span>
            </label>
          </section>

          <section class="mt-4" aria-label="Editable IIRUP inventory and inspection details">
            <h2 class="mb-1 text-center text-[13px] font-bold uppercase">Inventory and Inspection Details</h2>
            <div v-if="selectedPreviewGroups.length === 0" class="mb-2 border border-dashed border-gray-400 p-3 text-center text-xs text-gray-600">
              Select items in the left panel to add them to this report.
            </div>
            <div v-for="group in selectedPreviewGroups" :key="group.category_id" class="mb-2 overflow-x-auto">
              <p class="mb-1 text-xs font-bold">{{ group.category_name }}</p>
              <table class="min-w-[1900px] table-fixed border-collapse text-xs">
                <thead>
                  <tr>
                    <th colspan="10" class="iirup-preview-th">INVENTORY</th>
                    <th colspan="8" class="iirup-preview-th">INSPECTION AND DISPOSAL</th>
                    <th rowspan="4" class="iirup-preview-th w-9" aria-hidden="true"></th>
                  </tr>
                  <tr>
                    <th rowspan="2" class="iirup-preview-th">Date Acquired</th>
                    <th rowspan="2" class="iirup-preview-th">Particulars / Articles</th>
                    <th rowspan="2" class="iirup-preview-th">Property No.</th>
                    <th rowspan="2" class="iirup-preview-th">Qty</th>
                    <th rowspan="2" class="iirup-preview-th">Unit Cost</th>
                    <th rowspan="2" class="iirup-preview-th">Total Cost</th>
                    <th rowspan="2" class="iirup-preview-th">Accumulated Depreciation</th>
                    <th rowspan="2" class="iirup-preview-th">Accumulated Impairment Losses</th>
                    <th rowspan="2" class="iirup-preview-th">Carrying Amount</th>
                    <th rowspan="2" class="iirup-preview-th">Remarks</th>
                    <th colspan="5" class="iirup-preview-th">DISPOSAL</th>
                    <th rowspan="2" class="iirup-preview-th">Appraised Value</th>
                    <th colspan="2" class="iirup-preview-th">RECORD OF SALES</th>
                  </tr>
                  <tr>
                    <th class="iirup-preview-th">Sale</th>
                    <th class="iirup-preview-th">Transfer</th>
                    <th class="iirup-preview-th">Destruction</th>
                    <th class="iirup-preview-th">Others (Specify)</th>
                    <th class="iirup-preview-th">Total</th>
                    <th class="iirup-preview-th">OR No.</th>
                    <th class="iirup-preview-th">Amount</th>
                  </tr>
                  <tr>
                    <th v-for="(column, index) in previewColumns" :key="column.key" class="iirup-preview-th font-normal">({{ index + 1 }})</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in group.items" :key="item.item_id">
                    <td v-for="column in previewColumns" :key="column.key" class="iirup-preview-td" :style="{ width: column.width }">
                      <textarea
                        v-if="column.type === 'textarea'"
                        v-model="editableRows[item.item_id][column.key]"
                        rows="2"
                        class="iirup-preview-input min-w-[90px]"
                        :aria-label="`${column.label} for ${item.item_name}`"
                      />
                      <input
                        v-else
                        v-model="editableRows[item.item_id][column.key]"
                        :type="column.type"
                        :min="column.type === 'number' ? 0 : undefined"
                        :step="column.type === 'number' ? 'any' : undefined"
                        class="iirup-preview-input min-w-[70px]"
                        :aria-label="`${column.label} for ${item.item_name}`"
                      />
                    </td>
                    <td class="iirup-preview-td text-center">
                      <button
                        type="button"
                        class="inline-flex h-6 w-6 items-center justify-center rounded text-gray-500 hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                        :aria-label="`Remove ${item.item_name} from report`"
                        title="Remove item"
                        @click="toggleItem(item.item_id, false)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="mt-5 grid grid-cols-2 gap-4 border border-black p-3 text-xs">
            <div>
              <p class="mb-4">I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.</p>
              <div class="grid grid-cols-2 gap-4">
                <div class="iirup-signatory">
                  <span class="mb-2 block">Requested by:</span>
                  <input v-model="form.accountable_officer_name" class="iirup-preview-input w-full text-center font-bold" aria-label="Requested by name" />
                  <span>Signature over Printed Name of Accountable Officer</span>
                  <input v-model="form.accountable_officer_designation" class="iirup-preview-input mt-2 w-full text-center" aria-label="Requested by designation" />
                  <span>(Designation of Accountable Officer)</span>
                </div>
                <div class="iirup-signatory">
                  <span class="mb-2 block">Approved by:</span>
                  <input v-model="form.approving_official_name" required class="iirup-preview-input w-full text-center font-bold" aria-label="Approving official name" />
                  <span>Signature over Printed Name of Authorized Official</span>
                  <input v-model="form.approving_official_designation" class="iirup-preview-input mt-2 w-full text-center" aria-label="Approving official designation" />
                  <span>(Designation of Authorized Official)</span>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="iirup-signatory">
                <span class="mb-3 block">I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.</span>
                <input v-model="form.inspection_officer_name" required class="iirup-preview-input w-full text-center font-bold" aria-label="Inspection officer name" />
                <span>Signature over Printed Name of Inspection Officer</span>
                <input v-model="form.inspection_designation" class="iirup-preview-input iirup-preview-input--plain mt-2 w-full text-center" aria-label="Inspection officer designation" />
              </div>
              <div class="iirup-signatory">
                <span class="mb-3 block">
                  I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this
                  <input v-model="form.witness_disposition_day" class="iirup-preview-input iirup-certification-day" aria-label="Witnessed disposition day" />
                  day of
                  <input v-model="form.witness_disposition_date" class="iirup-preview-input iirup-certification-date" aria-label="Witnessed disposition date" />.
                </span>
                <input v-model="form.witness_name" required class="iirup-preview-input w-full text-center font-bold" aria-label="Witness name" />
                <span>Signature over Printed Name of Witness</span>
              </div>
            </div>
          </section>
        </article>

        <p v-if="error" class="mx-auto mt-3 max-w-6xl text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
      </main>
    </div>

    <template #footer>
    <div class="flex justify-end gap-2">
      <button
        type="button"
        class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
        :disabled="generating || previewing"
        @click="handleClose"
      >
        Cancel
      </button>
      <button
        type="button"
        class="rounded-md border border-purple-200 bg-white px-3 py-2 text-xs font-semibold text-purple-700 shadow-sm hover:bg-purple-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-purple-500/30 dark:bg-gray-800 dark:text-purple-300 dark:hover:bg-purple-500/10"
        :disabled="generating || previewing || !canGenerate"
        @click="previewIIRUP"
      >
        {{ previewing ? 'Generating…' : 'Preview IIRUP' }}
      </button>
      <button
        type="submit"
        class="rounded-md border border-purple-200 bg-purple-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-purple-500/30 dark:hover:bg-purple-700/80"
        :disabled="generating || previewing || !canGenerate"
        @click="submit"
      >
        {{ generating ? 'Generating…' : 'Generate IIRUP PDF' }}
      </button>
    </div>
    </template>
    <template #overlay>
      <button
        type="button"
        class="absolute left-0 top-26 z-[100001] flex items-center gap-2 rounded-r-md border border-l-0 border-purple-200 bg-white px-3 py-3 text-xs font-semibold text-purple-700 shadow-lg transition hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 dark:border-purple-500/30 dark:bg-gray-800 dark:text-purple-300 dark:hover:bg-purple-500/10"
        :aria-expanded="selectionDrawerOpen"
        aria-controls="iirup-selection-drawer"
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
        <span>{{ selectedCount }} selected</span>
      </button>

      <Transition name="iirup-fade">
        <div
          v-if="selectionDrawerOpen"
          class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
          aria-hidden="true"
          @click="selectionDrawerOpen = false"
        />
      </Transition>

      <Transition name="iirup-drawer">
        <aside
          v-if="selectionDrawerOpen"
          id="iirup-selection-drawer"
          class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
          aria-label="Select disposal-eligible items"
        >
          <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Select disposal-eligible items</h3>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ selectedCount }} item(s) selected</p>
            </div>
            <button
              type="button"
              class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
              aria-label="Close item selection drawer"
              @click="selectionDrawerOpen = false"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div
            v-if="loadingItems"
            class="min-h-0 flex-1 space-y-2 overflow-hidden p-3 dark:bg-gray-900/5"
            aria-label="Loading disposal-eligible items"
            aria-busy="true"
          >
            <div
              v-for="row in 4"
              :key="row"
              class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center gap-2 px-3 py-2">
                <div class="h-3.5 w-3.5 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
                <div class="h-3 w-28 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                <div class="ml-auto h-2.5 w-8 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
              </div>
              <div class="space-y-2 border-t border-gray-100 px-3 py-2 dark:border-gray-700">
                <div v-for="itemRow in 2" :key="itemRow" class="flex items-center gap-2">
                  <div class="h-3.5 w-3.5 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
                  <div class="h-2.5 w-24 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                  <div class="ml-auto h-2.5 w-12 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                </div>
              </div>
            </div>
          </div>
          <div v-else-if="eligibleByCategory.length === 0" class="m-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            No disposal-eligible items found.
          </div>
          <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3 dark:bg-gray-900/5">
            <div
              v-for="group in eligibleByCategory"
              :key="group.category_id"
              class="rounded-md border border-gray-200 dark:border-gray-700"
            >
              <button
                type="button"
                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs font-semibold text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                @click="toggleCategory(group.category_id)"
              >
                <span class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    :checked="isCategoryFullySelected(group)"
                    :indeterminate="isCategoryPartiallySelected(group)"
                    class="h-3.5 w-3.5 rounded-md border-gray-300 text-purple-600 focus:ring-purple-500 dark:border-gray-700 dark:bg-gray-800"
                    @click.stop="toggleCategorySelection(group)"
                  />
                  <span>{{ group.category_name }}</span>
                  <span class="text-[10px] text-gray-500 dark:text-gray-400">({{ group.items.length }})</span>
                </span>
                <svg
                  class="h-3.5 w-3.5 text-gray-400 transition-transform"
                  :class="{ 'rotate-180': expandedCategories.includes(group.category_id) }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
              </button>
              <div v-if="expandedCategories.includes(group.category_id)" class="border-t border-gray-100 dark:border-gray-700">
                <div v-if="group.items.length === 0" class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                  No disposal-eligible items found for this category.
                </div>
                <table v-else class="w-full text-left text-[11px]">
                  <thead>
                    <tr class="border-b border-gray-100 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                      <th class="w-8 px-2 py-1.5" />
                      <th class="px-2 py-1.5">Item No.</th>
                      <th class="px-2 py-1.5">Particulars / Articles</th>
                      <th class="px-2 py-1.5 text-right">Qty</th>
                      <th class="px-2 py-1.5 text-right">Unit Cost</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    <tr
                      v-for="item in group.items"
                      :key="item.item_id"
                      class="hover:bg-gray-50/40 dark:hover:bg-white/5"
                    >
                      <td class="px-2 py-1.5">
                        <input
                          type="checkbox"
                          :checked="selectedIds.includes(item.item_id)"
                          class="h-3.5 w-3.5 rounded-md border-gray-300 text-purple-600 focus:ring-purple-500 dark:border-gray-700 dark:bg-gray-800"
                          @change="toggleItem(item.item_id, $event.target.checked)"
                        />
                      </td>
                      <td class="px-2 py-1.5 font-mono text-gray-900 dark:text-white">{{ item.inventory_item_no || '#' + item.item_id }}</td>
                      <td class="px-2 py-1.5 text-gray-900 dark:text-white">{{ item.item_name }}</td>
                      <td class="px-2 py-1.5 text-right text-gray-900 dark:text-white">{{ item.quantity }}</td>
                      <td class="px-2 py-1.5 text-right text-gray-900 dark:text-white">{{ formatMoney(item.unit_cost) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </aside>
      </Transition>
    </template>
  </Modal>

  <IirupPreviewModal
    :open="previewOpen"
    :pdf-url="previewPdfUrl"
    @close="closePreview"
    @download="downloadFromPreview"
  />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import Modal from '../../../components/ui/dialogs/Modal.vue';
import IirupPreviewModal from './IirupPreviewModal.vue';
import api from '../../../lib/axios';

const props = defineProps({
  open: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  selectedIds: { type: Array, default: () => [] },
  selectedCount: { type: Number, default: 0 },
});

const emit = defineEmits(['close']);

const authStore = useAuthStore();

const generating = ref(false);
const previewing = ref(false);
const error = ref('');
const previewOpen = ref(false);
const previewPdfUrl = ref('');
let previewBlob = null;
const loadingItems = ref(false);
const selectionDrawerOpen = ref(false);
const expandedCategories = ref([]);
const eligibleByCategory = ref([]);
const editableRows = ref({});
const originalRows = ref({});

const internalSelectedIds = ref([]);

const form = ref({
  report_date: new Date().toISOString().slice(0, 10),
  entity_name: 'Dian-ay National High School',
  fund_cluster: '',
  accountable_officer_name: '',
  accountable_officer_designation: 'SCHOOL PROPERTY CUSTODIAN',
  accountable_officer_station: '',
  approving_official_name: '',
  approving_official_designation: 'PRINCIPAL I',
  inspection_officer_name: '',
  inspection_designation: '',
  witness_name: '',
  witness_disposition_day: '',
  witness_disposition_date: '',
});

const previewColumns = [
  { key: 'date_acquired', label: 'Date Acquired', type: 'date', width: '115px' },
  { key: 'item_name', label: 'Particulars / Articles', type: 'text', width: '150px' },
  { key: 'inventory_item_no', label: 'Property No.', type: 'text', width: '120px' },
  { key: 'quantity', label: 'Qty', type: 'number', width: '75px' },
  { key: 'unit_cost', label: 'Unit Cost', type: 'number', width: '95px' },
  { key: 'total_cost', label: 'Total Cost', type: 'number', width: '95px' },
  { key: 'accumulated_depreciation', label: 'Accumulated Depreciation', type: 'number', width: '115px' },
  { key: 'accumulated_impairment', label: 'Accumulated Impairment Losses', type: 'number', width: '125px' },
  { key: 'carrying_amount', label: 'Carrying Amount', type: 'number', width: '100px' },
  { key: 'remarks', label: 'Remarks', type: 'textarea', width: '140px' },
  { key: 'disposal_sale', label: 'Sale', type: 'number', width: '90px' },
  { key: 'disposal_transfer', label: 'Transfer', type: 'number', width: '90px' },
  { key: 'disposal_destruction', label: 'Destruction', type: 'number', width: '90px' },
  { key: 'disposal_others', label: 'Others (Specify)', type: 'textarea', width: '125px' },
  { key: 'disposal_total', label: 'Total Disposal', type: 'number', width: '95px' },
  { key: 'appraised_value', label: 'Appraised Value', type: 'number', width: '100px' },
  { key: 'or_no', label: 'OR No.', type: 'text', width: '100px' },
  { key: 'sales_amount', label: 'Sales Amount', type: 'number', width: '100px' },
];

const selectedIds = computed({
  get: () => internalSelectedIds.value,
  set: (val) => {
    internalSelectedIds.value = val;
  },
});

const selectedCount = computed(() => internalSelectedIds.value.length);
const selectedPreviewGroups = computed(() => eligibleByCategory.value
  .map((group) => ({
    ...group,
    items: group.items.filter((item) => internalSelectedIds.value.includes(item.item_id)),
  }))
  .filter((group) => group.items.length > 0));

const canGenerate = computed(() =>
  !loadingItems.value &&
  internalSelectedIds.value.length > 0 &&
  form.value.approving_official_name.trim() !== '' &&
  form.value.inspection_officer_name.trim() !== '' &&
  form.value.witness_name.trim() !== '',
);

function formatMoney(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toggleCategory(catId) {
  const idx = expandedCategories.value.indexOf(catId);
  if (idx === -1) {
    expandedCategories.value.push(catId);
  } else {
    expandedCategories.value.splice(idx, 1);
  }
}

function isCategoryFullySelected(group) {
  return group.items.length > 0 && group.items.every((item) => internalSelectedIds.value.includes(item.item_id));
}

function isCategoryPartiallySelected(group) {
  return group.items.some((item) => internalSelectedIds.value.includes(item.item_id)) && !isCategoryFullySelected(group);
}

function toggleCategorySelection(group) {
  if (isCategoryFullySelected(group)) {
    selectedIds.value = internalSelectedIds.value.filter((id) => !group.items.some((item) => item.item_id === id));
  } else {
    const newIds = group.items.map((item) => item.item_id).filter((id) => !internalSelectedIds.value.includes(id));
    selectedIds.value = [...internalSelectedIds.value, ...newIds];
  }
}

function toggleItem(itemId, checked) {
  if (checked) {
    if (!internalSelectedIds.value.includes(itemId)) {
      selectedIds.value = [...internalSelectedIds.value, itemId];
    }
  } else {
    selectedIds.value = internalSelectedIds.value.filter((id) => id !== itemId);
  }
}

function makeEditableRow(source) {
  const quantity = Number(source.quantity ?? 0);
  const unitCost = Number(source.unit_cost ?? 0);
  const totalCost = Number(source.total_cost ?? quantity * unitCost);

  return {
    date_acquired: source.date_acquired ?? '',
    item_name: source.item_name ?? '',
    inventory_item_no: source.inventory_item_no ?? '',
    quantity,
    unit_cost: unitCost,
    total_cost: totalCost,
    accumulated_depreciation: Number(source.accumulated_depreciation ?? 0),
    accumulated_impairment: Number(source.accumulated_impairment ?? source.accumulated_impairment_losses ?? 0),
    carrying_amount: Number(source.carrying_amount ?? totalCost),
    remarks: source.remarks ?? source.description ?? source.status ?? '',
    appraised_value: Number(source.appraised_value ?? 0),
    disposal_sale: Number(source.disposal_sale ?? 0),
    disposal_transfer: Number(source.disposal_transfer ?? 0),
    disposal_destruction: Number(source.disposal_destruction ?? 0),
    disposal_others: source.disposal_others ?? '',
    disposal_total: Number(source.disposal_total ?? 0),
    or_no: source.or_no ?? '',
    sales_amount: Number(source.sales_amount ?? 0),
  };
}

function resetItem(itemId) {
  const key = String(itemId);
  if (originalRows.value[key]) {
    editableRows.value[key] = { ...originalRows.value[key] };
  }
}

async function fetchEligibleItems() {
  loadingItems.value = true;
  try {
    const collected = [];
    for (const ws of ['disposed', 'ready_to_dispose']) {
      let page = 1;
      let lastPage = 1;
      do {
        const { data } = await api.get('/custodian/inventory', {
          params: { workspace: ws, [ws === 'all' ? 'all_page' : `${ws}_page`]: page },
        });
        const pageData = ws === 'all' ? data.allInventoryPage : (data.inventoryPages ?? {})[ws];
        const groups = pageData?.data ?? [];
        for (const group of groups) {
          for (const source of group.sourceItems ?? []) {
            if (source.status === 'disposed' || source.status === 'ready_to_dispose') {
              const sourceItem = {
                ...source,
                item_id: source.item_id,
                inventory_item_no: source.inventory_item_no,
                item_name: source.item_name,
                quantity: source.quantity ?? 0,
                carrying_amount: (source.quantity ?? 0) * (source.unit_cost ?? 0),
                category_id: source.category_id ?? source.category?.category_id,
                category_name: source.category?.category_name ?? 'Uncategorized',
                status: source.status,
              };
              collected.push(sourceItem);
            }
          }
        }
        lastPage = Number(pageData?.last_page ?? 1);
        page++;
      } while (page <= lastPage);
    }

    // Group by category
    const byCategory = {};
    for (const item of collected) {
      const catId = item.category_id;
      if (!byCategory[catId]) {
        byCategory[catId] = {
          category_id: catId,
          category_name: item.category_name,
          items: [],
        };
      }
      byCategory[catId].items.push(item);
    }

    const groups = Object.values(byCategory);
    const newOriginalRows = {};
    const newEditableRows = {};
    for (const item of collected) {
      const key = String(item.item_id);
      const row = makeEditableRow(item);
      newOriginalRows[key] = row;
      newEditableRows[key] = { ...row };
    }
    originalRows.value = newOriginalRows;
    editableRows.value = newEditableRows;

    eligibleByCategory.value = groups.sort((a, b) => a.category_name.localeCompare(b.category_name));
  } catch (err) {
    console.error('Failed to load disposal-eligible items', err);
    error.value = 'Failed to load disposal-eligible items. Please try again.';
  } finally {
    loadingItems.value = false;
  }
}

function prefill() {
  const user = authStore.user;
  if (user) {
    const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
    form.value.accountable_officer_name = fullName || user.username || '';
    form.value.accountable_officer_station = user.building
      ? `${user.building}${user.room ? ', Room ' + user.room : ''}`
      : 'Dian-ay National High School';
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      error.value = '';
      generating.value = false;
      form.value.report_date = new Date().toISOString().slice(0, 10);
      internalSelectedIds.value = [...props.selectedIds];
      prefill();
      expandedCategories.value = [];
      selectionDrawerOpen.value = false;
      originalRows.value = {};
      editableRows.value = {};
      eligibleByCategory.value = [];
      fetchEligibleItems();
    }
  },
  { immediate: true },
);

function handleClose() {
  if (generating.value || previewing.value) return;
  closePreview();
  emit('close');
}

async function generatePdfBlob() {
  const payload = {
    report_date: form.value.report_date,
    entity_name: form.value.entity_name,
    fund_cluster: form.value.fund_cluster,
    accountable_officer_name: form.value.accountable_officer_name,
    accountable_officer_designation: form.value.accountable_officer_designation,
    accountable_officer_station: form.value.accountable_officer_station,
    approving_official_name: form.value.approving_official_name,
    approving_official_designation: form.value.approving_official_designation,
    inspection_officer_name: form.value.inspection_officer_name,
    inspection_designation: form.value.inspection_designation,
    witness_name: form.value.witness_name,
    witness_disposition_day: form.value.witness_disposition_day,
    witness_disposition_date: form.value.witness_disposition_date,
    selected_ids: internalSelectedIds.value,
    overrides: Object.fromEntries(internalSelectedIds.value.map((itemId) => [
      itemId,
      { ...editableRows.value[String(itemId)] },
    ])),
  };

  const response = await api.post('/custodian/inventory/iirup-pdf', payload, {
    responseType: 'blob',
    skipToast: true,
  });

  return new Blob([response.data], { type: 'application/pdf' });
}

async function previewIIRUP() {
  if (!canGenerate.value || previewing.value || generating.value) return;
  previewing.value = true;
  error.value = '';

  try {
    const blob = await generatePdfBlob();
    if (previewPdfUrl.value) {
      window.URL.revokeObjectURL(previewPdfUrl.value);
    }
    previewBlob = blob;
    previewPdfUrl.value = window.URL.createObjectURL(blob);
    previewOpen.value = true;
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'Failed to generate IIRUP PDF. Please try again.';
  } finally {
    previewing.value = false;
  }
}

function closePreview() {
  previewOpen.value = false;
  if (previewPdfUrl.value) {
    window.URL.revokeObjectURL(previewPdfUrl.value);
    previewPdfUrl.value = '';
  }
  previewBlob = null;
}

function downloadFromPreview() {
  if (!previewBlob) return;
  const url = window.URL.createObjectURL(previewBlob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `IIRUP_${new Date().toISOString().slice(0, 10)}.pdf`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}

async function submit() {
  if (!canGenerate.value || generating.value || previewing.value) return;
  generating.value = true;
  error.value = '';

  try {
    const blob = await generatePdfBlob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `IIRUP_${new Date().toISOString().slice(0, 10)}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    emit('close');
  } catch (err) {
    error.value = err?.response?.data?.message ?? 'Failed to generate IIRUP PDF. Please try again.';
  } finally {
    generating.value = false;
  }
}
</script>

<style scoped>
.iirup-preview-input {
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

.iirup-preview-input:focus {
  border-bottom-color: #7c3aed;
  outline: none;
  box-shadow: 0 1px 0 #7c3aed;
}

.iirup-preview-input--plain,
.iirup-preview-input--plain:focus {
  border-bottom-color: transparent;
  box-shadow: none;
}

.iirup-certification-day {
  display: inline-block;
  width: 3.5em;
  text-align: center;
}

.iirup-certification-date {
  display: inline-block;
  width: 10em;
  text-align: center;
}

.iirup-info-line {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.iirup-officer-field {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.iirup-officer-field span,
.iirup-signatory > span {
  font-size: 12px;
  font-style: italic;
}

.iirup-preview-th,
.iirup-preview-td {
  border: 1px solid #000;
  padding: 2px;
  vertical-align: middle;
  overflow-wrap: anywhere;
}

.iirup-preview-th {
  text-align: center;
  font-weight: 700;
  font-size: 12px;
}

.iirup-preview-td .iirup-preview-input {
  width: 100%;
  font-size: 11px;
}

.iirup-signatory {
  display: flex;
  flex-direction: column;
  text-align: center;
}

.iirup-drawer-enter-active,
.iirup-drawer-leave-active {
  transition: transform 0.3s ease;
}

.iirup-drawer-enter-from,
.iirup-drawer-leave-to {
  transform: translateX(-100%);
}

.iirup-fade-enter-active,
.iirup-fade-leave-active {
  transition: opacity 0.25s ease;
}

.iirup-fade-enter-from,
.iirup-fade-leave-to {
  opacity: 0;
}
</style>