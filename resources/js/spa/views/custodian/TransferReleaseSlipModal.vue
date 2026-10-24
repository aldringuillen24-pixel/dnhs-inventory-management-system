<template>
  <Modal
    :open="open"
    title="Property Release Slip"
    subtitle="Preview only — nothing is issued or transferred until the custodian confirms allocation."
    max-width="max-w-6xl"
    @close="close"
  >
    <form v-if="transfer" id="transfer-release-slip-form" @submit.prevent="saveDraft" @input="handleFieldInput">
      <section class="min-w-0 rounded-md border border-gray-200 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-800">
        <div class="sticky top-0 z-20 -mx-2 mb-2 flex flex-wrap items-center justify-between gap-2 border-b border-gray-300 bg-gray-100 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
          <h3 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h3>
          <div class="flex gap-1" aria-label="Preview format">
            <button
              type="button"
              class="rounded px-2.5 py-1 text-xs font-semibold"
              :class="previewMode === 'html' ? 'bg-white text-emerald-700 shadow-sm dark:bg-gray-700 dark:text-emerald-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
              :aria-pressed="previewMode === 'html'"
              @click="previewMode = 'html'"
            >
              HTML Preview
            </button>
            <button
              type="button"
              class="rounded px-2.5 py-1 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-40"
              :class="previewMode === 'pdf' ? 'bg-white text-emerald-700 shadow-sm dark:bg-gray-700 dark:text-emerald-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
              :aria-pressed="previewMode === 'pdf'"
              :disabled="previewBusy && !previewUrl"
              @click="selectPdfPreview"
            >
              PDF Preview
            </button>
          </div>
        </div>

        <div v-if="previewMode === 'pdf'" class="relative h-[65vh] min-h-[24rem] overflow-hidden rounded bg-white">
          <iframe v-if="previewUrl" :src="previewUrl" title="Property Release Slip PDF preview" class="h-full w-full border-0" />
          <div v-else class="flex h-full items-center justify-center text-sm text-gray-500" role="status">
            {{ previewBusy ? 'Generating PDF preview…' : 'PDF preview is not available.' }}
          </div>
          <p v-if="previewBusy && previewUrl" class="absolute right-2 top-2 z-10 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">Refreshing PDF preview…</p>
          <p v-else-if="!previewCurrent && previewUrl" class="absolute right-2 top-2 z-10 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">PDF preview will refresh after editing</p>
        </div>

        <div v-else class="min-w-0 overflow-x-auto rounded bg-gray-200 p-3 dark:bg-gray-900 sm:p-5">
          <article class="mx-auto min-w-[38rem] max-w-4xl bg-white p-6 font-serif text-[11px] leading-tight text-black shadow-sm sm:p-8">
            <header class="mb-4 text-center">
              <h1 class="text-[15px] font-bold">PROPERTY RELEASE SLIP</h1>
            </header>

            <div class="space-y-1">
              <p><strong>Document Control No.</strong> <span class="border-b border-black px-1">{{ documentControlNo }}</span></p>
              <p><strong>Date:</strong> <span class="border-b border-black px-1">{{ documentDate }}</span></p>
            </div>

            <section class="mt-4">
              <h2 class="mb-1 font-bold">RECIPIENT INFORMATION</h2>
              <p>Name of Requestor / Recipient: <input v-model="fields.recipient_name" required maxlength="150" aria-label="Recipient name" class="release-slip-input w-64" /></p>
              <p>Transferred From: <span class="border-b border-black px-1">{{ personName(transfer.user) }}</span></p>
              <p>Department / Unit: <input v-model="fields.department_unit" maxlength="150" aria-label="Department or unit" class="release-slip-input w-64" /></p>
              <p>Designation: <input v-model="fields.designation" maxlength="150" aria-label="Designation" class="release-slip-input w-64" /></p>
              <p class="mt-2">
                <strong>Purpose of Release:</strong>
                <label v-for="purpose in purposes" :key="purpose" class="mr-2 inline-flex items-center gap-0.5">
                  <span aria-hidden="true">[{{ purpose === transferPurpose ? 'X' : ' ' }}]</span>
                  <span>{{ purpose }}</span>
                </label>
              </p>
            </section>

            <section class="mt-4">
              <h2 class="font-bold">ITEM DETAILS</h2>
              <div class="overflow-x-auto">
                <table class="mt-1 w-full table-fixed border-collapse text-[9px]">
                  <thead>
                    <tr>
                      <th class="w-[7%] border border-black p-1 text-center align-top">ITEM NO.</th>
                      <th class="w-[14%] border border-black p-1 text-center align-top">PROPERTY</th>
                      <th class="w-[24%] border border-black p-1 text-center align-top">ITEM DESCRIPTION &amp; SERIAL NO.</th>
                      <th class="w-[8%] border border-black p-1 text-center align-top">QTY</th>
                      <th class="w-[13%] border border-black p-1 text-center align-top">UNIT COST</th>
                      <th class="w-[15%] border border-black p-1 text-center align-top">AMOUNT</th>
                      <th class="w-[19%] border border-black p-1 text-center align-top">CONDITION UPON RELEASE</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="border border-black p-1 text-center align-top">1</td>
                      <td class="border border-black p-1 text-center align-top">{{ transfer.item?.inventory_item_no ?? transfer.item?.item_id ?? '—' }}</td>
                      <td class="border border-black p-1 align-top">{{ itemDescription }}</td>
                      <td class="border border-black p-1 text-right align-top">{{ itemQuantity }}</td>
                      <td class="border border-black p-1 text-right align-top">PHP {{ formatAmount(unitCost) }}</td>
                      <td class="border border-black p-1 text-right align-top">PHP {{ formatAmount(lineAmount) }}</td>
                      <td class="border border-black p-1 align-top">
                        <textarea v-model="fields.condition_upon_release" rows="2" maxlength="1000" aria-label="Condition upon release" class="release-slip-input w-full resize-y" />
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6" class="border border-black p-1 font-bold">TOTAL: <span class="float-right">PHP {{ formatAmount(lineAmount) }}</span></td>
                      <td class="border border-black p-1"></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section class="mt-4">
              <h2 class="font-bold">RETURN INFORMATION (If temporary release)</h2>
              <p>Expected Return Date: <input v-model="fields.expected_return_date" type="date" aria-label="Expected return date" class="release-slip-input" /></p>
              <p>Actual Return Date: <input v-model="fields.actual_return_date" type="date" aria-label="Actual return date" class="release-slip-input" /></p>
            </section>

            <section class="mt-4">
              <h2 class="font-bold">AUTHORIZATION &amp; ACKNOWLEDGEMENT</h2>
              <p class="mt-1">Recipient Acknowledgement:</p>
              <p class="mt-1">I hereby acknowledge receipt of the above-listed items(s) in good order and condition unless otherwise stated. I accept responsibility for the proper care, safe custody, and timely return of these properties.</p>

              <div class="mt-4">
                <p>Released By (Property Custodian):</p>
                <p>Signature: ______________________________</p>
                <p>Printed Name: <input v-model="fields.released_by_name" maxlength="150" aria-label="Released by printed name" class="release-slip-input w-56" /></p>
                <p>Date: <input v-model="fields.released_by_date" type="date" aria-label="Released by date" class="release-slip-input" /></p>
              </div>

              <div class="ml-auto mt-4 w-2/5">
                <p>Received By (Recipient):</p>
                <p>Signature: ______________________________</p>
                <p>Printed Name: <input v-model="fields.received_by_name" maxlength="150" aria-label="Received by printed name" class="release-slip-input w-56" /></p>
                <p>Date: <input v-model="fields.received_by_date" type="date" aria-label="Received by date" class="release-slip-input" /></p>
              </div>

              <div class="mt-4">
                <p>Approved By (Department Head / Supervisor)</p>
                <p>Signature: ______________________________</p>
                <p>Printed Name: <input v-model="fields.approved_by_name" maxlength="150" aria-label="Approved by printed name" class="release-slip-input w-56" /></p>
                <p>Date: <input v-model="fields.approved_by_date" type="date" aria-label="Approved by date" class="release-slip-input" /></p>
              </div>
            </section>
          </article>
        </div>
      </section>
      <p v-if="error" role="alert" class="mt-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ error }}</p>
    </form>

    <template #footer>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" :disabled="busy" @click="close">
          ← Back
        </button>
        <div class="flex flex-wrap gap-2">
          <a
            v-if="previewUrl && previewCurrent"
            :href="previewUrl"
            target="_blank"
            rel="noopener"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
          >
            Print / Download
          </a>
          <button v-else-if="previewUrl" type="button" disabled class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-400 dark:border-gray-700">
            Update preview to print
          </button>
          <button form="transfer-release-slip-form" type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="busy">
            {{ saveBusy ? 'Saving…' : 'Save Draft' }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  transfer: { type: Object, default: null },
  custodianName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'saved']);
const transferPurpose = 'Transfer / Relocation';
const purposes = ['Official Use (Off-site)', 'Transfer / Relocation', 'Repair / Maintenance', 'Disposal'];
const fields = reactive({});
const previewUrl = ref('');
const previewCurrent = ref(false);
const previewMode = ref('html');
const previewBusy = ref(false);
const saveBusy = ref(false);
const error = ref('');
const busy = computed(() => previewBusy.value || saveBusy.value);
let previewRefreshTimer = null;
let previewRequestId = 0;
const itemQuantity = computed(() => Number(props.transfer?.quantity ?? 0));
const unitCost = computed(() => Number(props.transfer?.item?.unit_cost ?? 0));
const lineAmount = computed(() => itemQuantity.value * unitCost.value);
const itemDescription = computed(() => {
  const item = props.transfer?.item;
  if (!item) return 'Unknown item';
  return `${item.item_name ?? 'Unknown item'}${item.serial_number ? ` (SN: ${item.serial_number})` : ''}`;
});
const documentControlNo = computed(() => `PRS-${new Date().getFullYear()}-${String(props.transfer?.id ?? '').padStart(6, '0')}`);
const documentDate = computed(() => formatDate(props.transfer?.requested_at) || today());

function today() {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function formatDate(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function formatAmount(value) {
  return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function personName(person) {
  if (!person) return 'Unknown';
  return person.name ?? ([person.first_name, person.last_name].filter(Boolean).join(' ') || person.username || 'Unknown');
}

function setFields() {
  const slip = props.transfer?.release_slip ?? props.transfer?.releaseSlip ?? {};
  const recipient = props.transfer?.target_user ?? props.transfer?.targetUser;
  Object.assign(fields, {
    recipient_name: slip.recipient_name ?? personName(recipient),
    department_unit: slip.department_unit ?? '',
    designation: slip.designation ?? '',
    expected_return_date: String(slip.expected_return_date ?? '').slice(0, 10),
    actual_return_date: String(slip.actual_return_date ?? '').slice(0, 10),
    purpose: transferPurpose,
    condition_upon_release: slip.condition_upon_release ?? '',
    released_by_name: slip.released_by_name ?? props.custodianName,
    released_by_date: String(slip.released_by_date ?? today()).slice(0, 10),
    received_by_name: slip.received_by_name ?? personName(recipient),
    received_by_date: String(slip.received_by_date ?? '').slice(0, 10),
    approved_by_name: slip.approved_by_name ?? '',
    approved_by_date: String(slip.approved_by_date ?? '').slice(0, 10),
  });
  error.value = '';
  previewCurrent.value = false;
  previewMode.value = 'html';
  clearTimeout(previewRefreshTimer);
  previewRequestId += 1;
  previewBusy.value = false;
  clearPreview();
}

function handleFieldInput() {
  previewCurrent.value = false;
  if (previewMode.value === 'pdf') {
    clearTimeout(previewRefreshTimer);
    previewRefreshTimer = setTimeout(() => {
      previewRefreshTimer = null;
      updatePreview();
    }, 500);
  }
}

function selectPdfPreview() {
  previewMode.value = 'pdf';
  if (!previewCurrent.value) {
    clearTimeout(previewRefreshTimer);
    previewRefreshTimer = null;
    updatePreview();
  }
}

function clearPreview() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
  previewUrl.value = '';
}

function payload() {
  return { slip_fields: { ...fields, purpose: transferPurpose } };
}

async function errorMessage(requestError, fallback) {
  const responseData = requestError?.response?.data;
  if (responseData instanceof Blob) {
    const text = await responseData.text();
    try {
      const parsed = JSON.parse(text);
      return parsed.message ?? Object.values(parsed.errors ?? {})[0]?.[0] ?? fallback;
    } catch (parseError) {
      if (!(parseError instanceof SyntaxError)) throw parseError;
      return text || fallback;
    }
  }
  return responseData?.errors?.['slip_fields.recipient_name']?.[0] ?? responseData?.message ?? fallback;
}

async function updatePreview() {
  if (!props.transfer) return;
  const requestId = ++previewRequestId;
  previewBusy.value = true;
  error.value = '';
  try {
    const { data } = await api.post(`/custodian/transfers/${props.transfer.id}/release-slip-preview`, payload(), {
      responseType: 'blob',
      skipToast: true,
    });
    if (requestId !== previewRequestId) return;
    const previousUrl = previewUrl.value;
    previewUrl.value = URL.createObjectURL(data);
    if (previousUrl) URL.revokeObjectURL(previousUrl);
    previewCurrent.value = true;
  } catch (requestError) {
    if (requestId === previewRequestId) {
      const message = await errorMessage(requestError, 'Could not render the release slip preview.');
      if (requestId === previewRequestId) error.value = message;
    }
  } finally {
    if (requestId === previewRequestId) previewBusy.value = false;
  }
}

async function saveDraft() {
  if (!props.transfer || saveBusy.value) return;
  saveBusy.value = true;
  error.value = '';
  try {
    await api.post(`/custodian/transfers/${props.transfer.id}/draft`, payload(), { skipToast: true });
    emit('saved');
    emit('close');
  } catch (requestError) {
    error.value = await errorMessage(requestError, 'Could not save the release slip draft.');
  } finally {
    saveBusy.value = false;
  }
}

function close() {
  if (!busy.value) emit('close');
}

watch(() => [props.open, props.transfer?.id], ([open]) => {
  if (open) setFields();
});

onUnmounted(() => {
  clearTimeout(previewRefreshTimer);
  previewRequestId += 1;
  clearPreview();
});
</script>

<style scoped>
.release-slip-input {
  min-width: 0;
  border: 0;
  border-bottom: 1px solid #000;
  border-radius: 0;
  background: transparent;
  padding: 1px 3px;
  color: inherit;
  font: inherit;
}

.release-slip-input:focus {
  outline: 1px solid #059669;
  outline-offset: 1px;
}
</style>
