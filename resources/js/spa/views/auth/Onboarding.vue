<template>
  <main class="min-h-screen bg-[#f6f7f2] px-4 py-6 text-slate-900 sm:px-6">
    <div class="mx-auto flex min-h-[calc(100vh-2.5rem)] w-full max-w-4xl items-center justify-center">
      <section class="relative w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.10)] sm:p-8">
        <!-- Signs the user out so the router guard lets them return to sign-in.
             Without this the onboarding guard bounces every route back here. -->


        <div class="mx-auto flex w-full max-w-md flex-col justify-center">
          <div class="mb-7">
              <button
                type="button"
                :disabled="busy || signingOut"
                class="absolute left-4 top-4 inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 disabled:opacity-60 sm:left-6 sm:top-6"
                @click="backToSignIn"
              >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ signingOut ? 'Returning…' : 'Back to sign in' }}
              </button>

            <div class="mt-9 flex items-center justify-center gap-4 text-left">
              <div>
                <h1 class="mt-0.5 text-xl font-semibold text-slate-800">Complete Your Account Setup</h1>
              </div>
            </div>
          </div>

          <p class="mb-7 text-center text-sm leading-relaxed text-slate-500">Update your details and set a new password to activate your account.</p>

          <div
            v-if="formError"
            role="alert"
            class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800"
          >
            {{ formError }}
          </div>

          <form class="space-y-5" novalidate @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="onboarding-first-name" class="mb-2 block text-xs font-semibold text-slate-700">First name</label>
                <input
                  id="onboarding-first-name"
                  v-model="firstName"
                  type="text"
                  placeholder="Enter first name"
                  required
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <FieldError :errors="errors" field="first_name" />
              </div>
              <div>
                <label for="onboarding-last-name" class="mb-2 block text-xs font-semibold text-slate-700">Last name</label>
                <input
                  id="onboarding-last-name"
                  v-model="lastName"
                  type="text"
                  placeholder="Enter last name"
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <FieldError :errors="errors" field="last_name" />
              </div>
            </div>

            <div v-if="isInspector">
              <label for="onboarding-username" class="mb-2 block text-xs font-semibold text-slate-700">Username</label>
              <input
                id="onboarding-username"
                v-model="username"
                type="text"
                placeholder="Your sign-in username"
                required
                autocomplete="username"
                :disabled="busy"
                class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
              <p class="mt-1 text-[11px] text-slate-500">This is the username you use to sign in.</p>
              <FieldError :errors="errors" field="username" />
            </div>

            <div>
              <label for="onboarding-email" class="mb-2 block text-xs font-semibold text-slate-700">Email</label>
              <input
                id="onboarding-email"
                v-model="email"
                type="email"
                placeholder="Enter email address"
                required
                autocomplete="email"
                :disabled="busy"
                class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
              <FieldError :errors="errors" field="email" />
            </div>

            <div v-if="isEndUser" class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="onboarding-building" class="mb-2 block text-xs font-semibold text-slate-700">Building</label>
                <input
                  id="onboarding-building"
                  v-model="building"
                  type="text"
                  placeholder="Building name"
                  required
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <FieldError :errors="errors" field="building" />
              </div>
              <div>
                <label for="onboarding-room" class="mb-2 block text-xs font-semibold text-slate-700">Room</label>
                <input
                  id="onboarding-room"
                  v-model="room"
                  type="text"
                  placeholder="Room or storage area"
                  required
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <FieldError :errors="errors" field="room" />
              </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="onboarding-password" class="mb-2 block text-xs font-semibold text-slate-700">New password</label>
                <input
                  id="onboarding-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Min. 8 characters"
                  required
                  autocomplete="new-password"
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <FieldError :errors="errors" field="password" />
              </div>
              <div>
                <label for="onboarding-password-confirm" class="mb-2 block text-xs font-semibold text-slate-700">Confirm password</label>
                <input
                  id="onboarding-password-confirm"
                  v-model="passwordConfirmation"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Repeat password"
                  required
                  autocomplete="new-password"
                  :disabled="busy"
                  class="h-11 w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-2 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
              </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-500">
              <input
                v-model="showPassword"
                type="checkbox"
                :disabled="busy"
                class="h-4 w-4 rounded border-slate-300 text-[#173f3b] focus:ring-[#173f3b]"
              />
              Show passwords
            </label>

            <button
              type="submit"
              :disabled="busy"
              class="h-11 w-full rounded-md bg-[#173f3b] px-4 text-sm font-semibold text-white transition hover:bg-[#0f302d] focus:ring-2 focus:ring-[#173f3b]/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ busy ? 'Saving…' : 'Complete Setup' }}
            </button>
          </form>
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const router = useRouter();
const auth = useAuthStore();

const logoUrl = '/images/logo/dnhs_school_logo.svg';
const firstName = ref(auth.user?.first_name ?? '');
const lastName = ref(auth.user?.last_name ?? '');
const email = ref(auth.user?.email ?? '');
const username = ref(auth.user?.username ?? '');
const building = ref(auth.user?.building ?? '');
const room = ref(auth.user?.room ?? '');
const password = ref('');
const passwordConfirmation = ref('');
const showPassword = ref(false);
const busy = ref(false);
const signingOut = ref(false);
const errors = ref({});
const formError = ref('');

const isEndUser = computed(() => auth.role === 'End User');

// Inspector is the only role without a writable profile route, so onboarding is
// the one place their sign-in username can be reviewed or corrected.
const isInspector = computed(() => auth.role === 'Inspector');

async function backToSignIn() {
  if (busy.value || signingOut.value) return;
  signingOut.value = true;

  try {
    await api.post('/logout').catch(() => {});
  } finally {
    auth.clear();
    window.location.href = '/spa/signin';
  }
}

async function submit() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  formError.value = '';

  try {
    const payload = {
      first_name: firstName.value,
      last_name: lastName.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    };

    if (isEndUser.value) {
      payload.building = building.value;
      payload.room = room.value;
    }

    if (isInspector.value) {
      payload.username = username.value;
    }

    const { data } = await api.post('/onboarding', payload);

    auth.setPayload(data);
    router.push(auth.homePath);
  } catch (error) {
    const responseErrors = error?.response?.data?.errors;
    if (responseErrors) {
      errors.value = responseErrors;
      if (responseErrors.account) {
        router.push(auth.homePath);
        return;
      }
      const first = Object.values(responseErrors).flat().find(Boolean);
      formError.value = typeof first === 'string' ? first : (error?.response?.data?.message ?? 'Setup failed.');
    } else {
      formError.value = error?.response?.data?.message ?? 'Could not complete setup. Please try again.';
    }
  } finally {
    busy.value = false;
  }
}
</script>
