<template>
  <div class="space-y-4 min-[480px]:space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 min-[480px]:gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white min-[480px]:text-2xl">Stockroom Transactions</h1>
          <span
            v-if="metrics.totalTransactionsCount !== undefined"
            class="rounded-md bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300"
          >

          </span>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 min-[480px]:text-sm">Incoming requests, custody transfers, user returns, and complete stock audit ledger.</p>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
        @click="assignOpen = true"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
        </svg>
        Assign Item
      </button>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <div class="flex items-center gap-3">
        <svg class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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

    <template v-else>
      <!-- Main Transactions Panel -->
      <div class="custodian-panel overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <!-- Interactive Segmented Navigation -->
        <div ref="tabStripRef" class="relative flex shrink-0 flex-nowrap gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50">
          <span
            aria-hidden="true"
            class="pointer-events-none absolute inset-y-2 rounded-md bg-white shadow-sm ring-1 ring-emerald-500/20 motion-safe:transition-all motion-safe:duration-200 dark:bg-gray-800 dark:ring-emerald-500/30"
            :style="pillStyle"
          />
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :data-transaction-tab="tab.key"
            type="button"
            class="relative inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
            :class="
              activeTab === tab.key
                ? 'text-emerald-900 dark:text-emerald-300'
                : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'
            "
            :aria-pressed="activeTab === tab.key"
            @click="activeTab = tab.key"
          >
            <span>{{ tab.label }}</span>
            <span
              :key="`${tab.key}:${tab.count}`"
              class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-bold tabular-nums transition-colors"
              :class="[tabCountBadgeClass(tab.key, activeTab === tab.key), animationsLive && 'tab-badge-pop']"
            >
              {{ format(tab.count) }}
            </span>
          </button>
        </div>

        <!-- Panel Body Loading -->
        <TransactionsSkeleton v-if="loading" :active-tab="activeTab" />

        <Transition name="tab" mode="out-in">
          <div v-if="!loading" :key="activeTab" class="p-2.5 min-[480px]:p-4 sm:p-6">
          <!-- 1. REQUESTS TAB -->
          <template v-if="activeTab === 'requests'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="requestsSearch"
                    type="search"
                    placeholder="Search requester, item, category…"
                    aria-label="Search requests"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <button
                  v-if="requestsSearch"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="requestsSearch = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredIncoming.length + filteredAssignments.length }} of {{ incoming.length + assignments.length }} shown
                </span>
                <a :href="exportPdfHref" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Export PDF
                </a>
              </div>
            </div>

            <!-- Incoming Requests -->
            <div class="mb-5 min-[480px]:mb-8">
              <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2.5 dark:border-white/5 min-[480px]:mb-4 min-[480px]:pb-3">
                <div class="flex min-w-0 items-center gap-1.5 min-[480px]:gap-2">
                  <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-emerald-100 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 min-[480px]:h-6 min-[480px]:w-6 min-[480px]:text-xs">
                    {{ incoming.length }}
                  </span>
                  <h3 class="text-xs font-bold text-gray-800 dark:text-white min-[480px]:text-sm">Incoming Requests Awaiting Custodian Approval</h3>
                </div>
              </div>

              <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
                <table class="w-full min-w-[64rem] text-left text-xs">
                  <thead class="sticky top-0 z-10">
                    <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                      <th class="px-4 py-3.5">Requester</th>
                      <th class="px-4 py-3.5">Item Requested</th>
                      <th class="px-4 py-3.5">Category</th>
                      <th class="px-4 py-3.5 text-center">Qty</th>
                      <th class="px-4 py-3.5">Stock Health</th>
                      <th class="px-4 py-3.5">Requested On</th>
                      <th class="px-4 py-3.5 text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    <tr v-for="request in filteredIncoming" :key="request.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                      <!-- Requester -->
                      <td class="px-4 py-3.5">
                        <div class="flex items-center gap-2.5">
                          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                            {{ (personName(request.user) || 'U').slice(0, 2).toUpperCase() }}
                          </div>
                          <div class="min-w-0">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ personName(request.user) }}</div>
                            <span class="text-xs text-gray-400">{{ request.user?.email || 'No email' }}</span>
                          </div>
                        </div>
                      </td>

                      <!-- Item -->
                      <td class="px-4 py-3.5">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ itemName(request) }}</div>
                        <!-- An item-type request has no inventory record yet, so
                             there is no item number to show. The old placeholder
                             text rendered "New request item" directly under the
                             real name, which read as a second, wrong item. -->
                        <span v-if="request.item?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ request.item.inventory_item_no }}</span>
                      </td>

                      <!-- Category -->
                      <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                        <span class="inline-flex rounded-md bg-gray-100 px-2 py-0.5 font-medium dark:bg-white/5 dark:text-gray-300">
                          {{ categoryLine(request) }}
                        </span>
                      </td>

                      <!-- Qty -->
                      <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                        {{ request.quantity }}
                      </td>

                      <!-- Stock Availability -->
                      <td class="px-4 py-3.5">
                        <span
                          class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold"
                          :class="hasStock(request) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 border border-rose-200/60 dark:bg-rose-500/10 dark:text-rose-300'"
                        >
                          <span class="h-1.5 w-1.5 rounded-md" :class="hasStock(request) ? 'bg-emerald-500' : 'bg-rose-500'" />
                          {{ pendingOf(request) > 0 ? `${freeOf(request)} free (${pendingOf(request)} pending)` : `${stockOf(request)} in stock` }}
                        </span>
                      </td>

                      <!-- Date -->
                      <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDate(request.requested_at) }}
                      </td>

                      <!-- Action Buttons -->
                      <td class="px-4 py-3.5">
                        <div class="flex flex-col items-center justify-center gap-1.5">
                          <template v-if="isAwaitingProcurement(request)">
                            <!-- Recorded demand with nothing to allocate yet. The label is
                                 stock-driven, not status-driven: as soon as the
                                 custodian stocks the item this becomes an
                                 Assign action, because the request is now
                                 fulfillable. -->
                            <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                              Awaiting procurement
                            </span>
                            <button
                              type="button"
                              class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                              @click="openPrompt('decline-request', request.id)"
                            >
                              Decline
                            </button>
                          </template>

                          <template v-else>
                            <button
                              type="button"
                              :disabled="!hasStock(request)"
                              class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                              @click="reviewRequest = request"
                            >
                              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                              </svg>
                              Fulfill
                            </button>

                            <button
                              type="button"
                              class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                              @click="openPrompt('decline-request', request.id)"
                            >
                              Decline
                            </button>
                          </template>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="!filteredIncoming.length">
                      <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                        {{ requestsSearch.trim() ? `No results for '${requestsSearch.trim()}'.` : 'No incoming requests waiting for approval.' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </template>

          <!-- DRAFTS TAB -->
          <template v-else-if="activeTab === 'drafts'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="draftsSearch"
                    type="search"
                    placeholder="Search recipient, item…"
                    aria-label="Search drafts"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <button
                  v-if="draftsSearch"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="draftsSearch = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredDrafts.length }} of {{ drafts.length }} shown
                </span>
              </div>
            </div>

            <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2.5 dark:border-white/5 min-[480px]:mb-4 min-[480px]:pb-3">
              <div class="flex min-w-0 items-center gap-1.5 min-[480px]:gap-2">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-violet-100 text-[10px] font-bold text-violet-700 dark:bg-violet-950 dark:text-violet-300 min-[480px]:h-6 min-[480px]:w-6 min-[480px]:text-xs">
                  {{ drafts.length }}
                </span>
                <h3 class="text-xs font-bold text-gray-800 dark:text-white min-[480px]:text-sm">Draft Slips Awaiting Allocation</h3>
              </div>
              <span class="text-[11px] text-gray-400 dark:text-gray-500">Hold-only — no stock deducted until Allocate.</span>
            </div>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="w-full min-w-[56rem] text-left text-xs">
                <thead class="sticky top-0 z-10">
                  <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <th class="px-4 py-3.5">Recipient</th>
                    <th class="px-4 py-3.5">Item</th>
                    <th class="px-4 py-3.5 text-center">Qty</th>
                    <th class="px-4 py-3.5">Drafted On</th>
                    <th class="px-4 py-3.5 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                  <tr v-for="draft in filteredDrafts" :key="draft.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">
                      {{ personName(draft.target_user ?? draft.targetUser) }}
                    </td>
                    <td class="px-4 py-3.5">
                      <div class="font-medium text-gray-800 dark:text-gray-200">{{ draft.requested_item_name ?? 'Unknown item' }}</div>
                      <span v-if="draftHoldNos(draft)" class="text-xs text-gray-400 font-mono">{{ draftHoldNos(draft) }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                      {{ draft.quantity }}
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(draft.requested_at) }}
                    </td>
                    <td class="px-4 py-3.5">
                      <div class="flex flex-row items-center justify-center gap-1.5 whitespace-nowrap">
                        <a
                          :href="draftSlipHref(draft)"
                          target="_blank"
                          rel="noopener"
                          class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                          Reprint
                        </a>
                        <button
                          type="button"
                          class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                          @click="confirmAction = { kind: 'allocate-draft', id: draft.id, label: `Allocate ${draft.quantity} to ${personName(draft.target_user ?? draft.targetUser)}? Signed slip collected.` }"
                        >
                          Allocate
                        </button>
                        <button
                          type="button"
                          class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                          :disabled="working || confirmAction !== null || prompt !== null"
                          @click="confirmAction = { kind: 'cancel-draft', id: draft.id, label: `Delete draft and release ${draft.quantity} held unit(s)?` }"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="!filteredDrafts.length">
                    <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      {{ draftsSearch.trim() ? `No results for '${draftsSearch.trim()}'.` : 'No draft slips. Print & Draft from Assign to hold stock here.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 2. TRANSFERS TAB -->
          <template v-else-if="activeTab === 'transfers'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="transfersSearch"
                    type="search"
                    placeholder="Search sender, recipient, item…"
                    aria-label="Search transfers"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <button
                  v-if="transfersSearch"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="transfersSearch = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredTransfers.length }} of {{ transfers.length }} shown
                </span>
                <a :href="exportPdfHref" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Export PDF
                </a>
              </div>
            </div>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="w-full min-w-[56rem] text-left text-xs">
                <thead class="sticky top-0 z-10">
                  <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <th class="px-4 py-3.5">Transfer Flow</th>
                    <th class="px-4 py-3.5">Item Details</th>
                    <th class="px-4 py-3.5 text-center">Qty</th>
                    <th class="px-4 py-3.5">Requested At</th>
                    <th class="px-4 py-3.5 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                  <tr v-for="transfer in filteredTransfers" :key="transfer.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <!-- From -> To -->
                    <td class="px-4 py-3.5">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ personName(transfer.user) }}</span>
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ personName(transfer.target_user ?? transfer.targetUser) }}</span>
                      </div>
                    </td>

                    <!-- Item -->
                    <td class="px-4 py-3.5">
                      <div class="font-medium text-gray-800 dark:text-gray-200">{{ transfer.item?.item_name ?? 'Unknown item' }}</div>
                      <span v-if="transfer.item?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ transfer.item.inventory_item_no }}</span>
                    </td>

                    <!-- Qty -->
                    <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                      {{ transfer.quantity }}
                    </td>

                    <!-- Date -->
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(transfer.requested_at) }}
                    </td>

                    <!-- Actions -->
                    <td class="px-4 py-3.5 text-center">
                      <div class="inline-flex items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50"
                          :disabled="working"
                          @click="confirmAction = { kind: 'approve-transfer', id: transfer.id, label: `Approve transfer of ${transfer.item?.item_name ?? 'item'} to ${personName(transfer.target_user ?? transfer.targetUser)}?` }"
                        >
                          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                          Approve
                        </button>

                        <button
                          type="button"
                          class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-rose-950/30"
                          @click="openPrompt('decline-transfer', transfer.id)"
                        >
                          Decline
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="!filteredTransfers.length">
                    <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      {{ transfersSearch.trim() ? `No results for '${transfersSearch.trim()}'.` : 'No pending custody transfers.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 3. RETURNS TAB -->
          <template v-else-if="activeTab === 'returns'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="returnsSearch"
                    type="search"
                    placeholder="Search user, item…"
                    aria-label="Search returns"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <button
                  v-if="returnsSearch"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="returnsSearch = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredReturns.length }} of {{ returns.length }} shown
                </span>
                <a :href="exportPdfHref" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Export PDF
                </a>
              </div>
            </div>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="w-full min-w-[52rem] text-left text-xs">
                <thead class="sticky top-0 z-10">
                  <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <th class="px-4 py-3.5">End User</th>
                    <th class="px-4 py-3.5">Item to Return</th>
                    <th class="px-4 py-3.5 text-center">Qty</th>
                    <th class="px-4 py-3.5">Requested At</th>
                    <th class="px-4 py-3.5 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                  <tr v-for="returnRequest in filteredReturns" :key="returnRequest.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <!-- End user -->
                    <td class="px-4 py-3.5">
                      <div class="font-semibold text-gray-900 dark:text-white">{{ returnRequest.user?.full_name ?? 'Unknown User' }}</div>
                      <span class="text-xs text-gray-400">@{{ returnRequest.user?.username ?? '' }}</span>
                    </td>

                    <!-- Item -->
                    <td class="px-4 py-3.5">
                      <div class="font-medium text-gray-800 dark:text-gray-200">{{ returnRequest.item?.item_name ?? 'Unknown item' }}</div>
                      <span v-if="returnRequest.item?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ returnRequest.item.inventory_item_no }}</span>
                    </td>

                    <!-- Qty -->
                    <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                      {{ returnRequest.quantity }}
                    </td>

                    <!-- Date -->
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(returnRequest.requested_at) }}
                    </td>

                    <!-- Actions -->
                    <td class="px-4 py-3.5 text-center">
                      <div class="inline-flex items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                          @click="openApproveReturn(returnRequest)"
                        >
                          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                          Accept Return
                        </button>

                        <button
                          type="button"
                          class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-rose-950/30"
                          @click="openPrompt('decline-return', returnRequest.id)"
                        >
                          Decline
                        </button>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="!filteredReturns.length">
                    <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      {{ returnsSearch.trim() ? `No results for '${returnsSearch.trim()}'.` : 'No pending item returns.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- IAR APPROVALS TAB -->
          <template v-else-if="activeTab === 'iar-approvals'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ iarApprovals.length }}</span>
                <div>
                  <h3 class="text-sm font-bold text-gray-800 dark:text-white">IARs Awaiting Custodian Approval</h3>
                  <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Submitted inspection and acceptance reports ready for review.</p>
                </div>
              </div>
              <button
                type="button"
                class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                :disabled="iarApprovalLoading"
                @click="loadIarApprovals"
              >
                {{ iarApprovalLoading ? 'Refreshing…' : 'Refresh' }}
              </button>
            </div>

            <p v-if="iarApprovalError" role="alert" class="mb-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
              {{ iarApprovalError }}
              <button type="button" class="ml-2 font-semibold underline" @click="loadIarApprovals">Retry</button>
            </p>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="w-full min-w-[64rem] text-left text-xs">
                <thead class="sticky top-0 z-10">
                  <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                    <th class="px-4 py-3.5">IAR No.</th>
                    <th class="px-4 py-3.5">Supplier</th>
                    <th class="px-4 py-3.5">Invoice No.</th>
                    <th class="px-4 py-3.5">Inspection Date</th>
                    <th class="px-4 py-3.5">Submitted By</th>
                    <th class="px-4 py-3.5 text-center">Item Lines</th>
                    <th class="px-4 py-3.5 text-center">Delivery Qty</th>
                    <th class="px-4 py-3.5 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                  <tr v-if="iarApprovalLoading && !iarApprovals.length">
                    <td colspan="8" class="px-4 py-10 text-center text-xs text-gray-500 dark:text-gray-400">Loading submitted IARs…</td>
                  </tr>
                  <tr v-for="inspection in iarApprovals" :key="inspection.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ inspection.iar_number || '—' }}</td>
                    <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ inspection.supplier_name || '—' }}</td>
                    <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ inspection.invoice_number || '—' }}</td>
                    <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ formatDate(inspection.inspection_date) }}</td>
                    <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ personName(inspection.creator) }}</td>
                    <td class="px-4 py-3.5 text-center text-gray-700 dark:text-gray-300">{{ inspection.items?.length ?? 0 }}</td>
                    <td class="px-4 py-3.5 text-center font-semibold text-gray-900 dark:text-white">{{ iarDeliveryQuantity(inspection) }}</td>
                    <td class="px-4 py-3.5 text-center">
                      <button
                        type="button"
                        class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="iarActionBusy"
                        @click="openIarReview(inspection)"
                      >
                        Review
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!iarApprovalLoading && !iarApprovals.length && !iarApprovalError">
                    <td colspan="8" class="px-4 py-10 text-center text-xs text-gray-500 dark:text-gray-400">
                      No IARs are awaiting approval.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 4. HISTORY TAB -->
          <template v-else-if="activeTab === 'history'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="historySearch"
                    type="search"
                    placeholder="Search by item, person, department…"
                    aria-label="Search transaction history"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                  <label for="historyDateFrom" class="shrink-0">From</label>
                  <input
                    id="historyDateFrom"
                    v-model="historyDateFrom"
                    type="date"
                    aria-label="Filter history from date"
                    class="w-[140px] shrink-0 rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:[color-scheme:dark]"
                  />
                </span>
                <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                  <label for="historyDateTo" class="shrink-0">to</label>
                  <input
                    id="historyDateTo"
                    v-model="historyDateTo"
                    type="date"
                    aria-label="Filter history to date"
                    class="w-[140px] shrink-0 rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:[color-scheme:dark]"
                  />
                </span>
                <button
                  v-if="historySearch || historyDateFrom || historyDateTo"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="historySearch = ''; historyDateFrom = ''; historyDateTo = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredHistory.length }} recorded entries
                </span>
                <a :href="exportPdfHref" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Export PDF
                </a>
              </div>
            </div>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="min-w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400 z-10">
                  <tr>
                    <th class="px-4 py-3.5 text-center">Qty</th>
                    <th class="px-4 py-3.5">Item</th>
                    <th class="px-4 py-3.5">From</th>
                    <th class="px-4 py-3.5">To</th>
                    <th class="px-4 py-3.5">Transaction Date</th>
                    <th class="px-4 py-3.5">Activity Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-white/5 dark:bg-gray-900">
                  <template v-for="group in groupedHistory" :key="group.key">
                  <template v-if="group.count === 1" v-for="transaction in group.entries" :key="transaction.id">
                  <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                      {{ transaction.quantity }}
                    </td>
                    <td class="px-4 py-3.5">
                      <div class="font-medium text-gray-900 dark:text-white">{{ transaction.item?.item_name ?? 'Unknown item' }}</div>
                      <span v-if="transaction.item?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ transaction.item.inventory_item_no }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">
                      {{ relUser(transaction, 'from_user', 'fromUser') ? personName(relUser(transaction, 'from_user', 'fromUser')) : 'Stockroom / Warehouse' }}
                    </td>
                    <td class="px-4 py-3.5">
                      <div class="font-semibold text-gray-900 dark:text-white">{{ transaction.manual_recipient_name ?? personName(transaction.user) }}</div>
                      <span v-if="transaction.manual_department" class="block text-xs text-gray-400">{{ transaction.manual_department }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(transaction.transaction_date) }}
                    </td>
                    <td class="px-4 py-3.5">
                      <StatusBadge :status="transaction.status" />
                    </td>
                  </tr>
                  </template>
                  <template v-else>
                  <!-- Grouped transactions: identical item, parties, date and status -->
                  <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                    <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white" :title="`Sum of ${group.count} records`">
                      {{ group.totalQuantity }}
                    </td>
                    <td class="px-4 py-3.5">
                      <button
                        type="button"
                        class="flex items-center gap-1.5 text-left"
                        :aria-expanded="isHistoryGroupExpanded(group.key)"
                        :title="isHistoryGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                        @click="toggleHistoryGroup(group.key)"
                      >
                        <svg
                          class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                          :class="{ 'rotate-180': isHistoryGroupExpanded(group.key) }"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                        <span>
                          <span class="font-medium text-gray-900 dark:text-white">{{ group.first.item?.item_name ?? 'Unknown item' }}</span>
                          <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                        </span>
                      </button>
                    </td>
                    <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">
                      {{ relUser(group.first, 'from_user', 'fromUser') ? personName(relUser(group.first, 'from_user', 'fromUser')) : 'Stockroom / Warehouse' }}
                    </td>
                    <td class="px-4 py-3.5">
                      <div class="font-semibold text-gray-900 dark:text-white">{{ group.first.manual_recipient_name ?? personName(group.first.user) }}</div>
                      <span v-if="group.first.manual_department" class="block text-xs text-gray-400">{{ group.first.manual_department }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(group.first.transaction_date) }}
                    </td>
                    <td class="px-4 py-3.5">
                      <StatusBadge :status="group.first.status" />
                    </td>
                  </tr>
                  <tr v-if="isHistoryGroupExpanded(group.key)">
                    <td colspan="6" class="bg-gray-50/40 px-4 py-2 dark:bg-white/[0.02]">
                      <ul class="space-y-1">
                        <li v-for="entry in group.entries" :key="entry.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 text-[11px] text-gray-500 dark:text-gray-400">
                          <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ entry.item?.inventory_item_no ?? `#${entry.id}` }}</span>
                          <span class="font-bold text-gray-900 dark:text-white">×{{ entry.quantity }}</span>
                          <span v-if="entry.expected_return_date">due {{ formatDate(entry.expected_return_date) }}</span>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  </template>
                  </template>

                  <tr v-if="!filteredHistory.length">
                    <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      No transactions found matching your criteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 5. AUDIT LEDGER TAB -->
          <template v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full max-w-sm">
                  <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    v-model="ledgerSearch"
                    type="search"
                    placeholder="Search item, movement, actor, details…"
                    aria-label="Search audit ledger"
                    class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                  />
                </div>
                <button
                  v-if="ledgerSearch"
                  type="button"
                  class="rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                  @click="ledgerSearch = ''"
                >
                  Clear
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ filteredLedger.length }} of {{ ledger.length }} shown
                </span>
                <a :href="exportPdfHref" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                  Export PDF
                </a>
              </div>
            </div>

            <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
              <table class="w-full min-w-[64rem] table-fixed text-left text-xs">
                <thead class="sticky top-0 z-10">
                  <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                    <th class="w-32 px-3 py-3.5">Timestamp</th>
                    <th class="w-40 px-3 py-3.5">Item</th>
                    <th class="w-32 px-3 py-3.5">Movement Type</th>
                    <th class="w-20 px-3 py-3.5 text-center">Qty Diff</th>
                    <th class="w-20 px-3 py-3.5 text-center">Stock Before</th>
                    <th class="w-20 px-3 py-3.5 text-center">Stock After</th>
                    <th class="w-36 px-3 py-3.5">Authorized By</th>
                    <th class="w-56 px-3 py-3.5">Movement Details</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                  <template v-for="group in ledgerGroups" :key="group.key">
                  <template v-if="group.count === 1" v-for="entry in group.entries" :key="entry.id">
                  <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <td class="break-words px-3 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(entry.created_at) }}
                    </td>
                    <td class="break-words px-3 py-3.5">
                      <div class="font-medium text-gray-900 dark:text-white">{{ entry.inventory?.item_name ?? 'Unknown item' }}</div>
                      <span v-if="entry.inventory?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ entry.inventory.inventory_item_no }}</span>
                    </td>
                    <td class="break-words px-3 py-3.5">
                      <span
                        class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                        :class="ledgerBadgeClass(entry.movement_type)"
                      >
                        {{ (entry.movement_type ?? '').replace(/_/g, ' ') }}
                      </span>
                    </td>
                    <td class="px-3 py-3.5 text-center font-bold">
                      <span :class="entry.quantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ entry.quantity > 0 ? `+${entry.quantity}` : entry.quantity }}
                      </span>
                    </td>
                    <td class="px-3 py-3.5 text-center font-mono text-xs text-gray-500 dark:text-gray-400">
                      {{ entry.quantity_before }}
                    </td>
                    <td class="px-3 py-3.5 text-center font-mono text-xs font-bold text-gray-900 dark:text-white">
                      {{ entry.quantity_after }}
                    </td>
                    <td class="break-words px-3 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                      {{ entry.user ? personName(entry.user) : 'System Automated' }}
                    </td>
                    <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
                      {{ entry.display_details || entry.notes || 'No additional details recorded.' }}
                    </td>
                  </tr>
                  </template>
                  <template v-else>
                  <!-- Grouped records: identical item, type, minute, actor and details -->
                  <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                    <td class="break-words px-3 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDate(group.first.created_at) }}
                    </td>
                    <td class="break-words px-3 py-3.5">
                      <button
                        type="button"
                        class="flex items-center gap-1.5 text-left"
                        :aria-expanded="isLedgerGroupExpanded(group.key)"
                        :title="isLedgerGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                        @click="toggleLedgerGroup(group.key)"
                      >
                        <svg
                          class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                          :class="{ 'rotate-180': isLedgerGroupExpanded(group.key) }"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                        <span>
                          <span class="font-medium text-gray-900 dark:text-white">{{ group.first.inventory?.item_name ?? 'Unknown item' }}</span>
                          <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                        </span>
                      </button>
                    </td>
                    <td class="break-words px-3 py-3.5">
                      <span
                        class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                        :class="ledgerBadgeClass(group.first.movement_type)"
                      >
                        {{ (group.first.movement_type ?? '').replace(/_/g, ' ') }}
                      </span>
                    </td>
                    <td class="px-3 py-3.5 text-center font-bold" :title="`Sum of ${group.count} records`">
                      <span :class="group.totalQuantity >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ group.totalQuantity > 0 ? `+${group.totalQuantity}` : group.totalQuantity }}
                      </span>
                    </td>
                    <td class="px-3 py-3.5 text-center font-mono text-xs text-gray-500 dark:text-gray-400" title="Oldest record's stock before">
                      {{ group.rangeBefore }}
                    </td>
                    <td class="px-3 py-3.5 text-center font-mono text-xs font-bold text-gray-900 dark:text-white" title="Newest record's stock after">
                      {{ group.rangeAfter }}
                    </td>
                    <td class="break-words px-3 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                      {{ group.first.user ? personName(group.first.user) : 'System Automated' }}
                    </td>
                    <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
                      {{ group.first.display_details || group.first.notes || 'No additional details recorded.' }}
                    </td>
                  </tr>
                  <tr v-if="isLedgerGroupExpanded(group.key)">
                    <td colspan="8" class="bg-gray-50/40 px-3 py-2 dark:bg-white/[0.02]">
                      <ul class="space-y-1">
                        <li v-for="entry in group.entries" :key="entry.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 font-mono text-[11px] text-gray-500 dark:text-gray-400">
                          <span class="font-semibold text-gray-700 dark:text-gray-300">{{ entry.inventory?.inventory_item_no ?? `#${entry.id}` }}</span>
                          <span :class="Number(entry.quantity ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                            {{ Number(entry.quantity ?? 0) > 0 ? `+${entry.quantity}` : entry.quantity }}
                          </span>
                          <span>{{ entry.quantity_before }} → {{ entry.quantity_after }}</span>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  </template>
                  </template>

                  <tr v-if="!filteredLedger.length">
                    <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      {{ ledgerSearch.trim() ? `No results for '${ledgerSearch.trim()}'.` : 'No stock movement audit records yet.' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          </div>
        </Transition>
      </div>
    </template>

    <!-- Modals -->
    <AssignModal :open="assignOpen" :items="availableItems" :end-users="endUsers" @close="assignOpen = false" @saved="onAssignSaved" @drafted="onDraftSaved" />
    <ApproveRequestModal :open="reviewRequest !== null" :request="reviewRequest" @close="reviewRequest = null" @approved="onApproveSaved" />

    <Modal
      :open="iarReview !== null"
      title="Review Inspection and Acceptance Report"
      subtitle="Verify the IAR and delivered item details before signing your decision."
      max-width="max-w-4xl"
      @close="closeIarReview"
    >
      <div v-if="iarReview" class="max-h-[65vh] space-y-4 overflow-y-auto pr-1">
        <dl class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-2 lg:grid-cols-3">
          <div><dt class="text-gray-500 dark:text-gray-400">IAR No.</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ iarReview.iar_number || '—' }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Supplier</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ iarReview.supplier_name || '—' }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Contract</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ iarReview.contract_title || '—' }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Invoice</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ iarReview.invoice_number || '—' }} · {{ formatDate(iarReview.invoice_date) }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Inspection Date</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ formatDate(iarReview.inspection_date) }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Submitted By</dt><dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ personName(iarReview.creator) }}</dd></div>
          <div><dt class="text-gray-500 dark:text-gray-400">Acceptance</dt><dd class="mt-1 font-semibold capitalize text-gray-900 dark:text-white">{{ iarReview.acceptance_status || '—' }}</dd></div>
        </dl>

        <div class="overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
          <table class="w-full min-w-[36rem] text-left text-xs">
            <thead class="bg-gray-50 text-[11px] font-bold uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
              <tr>
                <th class="px-3 py-2.5">Stock / Property No.</th>
                <th class="px-3 py-2.5">Description</th>
                <th class="px-3 py-2.5">Unit</th>
                <th class="px-3 py-2.5 text-right">Delivered Qty</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in iarReview.items" :key="item.id">
                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ item.stock_property_number || '—' }}</td>
                <td class="px-3 py-2.5 text-gray-900 dark:text-white">{{ item.description || '—' }}</td>
                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ item.unit || '—' }}</td>
                <td class="px-3 py-2.5 text-right font-semibold text-gray-900 dark:text-white">{{ item.quantity ?? '—' }}</td>
              </tr>
              <tr v-if="!iarReview.items?.length">
                <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No item lines were submitted.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="iarReview.acceptance_remarks" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
          <strong>Inspection remarks:</strong> {{ iarReview.acceptance_remarks }}
        </p>
        <p v-if="iarActionError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">{{ iarActionError }}</p>

        <div class="space-y-2 rounded-md border border-gray-200 p-3 dark:border-gray-700">
          <label for="iar-approval-signature" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Custodian electronic signature</label>
          <input id="iar-approval-signature" :value="auth.displayName" readonly class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <label class="flex items-start gap-2 text-xs text-gray-700 dark:text-gray-300">
            <input v-model="iarSignatureConfirmed" type="checkbox" class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
            <span>I reviewed the delivery and IAR details and approve them under my account. I understand this approval is recorded with my name and timestamp.</span>
          </label>
        </div>
      </div>
      <template #footer>
        <div class="flex flex-wrap justify-between gap-2">
          <button type="button" class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/30" :disabled="iarActionBusy" @click="openIarReturn">
            Return for correction
          </button>
          <div class="flex gap-2">
            <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" :disabled="iarActionBusy" @click="closeIarReview">Cancel</button>
            <button type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="iarActionBusy || !iarSignatureConfirmed || !auth.displayName" @click="approveIar">
              {{ iarActionBusy ? 'Recording…' : 'Approve and sign' }}
            </button>
          </div>
        </div>
      </template>
    </Modal>

    <PromptModal
      :open="prompt !== null"
      :title="promptConfig.title"
      :subtitle="promptConfig.subtitle"
      :label="promptConfig.label"
      :required="promptConfig.required"
      :confirm-label="promptConfig.confirmLabel"
      :danger="promptConfig.danger"
      :busy="working"
      :busy-label="promptConfig.busyLabel"
      @cancel="cancelPrompt"
      @submit="submitPrompt"
    />

    <ConfirmDialog
      :open="confirmAction !== null"
      :title="confirmAction?.kind === 'cancel-draft' ? 'Delete Draft' : 'Confirm Approval'"
      :message="confirmAction?.label ?? ''"
      :confirm-label="confirmAction?.kind === 'cancel-draft' ? 'Yes' : 'Confirm Approval'"
      :busy="working"
      @cancel="confirmAction = null"
      @confirm="submitConfirm"
    />

    <!-- Approve Return Storage Location Modal -->
    <Modal :open="approveReturn !== null" title="Approve Item Return" subtitle="Accept item back into stockroom custody." max-width="max-w-md" @close="approveReturn = null">
      <div v-if="approveReturn" class="space-y-4">
        <div class="space-y-2 rounded-md bg-gray-50 p-4 text-sm dark:bg-white/5 border border-gray-100 dark:border-white/5">
          <div class="flex justify-between"><span class="text-gray-500">End User:</span><span class="font-semibold text-gray-900 dark:text-white">{{ approveReturn.user?.full_name ?? 'Unknown' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Item:</span><span class="font-semibold text-gray-900 dark:text-white">{{ approveReturn.item?.item_name ?? 'Unknown' }}</span></div>
          <div class="flex justify-between"><span class="text-gray-500">Quantity:</span><span class="font-bold text-emerald-600 dark:text-emerald-400">{{ approveReturn.quantity }}</span></div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300" for="return-building">Storage Building</label>
            <input id="return-building" v-model="returnLocation.building" type="text" maxlength="255" placeholder="e.g. Science Wing" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300" for="return-room">Storage Room</label>
            <input id="return-room" v-model="returnLocation.room" type="text" maxlength="255" placeholder="e.g. Room 102" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          </div>
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="approveReturn = null">Cancel</button>
          <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50" :disabled="working" @click="submitApproveReturn">
            {{ working ? 'Working…' : 'Confirm & Accept' }}
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { groupLedgerEntries } from '../../lib/auditLedger';
import { useToastStore } from '../../stores/toast';
import TransactionsSkeleton from '../../components/ui/skeletons/TransactionsSkeleton.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import PromptModal from '../../components/ui/dialogs/PromptModal.vue';
import AssignModal from './AssignModal.vue';
import ApproveRequestModal from './ApproveRequestModal.vue';

const toast = useToastStore();
const auth = useAuthStore();

const activeTab = ref('requests');

// Sliding active-tab pill, measured from the rendered button. Writes are
// guarded so render never feeds back into reactive state.
const tabStripRef = ref(null);
const pillStyle = ref({ opacity: '0' });
const animationsLive = ref(false);

function updatePill() {
  const strip = tabStripRef.value;
  if (!strip) return;
  const active = strip.querySelector(`[data-transaction-tab="${activeTab.value}"]`);
  const next = !active
    ? { opacity: '0' }
    : {
      opacity: '1',
      left: `${active.offsetLeft}px`,
      width: `${active.offsetWidth}px`,
    };
  const prev = pillStyle.value;
  if (prev.opacity === next.opacity && prev.left === next.left && prev.width === next.width) {
    return;
  }
  pillStyle.value = next;
}

let pillObserver = null;

onMounted(() => {
  nextTick(updatePill);
  if (typeof ResizeObserver !== 'undefined' && tabStripRef.value) {
    pillObserver = new ResizeObserver(() => updatePill());
    pillObserver.observe(tabStripRef.value);
  } else {
    window.addEventListener('resize', updatePill);
  }
});

onUnmounted(() => {
  pillObserver?.disconnect();
  window.removeEventListener('resize', updatePill);
});

watch(activeTab, () => {
  animationsLive.value = true;
  nextTick(updatePill);
if (activeTab.value === 'iar-approvals') {
  loadIarApprovals();
}
});
const loading = ref(true);
const error = ref('');
const working = ref(false);
const historySearch = ref('');
const historyDateFrom = ref('');
const historyDateTo = ref('');
const requestsSearch = ref('');
const draftsSearch = ref('');
const transfersSearch = ref('');
const returnsSearch = ref('');
const ledgerSearch = ref('');

const assignOpen = ref(false);
const reviewRequest = ref(null);
const prompt = ref(null);
const confirmAction = ref(null);
const pendingConfirm = ref(null);
const approveReturn = ref(null);
const returnLocation = ref({ building: '', room: '' });

const metrics = ref({});
const availableItems = ref([]);
const endUsers = ref([]);
const incoming = ref([]);
const assignments = ref([]);
const drafts = ref([]);
const transfers = ref([]);
const returns = ref([]);
const history = ref([]);
const ledger = ref([]);
const iarApprovals = ref([]);
const iarApprovalLoading = ref(false);
const iarApprovalError = ref('');
const iarReview = ref(null);
const iarSignatureConfirmed = ref(false);
const iarActionBusy = ref(false);
const iarActionError = ref('');

const tabs = computed(() => [
  { key: 'requests', label: 'Requests', count: incoming.value.length + assignments.value.length },
  { key: 'drafts', label: 'Drafts', count: drafts.value.length },
  { key: 'transfers', label: 'Transfers', count: transfers.value.length },
  { key: 'returns', label: 'Returns', count: returns.value.length },
  { key: 'iar-approvals', label: 'IAR Approvals', count: iarApprovals.value.length },
  { key: 'history', label: 'History', count: history.value.length },
  { key: 'ledger', label: 'Audit Ledger', count: ledger.value.length },
]);

// Per-tab filtered PDF export: same search (and History dates) as the screen table.
const exportPdfHref = computed(() => {
  const params = new URLSearchParams({ tab: activeTab.value });
  const searchByTab = {
    requests: requestsSearch.value,
    drafts: draftsSearch.value,
    transfers: transfersSearch.value,
    returns: returnsSearch.value,
    history: historySearch.value,
    ledger: ledgerSearch.value,
  };
  const query = String(searchByTab[activeTab.value] ?? '').trim();
  if (query) {
    params.set('search', query);
  }
  if (activeTab.value === 'history') {
    if (historyDateFrom.value) {
      params.set('date_from', String(historyDateFrom.value).slice(0, 10));
    }
    if (historyDateTo.value) {
      params.set('date_to', String(historyDateTo.value).slice(0, 10));
    }
  }
  return `/property-custodian/transactions/export-pdf?${params.toString()}`;
});

function tabCountBadgeClass(key, isActive) {
  if (isActive) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
  }
  switch (key) {
    case 'requests':
      return incoming.value.length > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'transfers':
      return transfers.value.length > 0 ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'drafts':
      return drafts.value.length > 0 ? 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'returns':
      return returns.value.length > 0 ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'iar-approvals':
      return iarApprovals.value.length > 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    default:
      return 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
  }
}

function ledgerBadgeClass(type) {
  const t = String(type ?? '').toLowerCase();
  if (t.includes('stock_in') || t.includes('acquired')) {
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
  }
  if (t.includes('assignment') || t.includes('issue')) {
    return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300';
  }
  if (t.includes('return')) {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
  }
  if (t.includes('transfer')) {
    return 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300';
  }
  if (t.includes('dispose') || t.includes('lost') || t.includes('damage')) {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
  }
  return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
}

// Display-only grouping for the audit ledger, shared with the school-head
// audit view through lib/auditLedger so both pages group by exactly the same
// rule. Adjacent entries identical in every audited dimension collapse into
// one expandable group row; the raw entries are untouched.
const expandedLedgerGroups = ref(new Set());

const ledgerGroups = computed(() => groupLedgerEntries(
  filteredLedger.value,
  (entry) => (entry.user ? personName(entry.user) : 'System Automated'),
));

function isLedgerGroupExpanded(key) {
  return expandedLedgerGroups.value.has(key);
}

function toggleLedgerGroup(key) {
  if (expandedLedgerGroups.value.has(key)) {
    expandedLedgerGroups.value.delete(key);
  } else {
    expandedLedgerGroups.value.add(key);
  }
}

// Display-only grouping for the History tab, mirroring the audit ledger.
// Adjacent transactions identical in item, quantity, parties, date, status and
// expected return date collapse into one expandable row. Manual returns are
// recorded from the Inventory Assigned workspace, so this tab stays read-only.
const expandedHistoryGroups = ref(new Set());

function historyGroupKey(transaction) {
  return [
    transaction.item?.item_name ?? 'Unknown item',
    Number(transaction.quantity ?? 0),
    transaction.from_user ?? transaction.fromUser ? personName(transaction.from_user ?? transaction.fromUser) : 'Stockroom / Warehouse',
    transaction.manual_recipient_name ?? personName(transaction.user),
    transaction.manual_department ?? '',
    String(transaction.transaction_date ?? '').slice(0, 16),
    transaction.status ?? '',
    transaction.expected_return_date ?? '',
  ].join('|');
}

const groupedHistory = computed(() => {
  const groups = [];
  filteredHistory.value.forEach((transaction, index) => {
    const key = historyGroupKey(transaction);
    const current = groups[groups.length - 1];
    if (current && current.baseKey === key) {
      current.entries.push(transaction);
      return;
    }
    groups.push({ key: `${index}:${key}`, baseKey: key, entries: [transaction] });
  });
  return groups.map((group) => ({
    ...group,
    count: group.entries.length,
    totalQuantity: group.entries.reduce((sum, transaction) => sum + Number(transaction.quantity ?? 0), 0),
    first: group.entries[0],
  }));
});

function isHistoryGroupExpanded(key) {
  return expandedHistoryGroups.value.has(key);
}

function toggleHistoryGroup(key) {
  if (expandedHistoryGroups.value.has(key)) {
    expandedHistoryGroups.value.delete(key);
  } else {
    expandedHistoryGroups.value.add(key);
  }
}

const filteredHistory = computed(() => {
  const query = historySearch.value.toLowerCase().trim();
  const from = historyDateFrom.value ? String(historyDateFrom.value).slice(0, 10) : '';
  const to = historyDateTo.value ? String(historyDateTo.value).slice(0, 10) : '';
  if (!query && !from && !to) {
    return history.value;
  }
  return history.value.filter((transaction) => {
    const day = String(transaction.transaction_date ?? '').slice(0, 10);
    if (from && (!day || day < from)) {
      return false;
    }
    if (to && (!day || day > to)) {
      return false;
    }
    if (!query) {
      return true;
    }
    return [
      transaction.quantity,
      transaction.item?.item_name,
      transaction.item?.inventory_item_no,
      transaction.from_user ?? transaction.fromUser ? personName(transaction.from_user ?? transaction.fromUser) : 'Warehouse',
      transaction.manual_recipient_name ?? personName(transaction.user),
      transaction.manual_department,
      transaction.transaction_date,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });
});

const promptConfig = computed(() => {
  switch (prompt.value?.kind) {
    case 'decline-request':
      return { title: 'Decline Request', subtitle: 'Reject this incoming item request.', label: 'Reason / Notes (Optional)', required: false, confirmLabel: 'Confirm Decline', danger: true };
    case 'decline-transfer':
      return { title: 'Decline Transfer', subtitle: 'Provide a reason for declining this transfer.', label: 'Reason / Notes (Optional)', required: false, confirmLabel: 'Confirm Decline', danger: true };
    case 'decline-return':
      return { title: 'Decline Return', subtitle: "Reject end user's return request.", label: 'Reason / Notes (Optional)', required: false, confirmLabel: 'Confirm Decline', danger: true };
    case 'manual-return':
      return { title: 'Record Manual Return', subtitle: 'Return the manually issued item back to stockroom inventory.', label: 'Notes (optional)', required: false, confirmLabel: 'Confirm Return', danger: false };
    case 'cancel-draft':
      return { title: 'Delete Draft', subtitle: 'Release the held units back to free stock.', label: 'Reason (Required)', required: true, confirmLabel: 'Delete Draft', busyLabel: 'Deleting…', danger: true };
    case 'return-iar':
      return { title: 'Return IAR for Correction', subtitle: 'The Inspector will see this reason and can correct and resubmit the report.', label: 'Correction reason (Required)', required: true, confirmLabel: 'Return to Inspector', danger: true };
    default:
      return { title: '', subtitle: '', label: 'Notes', required: false, confirmLabel: 'Confirm', danger: false };
  }
});

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

// Client-side, case-insensitive match across the given display values.
// Pure computed filtering: no extra API calls.
function matchesSearch(query, values) {
  const q = String(query ?? '').toLowerCase().trim();
  if (!q) {
    return true;
  }
  return values
    .filter((value) => value !== null && value !== undefined && value !== '')
    .some((value) => String(value).toLowerCase().includes(q));
}

function requestSearchValues(request) {
  return [
    personName(request.user),
    request.user?.email,
    request.user?.username,
    itemName(request),
    request.item?.inventory_item_no,
    categoryLine(request),
    request.requested_category?.category_name,
    request.quantity,
    request.status,
    request.notes,
    request.requested_at,
  ];
}

const filteredIncoming = computed(() =>
  incoming.value.filter((request) => matchesSearch(requestsSearch.value, requestSearchValues(request))),
);

const filteredAssignments = computed(() =>
  assignments.value.filter((assignment) => matchesSearch(requestsSearch.value, [
    personName(assignment.target_user ?? assignment.targetUser),
    assignment.item?.item_name,
    assignment.item?.inventory_item_no,
    assignment.quantity,
    assignment.status,
    assignment.requested_at,
  ])),
);

function draftChildren(draft) {
  return draft?.fulfillment_requests ?? draft?.fulfillmentRequests ?? [];
}

function draftHoldNos(draft) {
  return draftChildren(draft)
    .map((child) => child.item?.inventory_item_no ?? (child.item_id ? `Inventory #${child.item_id}` : ''))
    .filter(Boolean)
    .join(', ');
}

function draftSlipHref(draft) {
  const params = new URLSearchParams({ preview: '1' });
  draftChildren(draft).forEach((child) => {
    if (child.item_id) {
      params.append('inventory_ids[]', String(child.item_id));
    }
  });
  return `/property-custodian/requests/${draft.id}/slip-pdf?${params.toString()}`;
}

const filteredDrafts = computed(() =>
  drafts.value.filter((draft) => matchesSearch(draftsSearch.value, [
    personName(draft.target_user ?? draft.targetUser),
    draft.requested_item_name,
    draftHoldNos(draft),
    draft.quantity,
    draft.status,
    draft.requested_at,
  ])),
);

const filteredTransfers = computed(() =>
  transfers.value.filter((transfer) => matchesSearch(transfersSearch.value, [
    personName(transfer.user),
    personName(transfer.target_user ?? transfer.targetUser),
    transfer.item?.item_name,
    transfer.item?.inventory_item_no,
    transfer.quantity,
    transfer.status,
    transfer.notes,
    transfer.requested_at,
  ])),
);

const filteredReturns = computed(() =>
  returns.value.filter((returnRequest) => matchesSearch(returnsSearch.value, [
    returnRequest.user?.full_name,
    personName(returnRequest.user),
    returnRequest.user?.username,
    returnRequest.item?.item_name,
    returnRequest.item?.inventory_item_no,
    returnRequest.quantity,
    returnRequest.status,
    returnRequest.notes,
    returnRequest.requested_at,
  ])),
);

const filteredLedger = computed(() =>
  ledger.value.filter((entry) => matchesSearch(ledgerSearch.value, [
    entry.inventory?.item_name,
    entry.inventory?.inventory_item_no,
    entry.movement_type,
    entry.quantity,
    entry.quantity_before,
    entry.quantity_after,
    entry.user ? personName(entry.user) : 'System Automated',
    entry.display_details,
    entry.notes,
    entry.created_at,
  ])),
);

function personName(user) {
  if (!user) {
    return 'Unknown';
  }
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

// Eloquent snake_cases relation names in JSON (`targetUser` arrives as
// `target_user`), so read the snake key first with a camel fallback.
function relUser(obj, snakeKey, camelKey) {
  return obj?.[snakeKey] ?? obj?.[camelKey] ?? null;
}

function formatDate(value) {
  if (!value) {
    return 'N/A';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function itemName(request) {
  return request.requested_item_name ?? request.item?.item_name ?? 'Unknown item';
}

function categoryLine(request) {
  // Relation keys are snake_cased by Laravel on serialization, so this is
  // `requested_category`, never `requestedCategory`. Reading the camelCase name
  // silently returned undefined and fell through to 'General' on every
  // item-type request.
  const category = request.requested_category?.category_name ?? request.item?.category?.category_name ?? 'General';
  const unit = request.requested_unit ?? request.item?.unit ?? '';
  return unit ? `${category} · ${unit}` : category;
}

function stockOf(request) {
  return Number(request.total_available_stock ?? request.item?.quantity ?? 0);
}

function pendingOf(request) {
  return Number(request.pending_hold_stock ?? 0);
}

function freeOf(request) {
  if (request.total_free_stock !== undefined && request.total_free_stock !== null) {
    return Number(request.total_free_stock);
  }
  return Math.max(0, stockOf(request) - pendingOf(request));
}

function hasStock(request) {
  return freeOf(request) >= Number(request.quantity);
}

// Unmet demand: submitted when the item had zero stock. Mirrors
// AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT.
function isUnmet(request) {
  return String(request.status ?? '').trim().toLowerCase() === 'waiting for procurement';
}

// Whether the row still has nothing to allocate. An unmet request is not a
// permanent state: once the custodian stocks the item, the same request becomes
// fulfillable and must offer Assign. Gating on status alone would strand it as
// "Awaiting procurement" forever, which was the point of recording it.
function isAwaitingProcurement(request) {
  return isUnmet(request) && !hasStock(request);
}

function openPrompt(kind, id) {
  prompt.value = { kind, id };
}

function cancelPrompt() {
  if (prompt.value?.kind === 'cancel-draft' && pendingConfirm.value) {
    confirmAction.value = pendingConfirm.value;
  }
  pendingConfirm.value = null;
  prompt.value = null;
}

function openApproveReturn(returnRequest) {
  approveReturn.value = returnRequest;
  returnLocation.value = { building: returnRequest.building ?? '', room: returnRequest.room ?? '' };
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'custodian-transactions',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/custodian/transactions');
      return data;
    },
    applyData: applyTransactionsPayload,
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load transactions.';
    },
  });
}

async function loadIarApprovals() {
  iarApprovalLoading.value = true;
  iarApprovalError.value = '';
  try {
    const { data } = await api.get('/custodian/arrival-inspections/pending-approval');
    iarApprovals.value = data.inspections ?? [];
  } catch (requestError) {
    iarApprovalError.value = requestError?.response?.data?.message ?? 'Could not load IARs awaiting approval.';
  } finally {
    iarApprovalLoading.value = false;
  }
}

function applyTransactionsPayload(data) {
  metrics.value = {
    totalAssignedCount: data.totalAssignedCount,
    pendingRequestsCount: data.pendingRequestsCount,
    pendingTransfersCount: data.pendingTransfersCount,
    pendingReturnsCount: data.pendingReturnsCount,
    totalTransactionsCount: data.totalTransactionsCount,
    overdueReturnsCount: data.overdueReturnsCount,
  };
  availableItems.value = data.availableInventoryItems ?? [];
  endUsers.value = data.endUsers ?? [];
  incoming.value = data.incomingRequests ?? [];
  assignments.value = data.assignmentRequests ?? [];
  drafts.value = data.draftRequests ?? [];
  transfers.value = data.pendingTransfers ?? [];
  returns.value = data.pendingReturns ?? [];
  history.value = data.transactions ?? [];
  ledger.value = data.auditLedger ?? [];
}

function iarDeliveryQuantity(inspection) {
  return (inspection.items ?? []).reduce((total, item) => total + Number(item.quantity ?? 0), 0);
}

function openIarReview(inspection) {
  iarReview.value = inspection;
  iarSignatureConfirmed.value = false;
  iarActionError.value = '';
}

function closeIarReview() {
  if (iarActionBusy.value) return;
  iarReview.value = null;
  iarSignatureConfirmed.value = false;
  iarActionError.value = '';
}

function openIarReturn() {
  if (!iarReview.value) return;
  prompt.value = { kind: 'return-iar', id: iarReview.value.id };
}

async function approveIar() {
  if (!iarReview.value || !iarSignatureConfirmed.value || !auth.displayName || iarActionBusy.value) return;

  iarActionBusy.value = true;
  iarActionError.value = '';
  try {
    const { data } = await api.post(`/custodian/arrival-inspections/${iarReview.value.id}/approve`, {
      signature_name: auth.displayName,
      confirmation: true,
    }, { skipToast: true });
    toast.success('IAR approved', data.message ?? 'The IAR was approved and signed.');
    iarReview.value = null;
    iarSignatureConfirmed.value = false;
    await loadIarApprovals();
  } catch (requestError) {
    iarActionError.value = requestError?.response?.data?.errors?.signature_name?.[0]
      ?? requestError?.response?.data?.message
      ?? 'Could not approve this IAR.';
  } finally {
    iarActionBusy.value = false;
  }
}

function reloadAfterChange() {
  forgetPageCache();
  return load();
}

function onAssignSaved() {
  assignOpen.value = false;
  reloadAfterChange();
}

function onDraftSaved() {
  assignOpen.value = false;
  activeTab.value = 'drafts';
  reloadAfterChange();
}

function onApproveSaved() {
  reviewRequest.value = null;
  reloadAfterChange();
}

async function runMutation(call) {
  working.value = true;

  try {
    await call();
    await reloadAfterChange();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'The action could not be completed.');
    }
  } finally {
    working.value = false;
  }
}

async function submitPrompt(text) {
  const target = prompt.value;
  if (!target) {
    return;
  }

  const routes = {
    'decline-request': [(id) => `/custodian/requests/${id}/decline`, { notes: text || null }],
    'decline-transfer': [(id) => `/custodian/transfers/${id}/decline`, { notes: text || null }],
    'decline-return': [(id) => `/custodian/returns/${id}/decline`, { notes: text || null }],
    'manual-return': [(id) => `/custodian/transactions/${id}/manual-return`, { notes: text || null }],
    'cancel-draft': [(id) => `/custodian/requests/${id}/cancel`, { cancellation_reason: text }],
  };

  if (target.kind === 'return-iar') {
    working.value = true;
    try {
      const { data } = await api.post(`/custodian/arrival-inspections/${target.id}/return`, { reason: text }, { skipToast: true });
      prompt.value = null;
      iarReview.value = null;
      iarSignatureConfirmed.value = false;
      toast.success('IAR returned', data.message ?? 'The IAR was returned to the Inspector.');
      await loadIarApprovals();
    } catch (requestError) {
      toast.error('Could not return IAR', requestError?.response?.data?.message ?? 'Please try again.');
    } finally {
      working.value = false;
    }
    return;
  }

  if (target.kind === 'cancel-draft') {
    const [urlFor, payload] = routes[target.kind];
    pendingConfirm.value = null;
    await runMutation(() => api.post(urlFor(target.id), payload));
    prompt.value = null;
    return;
  }

  prompt.value = null;
  pendingConfirm.value = null;

  const [urlFor, payload] = routes[target.kind];
  await runMutation(() => api.post(urlFor(target.id), payload));
}

async function submitConfirm() {
  const action = confirmAction.value;
  if (!action) {
    return;
  }

  if (action.kind === 'cancel-draft') {
    pendingConfirm.value = action;
    confirmAction.value = null;
    prompt.value = { kind: 'cancel-draft', id: action.id };
    return;
  }

  const urls = {
    'approve-transfer': `/custodian/transfers/${action.id}/approve`,
    'allocate-draft': `/custodian/drafts/${action.id}/allocate`,
  };

  confirmAction.value = null;
  await runMutation(() => api.post(urls[action.kind]));
}

async function submitApproveReturn() {
  const target = approveReturn.value;
  if (!target) {
    return;
  }

  approveReturn.value = null;
  await runMutation(() =>
    api.post(`/custodian/returns/${target.id}/approve`, {
      building: returnLocation.value.building || null,
      room: returnLocation.value.room || null,
    }),
  );
}

onMounted(() => {
  load();
  loadIarApprovals();
});
</script>

<style scoped>
.tab-enter-active {
  transition: opacity 0.18s ease-out, transform 0.18s ease-out;
}
.tab-leave-active {
  transition: opacity 0.15s ease-in, transform 0.15s ease-in;
}
.tab-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.tab-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@keyframes tab-badge-pop {
  0% { transform: scale(1); }
  40% { transform: scale(1.25); }
  100% { transform: scale(1); }
}
.tab-badge-pop {
  animation: tab-badge-pop 0.15s ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .tab-enter-active,
  .tab-leave-active {
    transition: none;
  }
  .tab-badge-pop {
    animation: none;
  }
}
</style>
