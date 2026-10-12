<template>
  <Modal :open="open" title="Stock In New Items" subtitle="Record and add new inventory items into the stockroom." max-width="max-w-5xl" compact @close="$emit('close')">
    <div class="mb-2 grid grid-cols-2 gap-0.5 rounded-md bg-gray-100 p-0.5 dark:bg-white/5" role="tablist" aria-label="Stock-in method">
      <button
        v-for="tab in stockInTabs"
        :key="tab.key"
        type="button"
        role="tab"
        class="rounded px-2 py-1 text-[11px] font-semibold transition-all"
        :class="activeTab === tab.key
          ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
          : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
        :aria-selected="activeTab === tab.key"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>
    <form v-if="activeTab === 'manual'" class="space-y-3 min-[480px]:space-y-4" @submit.prevent="submit">
      <div
        v-if="validationMessages.length || formError"
        role="alert"
        aria-live="assertive"
        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200 min-[480px]:px-4 min-[480px]:py-3 min-[480px]:text-sm"
      >
        <p class="font-semibold">{{ formError || 'Please correct the highlighted fields and try again.' }}</p>
        <ul v-if="validationMessages.length" class="mt-1 list-inside list-disc space-y-0.5">
          <li v-for="(error, index) in validationMessages" :key="`${error.field}-${index}`">
            <strong>{{ error.field }}:</strong> {{ error.message }}
          </li>
        </ul>
      </div>

      <div class="grid gap-3 lg:grid-cols-2 lg:items-start min-[480px]:gap-4">
      <!-- Section 1: Item Basic Information -->
      <div class="rounded-md bg-gray-50/60 p-3 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5 min-[480px]:p-4">
        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 mb-2.5 flex items-center gap-1.5 min-[480px]:mb-3">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          Primary Details
        </h4>

        <div class="grid gap-2.5 min-[360px]:grid-cols-2 min-[480px]:gap-3.5">
          <div class="min-[360px]:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-item-name">
              Item Name <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-item-name"
              v-model="form.item_name"
              type="text"
              required
              maxlength="255"
              placeholder="e.g. Epson EB-X06 Projector, Microscope 40x-1000x…"
              @input="clearFieldError('item_name')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="item_name" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-category">
              Category <span class="text-rose-500">*</span>
            </label>
            <select
              id="stock-category"
              v-model="form.category_id"
              required
              @change="clearFieldError('category_id')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3 min-[480px]:text-sm"
            >
              <option value="">Select a category</option>
              <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                {{ category.category_name }}
              </option>
            </select>
            <FieldError :errors="errors" field="category_id" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-unit">
              Measurement Unit <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-unit"
              v-model="form.unit"
              type="text"
              required
              maxlength="255"
              placeholder="piece, set, box, roll…"
              @input="clearFieldError('unit')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="unit" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-quantity">
              Quantity (1–100) <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-quantity"
              v-model.number="form.quantity"
              type="number"
              required
              min="1"
              max="100"
              @input="clearFieldError('quantity')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="quantity" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-unit-cost">
              Unit Cost (₱) <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute left-2.5 top-2 text-xs font-semibold text-gray-400 min-[480px]:left-3 min-[480px]:text-sm">₱</span>
              <input
                id="stock-unit-cost"
                v-model.number="form.unit_cost"
                type="number"
                required
                min="0"
                step="0.01"
                @input="clearFieldError('unit_cost')"
                class="block w-full rounded-md border border-gray-200 bg-white py-2 pl-7 pr-2.5 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:pl-8 min-[480px]:pr-3 min-[480px]:text-sm"
              />
            </div>
            <FieldError :errors="errors" field="unit_cost" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-date">
              Date Acquired <span class="text-rose-500">*</span>
            </label>
            <input
              id="stock-date"
              v-model="form.date_acquired"
              type="date"
              required
              :max="todayMax"
              @change="clearFieldError('date_acquired')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="date_acquired" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-lifespan">
              Estimated Lifespan (Years)
            </label>
            <input
              id="stock-lifespan"
              v-model.number="form.lifespan_years"
              type="number"
              min="1"
              placeholder="e.g. 5"
              @input="clearFieldError('lifespan_years')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="lifespan_years" />
          </div>
        </div>
      </div>

      <!-- Section 2: Storage Location & Identification -->
      <div class="rounded-md bg-gray-50/60 p-3 border border-gray-100 dark:bg-white/[0.02] dark:border-white/5 min-[480px]:p-4">
        <h4 class="text-xs font-bold uppercase tracking-wider text-teal-800 dark:text-teal-300 mb-2.5 flex items-center gap-1.5 min-[480px]:mb-3">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          Tracking & Storage Location
        </h4>

        <div class="grid gap-2.5 min-[360px]:grid-cols-2 min-[480px]:gap-3.5">
          <div class="min-[360px]:col-span-2">
            <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300" for="stock-ics">
              Inventory Custodian Slip (ICS) No.
            </label>
            <input
              id="stock-ics"
              v-model="form.ics_no"
              type="text"
              maxlength="255"
              placeholder="e.g. ICS-2026-001"
              @input="clearFieldError('ics_no')"
              class="block w-full rounded-md border border-gray-200 bg-white px-2.5 py-2 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 font-mono min-[480px]:px-3.5 min-[480px]:text-sm"
            />
            <FieldError :errors="errors" field="ics_no" />
          </div>
        </div>
      </div>
      </div>

      <!-- Section 3: Serial Numbers (Required by Category) -->
      <div
        v-if="requiresSerial"
        class="rounded-md border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900/40 dark:bg-amber-950/20 min-[480px]:p-4"
      >
        <div class="flex items-center gap-2 mb-2">
          <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p class="text-xs font-bold text-amber-900 dark:text-amber-300">
            Serial Numbers Required for Category ({{ form.quantity }} items)
          </p>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 mt-3">
          <div v-for="index in serialCount" :key="index" class="relative">
            <input
              v-model="serials[index - 1]"
              type="text"
              required
              maxlength="255"
              :placeholder="`Serial number #${index}`"
              @input="clearFieldError(`serial_numbers.${index - 1}`)"
              class="block w-full rounded-md border border-amber-300 bg-white px-3 py-1.5 text-xs text-gray-800 font-mono transition-colors focus:border-emerald-500 focus:outline-none dark:border-amber-800 dark:bg-gray-800 dark:text-gray-100"
            />
            <FieldError
              :errors="errors"
              :field="`serial_numbers.${index - 1}`"
            />
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end gap-2 pt-1 min-[480px]:gap-3 min-[480px]:pt-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5 min-[480px]:px-4 min-[480px]:py-2.5 min-[480px]:text-sm"
          @click="$emit('close')"
        >
          Cancel
        </button>
        <button
          type="submit"
          class="inline-flex items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg disabled:opacity-50 min-[480px]:gap-2 min-[480px]:px-5 min-[480px]:py-2.5 min-[480px]:text-sm"
          :disabled="manualBusy"
        >
          <svg v-if="manualBusy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          {{ manualBusy ? 'Saving Stock…' : 'Save & Stock In' }}
        </button>
      </div>
    </form>
    <div v-else class="space-y-2 min-[480px]:space-y-3">
      <div class="rounded-md border border-gray-100 bg-gray-50/60 p-2.5 dark:border-white/5 dark:bg-white/[0.02] min-[480px]:p-3">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
          <span class="inline-flex items-center gap-1.5">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">1</span>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
              @click="exportCsv"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
              Export template
            </button>
          </span>
          <span class="inline-flex items-center gap-1.5">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">2</span>
            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
              </svg>
              Choose file
              <input ref="csvFileInput" type="file" accept=".csv,text/csv" class="sr-only" @change="handleCsvFile" />
            </label>
          </span>
          <span v-if="csvRows.length" class="inline-flex items-center gap-1.5">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">3</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ validCsvRows.length }} of {{ csvRows.length }} ready</span>
          </span>
        </div>
        <p v-if="csvError" role="alert" class="mb-2 mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ csvError }}</p>
        <div v-if="csvRows.length" class="mt-2 max-h-64 overflow-auto overflow-x-auto rounded-md border border-gray-200/70 dark:border-gray-800">
          <table class="w-max min-w-full text-left text-xs">
            <thead class="sticky top-0 z-10 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              <tr>
                <th class="whitespace-nowrap px-4 py-2 text-xs">#</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-52">Item name</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-40">Category</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-24">Unit</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-24 text-right">Qty</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-28 text-right">Unit cost</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-32">Acquired</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-24 text-right">Lifespan</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-44 font-mono">ICS no.</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-48 font-mono">Serials</th>
                <th class="whitespace-nowrap px-4 py-2 text-xs min-w-40">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
              <tr v-for="row in csvRows" :key="row.n" :class="[(row.errors.length || row.serverErrors.length) ? 'bg-rose-50/50 dark:bg-rose-950/20' : '', row.n % 2 === 0 ? 'bg-gray-50/60 dark:bg-white/[0.02]' : '']">
                <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-400">{{ row.n }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-52" :class="row.fieldErrors?.item_name ? 'border border-rose-300' : ''">
                  <span :class="row.values.item_name ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-400'">{{ row.values.item_name || '—' }}</span>
                  <p v-if="row.fieldErrors?.item_name" class="mt-0.5 whitespace-normal text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.item_name }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-40" :class="row.fieldErrors?.category_name ? 'border border-rose-300' : ''">
                  <span :class="row.values.category_name ? 'text-gray-600 dark:text-gray-300' : 'text-gray-400'">{{ row.values.category_name || '—' }}</span>
                  <p v-if="row.fieldErrors?.category_name" class="mt-0.5 whitespace-normal text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.category_name }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-24" :class="row.fieldErrors?.unit ? 'border border-rose-300' : ''">
                  <span :class="row.values.unit ? 'text-gray-600 dark:text-gray-300' : 'text-gray-400'">{{ row.values.unit || '—' }}</span>
                  <p v-if="row.fieldErrors?.unit" class="mt-0.5 whitespace-normal text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.unit }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-24 text-right" :class="row.fieldErrors?.quantity ? 'border border-rose-300' : ''">
                  <span :class="String(row.values.quantity ?? '') !== '' ? 'font-bold text-gray-900 dark:text-white' : 'text-gray-400'">{{ String(row.values.quantity ?? '') !== '' ? row.values.quantity : '—' }}</span>
                  <p v-if="row.fieldErrors?.quantity" class="mt-0.5 whitespace-normal text-left text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.quantity }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-28 text-right" :class="row.fieldErrors?.unit_cost ? 'border border-rose-300' : ''">
                  <span :class="String(row.values.unit_cost ?? '') !== '' ? 'text-gray-900 dark:text-white' : 'text-gray-400'">{{ String(row.values.unit_cost ?? '') !== '' ? row.values.unit_cost : '—' }}</span>
                  <p v-if="row.fieldErrors?.unit_cost" class="mt-0.5 whitespace-normal text-left text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.unit_cost }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-32" :class="row.fieldErrors?.date_acquired ? 'border border-rose-300' : ''">
                  <span :class="row.values.date_acquired ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400'">{{ row.values.date_acquired || '—' }}</span>
                  <p v-if="row.fieldErrors?.date_acquired" class="mt-0.5 whitespace-normal text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.date_acquired }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-24 text-right" :class="row.fieldErrors?.lifespan_years ? 'border border-rose-300' : ''">
                  <span :class="row.values.lifespan_years ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400'">{{ row.values.lifespan_years ?? '—' }}</span>
                  <p v-if="row.fieldErrors?.lifespan_years" class="mt-0.5 whitespace-normal text-left text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.lifespan_years }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-44 font-mono" :class="row.fieldErrors?.ics_no ? 'border border-rose-300' : ''">
                  <span :class="row.values.ics_no ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400'">{{ row.values.ics_no || '—' }}</span>
                  <p v-if="row.fieldErrors?.ics_no" class="mt-0.5 whitespace-normal font-sans text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.ics_no }}</p>
                </td>
                <td class="max-w-52 px-4 py-2 text-xs min-w-48 font-mono" :class="row.fieldErrors?.serial_numbers ? 'border border-rose-300' : ''">
                  <span class="block max-w-52 truncate whitespace-nowrap" :title="row.values.serials.join(', ')" :class="row.values.serials.length ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400'">{{ row.values.serials.length ? row.values.serials.join(', ') : '—' }}</span>
                  <p v-if="row.fieldErrors?.serial_numbers" class="mt-0.5 whitespace-normal font-sans text-[11px] text-rose-700 dark:text-rose-300">{{ row.fieldErrors.serial_numbers }}</p>
                </td>
                <td class="whitespace-nowrap px-4 py-2 text-xs min-w-40">
                  <span v-if="!row.errors.length && !row.serverErrors.length" class="inline-flex rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">Ready</span>
                  <ul v-else class="list-inside list-disc space-y-0.5 whitespace-normal text-[11px] text-rose-700 dark:text-rose-300">
                    <li v-for="(error, index) in [...row.errors, ...row.serverErrors]" :key="index">{{ error }}</li>
                  </ul>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

        <div v-if="csvResult" role="status" class="mt-2 rounded-md border px-3 py-2.5 text-xs min-[480px]:text-sm" :class="csvResult.failed === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200'">
          <p class="font-semibold">{{ csvResult.message }}</p>
          <ul v-if="csvResult.failed > 0" class="mt-1 list-inside list-disc space-y-0.5">
            <li v-for="(messages, position) in csvResult.errors" :key="position">Row {{ Number(position) + 1 }}: {{ flattenMessages(messages).join('; ') }}</li>
          </ul>
        </div>

      <div class="flex justify-end gap-2 pt-1 min-[480px]:gap-3 min-[480px]:pt-2">
        <button
          type="button"
          class="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5 min-[480px]:px-4 min-[480px]:py-2.5 min-[480px]:text-sm"
          @click="$emit('close')"
        >
          Close
        </button>
        <button
          v-if="csvResult && csvResult.imported > 0"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg min-[480px]:gap-2 min-[480px]:px-5 min-[480px]:py-2.5 min-[480px]:text-sm"
          @click="finishCsvImport"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
          Done
        </button>
        <button
          v-else
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg disabled:opacity-50 min-[480px]:gap-2 min-[480px]:px-5 min-[480px]:py-2.5 min-[480px]:text-sm"
          :disabled="csvBusy || !validCsvRows.length"
          @click="confirmCsvImport"
        >
          <svg v-if="csvBusy" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ csvBusy ? 'Importing…' : 'Confirm import' }}
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Papa from 'papaparse';
import api from '../../lib/axios';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const activeTab = ref('manual');
const stockInTabs = [
    { key: 'manual', label: 'Manual' },
    { key: 'csv', label: 'Through CSV' },
];

const manualBusy = ref(false);
const errors = ref({});
const formError = ref('');
const serials = ref([]);

const form = reactive({
    item_name: '',
    category_id: '',
    unit: '',
    quantity: 1,
    unit_cost: 0,
    date_acquired: new Date().toISOString().slice(0, 10),
    lifespan_years: null,
    ics_no: '',
});

const selectedCategory = computed(() =>
    props.categories.find((category) => String(category.category_id) === String(form.category_id)),
);

const todayMax = computed(() => new Date().toISOString().slice(0, 10));

const requiresSerial = computed(() => selectedCategory.value?.requires_serial_number === true || selectedCategory.value?.requires_serial_number === 1);

const serialCount = computed(() => Math.min(Math.max(Number(form.quantity) || 1, 1), 100));
const validationMessages = computed(() =>
    Object.entries(errors.value).flatMap(([key, messages]) => {
        const field = key.startsWith('serial_numbers.')
            ? `Serial number #${Number(key.split('.')[1]) + 1}`
            : key.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        return (Array.isArray(messages) ? messages : [messages])
            .filter((message) => typeof message === 'string' && message)
            .map((message) => ({ field, message }));
    }),
);

watch(
    () => [form.category_id, form.quantity],
    () => {
        errors.value = {};
        formError.value = '';
        serials.value = Array.from({ length: requiresSerial.value ? serialCount.value : 0 }, (_, index) => serials.value[index] ?? '');
    },
);

watch(
    () => props.open,
    (open) => {
        if (open) {
            errors.value = {};
            formError.value = '';
            manualBusy.value = false;
            csvBusy.value = false;
            csvError.value = '';
            csvResult.value = null;
        }
    },
);

function resetForm() {
    errors.value = {};
    formError.value = '';
    manualBusy.value = false;
    serials.value = [];
    form.item_name = '';
    form.category_id = '';
    form.unit = '';
    form.quantity = 1;
    form.unit_cost = 0;
    form.date_acquired = new Date().toISOString().slice(0, 10);
    form.lifespan_years = null;
    form.ics_no = '';
}

async function submit() {
    if (manualBusy.value) return;

    manualBusy.value = true;
    errors.value = {};
    formError.value = '';

    const payload = {
        item_name: form.item_name,
        category_id: Number(form.category_id),
        unit: form.unit,
        quantity: Number(form.quantity),
        unit_cost: Number(form.unit_cost),
        date_acquired: form.date_acquired,
        description: null,
        ics_no: form.ics_no || null,
        building: null,
        room: null,
    };

    if (form.lifespan_years) {
        payload.lifespan_years = Number(form.lifespan_years);
    }

    if (requiresSerial.value) {
        payload.serial_numbers = serials.value.slice(0, serialCount.value);
    }

    try {
        await api.post('/custodian/inventory/stock-in', payload);
        resetForm();
        emit('saved');
    } catch (requestError) {
        const response = requestError?.response;
        const responseErrors = response?.data?.errors;
        errors.value = responseErrors && typeof responseErrors === 'object' && !Array.isArray(responseErrors)
            ? responseErrors
            : {};

        if (response?.status === 422 && validationMessages.value.length) {
            formError.value = '';
        } else {
            formError.value = stockInErrorMessage(response, requestError);
        }
    } finally {
        manualBusy.value = false;
    }
}

function clearFieldError(field) {
    if (Object.prototype.hasOwnProperty.call(errors.value, field)) {
        const remaining = { ...errors.value };
        delete remaining[field];
        errors.value = remaining;
    }
    formError.value = '';
}

const CSV_HEADERS = ['item_name', 'category_name', 'unit', 'quantity', 'unit_cost', 'date_acquired', 'lifespan_years', 'ics_no', 'serial_numbers'];
const CSV_EXPECTED_LABEL = CSV_HEADERS.join(',');

function escapeCsvCell(value) {
    const text = value === null || value === undefined ? '' : String(value);
    return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function exportCsv() {
    const csv = String.fromCharCode(0xFEFF) + [CSV_HEADERS].map((row) => row.map(escapeCsvCell).join(',')).join('\r\n') + '\r\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'stock-in-template.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

// ---- Through CSV tab state (independent from the manual draft) ----
const csvFileInput = ref(null);
const csvFileName = ref('');
const csvRows = ref([]);
const csvError = ref('');
const csvBusy = ref(false);
const csvResult = ref(null);
const sentCsvRows = ref([]);

const validCsvRows = computed(() => csvRows.value.filter((row) => row.errors.length === 0));

function flattenMessages(messages) {
    if (Array.isArray(messages)) {
        return messages.filter((message) => typeof message === 'string' && message);
    }
    if (messages && typeof messages === 'object') {
        return Object.entries(messages).flatMap(([field, fieldMessages]) => {
            const list = Array.isArray(fieldMessages) ? fieldMessages : [fieldMessages];
            return list
                .filter((message) => typeof message === 'string' && message)
                .map((message) => `${field}: ${message}`);
        });
    }
    return typeof messages === 'string' && messages ? [messages] : [];
}

function categoryForCsv(categoryName) {
    const needle = String(categoryName ?? '').trim().toLowerCase();
    if (!needle) return null;
    return props.categories.find((category) => String(category.category_name ?? '').trim().toLowerCase() === needle) ?? null;
}

function validateCsvRow(raw, position) {
    const errors = [];
    const fieldErrors = {};
    const fail = (field, message) => {
        errors.push(message);
        if (!fieldErrors[field]) fieldErrors[field] = message;
    };
    const normalized = {};
    for (const [key, value] of Object.entries(raw ?? {})) {
        normalized[String(key).replace(/^\uFEFF/, '').trim()] = typeof value === 'string' ? value.trim() : value;
    }
    if ('category_id' in normalized) {
        fail('category_name', "Column 'category_id' is no longer supported. Use 'category_name' instead — please export a fresh template.");
    }
    const text = (key) => (normalized[key] === null || normalized[key] === undefined ? '' : String(normalized[key]));

    if (!text('item_name')) {
        fail('item_name', 'Item name is required.');
    } else if (text('item_name').length > 255) {
        fail('item_name', 'Item name must not exceed 255 characters.');
    }

    const category = text('category_name') ? categoryForCsv(text('category_name')) : null;
    if (!text('category_name')) {
        fail('category_name', 'Category is required.');
    } else if (!category) {
        fail('category_name', `Unknown category '${text('category_name')}'. Use the exact category name from the template.`);
    }
    const requiresSerial = category?.requires_serial_number === true || category?.requires_serial_number === 1;

    if (!text('unit')) {
        fail('unit', 'Unit is required.');
    }

    const quantity = Number(text('quantity'));
    if (!text('quantity') || !Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
        fail('quantity', 'Quantity must be a whole number from 1 to 100.');
    }

    const unitCost = Number(text('unit_cost'));
    if (!text('unit_cost') && text('unit_cost') !== '0' && text('unit_cost') !== 0) {
        fail('unit_cost', 'Unit cost must be a number of 0 or more.');
    } else if (Number.isNaN(unitCost) || unitCost < 0) {
        fail('unit_cost', 'Unit cost must be a number of 0 or more.');
    }

    const acquired = text('date_acquired');
    if (!acquired || Number.isNaN(Date.parse(acquired))) {
        fail('date_acquired', 'Date acquired must be a valid date.');
    } else if (acquired > todayMax.value) {
        fail('date_acquired', 'Date acquired cannot be in the future.');
    }

    let lifespanYears = null;
    if (text('lifespan_years')) {
        lifespanYears = Number(text('lifespan_years'));
        if (!Number.isInteger(lifespanYears) || lifespanYears < 1 || lifespanYears > 65535) {
            fail('lifespan_years', 'Lifespan must be a whole number from 1 to 65535 years.');
        }
    }

    if (text('ics_no').length > 255) {
        fail('ics_no', 'ICS number must not exceed 255 characters.');
    }

    const serials = text('serial_numbers') ? text('serial_numbers').split(';').map((serial) => serial.trim()).filter(Boolean) : [];
    if (requiresSerial) {
        if (serials.length !== quantity) {
            fail('serial_numbers', `Serial numbers must list exactly ${quantity} value(s) separated by ';' (found ${serials.length}).`);
        } else if (new Set(serials).size !== serials.length) {
            fail('serial_numbers', 'Serial numbers must not repeat within the row.');
        }
    }

    return {
        n: position + 1,
        values: {
            item_name: text('item_name'),
            category_id: category ? Number(category.category_id) : '',
            category_name: category?.category_name ?? text('category_name'),
            unit: text('unit'),
            quantity: Number.isInteger(quantity) ? quantity : text('quantity'),
            unit_cost: Number.isNaN(unitCost) ? text('unit_cost') : unitCost,
            date_acquired: acquired,
            lifespan_years: lifespanYears,
            ics_no: text('ics_no'),
            serials,
        },
        errors,
        fieldErrors,
        serverErrors: [],
    };
}

function resetCsv() {
    csvBusy.value = false;
    csvError.value = '';
    csvResult.value = null;
    sentCsvRows.value = [];
    csvRows.value = [];
    csvFileName.value = '';
    if (csvFileInput.value) {
        csvFileInput.value.value = '';
    }
}

function handleCsvFile(event) {
    const file = event?.target?.files?.[0];
    resetCsv();
    if (!file) {
        return;
    }
    csvFileName.value = file.name;

    Papa.parse(file, {
        header: true,
        skipEmptyLines: true,
        complete: (results) => {
            const fields = Array.isArray(results?.meta?.fields)
                ? results.meta.fields.map((field) => String(field ?? '').replace(/^\uFEFF/, '').trim())
                : [];
            if (fields.includes('category_id')) {
                csvError.value = `Column 'category_id' is no longer supported. Use 'category_name' instead — please export a fresh template. Expected header: ${CSV_EXPECTED_LABEL}.`;
                csvRows.value = [];
                return;
            }
            const missing = CSV_HEADERS.filter((header) => !fields.includes(header));
            const unexpected = fields.filter((field) => field && !CSV_HEADERS.includes(field));
            if (missing.length || unexpected.length || fields.length !== CSV_HEADERS.length) {
                csvError.value = `Invalid CSV header. Expected: ${CSV_EXPECTED_LABEL}.`;
                csvRows.value = [];
                return;
            }
            if (results.errors.length) {
                csvError.value = `Could not parse some rows: ${results.errors[0]?.message ?? 'malformed CSV'}.`;
            }
            const parsed = Array.isArray(results.data) ? results.data : [];
            if (parsed.length > 100) {
                csvError.value = 'Import is limited to 100 rows per file.';
                csvRows.value = [];
                return;
            }
            csvRows.value = parsed.map((raw, index) => validateCsvRow(raw, index));
            if (!csvRows.value.length && !csvError.value) {
                csvError.value = 'No data rows found in this file.';
            }
        },
        error: (parseError) => {
            csvError.value = parseError?.message ?? 'Could not read this file.';
            csvRows.value = [];
        },
    });
}

function csvRowPayload(row) {
    return {
        item_name: row.values.item_name,
        category_id: row.values.category_id,
        unit: row.values.unit,
        quantity: row.values.quantity,
        unit_cost: row.values.unit_cost,
        date_acquired: row.values.date_acquired,
        lifespan_years: row.values.lifespan_years,
        ics_no: row.values.ics_no || null,
        serial_numbers: row.values.serials,
    };
}

async function confirmCsvImport() {
    const valid = validCsvRows.value;
    if (!valid.length || csvBusy.value) {
        return;
    }

    csvBusy.value = true;
    csvError.value = '';
    csvResult.value = null;
    try {
        const { data } = await api.post('/custodian/inventory/stock-in/import', {
            rows: valid.map(csvRowPayload),
        });
        sentCsvRows.value = valid;
        csvResult.value = data ?? null;

        const serverErrors = data?.errors && typeof data.errors === 'object' ? data.errors : {};
        for (const [position, messages] of Object.entries(serverErrors)) {
            const row = sentCsvRows.value[Number(position)];
            if (row) {
                row.serverErrors = flattenMessages(messages);
            }
        }

        // Fully successful imports close immediately like the manual tab;
        // partial results stay open so the per-row errors can be reviewed.
        if ((data?.imported ?? 0) > 0 && (data?.failed ?? 0) === 0) {
            finishCsvImport();
            return;
        }
    } catch (requestError) {
        csvError.value = requestError?.response?.data?.message ?? 'Import could not be completed. Check your connection and try again.';
    } finally {
        csvBusy.value = false;
    }
}

function finishCsvImport() {
    resetCsv();
    emit('saved');
}

function stockInErrorMessage(response, requestError) {
    if (!response) {
        return requestError?.code === 'ERR_NETWORK'
            ? 'Could not reach the server. Check your connection and try again. Your form entries are still here.'
            : 'Stock-in could not be completed. Check your connection and try again.';
    }

    if (response.status === 422) {
        return response.data?.message ?? 'Some submitted details are invalid. Review the form and try again.';
    }
    if (response.status === 403) {
        return response.data?.message ?? 'You do not have permission to stock in inventory.';
    }
    if (response.status === 419) {
        return 'Your session expired. Refresh the page, then try stock-in again.';
    }
    if (response.status === 429) {
        return 'Too many attempts. Wait a moment, then try again.';
    }
    if (response.status >= 500) {
        return 'The server could not save this stock-in. No success was reported; try again or contact an administrator.';
    }

    return response.data?.message ?? `Stock-in could not be completed (HTTP ${response.status}). Review the form and try again.`;
}
</script>
