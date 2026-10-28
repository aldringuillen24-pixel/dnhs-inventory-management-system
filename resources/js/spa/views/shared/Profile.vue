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
    :profile-working="profileWorking"
    :password-working="passwordWorking"
    @update-field="updateField"
    @save-profile="saveProfile"
    @save-password="savePassword"
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
const profileWorking = ref(false);
const passwordWorking = ref(false);
const errors = ref({});
const form = reactive({ first_name: '', last_name: '', username: '', email: '', building: '', room: '', password: '', password_confirmation: '' });

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
    case 'Inspector':
      return '/inspector/profile';
    default:
      return null;
  }
});

const needsUsername = computed(() => ['Administrator', 'Property Custodian', 'End User', 'Inspector'].includes(auth.role));
const needsNameFields = computed(() => ['Administrator', 'Property Custodian', 'School Head', 'Inspector', 'End User'].includes(auth.role));
const needsPasswordConfirmation = true;

function syncFromUser() {
  form.first_name = auth.user?.first_name ?? '';
  form.last_name = auth.user?.last_name ?? '';
  form.username = auth.user?.username ?? '';
  form.email = auth.user?.email ?? '';
  form.building = auth.user?.building ?? '';
  form.room = auth.user?.room ?? '';
  form.password = '';
  form.password_confirmation = '';
}

function updateField(field, value) {
  if (Object.prototype.hasOwnProperty.call(form, field)) {
    form[field] = value;
  }
}

watch(() => auth.user, syncFromUser, { immediate: true });

async function saveProfile() {
  if (!profileEndpoint.value) return;
  profileWorking.value = true;
  errors.value = {};
  try {
    const payload = { email: form.email, building: form.building, room: form.room };
    if (needsUsername.value) payload.username = form.username;
    if (needsNameFields.value) {
      payload.first_name = form.first_name;
      payload.last_name = form.last_name || '';
    }
    await api.patch(profileEndpoint.value, payload, { skipToast: true });
    await auth.refresh();
    syncFromUser();
    toast.success('Profile updated', 'Your profile information has been saved.');
  } catch (requestError) {
    errors.value = requestError?.response?.data?.errors ?? {};
    if (!Object.keys(errors.value).length) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not update the profile.');
    }
  } finally {
    profileWorking.value = false;
  }
}

async function savePassword() {
  if (!profileEndpoint.value) return;
  if (!form.password) return;
  passwordWorking.value = true;
  errors.value = {};
  try {
    const payload = { password: form.password };
    if (needsPasswordConfirmation) payload.password_confirmation = form.password_confirmation;
    await api.patch('/profile/password', payload, { skipToast: true });
    form.password = '';
    form.password_confirmation = '';
    toast.success('Password updated', 'Your password has been changed.');
  } catch (requestError) {
    errors.value = requestError?.response?.data?.errors ?? {};
    if (!Object.keys(errors.value).length) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not update the password.');
    }
  } finally {
    passwordWorking.value = false;
  }
}
</script>
