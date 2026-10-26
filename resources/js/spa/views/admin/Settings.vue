<template>
  <div class="space-y-4">

    <!-- Page Header -->
    <div class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
            Administrator
          </div>
          <h1 class="mt-0.5 text-lg font-bold tracking-tight text-gray-900 dark:text-white">System Settings</h1>
          <p class="mt-0.5 max-w-2xl text-xs text-gray-500 dark:text-gray-400">
            Manage policies, diagnostics, and AI configuration.
          </p>
        </div>

        <button
          type="button"
          :disabled="pinging"
          class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-900"
          @click="pingAi"
        >
          <LucideIcon :icon="Wifi" class="h-4 w-4" />
          {{ pinging ? 'Checking…' : 'Ping AI Provider' }}
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <div class="flex items-center gap-2.5">
        <svg class="h-4 w-4 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ error }}</span>
      </div>
      <button
        type="button"
        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <!-- Main Tabbed Card -->
    <div v-else class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">

      <!-- Ping Result Banner (inside card, above tabs) -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="pingResult"
          class="flex items-center gap-2 border-b border-emerald-100 bg-emerald-50 px-4 py-2 text-xs text-emerald-800 dark:border-emerald-800/30 dark:bg-emerald-950/20 dark:text-emerald-300"
        >
          <LucideIcon :icon="CheckCircle" class="h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
          {{ pingResult }}
        </div>
      </transition>

      <!-- Tab Bar -->
      <div class="flex border-b border-gray-200 bg-gray-50/70 dark:border-white/5 dark:bg-white/[0.02]">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :aria-selected="activeTab === tab.key"
          :class="[
            'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-xs font-medium transition-colors focus:outline-none',
            activeTab === tab.key
              ? 'border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
          ]"
          @click="activeTab = tab.key"
        >
          <span
            :class="[
              'flex h-5 w-5 items-center justify-center rounded text-[10px]',
              activeTab === tab.key ? tab.activeIconClass : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400',
            ]"
          >
            <LucideIcon :icon="tab.icon" class="h-3 w-3" />
          </span>
          <span class="hidden sm:inline">{{ tab.label }}</span>
        </button>
      </div>

      <!-- Tab Panels -->
      <div class="p-4">
        <transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          mode="out-in"
        >

          <!-- Diagnostics Panel -->
          <div v-if="activeTab === 'diagnostics'" key="diagnostics" class="space-y-3.5">
            <dl class="grid gap-x-6 gap-y-0 sm:grid-cols-2">
              <div
                v-for="(value, key) in diagnosticsList"
                :key="key"
                class="flex items-center justify-between gap-2 border-b border-gray-100 py-2 last:border-0 dark:border-white/5"
              >
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ key }}</dt>
                <dd class="text-xs font-semibold text-gray-800 dark:text-white/90">{{ value }}</dd>
              </div>
            </dl>

            <!-- Cache Controls -->
            <div class="rounded-md border border-gray-100 bg-gray-50 p-3 dark:border-white/5 dark:bg-white/[0.03]">
              <div class="mb-2.5 flex items-center gap-1.5">
                <LucideIcon :icon="Trash2" class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Cache Management</h3>
              </div>
              <form class="flex flex-wrap items-center gap-2" @submit.prevent="clearCache">
                <select
                  v-model="cacheType"
                  class="flex-1 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                  <option value="all">All caches</option>
                  <option value="config">Config</option>
                  <option value="routes">Routes</option>
                  <option value="views">Views</option>
                  <option value="optimize">Optimize</option>
                </select>
                <button
                  type="submit"
                  :disabled="working"
                  class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/10"
                >
                  <LucideIcon :icon="RefreshCw" class="h-3.5 w-3.5" />
                  {{ working ? 'Working…' : 'Clear Cache' }}
                </button>
              </form>
            </div>
          </div>

          <!-- Inventory Policy Panel -->
          <form v-else-if="activeTab === 'inventory'" key="inventory" @submit.prevent="saveSection('inventory', inventoryForm)">
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label for="low_stock_threshold" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Low stock threshold
                </label>
                <input
                  id="low_stock_threshold"
                  v-model.number="inventoryForm.low_stock_threshold"
                  type="number" min="1" max="1000" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <FieldError :errors="formErrors" field="low_stock_threshold" />
              </div>

              <div>
                <label for="default_lifespan_years" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Default lifespan (years)
                </label>
                <input
                  id="default_lifespan_years"
                  v-model.number="inventoryForm.default_lifespan_years"
                  type="number" min="1" max="30" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="return_due_days" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Return due (days)
                </label>
                <input
                  id="return_due_days"
                  v-model.number="inventoryForm.return_due_days"
                  type="number" min="1" max="365" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="auto_reminder_days" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Reminder (days before due)
                </label>
                <input
                  id="auto_reminder_days"
                  v-model.number="inventoryForm.auto_reminder_days"
                  type="number" min="1" max="30" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="overdue_escalation_days" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Overdue return escalation (days)
                </label>
                <input
                  id="overdue_escalation_days"
                  v-model.number="inventoryForm.overdue_escalation_days"
                  type="number" min="1" max="90" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <FieldError :errors="formErrors" field="overdue_escalation_days" />
              </div>

              <div>
                <label for="inventory_verification_interval_months" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Inventory verification reminder
                </label>
                <select
                  id="inventory_verification_interval_months"
                  v-model.number="inventoryForm.inventory_verification_interval_months"
                  required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                  <option :value="1">Monthly</option>
                  <option :value="3">Every 3 months</option>
                  <option :value="6">Every 6 months</option>
                  <option :value="12">Yearly</option>
                </select>
                <FieldError :errors="formErrors" field="inventory_verification_interval_months" />
                <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                  Sends a reminder only; it does not record inventory verification.
                </p>
              </div>
            </div>

            <div class="mt-4 flex justify-end border-t border-gray-100 pt-3.5 dark:border-white/5">
              <button
                type="submit"
                :disabled="working"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 disabled:opacity-50"
              >
                <LucideIcon :icon="Save" class="h-3.5 w-3.5" />
                {{ working ? 'Saving…' : 'Save Inventory Policy' }}
              </button>
            </div>
          </form>

          <!-- AI Configuration Panel -->
          <form v-else-if="activeTab === 'ai'" key="ai" @submit.prevent="saveSection('ai', aiForm)">
            <!-- API key status cards: one card per pooled key (Ping refreshes inline) -->
            <div class="mt-4 grid items-start gap-4 lg:grid-cols-2">
            <div
              class="rounded-md border border-gray-200 bg-gray-50/60 p-3 dark:border-white/10 dark:bg-white/[0.03]"
              aria-live="polite"
            >
              <div class="flex items-center gap-1.5">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">API key status</h3>
                <span class="group relative inline-flex">
                  <button
                    type="button"
                    aria-label="How to get an API key"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:hover:bg-white/10 dark:hover:text-gray-200"
                    @click="openApiSetupGuide"
                  >
                    <LucideIcon :icon="CircleHelp" class="h-3.5 w-3.5" />
                  </button>
                  <span
                    role="tooltip"
                    class="pointer-events-none absolute left-1/2 top-full z-20 mt-2 w-48 -translate-x-1/2 rounded-md bg-gray-900 px-2.5 py-2 text-center text-[11px] font-medium normal-case tracking-normal text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-within:opacity-100"
                  >
                    How to get a Gemini API key
                  </span>
                </span>
              </div>
              <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">Quota-hit Gemini keys fail over automatically. Keys are stored encrypted and shown masked — the full value is never displayed again. Max 5 active keys per provider.</p>
              <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <input
                  v-model="newKeyLabel"
                  maxlength="30"
                  placeholder="Label (e.g. key-1)"
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:max-w-40"
                />
                <input
                  v-model="newKeyValue"
                  type="password"
                  autocomplete="new-password"
                  placeholder="Paste API key (min 20 chars)"
                  class="w-full flex-1 rounded-md border border-gray-200 px-3 py-1.5 font-mono text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <button
                  type="button"
                  :disabled="keyWorking || !newKeyLabel.trim() || newKeyValue.length < 20"
                  class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-emerald-600 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm transition-colors hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-500/40 dark:bg-gray-800 dark:text-emerald-300"
                  @click="addKey"
                >
                  {{ keyWorking ? 'Saving…' : 'Add key' }}
                </button>
              </div>
              <p v-if="keyMessage" class="mt-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ keyMessage }}</p>
              <div
                v-if="!hasActiveKeys && !envKey"
                class="mt-2 rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400"
              >
                No active key — add one with the form above and Ping to verify.
              </div>
              <div v-if="statusKeys.length || envKey" class="mt-2 grid gap-2 xl:grid-cols-2">
                <div
                  v-for="key in statusKeys"
                  :key="'status-' + key.id"
                  class="rounded-md border border-gray-300 bg-white p-2.5 text-xs shadow-sm dark:border-white/15 dark:bg-gray-900"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      class="relative flex h-2 w-2 shrink-0"
                      :title="statusKeyRole(key)"
                      :aria-label="statusKeyRole(key)"
                    >
                      <span
                        v-if="isPrimaryGeminiKey(key) && key.last_ping_ok === true"
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                      />
                      <span class="relative inline-flex h-2 w-2 rounded-full" :class="statusDotClass(key)" />
                    </span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-gray-500 dark:bg-white/10 dark:text-gray-400">{{ key.provider ?? 'gemini' }}</span>
                    <span class="truncate font-medium text-gray-800 dark:text-gray-200">{{ key.label }}</span>
                    <span class="shrink-0 font-mono text-gray-500 dark:text-gray-400">{{ key.masked_key }}</span>
                    <span class="ml-auto inline-flex shrink-0 items-center gap-1.5">
                      <button
                        type="button"
                        :disabled="keyWorking"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        title="Test this API key with Gemini"
                        aria-label="Test this API key with Gemini"
                        @click="pingKey(key.id)"
                      >
                        <LucideIcon :icon="Wifi" class="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        :disabled="keyWorking"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        :title="key.is_active ? 'Disable this API key; it will no longer be used' : 'Enable this API key for Gemini requests'"
                        :aria-label="key.is_active ? 'Disable this API key; it will no longer be used' : 'Enable this API key for Gemini requests'"
                        @click="toggleKey(key)"
                      >
                        <LucideIcon :icon="Power" class="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        :disabled="keyWorking"
                        class="inline-flex h-7 w-7 items-center justify-center rounded border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900/50 dark:bg-gray-800"
                        title="Permanently delete this stored API key"
                        aria-label="Permanently delete this stored API key"
                        @click="deleteKey(key.id)"
                      >
                        <LucideIcon :icon="Trash2" class="h-3.5 w-3.5" />
                      </button>
                    </span>
                  </div>
                  <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                    <span>{{ key.is_active ? 'Active' : 'Off' }}</span>
                    <span class="font-mono">{{ key.last_tokens_sent ?? '—' }} tokens</span>
                    <span
                      v-if="key.last_checked_at"
                      :class="key.last_ping_ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                    >
                      {{ key.last_ping_ok ? 'Ping ok' : 'Ping fail' }} · {{ key.last_checked_at }}
                    </span>
                    <span v-else-if="(key.provider ?? 'gemini') !== 'gemini'">Stored only — no live probe</span>
                    <span v-else>Never pinged</span>
                  </div>
                  <p v-if="key.last_error" class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">{{ key.last_error }}</p>
                </div>
                <!-- Environment fallback key card (GEMINI_API_KEY) -->
                <div
                  v-if="envKey"
                  class="rounded-md border border-gray-300 bg-white p-2.5 text-xs shadow-sm dark:border-white/15 dark:bg-gray-900"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="relative flex h-2 w-2 shrink-0">
                      <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                        :class="envKey.in_use ? 'bg-emerald-400' : 'bg-blue-400'"
                      />
                      <span
                        class="relative inline-flex h-2 w-2 rounded-full"
                        :class="envKey.in_use ? 'bg-emerald-500' : 'bg-blue-500'"
                      />
                    </span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-gray-500 dark:bg-white/10 dark:text-gray-400">gemini</span>
                    <span class="truncate font-medium text-gray-800 dark:text-gray-200">Environment key (GEMINI_API_KEY)</span>
                    <span class="shrink-0 font-mono text-gray-500 dark:text-gray-400">{{ envKey.masked_key }}</span>
                    <span
                      class="ml-auto shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold"
                      :class="envKey.in_use
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'"
                    >
                      {{ envKey.in_use ? 'In use' : 'Fallback' }}
                    </span>
                  </div>
                  <div class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                    {{ envKey.in_use
                      ? 'Serving Gemini traffic because there is no active pooled Gemini key.'
                      : 'Not serving traffic while an active pooled Gemini key is available.' }}
                  </div>
                </div>
              </div>
            </div>

              <div class="rounded-md border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Model configuration</h3>
                <div class="mt-2 grid gap-3">
              <div>
                <label for="primary_model" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Primary model
                </label>
                <input
                  id="primary_model"
                  v-model="aiForm.primary_model"
                  required maxlength="100"
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 font-mono text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="fallback_model" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Fallback model
                </label>
                <input
                  id="fallback_model"
                  v-model="aiForm.fallback_model"
                  required maxlength="100"
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 font-mono text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
              </div>

              <div>
                <label for="max_history_messages" class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300">
                  Max history messages
                </label>
                <input
                  id="max_history_messages"
                  v-model.number="aiForm.max_history_messages"
                  type="number" min="2" max="20" required
                  class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs text-gray-800 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                />
                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">How many past messages the AI retains per session (2–20).</p>
              </div>
                </div>
              </div>
            </div>

            <div class="mt-4 flex justify-end border-t border-gray-100 pt-3.5 dark:border-white/5">
              <button
                type="submit"
                :disabled="working"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 disabled:opacity-50"
              >
                <LucideIcon :icon="Save" class="h-3.5 w-3.5" />
                {{ working ? 'Saving…' : 'Save AI Config' }}
              </button>
            </div>
          </form>

        </transition>
      </div>
    </div>

    <Modal
      :open="apiSetupGuideOpen"
      title="API Setup Guide"
      subtitle="Set up and manage Gemini API access for the assistant."
      max-width="max-w-2xl"
      :close-on-escape="!guideImagePreviewOpen"
      @close="closeApiSetupGuide"
    >
      <div class="overflow-hidden">
        <Transition :name="guideTransition" mode="out-in">
          <section :key="guideStep" class="flex h-[min(55vh,480px)] flex-col">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                Step {{ guideStep + 1 }}: {{ apiSetupSteps[guideStep].title }}
              </h3>
              <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">Step {{ guideStep + 1 }} of {{ apiSetupSteps.length }}</span>
            </div>
            <button
              v-if="apiSetupSteps[guideStep].image"
              type="button"
              class="relative flex min-h-0 w-full flex-1 cursor-zoom-in items-center justify-center overflow-hidden rounded-lg border border-gray-200 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-700"
              :aria-label="`Enlarge image: ${apiSetupSteps[guideStep].alt}`"
              @click="openGuideImagePreview"
            >
              <img
                :src="apiSetupSteps[guideStep].image"
                :alt="apiSetupSteps[guideStep].alt"
                class="h-full w-full object-contain"
                :class="{
                  'guide-search-zoom': guideStep === 0,
                  'guide-get-started-zoom': guideStep === 1,
                  'guide-dashboard-sidebar-zoom': guideStep === 4,
                  'guide-create-key-zoom': guideStep === 5,
                  'guide-api-label-zoom': guideStep === 8,
                }"
              />
              <svg
                v-if="guideStep === 0 || guideStep === 1 || guideStep === 5 || guideStep === 8"
                class="guide-step-arrow absolute h-7 w-7 text-amber-400 drop-shadow-md"
                :class="{
                  'left-[8%] top-[4%]': guideStep === 0,
                  'left-[82%] top-[4%]': guideStep === 1,
                  'guide-api-key-arrow-sequence': guideStep === 8,
                }"
                :style="guideStep === 5 ? { left: '90%', top: '14%', transform: 'rotate(-90deg)' } : null"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M4 12h16m0 0-7-7m7 7-7 7" />
              </svg>
              <svg
                v-if="guideStep === 4"
                class="guide-step-arrow absolute left-[10%] top-[46%] h-7 w-7 text-amber-400 drop-shadow-md"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M20 12H4m0 0 7-7m-7 7 7 7" />
              </svg>
            </button>
            <div
              v-else
              class="flex min-h-0 flex-1 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-800/50"
            >
              <p class="max-w-md text-base text-gray-700 dark:text-gray-200">{{ apiSetupSteps[guideStep].description }}</p>
            </div>
            <p class="mt-3 min-h-5 text-sm text-gray-600 dark:text-gray-300">
              {{ apiSetupSteps[guideStep].description }}
            </p>
          </section>
        </Transition>
      </div>

      <template #footer>
        <div class="flex items-center justify-between gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
            :disabled="guideStep === 0"
            @click="moveApiSetupStep(-1)"
          >
            Previous
          </button>
          <div class="flex items-center gap-1.5" aria-label="Setup guide progress">
            <span
              v-for="(_, index) in apiSetupSteps"
              :key="index"
              class="h-1.5 w-1.5 rounded-full"
              :class="index === guideStep ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600'"
            />
          </div>
          <button
            type="button"
            class="rounded-md bg-brand-500 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-600"
            @click="guideStep === apiSetupSteps.length - 1 ? closeApiSetupGuide() : moveApiSetupStep(1)"
          >
            {{ guideStep === apiSetupSteps.length - 1 ? 'Done' : 'Next' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="guideImagePreviewOpen"
      :title="guideImagePreviewAlt || 'Setup guide image'"
      max-width="max-w-6xl"
      z-class="z-[100000]"
      @close="guideImagePreviewOpen = false"
    >
      <img
        :src="guideImagePreviewSrc"
        :alt="guideImagePreviewAlt"
        class="max-h-[75vh] w-full rounded-lg object-contain"
      />
    </Modal>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Activity, BrainCircuit, Check, CheckCircle, CircleHelp, Copy, PackageOpen, Power, RefreshCw, Save, Trash2, Wifi } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';

const toast = useToastStore();
const working = ref(false);
const pinging = ref(false);
const error = ref('');
const pingResult = ref('');
const cacheType = ref('all');
const diagnostics = ref({});
const inventoryForm = reactive({
  low_stock_threshold: 5,
  default_lifespan_years: 5,
  return_due_days: 14,
  auto_reminder_days: 3,
  overdue_escalation_days: 7,
  inventory_verification_interval_months: 1,
});
const aiForm = reactive({ primary_model: '', fallback_model: '', max_history_messages: 6 });
const formErrors = ref({});
const aiKeys = ref([]);
const envKey = ref(null);
const newKeyLabel = ref('');
const newKeyValue = ref('');
const keyWorking = ref(false);
const keyMessage = ref('');
const copiedEnv = ref(false);
const apiSetupGuideOpen = ref(false);
const guideStep = ref(0);
const guideDirection = ref(1);
const guideTransition = computed(() => guideDirection.value > 0 ? 'guide-slide-forward' : 'guide-slide-backward');
const guideImagePreviewOpen = ref(false);
const guideImagePreviewSrc = ref('');
const guideImagePreviewAlt = ref('');

const ENV_SNIPPET = 'GEMINI_API_KEY=paste-key-here\nGEMINI_MODEL=gemini-3.1-flash-lite';

const apiSetupSteps = [
  {
    title: 'Search for Google AI Studio',
    image: '/images/setup/step 1 - search .png',
    alt: 'Search for Google AI Studio',
    description: 'Search for Google AI Studio in your browser.',
  },
  {
    title: 'Click “Get started”',
    image: '/images/setup/step-2 Get Started .png',
    alt: 'Google AI Studio Get started page',
    description: 'Open Google AI Studio by selecting “Get started.”',
  },
  {
    title: 'Log in to your Gmail account',
    image: null,
    alt: '',
    description: 'Log in to your Gmail account to continue to Google AI Studio.',
  },
  {
    title: 'Open the Google AI Studio dashboard',
    image: '/images/setup/Step 4 Google AI Studio Dashboard .png',
    alt: 'Google AI Studio dashboard',
    description: 'After signing in, open the Google AI Studio dashboard.',
  },
  {
    title: 'Select Dashboard in the sidebar',
    image: '/images/setup/Step 4 Google AI Studio Dashboard .png',
    alt: 'Google AI Studio dashboard with Dashboard highlighted in the sidebar',
    description: 'Select Dashboard in the left sidebar to open the project dashboard.',
  },
  {
    title: 'Open API Keys and create a key',
    image: '/images/setup/Step 5 Create API.png',
    alt: 'Google AI Studio API Keys page with Create API key',
    description: 'Select API Keys, then choose “Create API key.”',
  },
  {
    title: 'Create the API key',
    image: '/images/setup/step 6 create api key modal.png',
    alt: 'Create a new Gemini API key',
    description: 'Enter a name for the key, select a project if prompted, and create it.',
  },
  {
    title: 'Copy the API key',
    image: '/images/setup/step 7 copy the key .png',
    alt: 'Google AI Studio API key details with Copy key button',
    description: 'Copy and save the key securely. Google may only show the full key once.',
  },
  {
    title: 'Add the API key to this app',
    image: '/images/setup/step 8 put the api.png',
    alt: 'AI Config API key form',
    description: 'Return to AI Config, enter a label, paste your copied key, and select “Add key.”',
  },
];

function openApiSetupGuide() {
  guideStep.value = 0;
  guideDirection.value = 1;
  apiSetupGuideOpen.value = true;
}

function closeApiSetupGuide() {
  apiSetupGuideOpen.value = false;
}

function moveApiSetupStep(direction) {
  const nextStep = guideStep.value + direction;
  if (nextStep < 0 || nextStep >= apiSetupSteps.length) return;

  guideDirection.value = direction;
  guideStep.value = nextStep;
}

function openGuideImagePreview() {
  const step = apiSetupSteps[guideStep.value];
  if (!step?.image) return;

  guideImagePreviewSrc.value = step.image;
  guideImagePreviewAlt.value = step.alt;
  guideImagePreviewOpen.value = true;
}

const activeTab = ref('diagnostics');

const tabs = [
  {
    key: 'diagnostics',
    label: 'Diagnostics',
    icon: Activity,
    activeIconClass: 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400',
  },
  {
    key: 'inventory',
    label: 'Inventory Policy',
    icon: PackageOpen,
    activeIconClass: 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
  },
  {
    key: 'ai',
    label: 'AI Config',
    icon: BrainCircuit,
    activeIconClass: 'bg-purple-100 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400',
  },
];

const diagnosticsList = computed(() => {  const d = diagnostics.value ?? {};
  return {
    PHP: d.phpVersion ?? '—',
    Laravel: d.laravelVersion ?? '—',
    Environment: d.appEnv ?? '—',
    Database: d.dbConnected ? `Connected (${d.dbDriver ?? ''})` : 'Disconnected',
    Users: Number(d.totalUsers ?? 0).toLocaleString(),
    Inventory: Number(d.totalInventory ?? 0).toLocaleString(),
    'AI key set': d.geminiKeySet ? 'Yes' : 'No',
    'AI model': d.activeAiModel ?? '—',
  };
});

// Status cards: one per pooled key (all providers), active first. Shares
// the aiKeys pool state, so Ping updates each card inline via refreshKeys().
const statusKeys = computed(() => {
  const rows = aiKeys.value ?? [];
  return [...rows].sort((a, b) => Number(b.is_active) - Number(a.is_active));
});

const primaryGeminiKey = computed(() => statusKeys.value
  .filter((key) => key.is_active && (key.provider ?? 'gemini') === 'gemini')
  .sort((a, b) => Number(a.priority) - Number(b.priority) || Number(a.id) - Number(b.id))[0] ?? null);

const hasActiveKeys = computed(() => statusKeys.value.some((key) => key.is_active));

function statusDotClass(key) {
  if (!key.is_active) return 'bg-gray-300 dark:bg-gray-600';
  if (key.last_ping_ok === false) return 'bg-rose-500';
  return isPrimaryGeminiKey(key) ? 'bg-emerald-500' : 'bg-blue-500';
}

function isPrimaryGeminiKey(key) {
  return primaryGeminiKey.value?.id === key.id;
}

function statusKeyRole(key) {
  if (!key.is_active) return 'Inactive key';
  if (key.last_ping_ok === false) return 'Last ping failed';
  return isPrimaryGeminiKey(key)
    ? 'Primary Gemini key — tried first'
    : 'Standby key — available for failover';
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'admin-settings',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/admin/settings');
      return data;
    },
    applyData: (data) => {
      diagnostics.value = data.systemDiagnostics ?? {};
      Object.assign(inventoryForm, data.inventoryPolicy ?? {});
      Object.assign(aiForm, data.aiConfig ?? {});
      aiKeys.value = data.aiKeys ?? [];
      envKey.value = data.envKey ?? null;
    },
    onStart: () => {
      error.value = '';
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load settings.';
    },
  });
}

async function saveSection(section, form) {
  working.value = true;
  formErrors.value = {};
  try {
    await api.post('/admin/settings', { section, ...form });
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      formErrors.value = requestError?.response?.data?.errors ?? {};
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not save settings.');
    }
  } finally {
    working.value = false;
  }
}

async function clearCache() {
  working.value = true;
  try {
    await api.post('/admin/settings/clear-cache', { cache_type: cacheType.value });
  } finally {
    working.value = false;
  }
}

async function pingAi() {
  pinging.value = true;
  pingResult.value = '';
  try {
    const { data } = await api.post('/admin/settings/ping-ai');
    pingResult.value = data.message ?? (data.ok ? 'AI provider responded.' : 'AI provider did not respond.');
  } catch (requestError) {
    pingResult.value = requestError?.response?.data?.message ?? 'AI connectivity check failed.';
  } finally {
    pinging.value = false;
  }
}

async function refreshKeys() {
  const { data } = await api.get('/admin/settings/keys');
  aiKeys.value = data.keys ?? [];
  envKey.value = data.envKey ?? null;
}

async function addKey() {
  keyWorking.value = true;
  keyMessage.value = '';
  try {
    await api.post('/admin/settings/keys', { label: newKeyLabel.value.trim(), key: newKeyValue.value });
    newKeyLabel.value = '';
    newKeyValue.value = '';
    await refreshKeys();
    keyMessage.value = 'Key saved.';
  } catch (requestError) {
    keyMessage.value = requestError?.response?.data?.message
      ?? Object.values(requestError?.response?.data?.errors ?? {}).flat().join(' ')
      ?? 'Could not save key.';
  } finally {
    keyWorking.value = false;
  }
}

async function copyEnvSnippet() {
  try {
    await navigator.clipboard.writeText(ENV_SNIPPET);
  } catch {
    const fallback = document.createElement('textarea');
    fallback.value = ENV_SNIPPET;
    document.body.appendChild(fallback);
    fallback.select();
    try {
      document.execCommand('copy');
    } catch {
      return;
    } finally {
      fallback.remove();
    }
  }
  copiedEnv.value = true;
  setTimeout(() => { copiedEnv.value = false; }, 2000);
}

async function toggleKey(key) {
  keyWorking.value = true;
  keyMessage.value = '';
  try {
    await api.patch(`/admin/settings/keys/${key.id}`, { is_active: !key.is_active });
    await refreshKeys();
  } catch (requestError) {
    keyMessage.value = requestError?.response?.data?.message ?? 'Could not update key.';
  } finally {
    keyWorking.value = false;
  }
}

async function deleteKey(id) {
  keyWorking.value = true;
  keyMessage.value = '';
  try {
    await api.delete(`/admin/settings/keys/${id}`);
    await refreshKeys();
  } catch (requestError) {
    keyMessage.value = requestError?.response?.data?.message ?? 'Could not delete key.';
  } finally {
    keyWorking.value = false;
  }
}

async function pingKey(id) {
  keyWorking.value = true;
  keyMessage.value = '';
  try {
    const { data } = await api.post(`/admin/settings/keys/${id}/ping`);
    keyMessage.value = data.message ?? (data.ok ? 'Key responded.' : 'Key did not respond.');
    await refreshKeys();
  } catch (requestError) {
    keyMessage.value = requestError?.response?.data?.message ?? 'Key ping failed.';
  } finally {
    keyWorking.value = false;
  }
}

onMounted(() => load());
</script>

<style scoped>
.guide-slide-forward-enter-active,
.guide-slide-forward-leave-active,
.guide-slide-backward-enter-active,
.guide-slide-backward-leave-active {
  transition: opacity 220ms ease, transform 220ms ease;
}

.guide-slide-forward-enter-from {
  opacity: 0;
  transform: translateX(2rem);
}

.guide-slide-forward-leave-to {
  opacity: 0;
  transform: translateX(-2rem);
}

.guide-slide-backward-enter-from {
  opacity: 0;
  transform: translateX(-2rem);
}

.guide-slide-backward-leave-to {
  opacity: 0;
  transform: translateX(2rem);
}

.guide-get-started-zoom {
  transform-origin: 94% 5%;
  animation: guide-highlight-zoom 7s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-search-zoom {
  transform-origin: 20% 5%;
  animation: guide-highlight-zoom 7s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-create-key-zoom {
  transform-origin: 90% 8%;
  animation: guide-highlight-zoom 7s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-api-label-zoom {
  transform-origin: 8% 42%;
  animation: guide-api-fields-zoom 21s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-api-key-arrow-sequence {
  left: 8%;
  top: 16%;
  transform: rotate(90deg);
  animation: guide-api-fields-arrow 21s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-dashboard-sidebar-zoom {
  transform-origin: 7% 49%;
  animation: guide-dashboard-sidebar-zoom 7s ease-in-out infinite;
  animation-delay: 1s;
}

@keyframes guide-highlight-zoom {
  0%,
  100% {
    transform: scale(1);
  }

  14.29%,
  57.14% {
    transform: scale(1.35);
  }

  71.43% {
    transform: scale(1);
  }
}

@keyframes guide-dashboard-sidebar-zoom {
  0%,
  100% {
    transform: scale(1);
  }

  14.29%,
  57.14% {
    transform: scale(1.35);
  }

  71.43% {
    transform: scale(1);
  }
}

@keyframes guide-api-fields-zoom {
  0%,
  100% {
    transform: scale(1);
    transform-origin: 8% 42%;
  }

  4.76%,
  19.05% {
    transform: scale(1.35);
    transform-origin: 8% 42%;
  }

  23.81% {
    transform: scale(1);
    transform-origin: 8% 42%;
  }

  33.33% {
    transform: scale(1);
    transform-origin: 35% 42%;
  }

  38.1%,
  52.38% {
    transform: scale(1.35);
    transform-origin: 35% 42%;
  }

  57.14% {
    transform: scale(1);
    transform-origin: 35% 42%;
  }

  66.67% {
    transform: scale(1);
    transform-origin: 96% 42%;
  }

  71.43%,
  85.71% {
    transform: scale(1.35);
    transform-origin: 96% 42%;
  }

  90.48% {
    transform: scale(1);
    transform-origin: 96% 42%;
  }
}

.guide-step-arrow {
  opacity: 0;
  animation: guide-step-arrow 7s ease-in-out infinite;
  animation-delay: 1s;
}

.guide-api-key-arrow-sequence {
  animation: guide-api-fields-arrow 21s ease-in-out infinite;
  animation-delay: 1s;
}

@keyframes guide-api-fields-arrow {
  0%,
  4%,
  23.81%,
  100% {
    left: 8%;
    opacity: 0;
  }

  4.76%,
  19.05% {
    left: 8%;
    opacity: 1;
  }

  28% {
    left: 19%;
    opacity: 0;
  }

  38.1%,
  52.38% {
    left: 19%;
    opacity: 1;
  }

  57.14% {
    left: 19%;
    opacity: 0;
  }

  66.67%,
  85.71% {
    left: 90%;
    opacity: 1;
  }

  90.48% {
    left: 90%;
    opacity: 0;
  }

  95% {
    left: 19%;
    opacity: 0;
  }
}

@keyframes guide-step-arrow {
  0%,
  14.29%,
  58.5%,
  71.43%,
  100% {
    opacity: 0;
  }

  17.14%,
  57.14% {
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .guide-get-started-zoom,
  .guide-search-zoom,
  .guide-create-key-zoom,
  .guide-api-label-zoom,
  .guide-dashboard-sidebar-zoom {
    animation: none;
  }

  .guide-step-arrow {
    opacity: 1;
    animation: none;
  }

  .guide-slide-forward-enter-active,
  .guide-slide-forward-leave-active,
  .guide-slide-backward-enter-active,
  .guide-slide-backward-leave-active {
    transition-duration: 1ms;
  }
}
</style>
