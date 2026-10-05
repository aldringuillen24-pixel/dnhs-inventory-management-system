<template>
  <Modal :open="open" title="Edit Inventory Item" subtitle="Only available stock items can be edited directly." max-width="max-w-3xl" fixed-body @close="$emit('close')">
    <div v-if="loading" class="min-h-0 space-y-3 overflow-y-auto p-4">
      <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
    </div>

    <form v-else class="flex min-h-0 flex-col" @submit.prevent="submit">
      <div class="min-h-0 space-y-3 overflow-y-auto">
        <!-- Primary Details -->
        <div class="shrink-0 rounded-md bg-gray-50/60 p-4 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5">
          <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 mb-3 flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Primary Item Information
          </h4>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-item-name">
                Item Name <span class="text-rose-500">*</span>
              </label>
              <input
                id="edit-item-name"
                v-model="form.item_name"
                type="text"
                required
                maxlength="255"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="item_name" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-category">
                Category <span class="text-rose-500">*</span>
              </label>
              <select
                id="edit-category"
                v-model="form.category_id"
                required
                class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              >
                <option value="">Select a category</option>
                <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                  {{ category.category_name }}
                </option>
              </select>
              <FieldError :errors="errors" field="category_id" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-unit">
                Measurement Unit <span class="text-rose-500">*</span>
              </label>
              <input
                id="edit-unit"
                v-model="form.unit"
                type="text"
                required
                maxlength="255"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="unit" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-quantity">
                Quantity (1–100) <span class="text-rose-500">*</span>
              </label>
              <input
                id="edit-quantity"
                v-model.number="form.quantity"
                type="number"
                required
                min="1"
                max="100"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="quantity" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-unit-cost">
                Unit Cost (₱) <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <span class="pointer-events-none absolute left-3 top-2 text-sm font-semibold text-gray-400">₱</span>
                <input
                  id="edit-unit-cost"
                  v-model.number="form.unit_cost"
                  type="number"
                  required
                  min="0"
                  step="0.01"
                  class="block w-full rounded-md border border-gray-200 bg-white pl-8 pr-3 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
              </div>
              <FieldError :errors="errors" field="unit_cost" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-date">
                Date Acquired <span class="text-rose-500">*</span>
              </label>
              <input
                id="edit-date"
                v-model="form.date_acquired"
                type="date"
                required
                class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="date_acquired" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-lifespan">
                Estimated Lifespan (Years)
              </label>
              <input
                id="edit-lifespan"
                v-model.number="form.lifespan_years"
                type="number"
                min="1"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="lifespan_years" />
            </div>
          </div>
        </div>

        <!-- Tracking & Location -->
        <div class="shrink-0 rounded-md bg-gray-50/60 p-4 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5">
          <h4 class="text-xs font-bold uppercase tracking-wider text-teal-800 dark:text-teal-300 mb-3 flex items-center gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
            Tracking & Location
          </h4>

          <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-ics">
                Inventory Custodian Slip (ICS) No.
              </label>
              <input
                id="edit-ics"
                v-model="form.ics_no"
                type="text"
                maxlength="255"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 font-mono"
              />
              <FieldError :errors="errors" field="ics_no" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-building">
                Building
              </label>
              <input
                id="edit-building"
                v-model="form.building"
                type="text"
                maxlength="255"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="building" />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-room">
                Room
              </label>
              <input
                id="edit-room"
                v-model="form.room"
                type="text"
                maxlength="255"
                class="block w-full rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="room" />
            </div>

            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="edit-description">
                Description / Notes
              </label>
              <textarea
                id="edit-description"
                v-model="form.description"
                rows="3"
                class="block h-24 w-full resize-none rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
              <FieldError :errors="errors" field="description" />
            </div>
          </div>
        </div>

        <div
          v-if="serialNumbers.length"
          class="shrink-0 rounded-md border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-950/20"
        >
          <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300">
            Serial Numbers ({{ serialNumbers.length }})
          </h4>
          <div
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            :class="serialNumbers.length > 6 ? 'max-h-44 overflow-y-auto pr-1' : ''"
          >
            <div
              v-for="(serialNumber, index) in serialNumbers"
              :key="`${serialNumber}-${index}`"
              class="space-y-1"
            >
              <label
                :for="`edit-serial-number-${index}`"
                class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
              >
                Item {{ index + 1 }} Serial Number
              </label>
              <input
                :id="`edit-serial-number-${index}`"
                v-model="serialNumbers[index]"
                type="text"
                required
                maxlength="255"
                :aria-invalid="Boolean(errors[`serial_numbers.${index}`])"
                class="block w-full rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-gray-800 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-amber-800/50 dark:bg-gray-900 dark:text-gray-200"
              />
              <FieldError :errors="errors" :field="`serial_numbers.${index}`" />
            </div>
          </div>
          <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Edit each serial number as needed. Each field matches one inventory record in this group.
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-3 flex shrink-0 justify-end gap-3 border-t border-gray-100 pt-3 dark:border-white/5">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import { useToastStore } from '../../stores/toast';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  itemId: { type: [Number, String], default: null },
  categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const toast = useToastStore();
const loading = ref(false);
const busy = ref(false);
const errors = ref({});
const serialNumbers = ref([]);
const form = reactive({
  item_name: '',
  category_id: '',
  unit: '',
  quantity: 1,
  unit_cost: 0,
  date_acquired: '',
  lifespan_years: null,
  ics_no: '',
  building: '',
  room: '',
  description: '',
});

function dateOnly(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

async function fetchItem() {
  if (!props.open || !props.itemId) return;
  loading.value = true;
  errors.value = {};
  try {
    const { data } = await api.get(`/custodian/inventory/${props.itemId}`);
    const item = data.inventoryItem ?? data;
    serialNumbers.value = data.serialNumbers ?? (item.serial_number ? [item.serial_number] : []);
    form.item_name = item.item_name ?? '';
    form.category_id = item.category_id ?? '';
    form.unit = item.unit ?? '';
    form.quantity = item.quantity ?? 1;
    form.unit_cost = item.unit_cost ?? 0;
    form.date_acquired = dateOnly(item.date_acquired);
    form.lifespan_years = item.lifespan_years ?? null;
    form.ics_no = item.ics_no ?? '';
    form.building = item.building ?? '';
    form.room = item.room ?? '';
    form.description = item.description ?? '';
  } catch (requestError) {
    serialNumbers.value = [];
    toast.error('Error', requestError?.response?.data?.message ?? 'Could not load the item.');
    emit('close');
  } finally {
    loading.value = false;
  }
}

watch(() => [props.open, props.itemId], fetchItem);

async function submit() {
  busy.value = true;
  errors.value = {};
  try {
    await api.patch(`/custodian/inventory/${props.itemId}`, {
      item_name: form.item_name,
      category_id: Number(form.category_id),
      unit: form.unit,
      quantity: Number(form.quantity),
      unit_cost: Number(form.unit_cost),
      date_acquired: form.date_acquired,
      ...(form.lifespan_years ? { lifespan_years: Number(form.lifespan_years) } : {}),
      description: form.description || null,
      ics_no: form.ics_no || null,
      building: form.building || null,
      room: form.room || null,
      ...(serialNumbers.value.length
        ? { serial_numbers: serialNumbers.value.map((serialNumber) => serialNumber.trim()) }
        : {}),
    });
    emit('saved');
  } catch (requestError) {
    errors.value = requestError?.response?.data?.errors ?? {};
    if (requestError?.response?.status === 422 && !Object.keys(errors.value).length) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not update the item.');
    }
  } finally {
    busy.value = false;
  }
}
</script>
