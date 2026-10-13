<template>
  <div class="compact-page">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">My Assigned Items</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Items in your care, transferable or returnable from here.</p>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      <span>{{ error }}</span>
      <button
        type="button"
        class="ml-2 rounded-md bg-error-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <div v-else class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:max-w-xs">
          <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="filterSearch"
            type="search"
            placeholder="Search item…"
            aria-label="Filter assigned items"
            class="w-full rounded-md border border-gray-200 bg-white py-1.5 pl-8 pr-3 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:py-2 min-[480px]:text-sm [&::-webkit-search-cancel-button]:hidden"
          />
        </div>
        <select
          v-model="filterStatus"
          aria-label="Filter assigned items by status"
          class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:py-2 min-[480px]:text-sm"
        >
          <option value="">All Statuses</option>
          <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
        </select>
        <a
          :href="exportAssignedHref"
          class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/5 min-[480px]:py-2 min-[480px]:text-sm"
        >
          <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
          Export PDF
        </a>
        <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
          Showing {{ filteredRows.length }} of {{ rows.length }}
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-left text-xs">
          <thead class="sticky top-0 z-10 bg-white dark:bg-gray-900">
            <tr class="border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <th class="px-4 py-3.5">Type</th>
              <th class="px-4 py-3.5">Item</th>
              <th class="px-4 py-3.5">Qty</th>
              <th class="px-4 py-3.5">Date</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5 text-center">Actions</th>
            </tr>
          </thead>
          <tbody v-if="loading" aria-hidden="true">
            <tr v-for="row in 5" :key="row">
              <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                <div class="compact-skeleton h-3" :class="column === 6 ? 'mx-auto w-20' : 'w-3/4'" />
              </td>
            </tr>
          </tbody>
          <tbody v-else>
            <template v-for="group in groupedRows" :key="group.key">
            <template v-if="group.count === 1" v-for="(row, index) in group.entries" :key="`${row.type}-${row.item_id ?? row.item_name}-${index}`">
              <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3.5">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium"
                    :class="row.type === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'"
                  >
                    {{ row.type }}
                  </span>
                </td>
                <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ row.item_name }}</td>
                <td class="px-4 py-3.5">{{ row.quantity }}</td>
                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(row.date) }}</td>
                <td class="px-4 py-3.5"><StatusBadge :status="row.status" /></td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                      :aria-expanded="expanded.has(singleKey(group.key))"
                      :aria-label="expanded.has(singleKey(group.key)) ? `Hide details for ${row.item_name}` : `Show details for ${row.item_name}`"
                      :title="expanded.has(singleKey(group.key)) ? 'Hide details' : 'Details'"
                      @click="toggleExpand(singleKey(group.key))"
                    >
                      <LucideIcon
                        :icon="expanded.has(singleKey(group.key)) ? ChevronUp : ChevronDown"
                        class="h-4 w-4 transition-transform duration-200"
                      />
                    </button>
                    <button
                      v-if="canTransfer(row)"
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white transition-colors hover:bg-brand-700"
                      :aria-label="`Transfer ${row.item_name} to another end user`"
                      title="Transfer"
                      @click="transferRow = row"
                    >
                      <LucideIcon :icon="ArrowLeftRight" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canRequestReturn(row)"
                      type="button"
                      :disabled="workingId === rowKey(row)"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                      :aria-label="`Request return of ${row.item_name}`"
                      title="Request Return"
                      @click="openReturnConfirm(row)"
                    >
                      <LucideIcon :icon="Undo2" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canReportIssue(row)"
                      type="button"
                      :disabled="workingId === rowKey(row)"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-amber-200 text-amber-700 transition-colors hover:bg-amber-50 disabled:opacity-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-500/10"
                      :aria-label="`Report an issue with ${row.item_name}`"
                      title="Report Issue"
                      @click="openReportIssue(row)"
                    >
                      <LucideIcon :icon="TriangleAlert" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="isPendingReturn(row)"
                      type="button"
                      :disabled="workingId === rowKey(row)"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-error-200 text-error-700 transition-colors hover:bg-error-50 disabled:opacity-50 dark:border-error-800 dark:text-error-400 dark:hover:bg-error-500/10"
                      :aria-label="`Cancel pending return for ${row.item_name}`"
                      title="Cancel Return"
                      @click="cancelReturn(row)"
                    >
                      <LucideIcon :icon="X" class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="expanded.has(singleKey(group.key))">
                <td colspan="6" class="bg-gray-50 px-4 py-3.5 dark:bg-white/[0.02]">
                  <dl class="grid gap-3 text-xs text-gray-600 sm:grid-cols-3 dark:text-gray-400">
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Requested</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(row.request?.requested_at) }}</dd>
                    </div>
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Responded</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(row.request?.responded_at) }}</dd>
                    </div>
                    <div>
                      <dt class="text-[11px] font-medium uppercase text-gray-400">Notes</dt>
                      <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ row.request?.notes ?? '—' }}</dd>
                    </div>
                  </dl>
                </td>
              </tr>
            </template>
            <template v-else>
              <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                <td class="px-4 py-3.5">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium"
                    :class="group.first.type === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'"
                  >
                    {{ group.first.type }}
                  </span>
                </td>
                <td class="px-4 py-3.5">
                  <button
                    type="button"
                    class="flex items-center gap-1.5 text-left font-semibold text-gray-900 dark:text-white"
                    :aria-expanded="isGroupExpanded(group.key)"
                    :title="isGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                    @click="toggleGroup(group.key)"
                  >
                    <svg
                      class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                      :class="{ 'rotate-180': isGroupExpanded(group.key) }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span>
                      {{ group.first.item_name }}
                      <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                    </span>
                  </button>
                </td>
                <td class="px-4 py-3.5 font-bold" :title="`Sum of ${group.count} records`">{{ group.totalQuantity }}</td>
                <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(group.first.date) }}</td>
                <td class="px-4 py-3.5"><StatusBadge :status="group.first.status" /></td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      v-if="firstTransferable(group)"
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white transition-colors hover:bg-brand-700"
                      :aria-label="`Transfer ${group.first.item_name} to another end user`"
                      title="Transfer"
                      @click="transferRow = firstTransferable(group)"
                    >
                      <LucideIcon :icon="ArrowLeftRight" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="firstReturnable(group)"
                      type="button"
                      :disabled="workingId === rowKey(firstReturnable(group))"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                      :aria-label="`Request return of ${group.first.item_name}`"
                      title="Request Return"
                      @click="openReturnConfirm(firstReturnable(group))"
                    >
                      <LucideIcon :icon="Undo2" class="h-4 w-4" />
                    </button>
                    <button
                      v-if="firstReturnable(group)"
                      type="button"
                      :disabled="workingId === rowKey(firstReturnable(group))"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-amber-200 text-amber-700 transition-colors hover:bg-amber-50 disabled:opacity-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-500/10"
                      :aria-label="`Report an issue with ${group.first.item_name}`"
                      title="Report Issue"
                      @click="openReportIssue(firstReturnable(group))"
                    >
                      <LucideIcon :icon="TriangleAlert" class="h-4 w-4" />
                    </button>
                    <span v-if="!firstTransferable(group) && !firstReturnable(group)" class="text-[11px] text-gray-400 dark:text-gray-500">
                      {{ groupActionHint(group) }}
                    </span>
                  </div>
                </td>
              </tr>
              <template v-if="isGroupExpanded(group.key)">
                <template v-for="(entry, entryIndex) in group.entries" :key="groupEntryKey(group, entryIndex)">
                  <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                    <td class="px-4 py-3.5 pl-8">
                      <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Record {{ entryIndex + 1 }} of {{ group.count }}</span>
                    </td>
                    <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ entry.item_name }}</td>
                    <td class="px-4 py-3.5">{{ entry.quantity }}</td>
                    <td class="px-4 py-3.5 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.date) }}</td>
                    <td class="px-4 py-3.5"><StatusBadge :status="entry.status" /></td>
                    <td class="px-4 py-3.5">
                      <div class="flex items-center justify-center gap-1.5">
                        <button
                          type="button"
                          class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                          :aria-expanded="expanded.has(groupEntryKey(group, entryIndex))"
                          :aria-label="expanded.has(groupEntryKey(group, entryIndex)) ? `Hide details for ${entry.item_name}` : `Show details for ${entry.item_name}`"
                          :title="expanded.has(groupEntryKey(group, entryIndex)) ? 'Hide details' : 'Details'"
                          @click="toggleExpand(groupEntryKey(group, entryIndex))"
                        >
                          <LucideIcon
                            :icon="expanded.has(groupEntryKey(group, entryIndex)) ? ChevronUp : ChevronDown"
                            class="h-4 w-4 transition-transform duration-200"
                          />
                        </button>
                        <button
                          v-if="canTransfer(entry)"
                          type="button"
                          class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-brand-600 text-white transition-colors hover:bg-brand-700"
                          :aria-label="`Transfer ${entry.item_name} to another end user`"
                          title="Transfer"
                          @click="transferRow = entry"
                        >
                          <LucideIcon :icon="ArrowLeftRight" class="h-4 w-4" />
                        </button>
                        <button
                          v-if="canRequestReturn(entry)"
                          type="button"
                          :disabled="workingId === rowKey(entry)"
                          class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition-colors hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                          :aria-label="`Request return of ${entry.item_name}`"
                          title="Request Return"
                          @click="openReturnConfirm(entry)"
                        >
                          <LucideIcon :icon="Undo2" class="h-4 w-4" />
                        </button>
                        <button
                          v-if="canReportIssue(entry)"
                          type="button"
                          :disabled="workingId === rowKey(entry)"
                          class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-amber-200 text-amber-700 transition-colors hover:bg-amber-50 disabled:opacity-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-500/10"
                          :aria-label="`Report an issue with ${entry.item_name}`"
                          title="Report Issue"
                          @click="openReportIssue(entry)"
                        >
                          <LucideIcon :icon="TriangleAlert" class="h-4 w-4" />
                        </button>
                        <button
                          v-if="isPendingReturn(entry)"
                          type="button"
                          :disabled="workingId === rowKey(entry)"
                          class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-error-200 text-error-700 transition-colors hover:bg-error-50 disabled:opacity-50 dark:border-error-800 dark:text-error-400 dark:hover:bg-error-500/10"
                          :aria-label="`Cancel pending return for ${entry.item_name}`"
                          title="Cancel Return"
                          @click="cancelReturn(entry)"
                        >
                          <LucideIcon :icon="X" class="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="expanded.has(groupEntryKey(group, entryIndex))">
                    <td colspan="6" class="bg-gray-50 px-4 py-3.5 dark:bg-white/[0.02]">
                      <dl class="grid gap-3 text-xs text-gray-600 sm:grid-cols-3 dark:text-gray-400">
                        <div>
                          <dt class="text-[11px] font-medium uppercase text-gray-400">Requested</dt>
                          <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(entry.request?.requested_at) }}</dd>
                        </div>
                        <div>
                          <dt class="text-[11px] font-medium uppercase text-gray-400">Responded</dt>
                          <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ formatDate(entry.request?.responded_at) }}</dd>
                        </div>
                        <div>
                          <dt class="text-[11px] font-medium uppercase text-gray-400">Notes</dt>
                          <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ entry.request?.notes ?? '—' }}</dd>
                        </div>
                      </dl>
                    </td>
                  </tr>
                </template>
              </template>
            </template>
            </template>
            <tr v-if="!loading && !filteredRows.length">
              <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">{{ rows.length ? 'No items match the current filters.' : 'No assigned or requested items.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <TransferModal :open="transferRow !== null" :row="transferRow" :end-users="endUsers" @close="transferRow = null" @saved="onTransferSaved" />

    <!-- Return confirmation: the request posts per item, so confirm which
         record it acts on instead of firing from the icon directly. -->
    <Modal :open="returnConfirm !== null" title="Request Return" max-width="max-w-md" @close="returnConfirm = null">
      <div v-if="returnConfirm" class="space-y-4">
        <p class="rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-300">
          Request return of <span class="font-semibold text-gray-900 dark:text-white">{{ returnConfirm.item_name }}</span>
          ({{ returnConfirm.quantity }} {{ Number(returnConfirm.quantity) === 1 ? 'unit' : 'units' }} assigned)?
          This sends a request to the property custodian.
        </p>
        <div class="space-y-1.5">
          <label for="return-quantity" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Quantity to return (max {{ returnQuantityCap() }})
          </label>
          <input
            id="return-quantity"
            v-model.number="returnQuantity"
            type="number"
            :min="1"
            :max="returnQuantityCap()"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            @click="returnConfirm = null"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
            :disabled="workingId === rowKey(returnConfirm)"
            @click="confirmReturn"
          >
            Confirm Return
          </button>
        </div>
      </div>
    </Modal>
    <!-- Report issue: damage/loss on an assigned item. Report-only — the item
         stays assigned and no stock moves. -->
    <Modal :open="reportTarget !== null" title="Report Issue" max-width="max-w-md" @close="reportTarget = null">
      <div v-if="reportTarget" class="space-y-4">
        <p class="rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-300">
          Report an issue with <span class="font-semibold text-gray-900 dark:text-white">{{ reportTarget.item_name }}</span>
          ({{ reportTarget.quantity }} {{ Number(reportTarget.quantity) === 1 ? 'unit' : 'units' }} assigned)?
          The item stays assigned; the custodian triages the report.
        </p>
        <div class="space-y-1.5">
          <label for="report-type" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Issue type
          </label>
          <select
            id="report-type"
            v-model="reportType"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option value="damaged">Damaged</option>
            <option value="lost">Lost</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label for="report-quantity" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Quantity affected (max {{ reportQuantityCap() }})
          </label>
          <input
            id="report-quantity"
            v-model.number="reportQuantity"
            type="number"
            :min="1"
            :max="reportQuantityCap()"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="space-y-1.5">
          <label for="report-notes" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="report-notes"
            v-model="reportNotes"
            rows="2"
            maxlength="1000"
            placeholder="What happened, visible damage, last known location…"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            @click="reportTarget = null"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-brand-600 disabled:opacity-50"
            :disabled="reportTarget && workingId === rowKey(reportTarget)"
            @click="confirmReportIssue"
          >
            Submit Report
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { ArrowLeftRight, ChevronDown, ChevronUp, TriangleAlert, Undo2, X } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';
import TransferModal from './TransferModal.vue';

const toast = useToastStore();

const loading = ref(true);
const error = ref('');
const workingId = ref(null);
const transferRow = ref(null);
const expanded = ref(new Set());

const rows = ref([]);
const endUsers = ref([]);
const filterSearch = ref('');
const filterStatus = ref('');

const statusOptions = computed(() => [...new Set(
  rows.value.map((row) => row.status).filter(Boolean),
)].sort());

function assignedMatches(row) {
  if (filterStatus.value && row.status !== filterStatus.value) {
    return false;
  }
  const query = filterSearch.value.toLowerCase().trim();
  if (!query) {
    return true;
  }
  return [row.item_name, row.type]
    .filter(Boolean)
    .some((value) => String(value).toLowerCase().includes(query));
}

const filteredRows = computed(() => rows.value.filter(assignedMatches));

const exportAssignedHref = computed(() => {
  const params = new URLSearchParams({
    status: filterStatus.value,
    search: filterSearch.value.trim(),
  });

  return `/end-user/assigned-items/export-pdf?${params.toString()}`;
});
const returnConfirm = ref(null);
const returnQuantity = ref(1);
const reportTarget = ref(null);
const reportType = ref('damaged');
const reportQuantity = ref(1);
const reportNotes = ref('');

const TRANSFERABLE = ['approved', 'accepted'];

function statusOf(row) {
  return String(row.status ?? '').toLowerCase();
}

function rowKey(row) {
  return `${row.type}|${row.item_id ?? row.item_name}`;
}

function requestItemId(row) {
  return row.item_id ?? row.request?.item_id ?? null;
}

function canTransfer(row) {
  return Boolean(row.request?.id) && TRANSFERABLE.includes(statusOf(row));
}

function isPendingReturn(row) {
  return statusOf(row).includes('waiting for return approval');
}

function canRequestReturn(row) {
  return ['approved', 'accepted', 'waiting for return approval'].includes(statusOf(row))
    && !isPendingReturn(row)
    && requestItemId(row) !== null;
}

function canReportIssue(row) {
  return canRequestReturn(row);
}

function openReportIssue(row) {
  reportTarget.value = row;
  reportType.value = 'damaged';
  reportQuantity.value = Math.max(1, Number(row?.quantity) || 1);
  reportNotes.value = '';
}

function reportQuantityCap() {
  return Math.max(1, Number(reportTarget.value?.quantity) || 1);
}

async function confirmReportIssue() {
  const row = reportTarget.value;
  const itemId = row ? requestItemId(row) : null;
  if (!row || !itemId || workingId.value === rowKey(row)) {
    return;
  }
  workingId.value = rowKey(row);

  const cap = Math.max(1, Number(row.quantity) || 1);
  const quantity = Math.min(cap, Math.max(1, Number(reportQuantity.value) || 1));

  try {
    await api.post(`/end-user/inventory/${itemId}/report`, {
      type: reportType.value,
      quantity,
      notes: reportNotes.value.trim() || null,
    });
    reportTarget.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not report the issue.');
    }
  } finally {
    workingId.value = null;
  }
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function toggleExpand(key) {
  const next = new Set(expanded.value);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  expanded.value = next;
}

function singleKey(key) {
  return `s:${key}`;
}

function groupEntryKey(group, entryIndex) {
  return `g:${group.key}:${entryIndex}`;
}

// Display-only grouping: adjacent rows identical in type, item, quantity,
// date and status collapse into one expandable group. Every action still
// addresses one record — transfer posts a single request id and return posts
// a single item — so nothing here changes what the server receives.
function assignedGroupKey(row) {
  return [
    row.type ?? '',
    row.item_name ?? '',
    Number(row.quantity ?? 0),
    row.date ?? '',
    String(row.status ?? '').toLowerCase(),
  ].join('|');
}

const groupedRows = computed(() => {
  const groups = [];
  filteredRows.value.forEach((row, index) => {
    const key = assignedGroupKey(row);
    const current = groups[groups.length - 1];
    if (current && current.baseKey === key) {
      current.entries.push(row);
      return;
    }
    groups.push({ key: `${index}:${key}`, baseKey: key, entries: [row] });
  });
  return groups.map((group) => ({
    ...group,
    count: group.entries.length,
    totalQuantity: group.entries.reduce((sum, entry) => sum + Number(entry.quantity ?? 0), 0),
    first: group.entries[0],
  }));
});

function isGroupExpanded(key) {
  return expanded.value.has(key);
}

function toggleGroup(key) {
  toggleExpand(key);
}

function groupActionHint(group) {
  if (group.entries.some((entry) => canTransfer(entry))) {
    return 'Expand to transfer';
  }
  if (group.entries.some((entry) => canRequestReturn(entry))) {
    return 'Expand to return';
  }
  if (group.entries.some((entry) => isPendingReturn(entry))) {
    return 'Expand to manage';
  }
  return '';
}

// Group-level actions address the first eligible record: transfer posts one
// request id and return posts one item, so the modal and confirmation always
// name a real record and its own quantity cap. Per-record buttons in the
// expanded list cover choosing a different record.
function firstTransferable(group) {
  return group.entries.find((entry) => canTransfer(entry)) ?? null;
}

function firstReturnable(group) {
  return group.entries.find((entry) => canRequestReturn(entry)) ?? null;
}

function confirmReturn() {
  const row = returnConfirm.value;
  returnConfirm.value = null;
  if (row) {
    requestReturn(row);
  }
}

function openReturnConfirm(row) {
  returnConfirm.value = row;
  returnQuantity.value = Math.max(1, Number(row?.quantity) || 1);
}

function returnQuantityCap() {
  return Math.max(1, Number(returnConfirm.value?.quantity) || 1);
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'end-user-assigned',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/end-user/assigned-items');
      return data;
    },
    applyData: (data) => {
      rows.value = data.activityRows ?? [];
      endUsers.value = (data.endUsers ?? []).filter((person) => person.status !== 'inactive');
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load assigned items.';
    },
  });
}

async function requestReturn(row) {
  const itemId = requestItemId(row);
  if (!itemId) {
    return;
  }
  workingId.value = rowKey(row);

  const cap = Math.max(1, Number(row.quantity) || 1);
  const quantity = Math.min(cap, Math.max(1, Number(returnQuantity.value) || 1));

  try {
    await api.post(`/end-user/inventory/${itemId}/request-return`, { quantity });
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not request the return.');
    }
  } finally {
    workingId.value = null;
  }
}

async function cancelReturn(row) {
  const itemId = requestItemId(row);
  if (!itemId) {
    return;
  }
  workingId.value = rowKey(row);

  try {
    await api.post(`/end-user/inventory/${itemId}/cancel-return`);
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not cancel the return.');
    }
  } finally {
    workingId.value = null;
  }
}

function onTransferSaved() {
  transferRow.value = null;
  forgetPageCache();
  load();
}

onMounted(() => load());
</script>
