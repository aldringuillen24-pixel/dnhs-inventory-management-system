<template>
  <Modal :open="open" title="Stock In New Items" subtitle="Record and add new inventory items into the stockroom." max-width="max-w-5xl" compact @close="$emit('close')">
    <form class="space-y-3 min-[480px]:space-y-4" @submit.prevent="submit">
      <div
        v-if="validationMessages.length || formError"
        role="alert"
        aria-live="assertive"
        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200 min-[480px]:px-4 min-[480px]:py-3 min-[480px]:text-sm"
      >
        <p class="font-semibold">{{ formError || 'Please correct the highlighted fields and try again.' }}</p>
        <ul v-if="validationMessages.length" class="mt-1 list-inside list-disc space-y-0.5">
          <li v-for="(error, index) in validationMessages" :key="`${error.field}-${index}`">
            <strong>{{ error.field }}:</strong> {{ error.message }}
          </li>
        </ul>
      </div>

      <div class="grid gap-3 lg:grid-cols-2 lg:items-start min-[480px]:gap-4">
      <!-- Section 1: Item Basic Information -->
      <div class="rounded-md bg-gray-50/60 p-3 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5 min-[480px]:p-4">
        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 mb-2.5 flex items-center gap-1.5 min-[480px]:mb-3">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          Primary Details
        </h4>

        <div class="grid gap-2.5 min-[360px]:grid-cols-2 min-[480px]:gap-3.5">
          <div class="min-[360px]:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-item-name">
              Item Name <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-item-name"
              v-model="form.item_name"
              type="text"
              required
              maxlength="255"
              placeholder="e.g. Epson EB-X06 Projector, Microscope 40x-1000x…"
              @input="clearFieldError('item_name')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="item_name" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-category">
              Category <span class="text-rose-500">*</span>
            </label>
            <select
              id="stock-category"
              v-model="form.category_id"
              required
              @change="clearFieldError('category_id')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3 min-[480px]:text-sm"
            >
              <option value="">Select a category</option>
              <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                {{ category.category_name }}
              </option>
            </select>
            <FieldError :errors="errors" field="category_id" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-unit">
              Measurement Unit <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-unit"
              v-model="form.unit"
              type="text"
              required
              maxlength="255"
              placeholder="piece, set, box, roll…"
              @input="clearFieldError('unit')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="unit" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-quantity">
              Quantity (1–100) <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-quantity"
              v-model.number="form.quantity"
              type="number"
              required
              min="1"
              max="100"
              @input="clearFieldError('quantity')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="quantity" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-unit-cost">
              Unit Cost (₱) <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute left-2.5 top-2 text-xs font-semibold text-gray-400 min-[480px]:left-3 min-[480px]:text-sm">₱</span>
              <input
                id="stock-unit-cost"
                v-model.number="form.unit_cost"
                type="number"
                required
                min="0"
                step="0.01"
                @input="clearFieldError('unit_cost')"
                class="block w-full rounded-md border border-gray-200 bg-white py-2 pl-7 pr-2.5 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:pl-8 min-[480px]:pr-3 min-[480px]:text-sm"
              />
            </div>
            <FieldError :errors="errors" field="unit_cost" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-date">
              Date Acquired <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-date"
              v-model="form.date_acquired"
              type="date"
              required
              :max="todayMax"
              @change="clearFieldError('date_acquired')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="date_acquired" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-lifespan">
              Estimated Lifespan (Years)
            </label>
            <input
              id="stock-lifespan"
              v-model.number="form.lifespan_years"
              type="number"
              min="1"
              placeholder="e.g. 5"
              @input="clearFieldError('lifespan_years')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="lifespan_years" />
          </div>
        </div>
      </div>

      <!-- Section 2: Storage Location & Identification -->
      <div class="rounded-md bg-gray-50/60 p-3 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5 min-[480px]:p-4">
        <h4 class="text-xs font-bold uppercase tracking-wider text-teal-800 dark:text-teal-300 mb-2.5 flex items-center gap-1.5 min-[480px]:mb-3">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Tracking & Storage Location
        </h4>

        <div class="grid gap-2.5 min-[360px]:grid-cols-2 min-[480px]:gap-3.5">
          <div class="min-[360px]:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-ics">
              Inventory Custodian Slip (ICS) No.
            </label>
            <input
              id="stock-ics"
              v-model="form.ics_no"
              type="text"
              maxlength="255"
              placeholder="e.g. ICS-2026-001"
              @input="clearFieldError('ics_no')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 font-mono min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="ics_no" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-building">
              Building
            </label>
            <input
              id="stock-building"
              v-model="form.building"
              type="text"
              maxlength="255"
              placeholder="e.g. Science Laboratory Wing"
              @input="clearFieldError('building')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="building" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-room">
              Room
            </label>
            <input
              id="stock-room"
              v-model="form.room"
              type="text"
              maxlength="255"
              placeholder="e.g. Room 204"
              @input="clearFieldError('room')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="room" />
          </div>

          <div class="min-[360px]:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-description">
              Item Specifications / Description
            </label>
            <textarea
              id="stock-description"
              v-model="form.description"
              rows="2"
              placeholder="Condition, technical specifications, serial marks, or supplier notes…"
              @input="clearFieldError('description')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="description" />
          </div>
        </div>
      </div>
      </div>

      <!-- Section 3: Serial Numbers (Required by Category) -->
      <div
        v-if="requiresSerial"
        class="rounded-md border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900/40 dark:bg-amber-950/20 min-[480px]:p-4"
      >
        <div class="flex items-center gap-2 mb-2">
          <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p class="text-xs font-bold text-amber-900 dark:text-amber-300">
            Serial Numbers Required for Category ({{ form.quantity }} items)
          </p>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 mt-3">
          <div v-for="index in serialCount" :key="index" class="relative">
            <input
              v-model="serials[index - 1]"
              type="text"
              required
              maxlength="255"
              :placeholder="`Serial number #${index}`"
              @input="clearFieldError(`serial_numbers.${index - 1}`)"
              class="block w-full rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs text-gray-800 font-mono transition-colors focus:border-emerald-500 focus:outline-none dark:border-amber-800 dark:bg-gray-800 dark:text-gray-100"
            />
            <FieldError
              :errors="errors"
              :field="`serial_numbers.${index - 1}`"
            />
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-2 pt-1 min-[480px]:gap-3 min-[480px]:pt-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5 min-[480px]:px-4 min-[480px]:py-2.5 min-[480px]:text-sm"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg disabled:opacity-50 min-[480px]:gap-2 min-[480px]:px-5 min-[480px]:py-2.5 min-[480px]:text-sm"
          :disabled="busy"
        >
          <svg v-if="busy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          {{ busy ? 'Saving Stock…' : 'Save & Stock In' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const busy = ref(false);
const errors = ref({});
const formError = ref('');
const serials = ref([]);

const form = reactive({
    item_name: '',
    category_id: '',
    unit: '',
    quantity: 1,
    unit_cost: 0,
    date_acquired: new Date().toISOString().slice(0, 10),
    lifespan_years: null,
    ics_no: '',
    building: '',
    room: '',
    description: '',
});

const selectedCategory = computed(() =>
    props.categories.find((category) => String(category.category_id) === String(form.category_id)),
);

const todayMax = computed(() => new Date().toISOString().slice(0, 10));

const requiresSerial = computed(() => selectedCategory.value?.requires_serial_number === true || selectedCategory.value?.requires_serial_number === 1);

const serialCount = computed(() => Math.min(Math.max(Number(form.quantity) || 1, 1), 100));
const validationMessages = computed(() =>
    Object.entries(errors.value).flatMap(([key, messages]) => {
        const field = key.startsWith('serial_numbers.')
            ? `Serial number #${Number(key.split('.')[1]) + 1}`
            : key.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        return (Array.isArray(messages) ? messages : [messages])
            .filter((message) => typeof message === 'string' && message)
            .map((message) => ({ field, message }));
    }),
);

watch(
    () => [form.category_id, form.quantity],
    () => {
        errors.value = {};
        formError.value = '';
        serials.value = Array.from({ length: requiresSerial.value ? serialCount.value : 0 }, (_, index) => serials.value[index] ?? '');
    },
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            resetForm();
        }
    },
);

function resetForm() {
    errors.value = {};
    formError.value = '';
    busy.value = false;
    serials.value = [];
    form.item_name = '';
    form.category_id = '';
    form.unit = '';
    form.quantity = 1;
    form.unit_cost = 0;
    form.date_acquired = new Date().toISOString().slice(0, 10);
    form.lifespan_years = null;
    form.ics_no = '';
    form.building = '';
    form.room = '';
    form.description = '';
}

async function submit() {
    if (busy.value) return;

    busy.value = true;
    errors.value = {};
    formError.value = '';

    const payload = {
        item_name: form.item_name,
        category_id: Number(form.category_id),
        unit: form.unit,
        quantity: Number(form.quantity),
        unit_cost: Number(form.unit_cost),
        date_acquired: form.date_acquired,
        description: form.description || null,
        ics_no: form.ics_no || null,
        building: form.building || null,
        room: form.room || null,
    };

    if (form.lifespan_years) {
        payload.lifespan_years = Number(form.lifespan_years);
    }

    if (requiresSerial.value) {
        payload.serial_numbers = serials.value.slice(0, serialCount.value);
    }

    try {
        await api.post('/custodian/inventory/stock-in', payload);
        resetForm();
        emit('saved');
    } catch (requestError) {
        const response = requestError?.response;
        const responseErrors = response?.data?.errors;
        errors.value = responseErrors && typeof responseErrors === 'object' && !Array.isArray(responseErrors)
            ? responseErrors
            : {};

        if (response?.status === 422 && validationMessages.value.length) {
            formError.value = '';
        } else {
            formError.value = stockInErrorMessage(response, requestError);
        }
    } finally {
        busy.value = false;
    }
}

function clearFieldError(field) {
    if (Object.prototype.hasOwnProperty.call(errors.value, field)) {
        const remaining = { ...errors.value };
        delete remaining[field];
        errors.value = remaining;
    }
    formError.value = '';
}

function stockInErrorMessage(response, requestError) {
    if (!response) {
        return requestError?.code === 'ERR_NETWORK'
            ? 'Could not reach the server. Check your connection and try again. Your form entries are still here.'
            : 'Stock-in could not be completed. Check your connection and try again.';
    }

    if (response.status === 422) {
        return response.data?.message ?? 'Some submitted details are invalid. Review the form and try again.';
    }
    if (response.status === 403) {
        return response.data?.message ?? 'You do not have permission to stock in inventory.';
    }
    if (response.status === 419) {
        return 'Your session expired. Refresh the page, then try stock-in again.';
    }
    if (response.status === 429) {
        return 'Too many attempts. Wait a moment, then try again.';
    }
    if (response.status >= 500) {
        return 'The server could not save this stock-in. No success was reported; try again or contact an administrator.';
    }

    return response.data?.message ?? `Stock-in could not be completed (HTTP ${response.status}). Review the form and try again.`;
}
</script>
