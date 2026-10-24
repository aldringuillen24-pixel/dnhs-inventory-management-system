<template>
  <div>
    <div class="mb-4">
      <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Logs</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Account, inventory, request, incident, and inspection history. Read-only.</p>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <div v-else>
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Audit streams">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'accounts'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'accounts'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'accounts'"
        >
          <span>Account Activity</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ paginator.total ?? 0 }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'movements'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'movements'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'movements'"
        >
          <span>Item Movements</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ movements.length }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'itemLogs'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'itemLogs'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'itemLogs'"
        >
          <span>Item Logs</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ itemLogs.length }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'incidents'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'incidents'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'incidents'"
        >
          <span>Incident &amp; Maintenance</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ incidentLogs.length }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'requests'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'requests'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'requests'"
        >
          <span>Request Decisions</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ requestLogs.length }}
          </span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'arrivals'"
          class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
          :class="activeTab === 'arrivals'
            ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
            : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
          @click="activeTab = 'arrivals'"
        >
          <span>Arrival Inspections</span>
          <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">
            {{ arrivalLogs.length }}
          </span>
        </button>
      </div>
      <button
        type="button"
        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
        @click="exportLogsModalOpen = true"
      >
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M5 14v5h14v-5" />
        </svg>
        Export Logs PDF
      </button>
      </div>

      <template v-if="activeTab === 'accounts'">
      <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
      <table class="w-full min-w-[52rem] text-left text-xs">
        <thead class="sticky top-0 z-10">
          <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
            <th class="px-4 py-3.5">Date</th>
            <th class="px-4 py-3.5">Action</th>
            <th class="px-4 py-3.5">Actor</th>
            <th class="px-4 py-3.5">Target</th>
          </tr>
        </thead>
        <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
          <tr v-for="row in 6" :key="row">
            <td v-for="column in 4" :key="column" class="px-4 py-3.5">
              <div class="compact-skeleton h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-2/3' : 'w-3/4'" />
            </td>
          </tr>
        </tbody>
        <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
            <td class="px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ label(log.action) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.actor_name ?? actorName(log.actor) }}</td>
            <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ log.target_name ?? actorName(log.target_user) }}</td>
          </tr>
          <tr v-if="!loading && !logs.length">
            <td colspan="4" class="px-4 py-8 text-center text-xs text-gray-500">No audit logs.</td>
          </tr>
        </tbody>
      </table>
      </div>
      <div class="mt-3 flex items-center justify-end">
        <Pagination :current-page="paginator.current_page ?? 1" :last-page="paginator.last_page ?? 1" :from="paginator.from" :to="paginator.to" :total="paginator.total ?? 0" @page="goToPage" />
      </div>
      </template>

      <template v-else-if="activeTab === 'movements'">
      <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
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
        <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
          <tr v-for="row in 6" :key="row">
            <td v-for="column in 8" :key="column" class="px-3 py-3.5">
              <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            </td>
          </tr>
        </tbody>
        <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
          <template v-for="group in pagedMovementGroups" :key="group.key">
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
                :class="movementBadgeClass(entry.movement_type)"
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
              {{ entry.user ? movementActor(entry.user) : 'System Automated' }}
            </td>
            <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
              {{ entry.display_details || entry.notes || 'No additional details recorded.' }}
            </td>
          </tr>
          </template>
          <template v-else>
          <tr class="bg-gray-50/60 hover:bg-gray-50 dark:bg-white/[0.03] dark:hover:bg-white/[0.05]">
            <td class="break-words px-3 py-3.5 text-xs text-gray-500 dark:text-gray-400">
              {{ formatDate(group.first.created_at) }}
            </td>
            <td class="break-words px-3 py-3.5">
              <button
                type="button"
                class="flex items-center gap-1.5 text-left"
                :aria-expanded="isMovementGroupExpanded(group.key)"
                :title="isMovementGroupExpanded(group.key) ? 'Hide individual records' : 'Show individual records'"
                @click="toggleMovementGroup(group.key)"
              >
                <svg
                  class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-200"
                  :class="{ 'rotate-180': isMovementGroupExpanded(group.key) }"
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
                :class="movementBadgeClass(group.first.movement_type)"
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
              {{ group.first.user ? movementActor(group.first.user) : 'System Automated' }}
            </td>
            <td class="w-56 whitespace-normal break-words px-3 py-3.5 text-xs leading-snug text-gray-600 dark:text-gray-300">
              {{ group.first.display_details || group.first.notes || 'No additional details recorded.' }}
            </td>
          </tr>
          <tr v-if="isMovementGroupExpanded(group.key)">
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
          <tr v-if="!loading && !movements.length">
            <td colspan="8" class="px-4 py-8 text-center text-xs text-gray-500">No item movements recorded.</td>
          </tr>
        </tbody>
      </table>
      </div>
      <div class="mt-3 flex items-center justify-between gap-3">
        <p class="text-[11px] text-gray-500 dark:text-gray-400">
          Showing {{ movementPageFrom }}–{{ movementPageTo }} of {{ movementGroups.length }} {{ movementGroups.length === 1 ? 'group' : 'groups' }}
        </p>
        <Pagination
          :current-page="Math.min(Math.max(1, movementsPage), movementLastPage)"
          :last-page="movementLastPage"
          :from="movementPageFrom"
          :to="movementPageTo"
          :total="movementGroups.length"
          @page="goToMovementsPage"
        />
      </div>
      </template>

      <template v-else-if="activeTab === 'itemLogs'">
        <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <table class="w-full min-w-[56rem] text-left text-xs">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                <th class="px-4 py-3.5">Date</th>
                <th class="px-4 py-3.5">Item</th>
                <th class="px-4 py-3.5">Action</th>
                <th class="px-4 py-3.5">Changes</th>
                <th class="px-4 py-3.5">Actor</th>
              </tr>
            </thead>
            <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
              <tr v-for="row in 6" :key="row">
                <td v-for="column in 5" :key="column" class="px-4 py-3.5">
                  <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                </td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="log in itemLogs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="whitespace-nowrap px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(log.created_at) }}</td>
                <td class="px-4 py-3.5">
                  <div class="font-semibold text-gray-900 dark:text-white">{{ log.item_name }}</div>
                  <span v-if="log.inventory_item_no" class="font-mono text-[10px] text-gray-400">{{ log.inventory_item_no }}</span>
                </td>
                <td class="px-4 py-3.5">
                  <span
                    class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase"
                    :class="log.action === 'deleted'
                      ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                      : 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300'"
                  >
                    {{ label(log.action) }}
                  </span>
                </td>
                <td class="max-w-xl whitespace-normal px-4 py-3.5 text-gray-700 dark:text-gray-300">
                  <div v-for="change in describeItemLog(log)" :key="change" class="mb-0.5 last:mb-0">{{ change }}</div>
                  <div v-if="log.reason" class="mt-1 italic text-gray-500">{{ log.reason }}</div>
                </td>
                <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ actorName(log.user) }}</td>
              </tr>
              <tr v-if="!itemLogs.length">
                <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500">No item edits or deletions recorded.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <template v-else>
        <div class="max-h-[34rem] overflow-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
          <table class="w-full min-w-[64rem] text-left text-xs">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                <th class="px-4 py-3.5">Date</th>
                <th class="px-4 py-3.5">Log Type</th>
                <th class="px-4 py-3.5">Action</th>
                <th class="px-4 py-3.5">Item / Reference</th>
                <th class="px-4 py-3.5">Details</th>
                <th class="px-4 py-3.5">Actor</th>
              </tr>
            </thead>
            <tbody v-if="loading" class="divide-y divide-gray-100 dark:divide-white/5" aria-hidden="true">
              <tr v-for="row in 6" :key="row">
                <td v-for="column in 6" :key="column" class="px-4 py-3.5">
                  <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                </td>
              </tr>
            </tbody>
            <tbody v-else class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="log in activeWorkflowLogs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="whitespace-nowrap px-4 py-3.5 text-[11px] text-gray-500">{{ formatDate(log.occurred_at) }}</td>
                <td class="px-4 py-3.5 font-medium text-gray-700 dark:text-gray-300">{{ activeWorkflowType }}</td>
                <td class="px-4 py-3.5 font-semibold text-gray-900 dark:text-white">{{ label(log.action) }}</td>
                <td class="px-4 py-3.5">
                  <div class="font-medium text-gray-900 dark:text-white">{{ log.item_name }}</div>
                  <span v-if="log.reference" class="font-mono text-[10px] text-gray-400">{{ log.reference }}</span>
                </td>
                <td class="max-w-lg whitespace-normal px-4 py-3.5 text-gray-600 dark:text-gray-300">
                  <span v-if="log.from_status || log.to_status" class="font-medium">
                    {{ label(log.from_status || '—') }} → {{ label(log.to_status || '—') }}
                    <span v-if="log.details"> · </span>
                  </span>
                  {{ log.details || (log.quantity ? `${log.quantity} units` : '—') }}
                </td>
                <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ workflowActorName(log.actor) }}</td>
              </tr>
              <tr v-if="!activeWorkflowLogs.length">
                <td colspan="6" class="px-4 py-8 text-center text-xs text-gray-500">{{ activeWorkflowEmptyMessage }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>

  <Modal
    :open="exportLogsModalOpen"
    :title="exportLogsPreviewOpen ? 'Review Logs' : 'Export Logs PDF'"
    :subtitle="exportLogsPreviewOpen ? 'Review the selected logs before exporting the PDF.' : 'Choose which log types to include in the report.'"
    :max-width="exportLogsPreviewOpen ? 'max-w-7xl' : 'max-w-lg'"
    :fixed-body="exportLogsPreviewOpen"
    @close="closeExportLogsModal"
  >
    <div v-if="exportLogsPreviewOpen" class="flex min-h-0 flex-1 flex-col">
      <div class="h-[65vh] min-h-[50vh] max-h-[65vh] min-w-0 flex-none overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
        <div v-if="exportLogsPreviewLoading" class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400" role="status">
          Loading logs preview…
        </div>
        <div v-else-if="exportLogsPreviewError" class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center" role="alert">
          <p class="text-sm text-rose-700 dark:text-rose-300">{{ exportLogsPreviewError }}</p>
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="reviewLogs"
          >
            Try again
          </button>
        </div>
        <iframe
          v-else
          :srcdoc="exportLogsPreviewHtml"
          class="h-full w-full"
          title="School Head logs report preview"
          sandbox="allow-same-origin"
        />
      </div>
    </div>
    <section v-else class="space-y-3" aria-label="Choose log types">
      <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ selectedLogTypes.length }} of {{ logTypeOptions.length }} log types selected
        </p>
        <div class="flex shrink-0 items-center gap-3 text-[11px] font-semibold">
          <button
            type="button"
            class="text-emerald-700 hover:text-emerald-800 dark:text-emerald-300"
            @click="selectedLogTypes = logTypeOptions.map((option) => option.key)"
          >
            Select all
          </button>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            :disabled="!selectedLogTypes.length"
            @click="selectedLogTypes = []"
          >
            Clear
          </button>
        </div>
      </div>
      <div class="divide-y divide-gray-100 rounded-md border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
        <label
          v-for="option in logTypeOptions"
          :key="option.key"
          class="flex cursor-pointer items-center justify-between gap-3 px-3 py-3 text-xs hover:bg-gray-50 dark:hover:bg-white/5"
        >
          <span class="font-medium text-gray-800 dark:text-gray-200">{{ option.label }}</span>
          <span class="flex items-center gap-3">
            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ formatCount(option.count) }}</span>
            <input
              v-model="selectedLogTypes"
              type="checkbox"
              :value="option.key"
              class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
            />
          </span>
        </label>
      </div>
    </section>
    <template #footer>
      <div v-if="exportLogsPreviewOpen" class="flex flex-wrap items-center justify-between gap-2 pt-3">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
          @click="backToLogFilters"
        >
          Back to Filters
        </button>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="closeExportLogsModal"
          >
            Close
          </button>
          <button
            type="button"
            :disabled="exportLogsPreviewLoading || Boolean(exportLogsPreviewError) || exportLogsSubmitting"
            class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            @click="exportLogsPdf"
          >
            {{ exportLogsSubmitting ? 'Exporting…' : 'Export PDF' }}
          </button>
        </div>
      </div>
      <div v-else class="flex items-center justify-end gap-2 pt-3">
        <button
          type="button"
          class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
          @click="closeExportLogsModal"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="!selectedLogTypes.length || exportLogsPreviewLoading"
          class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
          @click="reviewLogs"
        >
          {{ exportLogsPreviewLoading ? 'Loading…' : 'Review Logs' }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/axios';
import { loadCachedPage } from '../../lib/pageCache';
import { groupLedgerEntries } from '../../lib/auditLedger';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';

const loading = ref(true);
const error = ref('');
const activeTab = ref('accounts');
const logs = ref([]);
const paginator = ref({ data: [] });
const page = ref(1);
const movements = ref([]);
const itemLogs = ref([]);
const incidentLogs = ref([]);
const requestLogs = ref([]);
const arrivalLogs = ref([]);
const movementsPage = ref(1);
const expandedMovementGroups = ref(new Set());
const exportLogsModalOpen = ref(false);
const exportLogsPreviewOpen = ref(false);
const exportLogsPreviewLoading = ref(false);
const exportLogsPreviewError = ref('');
const exportLogsPreviewHtml = ref('');
const exportLogsSubmitting = ref(false);
let exportLogsPreviewRequestId = 0;
const logTypeOptions = computed(() => [
  { key: 'accounts', label: 'Account Activity', count: paginator.value.total ?? 0 },
  { key: 'movements', label: 'Item Movements', count: movements.value.length },
  { key: 'itemLogs', label: 'Item Logs', count: itemLogs.value.length },
  { key: 'incidents', label: 'Incident & Maintenance', count: incidentLogs.value.length },
  { key: 'requests', label: 'Request Decisions', count: requestLogs.value.length },
  { key: 'arrivals', label: 'Arrival Inspections', count: arrivalLogs.value.length },
]);
const selectedLogTypes = ref(['accounts', 'movements', 'itemLogs', 'incidents', 'requests', 'arrivals']);

// Ten groups per page, like the item monitor. Pagination applies after
// grouping so a batch is never split across pages.
const MOVEMENT_GROUPS_PER_PAGE = 10;

function label(action) {
  return String(action ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || '—';
}

function formatCount(value) {
  return Number(value ?? 0).toLocaleString();
}

function closeExportLogsModal() {
  exportLogsPreviewRequestId += 1;
  exportLogsModalOpen.value = false;
  exportLogsPreviewOpen.value = false;
  exportLogsPreviewLoading.value = false;
  exportLogsPreviewError.value = '';
  exportLogsPreviewHtml.value = '';
  exportLogsSubmitting.value = false;
}

function backToLogFilters() {
  exportLogsPreviewRequestId += 1;
  exportLogsPreviewOpen.value = false;
  exportLogsPreviewLoading.value = false;
  exportLogsPreviewError.value = '';
  exportLogsPreviewHtml.value = '';
}

async function reviewLogs() {
  if (!selectedLogTypes.value.length || exportLogsPreviewLoading.value) return;

  exportLogsPreviewOpen.value = true;
  exportLogsPreviewLoading.value = true;
  exportLogsPreviewError.value = '';
  exportLogsPreviewHtml.value = '';
  const requestId = ++exportLogsPreviewRequestId;

  try {
    const params = new URLSearchParams();
    selectedLogTypes.value.forEach((type) => params.append('log_types[]', type));
    const { data } = await api.get(`/school-head/audit-logs/export-preview?${params.toString()}`, {
      responseType: 'text',
      skipToast: true,
    });

    if (requestId !== exportLogsPreviewRequestId) return;
    if (typeof data !== 'string' || !data.includes('<html')) {
      throw new Error('The server returned an invalid logs preview.');
    }
    exportLogsPreviewHtml.value = data;
  } catch (requestError) {
    if (requestId !== exportLogsPreviewRequestId) return;
    console.error('Failed to load logs report preview', requestError);
    exportLogsPreviewError.value = 'Could not load the logs preview. Please try again.';
  } finally {
    if (requestId === exportLogsPreviewRequestId) exportLogsPreviewLoading.value = false;
  }
}

async function exportLogsPdf() {
  if (!selectedLogTypes.value.length || exportLogsPreviewLoading.value
    || exportLogsPreviewError.value || exportLogsSubmitting.value) return;

  exportLogsSubmitting.value = true;
  exportLogsPreviewError.value = '';

  try {
    const params = new URLSearchParams();
    selectedLogTypes.value.forEach((type) => params.append('log_types[]', type));
    const response = await api.get(`/school-head/audit-logs/export-pdf?${params.toString()}`, {
      responseType: 'blob',
      skipToast: true,
    });
    const blobUrl = window.URL.createObjectURL(response.data);
    const downloadLink = document.createElement('a');
    const disposition = response.headers['content-disposition'] ?? '';
    downloadLink.href = blobUrl;
    downloadLink.download = disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? 'school_head_logs.pdf';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);
  } catch (requestError) {
    console.error('Failed to export School Head logs PDF', requestError);
    exportLogsPreviewError.value = 'Could not export the logs PDF. Please try again.';
  } finally {
    exportLogsSubmitting.value = false;
  }
}

function describeItemLog(log) {
  const changes = log.changes && typeof log.changes === 'object' ? log.changes : {};

  return Object.entries(changes).map(([field, value]) => {
    const name = String(field).replace(/_/g, ' ');
    if (value && typeof value === 'object' && ('old' in value || 'new' in value)) {
      return `${name}: ${value.old ?? '—'} → ${value.new ?? '—'}`;
    }
    return `${name}: ${value ?? '—'}`;
  });
}

function actorName(user) {
  if (!user) return '—';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || '—';
}

function workflowActorName(user) {
  return user ? actorName(user) : 'Not recorded';
}

function formatDate(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function movementActor(user) {
  if (!user) return 'Unknown';
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

function movementBadgeClass(type) {
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

// Same grouping rule as the custodian ledger, so both pages fold identical
// rows identically. Actor labels match this view's display exactly.
const movementGroups = computed(() => groupLedgerEntries(
  movements.value,
  (entry) => (entry.user ? movementActor(entry.user) : 'System Automated'),
));

const movementLastPage = computed(() => Math.max(1, Math.ceil(movementGroups.value.length / MOVEMENT_GROUPS_PER_PAGE)));

const pagedMovementGroups = computed(() => {
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  const start = (page - 1) * MOVEMENT_GROUPS_PER_PAGE;
  return movementGroups.value.slice(start, start + MOVEMENT_GROUPS_PER_PAGE);
});

const movementPageFrom = computed(() => {
  if (movementGroups.value.length === 0) {
    return 0;
  }
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  return (page - 1) * MOVEMENT_GROUPS_PER_PAGE + 1;
});

const movementPageTo = computed(() => {
  const page = Math.min(Math.max(1, movementsPage.value), movementLastPage.value);
  return Math.min(movementGroups.value.length, page * MOVEMENT_GROUPS_PER_PAGE);
});

const activeWorkflowLogs = computed(() => ({
  incidents: incidentLogs.value,
  requests: requestLogs.value,
  arrivals: arrivalLogs.value,
}[activeTab.value] ?? []));
const activeWorkflowType = computed(() => ({
  incidents: 'Incident / Maintenance',
  requests: 'Request Decision',
  arrivals: 'Arrival Inspection',
}[activeTab.value] ?? 'Workflow'));
const activeWorkflowEmptyMessage = computed(() => ({
  incidents: 'No incident or maintenance history recorded.',
  requests: 'No completed request decisions recorded.',
  arrivals: 'No arrival inspection history recorded.',
}[activeTab.value] ?? 'No workflow history recorded.'));

function goToMovementsPage(next) {
  movementsPage.value = Math.min(Math.max(1, next), movementLastPage.value);
}

function isMovementGroupExpanded(key) {
  return expandedMovementGroups.value.has(key);
}

function toggleMovementGroup(key) {
  if (expandedMovementGroups.value.has(key)) {
    expandedMovementGroups.value.delete(key);
  } else {
    expandedMovementGroups.value.add(key);
  }
}

async function load(options = {}) {
  const params = { page: page.value };
  await loadCachedPage({
    page: 'school-head-audit-logs',
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/school-head/audit-logs', { params });
      return data;
    },
    applyData: (data) => {
      const logPage = data.logs ?? {};
      logs.value = logPage.data ?? [];
      paginator.value = logPage;
      movements.value = Array.isArray(data.movements) ? data.movements : [];
      itemLogs.value = Array.isArray(data.itemLogs) ? data.itemLogs : [];
      incidentLogs.value = Array.isArray(data.incidentLogs) ? data.incidentLogs : [];
      requestLogs.value = Array.isArray(data.requestLogs) ? data.requestLogs : [];
      arrivalLogs.value = Array.isArray(data.arrivalLogs) ? data.arrivalLogs : [];
      movementsPage.value = 1;
    },
    isCurrent: () => page.value === params.page,
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load audit logs.';
    },
  });
}

function goToPage(next) {
  page.value = next;
  load();
}

onMounted(() => load());
</script>
