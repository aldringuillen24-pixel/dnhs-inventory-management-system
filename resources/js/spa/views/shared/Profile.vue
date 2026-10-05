<template>
  <ProfilePanel
    :display-name="auth.displayName"
    :role="auth.role"
    :user="auth.user"
    :editable="Boolean(profileEndpoint)"
    :needs-username="needsUsername"
    :needs-name-fields="needsNameFields"
    :needs-password-confirmation="needsPasswordConfirmation"
    :form="form"
    :errors="errors"
    :working="working"
    @update-field="updateField"
    @save="save"
  />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import ProfilePanel from '../../components/ui/data-display/ProfilePanel.vue';

const auth = useAuthStore();
const toast = useToastStore();
const working = ref(false);
const errors = ref({});
const form = reactive({ first_name: '', last_name: '', username: '', email: '', password: '', password_confirmation: '' });

const profileEndpoint = computed(() => {
  switch (auth.role) {
    case 'Property Custodian':
      return '/custodian/profile';
    case 'End User':
      return '/end-user/profile';
    case 'School Head':
      return '/school-head/profile';
    case 'Administrator':
      return '/admin/profile';
    default:
      return null;
  }
});

const needsUsername = computed(() => ['Administrator', 'Property Custodian', 'End User'].includes(auth.role));
const needsNameFields = computed(() => ['Administrator', 'Property Custodian', 'School Head'].includes(auth.role));
const needsPasswordConfirmation = true;

function syncFromUser() {
  form.first_name = auth.user?.first_name ?? '';
  form.last_name = auth.user?.last_name ?? '';
  form.username = auth.user?.username ?? '';
  form.email = auth.user?.email ?? '';
  form.password = '';
  form.password_confirmation = '';
}

function updateField(field, value) {
  if (Object.prototype.hasOwnProperty.call(form, field)) {
    form[field] = value;
  }
}

watch(() => auth.user, syncFromUser, { immediate: true });

async function save() {
  if (!profileEndpoint.value) return;
  working.value = true;
  errors.value = {};
  try {
    const payload = { email: form.email };
    if (needsUsername.value) payload.username = form.username;
    if (needsNameFields.value) {
      payload.first_name = form.first_name;
      payload.last_name = form.last_name || '';
    }
    if (form.password) {
      payload.password = form.password;
      if (needsPasswordConfirmation) payload.password_confirmation = form.password_confirmation;
    }
    await api.patch(profileEndpoint.value, payload, { skipToast: true });
    await auth.refresh();
    syncFromUser();
    toast.success('Profile updated', 'Your profile information has been saved.');
  } catch (requestError) {
    errors.value = requestError?.response?.data?.errors ?? {};
    // This request opts out of the interceptor's toasts, so the view owns every
    // outcome. Field errors render inline against the inputs; anything else
    // (including a 422 with no field detail, and server failures) needs a toast.
    if (!Object.keys(errors.value).length) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not update the profile.');
    }
  } finally {
    working.value = false;
  }
}
</script>
