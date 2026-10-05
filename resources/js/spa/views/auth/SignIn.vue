<template>
  <main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 sm:px-8">
    <div class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-4xl items-center justify-center">
      <section class="w-full max-w-lg overflow-hidden rounded-md border border-slate-200 bg-white p-4 shadow-[0_24px_80px_rgba(15,23,42,0.10)] sm:p-10 lg:p-8">
        <div class="mx-auto flex w-full max-w-sm flex-col justify-center">
          <div class="mb-6 flex items-center justify-center gap-4 text-left">
            <img :src="logoUrl" alt="DNHS Logo" class="h-20 w-auto object-contain" />
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
              <h1 class="text-xl font-semibold text-slate-800">Inventory Management System</h1>
            </div>
          </div>

          <p class="mb-6 text-center text-sm leading-6 text-slate-500">Welcome back. Sign in to access your inventory dashboard.</p>

          <div
            v-if="formError"
            role="alert"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
          >
            {{ formError }}
          </div>

          <form class="space-y-4" novalidate @submit.prevent="submit">
            <div>
              <label for="signin-username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
              <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                  <LucideIcon :icon="UserRound" class="h-4 w-4" />
                </span>
                <input
                  id="signin-username"
                  v-model="username"
                  type="text"
                  placeholder="Enter your username"
                  autocomplete="username"
                  required
                  autofocus
                  :disabled="busy"
                  class="h-11 w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pl-11 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
              </div>
              <FieldError :errors="errors" field="username" />
            </div>

            <div>
              <div class="mb-2 flex items-center justify-between">
                <label for="signin-password" class="block text-sm font-semibold text-slate-700">Password</label>
                <RouterLink to="/forgot-password" class="text-xs font-semibold text-[#b17b28] transition hover:text-[#173f3b]">Forgot password?</RouterLink>
              </div>
              <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                  <LucideIcon :icon="LockKeyhole" class="h-4 w-4" />
                </span>
                <input
                  id="signin-password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  required
                  :disabled="busy"
                  class="h-11 w-full rounded-xl border border-slate-300 bg-slate-50 py-2.5 pl-11 pr-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 transition hover:text-[#173f3b]"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  @click="showPassword = !showPassword"
                >
                  <span class="text-xs font-semibold">{{ showPassword ? 'Hide' : 'Show' }}</span>
                </button>
              </div>
              <FieldError :errors="errors" field="password" />
            </div>

            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-500">
              <input
                v-model="remember"
                type="checkbox"
                :disabled="busy"
                class="h-4 w-4 rounded border-slate-300 text-[#173f3b] focus:ring-[#173f3b]"
              />
              <span class="inline-flex items-center gap-1.5">
                <LucideIcon :icon="ShieldCheck" class="h-4 w-4 text-slate-400" />
                Keep me logged in
              </span>
            </label>

            <button
              type="submit"
              :disabled="busy"
              class="h-11 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white transition hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ busy ? 'Signing in…' : 'Sign in' }}
            </button>
          </form>
        </div>

        <p class="mt-6 text-center text-xs leading-5 text-slate-400">Need access help? Contact your system administrator.</p>
      </section>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { LockKeyhole, ShieldCheck, UserRound } from 'lucide';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const logoUrl = '/images/logo/dnhs_school_logo.svg';
const username = ref('');
const password = ref('');
const remember = ref(false);
const showPassword = ref(false);
const busy = ref(false);
const errors = ref({});
const formError = ref('');

onMounted(() => {
  if (route.query.signed_out !== undefined) {
    toast.success('Signed out', 'Signed out successfully.');
    router.replace({ path: route.path });
  }
});

async function submit() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  formError.value = '';

  try {
    // skipToast: sign-in either redirects into onboarding or leaves the page,
    // so the interceptor's "Signed in successfully" toast is never useful here.
    // Failures still surface as inline form errors from the catch block.
    const { data } = await api.post('/signin', {
      username: username.value,
      password: password.value,
      remember: remember.value,
    }, { skipToast: true });

    // Login regenerates the session, so refresh the CSRF token that
    // axios captured from the <meta> tag before signing in. Without
    // this, subsequent POSTs (AI chat, inventory actions) fail with
    // a 419 CSRF token mismatch.
    if (data.csrf_token) {
      document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.csrf_token);
      api.defaults.headers.common['X-CSRF-TOKEN'] = data.csrf_token;
    }

    auth.setPayload(data);

    if (auth.onboardingRequired) {
      router.push({ name: 'onboarding' });
      return;
    }

    router.push(auth.homePath);
  } catch (error) {
    const responseErrors = error?.response?.data?.errors;
    if (responseErrors) {
      errors.value = responseErrors;
      const first = Object.values(responseErrors).flat().find(Boolean);
      formError.value = typeof first === 'string' ? first : (error?.response?.data?.message ?? 'Sign in failed.');
    } else {
      formError.value = error?.response?.data?.message ?? 'Could not sign in. Please try again.';
    }
  } finally {
    busy.value = false;
  }
}
</script>
