<template>
  <main class="min-h-screen bg-[#f6f7f2] text-slate-900">
    <div class="mx-auto flex min-h-screen w-full max-w-lg items-center justify-center px-4 py-6 sm:px-8 lg:py-10">
      <section class="w-full rounded-md bg-white px-6 py-10 shadow-[0_24px_80px_rgba(15,23,42,0.12)] sm:px-12">
        <div class="mb-8 flex items-center gap-4">
          <img :src="logoUrl" alt="DNHS Logo" class="h-16 w-auto object-contain" />
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#173f3b]">Dian-ay NHS</p>
            <h1 class="text-xl font-semibold text-slate-800">Inventory Management System</h1>
          </div>
        </div>

        <h2 class="text-2xl font-semibold text-[#173f3b]">Account access</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
          Public registration is not available. Accounts are managed by the system administrator.
          Please contact your administrator to request access.
        </p>

        <RouterLink
          to="/signin"
          class="mt-8 inline-flex h-11 w-full items-center justify-center rounded-full bg-[#173f3b] px-5 text-xs font-semibold uppercase tracking-wider text-white transition-colors hover:bg-[#0f302d] focus:outline-none focus:ring-4 focus:ring-[#173f3b]/20"
        >
          Back to sign in
        </RouterLink>
      </section>
    </div>
  </main>
</template>

<script setup>
const logoUrl = '/images/logo/dnhs_school_logo.svg';
</script>
