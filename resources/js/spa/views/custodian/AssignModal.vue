<template>
  <Modal :open="open && !showSlip" title="Assign Stockroom Items" subtitle="Issue one or more items to a registered staff member or record a manual loan." max-width="max-w-3xl" @close="$emit('close')">
    <!-- Mode Switcher -->
    <div class="mb-5 flex rounded-md bg-gray-100 p-1 dark:bg-white/5 border border-gray-200/60 dark:border-gray-800">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="flex-1 rounded-md py-2 text-xs font-bold transition-all"
        :class="
          mode === tab.key
            ? 'bg-white text-emerald-900 shadow-sm dark:bg-gray-800 dark:text-emerald-300'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
        "
        @click="mode = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- 1. Registered User Mode -->
    <form v-if="mode === 'registered'" id="assign-registered-form" class="space-y-4" @submit.prevent="openSlipPreview">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-user">
            Registered Recipient <span class="text-rose-500">*</span>
          </label>
          <select
            id="assign-user"
            v-model="registered.user_id"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          >
            <option value="">Select staff recipient</option>
            <option v-for="person in endUsers" :key="person.id" :value="person.id">
              {{ person.name }}
            </option>
          </select>
          <FieldError :errors="errors" field="user_id" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="assign-date">
            Assignment Date
          </label>
          <input
            id="assign-date"
            v-model="registered.transaction_date"
            type="date"
            :max="today"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="transaction_date" />
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">
          Items to assign <span class="font-normal text-gray-500 dark:text-gray-400">({{ lines.length }} line(s), max 100)</span>
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="lines.length >= 100"
          @click="addLine"
        >
          <span class="text-base leading-none">+</span> Add item
        </button>
      </div>

      <p v-if="assignableItems.length === 0" class="text-xs text-amber-700 dark:text-amber-300">
        No inventory items have unreserved stock available.
      </p>
      <p v-else-if="staleNotice" class="text-xs text-amber-700 dark:text-amber-300">
        {{ staleNotice }}
      </p>

      <div class="max-h-[224px] min-h-[132px] overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
        <table class="w-max min-w-full border-collapse text-left text-xs">
          <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            <tr>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Item</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Free</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Qty</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
              <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
            <tr v-for="(line, index) in lines" :key="line.key">
              <td class="min-w-52 px-3 py-2 align-top">
                <select
                  v-model="line.item_id"
                  :aria-label="`Item for line ${index + 1}`"
                  class="w-52 rounded border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                  @change="onLineItem(line)"
                >
                  <option value="">Select stock</option>
                  <option v-for="item in assignableItems" :key="item.item_id" :value="item.item_id">
                    {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ freeQuantity(item) }} {{ item.unit }} free)
                  </option>
                </select>
                <p v-if="lineErrors[line.key]?.item_id" class="mt-1 max-w-52 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].item_id }}</p>
              </td>
              <td class="whitespace-nowrap px-3 py-2 align-top text-right text-gray-600 dark:text-gray-400">
                {{ lineItem(line) ? `${freeQuantity(lineItem(line))} ${lineItem(line).unit}` : '—' }}
              </td>
              <td class="px-3 py-2 align-top text-right">
                <input
                  v-model.number="line.quantity"
                  type="number"
                  min="1"
                  :max="lineFree(line) || undefined"
                  :aria-label="`Quantity for line ${index + 1}`"
                  class="w-20 rounded border border-gray-200 bg-white px-2 py-1.5 text-right text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
                <p v-if="lineErrors[line.key]?.quantity" class="mt-1 max-w-20 break-words text-right text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].quantity }}</p>
              </td>
              <td class="whitespace-nowrap px-3 py-2 align-top text-right font-semibold text-gray-800 dark:text-gray-200">
                {{ formatCurrency(lineAmount(line)) }}
              </td>
              <td class="px-3 py-2 align-top">
                <button
                  type="button"
                  class="rounded px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-40 dark:text-rose-300 dark:hover:bg-rose-500/10"
                  :disabled="lines.length <= 1"
                  :aria-label="`Remove line ${index + 1}`"
                  @click="removeLine(line.key)"
                >
                  Remove
                </button>
                <p v-if="lineErrors[line.key]?.row" class="mt-1 max-w-40 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].row }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="linesTotal > 0" class="text-right text-xs font-medium text-gray-500 dark:text-gray-400">
        Slip total: <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(linesTotal) }}</span>
      </p>

      <!-- Action Buttons -->
    </form>

    <!-- 2. Manual Issue Mode -->
    <form v-else id="assign-manual-form" class="space-y-4" @submit.prevent="openManualSlip">
      <div class="grid gap-3.5 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-name">
            Recipient Name <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-name"
            v-model="manual.manual_recipient_name"
            type="text"
            required
            maxlength="255"
            placeholder="e.g. Maria Santos"
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="manual_recipient_name" />
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="manual-date">
            Transaction Date <span class="text-rose-500">*</span>
          </label>
          <input
            id="manual-date"
            v-model="manual.transaction_date"
            type="date"
            required
            class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />
          <FieldError :errors="errors" field="transaction_date" />
        </div>

      </div>

      <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">
          Items to issue <span class="font-normal text-gray-500 dark:text-gray-400">({{ lines.length }} line(s), max 100)</span>
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="lines.length >= 100"
          @click="addLine"
        >
          <span class="text-base leading-none">+</span> Add item
        </button>
      </div>

      <p v-if="assignableItems.length === 0" class="text-xs text-amber-700 dark:text-amber-300">
        No inventory items have unreserved stock available.
      </p>
      <p v-else-if="staleNotice" class="text-xs text-amber-700 dark:text-amber-300">
        {{ staleNotice }}
      </p>

      <div class="max-h-[224px] min-h-[132px] overflow-auto rounded-md border border-gray-200 dark:border-gray-700">
        <table class="w-max min-w-full border-collapse text-left text-xs">
          <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            <tr>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 dark:border-gray-700">Item</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Free</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Qty</th>
              <th class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Amount</th>
              <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
            <tr v-for="(line, index) in lines" :key="line.key">
              <td class="min-w-52 px-3 py-2 align-top">
                <select
                  v-model="line.item_id"
                  :aria-label="`Item for line ${index + 1}`"
                  class="w-52 rounded border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                  @change="onLineItem(line)"
                >
                  <option value="">Select stock</option>
                  <option v-for="item in assignableItems" :key="item.item_id" :value="item.item_id">
                    {{ item.item_name }} · {{ item.category_name ?? 'Uncategorized' }} ({{ freeQuantity(item) }} {{ item.unit }} free)
                  </option>
                </select>
                <p v-if="lineErrors[line.key]?.item_id" class="mt-1 max-w-52 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].item_id }}</p>
              </td>
              <td class="whitespace-nowrap px-3 py-2 align-top text-right text-gray-600 dark:text-gray-400">
                {{ lineItem(line) ? `${freeQuantity(lineItem(line))} ${lineItem(line).unit}` : '—' }}
              </td>
              <td class="px-3 py-2 align-top text-right">
                <input
                  v-model.number="line.quantity"
                  type="number"
                  min="1"
                  :max="lineFree(line) || undefined"
                  :aria-label="`Quantity for line ${index + 1}`"
                  class="w-20 rounded border border-gray-200 bg-white px-2 py-1.5 text-right text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
                <p v-if="lineErrors[line.key]?.quantity" class="mt-1 max-w-20 break-words text-right text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].quantity }}</p>
              </td>
              <td class="whitespace-nowrap px-3 py-2 align-top text-right font-semibold text-gray-800 dark:text-gray-200">
                {{ formatCurrency(lineAmount(line)) }}
              </td>
              <td class="px-3 py-2 align-top">
                <button
                  type="button"
                  class="rounded px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-40 dark:text-rose-300 dark:hover:bg-rose-500/10"
                  :disabled="lines.length <= 1"
                  :aria-label="`Remove line ${index + 1}`"
                  @click="removeLine(line.key)"
                >
                  Remove
                </button>
                <p v-if="lineErrors[line.key]?.row" class="mt-1 max-w-40 break-words text-[10px] text-rose-700 dark:text-rose-300">{{ lineErrors[line.key].row }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="linesTotal > 0" class="text-right text-xs font-medium text-gray-500 dark:text-gray-400">
        Slip total: <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(linesTotal) }}</span>
      </p>

      <!-- Action Buttons -->
    </form>
    <template #footer>
      <div class="flex justify-end gap-2">
        <button type="button" class="shrink-0 rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="$emit('close')">Cancel</button>
        <button v-if="mode === 'registered'" type="submit" form="assign-registered-form" class="shrink-0 rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-300" :disabled="busy || drafting || !canDraftPreview">{{ drafting ? 'Saving…' : 'Print & Draft' }}</button>
        <button v-else type="submit" form="assign-manual-form" class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50" :disabled="busy || !canManualPreview">{{ busy ? 'Saving…' : 'Preview Slip PDF' }}</button>
      </div>
    </template>
  </Modal>

  <SlipDocument
    v-if="slipData"
    :open="showSlip"
    title="Property Release Slip"
    subtitle="Preview only — nothing issued yet"
    :doc-no="slipData.docNo"
    :document-date="slipData.documentDate"
    :lines="slipData.lines"
    :purposes="purposes"
    :recipient-name="slipData.recipientName"
    :custodian-name="auth.displayName"
    :draft-mode="true"
    :primary-label="manualMode
      ? (busy ? 'Recording…' : 'Record Manual Assignment')
      : (drafting ? 'Saving…' : 'Save Draft')"
    :primary-disabled="busy || drafting"
    @back="backFromSlip"
    @primary="manualMode ? saveManualFromSlip($event) : saveDraftFromSlip()"
    @close="showSlip = false"
  />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import SlipDocument from '../../views/shared/SlipDocument.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    items: { type: Array, default: () => [] },
    endUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved', 'drafted']);
const toast = useToastStore();
const auth = useAuthStore();

const tabs = [
    { key: 'registered', label: 'Registered Staff Member' },
    { key: 'manual', label: 'Manual Loan / Issuance' },
];

const mode = ref('registered');
const busy = ref(false);
const drafting = ref(false);
const errors = ref({});
const staleNotice = ref('');
const showSlip = ref(false);
const slipData = ref(null);
const slipMode = ref(null);
const purposes = [
    'Official Use (Off-site)',
    'Transfer / Relocation',
    'Repair / Maintenance',
    'Disposal',
];

const todayISO = () => new Date().toISOString().slice(0, 10);
const today = computed(todayISO);

function freeQuantity(item) {
    if (item.free_quantity !== undefined && item.free_quantity !== null) {
        return Math.max(0, Number(item.free_quantity));
    }

    return Math.max(0, Number(item.quantity ?? 0) - Number(item.pending_hold ?? 0));
}

const assignableItems = computed(() => props.items.filter((item) => freeQuantity(item) > 0));

const registered = reactive({ user_id: '', transaction_date: todayISO() });

const manual = reactive({
    manual_recipient_name: '',
    building: '',
    room: '',
    manual_recipient_type: '',
    manual_contact: '',
    manual_notes: '',
    transaction_date: todayISO(),
});

let nextLineKey = 1;
const blankLine = () => ({ key: nextLineKey++, item_id: '', quantity: 1 });
const lines = ref([blankLine()]);
const lineErrors = ref({});

function lineItem(line) {
    return props.items.find((item) => String(item.item_id) === String(line.item_id)) ?? null;
}

function lineFree(line) {
    const item = lineItem(line);
    return item ? freeQuantity(item) : 0;
}

function lineAmount(line) {
    const item = lineItem(line);
    const qty = Number(line.quantity);
    const cost = Number(item?.unit_cost ?? 0);
    return Number.isFinite(qty) && Number.isFinite(cost) ? qty * cost : 0;
}

const linesTotal = computed(() => lines.value.reduce((sum, line) => sum + lineAmount(line), 0));

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(amount) || 0);
}

function addLine() {
    if (lines.value.length >= 100) return;
    lines.value.push(blankLine());
    errors.value = {};
}

function removeLine(key) {
    if (lines.value.length <= 1) return;
    lines.value = lines.value.filter((line) => line.key !== key);
    const remaining = { ...lineErrors.value };
    delete remaining[key];
    lineErrors.value = remaining;
}

function onLineItem(line) {
    const duplicate = lines.value.find((other) => other.key !== line.key && String(other.item_id) !== '' && String(other.item_id) === String(line.item_id));
    if (line.item_id !== '' && duplicate) {
        const item = lineItem(duplicate);
        const free = item ? freeQuantity(item) : 0;
        duplicate.quantity = Math.min(Number(duplicate.quantity) + Number(line.quantity), Math.max(free, 1));
        lines.value = lines.value.filter((other) => other.key !== line.key);
        if (!lines.value.length) {
            lines.value.push(blankLine());
        }
    }
    const item = lineItem(line);
    if (item?.serial_number) {
        line.quantity = 1;
    }
    const remaining = { ...lineErrors.value };
    delete remaining[line.key];
    lineErrors.value = remaining;
}

function checkLines() {
    const found = {};
    let ok = true;
    for (const line of lines.value) {
        const item = lineItem(line);
        if (!line.item_id || !item || freeQuantity(item) <= 0) {
            found[line.key] = { ...(found[line.key] ?? {}), item_id: 'Select an available stock record.' };
            ok = false;
            continue;
        }
        const qty = Number(line.quantity);
        const free = freeQuantity(item);
        if (!Number.isInteger(qty) || qty < 1) {
            found[line.key] = { ...(found[line.key] ?? {}), quantity: 'Quantity must be at least 1.' };
            ok = false;
        } else if (item.serial_number && qty !== 1) {
            found[line.key] = { ...(found[line.key] ?? {}), quantity: 'Serialized stock issues exactly 1 unit.' };
            ok = false;
        } else if (qty > free) {
            found[line.key] = { ...(found[line.key] ?? {}), quantity: 'Exceeds free stock (available minus units already promised).' };
            ok = false;
        }
    }
    if (!lines.value.length) {
        ok = false;
    }
    return { ok, errors: found };
}

const linesValid = computed(() => checkLines().ok);

function validateLines() {
    const { ok, errors: found } = checkLines();
    lineErrors.value = found;
    return ok;
}

const draftRecipientName = computed(() => props.endUsers.find((person) => String(person.id) === String(registered.user_id))?.name ?? '');
const manualMode = computed(() => slipMode.value === 'manual');

const canDraftPreview = computed(() => {
    if (!registered.user_id || !linesValid.value) {
        return false;
    }
    return true;
});

const canManualPreview = computed(() => {
    if (!manual.manual_recipient_name.trim() || !manual.transaction_date || !linesValid.value) {
        return false;
    }
    return true;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            lineErrors.value = {};
            busy.value = false;
            drafting.value = false;
            staleNotice.value = '';
            showSlip.value = false;
            slipData.value = null;
            slipMode.value = null;
            registered.transaction_date = todayISO();
            lines.value = [blankLine()];
        }
    },
);

watch(assignableItems, (list) => {
    if (!props.open) {
        return;
    }
    const live = new Set(list.map((item) => String(item.item_id)));
    const before = lines.value.length;
    lines.value = lines.value.filter((line) => line.item_id === '' || live.has(String(line.item_id)));
    if (!lines.value.length) {
        lines.value.push(blankLine());
    }
    if (lines.value.length !== before) {
        staleNotice.value = 'Previously available stock is now fully requested.';
    }
});

function resetAssignForm() {
    errors.value = {};
    lineErrors.value = {};
    staleNotice.value = '';
    showSlip.value = false;
    slipData.value = null;
    slipMode.value = null;
    registered.user_id = '';
    registered.transaction_date = todayISO();
    manual.manual_recipient_name = '';
    manual.building = '';
    manual.room = '';
    manual.manual_recipient_type = '';
    manual.manual_contact = '';
    manual.manual_notes = '';
    manual.transaction_date = todayISO();
    lines.value = [blankLine()];
}

function validateDraftStep1() {
    const stepErrors = {};
    if (!registered.user_id) {
        stepErrors.user_id = ['Select staff recipient.'];
    }
    if (!validateLines()) {
        stepErrors.items = ['Review the marked item lines.'];
    }
    errors.value = stepErrors;
    return Object.keys(stepErrors).length === 0;
}

function buildSlipLine(line) {
    const item = lineItem(line) ?? {};
    const qty = Number(line.quantity);
    const unitCost = Number(item.unit_cost ?? 0);
    return {
        item_id: Number(line.item_id),
        item_no: item.inventory_item_no ?? String(line.item_id),
        description: [item.item_name ?? 'Unknown item', item.serial_number ? `(SN: ${item.serial_number})` : '']
            .filter(Boolean).join(' ').trim(),
        qty,
        unit_cost: unitCost,
        amount: unitCost * qty,
    };
}

function buildDraftSlip() {
    return {
        docNo: 'DRAFT-PREVIEW',
        documentDate: registered.transaction_date || todayISO(),
        recipientName: draftRecipientName.value,
        lines: lines.value.map(buildSlipLine),
    };
}

function openSlipPreview() {
    if (!validateDraftStep1()) {
        return;
    }
    slipMode.value = 'registered';
    slipData.value = buildDraftSlip();
    showSlip.value = true;
}

function openManualSlip() {
    const stepErrors = {};
    if (!manual.manual_recipient_name.trim()) {
        stepErrors.manual_recipient_name = ['Enter the recipient name.'];
    }
    if (!manual.transaction_date) {
        stepErrors.transaction_date = ['Enter the transaction date.'];
    }
    if (!validateLines()) {
        stepErrors.items = ['Review the marked item lines.'];
    }
    errors.value = stepErrors;
    if (Object.keys(stepErrors).length > 0) {
        return;
    }

    slipMode.value = 'manual';
    slipData.value = {
        docNo: `MANUAL-${todayISO()}`,
        documentDate: manual.transaction_date || todayISO(),
        recipientName: manual.manual_recipient_name.trim(),
        lines: lines.value.map(buildSlipLine),
    };
    showSlip.value = true;
}

function backFromSlip() {
    showSlip.value = false;
}

function printSlip() {
    window.print();
}

function buildDraftPayload() {
    return {
        user_id: Number(registered.user_id),
        transaction_date: registered.transaction_date || todayISO(),
        items: lines.value.map((line) => ({
            item_id: Number(line.item_id),
            quantity: Number(line.quantity),
        })),
    };
}

function mapServerErrors(failures) {
    const headerErrors = {};
    const found = {};
    for (const [rawIndex, messages] of Object.entries(failures ?? {})) {
        const list = Array.isArray(messages) ? messages : [messages];
        const text = list.filter((message) => typeof message === 'string' && message).join(' ');
        const numeric = /^\d+$/.test(String(rawIndex));
        const dotted = String(rawIndex).match(/^items\.(\d+)\.(.+)$/);
        const line = numeric ? lines.value[Number(rawIndex)] : dotted ? lines.value[Number(dotted[1])] : null;
        if (line) {
            const field = dotted ? dotted[2] : 'row';
            found[line.key] = { ...(found[line.key] ?? {}), [field === 'quantity' || field === 'item_id' ? field : 'row']: text };
        } else {
            headerErrors[rawIndex] = list;
        }
    }
    return { headerErrors, found };
}

function applyServerErrors(failures) {
    const { headerErrors, found } = mapServerErrors(failures);
    errors.value = headerErrors;
    lineErrors.value = found;
    showSlip.value = false;
    return Object.keys(headerErrors).length + Object.keys(found).length;
}

async function saveDraftFromSlip() {
    drafting.value = true;

    const payload = buildDraftPayload();

    try {
        const { data } = await api.post('/custodian/assignments/draft', payload, { skipToast: true });
        toast.success('Draft saved', data.message ?? 'Print the slip, then allocate once the signed slip is collected.');
        resetAssignForm();
        emit('drafted');
    } catch (requestError) {
        const failures = requestError?.response?.data?.errors ?? {};
        if (applyServerErrors(failures) === 0) {
            toast.error('Draft could not be saved', requestError?.response?.data?.message ?? 'The draft could not be saved.');
        }
    } finally {
        drafting.value = false;
    }
}

async function submitManual() {
    busy.value = true;
    errors.value = {};
    lineErrors.value = {};

    if (!validateLines()) {
        busy.value = false;
        showSlip.value = false;
        return;
    }

    const payload = {
        assignment_type: 'manual',
        manual_recipient_name: manual.manual_recipient_name.trim(),
        transaction_date: manual.transaction_date,
        items: lines.value.map((line) => {
            const item = lineItem(line);
            return {
                item_id: Number(line.item_id),
                category_id: Number(item.category_id),
                unit: item.unit,
                quantity: Number(line.quantity),
            };
        }),
    };
    for (const key of ['building', 'room', 'manual_recipient_type', 'manual_contact', 'manual_notes']) {
        if (manual[key]) {
            payload[key] = manual[key];
        }
    }

    try {
        await api.post('/custodian/transactions/assign', payload);
        resetAssignForm();
        emit('saved');
    } catch (requestError) {
        const failures = requestError?.response?.data?.errors ?? {};
        if (applyServerErrors(failures) === 0) {
            toast.error('Assignment failed', requestError?.response?.data?.message ?? 'The manual assignment could not be recorded.');
        }
    } finally {
        busy.value = false;
    }
}

async function saveManualFromSlip(slipFields) {
    manual.manual_recipient_name = String(slipFields?.recipient_name ?? manual.manual_recipient_name).trim();
    await submitManual();
}
</script>
