<template>
  <main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 sm:px-8">
    <div class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-4xl items-center justify-center">
      <section class="w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-4 shadow-[0_24px_80px_rgba(15,23,42,0.10)] sm:p-12 lg:p-10">
        <div class="mx-auto flex w-full max-w-md flex-col justify-center">
          <div class="mb-8 flex items-center justify-center gap-5 text-left">
            <img :src="logoUrl" alt="DNHS Logo" class="h-24 w-auto object-contain" />
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
              <h1 class="text-xl font-semibold text-slate-800">Reset Password</h1>
            </div>
          </div>

          <div
            v-if="formError"
            role="alert"
            class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
          >
            {{ formError }}
          </div>

          <!-- Step 1: email -->
          <form v-if="step === 'email'" class="space-y-5" novalidate @submit.prevent="sendOtp">
            <p class="text-center text-sm leading-6 text-slate-500">Enter your account email and we will send a 6-digit verification code.</p>
            <div>
              <label for="reset-email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
              <input
                id="reset-email"
                v-model="email"
                type="email"
                placeholder="Enter email address"
                required
                autocomplete="email"
                :disabled="busy"
                class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
              <FieldError :errors="errors" field="email" />
            </div>
            <button
              type="submit"
              :disabled="busy"
              class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white transition hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ busy ? 'Sending…' : 'Send Code' }}
            </button>
          </form>

          <!-- Step 2: OTP -->
          <form v-if="step === 'otp'" class="space-y-5" novalidate @submit.prevent="verifyOtp">
            <p class="text-center text-sm leading-6 text-slate-500">
              A code was sent to <span class="font-semibold text-slate-700">{{ email }}</span>.
              <span v-if="remaining !== null" class="mt-1 block text-xs" :class="remaining > 0 ? 'text-slate-400' : 'text-red-600'">
                {{ remaining > 0 ? `Code expires in ${formattedRemaining}` : 'Code expired — request a new one.' }}
              </span>
            </p>
            <div>
              <label for="reset-otp" class="mb-2 block text-sm font-semibold text-slate-700">6-digit code</label>
              <input
                id="reset-otp"
                v-model="otp"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Enter 6-digit code"
                required
                autocomplete="one-time-code"
                :disabled="busy"
                class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-center text-lg font-semibold tracking-[0.4em] text-slate-900 outline-none transition placeholder:text-slate-400 placeholder:tracking-normal placeholder:text-sm focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
              <FieldError :errors="errors" field="otp" />
            </div>
            <button
              type="submit"
              :disabled="busy"
              class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white transition hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ busy ? 'Verifying…' : 'Verify Code' }}
            </button>
            <button
              type="button"
              :disabled="busy"
              class="w-full text-center text-xs font-semibold text-[#b17b28] transition hover:text-[#173f3b] disabled:opacity-50"
              @click="resend"
            >
              Resend code
            </button>
          </form>

          <!-- Step 3: new password -->
          <form v-if="step === 'password'" class="space-y-5" novalidate @submit.prevent="resetPassword">
            <p class="text-center text-sm leading-6 text-slate-500">Choose a new password for <span class="font-semibold text-slate-700">{{ email }}</span>.</p>
            <div>
              <label for="reset-password" class="mb-2 block text-sm font-semibold text-slate-700">New password</label>
              <input
                id="reset-password"
                v-model="password"
                type="password"
                placeholder="Min. 8 characters"
                required
                autocomplete="new-password"
                :disabled="busy"
                class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
              <FieldError :errors="errors" field="password" />
            </div>
            <div>
              <label for="reset-password-confirm" class="mb-2 block text-sm font-semibold text-slate-700">Confirm password</label>
              <input
                id="reset-password-confirm"
                v-model="passwordConfirmation"
                type="password"
                placeholder="Repeat password"
                required
                autocomplete="new-password"
                :disabled="busy"
                class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 disabled:opacity-60"
              />
            </div>
            <button
              type="submit"
              :disabled="busy"
              class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white transition hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ busy ? 'Saving…' : 'Reset Password' }}
            </button>
          </form>

          <!-- Step 4: done -->
          <div v-if="step === 'done'" class="space-y-5 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-green-700">
              <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
              </svg>
            </div>
            <p class="text-sm leading-6 text-slate-500">Your password has been reset successfully. You can now log in with your new password.</p>
            <RouterLink
              to="/signin"
              class="block h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold leading-[3rem] text-white transition hover:bg-[#0f302d]"
            >
              Back to Sign In
            </RouterLink>
          </div>

          <p v-if="step !== 'done'" class="mt-8 text-center text-xs leading-5 text-slate-400">
            <RouterLink to="/signin" class="font-semibold text-[#b17b28] hover:text-[#173f3b]">Back to sign in</RouterLink>
          </p>
        </div>
      </section>
    </div>
  </main>
</template>

<script setup>
import { computed, onUnmounted, ref } from 'vue';
import api from '../../lib/axios';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const logoUrl = '/images/logo/dnhs_school_logo.svg';

const step = ref('email');
const email = ref('');
const otp = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const busy = ref(false);
const errors = ref({});
const formError = ref('');
const expiresAt = ref(null);
const remaining = ref(null);
let countdownTimer = null;

const formattedRemaining = computed(() => {
  const total = Math.max(0, remaining.value ?? 0);
  const minutes = Math.floor(total / 60);
  const seconds = total % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

function stopCountdown() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
  remaining.value = null;
}

function startCountdown(isoDate) {
  stopCountdown();
  if (!isoDate) return;
  const target = new Date(isoDate).getTime();
  if (Number.isNaN(target)) return;
  const tick = () => {
    remaining.value = Math.max(0, Math.round((target - Date.now()) / 1000));
  };
  tick();
  countdownTimer = setInterval(tick, 1000);
}

onUnmounted(stopCountdown);

function failure(error, fallback) {
  const responseErrors = error?.response?.data?.errors;
  if (responseErrors) {
    errors.value = responseErrors;
    const first = Object.values(responseErrors).flat().find(Boolean);
    formError.value = typeof first === 'string' ? first : (error?.response?.data?.message ?? fallback);
  } else {
    formError.value = error?.response?.data?.message ?? fallback;
  }
}

async function sendOtp() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  formError.value = '';
  try {
    const { data } = await api.post(
      '/password/email',
      { email: email.value },
      { skipToast: true },
    );
    email.value = data.email ?? email.value;
    startCountdown(data.expires_at);
    otp.value = '';
    step.value = 'otp';
  } catch (error) {
    failure(error, 'Could not send the code. Please try again.');
  } finally {
    busy.value = false;
  }
}

async function resend() {
  await sendOtp();
}

async function verifyOtp() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  formError.value = '';
  try {
    await api.post('/password/verify', { email: email.value, otp: otp.value });
    stopCountdown();
    password.value = '';
    passwordConfirmation.value = '';
    step.value = 'password';
  } catch (error) {
    failure(error, 'Could not verify the code. Please try again.');
  } finally {
    busy.value = false;
  }
}

async function resetPassword() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  formError.value = '';
  try {
    await api.post('/password/reset', {
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });
    step.value = 'done';
  } catch (error) {
    const responseErrors = error?.response?.data?.errors;
    if (responseErrors?.email && !responseErrors?.password) {
      step.value = 'email';
    }
    failure(error, 'Could not reset the password. Please try again.');
  } finally {
    busy.value = false;
  }
}
</script>
