<template>
  <Modal
    :open="open"
    :title="step === 'category' ? 'New Request' : 'Select an Item'"
    :subtitle="step === 'category'
      ? 'Choose a category to request from'
      : selectedCategory
        ? `Items in ${selectedCategory.category_name}`
        : 'Submit an item requisition to the property custodian'"
    max-width="max-w-4xl"
    fixed-body
    @close="$emit('close')"
  >
    <form id="request-item-form" class="compact-modal-body flex-1" @submit.prevent="submit">
      <!-- STEP 1: CATEGORY -->
      <div v-if="step === 'category'" class="min-h-0 flex-1 overflow-y-auto pr-1">
        <div v-if="!categories.length" class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
          No categories have been set up yet.
        </div>

        <!-- 3 per row on small, 4 per row on md/lg: larger cards with spacious padding and clear hierarchy -->
        <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
          <li v-for="category in categories" :key="category.category_id">
            <button
              type="button"
              :title="category.category_name"
              class="group flex h-full w-full flex-col justify-between rounded-xl border border-gray-200/90 bg-white p-3.5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-brand-400 hover:shadow-md dark:border-gray-700/80 dark:bg-gray-800/80 dark:hover:border-brand-500 dark:hover:bg-gray-800"
              @click="chooseCategory(category)"
            >
              <div class="mb-3 space-y-1">
                <span class="block text-sm font-semibold text-gray-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-400">
                  {{ category.category_name }}
                </span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                  {{ category.items.length
                    ? `${category.items.length} item${category.items.length === 1 ? '' : 's'}`
                    : 'Nothing catalogued' }}
                </span>
              </div>
              <span
                class="w-full rounded-md px-2 py-1 text-center text-[11px] font-semibold tracking-wide"
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
        <div class="space-y-4">
          <!-- Category Context Ribbon -->
          <div class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-800/40">
            <div class="flex items-center gap-2.5">
              <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                <LucideIcon :icon="Layers" class="h-4 w-4" />
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Selected Category</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ selectedCategory?.category_name }}</p>
              </div>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
              @click="step = 'category'"
            >
              <LucideIcon :icon="ArrowLeft" class="h-3.5 w-3.5" />
              Change Category
            </button>
          </div>

          <!-- Out-of-stock notice -->
          <div
            v-if="isCategoryEmpty"
            class="flex items-start gap-2.5 rounded-lg border border-amber-200/80 bg-amber-50/90 p-3 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
          >
            <LucideIcon :icon="AlertTriangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <div>
              <p class="font-semibold">
                <template v-if="isCategoryUncatalogued">
                  Nothing in {{ selectedCategory?.category_name }} has been catalogued yet.
                </template>
                <template v-else>
                  No items in {{ selectedCategory?.category_name }} are currently in stock.
                </template>
              </p>
              <p class="mt-0.5 text-[11px] leading-relaxed text-amber-700 dark:text-amber-300">
                You can still submit a request. It will be recorded and forwarded to the custodian for procurement planning.
              </p>
            </div>
          </div>

          <!-- Form Fields Container -->
          <div class="space-y-4 rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <!-- Item Field -->
            <div>
              <template v-if="isCategoryUncatalogued">
                <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="request-free-text">
                  Item Needed <span class="text-rose-500">*</span>
                </label>
                <input
                  id="request-free-text"
                  v-model="freeTextName"
                  type="text"
                  required
                  maxlength="255"
                  placeholder="e.g. HDMI Cable, Wireless Mouse, Projector Remote"
                  class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-800 shadow-sm transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </template>

              <template v-else>
                <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="request-item-type">
                  Select Item <span class="text-rose-500">*</span>
                </label>
                <select
                  id="request-item-type"
                  v-model="selectedItemKey"
                  required
                  class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-800 shadow-sm transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                  <option value="">Choose an item from this category…</option>
                  <option v-for="item in selectedCategory?.items ?? []" :key="itemKey(item)" :value="itemKey(item)">
                    {{ item.item_name }} ({{ item.unit }}) — {{ itemLabel(item) }}
                  </option>
                </select>
              </template>
              <FieldError :errors="errors" field="item_name" />
            </div>

            <!-- Item Stock Card Preview (when item is selected) -->
            <div
              v-if="chosenItem()"
              class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50/70 p-3 text-xs dark:border-gray-800 dark:bg-gray-800/40"
            >
              <div class="space-y-0.5">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ chosenItem().item_name }}</span>
                <p class="text-[11px] text-gray-400">Unit: {{ chosenItem().unit }}</p>
              </div>
              <div class="text-right">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                  :class="selectedFree > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'"
                >
                  {{ selectedFree > 0 ? `${selectedFree} available` : '0 available' }}
                </span>
                <p v-if="chosenItem() && Number(chosenItem().pending_quantity ?? 0) > 0" class="mt-0.5 text-[10px] text-amber-600 dark:text-amber-400">
                  ({{ chosenItem().pending_quantity }} pending approvals)
                </p>
              </div>
            </div>

            <!-- Quantity Field with Quick Stepper -->
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="request-quantity">
                Quantity <span class="text-rose-500">*</span>
              </label>
              <div class="flex items-center gap-2">
                <div class="relative w-40">
                  <input
                    id="request-quantity"
                    v-model.number="quantity"
                    type="number"
                    min="1"
                    :max="selectedFree || undefined"
                    required
                    placeholder="1"
                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-800 shadow-sm transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                  />
                </div>
                <div class="flex items-center gap-1">
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    :disabled="quantity <= 1"
                    @click="quantity = Math.max(1, quantity - 1)"
                  >
                    -
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    :disabled="selectedFree > 0 && quantity >= selectedFree"
                    @click="quantity++"
                  >
                    +
                  </button>
                  <span v-if="chosenItem()" class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ chosenItem().unit }}{{ quantity > 1 ? 's' : '' }}
                  </span>
                </div>
              </div>
              <FieldError :errors="errors" field="quantity" />
            </div>

            <!-- Notes Field -->
            <div>
              <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="request-notes">
                Purpose or Notes
                <span v-if="isCategoryEmpty" class="font-normal text-amber-600 dark:text-amber-400">(pre-filled for procurement)</span>
                <span v-else class="font-normal text-gray-400">(optional)</span>
              </label>
              <textarea
                id="request-notes"
                v-model="notes"
                rows="3"
                maxlength="1000"
                placeholder="State the purpose, classroom/office location, or specific requirements…"
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-800 shadow-sm transition-colors focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
              />
              <FieldError :errors="errors" field="notes" />
            </div>
          </div>
        </div>
      </template>

    </form>
    <template #footer>
      <div class="flex items-center justify-between gap-2">
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
          form="request-item-form"
          class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
          :disabled="busy"
        >
          {{ busy ? 'Submitting…' : submitLabel }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { AlertTriangle, ArrowLeft, Layers } from 'lucide';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';

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

function itemLabel(item) {
    if ((item.available_quantity ?? 0) <= 0) {
        return '0 available';
    }
    const pending = Number(item.pending_quantity ?? 0);
    const free = Number(item.free_quantity ?? item.available_quantity ?? 0);
    return pending > 0
        ? `${free} ${item.unit} free (${pending} pending)`
        : `${item.available_quantity} ${item.unit} available`;
}

const selectedFree = computed(() => {
    const item = chosenItem();
    if (!item) {
        return 0;
    }
    return Number(item.free_quantity ?? item.available_quantity ?? 0);
});

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
