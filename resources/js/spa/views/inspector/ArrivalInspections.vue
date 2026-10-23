<template>
  <main class="space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Arrival Inspections</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Enter the document and item details shown on the Inspection and Acceptance Report.</p>
      </div>
      <button type="button" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700" @click="startNew">
        New Inspection
      </button>
    </header>

    <p v-if="error" role="alert" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
      {{ error }}
      <button type="button" class="ml-2 underline" @click="load">Retry</button>
    </p>

    <section v-if="!editing" class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Inspection records</h2>
      </div>
      <div v-if="loading" class="p-4 text-sm text-gray-500">Loading inspections…</div>
      <div v-else-if="!inspections.length" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
        No arrival inspections yet. Start a record when goods arrive.
      </div>
      <div v-else class="grid gap-3 p-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
        <article v-for="record in inspections" :key="record.id" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
          <div class="flex h-[126px] items-center justify-center overflow-hidden bg-gray-100 p-2 dark:bg-gray-800">
            <div class="iar-card-preview" :aria-label="`Preview of ${record.status === 'draft' ? 'draft ' : ''}IAR ${record.iar_number || ''}`" role="img">
              <header class="iar-card-preview-letterhead">
                <img :src="'/images/header/Philippine%20Education%20Department%20Header.png'" alt="" />
              </header>
              <div class="iar-card-preview-appendix">Appendix 62</div>
              <div class="iar-card-preview-title">INSPECTION AND ACCEPTANCE REPORT</div>
              <div class="iar-card-preview-entity">
                <span><b>Entity Name:</b><i>{{ record.entity_name || '' }}</i></span>
                <span><b>Fund Cluster:</b><i>{{ record.fund_cluster || '' }}</i></span>
              </div>
              <div class="iar-card-preview-first-row">
                <b>Supplier:</b>
                <span>{{ record.supplier_name || '' }}</span>
                <b>IAR No.:</b>
                <span>{{ record.iar_number || '' }}</span>
              </div>
            </div>
          </div>
          <div class="flex items-start justify-between gap-2 p-3">
            <div class="min-w-0">
              <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ record.supplier_name || 'Untitled inspection' }}</h3>
              <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ record.iar_number || 'IAR number pending' }} · {{ formatDate(record.iar_date) || 'No date' }}</p>
              <span class="mt-2 inline-flex rounded-full px-2 py-1 text-[11px] font-medium" :class="recordStatusClass(record.status)">
                {{ recordStatusLabel(record.status) }}
              </span>
            </div>
            <div class="flex shrink-0 items-center gap-1">
              <button
                v-if="record.status === 'draft'"
                type="button"
                class="rounded-md p-2 text-rose-600 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-500/10"
                :aria-label="`Delete draft for ${record.supplier_name || 'untitled inspection'}`"
                title="Delete draft"
                @click="deleteTarget = record"
              >
                <LucideIcon :icon="Trash2" class="h-4 w-4" />
              </button>
              <button
                type="button"
                class="rounded-md p-2 text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 dark:text-brand-300 dark:hover:bg-brand-500/10"
                :aria-label="['draft', 'returned'].includes(record.status) ? 'Continue draft' : 'Review inspection'"
                :title="['draft', 'returned'].includes(record.status) ? 'Continue' : 'Review'"
                :disabled="openingRecordId === record.id"
                :class="{ 'cursor-wait opacity-70': openingRecordId === record.id }"
                @click="openRecord(record.id)"
              >
                <LucideIcon
                  :icon="openingRecordId === record.id ? LoaderCircle : (['draft', 'returned'].includes(record.status) ? Pencil : Eye)"
                  class="h-4 w-4"
                  :class="{ 'animate-spin': openingRecordId === record.id }"
                />
              </button>
              <a
                v-if="['inspected', 'approved', 'posted'].includes(record.status) && record.acceptance_status !== 'rejected' && record.iar_number"
                :href="`/api/inspector/arrival-inspections/${record.id}/pdf`"
                target="_blank"
                rel="noopener noreferrer"
                class="rounded-md p-2 text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                :aria-label="`Open IAR PDF for ${record.supplier_name || 'inspection'}`"
                title="Open IAR PDF"
              >
                <LucideIcon :icon="FileText" class="h-4 w-4" />
              </a>
            </div>
          </div>
        </article>
      </div>
    </section>

    <Modal
      :open="editing"
      :title="form.id ? (form.status === 'draft' ? 'Continue Arrival Inspection' : 'Review Arrival Inspection') : 'New Arrival Inspection'"
      subtitle="Inspection and Acceptance Report (IAR)"
      max-width="max-w-7xl"
      :fixed-body="true"
      @close="cancelEdit"
    >
    <div class="flex min-h-0 flex-1 flex-col gap-3">
      <p v-if="form.status === 'returned' && form.custodian_return_reason" role="status" class="shrink-0 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
        <strong>Returned by the Custodian:</strong> {{ form.custodian_return_reason }}
      </p>
      <div class="flex min-h-0 flex-1 flex-col overflow-auto rounded-md bg-gray-100 p-3 dark:bg-gray-800 sm:p-5">
        <fieldset class="mx-auto min-w-0 flex-1 border-0 p-0" :disabled="!['draft', 'returned'].includes(form.status)">
        <article class="mx-auto flex min-h-[11in] w-full max-w-[8.5in] flex-col bg-white px-8 py-10 text-[10pt] leading-tight text-black shadow-sm sm:px-12">
          <header class="mb-3 border-b border-gray-300 pb-3">
            <img
              :src="'/images/header/Philippine%20Education%20Department%20Header.png'"
              alt="Department of Education, Schools Division of Escalante City"
              class="mx-auto block h-[0.88in] w-[40%] object-contain"
            />
          </header>
          <p class="mt-2 text-right font-bold italic">Appendix 62</p>
          <h2 class="my-3 text-center text-[10pt] font-bold">INSPECTION AND ACCEPTANCE REPORT</h2>

          <div class="mb-3 grid grid-cols-[1.2fr_1fr] gap-4">
            <label><strong>Entity Name:</strong> <input v-model="form.entity_name" aria-label="Entity Name" class="iar-preview-input w-[65%]" /></label>
            <label class="text-right"><strong>Fund Cluster:</strong> <input v-model="form.fund_cluster" aria-label="Fund Cluster" class="iar-preview-input w-[55%] text-right" /></label>
          </div>

          <table class="iar-details-table w-full table-fixed border-collapse">
            <tbody>
              <tr>
                <th class="w-[23%] border border-black p-1 text-left">Supplier:</th>
                <td class="w-[51%] border border-black p-1"><input v-model="form.supplier_name" aria-label="Supplier" class="iar-preview-input w-full" /></td>
                <th class="w-[13%] border border-black p-1 text-left">IAR No.:</th>
                <td class="w-[13%] border border-black p-1"><input v-model="form.iar_number" aria-label="IAR No." class="iar-preview-input w-full" /></td>
              </tr>
              <tr>
                <th class="border border-black p-1 text-left">Contract Title:</th>
                <td class="border border-black p-1"><input v-model="form.contract_title" aria-label="Contract Title" class="iar-preview-input w-full" /></td>
                <th class="border border-black p-1 text-left">Date:</th>
                <td class="border border-black p-1"><input v-model="form.iar_date" type="date" aria-label="IAR Date" class="iar-preview-input w-full" /></td>
              </tr>
              <tr>
                <th class="border border-black p-1 text-left">Requisitioning Office/Dept.:</th>
                <td class="border border-black p-1"><input v-model="form.requesting_office" aria-label="Requisitioning Office or Department" class="iar-preview-input w-full" /></td>
                <th class="border border-black p-1 text-left">Invoice No.:</th>
                <td class="border border-black p-1"><input v-model="form.invoice_number" aria-label="Invoice No." class="iar-preview-input w-full" /></td>
              </tr>
              <tr>
                <th class="border border-black p-1 text-left">Responsibility Center Code:</th>
                <td class="border border-black p-1"><input v-model="form.responsibility_center_code" aria-label="Responsibility Center Code" class="iar-preview-input w-full" /></td>
                <th class="border border-black p-1 text-left">Date:</th>
                <td class="border border-black p-1"><input v-model="form.invoice_date" type="date" aria-label="Invoice Date" class="iar-preview-input w-full" /></td>
              </tr>
            </tbody>
          </table>

          <div class="relative">
            <button
              v-if="!['finalized', 'posted'].includes(form.status)"
              type="button"
              aria-label="Add item"
              class="absolute -right-3 -top-3 z-10 h-6 w-6 rounded-full border border-gray-400 bg-white text-center text-sm font-semibold leading-5 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="form.items.length >= 100"
              @click="addItem"
            >
              +
            </button>
          <table class="w-full table-fixed border-collapse">
            <thead>
              <tr>
                <th class="w-[23%] border border-black border-t-0 p-2">Stock/Property No.</th>
                <th class="w-[51%] border border-black border-t-0 p-2">Description</th>
                <th class="w-[13%] border border-black border-t-0 p-2">Unit</th>
                <th class="w-[13%] border border-black border-t-0 p-2">Quantity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="item.key">
                <td class="border border-black p-2 align-top">
                  <input v-model="item.stock_property_number" :aria-label="`Stock/Property No. for item ${index + 1}`" class="iar-preview-input w-full" />
                  <button v-if="form.items.length > 1" type="button" class="mt-2 text-[10pt] text-rose-700 underline" @click="removeItem(index)">Remove item</button>
                </td>
                <td class="border border-black p-2 align-top"><textarea v-model="item.description" :aria-label="`Description for item ${index + 1}`" rows="3" class="iar-preview-input w-full resize-y whitespace-pre-line" /></td>
                <td class="border border-black p-2 text-center align-top"><input v-model="item.unit" :aria-label="`Unit for item ${index + 1}`" class="iar-preview-input w-full text-center" /></td>
                <td class="border border-black p-2 text-center align-top"><input v-model.number="item.quantity" type="number" min="1" :aria-label="`Quantity for item ${index + 1}`" class="iar-preview-input w-full text-center" /></td>
              </tr>
              <tr>
                <td colspan="4" class="border border-black p-1 text-center font-bold">***NOTHING FOLLOWS***</td>
              </tr>
            </tbody>
          </table>
          </div>

          <table class="mt-0 w-full table-fixed border-collapse">
            <thead>
              <tr>
                <th class="w-[51%] border border-black border-t-0 p-1 italic">INSPECTION</th>
                <th class="w-[49%] border border-black border-t-0 p-1 italic">ACCEPTANCE</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="border border-black p-2 align-top">
                  <p>Date Inspected: <input v-model="form.inspection_date" type="date" aria-label="Date Inspected" class="iar-preview-input" /></p>
                  <label class="my-2 flex items-start gap-1">
                    <input v-model="form.inspection_verified" type="checkbox" aria-label="Inspected, verified and found in order" class="iar-inspection-checkbox mt-0.5 shrink-0" />
                    <span>Inspected, verified and found in order as to quantity and specifications</span>
                  </label>
                  <div class="mx-auto mt-4 w-[82%] text-center">
                    <div class="h-5 border-b border-black" />
                    <input v-model="form.committee_member_1_name" aria-label="Committee member 1 name" class="iar-preview-input mt-1 w-full text-center font-bold uppercase" />
                    <input v-model="form.committee_member_1_designation" aria-label="Committee member 1 designation" class="iar-preview-input mt-2 w-full text-center" placeholder="Inspection Officer/Inspection Committee" />
                  </div>
                  <div class="mx-auto mt-4 w-[82%] text-center">
                    <div class="h-5 border-b border-black" />
                    <input v-model="form.committee_member_2_name" aria-label="Committee member 2 name" class="iar-preview-input mt-1 w-full text-center font-bold uppercase" />
                    <input v-model="form.committee_member_2_designation" aria-label="Committee member 2 designation" class="iar-preview-input mt-2 w-full text-center" placeholder="Inspection Officer/Inspection Committee" />
                  </div>
                  <div class="mx-auto mt-4 w-[82%] text-center">
                    <div class="h-5 border-b border-black" />
                    <input v-model="form.committee_member_3_name" aria-label="Committee member 3 name" class="iar-preview-input mt-1 w-full text-center font-bold uppercase" />
                    <input v-model="form.committee_member_3_designation" aria-label="Committee member 3 designation" class="iar-preview-input mt-2 w-full text-center" placeholder="Inspection Officer/Inspection Committee" />
                  </div>
                </td>
                <td class="border border-black p-2 align-top">
                  <p>Date Received: <input v-model="form.receipt_date" type="date" aria-label="Date Received" class="iar-preview-input" /></p>
                  <label class="iar-acceptance-option mt-3 block"><input v-model="form.acceptance_status" type="radio" value="complete" aria-label="Complete acceptance" /> Complete</label>
                  <label class="iar-acceptance-option mt-2 block"><input v-model="form.acceptance_status" type="radio" value="partial" aria-label="Partial acceptance" /> Partial (pls. specify quantity)</label>
                  <label class="iar-acceptance-option mt-2 block"><input v-model="form.acceptance_status" type="radio" value="rejected" aria-label="Rejected delivery" /> Rejected</label>
                  <textarea v-model="form.acceptance_remarks" aria-label="Acceptance remarks" rows="3" class="iar-preview-input mt-4 w-full resize-y text-center font-bold text-red-700" placeholder="Remarks / rejected quantities and findings" />
                  <div class="mx-auto mt-16 w-[82%] text-center">
                    <div class="h-5 border-b border-black" />
                    <input v-model="form.custodian_name" aria-label="Custodian name" class="iar-preview-input mt-1 w-full text-center font-bold uppercase" />
                    <input v-model="form.custodian_designation" aria-label="Custodian designation" class="iar-preview-input mt-2 w-full text-center" placeholder="School Property Custodian" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <footer class="mt-auto flex items-center border-t border-black pt-2 font-serif text-[10pt] leading-tight">
            <img
              :src="'/images/footer/escalante_city_education_seal.svg'"
              alt="Escalante City Division Department of Education seal"
              class="mr-3 h-auto w-9 shrink-0"
            />
            <p>
              Escalante City, Negros Occidental, Philippines<br />
              Tel. Nos. - (034) 724-5720, (034) 454-0746<br />
              Escalante.city001@deped.gov.ph<br />
              Depedescalantecity.weebly.com
            </p>
          </footer>
        </article>
        </fieldset>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
          :disabled="saving"
          @click="cancelEdit"
        >
          Cancel
        </button>
        <template v-if="['draft', 'returned'].includes(form.status)">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="saving"
            @click="save(form.status === 'inspected' ? 'inspected' : 'draft')"
          >
            {{ savingAction === 'draft' ? 'Saving draft…' : (form.status === 'inspected' ? (savingAction === 'inspected' ? 'Saving changes…' : 'Save changes') : 'Save draft') }}
          </button>
          <button
            type="button"
            class="rounded-md bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="saving || !canRecordInspection"
            @click="save('inspected')"
          >
            {{ savingAction === 'inspected' ? 'Recording…' : 'Record inspection results' }}
          </button>
        </template>
        <span v-else-if="form.status === 'inspected'" class="self-center text-xs text-gray-500 dark:text-gray-400">Submitted to the Custodian for approval.</span>
        <span v-else-if="form.status === 'approved'" class="self-center text-xs text-emerald-700 dark:text-emerald-300">Approved by the Custodian.</span>
      </div>
    </template>
    </Modal>
    <ConfirmDialog
      :open="deleteTarget !== null"
      title="Delete draft inspection?"
      :message="`Delete the draft for ${deleteTarget?.supplier_name || 'this inspection'}? This cannot be undone.`"
      confirm-label="Delete draft"
      :busy="deleting"
      @cancel="deleteTarget = null"
      @confirm="deleteDraft"
    />
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Eye, FileText, LoaderCircle, Pencil, Trash2 } from 'lucide';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import { useToastStore } from '../../stores/toast';

const loading = ref(true);
const saving = ref(false);
const savingAction = ref('');
const error = ref('');
const inspections = ref([]);
const editing = ref(false);
const openingRecordId = ref(null);
const deleteTarget = ref(null);
const deleting = ref(false);
const form = reactive(emptyForm());
const toast = useToastStore();
const canRecordInspection = computed(() => form.items.length > 0 && form.items.every((item) => (
  String(item.description ?? '').trim() !== '' && String(item.unit ?? '').trim() !== '' && Number(item.quantity) > 0
)));
function emptyItem() {
  return {
    key: `${Date.now()}-${Math.random()}`,
    stock_property_number: '',
    description: '',
    unit: 'unit',
    quantity: 1,
  };
}

function emptyForm() {
  return {
    id: null,
    status: 'draft',
    inspection_verified: true,
    entity_name: '',
    fund_cluster: '',
    supplier_name: '',
    contract_title: '',
    requesting_office: '',
    responsibility_center_code: '',
    iar_number: '',
    iar_date: '',
    invoice_number: '',
    invoice_date: '',
    inspection_date: '',
    receipt_date: '',
    acceptance_status: '',
    acceptance_remarks: '',
    committee_member_1_name: '',
    committee_member_1_designation: '',
    committee_member_2_name: '',
    committee_member_2_designation: '',
    committee_member_3_name: '',
    committee_member_3_designation: '',
    custodian_name: '',
    custodian_designation: '',
    items: [emptyItem()],
  };
}

function addItem() {
  if (form.items.length < 100) form.items.push(emptyItem());
}

function removeItem(index) {
  form.items.splice(index, 1);
}

function setForm(data) {
  Object.assign(form, emptyForm(), data, {
    iar_date: normalizeDateInput(data.iar_date),
    invoice_date: normalizeDateInput(data.invoice_date),
    inspection_date: normalizeDateInput(data.inspection_date),
    receipt_date: normalizeDateInput(data.receipt_date),
    items: (data.items ?? [emptyItem()]).map((item) => ({
      ...emptyItem(),
      ...item,
      stock_property_number: item.stock_property_number ?? '',
      description: item.description ?? '',
      unit: item.unit ?? '',
      quantity: item.quantity ?? '',
      key: `${item.id ?? Date.now()}-${Math.random()}`,
    })),
  });
}

function normalizeDateInput(value) {
  return value ? String(value).slice(0, 10) : '';
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/inspector/arrival-inspections');
    inspections.value = data.inspections ?? [];
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not load arrival inspections.';
  } finally {
    loading.value = false;
  }
}

function startNew() {
  setForm(emptyForm());
  editing.value = true;
}

async function openRecord(id) {
  if (openingRecordId.value !== null) return;

  openingRecordId.value = id;
  error.value = '';
  try {
    const { data } = await api.get(`/inspector/arrival-inspections/${id}`);
    setForm(data.inspection);
    editing.value = true;
  } catch (requestError) {
    error.value = requestError?.response?.data?.message ?? 'Could not open this inspection.';
  } finally {
    openingRecordId.value = null;
  }
}

async function deleteDraft() {
  if (!deleteTarget.value || deleting.value) return;

  deleting.value = true;
  try {
    await api.delete(`/inspector/arrival-inspections/${deleteTarget.value.id}`, { skipToast: true });
    inspections.value = inspections.value.filter((record) => record.id !== deleteTarget.value.id);
    toast.success('Draft deleted', 'The inspection draft has been deleted.');
    deleteTarget.value = null;
  } catch (requestError) {
    toast.error('Could not delete draft', requestError?.response?.data?.message ?? 'Please try again.');
  } finally {
    deleting.value = false;
  }
}

function cancelEdit() {
  editing.value = false;
}

async function save(status) {
  if (saving.value) return;
  if (status === 'inspected' && !canRecordInspection.value) {
    toast.error('Inspection details required', 'Enter a description, unit, and quantity greater than zero for every item.');
    return;
  }

  const pdfWindow = status === 'inspected' ? window.open('about:blank', '_blank') : null;
  saving.value = true;
  savingAction.value = status;
  const payload = {
    ...form,
    status,
    items: form.items.map((item) => ({
      stock_property_number: item.stock_property_number || null,
      description: item.description,
      unit: item.unit,
      quantity: item.quantity === '' || item.quantity == null ? null : Number(item.quantity),
    })),
  };
  delete payload.id;
  try {
    const response = form.id
      ? await api.patch(`/inspector/arrival-inspections/${form.id}`, payload, { skipToast: true })
      : await api.post('/inspector/arrival-inspections', payload, { skipToast: true });
    inspections.value = [response.data.inspection, ...inspections.value.filter((record) => record.id !== response.data.inspection.id)];
    if (status === 'inspected') {
      if (pdfWindow && !pdfWindow.closed) {
        pdfWindow.location.href = `/api/inspector/arrival-inspections/${response.data.inspection.id}/pdf`;
        toast.success('Inspection submitted', 'The IAR PDF was opened in a new tab.');
      } else {
        toast.success('Inspection submitted', 'Allow pop-ups to open the IAR PDF, or use the PDF icon on its card.');
      }
    } else {
      toast.success('Inspection saved', response.data.message ?? 'The inspection was saved.');
    }
    editing.value = false;
  } catch (requestError) {
    pdfWindow?.close();
    const errors = requestError?.response?.data?.errors;
    const hasItemErrors = Object.keys(errors ?? {}).some((key) => key.startsWith('items.'));
    const message = hasItemErrors
      ? 'Check that each item has a description, unit, and quantity greater than zero.'
      : Object.values(errors ?? {}).flat().join(' ') || requestError?.response?.data?.message || 'Could not save inspection.';
    toast.error('Could not save inspection', message);
  } finally {
    saving.value = false;
    savingAction.value = '';
  }
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(`${value}`.slice(0, 10));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
}

function recordStatusLabel(status) {
  return ({
    draft: 'Draft',
    returned: 'Returned',
    inspected: 'Awaiting custodian',
    approved: 'Approved',
    finalized: 'Finalized',
    posted: 'Posted',
  })[status] ?? 'Draft';
}

function recordStatusClass(status) {
  if (status === 'approved' || status === 'inspected' || status === 'finalized' || status === 'posted') {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
  }
  if (status === 'returned') {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300';
  }

  return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
}

onMounted(load);
</script>

<style scoped>
.iar-preview-input {
  min-width: 0;
  border: 0;
  border-bottom: 1px solid #9ca3af;
  border-radius: 0;
  background: transparent;
  padding: 1px 3px;
  color: inherit;
  font: inherit;
  line-height: inherit;
}

.iar-preview-input:focus {
  border-bottom-color: #7c3aed;
  outline: none;
  box-shadow: 0 1px 0 #7c3aed;
}

.iar-acceptance-option input[type="radio"] {
  appearance: none;
  width: 4mm;
  height: 4mm;
  margin: 0 2mm 0 1mm;
  border: 1px solid #000;
  border-radius: 0;
  vertical-align: middle;
}

.iar-acceptance-option input[type="radio"]:checked {
  background: #000;
  box-shadow: inset 0 0 0 1px #fff;
}

.iar-inspection-checkbox {
  appearance: none;
  width: 4mm;
  height: 4mm;
  margin-right: 1mm;
  border: 1px solid #000;
  border-radius: 0;
  background: transparent;
}

.iar-inspection-checkbox:checked {
  background: #000;
  box-shadow: inset 0 0 0 1px #fff;
}

.iar-card-preview {
  display: flex;
  width: 100%;
  max-width: 240px;
  height: 108px;
  flex: none;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  background: #fff;
  padding: 5px 7px 4px;
  color: #111827;
  box-shadow: 0 2px 5px rgb(15 23 42 / 10%);
  font-family: "Times New Roman", serif;
  font-size: 8px;
  line-height: 1.25;
}

.iar-card-preview-letterhead {
  display: flex;
  height: 51px;
  flex: none;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid #cbd5e1;
}

.iar-card-preview-letterhead img {
  display: block;
  width: 48%;
  height: 45px;
  object-fit: contain;
}

.iar-card-preview-appendix {
  margin-top: 8px;
  text-align: right;
  font-style: italic;
  font-weight: 700;
}

.iar-card-preview-title {
  margin: 8px 0 0;
  text-align: center;
  font-family: Arial, sans-serif;
  font-size: 9px;
  font-weight: 700;
}

.iar-card-preview-entity {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 5px;
  margin: 9px 0 4px;
  font-family: Arial, sans-serif;
  font-size: 8px;
}

.iar-card-preview-entity > span {
  display: flex;
  min-width: 0;
  align-items: baseline;
  gap: 3px;
  white-space: nowrap;
}

.iar-card-preview-entity i {
  min-width: 0;
  height: 10px;
  flex: 1;
  overflow: hidden;
  border-bottom: 1px solid #9ca3af;
  font-style: normal;
  text-overflow: ellipsis;
}

.iar-card-preview-first-row {
  display: grid;
  grid-template-columns: 23% 51% 13% 13%;
  min-height: 28px;
  border-top: 1px solid #111827;
  border-left: 1px solid #111827;
  font-family: Arial, sans-serif;
  font-size: 8px;
}

.iar-card-preview-first-row > * {
  min-width: 0;
  overflow: hidden;
  border-right: 1px solid #111827;
  border-bottom: 1px solid #111827;
  padding: 4px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

</style>
