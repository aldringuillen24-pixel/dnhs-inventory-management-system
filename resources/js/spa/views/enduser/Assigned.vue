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

    <div v-if="!error" class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="relative z-0 flex gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50" role="tablist" aria-label="Assigned items and blotter reports">
        <button
          id="assigned-items-tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === 'assigned'"
          aria-controls="assigned-items-panel"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="activeTab === 'assigned'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'assigned'"
        >
          Assigned Items
          <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ rows.length }}</span>
        </button>
        <button
          id="blotter-reports-tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === 'blotter'"
          aria-controls="blotter-reports-panel"
          class="relative z-10 inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
          :class="activeTab === 'blotter'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'blotter'"
        >
          Blooter Report
          <span class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ incidentReports.length }}</span>
        </button>
      </div>

      <!-- ── LEFT COLUMN: Assigned Items Table (3/4 on lg screens) ── -->
      <div
        v-show="activeTab === 'assigned'"
        id="assigned-items-panel"
        role="tabpanel"
        aria-labelledby="assigned-items-tab"
        class="min-w-0 w-full p-3 sm:p-4"
      >
        <div v-if="!error">
      <div class="mb-2 flex flex-wrap items-center gap-1.5">
        <div class="relative w-full sm:max-w-xs">
          <svg class="pointer-events-none absolute left-2.5 top-2 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="filterSearch"
            type="search"
            placeholder="Search item…"
            aria-label="Filter assigned items"
            class="h-[30px] w-full rounded-md border border-gray-200 bg-white py-0 pl-8 pr-3 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 [&::-webkit-search-cancel-button]:hidden"
          />
        </div>
        <select
          v-model="filterStatus"
          aria-label="Filter assigned items by status"
          class="h-[30px] rounded-md border border-gray-200 bg-white px-2.5 py-0 text-xs text-gray-800 transition-colors focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        >
          <option value="">All Statuses</option>
          <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
        </select>
        <a
          :href="exportAssignedHref"
          class="inline-flex h-[30px] items-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-white px-2.5 py-0 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/5"
        >
          <svg class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                <td class="px-3 py-2">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium"
                    :class="row.type === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'"
                  >
                    {{ row.type }}
                  </span>
                </td>
                <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ row.item_name }}</td>
                <td class="px-3 py-2">{{ row.quantity }}</td>
                <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(row.date) }}</td>
                <td class="px-3 py-2"><StatusBadge :status="row.status" compact /></td>
                <td class="px-3 py-2">
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
                <td class="px-3 py-2">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium"
                    :class="group.first.type === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'"
                  >
                    {{ group.first.type }}
                  </span>
                </td>
                <td class="px-3 py-2">
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
                <td class="px-3 py-2 font-bold" :title="`Sum of ${group.count} records`">{{ group.totalQuantity }}</td>
                <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(group.first.date) }}</td>
                <td class="px-3 py-2"><StatusBadge :status="group.first.status" compact /></td>
                <td class="px-3 py-2">
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
                    <td class="px-3 py-2 pl-6">
                      <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Record {{ entryIndex + 1 }} of {{ group.count }}</span>
                    </td>
                    <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ entry.item_name }}</td>
                    <td class="px-3 py-2">{{ entry.quantity }}</td>
                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.date) }}</td>
                    <td class="px-3 py-2"><StatusBadge :status="entry.status" compact /></td>
                    <td class="px-3 py-2">
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
      </div>

      <div
        v-show="activeTab === 'blotter'"
        id="blotter-reports-panel"
        role="tabpanel"
        aria-labelledby="blotter-reports-tab"
        class="w-full p-2 sm:p-3"
      >
        <section>
          <!-- Filters -->
          <div v-if="!loading && incidentReports.length > 0" class="flex items-center gap-2 px-3 py-2">
            <div class="relative min-w-0 basis-2/5">
              <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
              </svg>
              <input
                v-model="incidentSearch"
                type="search"
                placeholder="Search reports…"
                class="h-[30px] w-full rounded-md border border-gray-200 bg-gray-50 py-0 pl-8 pr-3 text-[11px] text-gray-800 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 [&::-webkit-search-cancel-button]:hidden"
              />
            </div>
            <div class="flex min-w-0 basis-2/5 items-center gap-2">
              <select
                v-model="incidentStatusFilter"
                class="h-[30px] min-w-0 flex-1 rounded-md border border-gray-200 bg-gray-50 px-2 py-0 text-[11px] text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              >
                <option value="">All Statuses</option>
                <option v-for="s in incidentStatusOptions" :key="s" :value="s">{{ s.replaceAll('_', ' ') }}</option>
              </select>
              <span class="shrink-0 text-[9px] text-gray-400 dark:text-gray-500">{{ filteredIncidentReports.length }} of {{ incidentReports.length }}</span>
            </div>
          </div>

          <div class="p-2">
            <!-- Skeleton -->
            <div v-if="loading" class="space-y-3">
              <div v-for="n in 2" :key="n" class="space-y-2.5 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                  <div class="compact-skeleton h-3 w-1/2" />
                  <div class="compact-skeleton h-4 w-16 rounded-full" />
                </div>
                <div class="compact-skeleton h-2.5 w-3/4" />
                <div class="compact-skeleton h-2 w-1/3" />
              </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="incidentReports.length === 0" class="flex flex-col items-center justify-center rounded-xl bg-gray-50 py-8 text-center dark:bg-white/[0.02]">
              <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <LucideIcon :icon="FileText" class="h-4 w-4 text-gray-400" />
              </div>
              <p class="text-xs font-medium text-gray-600 dark:text-gray-300">No incident reports</p>
              <p class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">None submitted yet.</p>
            </div>

            <!-- No filter match -->
            <div v-else-if="filteredIncidentReports.length === 0" class="rounded-xl bg-gray-50 py-6 text-center dark:bg-white/[0.02]">
              <p class="text-[11px] text-gray-500 dark:text-gray-400">No matching reports.</p>
            </div>

            <!-- Report Cards Stack -->
            <ul v-else class="grid max-h-[17.5rem] max-w-3xl grid-cols-1 content-start gap-2.5 overflow-y-auto pr-1 md:grid-cols-2">
              <li
                v-for="report in filteredIncidentReports"
                :key="report.id"
                class="group flex min-w-0 flex-col rounded-xl border bg-white transition-all duration-200 hover:shadow-sm dark:bg-gray-800/60"
                :class="incidentCardBorderClass(report)"
              >
                <!-- Card Header -->
                <div class="flex items-start justify-between gap-2 p-2.5 pb-1.5">
                  <div class="flex min-w-0 items-start gap-2">
                    <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg" :class="incidentTypeIconBg(report)">
                      <LucideIcon :icon="incidentTypeIcon(report)" class="h-3 w-3" :class="incidentTypeIconColor(report)" />
                    </div>
                    <div class="min-w-0">
                      <p class="truncate text-xs font-semibold text-gray-900 dark:text-white" :title="report.item_name">{{ report.item_name }}</p>
                      <p class="text-[11px] capitalize text-gray-500 dark:text-gray-400">{{ report.type.replaceAll('_', ' ') }}</p>
                    </div>
                  </div>
                  <!-- Status Badge -->
                  <span
                    class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                    :class="incidentStatusClass(report.status)"
                  >
                    <span class="h-1.5 w-1.5 rounded-full" :class="incidentStatusDotClass(report.status)" />
                    {{ report.status.replaceAll('_', ' ') }}
                  </span>
                </div>

                <!-- Card Body -->
                <div class="space-y-1 border-t border-gray-100 px-2.5 py-1.5 text-[11px] dark:border-gray-700/60">
                  <p class="text-gray-600 dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-white">{{ report.quantity }}</span> affected · {{ report.details }}
                  </p>
                  <p v-if="report.repair_return_destination" class="text-[11px] text-gray-500 dark:text-gray-400">
                    After repair: {{ report.repair_return_destination === 'assigned_user' ? 'return to me' : 'return to custodian' }}
                  </p>
                  <p class="text-[11px] text-gray-400 dark:text-gray-500">Submitted {{ formatDate(report.submitted_at) }}</p>
                </div>

                <!-- Evidence downloads -->
                <div v-if="report.evidence?.length" class="flex flex-wrap gap-1 border-t border-gray-100 px-2.5 py-1 dark:border-gray-700/60">
                  <a
                    v-for="file in report.evidence"
                    :key="file.id"
                    :href="file.download_url"
                    class="inline-flex items-center gap-1 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20"
                  >
                    <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    {{ file.filename }}
                  </a>
                </div>

                <!-- Awaiting Reporter Banner -->
                <div v-if="report.status === 'awaiting_reporter'" class="border-t border-purple-100 bg-purple-50 p-2 dark:border-purple-900/30 dark:bg-purple-500/10">
                  <div class="mb-1 flex items-center gap-1">
                    <LucideIcon :icon="MessageSquare" class="h-3 w-3 text-purple-600 dark:text-purple-400" />
                    <p class="text-[11px] font-semibold text-purple-800 dark:text-purple-300">Custodian requested info</p>
                  </div>
                  <div class="flex flex-col gap-1.5">
                    <textarea
                      v-model="incidentResponseDrafts[report.id]"
                      rows="2"
                      maxlength="2000"
                      aria-label="Response to custodian information request"
                      placeholder="Provide info…"
                      class="w-full rounded border border-purple-200 bg-white p-1.5 text-[11px] text-gray-800 focus:border-purple-400 focus:outline-none dark:border-purple-800 dark:bg-gray-800 dark:text-gray-100"
                    />
                    <button
                      type="button"
                      class="self-end inline-flex items-center gap-1 rounded bg-purple-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-purple-700 disabled:opacity-50"
                      :disabled="incidentResponseId === report.id || String(incidentResponseDrafts[report.id] ?? '').trim().length < 3"
                      @click="respondToIncidentInformation(report)"
                    >
                      <LucideIcon v-if="incidentResponseId === report.id" :icon="Loader2" class="h-2.5 w-2.5 animate-spin" />
                      <LucideIcon v-else :icon="Send" class="h-2.5 w-2.5" />
                      Send
                    </button>
                  </div>
                </div>

                <!-- Timeline Button (opens modal) -->
                <div v-if="report.timeline?.length" class="border-t border-gray-100 dark:border-gray-700/60">
                  <button
                    type="button"
                    class="flex w-full items-center justify-between px-2.5 py-1.5 text-[11px] font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-white"
                    @click="openTimelineModal(report)"
                  >
                    <span class="flex items-center gap-1.5">
                      <LucideIcon :icon="Clock" class="h-3.5 w-3.5 text-gray-400" />
                      View Timeline
                      <span class="rounded-full bg-gray-100 px-1.5 py-0.2 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ report.timeline.length }}</span>
                    </span>
                    <LucideIcon :icon="ChevronRight" class="h-3.5 w-3.5 text-gray-400" />
                  </button>
                </div>

                <div v-if="report.status === 'reported'" class="border-t border-gray-100 px-2.5 py-1.5 dark:border-gray-700/60">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-700 transition-colors hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
                    :disabled="incidentCancelId === report.id"
                    @click="incidentCancelTarget = report"
                  >
                    <LucideIcon v-if="incidentCancelId === report.id" :icon="Loader2" class="h-3 w-3 animate-spin" />
                    <LucideIcon v-else :icon="XCircle" class="h-3 w-3" />
                    Cancel Blotter
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </section>
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
      </div>
      <template #footer>
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
      </template>
    </Modal>
    <!-- Reporting records the issue only; inventory and assignment are unchanged. -->
    <Modal :open="reportTarget !== null" title="Report Issue" max-width="max-w-md" @close="reportTarget = null">
      <div v-if="reportTarget" class="space-y-4">
        <p class="rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-300">
          Report an issue with <span class="font-semibold text-gray-900 dark:text-white">{{ reportTarget.item_name }}</span>
          ({{ reportTarget.quantity }} {{ Number(reportTarget.quantity) === 1 ? 'unit' : 'units' }} assigned)?
          The item and assignment stay unchanged; the custodian reviews your report.
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
            <option v-if="!reportTarget.is_consumable" value="damaged">Damaged</option>
            <option v-if="reportTarget.maintenance_eligible" value="needs_repair">Needs Repair</option>
            <option value="lost">Lost</option>
            <option value="stolen">Stolen</option>
          </select>
        </div>
        <fieldset v-if="['damaged', 'needs_repair'].includes(reportType)" class="space-y-2">
          <legend class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            After repair, return the item to
          </legend>
          <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-2 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-300">
            <input
              v-model="repairReturnDestination"
              type="radio"
              value="custodian_available"
              class="mt-0.5 h-3.5 w-3.5 border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span>Custodian as available inventory</span>
          </label>
          <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-2 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-300">
            <input
              v-model="repairReturnDestination"
              type="radio"
              value="assigned_user"
              class="mt-0.5 h-3.5 w-3.5 border-gray-300 text-brand-600 focus:ring-brand-500"
            />
            <span>Return to me as assigned</span>
          </label>
        </fieldset>
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
            Incident details
          </label>
          <textarea
            id="report-notes"
            v-model="reportNotes"
            rows="3"
            maxlength="2000"
            placeholder="Describe what happened, when, and where…"
            class="block w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
          <p v-if="reportNotes.trim().length < 5" class="text-[11px] text-gray-500">Enter at least 5 characters.</p>
        </div>
        <div class="space-y-1.5">
          <label for="report-evidence" class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
            Supporting evidence <span class="font-normal text-gray-500">(optional, JPG/PNG/PDF, up to 5 MB; visible only to you and custodians)</span>
          </label>
          <input
            id="report-evidence"
            type="file"
            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
            class="block w-full text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold dark:text-gray-300 dark:file:bg-gray-800"
            @change="reportEvidence = $event.target.files?.[0] ?? null"
          />
          <p v-if="reportEvidence" class="truncate text-[11px] text-gray-500">{{ reportEvidence.name }}</p>
        </div>
      </div>
      <template #footer>
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
            :disabled="!reportTarget || workingId === rowKey(reportTarget) || reportNotes.trim().length < 5"
            @click="confirmReportIssue"
          >
            Submit Report
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="incidentCancelTarget !== null"
      title="Cancel Blotter Report"
      max-width="max-w-md"
      @close="incidentCancelTarget = null"
    >
      <p v-if="incidentCancelTarget" class="rounded-md bg-gray-50 p-3 text-xs leading-relaxed text-gray-600 dark:bg-white/5 dark:text-gray-300">
        Cancel the blotter report for
        <span class="font-semibold text-gray-900 dark:text-white">{{ incidentCancelTarget.item_name }}</span>?
        This can only be done before the custodian starts reviewing it.
      </p>
      <template #footer>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            :disabled="incidentCancelId !== null"
            @click="incidentCancelTarget = null"
          >
            Keep Report
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentCancelId !== null"
            @click="cancelIncidentReport"
          >
            <LucideIcon v-if="incidentCancelId !== null" :icon="Loader2" class="h-3 w-3 animate-spin" />
            Cancel Blotter
          </button>
        </div>
      </template>
    </Modal>

    <!-- ── Timeline Modal ── -->
    <Modal
      :open="timelineModalReport !== null"
      :title="timelineModalReport ? `${timelineModalReport.item_name} · Timeline` : 'Timeline'"
      :subtitle="timelineModalReport ? `${timelineModalReport.timeline?.length ?? 0} events` : ''"
      max-width="max-w-lg"
      :scrollable="true"
      @close="timelineModalReport = null"
    >
      <div v-if="timelineModalReport" class="space-y-1">
        <!-- Report meta -->
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/[0.03]">
          <div class="flex items-center gap-1.5">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md" :class="incidentTypeIconBg(timelineModalReport)">
              <LucideIcon :icon="incidentTypeIcon(timelineModalReport)" class="h-3 w-3" :class="incidentTypeIconColor(timelineModalReport)" />
            </div>
            <span class="text-xs font-semibold capitalize text-gray-700 dark:text-gray-200">{{ timelineModalReport.type.replaceAll('_', ' ') }}</span>
          </div>
          <span
            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
            :class="incidentStatusClass(timelineModalReport.status)"
          >
            <span class="h-1.5 w-1.5 rounded-full" :class="incidentStatusDotClass(timelineModalReport.status)" />
            {{ timelineModalReport.status.replaceAll('_', ' ') }}
          </span>
          <span class="ml-auto text-[11px] text-gray-400 dark:text-gray-500">{{ timelineModalReport.quantity }} affected</span>
        </div>

        <!-- Stepper -->
        <ol class="relative space-y-0 border-l-2 border-gray-100 pl-5 dark:border-gray-700">
          <li
            v-for="event in timelineEvents"
            :key="event.id ?? `${event.created_at}-${event.event_type}`"
            class="relative pb-5 last:pb-0"
          >
            <!-- Dot -->
            <span
              class="absolute -left-[1.4rem] flex h-5 w-5 items-center justify-center rounded-full border-2 border-white shadow-sm dark:border-gray-900"
              :class="incidentEventDotClass(event)"
            >
              <LucideIcon :icon="incidentEventIcon(event)" class="h-2.5 w-2.5 text-white" />
            </span>

            <!-- Event card -->
            <div class="rounded-lg border border-gray-100 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
              <div class="flex flex-wrap items-start justify-between gap-1">
                <span class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ incidentEventLabel(event) }}</span>
                <span class="whitespace-nowrap text-[11px] text-gray-400 dark:text-gray-500">{{ formatIncidentDate(event.created_at) }}</span>
              </div>
              <p class="mt-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ event.actor || 'System' }}</p>
              <p v-if="event.details" class="mt-1.5 whitespace-pre-wrap text-xs text-gray-600 dark:text-gray-300">{{ event.details }}</p>
              <!-- Evidence -->
              <div v-if="incidentEvidenceForEvent(timelineModalReport, event).length" class="mt-2 flex flex-wrap gap-1.5">
                <a
                  v-for="file in incidentEvidenceForEvent(timelineModalReport, event)"
                  :key="`${event.created_at}-${file.id}`"
                  :href="file.download_url"
                  class="inline-flex items-center gap-1 rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-300"
                >
                  <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                  {{ file.filename }}
                </a>
              </div>
            </div>
          </li>
        </ol>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
  ArrowLeftRight,
  CheckCircle,
  ChevronDown,
  ChevronRight,
  ChevronUp,
  Clock,
  FileText,
  Loader2,
  MessageSquare,
  RefreshCw,
  Search,
  Send,
  TriangleAlert,
  Undo2,
  Wrench,
  XCircle,
  Zap,
} from 'lucide';
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
const activeTab = ref('assigned');
const transferRow = ref(null);
const expanded = ref(new Set());

const rows = ref([]);
const endUsers = ref([]);
const incidentReports = ref([]);
const incidentResponseDrafts = ref({});
const incidentResponseId = ref(null);
const incidentCancelTarget = ref(null);
const incidentCancelId = ref(null);
const filterSearch = ref('');
const filterStatus = ref('');

// Incident reports filtering & timeline modal
const incidentSearch = ref('');
const incidentStatusFilter = ref('');
const timelineModalReport = ref(null);

function openTimelineModal(report) {
  timelineModalReport.value = report;
}

const timelineEvents = computed(() =>
  [...(timelineModalReport.value?.timeline ?? [])].sort((a, b) => {
    const timeDifference = Date.parse(b.created_at ?? '') - Date.parse(a.created_at ?? '');
    return Number.isNaN(timeDifference) || timeDifference === 0
      ? Number(b.id ?? 0) - Number(a.id ?? 0)
      : timeDifference;
  }),
);

const incidentStatusOptions = computed(() =>
  [...new Set(incidentReports.value.map((r) => r.status).filter(Boolean))].sort(),
);

const filteredIncidentReports = computed(() => {
  const q = incidentSearch.value.toLowerCase().trim();
  const s = incidentStatusFilter.value;
  return incidentReports.value.filter((r) => {
    if (s && r.status !== s) return false;
    if (!q) return true;
    return [r.item_name, r.type, r.details, r.status]
      .filter(Boolean)
      .some((v) => String(v).toLowerCase().includes(q));
  });
});

// Incident card border accent by status
function incidentCardBorderClass(report) {
  const s = String(report.status ?? '').toLowerCase();
  if (s === 'awaiting_reporter') return 'border-purple-300 dark:border-purple-700';
  if (s === 'declined' || s === 'cancelled') return 'border-rose-200 dark:border-rose-800';
  if (s === 'resolved') return 'border-emerald-200 dark:border-emerald-800';
  if (s === 'processing repair' || s === 'processing_repair') return 'border-amber-200 dark:border-amber-800';
  if (s === 'under review' || s === 'under_review') return 'border-blue-200 dark:border-blue-800';
  return 'border-gray-200 dark:border-gray-700';
}

// Incident type icon, background and color
function incidentTypeIcon(report) {
  const t = String(report.type ?? '').toLowerCase();
  if (t === 'damaged') return TriangleAlert;
  if (t === 'needs_repair' || t === 'needs repair') return Wrench;
  if (t === 'stolen') return XCircle;
  if (t === 'lost') return Search;
  return FileText;
}

function incidentTypeIconBg(report) {
  const t = String(report.type ?? '').toLowerCase();
  if (t === 'damaged') return 'bg-rose-50 dark:bg-rose-500/10';
  if (t === 'needs_repair' || t === 'needs repair') return 'bg-amber-50 dark:bg-amber-500/10';
  if (t === 'stolen') return 'bg-red-50 dark:bg-red-500/10';
  if (t === 'lost') return 'bg-orange-50 dark:bg-orange-500/10';
  return 'bg-gray-100 dark:bg-gray-700';
}

function incidentTypeIconColor(report) {
  const t = String(report.type ?? '').toLowerCase();
  if (t === 'damaged') return 'text-rose-500 dark:text-rose-400';
  if (t === 'needs_repair' || t === 'needs repair') return 'text-amber-500 dark:text-amber-400';
  if (t === 'stolen') return 'text-red-500 dark:text-red-400';
  if (t === 'lost') return 'text-orange-500 dark:text-orange-400';
  return 'text-gray-400';
}

// Incident status badge color
function incidentStatusClass(status) {
  const s = String(status ?? '').toLowerCase();
  if (s === 'under_review' || s === 'under review') return 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300';
  if (s === 'processing_repair' || s === 'processing repair') return 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300';
  if (s === 'resolved') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300';
  if (s === 'declined' || s === 'cancelled') return 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300';
  if (s === 'awaiting_reporter' || s === 'awaiting reporter') return 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-300';
  if (s === 'under_investigation' || s === 'under investigation') return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-300';
  return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
}

function incidentStatusDotClass(status) {
  const s = String(status ?? '').toLowerCase();
  if (s === 'under_review' || s === 'under review') return 'bg-blue-500';
  if (s === 'processing_repair' || s === 'processing repair') return 'bg-amber-500 animate-pulse';
  if (s === 'resolved') return 'bg-emerald-500';
  if (s === 'declined' || s === 'cancelled') return 'bg-rose-500';
  if (s === 'awaiting_reporter' || s === 'awaiting reporter') return 'bg-purple-500 animate-pulse';
  if (s === 'under_investigation' || s === 'under investigation') return 'bg-indigo-500';
  return 'bg-gray-400';
}

// Timeline event icon and dot color
function incidentEventIcon(event) {
  const et = String(event.event_type ?? '').toLowerCase();
  if (et === 'report_submitted') return FileText;
  if (et === 'report_cancelled') return XCircle;
  if (et === 'reporter_response_received') return MessageSquare;
  if (et === 'physical_inspection_recorded') return Search;
  if (et === 'incident_handover_confirmed') return CheckCircle;
  if (et === 'incident_repair_started') return Wrench;
  if (et === 'incident_repairability_reassessed') return RefreshCw;
  if (et === 'investigation_action_recorded') return Zap;
  if (et === 'status_changed') {
    const ts = String(event.to_status ?? '').toLowerCase();
    if (ts === 'declined') return XCircle;
    if (ts === 'resolved') return CheckCircle;
    if (ts === 'awaiting_reporter') return MessageSquare;
    return RefreshCw;
  }
  return Clock;
}

function incidentEventDotClass(event) {
  const et = String(event.event_type ?? '').toLowerCase();
  if (et === 'report_submitted') return 'bg-gray-400';
  if (et === 'report_cancelled') return 'bg-rose-500';
  if (et === 'reporter_response_received') return 'bg-indigo-500';
  if (et === 'physical_inspection_recorded') return 'bg-purple-500';
  if (et === 'incident_handover_confirmed') return 'bg-emerald-500';
  if (et === 'incident_repair_started') return 'bg-amber-500';
  if (et === 'incident_repairability_reassessed') return 'bg-orange-500';
  if (et === 'status_changed') {
    const ts = String(event.to_status ?? '').toLowerCase();
    if (ts === 'declined') return 'bg-rose-500';
    if (ts === 'resolved') return 'bg-emerald-500';
    if (ts === 'awaiting_reporter') return 'bg-purple-500';
    if (ts === 'under_investigation') return 'bg-indigo-500';
    return 'bg-blue-500';
  }
  return 'bg-gray-400';
}

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
const reportEvidence = ref(null);
const repairReturnDestination = ref('custodian_available');

const TRANSFERABLE = ['approved', 'accepted'];

function statusOf(row) {
  return String(row.status ?? '').toLowerCase();
}

function rowKey(row) {
  return `${row.type}|${row.request?.id ?? row.item_id ?? row.item_name}`;
}

function requestItemId(row) {
  return row.item_id ?? row.request?.item_id ?? null;
}

function canTransfer(row) {
  return !row.has_open_report
    && Boolean(row.request?.id)
    && TRANSFERABLE.includes(statusOf(row));
}

function isPendingReturn(row) {
  return statusOf(row).includes('waiting for return approval');
}

function canRequestReturn(row) {
  return !row.has_open_report
    && ['approved', 'accepted', 'waiting for return approval'].includes(statusOf(row))
    && !isPendingReturn(row)
    && requestItemId(row) !== null;
}

function canReportIssue(row) {
  return canRequestReturn(row);
}

function openReportIssue(row) {
  reportTarget.value = row;
  reportType.value = row?.is_consumable ? 'lost' : 'damaged';
  reportQuantity.value = Math.max(1, Number(row?.quantity) || 1);
  reportNotes.value = '';
  reportEvidence.value = null;
  repairReturnDestination.value = 'custodian_available';
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

  try {
    const formData = new FormData();
    formData.append('type', reportType.value);
    formData.append('quantity', String(reportQuantity.value));
    formData.append('notes', reportNotes.value.trim());
    if (['damaged', 'needs_repair'].includes(reportType.value)) {
      formData.append('repair_return_destination', repairReturnDestination.value);
    }
    if (reportEvidence.value) {
      formData.append('evidence', reportEvidence.value);
    }
    await api.post(`/end-user/inventory/${itemId}/report`, formData);
    reportTarget.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      const errors = requestError?.response?.data?.errors;
      const validationMessage = errors ? Object.values(errors).flat()[0] : null;
      toast.error(
        'Error',
        requestError?.response?.data?.message ?? validationMessage ?? 'Could not report the issue.',
      );
    } else {
      toast.error('Could not submit report', requestError?.response?.data?.message ?? 'Check your connection and try again.');
    }
  } finally {
    workingId.value = null;
  }
}

async function respondToIncidentInformation(report) {
  const details = String(incidentResponseDrafts.value[report.id] ?? '').trim();
  if (details.length < 3 || incidentResponseId.value !== null) {
    return;
  }
  incidentResponseId.value = report.id;

  try {
    await api.post(
      `/end-user/incident-reports/${report.id}/information-response`,
      { details },
      { skipToast: true },
    );
    incidentResponseDrafts.value[report.id] = '';
    toast.success('Response sent', 'Your response was added to the incident timeline.');
    forgetPageCache();
    await load();
  } catch (requestError) {
    toast.error(
      'Could not send response',
      requestError?.response?.data?.message ?? 'Refresh the report and try again.',
    );
  } finally {
    incidentResponseId.value = null;
  }
}

async function cancelIncidentReport() {
  const report = incidentCancelTarget.value;
  if (!report || incidentCancelId.value !== null) {
    return;
  }

  incidentCancelId.value = report.id;
  try {
    await api.post(`/end-user/incident-reports/${report.id}/cancel`);
    incidentCancelTarget.value = null;
    toast.success('Blotter cancelled', 'The report was cancelled.');
    forgetPageCache();
    await load();
  } catch (requestError) {
    const errors = requestError?.response?.data?.errors;
    const validationMessage = errors ? Object.values(errors).flat()[0] : null;
    toast.error(
      'Could not cancel blotter',
      validationMessage ?? requestError?.response?.data?.message ?? 'Refresh the report and try again.',
    );
  } finally {
    incidentCancelId.value = null;
  }
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function formatIncidentDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? String(value)
    : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function incidentEventLabel(event) {
  if (event.event_type === 'report_submitted') return 'Report submitted';
  if (event.event_type === 'report_cancelled') return 'Blotter report cancelled';
  if (event.event_type === 'reporter_response_received') return 'Information response received';
  if (event.event_type === 'status_changed' && event.to_status === 'awaiting_reporter') return 'Information requested';
  if (event.event_type === 'physical_inspection_recorded') return 'Physical inspection recorded';
  if (event.event_type === 'incident_handover_confirmed') return 'Handover confirmed';
  if (event.event_type === 'incident_repair_started') return 'Repair started';
  if (event.event_type === 'incident_repairability_reassessed') return 'Repair reassessed as not viable';
  if (event.event_type === 'investigation_action_recorded') return 'Investigation update';
  if (event.event_type === 'incident_processing_blocked') {
    return `Pending ${String(event.metadata?.stage ?? 'processing').replaceAll('_', ' ')}`;
  }
  if (event.event_type === 'status_changed' && event.to_status) {
    if (event.to_status === 'under_investigation') return 'Investigation started';
    if (event.to_status === 'resolved') {
      return `Report resolved: ${String(event.metadata?.outcome ?? 'complete').replaceAll('_', ' ')}`;
    }
    return `Status changed to ${event.to_status.replaceAll('_', ' ')}`;
  }
  return String(event.event_type ?? 'Incident activity').replaceAll('_', ' ');
}

function incidentRelatedRecords(event) {
  const metadata = event.metadata ?? {};
  const references = [
    ['inventory_id', 'The inventory item involved in this report.'],
    ['source_inventory_id', 'The original inventory item reported by you.'],
    ['processing_inventory_id', 'The separate item record used to track the affected units during repair or disposal.'],
    ['assignment_transaction_id', 'The record of when this item was assigned to you.'],
    ['assignment_request_id', 'The request associated with this item assignment.'],
    ['assignment_return_id', 'The record confirming this item was returned to the custodian.'],
    ['maintenance_record_id', 'The repair work record associated with this report.'],
    ['disposal_movement_id', 'The record of the affected item being disposed of.'],
    ['stock_movement_id', 'The record of the inventory quantity change related to this report.'],
  ];

  return references
    .filter(([key]) => Number.isInteger(Number(metadata[key])) && Number(metadata[key]) > 0)
    .map(([key, description]) => ({ key, description }));
}

function incidentEvidenceForEvent(report, event) {
  const evidenceIds = new Set((event.metadata?.evidence_ids ?? []).map(Number));
  return (report.evidence ?? []).filter((file) => evidenceIds.has(Number(file.id)));
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
      incidentReports.value = data.incidentReports ?? [];
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

function onTransferSaved() {
  transferRow.value = null;
  forgetPageCache();
  load();
}

onMounted(() => load());
</script>
