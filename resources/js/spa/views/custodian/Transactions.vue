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
        <div class="grid grid-cols-3 gap-1 border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50 min-[480px]:flex min-[480px]:flex-wrap min-[480px]:gap-1.5 min-[480px]:p-2.5">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="group inline-flex min-w-0 items-center justify-between gap-1 rounded-md px-1.5 py-1.5 text-[10px] font-semibold transition-all min-[480px]:w-auto min-[480px]:justify-start min-[480px]:gap-2 min-[480px]:px-4 min-[480px]:py-2 min-[480px]:text-xs"
            :class="
              activeTab === tab.key
                ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
                : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'
            "
            :aria-pressed="activeTab === tab.key"
            @click="activeTab = tab.key"
          >
            <span>{{ tab.label }}</span>
            <span
              class="shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-bold transition-colors min-[480px]:px-2 min-[480px]:text-[10px]"
              :class="tabCountBadgeClass(tab.key, activeTab === tab.key)"
            >
              {{ format(tab.count) }}
            </span>
          </button>
        </div>

        <!-- Panel Body Loading -->
        <TransactionsSkeleton v-if="loading" :active-tab="activeTab" />

        <div v-else class="p-2.5 min-[480px]:p-4 sm:p-6">
          <!-- 1. REQUESTS TAB -->
          <template v-if="activeTab === 'requests'">
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
                    <tr v-for="request in incoming" :key="request.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
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
                              {{ isUnmet(request) ? 'Assign' : 'Open' }}
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

                    <tr v-if="!incoming.length">
                      <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                        No incoming requests waiting for approval.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Assignments Awaiting End Users -->
            <div>
              <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2.5 dark:border-white/5 min-[480px]:mb-4 min-[480px]:pb-3">
              <div class="flex min-w-0 items-center gap-1.5 min-[480px]:gap-2">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-amber-100 text-[10px] font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300 min-[480px]:h-6 min-[480px]:w-6 min-[480px]:text-xs">
                    {{ assignments.length }}
                  </span>
                  <h3 class="text-xs font-bold text-gray-800 dark:text-white min-[480px]:text-sm">Assignments Awaiting End-User Acceptance</h3>
                </div>
              </div>

              <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200/70 dark:border-gray-800">
                <table class="w-full min-w-[48rem] text-left text-xs">
                  <thead class="sticky top-0 z-10">
                    <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                      <th class="px-4 py-3.5">Recipient</th>
                      <th class="px-4 py-3.5">Item</th>
                      <th class="px-4 py-3.5 text-center">Quantity</th>
                      <th class="px-4 py-3.5">Issued Date</th>
                      <th class="px-4 py-3.5">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    <tr v-for="assignment in assignments" :key="assignment.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                      <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">
                        {{ personName(assignment.targetUser) }}
                      </td>
                      <td class="px-4 py-3.5">
                        <div class="font-medium text-gray-800 dark:text-gray-200">{{ assignment.item?.item_name ?? 'Unknown item' }}</div>
                        <span v-if="assignment.item?.inventory_item_no" class="text-xs text-gray-400 font-mono">{{ assignment.item.inventory_item_no }}</span>
                      </td>
                      <td class="px-4 py-3.5 text-center font-bold text-gray-900 dark:text-white">
                        {{ assignment.quantity }}
                      </td>
                      <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDate(assignment.requested_at) }}
                      </td>
                      <td class="px-4 py-3.5">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 border border-amber-200/60 dark:bg-amber-950/40 dark:text-amber-300">
                          <span class="h-1.5 w-1.5 rounded-md bg-amber-500 animate-pulse" />
                          Waiting for End User
                        </span>
                      </td>
                    </tr>
                    <tr v-if="!assignments.length">
                      <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                        No pending assignment requests waiting for end user.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </template>

          <!-- 2. TRANSFERS TAB -->
          <template v-else-if="activeTab === 'transfers'">
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
                  <tr v-for="transfer in transfers" :key="transfer.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                    <!-- From -> To -->
                    <td class="px-4 py-3.5">
                      <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ personName(transfer.user) }}</span>
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ personName(transfer.targetUser) }}</span>
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
                          @click="confirmAction = { kind: 'approve-transfer', id: transfer.id, label: `Approve transfer of ${transfer.item?.item_name ?? 'item'} to ${personName(transfer.targetUser)}?` }"
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

                  <tr v-if="!transfers.length">
                    <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      No pending custody transfers.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 3. RETURNS TAB -->
          <template v-else-if="activeTab === 'returns'">
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
                  <tr v-for="returnRequest in returns" :key="returnRequest.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
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

                  <tr v-if="!returns.length">
                    <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      No pending item returns.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 4. HISTORY TAB -->
          <template v-else-if="activeTab === 'history'">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div class="relative w-full max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
                </svg>
                <input
                  v-model="historySearch"
                  type="search"
                  placeholder="Search by item, person, department…"
                  aria-label="Search transaction history"
                  class="w-full rounded-md border border-gray-200 bg-gray-50/60 pl-9 pr-3 py-2 text-sm text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
                />
              </div>

              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ filteredHistory.length }} recorded entries
              </span>
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
                    <th class="px-4 py-3.5 text-right">Actions</th>
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
                      {{ transaction.fromUser ? personName(transaction.fromUser) : 'Stockroom / Warehouse' }}
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
                    <td class="px-4 py-3.5 text-right">
                      <button
                        v-if="transaction.manual_recipient_name && transaction.expected_return_date && transaction.status === 'assigned'"
                        type="button"
                        class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300"
                        @click="openPrompt('manual-return', transaction.id)"
                      >
                        Record Return
                      </button>
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
                      {{ group.first.fromUser ? personName(group.first.fromUser) : 'Stockroom / Warehouse' }}
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
                    <td class="px-4 py-3.5 text-right">
                      <span
                        v-if="group.entries.some((entry) => entry.manual_recipient_name && entry.expected_return_date && entry.status === 'assigned')"
                        class="text-[11px] font-semibold text-amber-700 dark:text-amber-300"
                        title="Expand to record individual returns"
                      >
                        {{ group.entries.filter((entry) => entry.manual_recipient_name && entry.expected_return_date && entry.status === 'assigned').length }} to return
                      </span>
                    </td>
                  </tr>
                  <tr v-if="isHistoryGroupExpanded(group.key)">
                    <td colspan="7" class="bg-gray-50/40 px-4 py-2 dark:bg-white/[0.02]">
                      <ul class="space-y-1">
                        <li v-for="entry in group.entries" :key="entry.id" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 text-[11px] text-gray-500 dark:text-gray-400">
                          <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ entry.item?.inventory_item_no ?? `#${entry.id}` }}</span>
                          <span class="font-bold text-gray-900 dark:text-white">×{{ entry.quantity }}</span>
                          <span v-if="entry.expected_return_date">due {{ formatDate(entry.expected_return_date) }}</span>
                          <button
                            v-if="entry.manual_recipient_name && entry.expected_return_date && entry.status === 'assigned'"
                            type="button"
                            class="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300"
                            @click="openPrompt('manual-return', entry.id)"
                          >
                            Record Return
                          </button>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  </template>
                  </template>

                  <tr v-if="!filteredHistory.length">
                    <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      No transactions found matching your criteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- 5. AUDIT LEDGER TAB -->
          <template v-else>
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

                  <tr v-if="!ledger.length">
                    <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                      No stock movement audit records yet.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
      </div>
    </template>

    <!-- Modals -->
    <AssignModal :open="assignOpen" :items="availableItems" :end-users="endUsers" @close="assignOpen = false" @saved="onAssignSaved" />
    <ApproveRequestModal :open="reviewRequest !== null" :request="reviewRequest" @close="reviewRequest = null" @approved="onApproveSaved" />

    <PromptModal
      :open="prompt !== null"
      :title="promptConfig.title"
      :subtitle="promptConfig.subtitle"
      :label="promptConfig.label"
      :required="promptConfig.required"
      :confirm-label="promptConfig.confirmLabel"
      :danger="promptConfig.danger"
      :busy="working"
      @cancel="prompt = null"
      @submit="submitPrompt"
    />

    <ConfirmDialog
      :open="confirmAction !== null"
      title="Confirm Approval"
      :message="confirmAction?.label ?? ''"
      confirm-label="Confirm Approval"
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

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" @click="approveReturn = null">Cancel</button>
          <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50" :disabled="working" @click="submitApproveReturn">
            {{ working ? 'Working…' : 'Confirm & Accept' }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
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

const activeTab = ref('requests');
const loading = ref(true);
const error = ref('');
const working = ref(false);
const historySearch = ref('');

const assignOpen = ref(false);
const reviewRequest = ref(null);
const prompt = ref(null);
const confirmAction = ref(null);
const approveReturn = ref(null);
const returnLocation = ref({ building: '', room: '' });

const metrics = ref({});
const availableItems = ref([]);
const endUsers = ref([]);
const incoming = ref([]);
const assignments = ref([]);
const transfers = ref([]);
const returns = ref([]);
const history = ref([]);
const ledger = ref([]);

const tabs = computed(() => [
  { key: 'requests', label: 'Requests', count: incoming.value.length + assignments.value.length },
  { key: 'transfers', label: 'Transfers', count: transfers.value.length },
  { key: 'returns', label: 'Returns', count: returns.value.length },
  { key: 'history', label: 'History', count: history.value.length },
  { key: 'ledger', label: 'Audit Ledger', count: ledger.value.length },
]);

function tabCountBadgeClass(key, isActive) {
  if (isActive) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
  }
  switch (key) {
    case 'requests':
      return incoming.value.length > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'transfers':
      return transfers.value.length > 0 ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
    case 'returns':
      return returns.value.length > 0 ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
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
  ledger.value,
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
// expected return date collapse into one expandable row. The per-record
// "Record Return" action stays reachable: it renders on each expanded entry
// that qualifies, exactly as it would ungrouped.
const expandedHistoryGroups = ref(new Set());

function historyGroupKey(transaction) {
  return [
    transaction.item?.item_name ?? 'Unknown item',
    Number(transaction.quantity ?? 0),
    transaction.fromUser ? personName(transaction.fromUser) : 'Stockroom / Warehouse',
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
  if (!query) {
    return history.value;
  }
  return history.value.filter((transaction) =>
    [
      transaction.quantity,
      transaction.item?.item_name,
      transaction.item?.inventory_item_no,
      transaction.fromUser ? personName(transaction.fromUser) : 'Warehouse',
      transaction.manual_recipient_name ?? personName(transaction.user),
      transaction.manual_department,
      transaction.transaction_date,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  );
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
    default:
      return { title: '', subtitle: '', label: 'Notes', required: false, confirmLabel: 'Confirm', danger: false };
  }
});

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function personName(user) {
  if (!user) {
    return 'Unknown';
  }
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
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

function applyTransactionsPayload(data) {
  metrics.value = {
    totalAssignedCount: data.totalAssignedCount,
    pendingRequestsCount: data.pendingRequestsCount,
    totalTransactionsCount: data.totalTransactionsCount,
    overdueReturnsCount: data.overdueReturnsCount,
  };
  availableItems.value = data.availableInventoryItems ?? [];
  endUsers.value = data.endUsers ?? [];
  incoming.value = data.incomingRequests ?? [];
  assignments.value = data.assignmentRequests ?? [];
  transfers.value = data.pendingTransfers ?? [];
  returns.value = data.pendingReturns ?? [];
  history.value = data.transactions ?? [];
  ledger.value = data.auditLedger ?? [];
}

function reloadAfterChange() {
  forgetPageCache();
  return load();
}

function onAssignSaved() {
  assignOpen.value = false;
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
  prompt.value = null;

  const routes = {
    'decline-request': [(id) => `/custodian/requests/${id}/decline`, { notes: text || null }],
    'decline-transfer': [(id) => `/custodian/transfers/${id}/decline`, { notes: text || null }],
    'decline-return': [(id) => `/custodian/returns/${id}/decline`, { notes: text || null }],
    'manual-return': [(id) => `/custodian/transactions/${id}/manual-return`, { notes: text || null }],
  };

  const [urlFor, payload] = routes[target.kind];
  await runMutation(() => api.post(urlFor(target.id), payload));
}

async function submitConfirm() {
  const action = confirmAction.value;
  if (!action) {
    return;
  }

  const urls = {
    'approve-transfer': `/custodian/transfers/${action.id}/approve`,
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

onMounted(() => load());
</script>
