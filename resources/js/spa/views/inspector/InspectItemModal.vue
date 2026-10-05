<template>
  <QrScannerModal
    :open="open"
    title="Inspect Item"
    subtitle="Scan the item's QR label or enter its token to load the flagged record."
    :resolved="Boolean(record)"
    :lookup-error="lookupError"
    :loading="looking"
    viewport-id="inspector-qr-camera-viewport"
    token-placeholder="inventory-item:…"
    @close="close"
    @lookup="lookup"
  >
    <div class="compact-modal-body">
      <p
        v-if="submitError"
        role="alert"
        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
      >
        {{ submitError }}
      </p>

      <section
        class="rounded-md border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        aria-live="polite"
      >
        <div class="mb-2.5 flex items-center gap-1.5 text-emerald-800 dark:text-emerald-300">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <h3 class="text-sm font-semibold">Flagged item found</h3>
        </div>
        <dl class="space-y-1.5 text-xs">
          <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Item no.</dt>
            <dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ record?.inventory_item_no ?? `#${record?.inventory_id}` }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Name</dt>
            <dd class="text-right font-medium text-gray-900 dark:text-white">{{ record?.item_name }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Category</dt>
            <dd class="text-right text-gray-700 dark:text-gray-300">{{ record?.category ?? 'Uncategorized' }}</dd>
          </div>
          <div v-if="record?.serial_number" class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Serial no.</dt>
            <dd class="font-mono text-gray-700 dark:text-gray-300">{{ record.serial_number }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Quantity</dt>
            <dd class="font-semibold text-gray-900 dark:text-white">{{ format(record?.quantity) }} {{ record?.unit }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-gray-500 dark:text-gray-400">Returns to</dt>
            <dd class="text-right font-medium capitalize text-gray-900 dark:text-white">
              {{ (record?.status_before ?? '').replace(/_/g, ' ') }}
            </dd>
          </div>
          <div v-if="record?.reason" class="flex justify-between gap-3">
            <dt class="shrink-0 text-gray-500 dark:text-gray-400">Flagged because</dt>
            <dd class="text-right text-gray-700 dark:text-gray-300">{{ record.reason }}</dd>
          </div>
        </dl>
      </section>

      <div class="space-y-1">
        <label class="compact-label" for="inspection-findings">
          Inspection findings <span class="font-normal text-gray-500">(optional)</span>
        </label>
        <textarea
          id="inspection-findings"
          v-model="findings"
          rows="3"
          maxlength="1000"
          :disabled="busy"
          placeholder="Record what was verified, any defect found, or the condition observed."
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        />
      </div>

      <div class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-2.5 dark:border-gray-700">
        <button
          type="button"
          class="compact-btn compact-btn--outline"
          :disabled="busy"
          @click="scanAnother"
        >
          Scan Another
        </button>
        <button
          type="button"
          class="compact-btn compact-btn--primary disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="busy"
          @click="markInspected"
        >
          {{ busy ? 'Saving…' : 'Mark as Inspected' }}
        </button>
      </div>
    </div>
  </QrScannerModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '../../lib/axios';
import QrScannerModal from '../../components/ui/qr/QrScannerModal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'inspected']);

const record = ref(null);
const findings = ref('');
const looking = ref(false);
const busy = ref(false);
const lookupError = ref('');
const submitError = ref('');

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function reset() {
  record.value = null;
  findings.value = '';
  lookupError.value = '';
  submitError.value = '';
}

function close() {
  if (busy.value) return;
  reset();
  emit('close');
}

async function lookup(token) {
  const normalized = String(token ?? '').trim();
  if (!normalized || looking.value) return;

  looking.value = true;
  lookupError.value = '';
  try {
    const { data } = await api.get('/inspector/inspection/qr-lookup', {
      params: { token: normalized },
    });

    if (!data.found) {
      lookupError.value = data.message ?? 'No flagged item found for this code.';
      return;
    }

    record.value = data;
  } catch (requestError) {
    lookupError.value = requestError?.response?.data?.message ?? 'Could not look up this code. Try again.';
  } finally {
    looking.value = false;
  }
}

function scanAnother() {
  if (busy.value) return;
  reset();
}

async function markInspected() {
  if (!record.value || busy.value) return;

  busy.value = true;
  submitError.value = '';
  try {
    const { data } = await api.post(
      `/inspector/inspection/${record.value.inspection_id}/mark-inspected`,
      { finding_notes: findings.value.trim() || null },
      // The parent view raises the "Inspection recorded" toast from the emitted
      // event, so the interceptor must stay quiet to avoid a duplicate.
      { skipToast: true },
    );
    reset();
    emit('inspected', data);
  } catch (requestError) {
    submitError.value = requestError?.response?.data?.message ?? 'Could not mark the item as inspected.';
  } finally {
    busy.value = false;
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) reset();
  },
);
</script>