<template>
  <Modal
    :open="open"
    :title="isEdit ? 'Edit Category' : 'New Category'"
    :subtitle="isEdit ? 'Rename or change tracking flags. Rows already using this category keep working.' : 'Create a category that stock-in rows can use right away.'"
    max-width="max-w-lg"
    z-class="z-[100002]"
    :close-on-escape="true"
    @close="$emit('close')"
  >
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label for="category-form-name" class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">
          Category name <span class="text-rose-500">*</span>
        </label>
        <input
          id="category-form-name"
          v-model.trim="form.category_name"
          type="text"
          maxlength="255"
          required
          placeholder="e.g. Office Supplies"
          class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <p v-if="fieldErrors.category_name" class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ fieldErrors.category_name }}</p>
      </div>

      <div>
        <label for="category-form-lifespan" class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">
          Default lifespan (years)
        </label>
        <input
          id="category-form-lifespan"
          v-model="form.default_lifespan_years"
          type="number"
          min="1"
          max="65535"
          placeholder="Optional"
          class="block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <p v-if="fieldErrors.default_lifespan_years" class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ fieldErrors.default_lifespan_years }}</p>
      </div>

      <fieldset class="space-y-2 rounded-md border border-gray-100 bg-gray-50/60 p-3 dark:border-white/5 dark:bg-white/[0.02]">
        <legend class="px-1 text-xs font-semibold text-gray-700 dark:text-gray-300">Tracking flags</legend>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="form.requires_serial_number" type="checkbox" class="h-4 w-4 accent-emerald-600" />
          Requires serial number
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="form.requires_qr_code" type="checkbox" class="h-4 w-4 accent-emerald-600" />
          Requires QR code
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="form.is_maintenance_eligible" type="checkbox" class="h-4 w-4 accent-emerald-600" />
          Eligible for maintenance / repair
        </label>
      </fieldset>

      <p v-if="formError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
        {{ formError }}
      </p>

      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="rounded-md bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ saving ? 'Saving…' : isEdit ? 'Save changes' : 'Create category' }}
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  category: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const isEdit = computed(() => props.category?.category_id != null);
const saving = ref(false);
const form = ref(blankForm());
const fieldErrors = ref({});
const formError = ref('');

function blankForm() {
  return {
    category_name: '',
    default_lifespan_years: '',
    requires_serial_number: false,
    requires_qr_code: false,
    is_maintenance_eligible: false,
  };
}

watch(() => props.open, (isOpen) => {
  if (!isOpen) return;
  saving.value = false;
  fieldErrors.value = {};
  formError.value = '';
  const source = props.category;
  form.value = {
    category_name: source?.category_name ?? '',
    default_lifespan_years: source?.default_lifespan_years ?? '',
    requires_serial_number: Boolean(source?.requires_serial_number),
    requires_qr_code: Boolean(source?.requires_qr_code),
    is_maintenance_eligible: Boolean(source?.is_maintenance_eligible),
  };
});

function firstMessage(messages) {
  if (Array.isArray(messages)) return messages.find((message) => typeof message === 'string' && message) ?? '';
  return typeof messages === 'string' ? messages : '';
}

async function submit() {
  if (saving.value) return;
  saving.value = true;
  fieldErrors.value = {};
  formError.value = '';

  const payload = {
    category_name: form.value.category_name.trim(),
    default_lifespan_years: form.value.default_lifespan_years === '' || form.value.default_lifespan_years == null
      ? null
      : Number(form.value.default_lifespan_years),
    requires_serial_number: Boolean(form.value.requires_serial_number),
    requires_qr_code: Boolean(form.value.requires_qr_code),
    is_maintenance_eligible: Boolean(form.value.is_maintenance_eligible),
  };

  try {
    const { data } = isEdit.value
      ? await api.patch(`/custodian/categories/${props.category.category_id}`, payload)
      : await api.post('/custodian/categories', payload);
    emit('saved', data?.category);
  } catch (error) {
    if (error?.response?.status === 422) {
      const errors = error.response.data?.errors ?? {};
      const mapped = {};
      for (const [field, messages] of Object.entries(errors)) {
        mapped[field] = firstMessage(messages);
      }
      fieldErrors.value = mapped;
      formError.value = error.response.data?.message ?? 'Some details are invalid. Review the form and try again.';
    } else {
      formError.value = error?.response?.data?.message ?? 'Could not save this category. Check your connection and try again.';
    }
  } finally {
    saving.value = false;
  }
}
</script>
