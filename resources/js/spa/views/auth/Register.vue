<template>
  <main class="bg-[#f6f7f2] text-slate-900">
    <div class="mx-auto flex w-full max-w-2xl items-start justify-center pt-6 pb-8 px-4 sm:px-8 lg:pt-8 lg:pb-10">
      <section class="w-full rounded-md bg-white px-6 py-8 shadow-[0_24px_80px_rgba(15,23,42,0.12)] sm:px-12 lg:px-16 lg:py-10">
        <div class="mb-6 flex items-center gap-4">
          <img :src="logoUrl" alt="DNHS Logo" class="h-16 w-auto object-contain" />
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
            <h1 class="text-xl font-semibold text-slate-800">Inventory Management System</h1>
          </div>
        </div>

        <h2 class="text-2xl font-semibold text-[#173f3b]">Create an account</h2>
        <p class="mt-1 text-sm leading-5 text-slate-600">
          Register as an End User. Accounts are reviewed by the system administrator.
        </p>

        <form class="mt-6" novalidate @submit.prevent="submit">
          <div class="grid grid-cols-1 gap-y-4 gap-x-4 md:grid-cols-2">
            <div>
              <label for="register-first-name" class="mb-1.5 block text-xs text-slate-500">First Name</label>
              <input
                id="register-first-name"
                v-model="firstName"
                type="text"
                placeholder="Enter your first name"
                autocomplete="given-name"
                required
                :disabled="busy"
                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none disabled:opacity-60 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10"
              />
              <FieldError :errors="errors" field="first_name" />
            </div>

            <div>
              <label for="register-last-name" class="mb-1.5 block text-xs text-slate-500">Last Name</label>
              <input
                id="register-last-name"
                v-model="lastName"
                type="text"
                placeholder="Enter your last name"
                autocomplete="family-name"
                required
                :disabled="busy"
                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none disabled:opacity-60 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10"
              />
              <FieldError :errors="errors" field="last_name" />
            </div>

            <div>
              <label for="register-username" class="mb-1.5 block text-xs text-slate-500">Username</label>
              <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
                  <LucideIcon :icon="UserRound" class="h-4 w-4" />
                </span>
                <input
                  id="register-username"
                  v-model="username"
                  type="text"
                  placeholder="Choose a username"
                  autocomplete="username"
                  required
                  :disabled="busy"
                  class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                />
              </div>
              <FieldError :errors="errors" field="username" />
            </div>

            <div>
              <label for="register-email" class="mb-1.5 block text-xs text-slate-500">Email</label>
              <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
                  <LucideIcon :icon="Mail" class="h-4 w-4" />
                </span>
                <input
                  id="register-email"
                  v-model="email"
                  type="email"
                  placeholder="Enter your email"
                  autocomplete="email"
                  required
                  :disabled="busy"
                  class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                />
              </div>
              <FieldError :errors="errors" field="email" />
            </div>

            <div>
              <label for="register-password" class="mb-1.5 block text-xs text-slate-500">Password</label>
              <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
                  <LucideIcon :icon="LockKeyhole" class="h-4 w-4" />
                </span>
                <input
                  id="register-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Enter a password"
                  autocomplete="new-password"
                  required
                  :disabled="busy"
                  class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                />
                <button
                  type="button"
                  class="flex items-center px-3.5 text-slate-400 transition hover:text-[#173f3b]"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  :title="showPassword ? 'Hide password' : 'Show password'"
                  @click="showPassword = !showPassword"
                  :disabled="busy"
                >
                  <LucideIcon :icon="showPassword ? EyeOff : Eye" class="h-4 w-4" />
                </button>
              </div>
              <FieldError :errors="errors" field="password" />
              <p class="mt-1 text-[10px] text-slate-500">At least 8 characters.</p>
            </div>

            <div>
              <label for="register-confirm-password" class="mb-1.5 block text-xs text-slate-500">Confirm Password</label>
              <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
                  <LucideIcon :icon="LockKeyhole" class="h-4 w-4" />
                </span>
                <input
                  id="register-confirm-password"
                  v-model="confirmPassword"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Confirm your password"
                  autocomplete="new-password"
                  required
                  :disabled="busy"
                  class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                />
              </div>
              <FieldError :errors="errors" field="password_confirmation" />
            </div>
          </div>

          <button
            type="submit"
            :disabled="busy"
            class="mt-6 h-11 w-full rounded-full bg-[#173f3b] px-5 text-xs font-semibold uppercase tracking-wider text-white shadow-[0_10px_30px_rgba(23,63,59,0.35)] transition-all hover:-translate-y-0.5 hover:bg-[#0f302d] hover:shadow-[0_14px_36px_rgba(23,63,59,0.4)] focus:ring-4 focus:ring-[#173f3b]/20 active:translate-y-0 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
          >
            {{ busy ? 'Creating account…' : 'Create account' }}
          </button>
        </form>

        <RouterLink
          to="/signin"
          class="mt-6 block text-center text-xs font-medium text-slate-500 transition hover:text-[#173f3b]"
        >
          Already have an account? Back to sign in
        </RouterLink>

        <p class="mt-6 text-center text-[11px] leading-5 text-slate-400">
          Republic of the Philippines · Department of Education<br />
          Dian-ay National High School · Need access help? Contact your system administrator.
        </p>
      </section>
    </div>
  </main>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, EyeOff, LockKeyhole, Mail, UserRound } from 'lucide';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';

const router = useRouter();
const auth = useAuthStore();

const logoUrl = '/images/logo/dnhs_school_logo.svg';
const firstName = ref('');
const lastName = ref('');
const username = ref('');
const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const showPassword = ref(false);
const busy = ref(false);
const errors = ref({});

async function submit() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};

  try {
    const { data } = await api.post('/register', {
      first_name: firstName.value,
      last_name: lastName.value,
      username: username.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmPassword.value,
    });

    // No session is created for a pending account, so there is no CSRF
    // token to refresh and no payload to set. The axios interceptor shows
    // the success toast ("…pending admin approval…") automatically.
    router.push('/signin');
  } catch (error) {
    if (error?.response?.data?.errors) {
      errors.value = error.response.data.errors;
    } else if (error?.response?.data?.message) {
      errors.value = { registration: error.response.data.message };
    }
  } finally {
    busy.value = false;
  }
}
</script>
