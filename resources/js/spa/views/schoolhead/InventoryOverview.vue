<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory Overview</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Read-only category summary.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard compact dense micro mini title="Units" :value="format(metrics.units)" subtitle="Non-disposed" :loading="loading" />
        <MetricCard compact dense micro mini title="Available" :value="format(metrics.available)" subtitle="Ready for assignment" :loading="loading" />
        <MetricCard compact dense micro mini title="Assigned" :value="format(metrics.assigned)" subtitle="In end-user care" :loading="loading" />
        <MetricCard compact dense micro mini title="Value" :value="money(metrics.value)" subtitle="Pesos" :loading="loading" />
      </div>

      <!-- Item monitor: every status, read-only -->
      <div class="mt-6">
        <div class="mb-3 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Item Monitor</h2>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
              {{ isIncidentTab ? 'Incident records, read-only.' : `All inventory states, read-only. Showing ${filteredMonitorRows.length} of ${monitorRows.length} records.` }}
            </p>
          </div>
          <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <div class="relative w-full sm:max-w-xs">
              <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
              </svg>
              <input
                v-model="monitorSearch"
                type="search"
                :placeholder="isIncidentTab ? 'Search incidents, items, reporters…' : 'Search items, holders, INV numbers…'"
                :aria-label="isIncidentTab ? 'Search incident records' : 'Search monitored items'"
                class="w-full rounded-md border border-gray-200 bg-white pl-9 pr-3 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
              />
            </div>
            <button
              type="button"
              class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="loading"
              @click="openExportPdfModal"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M5 14v5h14v-5" />
              </svg>
              Export Inventory PDF
            </button>
          </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Filter inventory and incident records">
          <button
            v-for="tab in monitorTabs"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="monitorStatus === tab.key"
            class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="monitorStatus === tab.key
              ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
              : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
            @click="monitorStatus = tab.key"
          >
            <span>{{ tab.label }}</span>
            <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
              {{ format(tab.count) }}
            </span>
          </button>
        </div>

        <template v-if="isIncidentTab">
          <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full min-w-[64rem] text-left text-xs">
              <thead class="sticky top-0 z-10">
                <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                  <th class="px-4 py-3.5">Reported</th>
                  <th class="px-4 py-3.5">Item</th>
                  <th class="px-4 py-3.5">Incident Type</th>
                  <th class="px-4 py-3.5 text-right">Qty</th>
                  <th class="px-4 py-3.5">Status</th>
                  <th class="px-4 py-3.5">Reporter</th>
                  <th class="px-4 py-3.5">Details</th>
                </tr>
              </thead>
              <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
                <tr v-for="row in 5" :key="row">
                  <td v-for="column in 7" :key="column" class="px-4 py-3.5">
                    <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                  </td>
                </tr>
              </tbody>
              <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
                <tr v-for="report in pagedIncidentRows" :key="report.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                  <td class="whitespace-nowrap px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(report.submitted_at) }}</td>
                  <td class="px-4 py-3.5">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ report.item_name }}</div>
                    <span v-if="report.inventory_item_no" class="font-mono text-[11px] text-gray-400">{{ report.inventory_item_no }}</span>
                  </td>
                  <td class="px-4 py-3.5 capitalize text-gray-700 dark:text-gray-300">{{ label(report.type) }}</td>
                  <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">{{ format(report.quantity) }} {{ report.unit }}</td>
                  <td class="px-4 py-3.5"><StatusBadge :status="report.status" /></td>
                  <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ report.reporter }}</td>
                  <td class="max-w-sm whitespace-normal px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ report.details || '—' }}</td>
                </tr>
                <tr v-if="!filteredIncidentRows.length">
                  <td colspan="7" class="px-4 py-8 text-center text-xs text-gray-500">
                    {{ monitorSearch.trim() ? 'No incident records match this search.' : `No ${monitorStatus === 'investigations' ? 'active investigations' : 'incident reports'} recorded.` }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <template v-else>
        <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <table class="w-full min-w-[56rem] text-left text-xs">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                <th class="px-4 py-3.5">Item</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5 text-right">Qty</th>
                <th class="px-4 py-3.5">Condition / Status</th>
                <th class="px-4 py-3.5">Holder</th>
                <th class="px-4 py-3.5">Lifespan</th>
              </tr>
            </thead>
            <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
              <tr v-for="row in 5" :key="row">
                <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                  <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'ml-auto w-12'" />
                </td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-for="group in pagedMonitorGroups" :key="group.key">
              <template v-if="group.count === 1" v-for="row in group.entries" :key="row.item_id">
              <tr
                class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                @click="openMonitorDetails(row)"
              >
                <td class="px-4 py-3.5">
                  <div class="font-semibold text-gray-900 dark:text-white">{{ row.item_name }}</div>
                  <span v-if="row.inventory_item_no" class="font-mono text-[11px] text-gray-400">{{ row.inventory_item_no }}</span>
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ row.category }}</td>
                <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">{{ format(row.quantity) }}</td>
                <td class="px-4 py-3.5">
                  <StatusBadge :status="row.status" />
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ row.holder_name ?? '—' }}</td>
                <td class="px-4 py-3.5 text-[11px]" :class="lifespanTextClass(row)">
                  {{ lifespanText(row) }}
                </td>
              </tr>
              </template>
              <template v-else>
              <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
                <td class="px-4 py-3.5">
                  <button
                    type="button"
                    class="flex items-center gap-1.5 text-left"
                    :aria-expanded="isMonitorGroupExpanded(group.key)"
                    :title="isMonitorGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                    @click="toggleMonitorGroup(group.key)"
                  >
                    <svg
                      class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                      :class="{ 'rotate-180': isMonitorGroupExpanded(group.key) }"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                      stroke-width="2"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                    <span>
                      <span class="font-semibold text-gray-900 dark:text-white">{{ group.first.item_name }}</span>
                      <span class="ml-1.5 inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">×{{ group.count }}</span>
                    </span>
                  </button>
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ group.first.category }}</td>
                <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white" :title="`Sum of ${group.count} records`">{{ format(group.totalQuantity) }}</td>
                <td class="px-4 py-3.5">
                  <StatusBadge :status="group.first.status" />
                </td>
                <td class="px-4 py-3.5 text-gray-600 dark:text-gray-300">{{ group.first.holder_name ?? '—' }}</td>
                <td class="px-4 py-3.5 text-[11px]" :class="lifespanTextClass(group.first)">
                  {{ lifespanText(group.first) }}
                </td>
              </tr>
              <tr v-if="isMonitorGroupExpanded(group.key)">
                <td colspan="6" class="bg-gray-50/40 px-4 py-2 dark:bg-white/[0.02]">
                  <ul class="space-y-1">
                    <li v-for="entry in group.entries" :key="entry.item_id">
                      <button
                        type="button"
                        class="flex w-full flex-wrap items-center gap-x-3 gap-y-0.5 pl-6 text-left text-[11px] text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        @click="openMonitorDetails(entry)"
                      >
                        <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ entry.inventory_item_no ?? `#${entry.item_id}` }}</span>
                        <span class="font-bold text-gray-900 dark:text-white">×{{ entry.quantity }}</span>
                      </button>
                    </li>
                  </ul>
                </td>
              </tr>
              </template>
              </template>
              <tr v-if="!loading && !filteredMonitorRows.length">
                <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">No records match this filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
        </template>

        <div class="mt-3 flex items-center justify-between gap-3">
          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            Showing {{ displayPageFrom }}–{{ displayPageTo }} of {{ displayRowCount }} {{ displayRowCount === 1 ? (isIncidentTab ? 'report' : 'group') : (isIncidentTab ? 'reports' : 'groups') }}
          </p>
          <Pagination
            :current-page="Math.min(Math.max(1, monitorPage), displayLastPage)"
            :last-page="displayLastPage"
            :from="displayPageFrom"
            :to="displayPageTo"
            :total="displayRowCount"
            @page="goToMonitorPage"
          />
        </div>
      </div>
    </div>
  </div>

  <Modal
    :open="exportPdfOpen"
    :title="exportPdfPreviewOpen ? 'Review Inventory Report' : 'Export Inventory PDF'"
    :subtitle="exportPdfPreviewOpen ? 'Preview the report before exporting the PDF.' : 'Choose the inventory filters and ICS numbers to include.'"
    :max-width="exportPdfPreviewOpen ? 'max-w-7xl' : 'max-w-2xl'"
    :fixed-body="exportPdfPreviewOpen"
    @close="closeExportPdfModal"
  >
    <div v-if="exportPdfPreviewOpen" class="flex min-h-0 flex-1 flex-col">
      <div class="h-[65vh] min-h-[50vh] max-h-[65vh] min-w-0 flex-none overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
        <div v-if="exportPdfPreviewLoading" class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400" role="status">
          Loading report preview…
        </div>
        <div v-else-if="exportPdfPreviewError" class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center" role="alert">
          <p class="text-sm text-rose-700 dark:text-rose-300">{{ exportPdfPreviewError }}</p>
          <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="reviewInventoryReport">
            Try again
          </button>
        </div>
        <iframe
          v-else
          ref="exportPdfPreviewFrame"
          :srcdoc="exportPdfPreviewHtml"
          class="h-full w-full"
          title="Inventory report preview"
          sandbox="allow-same-origin"
          @load="exportPdfPreviewReady = true"
        />
      </div>
      <p v-if="exportPdfActionError" class="mt-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300" role="alert">
        {{ exportPdfActionError }}
      </p>
    </div>
    <div v-else class="space-y-4">
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
          <span>Workspace</span>
          <select v-model="exportPdfWorkspace" class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
            <option v-for="tab in exportPdfWorkspaces" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
          </select>
        </label>
        <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
          <span>Category</span>
          <select v-model="exportPdfCategory" class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
            <option value="">All Categories</option>
            <option v-for="category in categories" :key="category.category_id" :value="String(category.category_id)">
              {{ category.category_name }}
            </option>
          </select>
        </label>
      </div>

      <section class="space-y-2" aria-label="Filter by ICS number">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300">ICS numbers</h3>
            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
              {{ exportPdfSelectedIcs.length ? `${exportPdfSelectedIcs.length} selected` : 'No specific ICS selected — all matching ICS numbers will be included.' }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-3 text-[11px] font-semibold">
            <button type="button" class="text-emerald-700 hover:text-emerald-800 disabled:opacity-50 dark:text-emerald-300" :disabled="!filteredExportPdfIcs.length" @click="toggleVisibleExportIcs(true)">Select shown</button>
            <button type="button" class="text-gray-500 hover:text-gray-700 disabled:opacity-50 dark:text-gray-400 dark:hover:text-gray-200" :disabled="!exportPdfSelectedIcs.length" @click="exportPdfSelectedIcs = []">Clear</button>
          </div>
        </div>
        <input
          v-model="exportPdfIcsSearch"
          type="search"
          placeholder="Find an exact ICS number"
          aria-label="Search ICS numbers"
          class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        />
        <div v-if="exportPdfIcsLoading" class="max-h-48 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700" aria-label="Loading ICS numbers" aria-busy="true">
          <div v-for="row in 4" :key="row" class="flex items-center gap-2.5 border-b border-gray-100 px-3 py-2 last:border-b-0 dark:border-gray-800">
            <div class="h-4 w-4 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
            <div class="h-3 w-28 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
          </div>
        </div>
        <div v-else-if="exportPdfIcsError" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">
          {{ exportPdfIcsError }}
          <button type="button" class="ml-2 font-semibold underline" @click="loadExportPdfIcs">Try again</button>
        </div>
        <div v-else class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700">
          <label v-for="icsNumber in filteredExportPdfIcs" :key="icsNumber" class="flex cursor-pointer items-center gap-2.5 border-b border-gray-100 px-3 py-2 text-xs text-gray-700 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">
            <input v-model="exportPdfSelectedIcs" type="checkbox" :value="icsNumber" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800" />
            <span class="font-mono">{{ icsNumber }}</span>
          </label>
          <p v-if="!filteredExportPdfIcs.length" class="px-3 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
            {{ exportPdfIcsOptions.length ? 'No ICS numbers match your search.' : 'No ICS numbers are available.' }}
          </p>
        </div>
      </section>
    </div>
    <template #footer>
      <div v-if="exportPdfPreviewOpen" class="flex items-center justify-between gap-2 pt-3">
        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="backToExportFilters">
          Back to Filters
        </button>
        <button type="button" :disabled="exportPdfPreviewLoading || !exportPdfPreviewReady || Boolean(exportPdfPreviewError) || exportPdfSubmitting" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" @click="exportInventoryPdf">
          {{ exportPdfSubmitting ? 'Exporting…' : 'Export PDF' }}
        </button>
      </div>
      <div v-else class="flex items-center justify-end gap-2 pt-3">
        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="closeExportPdfModal">
          Cancel
        </button>
        <button type="button" :disabled="exportPdfIcsLoading || Boolean(exportPdfIcsError) || exportPdfSubmitting || exportPdfPreviewLoading" class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50" @click="reviewInventoryReport">
          {{ exportPdfPreviewLoading ? 'Loading…' : 'Review Report' }}
        </button>
      </div>
    </template>
  </Modal>

  <!-- Read-only details: facts only, no actions by design -->
  <Modal
    :open="monitorDetails !== null"
    :title="monitorDetails ? `${monitorDetails.item_name} details` : 'Item details'"
    :subtitle="monitorDetails?.inventory_item_no ?? ''"
    max-width="max-w-lg"
    @close="monitorDetails = null"
  >
    <div v-if="monitorDetails" class="space-y-2.5 text-sm">
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Category</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ monitorDetails.category }}</span>
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Status</span>
        <StatusBadge :status="monitorDetails.status" />
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Quantity</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ format(monitorDetails.quantity) }} {{ monitorDetails.unit }}</span>
      </div>
      <div class="flex justify-between gap-3 border-b border-gray-100 pb-2 dark:border-white/5">
        <span class="text-gray-500 dark:text-gray-400">Holder</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ monitorDetails.holder_name ?? '—' }}</span>
      </div>
      <div class="flex justify-between gap-3">
        <span class="text-gray-500 dark:text-gray-400">Expected end of life</span>
        <span class="font-semibold" :class="lifespanTextClass(monitorDetails)">{{ lifespanText(monitorDetails) }}</span>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';

const loading = ref(true);
const error = ref('');
const metrics = ref({});
const categories = ref([]);
const monitorRows = ref([]);
const incidentReports = ref([]);
const investigations = ref([]);
const monitorStatus = ref('all');
const monitorSearch = ref('');
const monitorDetails = ref(null);
const monitorPage = ref(1);
const exportPdfOpen = ref(false);
const exportPdfWorkspace = ref('all');
const exportPdfCategory = ref('');
const exportPdfIcsOptions = ref([]);
const exportPdfSelectedIcs = ref([]);
const exportPdfIcsSearch = ref('');
const exportPdfIcsLoading = ref(false);
const exportPdfIcsError = ref('');
const exportPdfSubmitting = ref(false);
const exportPdfActionError = ref('');
const exportPdfPreviewOpen = ref(false);
const exportPdfPreviewLoading = ref(false);
const exportPdfPreviewError = ref('');
const exportPdfPreviewHtml = ref('');
const exportPdfPreviewFrame = ref(null);
const exportPdfPreviewReady = ref(false);
const exportPdfSignatureValues = ref({});
let exportPdfPreviewRequestId = 0;

const EXPORT_PDF_SIGNATURE_FIELDS = [
  'received_from_name',
  'received_from_position',
  'received_from_date',
  'received_by_name',
  'received_by_role',
  'received_by_date',
];
const filteredExportPdfIcs = computed(() => {
  const query = exportPdfIcsSearch.value.trim().toLowerCase();
  return query
    ? exportPdfIcsOptions.value.filter((icsNumber) => icsNumber.toLowerCase().includes(query))
    : exportPdfIcsOptions.value;
});
const exportPdfModalHref = computed(() => {
  const params = new URLSearchParams({
    workspace: exportPdfWorkspace.value,
    category_id: exportPdfCategory.value,
    received_by_role: 'School Head',
  });
  exportPdfSelectedIcs.value.forEach((icsNumber) => params.append('ics_numbers[]', icsNumber));
  return `/school-head/inventory/export-pdf?${params.toString()}`;
});
const exportPdfWorkspaces = computed(() => monitorTabs.value
  .filter((tab) => tab.key !== 'under_inspection')
  .map((tab) => tab.key === 'all' ? { ...tab, label: 'All Inventory' } : tab));

// One page of groups: pagination applies after grouping so a collapsed batch
// is never split across two pages.
const MONITOR_PAGE_SIZE = 10;

const STATUS_LABELS = {
  all: 'All',
  available: 'Available',
  assigned: 'Assigned',
  under_repair: 'Under Repair',
  under_inspection: 'Under Inspection',
  ready_to_dispose: 'Ready to Dispose',
  disposed: 'Disposed',
  incident_reports: 'Incident Reports',
  investigations: 'Investigations',
};

const isIncidentTab = computed(() => ['incident_reports', 'investigations'].includes(monitorStatus.value));

const monitorTabs = computed(() => {
  const counts = {};
  monitorRows.value.forEach((row) => {
    counts[row.status] = (counts[row.status] ?? 0) + 1;
  });
  // Every state always shows, even at zero — the point of the monitor is
  // seeing all of inventory activity, not only states that have rows.
  const inventoryTabs = Object.keys(STATUS_LABELS)
    .filter((key) => !['incident_reports', 'investigations'].includes(key))
    .map((key) => ({
      key,
      label: STATUS_LABELS[key],
      count: key === 'all' ? monitorRows.value.length : (counts[key] ?? 0),
    }));
  return [
    ...inventoryTabs,
    { key: 'incident_reports', label: STATUS_LABELS.incident_reports, count: incidentReports.value.length },
    { key: 'investigations', label: STATUS_LABELS.investigations, count: investigations.value.length },
  ];
});

const filteredMonitorRows = computed(() => {
  const query = monitorSearch.value.trim().toLowerCase();
  return monitorRows.value.filter((row) => {
    if (monitorStatus.value !== 'all' && row.status !== monitorStatus.value) {
      return false;
    }
    if (query === '') {
      return true;
    }
    return [
      row.item_name,
      row.inventory_item_no,
      row.category,
      row.holder_name,
      row.status_label,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });
});

const filteredIncidentRows = computed(() => {
  const rows = monitorStatus.value === 'investigations' ? investigations.value : incidentReports.value;
  const query = monitorSearch.value.trim().toLowerCase();

  if (!query) return rows;

  return rows.filter((row) => [
    row.item_name,
    row.inventory_item_no,
    row.type,
    row.status,
    row.reporter,
    row.details,
  ].filter(Boolean).some((value) => String(value).toLowerCase().includes(query)));
});

// Display-only grouping, mirroring the Transactions ledger: adjacent rows
// identical in item, category, quantity, status, holder and lifespan collapse
// into one expandable group. Raw rows stay untouched; anything differing
// stays its own row.
const expandedMonitorGroups = ref(new Set());

function monitorGroupKey(row) {
  return [
    row.item_name ?? '',
    row.category ?? '',
    Number(row.quantity ?? 0),
    row.status ?? '',
    row.holder_name ?? '',
    row.expected_end_date ?? '',
  ].join('|');
}

const groupedMonitorRows = computed(() => {
  const rows = filteredMonitorRows.value;
  const groups = [];
  rows.forEach((row, index) => {
    const key = monitorGroupKey(row);
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
    totalQuantity: group.entries.reduce((sum, row) => sum + Number(row.quantity ?? 0), 0),
    first: group.entries[0],
  }));
});

function isMonitorGroupExpanded(key) {
  return expandedMonitorGroups.value.has(key);
}

function toggleMonitorGroup(key) {
  if (expandedMonitorGroups.value.has(key)) {
    expandedMonitorGroups.value.delete(key);
  } else {
    expandedMonitorGroups.value.add(key);
  }
}

const monitorLastPage = computed(() => Math.max(1, Math.ceil(groupedMonitorRows.value.length / MONITOR_PAGE_SIZE)));

const pagedMonitorGroups = computed(() => {
  const page = Math.min(Math.max(1, monitorPage.value), monitorLastPage.value);
  const start = (page - 1) * MONITOR_PAGE_SIZE;
  return groupedMonitorRows.value.slice(start, start + MONITOR_PAGE_SIZE);
});

const displayRowCount = computed(() => (
  isIncidentTab.value ? filteredIncidentRows.value.length : groupedMonitorRows.value.length
));
const displayLastPage = computed(() => Math.max(1, Math.ceil(displayRowCount.value / MONITOR_PAGE_SIZE)));
const displayPageFrom = computed(() => displayRowCount.value === 0
  ? 0
  : (Math.min(Math.max(1, monitorPage.value), displayLastPage.value) - 1) * MONITOR_PAGE_SIZE + 1);
const displayPageTo = computed(() => Math.min(displayRowCount.value, Math.min(Math.max(1, monitorPage.value), displayLastPage.value) * MONITOR_PAGE_SIZE));
const pagedIncidentRows = computed(() => {
  const start = (Math.min(Math.max(1, monitorPage.value), displayLastPage.value) - 1) * MONITOR_PAGE_SIZE;
  return filteredIncidentRows.value.slice(start, start + MONITOR_PAGE_SIZE);
});

function goToMonitorPage(page) {
  monitorPage.value = Math.min(Math.max(1, page), displayLastPage.value);
}

// A new filter restarts on the first page; the current page may no longer
// exist for the narrowed list.
watch([monitorStatus, monitorSearch], () => {
  monitorPage.value = 1;
});

function openMonitorDetails(row) {
  monitorDetails.value = row;
}

function lifespanEndDate(row) {
  if (!row?.expected_end_date) {
    return null;
  }
  const date = new Date(row.expected_end_date);
  return Number.isNaN(date.getTime()) ? null : date;
}

function lifespanText(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'No date';
  }
  return `Ends ${end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}`;
}

function lifespanTextClass(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'text-gray-400 dark:text-gray-500';
  }
  if (end.getTime() < Date.now()) {
    return 'font-semibold text-rose-600 dark:text-rose-400';
  }
  if (end.getTime() <= Date.now() + 365 * 24 * 60 * 60 * 1000) {
    return 'font-semibold text-amber-700 dark:text-amber-300';
  }
  return 'text-gray-500 dark:text-gray-400';
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function label(value) {
  return String(value ?? '').replaceAll('_', ' ');
}

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString();
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function openExportPdfModal() {
  exportPdfPreviewRequestId += 1;
  exportPdfWorkspace.value = monitorStatus.value === 'under_inspection' ? 'all' : (monitorStatus.value || 'all');
  exportPdfCategory.value = '';
  exportPdfSelectedIcs.value = [];
  exportPdfIcsSearch.value = '';
  exportPdfIcsError.value = '';
  exportPdfActionError.value = '';
  exportPdfSubmitting.value = false;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewError.value = '';
  exportPdfPreviewHtml.value = '';
  exportPdfPreviewReady.value = false;
  exportPdfSignatureValues.value = {};
  exportPdfOpen.value = true;
  await loadExportPdfIcs();
}

function closeExportPdfModal() {
  exportPdfPreviewRequestId += 1;
  exportPdfOpen.value = false;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewReady.value = false;
  exportPdfPreviewHtml.value = '';
  exportPdfPreviewError.value = '';
  exportPdfActionError.value = '';
}

function backToExportFilters() {
  try {
    exportPdfSignatureValues.value = readExportPdfSignatureFields();
  } catch (requestError) {
    console.error('Failed to preserve inventory report signature fields', requestError);
    exportPdfActionError.value = 'Could not save edits. Keep the report preview open and try again.';
    return;
  }

  exportPdfPreviewRequestId += 1;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewReady.value = false;
  exportPdfActionError.value = '';
}

async function loadExportPdfIcs() {
  exportPdfIcsLoading.value = true;
  exportPdfIcsError.value = '';
  try {
    const { data } = await api.get('/school-head/inventory/ics-numbers');
    if (!Array.isArray(data.icsNumbers) || data.icsNumbers.some((value) => typeof value !== 'string')) {
      throw new Error('The server returned an invalid ICS number list.');
    }
    exportPdfIcsOptions.value = data.icsNumbers;
  } catch (requestError) {
    exportPdfIcsError.value = requestError?.response?.data?.message
      ?? requestError?.message
      ?? 'Could not load ICS numbers. Try again before selecting ICS filters.';
  } finally {
    exportPdfIcsLoading.value = false;
  }
}

async function reviewInventoryReport() {
  if (exportPdfIcsLoading.value || exportPdfIcsError.value || exportPdfPreviewLoading.value) return;

  exportPdfPreviewOpen.value = true;
  exportPdfPreviewLoading.value = true;
  exportPdfPreviewError.value = '';
  exportPdfActionError.value = '';
  exportPdfPreviewHtml.value = '';
  exportPdfPreviewReady.value = false;
  const requestId = ++exportPdfPreviewRequestId;

  try {
    const params = new URLSearchParams(new URL(exportPdfModalHref.value, window.location.origin).search);
    params.set('preview', '1');
    Object.entries(exportPdfSignatureValues.value).forEach(([field, value]) => params.set(field, value));
    const { data } = await api.get(`/school-head/inventory/export-pdf?${params.toString()}`, {
      responseType: 'text',
      skipToast: true,
    });

    if (requestId !== exportPdfPreviewRequestId) return;
    if (typeof data !== 'string' || !data.includes('<html')) {
      throw new Error('The server returned an invalid report preview.');
    }
    exportPdfPreviewHtml.value = data;
  } catch (requestError) {
    if (requestId !== exportPdfPreviewRequestId) return;
    console.error('Failed to load inventory report preview', requestError);
    exportPdfPreviewError.value = 'Could not load the inventory report preview. Please try again.';
  } finally {
    if (requestId === exportPdfPreviewRequestId) exportPdfPreviewLoading.value = false;
  }
}

function readExportPdfSignatureFields() {
  const previewDocument = exportPdfPreviewFrame.value?.contentDocument;
  if (!previewDocument) throw new Error('The report preview is not available to read.');

  return Object.fromEntries(EXPORT_PDF_SIGNATURE_FIELDS.map((field) => {
    const input = previewDocument.querySelector(`[data-report-field="${field}"]`);
    if (!input || !('value' in input)) throw new Error(`The report preview is missing the ${field} field.`);
    return [field, input.value];
  }));
}

function toggleVisibleExportIcs() {
  const visible = new Set(filteredExportPdfIcs.value);
  exportPdfSelectedIcs.value = [...new Set([...exportPdfSelectedIcs.value, ...visible])];
}

async function exportInventoryPdf() {
  if (exportPdfIcsLoading.value || exportPdfIcsError.value || exportPdfSubmitting.value
    || exportPdfPreviewLoading.value || !exportPdfPreviewReady.value || exportPdfPreviewError.value) return;

  exportPdfSubmitting.value = true;
  exportPdfActionError.value = '';
  try {
    const params = new URLSearchParams(new URL(exportPdfModalHref.value, window.location.origin).search);
    Object.entries(readExportPdfSignatureFields()).forEach(([field, value]) => params.set(field, value));
    const response = await api.get(`/school-head/inventory/export-pdf?${params.toString()}`, {
      responseType: 'blob',
      skipToast: true,
    });
    const blobUrl = window.URL.createObjectURL(response.data);
    const downloadLink = document.createElement('a');
    const disposition = response.headers['content-disposition'] ?? '';
    downloadLink.href = blobUrl;
    downloadLink.download = disposition.match(/filename="?([^";]+)"?/i)?.[1]
      ?? `dnhs_inventory_${exportPdfWorkspace.value}.pdf`;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);
    closeExportPdfModal();
  } catch (requestError) {
    console.error('Failed to export inventory PDF', requestError);
    exportPdfActionError.value = 'Could not generate the inventory PDF. Please try again.';
  } finally {
    exportPdfSubmitting.value = false;
  }
}

async function load(options = {}) {
  await loadCachedPage({
    page: 'school-head-inventory',
    params: {},
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/inventory/overview');
      return data;
    },
    applyData: (data) => {
      metrics.value = data.metrics ?? {};
      categories.value = Array.isArray(data.exportCategories) ? data.exportCategories : [];
      monitorRows.value = Array.isArray(data.monitorRows) ? data.monitorRows : [];
      incidentReports.value = Array.isArray(data.incidentReports) ? data.incidentReports : [];
      investigations.value = Array.isArray(data.investigations) ? data.investigations : [];
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load the inventory overview.';
    },
  });
}

onMounted(() => load());
</script>
