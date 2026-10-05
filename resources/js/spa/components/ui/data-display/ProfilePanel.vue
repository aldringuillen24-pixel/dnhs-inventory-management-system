<template>
  <div class="compact-page max-w-6xl">
    <header>
      <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Account settings</p>
      <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">My Profile</h1>
      <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">View your account information and manage your sign-in details.</p>
    </header>

    <div class="grid gap-4 lg:grid-cols-[minmax(16rem,0.8fr)_minmax(0,1.4fr)]">
      <section class="h-fit overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="h-16 bg-gradient-to-r from-emerald-700 to-teal-600" />
        <div class="-mt-8 px-4 pb-4">
          <div class="flex h-16 w-16 items-center justify-center rounded-md border-4 border-white bg-emerald-50 text-xl font-bold text-emerald-800 shadow-sm dark:border-gray-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ initials }}
          </div>
          <h2 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ displayName || 'User' }}</h2>
          <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ user?.email || 'No email recorded' }}</p>
          <span class="compact-chip mt-2.5 compact-chip--emerald dark:bg-emerald-950/50 dark:text-emerald-300">
            {{ role || 'User' }}
          </span>

          <dl class="mt-4 divide-y divide-gray-100 border-t border-gray-100 text-xs dark:divide-white/5 dark:border-white/5">
            <div class="flex items-center justify-between gap-3 py-2">
              <dt class="text-gray-500 dark:text-gray-400">Username</dt>
              <dd class="max-w-[60%] break-all text-right font-medium text-gray-800 dark:text-gray-200">{{ user?.username || '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
              <dt class="text-gray-500 dark:text-gray-400">First name</dt>
              <dd class="text-right font-medium text-gray-800 dark:text-gray-200">{{ user?.first_name || '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3 py-2">
              <dt class="text-gray-500 dark:text-gray-400">Last name</dt>
              <dd class="text-right font-medium text-gray-800 dark:text-gray-200">{{ user?.last_name || '—' }}</dd>
            </div>
          </dl>
        </div>
      </section>

      <section class="compact-panel">
        <div class="mb-4 border-b border-gray-100 pb-2.5 dark:border-white/5">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Profile information</h2>
          <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Keep your personal and account details up to date.</p>
        </div>

        <div v-if="!editable" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-200">
          Profile editing is managed by your system administrator.
        </div>

        <form v-else class="space-y-3" @submit.prevent="$emit('save')">
          <div class="space-y-2">
            <div v-if="needsNameFields" class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
              <label class="compact-label sm:mb-0" for="profile-first">First name</label>
              <input id="profile-first" :value="form.first_name" required maxlength="255" autocomplete="given-name" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('first_name', $event.target.value)" />
              <FieldError :errors="errors" field="first_name" />
            </div>
            <div v-if="needsNameFields" class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
              <label class="compact-label sm:mb-0" for="profile-last">Last name</label>
              <input id="profile-last" :value="form.last_name" maxlength="255" autocomplete="family-name" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('last_name', $event.target.value)" />
              <FieldError :errors="errors" field="last_name" />
            </div>
            <div v-if="needsUsername" class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
              <label class="compact-label sm:mb-0" for="profile-username">Username</label>
              <input id="profile-username" :value="form.username" required maxlength="255" autocomplete="username" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('username', $event.target.value)" />
              <FieldError :errors="errors" field="username" />
            </div>
            <div class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
              <label class="compact-label sm:mb-0" for="profile-email">Email address</label>
              <input id="profile-email" :value="form.email" type="email" required maxlength="255" autocomplete="email" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('email', $event.target.value)" />
              <FieldError :errors="errors" field="email" />
            </div>
          </div>

          <div class="border-t border-gray-100 pt-4 dark:border-white/5">
            <div class="mb-3">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Change password</h3>
              <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Leave these fields blank to keep your current password.</p>
            </div>
            <div class="space-y-2">
              <div class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
                <label class="compact-label sm:mb-0" for="profile-password">New password</label>
                <input id="profile-password" :value="form.password" type="password" minlength="8" autocomplete="new-password" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('password', $event.target.value)" />
                <FieldError :errors="errors" field="password" />
              </div>
              <div v-if="needsPasswordConfirmation" class="grid gap-1.5 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-center sm:gap-2">
                <label class="compact-label sm:mb-0" for="profile-password-confirm">Confirm new password</label>
                <input id="profile-password-confirm" :value="form.password_confirmation" type="password" autocomplete="new-password" class="compact-field focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20" @input="updateField('password_confirmation', $event.target.value)" />
              </div>
            </div>
          </div>

          <div class="flex justify-end border-t border-gray-100 pt-4 dark:border-white/5">
            <button
              type="submit"
              class="compact-btn compact-btn--primary disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="working"
            >
              {{ working ? 'Saving changes…' : 'Save changes' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import FieldError from '../feedback/FieldError.vue';

const props = defineProps({
  displayName: { type: String, default: '' },
  role: { type: String, default: '' },
  user: { type: Object, default: () => ({}) },
  editable: { type: Boolean, default: false },
  needsUsername: { type: Boolean, default: false },
  needsNameFields: { type: Boolean, default: false },
  needsPasswordConfirmation: { type: Boolean, default: true },
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  working: { type: Boolean, default: false },
});

const emit = defineEmits(['save', 'update-field']);

const initials = computed(() => {
  const name = props.displayName.trim();
  if (!name) return 'U';
  return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
});

function updateField(field, value) {
  emit('update-field', field, value);
}
</script>
