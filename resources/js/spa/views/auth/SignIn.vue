<template>
  <main class="min-h-screen bg-[#f6f7f2] text-slate-900">
    <div class="mx-auto flex min-h-screen w-full max-w-lg items-center justify-center px-4 py-6 sm:px-8 lg:py-10">
      <div class="signin-enter w-full rounded-md bg-white shadow-[0_24px_80px_rgba(15,23,42,0.12)]">
        <!-- Sign-in form -->
        <section class="flex min-w-0 flex-1 items-center justify-center px-6 py-10 sm:px-12">
          <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-4">
              <img :src="logoUrl" alt="DNHS Logo" class="h-16 w-auto object-contain" />
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
                <h1 class="text-xl font-semibold text-slate-800">Inventory Management System</h1>
              </div>
            </div>

            <h1 class="text-2xl font-semibold text-[#173f3b]">Log In</h1>
            <p class="mb-8 mt-1 text-sm text-slate-500">Welcome back. Sign in to access your inventory dashboard.</p>

            <form class="space-y-5" novalidate @submit.prevent="submit">
              <div>
                <label for="signin-username" class="mb-1.5 block text-xs text-slate-500">Username</label>
                <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                  <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
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
                    class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                  />
                </div>
                <FieldError :errors="errors" field="username" />
              </div>

              <div>
                <label for="signin-password" class="mb-1.5 block text-xs text-slate-500">Password</label>
                <div class="flex overflow-hidden rounded-lg border border-slate-300 bg-white transition focus-within:border-[#173f3b] focus-within:ring-4 focus-within:ring-[#173f3b]/10">
                  <span class="flex items-center border-r border-slate-300 px-3.5 text-slate-400">
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
                    class="h-11 min-w-0 flex-1 border-0 bg-transparent px-3.5 text-sm text-slate-900 outline-none placeholder:text-slate-400 disabled:opacity-60"
                    @keyup="checkCapsLock"
                    @blur="capsLockOn = false"
                  />
                  <button
                    type="button"
                    class="flex items-center px-3.5 text-slate-400 transition hover:text-[#173f3b]"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    :title="showPassword ? 'Hide password' : 'Show password'"
                    @click="showPassword = !showPassword"
                  >
                    <LucideIcon :icon="showPassword ? EyeOff : Eye" class="h-4 w-4" />
                  </button>
                </div>
                <FieldError :errors="errors" field="password" />
                <p v-if="capsLockOn" class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-amber-600">
                  <LucideIcon :icon="TriangleAlert" class="h-3.5 w-3.5" />
                  Caps Lock is on
                </p>
              </div>

              <div class="flex items-center justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-500">
                  <input
                    v-model="remember"
                    type="checkbox"
                    :disabled="busy"
                    class="h-4 w-4 rounded border-slate-300 text-[#173f3b] focus:ring-[#173f3b]"
                  />
                  <span class="inline-flex items-center gap-1.5">
                    <LucideIcon :icon="ShieldCheck" class="h-3.5 w-3.5 text-slate-400" />
                    Keep me logged in
                  </span>
                </label>
                <RouterLink to="/forgot-password" class="text-xs font-medium text-slate-500 transition hover:text-[#173f3b]">Forgot password?</RouterLink>
              </div>

              <button
                type="submit"
                :disabled="busy"
                class="h-11 w-full rounded-full bg-[#173f3b] px-5 text-xs font-semibold uppercase tracking-wider text-white shadow-[0_10px_30px_rgba(23,63,59,0.35)] transition-all hover:-translate-y-0.5 hover:bg-[#0f302d] hover:shadow-[0_14px_36px_rgba(23,63,59,0.4)] focus:ring-4 focus:ring-[#173f3b]/20 active:translate-y-0 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
              >
                {{ busy ? 'Signing in…' : 'Sign in' }}
              </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
              Don't have an account?
              <RouterLink to="/register" class="font-medium text-[#173f3b] hover:underline">Register</RouterLink>
            </p>

            <p class="mt-8 text-center text-[11px] leading-5 text-slate-400">
              Republic of the Philippines · Department of Education<br />
              Dian-ay National High School · Need access help? Contact your system administrator.
            </p>
          </div>
        </section>
      </div>
    </div>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Eye, EyeOff, LockKeyhole, ShieldCheck, TriangleAlert, UserRound } from 'lucide';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useNavBadgeStore } from '../../stores/navBadge';
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
const capsLockOn = ref(false);
const busy = ref(false);
const errors = ref({});

function checkCapsLock(event) {
  const state = typeof event?.getModifierState === 'function'
    ? event.getModifierState('CapsLock')
    : null;
  if (typeof state === 'boolean') {
    capsLockOn.value = state;
  }
}

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
    useNavBadgeStore().fetch();

    if (auth.onboardingRequired) {
      router.push({ name: 'onboarding' });
      return;
    }

    router.push(auth.homePath);
  } catch (error) {
    if (error?.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
  } finally {
    busy.value = false;
  }
}
</script>

<style>
/* Entrance motion: card rises once. Purely decorative — disabled entirely
 * under prefers-reduced-motion. */
@keyframes signin-rise {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.signin-enter {
  animation: signin-rise 0.45s ease-out both;
}
@media (prefers-reduced-motion: reduce) {
  .signin-enter {
    animation: none;
  }
}
</style>
