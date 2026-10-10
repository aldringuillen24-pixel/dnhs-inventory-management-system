<template>
  <main class="min-h-screen bg-[#f6f7f2] text-slate-900">
    <div class="mx-auto flex min-h-screen w-full max-w-6xl items-stretch justify-center px-4 py-6 sm:px-8 lg:py-10">
      <div class="signin-enter flex w-full overflow-hidden rounded-2xl bg-white shadow-[0_24px_80px_rgba(15,23,42,0.12)]">
        <!-- Brand panel: bento mosaic, no photography -->
        <aside class="relative hidden w-[440px] shrink-0 flex-col justify-between overflow-hidden bg-[#173f3b] p-8 text-white lg:flex xl:w-[500px]">
          <div class="grid grid-cols-2 gap-3">
            <div
              tabindex="0"
              class="tile-rise col-span-2 flex items-center gap-4 rounded-2xl bg-white/10 p-5 transition-all duration-300 focus-visible:outline-2 focus-visible:outline-white/60"
              :class="tileStateClass(0)"
              style="animation-delay: 0.05s"
              @mouseenter="hoveredTile = 0"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 0"
              @focusout="hoveredTile = null"
            >
              <img :src="logoUrl" alt="DNHS Logo" class="h-16 w-auto shrink-0 object-contain" :class="hoveredTile === 0 ? 'icon-pop' : ''" />
              <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">Dian-ay NHS</p>
                <p class="mt-1 text-lg font-semibold leading-snug">Inventory Management System</p>
                <p class="overflow-hidden text-xs leading-5 text-emerald-50/75 transition-all duration-300" :class="tileDescriptionClass(0)">
                  Role-based stockroom control for Dian-ay National High School.
                </p>
              </div>
            </div>

            <div
              tabindex="0"
              class="tile-rise rounded-2xl bg-emerald-500 p-5 text-white transition-all duration-300 focus-visible:outline-2 focus-visible:outline-white/60"
              :class="tileStateClass(1)"
              style="animation-delay: 0.12s"
              @mouseenter="hoveredTile = 1"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 1"
              @focusout="hoveredTile = null"
            >
              <LucideIcon :icon="ShieldCheck" class="h-5 w-5" :class="hoveredTile === 1 ? 'icon-pop' : ''" />
              <p class="mt-6 text-sm font-semibold leading-snug">Role-based access</p>
              <p class="overflow-hidden text-xs leading-5 text-emerald-50/90 transition-all duration-300" :class="tileDescriptionClass(1)">
                Admins, custodians, heads, inspectors and end users — each with a dedicated workspace.
              </p>
            </div>

            <div
              tabindex="0"
              class="tile-rise rounded-2xl bg-amber-500 p-5 text-white transition-all duration-300 focus-visible:outline-2 focus-visible:outline-white/60"
              :class="tileStateClass(2)"
              style="animation-delay: 0.19s"
              @mouseenter="hoveredTile = 2"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 2"
              @focusout="hoveredTile = null"
            >
              <LucideIcon :icon="ChartColumn" class="h-5 w-5" :class="hoveredTile === 2 ? 'icon-pop' : ''" />
              <p class="mt-6 text-sm font-semibold leading-snug">ML forecasting</p>
              <p class="overflow-hidden text-xs leading-5 text-amber-50/90 transition-all duration-300" :class="tileDescriptionClass(2)">
                Stock-out predictions with verification-first procurement guidance.
              </p>
            </div>

            <div
              tabindex="0"
              class="tile-rise col-span-2 rounded-2xl bg-white/10 p-5 transition-all duration-300 focus-visible:outline-2 focus-visible:outline-white/60"
              :class="tileStateClass(3)"
              style="animation-delay: 0.26s"
              @mouseenter="hoveredTile = 3"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 3"
              @focusout="hoveredTile = null"
            >
              <LucideIcon :icon="ClipboardList" class="h-5 w-5 text-emerald-200" :class="hoveredTile === 3 ? 'icon-pop' : ''" />
              <p class="mt-4 text-sm font-semibold leading-snug">Requests & approvals</p>
              <p class="overflow-hidden text-xs leading-5 text-emerald-50/75 transition-all duration-300" :class="tileDescriptionClass(3)">
                Item requests, transfers and returns flow through custodian approval.
              </p>
            </div>

            <div
              tabindex="0"
              class="tile-rise rounded-2xl bg-white p-5 text-[#173f3b] transition-all duration-300 focus-visible:outline-2 focus-visible:outline-[#173f3b]/40"
              :class="tileStateClass(4)"
              style="animation-delay: 0.33s"
              @mouseenter="hoveredTile = 4"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 4"
              @focusout="hoveredTile = null"
            >
              <LucideIcon :icon="History" class="h-5 w-5" :class="hoveredTile === 4 ? 'icon-pop' : ''" />
              <p class="mt-4 text-sm font-semibold leading-snug">Audit ledger</p>
              <p class="overflow-hidden text-xs leading-5 text-slate-500 transition-all duration-300" :class="tileDescriptionClass(4)">
                Every stock movement, assignment and approval recorded.
              </p>
            </div>

            <div
              tabindex="0"
              class="tile-rise rounded-2xl bg-white/10 p-5 transition-all duration-300 focus-visible:outline-2 focus-visible:outline-white/60"
              :class="tileStateClass(5)"
              style="animation-delay: 0.4s"
              @mouseenter="hoveredTile = 5"
              @mouseleave="hoveredTile = null"
              @focusin="hoveredTile = 5"
              @focusout="hoveredTile = null"
            >
              <LucideIcon :icon="PackageCheck" class="h-5 w-5 text-emerald-200" :class="hoveredTile === 5 ? 'icon-pop' : ''" />
              <p class="mt-4 text-sm font-semibold leading-snug">Stockroom control</p>
              <p class="overflow-hidden text-xs leading-5 text-emerald-50/75 transition-all duration-300" :class="tileDescriptionClass(5)">
                Stock-in, stock-out, serial tracking and lifespan monitoring.
              </p>
            </div>
          </div>

          <p class="relative mt-6 text-[11px] leading-5 text-emerald-50/60">
            Republic of the Philippines · Department of Education<br />
            Dian-ay National High School
          </p>
        </aside>

        <!-- Sign-in form -->
        <section class="flex min-w-0 flex-1 items-center justify-center px-6 py-10 sm:px-12">
          <div class="w-full max-w-sm">
            <div class="mb-8 flex items-center gap-4 lg:hidden">
              <img :src="logoUrl" alt="DNHS Logo" class="h-16 w-auto object-contain" />
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
                <h1 class="text-xl font-semibold text-slate-800">Inventory Management System</h1>
              </div>
            </div>

            <h1 class="text-2xl font-semibold text-[#173f3b]">Log In</h1>
            <p class="mb-8 mt-1 text-sm text-slate-500">Welcome back. Sign in to access your inventory dashboard.</p>

            <div
              v-if="formError"
              role="alert"
              class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            >
              {{ formError }}
            </div>

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
                class="h-12 w-full rounded-full bg-[#173f3b] px-5 text-sm font-semibold uppercase tracking-wider text-white shadow-[0_10px_30px_rgba(23,63,59,0.35)] transition-all hover:-translate-y-0.5 hover:bg-[#0f302d] hover:shadow-[0_14px_36px_rgba(23,63,59,0.4)] focus:ring-4 focus:ring-[#173f3b]/20 active:translate-y-0 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
              >
                {{ busy ? 'Signing in…' : 'Sign in' }}
              </button>
            </form>

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
import { ChartColumn, ClipboardList, Eye, EyeOff, History, LockKeyhole, PackageCheck, ShieldCheck, TriangleAlert, UserRound } from 'lucide';
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
const capsLockOn = ref(false);
// Hovered mosaic tile index (null when none). Drives the expand interaction:
// the hovered tile lifts and opens its description, siblings shift aside.
const hoveredTile = ref(null);
const busy = ref(false);
const errors = ref({});
const formError = ref('');

function checkCapsLock(event) {
  const state = typeof event?.getModifierState === 'function'
    ? event.getModifierState('CapsLock')
    : null;
  if (typeof state === 'boolean') {
    capsLockOn.value = state;
  }
}

function tileStateClass(index) {
  if (hoveredTile.value === null) {
    return '';
  }
  return hoveredTile.value === index
    ? '-translate-y-1 shadow-xl'
    : '-translate-x-1.5 opacity-70';
}

function tileDescriptionClass(index) {
  return hoveredTile.value === index
    ? 'mt-1 max-h-20 opacity-90'
    : 'max-h-0 opacity-0';
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

<style>
/* Entrance motion: card rises once, mosaic tiles follow in stagger. Purely
 * decorative — disabled entirely under prefers-reduced-motion. */
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
/* Fill is backwards (not both): the finished keyframe must release so hover
 * transforms keep working. A forwards fill would pin translateY(0) forever
 * and silently swallow every hover lift. */
.tile-rise {
  animation: signin-rise 0.5s ease-out backwards;
}
@keyframes icon-pop {
  0% { transform: scale(1) rotate(0deg); }
  40% { transform: scale(1.3) rotate(-10deg); }
  70% { transform: scale(1.12) rotate(5deg); }
  100% { transform: scale(1.15) rotate(0deg); }
}
/* Icons and logo ease between states; the pop plays once per hover-in while
 * the held scale persists until hover-out. */
.tile-rise svg,
.tile-rise img {
  transition: transform 0.3s ease;
}
.tile-rise .icon-pop {
  animation: icon-pop 0.45s ease-out;
  transform: scale(1.15);
}
@media (prefers-reduced-motion: reduce) {
  .signin-enter,
  .tile-rise {
    animation: none;
  }
  .tile-rise .icon-pop {
    animation: none;
    transform: none;
  }
}
</style>
