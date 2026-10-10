<template>
  <Modal
    :open="open"
    :title="step === 'category' ? 'New Request' : 'Select an Item'"
    :subtitle="step === 'category'
      ? 'Choose a category to request from'
      : selectedCategory
        ? `Items in ${selectedCategory.category_name}`
        : 'Submit an item requisition to the property custodian'"
    max-width="max-w-2xl"
    compact
    fixed-body
    @close="$emit('close')"
  >
    <form class="compact-modal-body" @submit.prevent="submit">
      <!-- STEP 1: CATEGORY -->
      <div v-if="step === 'category'" class="min-h-0 flex-1 overflow-y-auto">
        <div v-if="!categories.length" class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">
          No categories have been set up yet.
        </div>

        <!-- Four per row: the first four across, then the next four below. -->
        <ul v-else class="grid grid-cols-4 gap-1.5">
          <li v-for="category in categories" :key="category.category_id">
            <button
              type="button"
              :title="category.category_name"
              class="flex h-full w-full flex-col items-start gap-1 rounded-md border border-gray-200 px-2 py-2 text-left transition-colors hover:border-brand-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/5"
              @click="chooseCategory(category)"
            >
              <span class="block w-full truncate text-xs font-semibold leading-tight text-gray-800 dark:text-gray-100">
                {{ category.category_name }}
              </span>
              <span class="block w-full truncate text-[11px] leading-tight text-gray-500 dark:text-gray-400">
                <!-- A category with nothing catalogued is still selectable:
                     the end user can still name what they need. -->
                {{ category.items.length
                  ? `${category.items.length} item${category.items.length === 1 ? '' : 's'}`
                  : 'Nothing catalogued' }}
              </span>
              <span
                class="mt-auto w-full rounded-md px-1.5 py-0.5 text-center text-[10px] font-bold leading-tight"
                :class="category.available_item_count > 0
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                  : 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'"
              >
                {{ category.available_item_count > 0 ? `${category.available_item_count} in stock` : 'Out of stock' }}
              </span>
            </button>
          </li>
        </ul>
      </div>

      <!-- STEP 2: ITEM -->
      <template v-else>
        <!-- Out-of-stock notice. Shown when the chosen category has nothing
             available, so the end user understands why this is not a normal
             requisition before they type anything. -->
        <div
          v-if="isCategoryEmpty"
          class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
        >
          <p class="font-semibold">
            <template v-if="isCategoryUncatalogued">
              Nothing in {{ selectedCategory?.category_name }} has been catalogued yet.
            </template>
            <template v-else>
              No items in {{ selectedCategory?.category_name }} are currently in stock.
            </template>
          </p>
          <p class="mt-1">You can still submit a request. It will be recorded and used to plan procurement.</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto" :class="isCategoryEmpty ? 'mt-3' : ''">
          <!-- Nothing catalogued in this category: there is no name to pick, so
               the end user types what they need. It is recorded verbatim against
               the category and never matched to inventory. -->
          <template v-if="isCategoryUncatalogued">
            <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-free-text">Item needed</label>
            <input
              id="request-free-text"
              v-model="freeTextName"
              type="text"
              required
              maxlength="255"
              placeholder="Name the item you need"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            />
          </template>

          <template v-else>
            <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-item-type">Item</label>
            <select
              id="request-item-type"
              v-model="selectedItemKey"
              required
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            >
              <option value="">Select an item</option>
              <option v-for="item in selectedCategory?.items ?? []" :key="itemKey(item)" :value="itemKey(item)">
                {{ item.item_name }} · {{ item.unit }} · {{ item.available_quantity > 0 ? `${item.available_quantity} ${item.unit} available` : '0 available' }}
              </option>
            </select>
          </template>
          <FieldError :errors="errors" field="item_name" />

          <div class="mt-3">
            <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-quantity">Quantity</label>
            <input
              id="request-quantity"
              v-model.number="quantity"
              type="number"
              min="1"
              required
              placeholder="Enter quantity"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            />
            <FieldError :errors="errors" field="quantity" />
          </div>

          <div class="mt-3">
            <label class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300" for="request-notes">
              Purpose or notes
              <span v-if="isCategoryEmpty" class="font-normal text-amber-600 dark:text-amber-400">(pre-filled — edit if needed)</span>
              <span v-else class="font-normal text-gray-400">(optional)</span>
            </label>
            <textarea
              id="request-notes"
              v-model="notes"
              rows="2"
              maxlength="1000"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            />
            <FieldError :errors="errors" field="notes" />
          </div>
        </div>
      </template>

      <div class="flex shrink-0 items-center justify-between gap-2 pt-3">
        <button
          v-if="step === 'item'"
          type="button"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
          @click="step = 'category'"
        >
          Back
        </button>
        <button
          v-else
          type="button"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
          @click="$emit('close')"
        >
          Cancel
        </button>

        <button
          v-if="step === 'item'"
          type="submit"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Submitting…' : submitLabel }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    // Category-grouped catalogue. Every requestable item type is included,
    // including types with zero stock, so an out-of-stock item can still be
    // named and requested.
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const step = ref('category');
const selectedCategory = ref(null);
const selectedItemKey = ref('');
const freeTextName = ref('');
const quantity = ref(1);
const notes = ref('');
const busy = ref(false);
const errors = ref({});

watch(
    () => props.open,
    (open) => {
        if (open) {
            step.value = 'category';
            selectedCategory.value = null;
            selectedItemKey.value = '';
            freeTextName.value = '';
            quantity.value = 1;
            notes.value = '';
            errors.value = {};
            busy.value = false;
        }
    },
);

// True when nothing in the chosen category is available, which is what
// switches the request into unmet demand.
const isCategoryEmpty = computed(() => (selectedCategory.value?.available_item_count ?? 0) === 0);

// True when the category has no item types at all, so there is no name to pick
// and the end user has to type what they need.
const isCategoryUncatalogued = computed(() => (selectedCategory.value?.items?.length ?? 0) === 0);

const submitLabel = computed(() => (isCategoryEmpty.value ? 'Submit Request for Procurement' : 'Submit Request'));

// Name + category + unit, because a name alone is not an identity — the same
// name can exist under two categories or two units.
function itemKey(item) {
    return `${item.item_name}|${item.category_id}|${item.unit}`;
}

function chosenItem() {
    return (selectedCategory.value?.items ?? []).find((item) => itemKey(item) === selectedItemKey.value) ?? null;
}

function chooseCategory(category) {
    selectedCategory.value = category;
    selectedItemKey.value = '';
    freeTextName.value = '';
    quantity.value = 1;
    errors.value = {};
    // Pre-fill the reason only when there is nothing to allocate, so the note
    // explains the situation the end user is actually reporting. The custodian
    // reads this text, so it is written for them. It is a convenience, not an
    // instruction the system parses.
    notes.value = category.available_item_count > 0
        ? ''
        : `No ${category.category_name} items are currently in stock. Requesting these so they can be included in the next procurement cycle.`;
    step.value = 'item';
}

async function submit() {
    const category = selectedCategory.value;
    const item = chosenItem();

    // An uncatalogued category has nothing to pick, so the typed name is the
    // item. It goes through the same endpoint and lands as unmet demand.
    if (isCategoryUncatalogued.value && !freeTextName.value.trim()) {
        errors.value = { item_name: ['Name the item you need.'] };
        return;
    }

    if (!isCategoryUncatalogued.value && !item) {
        errors.value = { item_name: ['Select an item.'] };
        return;
    }

    busy.value = true;
    errors.value = {};

    try {
        // The server decides the status from current availability, not the
        // client — a stock change between load and submit must not let an
        // empty request into the approval queue.
        await api.post('/end-user/requests', {
            item_name: item ? item.item_name : freeTextName.value.trim(),
            category_id: Number(category.category_id),
            // No inventory row means no unit to copy. 'units' is the same
            // placeholder the forecast reader uses for a missing unit.
            unit: item ? item.unit : 'units',
            quantity: Number(quantity.value),
            notes: notes.value || null,
        });
        emit('saved');
    } catch (requestError) {
        errors.value = requestError?.response?.data?.errors ?? {};
    } finally {
        busy.value = false;
    }
}
</script>
