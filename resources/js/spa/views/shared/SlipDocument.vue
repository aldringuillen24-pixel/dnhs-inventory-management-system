<template>
  <Modal
    :open="open"
    :title="title"
    :subtitle="subtitle"
    max-width="max-w-6xl"
    @close="$emit('close')"
  >
    <div v-if="slipData" class="space-y-4">
      <div class="grid min-w-0 gap-5 lg:grid-cols-2">
        <!-- Left: Form grids -->
        <section class="min-w-0 space-y-4 rounded-md border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-800/40">
          <h3 class="text-sm font-bold text-gray-900 dark:text-white">Release details</h3>

          <div class="grid gap-3 sm:grid-cols-2">
            <label class="slip-field sm:col-span-2">
              <span>Recipient name</span>
              <input v-model="slipFields.recipient_name" type="text" maxlength="150" />
            </label>
            <label class="slip-field">
              <span>Department / Unit</span>
              <input v-model="slipFields.department_unit" type="text" maxlength="150" />
            </label>
            <label class="slip-field">
              <span>Designation</span>
              <input v-model="slipFields.designation" type="text" maxlength="150" />
            </label>
            <label class="slip-field">
              <span>Expected return date</span>
              <input v-model="slipFields.expected_return_date" type="date" />
            </label>
            <label class="slip-field">
              <span>Actual return date</span>
              <input v-model="slipFields.actual_return_date" type="date" />
            </label>
          </div>

          <fieldset>
            <legend class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">Purpose of release</legend>
            <div class="grid gap-2 sm:grid-cols-2">
              <label v-for="purpose in purposes" :key="purpose" class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                <input
                  type="radio"
                  name="release-purpose"
                  :checked="slipFields.purpose === purpose"
                  @change="slipFields.purpose = purpose"
                />
                {{ purpose }}
              </label>
            </div>
          </fieldset>

          <fieldset class="space-y-3">
            <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Condition upon release</legend>
            <label v-for="line in lines" :key="line.item_id" class="slip-field">
              <span>{{ line.item_no }} — {{ line.description }}</span>
              <input
                v-model="slipFields.conditions[line.item_id]"
                type="text"
                maxlength="100"
                placeholder="Enter condition"
              />
            </label>
          </fieldset>

          <fieldset class="space-y-3">
            <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Signatories</legend>
            <div v-for="signatory in signatories" :key="signatory.key" class="grid gap-2 rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
              <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 sm:col-span-2">{{ signatory.label }}:</p>
              <label class="slip-field">
                <span>Printed name</span>
                <input
                  v-model="slipFields[`${signatory.key}_name`]"
                  type="text"
                  maxlength="150"
                />
              </label>
              <label class="slip-field sm:col-span-2">
                <span>Date</span>
                <input v-model="slipFields[`${signatory.key}_date`]" type="date" />
              </label>
            </div>
          </fieldset>
        </section>

        <!-- Right: PDF preview with iframe -->
        <section class="slip-preview">
          <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">PDF preview</h3>
            <span v-if="previewLoading && previewUrl" class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400" role="status">
              <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Updating preview…
            </span>
          </div>
          <div class="relative h-[75vh] min-h-[400px]">
            <div v-if="previewLoading && !previewUrl" class="absolute inset-0 flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 z-10">
              <svg class="animate-spin h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
            </div>
            <iframe
              v-if="previewUrl"
              :src="previewUrl"
              class="w-full h-full border border-gray-200 dark:border-gray-700 rounded-md bg-white"
              @load="onIframeLoad"
            />
            <div v-if="!previewUrl && !previewLoading" class="absolute inset-0 flex items-center justify-center bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-md">
              <p class="text-gray-500 dark:text-gray-400 text-sm">No preview available. Click "Update PDF Preview" to generate.</p>
            </div>
            <div v-if="previewError" class="absolute bottom-2 left-2 right-2 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-md p-2 text-xs text-red-700 dark:text-red-300">
              {{ previewError }}
            </div>
          </div>
          <div class="mt-2 flex gap-2">
            <button
              type="button"
              class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 disabled:opacity-50"
              :disabled="previewLoading"
              @click="loadPreview"
            >
              {{ previewLoading ? 'Updating…' : 'Update PDF Preview' }}
            </button>
            <button
              type="button"
              class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 disabled:opacity-50"
              :disabled="!previewUrl || previewLoading"
              @click="printSlip"
            >
              Print / Download
            </button>
          </div>
        </section>
      </div>

      <p v-if="error" class="text-sm text-red-600" role="alert">{{ error }}</p>

    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
          :disabled="primaryLoading"
          @click="$emit('back')"
        >
          ← Back
        </button>
        <button
          type="button"
          class="rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2 text-sm font-semibold text-white shadow-md hover:from-emerald-500 hover:to-teal-500 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="primaryLoading || primaryDisabled || previewLoading"
          @click="$emit('primary', slipFields)"
        >
          {{ primaryLoading ? 'Saving…' : primaryLabel }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Property Release Slip' },
  subtitle: { type: String, default: '' },
  docNo: { type: String, default: '' },
  documentDate: { type: String, default: '' },
  lines: { type: Array, default: () => [] },
  purposes: { type: Array, default: () => [] },
  requestId: { type: [Number, String], default: null },
  primaryDisabled: { type: Boolean, default: false },
  recipientName: { type: String, default: '' },
  custodianName: { type: String, default: '' },
  draftMode: { type: Boolean, default: true },
  primaryLabel: { type: String, default: 'Save Draft' },
});

const emit = defineEmits(['back', 'primary', 'close']);

const slipFields = ref({});
const slipData = ref({});
const previewUrl = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const primaryLoading = ref(false);
const error = ref('');
let previewDebounce = null;
let previewAbortController = null;
let skipFieldWatchSignature = null;

const signatories = [
  { key: 'released_by', label: 'Released By (Property Custodian)' },
  { key: 'received_by', label: 'Received By (Recipient)' },
  { key: 'approved_by', label: 'Approved By (Department Head / Supervisor)' },
];

const releasePurposes = [
  'Official Use (Off-site)',
  'Transfer / Relocation',
  'Repair / Maintenance',
  'Disposal',
];

const previewTextFields = [
  'recipient_name', 'department_unit', 'designation', 'purpose',
  'released_by_signature', 'released_by_name', 'received_by_signature',
  'received_by_name', 'approved_by_signature', 'approved_by_name',
];
const previewDateFields = [
  'expected_return_date', 'actual_return_date',
  'released_by_date', 'received_by_date', 'approved_by_date',
];

const purposeFields = computed(() => props.purposes.length > 0 ? props.purposes : releasePurposes);

function buildInitialFields() {
  const fields = {
    recipient_name: props.recipientName || '',
    department_unit: '',
    designation: '',
    purpose: '',
    conditions: {},
    expected_return_date: '',
    actual_return_date: '',
    released_by_signature: '',
    released_by_name: props.custodianName || '',
    released_by_date: '',
    received_by_signature: '',
    received_by_name: props.recipientName || '',
    received_by_date: '',
    approved_by_signature: '',
    approved_by_name: '',
    approved_by_date: '',
  };
  for (const line of props.lines) {
    fields.conditions[line.item_id] = '';
  }
  return fields;
}

function schedulePreview() {
  if (!props.open) {
    return;
  }

  clearTimeout(previewDebounce);
  previewAbortController?.abort();
  previewAbortController = null;
  previewLoading.value = true;
  previewDebounce = setTimeout(() => {
    previewDebounce = null;
    if (props.open) {
      loadPreview();
    }
  }, 300);
}

async function loadPreview() {
  clearTimeout(previewDebounce);
  previewDebounce = null;
  previewAbortController?.abort();
  const controller = new AbortController();
  previewAbortController = controller;
  previewLoading.value = true;
  previewError.value = '';

  try {
    const payload = {
      inventory_ids: props.lines.map((line) => line.item_id),
      slip_fields: slipFields.value,
    };
    let url;
    if (props.draftMode) {
      url = '/custodian/assignments/draft/preview';
      payload.inventory_quantities = Object.fromEntries(
        props.lines.map((line) => [line.item_id, line.qty]),
      );
      payload.document_date = props.documentDate;
    } else {
      if (props.requestId === null || props.requestId === '') {
        throw new Error('A request ID is required to preview this release slip.');
      }
      url = `/custodian/requests/${props.requestId}/slip-pdf?preview=1`;
    }

    const response = await api.post(url, payload, {
      responseType: 'blob',
      signal: controller.signal,
    });

    const blobUrl = URL.createObjectURL(response.data);
    if (controller.signal.aborted || previewAbortController !== controller) {
      URL.revokeObjectURL(blobUrl);
      return;
    }
    if (previewUrl.value) {
      URL.revokeObjectURL(previewUrl.value);
    }
    previewUrl.value = blobUrl;
    previewError.value = '';
  } catch (err) {
    if (controller.signal.aborted || previewAbortController !== controller) {
      return;
    }
    previewError.value = 'Failed to generate preview. Please try again.';
    console.error('Preview generation failed:', err);
  } finally {
    if (previewAbortController === controller) {
      previewAbortController = null;
      previewLoading.value = false;
    }
  }
}

function onIframeLoad() {
  // Iframe loaded successfully
}

function printSlip() {
  if (previewUrl.value) {
    // Open in new window for printing
    const printWindow = window.open(previewUrl.value, '_blank', 'noopener');
    if (printWindow) {
      printWindow.focus();
      // Give it a moment to load, then print
      setTimeout(() => {
        printWindow.print();
      }, 500);
    }
  }
}

function cleanupPreview() {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
  }
}

async function handlePrimary() {
  primaryLoading.value = true;
  error.value = '';
  try {
    await emit('primary');
  } catch (err) {
    error.value = err?.message ?? 'Operation failed.';
  } finally {
    primaryLoading.value = false;
  }
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      slipFields.value = buildInitialFields();
      skipFieldWatchSignature = JSON.stringify(slipFields.value);
      slipData.value = {
        docNo: props.docNo,
        documentDate: props.documentDate,
      };
      previewUrl.value = null;
      previewLoading.value = false;
      previewError.value = '';
      error.value = '';
      loadPreview();
    } else {
      clearTimeout(previewDebounce);
      previewDebounce = null;
      previewAbortController?.abort();
      previewAbortController = null;
      previewLoading.value = false;
      cleanupPreview();
    }
  },
  { immediate: true },
);

watch(
  () => JSON.stringify(slipFields.value),
  (signature) => {
    if (signature === skipFieldWatchSignature) {
      skipFieldWatchSignature = null;
      return;
    }
    skipFieldWatchSignature = null;
    schedulePreview();
  },
);

watch(
  () => [props.docNo, props.documentDate, props.lines],
  () => {
    if (props.open) {
      slipData.value = {
        docNo: props.docNo,
        documentDate: props.documentDate,
      };
    }
  },
);
</script>

<style scoped>
.slip-field {
  display: grid;
  gap: 4px;
  min-width: 0;
  color: #4b5563;
  font-size: 11px;
}
.slip-field input {
  min-width: 0;
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  padding: 7px 8px;
  color: #111827;
  font: inherit;
}
.slip-field input:read-only {
  background: #f9fafb;
  color: #6b7280;
}
.preview-scroll {
  height: 75vh;
  overflow: auto;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #e5e7eb;
  padding: 10px;
}
.slip-preview {
  position: sticky;
  top: 1rem;
  width: 520px;
  max-width: 100%;
  align-self: start;
}
@media (max-width: 1023px) {
  .slip-preview {
    position: static;
    width: 100%;
  }
}
</style>