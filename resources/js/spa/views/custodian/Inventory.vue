<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory Management</h1>
          <span
            v-if="metrics.total !== undefined"
            class="rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
          >
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stock records grouped by item, category, unit, and condition.</p>
      </div>

      <div class="grid w-full grid-cols-2 gap-2.5 sm:flex sm:w-auto sm:items-center">
        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm transition-colors hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-gray-800 dark:text-emerald-300 dark:hover:bg-emerald-500/10 sm:w-auto"
          @click="openQrScanner"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 0 1 1-1h2m10 0h2a1 1 0 0 1 1 1v2M4 17v2a1 1 0 0 0 1 1h2m10 0h2a1 1 0 0 0 1-1v-2M8 8h3v3H8zm5 0h3v3h-3zm-5 5h3v3H8zm6 0v1m2-1v3m-3-1h1" />
          </svg>
          Scan QR
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 sm:w-auto"
          @click="stockInOpen = true"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Stock In
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-purple-200 bg-white px-3 py-2 text-xs font-semibold text-purple-800 shadow-sm transition-colors hover:bg-purple-50 dark:border-purple-500/30 dark:bg-gray-800 dark:text-purple-300 dark:hover:bg-purple-500/10 sm:w-auto"
          @click="printIIRUP"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
          </svg>
          Print IIRUP
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-sky-200 bg-white px-3 py-2 text-xs font-semibold text-sky-800 shadow-sm transition-colors hover:bg-sky-50 dark:border-sky-500/30 dark:bg-gray-800 dark:text-sky-300 dark:hover:bg-sky-500/10 sm:w-auto"
          @click="openRfqPreview"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h8" />
          </svg>
          Print RFQ
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm transition-colors hover:bg-blue-50 dark:border-blue-500/30 dark:bg-gray-800 dark:text-blue-300 dark:hover:bg-blue-500/10 sm:w-auto"
          @click="openQrPrintModal"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H5a2 2 0 00-2 2v4h2a2 2 0 002 2zm0 0v2a2 2 0 002 2h6a2 2 0 002-2v-2" />
          </svg>
          Print QR Codes
        </button>
      </div>
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
      <!-- Main Inventory Panel -->
      <div class="custodian-panel relative flex max-h-[calc(100vh-7rem)] min-h-[32rem] flex-col overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <!-- Interactive Workspace Tabs -->
        <div class="relative shrink-0 border-b border-gray-200/80 bg-gray-50/50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 sm:hidden">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-semibold text-gray-800 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:gap-3 min-[480px]:px-3 min-[480px]:py-2.5 min-[480px]:text-sm"
            aria-label="Choose inventory status"
            :aria-expanded="workspaceMenuOpen"
            aria-controls="inventory-workspace-options"
            @click="workspaceMenuOpen = !workspaceMenuOpen"
            @keydown.esc="workspaceMenuOpen = false"
          >
            <span class="flex min-w-0 items-center gap-2 min-[480px]:gap-2.5">
              <svg class="h-3.5 w-3.5 shrink-0 text-gray-500 dark:text-gray-400 min-[480px]:h-4 min-[480px]:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <span class="truncate">{{ activeWorkspaceTab.label }}</span>
              <span
                :key="`trigger:${activeWorkspaceTab.key}:${activeWorkspaceTab.count}`"
                class="inline-block shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-bold min-[480px]:px-2 min-[480px]:text-[10px]"
                :class="[tabBadgeClass(activeWorkspaceTab.key, true), animationsLive && 'tab-badge-pop']"
              >
                {{ format(activeWorkspaceTab.count) }}
              </span>
            </span>
            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform min-[480px]:h-4 min-[480px]:w-4" :class="{ 'rotate-180': workspaceMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
          </button>

          <div
            v-if="workspaceMenuOpen"
            id="inventory-workspace-options"
            class="absolute inset-x-2.5 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800"
          >
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="workspace === tab.key
                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
              :aria-pressed="workspace === tab.key"
              @click="selectWorkspace(tab.key)"
            >
              <span>{{ tab.label }}</span>
              <span
                :key="`${tab.key}:${tab.count}`"
                class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold"
                :class="[tabBadgeClass(tab.key, workspace === tab.key), animationsLive && 'tab-badge-pop']"
              >
                {{ format(tab.count) }}
              </span>
            </button>
            <button
              type="button"
              class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="logsOpen
                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
              :aria-expanded="logsOpen"
              aria-controls="item-logs-drawer"
              @click="workspaceMenuOpen = false; logsOpen = true"
            >
              <span>Item logs</span>
              <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
          </div>
        </div>

        <div ref="tabStripRef" class="relative hidden shrink-0 flex-nowrap gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50 sm:flex">
          <span
            aria-hidden="true"
            class="pointer-events-none absolute inset-y-2 rounded-md bg-white shadow-sm ring-1 ring-emerald-500/20 motion-safe:transition-all motion-safe:duration-200 dark:bg-gray-800 dark:ring-emerald-500/30"
            :style="pillStyle"
          />
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :data-workspace-tab="tab.key"
            type="button"
            class="group relative inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
            :class="
              workspace === tab.key
                ? 'text-emerald-900 dark:text-emerald-300'
                : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'
            "
            :aria-pressed="workspace === tab.key"
            @click="switchWorkspace(tab.key)"
          >
            <span>{{ tab.label }}</span>
            <span
              :key="`${tab.key}:${tab.count}`"
              class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-bold tabular-nums transition-colors"
              :class="[tabBadgeClass(tab.key, workspace === tab.key), animationsLive && 'tab-badge-pop']"
            >
              {{ format(tab.count) }}
            </span>
          </button>
          <button
            type="button"
            class="ml-auto inline-flex min-h-[2rem] shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
            :class="logsOpen
              ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
              : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
            :aria-expanded="logsOpen"
            aria-controls="item-logs-drawer"
            @click="logsOpen = !logsOpen"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Item logs</span>
          </button>
        </div>

        <!-- Toolbar: Search + Category + Stats + Bulk Actions -->
        <div class="flex shrink-0 flex-col gap-2.5 border-b border-gray-100 p-2.5 dark:border-white/5 min-[480px]:gap-3 min-[480px]:p-4 sm:flex-row sm:items-center">
          <!-- Search Input -->
          <div class="relative w-full min-w-0 sm:max-w-[17rem] sm:flex-shrink-0">
            <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
            </svg>
            <input
              v-model="search"
              type="search"
              placeholder="Search item, category, assignee, ICS no…"
              aria-label="Search inventory"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 pl-7 pr-7 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:focus:border-emerald-400 dark:focus:bg-gray-800 [&::-webkit-search-cancel-button]:hidden"
            />
            <button
              v-if="search"
              type="button"
              class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              @click="search = ''"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Category Select -->
          <div class="w-full min-w-0 sm:max-w-[18rem] sm:flex-shrink-0">
            <select
              v-model="categoryFilter"
              aria-label="Filter inventory by category"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:focus:border-emerald-400"
            >
              <option value="">All Categories ({{ categories.length }})</option>
              <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                {{ category.category_name }}
              </option>
            </select>
          </div>

          <!-- Export PDF -->
          <button
            type="button"
            class="shrink-0 inline-flex w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-[12px] font-semibold text-gray-700 transition-colors hover:bg-gray-50 hover:border-gray-300 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-200 dark:hover:bg-gray-700/60 sm:w-auto"
            @click="openExportPdfModal"
          >
            <svg class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
            </svg>
            Export PDF
          </button>

          <!-- Result Counter -->
          <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 min-[480px]:text-xs sm:ml-auto">
            Showing <strong class="text-gray-800 dark:text-white">{{ format(filteredRows.length) }}</strong> of {{ format(paginator.total) }} groups
          </div>
        </div>

        <!-- Bulk Selection Alert Banner -->
        <div
          v-if="selection.length"
          class="flex shrink-0 items-center justify-between gap-3 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 border-b border-amber-200/60 dark:bg-amber-950/30 dark:text-amber-200 dark:border-amber-800/40"
        >
          <div class="flex items-center gap-2">
            <span class="flex h-5 w-5 items-center justify-center rounded-md bg-amber-200 text-amber-900 text-[10px] font-bold dark:bg-amber-800 dark:text-amber-100">
              {{ selection.length }}
            </span>
            <span>item group(s) selected</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="font-semibold text-amber-800 underline hover:text-amber-950 dark:text-amber-300"
              @click="selection = []"
            >
              Deselect All
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 font-bold text-white shadow-sm hover:bg-rose-700"
              @click="confirmBulk = true"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete Selected
            </button>
          </div>
        </div>

        <Transition name="workspace" mode="out-in">
          <div :key="workspace" class="flex min-h-0 min-w-0 flex-1 flex-col">
        <div v-if="loading" class="min-h-0 min-w-0 flex-1 shrink space-y-2.5 overflow-y-auto p-3 xl:hidden" aria-hidden="true">
          <div v-for="row in 4" :key="row" class="space-y-2.5 rounded-md border border-gray-200 p-3 dark:border-gray-800">
            <div class="h-3.5 w-2/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-3 w-1/2 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-10 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <InventoryTableSkeleton v-if="loading" class="hidden min-w-0 flex-1 xl:block" />

        <!-- Compact item cards for phones and tablets -->
        <div v-else class="min-h-0 min-w-0 flex-1 space-y-1.5 overflow-y-auto p-2.5 xl:hidden min-[480px]:space-y-2 min-[480px]:p-3">
          <article
            v-for="row in filteredRows"
            :key="groupKey(row)"
            class="rounded-md border border-gray-200 bg-white p-2.5 dark:border-gray-800 dark:bg-gray-900 min-[480px]:p-3"
          >
            <div class="flex items-center gap-2 min-[480px]:gap-3">
              <input
                :checked="isGroupSelected(row)"
                type="checkbox"
                :aria-label="`Select ${row.item_name}`"
                class="h-3.5 w-3.5 shrink-0 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 min-[480px]:h-4 min-[480px]:w-4"
                @change="toggleGroupSelection(row, $event.target.checked)"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base">{{ row.item_name }}</p>
                <p v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'" class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base"><span v-if="workspace !== 'assigned'">Assigned: </span>{{ format(assignedOutQty(row)) }} {{ row.unit }}</p>
                <p v-if="workspace === 'assigned' && rowAssignees(row)" class="mt-0.5 truncate text-[11px] font-medium text-gray-500 dark:text-gray-400 min-[480px]:text-xs">{{ rowAssignees(row) }}</p>
                <p v-else-if="row.status === 'available' && workspace !== 'all'" class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base">Available: {{ format(row.free_quantity ?? row.quantity) }} {{ row.unit }}</p>
                <p v-else class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 min-[480px]:mt-1 min-[480px]:text-xs">{{ format(row.quantity) }} {{ row.unit }}</p>
                <p v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all' && (row.pending_hold ?? 0) > 0" class="mt-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-300 min-[480px]:text-xs">
                  Pending: {{ format(row.pending_hold ?? 0) }}
                </p>
                <p v-else-if="row.status === 'available'" class="mt-0.5 text-[11px] font-semibold min-[480px]:text-xs" :class="(row.pending_hold ?? 0) > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400 dark:text-gray-500'">
                  Pending: {{ format(row.pending_hold ?? 0) }}
                </p>
                <p v-if="groupSubtitle(row)" class="mt-0.5 truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                  {{ groupSubtitle(row) }}
                </p>
              </div>
              <p v-if="workspace === 'disposed'" class="min-w-0 flex-1 truncate text-[11px] text-gray-500 dark:text-gray-400" :title="disposalReason(row) || 'No reason recorded.'">
                {{ disposalReason(row) || 'No reason recorded.' }}
              </p>
              <StatusBadge v-else :status="row.status" compact />
              <span v-if="hasRepairRecommendation(row)" class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Repair recommended</span>
              <span v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">Assigned issuing</span>
              <button
                type="button"
                class="shrink-0 rounded-md border border-emerald-200 px-2 py-1.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10 min-[480px]:px-3 min-[480px]:py-2 min-[480px]:text-xs"
                :aria-expanded="recordGroup?.item_id === row.item_id"
                @click="openRecordModal(row)"
              >
                Show more
              </button>
            </div>
          </article>

          <div v-if="!filteredRows.length" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            <p class="font-semibold text-gray-800 dark:text-white">No inventory groups found</p>
            <p class="mt-1 text-xs text-gray-400">Try adjusting your search or category filters.</p>
          </div>
        </div>

        <!-- Table -->
        <div v-if="!loading" class="hidden min-h-0 min-w-0 flex-1 overflow-auto custom-scrollbar xl:block" role="region" aria-label="Inventory table, scroll horizontally to view all columns" tabindex="0">
          <p class="sticky left-0 z-10 border-b border-gray-100 bg-white px-4 py-2 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 sm:hidden">
            Scroll horizontally to view all columns
          </p>
          <table class="w-full min-w-[64rem] text-left text-xs">
            <thead class="sticky top-0 z-20 bg-gray-50 dark:bg-gray-800">
              <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                <th class="w-10 px-4 py-3.5">
                  <input
                    v-if="filteredRows.length"
                    v-model="allChecked"
                    type="checkbox"
                    aria-label="Select all groups"
                    class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
                  />
                </th>
                <th class="px-4 py-3.5">Item & ICS</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5 text-right">Quantity</th>
                <th v-if="workspace === 'assigned'" class="px-4 py-3.5">Assignee</th>
                <th class="px-4 py-3.5 text-right">Unit Cost</th>
                <th class="px-4 py-3.5 text-right">Total Cost</th>
                <th v-if="workspace === 'disposed'" class="px-4 py-3.5">Reason</th>
                <th v-else class="px-4 py-3.5">Condition / Status</th>
                <th v-if="workspace !== 'all'" class="px-4 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-for="row in filteredRows" :key="groupKey(row)">
                <tr
                  class="group transition-colors hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10"
                  :class="{ 'bg-emerald-50/40 dark:bg-emerald-950/20': recordGroup?.item_id === row.item_id }"
                >
                  <!-- Checkbox -->
                  <td class="px-4 py-3.5">
                    <input
                      :checked="isGroupSelected(row)"
                      type="checkbox"
                      :aria-label="`Select ${row.item_name}`"
                      class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
                      @change="toggleGroupSelection(row, $event.target.checked)"
                    />
                  </td>

                  <!-- Item Name + ICS -->
                  <td class="px-4 py-3.5">
                    <div class="flex items-center gap-3">
                      <!-- Item Icon Badge -->
                      <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        role="img"
                        :aria-label="`${row.item_name} icon`"
                        v-html="itemIconMarkup(row)"
                      >
                      </div>
                      <div class="min-w-0">
                        <button
                          type="button"
                          class="font-semibold text-gray-900 transition-colors hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400"
                          :aria-expanded="recordGroup?.item_id === row.item_id"
                          @click="openRecordModal(row)"
                        >
                          {{ row.item_name }}
                        </button>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                          <span
                            v-if="row.ics_no"
                            class="rounded-md bg-emerald-50 px-1.5 py-0.5 font-mono text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                          >
                            {{ row.ics_no }}
                          </span>
                          <span v-else class="text-[11px] text-gray-400 dark:text-gray-500">No ICS</span>
                          <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                          <span class="text-[11px] text-gray-500 dark:text-gray-400">
                            Acquired {{ formatDate(row.date_acquired) }}
                          </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                          <button
                            v-if="lifespanRemainingFraction(row) !== null"
                            type="button"
                            class="flex w-20 items-center rounded-full py-1 transition-opacity hover:opacity-75 focus-visible:outline-2 focus-visible:outline-emerald-500"
                            :title="lifespanBarTitle(row)"
                            :aria-label="`${lifespanBarTitle(row)}. Show lifespan details.`"
                            @click="openRecordModal(row)"
                          >
                            <span class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                              <span
                                class="block h-full min-w-[3px] rounded-full"
                                :class="lifespanBarFillClass(row)"
                                :style="{ width: lifespanBarWidth(row) }"
                              ></span>
                            </span>
                          </button>
                          <button
                            v-else-if="lifespanChip(row)"
                            type="button"
                            class="rounded-md px-1.5 py-0.5 font-medium transition-colors"
                            :class="lifespanChipClass(row)"
                            title="Show lifespan details"
                            @click="openRecordModal(row)"
                          >
                            {{ lifespanChip(row) }}
                          </button>
                          <span
                            v-for="meta in [groupMeta(row)]"
                            :key="meta.key"
                            class="flex flex-wrap items-center gap-1.5"
                          >
                          <span
                            v-if="meta.count > 1"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300"
                          >
                            {{ meta.count }} units
                          </span>
                          <span
                            v-if="meta.invNo"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700 dark:bg-white/5 dark:text-gray-300"
                          >
                            {{ meta.invNo }}
                          </span>
                          <span
                            v-if="meta.serial"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-gray-600 dark:bg-white/5 dark:text-gray-400"
                          >
                            SN {{ meta.serial }}
                          </span>
                          </span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Category -->
                  <td class="px-4 py-3.5">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                      {{ row.category?.category_name ?? 'Uncategorized' }}
                    </span>
                  </td>

                  <!-- Quantity -->
                  <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">
                    <template v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'">
                      <span v-if="workspace !== 'assigned'">Assigned: </span>{{ format(assignedOutQty(row)) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                      <span v-if="(row.pending_hold ?? 0) > 0" class="block text-[11px] font-semibold text-amber-600 dark:text-amber-300">
                        Pending: {{ format(row.pending_hold ?? 0) }}
                      </span>
                    </template>
                    <template v-else-if="row.status === 'available' && workspace !== 'all'">
                      Available: {{ format(row.free_quantity ?? row.quantity) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                      <span class="block text-[11px] font-semibold" :class="(row.pending_hold ?? 0) > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400 dark:text-gray-500'">
                        Pending: {{ format(row.pending_hold ?? 0) }}
                      </span>
                    </template>
                    <template v-else>
                      {{ format(row.quantity) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                    </template>
                  </td>

                  <!-- Assignee -->
                  <td v-if="workspace === 'assigned'" class="px-4 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ rowAssignees(row) || '—' }}
                  </td>

                  <!-- Unit Cost -->
                  <td class="px-4 py-3.5 text-right font-medium text-gray-600 dark:text-gray-300">
                    ₱{{ money(row.unit_cost) }}
                  </td>

                  <!-- Total Cost -->
                  <td class="px-4 py-3.5 text-right font-semibold text-gray-900 dark:text-white">
                    ₱{{ money(row.total_cost) }}
                  </td>

                  <!-- Status (disposed workspace shows the disposal reason instead) -->
                  <td class="px-4 py-3.5">
                    <div v-if="workspace === 'disposed'" class="max-w-64">
                      <p v-if="disposalReason(row)" class="truncate text-xs text-gray-700 dark:text-gray-300" :title="disposalReason(row)">
                        {{ disposalReason(row) }}
                      </p>
                      <p v-else class="text-[11px] text-gray-400 dark:text-gray-500">
                        No reason recorded.
                      </p>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-1.5">
                      <StatusBadge :status="displayStatus(row)" />
                      <span v-if="hasRepairRecommendation(row)" class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Repair recommended</span>
                    </div>
                  </td>

                  <!-- Actions: display-only under All Inventory -->
                  <td v-if="workspace !== 'all'" class="px-4 py-3.5 text-right">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- Details Toggle -->
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                        @click="openRecordModal(row)"
                      >
                        Records
                        <svg
                          class="h-3.5 w-3.5 transition-transform duration-200"
                          :class="{ 'rotate-180': recordGroup?.item_id === row.item_id }"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      <button
                        v-if="displayStatus(row) === 'assigned'"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-indigo-200 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                        title="Record the return of an assigned item"
                        @click="openReturnModal(row)"
                      >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-1" />
                        </svg>
                        Return
                      </button>
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row) && hasMaintenanceEligibleItems(row)"
                        type="button"
                        class="rounded-md p-1.5 text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10"
                        title="Send selected items for repair"
                        aria-label="Send items for repair"
                        @click="openMaintenanceModal(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-7 7a2.1 2.1 0 0 1-3-3l7-7a6 6 0 0 1 7.9-7.9z" />
                        </svg>
                      </button>
                      <button
                        v-if="isInspectable(row.status) && hasInspectableItems(row)"
                        type="button"
                        class="rounded-md p-1.5 text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-sky-500/10"
                        title="Send selected items for inspection"
                        aria-label="Send items for inspection"
                        @click="openInspectionModal(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0z" />
                        </svg>
                      </button>
                      <template v-if="row.status === 'under_repair'">
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                          title="Mark selected items as repaired"
                          aria-label="Mark items as repaired"
                          @click="openMaintenanceActionModal(row, 'repair')"
                        >
                          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                            <circle cx="12" cy="12" r="10" />
                          </svg>
                        </button>
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-orange-700 hover:bg-orange-50 dark:text-orange-300 dark:hover:bg-orange-500/10"
                          title="Mark selected items ready to dispose"
                          aria-label="Mark items ready to dispose"
                          @click="openMaintenanceActionModal(row, 'dispose')"
                        >
                          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7l1 14h12l1-14M9 7V4h6v3m-5 4v6m4-6v6" />
                          </svg>
                        </button>
                      </template>
                      <button
                        v-if="row.status === 'ready_to_dispose'"
                        type="button"
                        class="rounded-md p-1.5 text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                        title="Dispose selected items"
                        aria-label="Dispose items"
                        @click="openDisposeModal(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15m-7 4v7m4-7v7" />
                        </svg>
                      </button>

                      <!-- Edit Button -->
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row)"
                        type="button"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 dark:text-gray-400 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                        title="Edit inventory group"
                        @click="askEdit(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                      </button>

                      <!-- Delete Button -->
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row)"
                        type="button"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-700 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                        title="Delete inventory group"
                        @click="askDelete(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Empty State -->
              <tr v-if="!filteredRows.length">
                <td :colspan="workspace === 'all' ? 7 : workspace === 'assigned' ? 9 : 8" class="px-4 py-16 text-center text-sm text-gray-500 dark:text-gray-400">
                  <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-md bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                  </div>
                  <p class="font-semibold text-gray-800 dark:text-white">No inventory groups found</p>
                  <p class="mt-1 text-xs text-gray-400">Try adjusting your search or category filters.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
          </div>
        </Transition>
        <InventoryItemLogDrawer
          :open="logsOpen"
          :workspace="workspace"
          :search="search"
          :category-id="categoryFilter"
          :categories="categories"
          :workspace-label="activeWorkspaceTab.label"
          @close="logsOpen = false"
        />

        <!-- Pagination & Footer -->
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
          <Pagination
            class="ml-auto"
            :current-page="paginator.current_page ?? 1"
            :last-page="paginator.last_page ?? 1"
            :from="paginator.from"
            :to="paginator.to"
            :total="paginator.total ?? 0"
            @page="goToPage"
          />
        </div>
      </div>
    </template>

    <Modal
      :open="recordGroup !== null"
      :title="recordGroup ? `${recordGroup.item_name} details` : 'Inventory details'"
      :subtitle="recordGroup ? `${recordGroup.sourceItems?.length ?? 0} source unit record(s) · ${recordGroup.category?.category_name ?? 'Uncategorized'}` : ''"
      max-width="max-w-4xl"
      fixed-body
      @close="recordGroup = null"
    >
      <div v-if="recordGroup" class="flex min-h-0 flex-1 flex-col space-y-3">
        <section class="shrink-0 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
          <div class="flex flex-wrap items-start justify-between gap-2.5">
            <div>
              <h3 class="font-semibold text-sm text-gray-900 dark:text-white">{{ recordGroup.item_name }}</h3>
              <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                {{ recordGroup.category?.category_name ?? 'Uncategorized' }}
                <span class="mx-1" aria-hidden="true">·</span>
                {{ recordGroup.ics_no ? `ICS: ${recordGroup.ics_no}` : 'No ICS' }}
              </p>
            </div>
            <StatusBadge :status="displayStatus(recordGroup)" />
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-gray-200 pt-2.5 text-xs dark:border-gray-700 sm:grid-cols-3">
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Quantity</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">{{ format(recordGroup.quantity) }} {{ recordGroup.unit }}</dd>
            </div>
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Unit Cost</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">₱{{ money(recordGroup.unit_cost) }}</dd>
            </div>
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Total Cost</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">₱{{ money(recordGroup.total_cost) }}</dd>
            </div>
            <div v-if="lifespanLabel(recordGroup) || lifespanEndLabel(recordGroup)">
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Lifespan</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">
                <span v-if="lifespanLabel(recordGroup)">{{ lifespanLabel(recordGroup) }}</span>
                <span v-if="lifespanLabel(recordGroup) && lifespanEndLabel(recordGroup)" class="mx-1" aria-hidden="true">·</span>
                <span v-if="lifespanEndLabel(recordGroup)" :class="lifespanEndClass(recordGroup)">
                  {{ lifespanEndLabel(recordGroup) }}
                </span>
              </dd>
            </div>
          </dl>
          <div class="mt-3 flex shrink-0 flex-wrap gap-1.5 border-t border-gray-200 pt-2.5 dark:border-gray-700 xl:hidden">
            <button
              v-if="displayStatus(recordGroup) === 'assigned'"
              type="button"
              class="rounded-md border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
              @click="openReturnModal(recordGroup)"
            >
              Return
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup) && hasMaintenanceEligibleItems(recordGroup)"
              type="button"
              class="rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-300 dark:hover:bg-amber-500/10"
              @click="openMaintenanceModal(recordGroup)"
            >
              Send for Repair
            </button>
            <button
              v-if="isInspectable(recordGroup.status) && hasInspectableItems(recordGroup)"
              type="button"
              class="rounded-md border border-sky-200 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50 dark:border-sky-500/30 dark:text-sky-300 dark:hover:bg-sky-500/10"
              @click="openInspectionModal(recordGroup)"
            >
              Send to Inspection
            </button>
            <template v-if="recordGroup.status === 'under_repair'">
              <button
                type="button"
                class="rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                @click="openMaintenanceActionModal(recordGroup, 'repair')"
              >
                Mark Repaired
              </button>
              <button
                type="button"
                class="rounded-md border border-orange-200 px-2.5 py-1.5 text-xs font-semibold text-orange-700 hover:bg-orange-50 dark:border-orange-500/30 dark:text-orange-300 dark:hover:bg-orange-500/10"
                @click="openMaintenanceActionModal(recordGroup, 'dispose')"
              >
                Ready to Dispose
              </button>
            </template>
            <button
              v-if="recordGroup.status === 'ready_to_dispose'"
              type="button"
              class="rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
              @click="openDisposeModal(recordGroup)"
            >
              Dispose
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup)"
              type="button"
              class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
              @click="askEdit(recordGroup)"
            >
              Edit
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup)"
              type="button"
              class="rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
              @click="askDelete(recordGroup)"
            >
              Delete
            </button>
          </div>
        </section>

        <div
          v-if="qrSources.length"
          class="flex shrink-0 flex-wrap items-center justify-between gap-2 rounded-md border border-blue-200 bg-blue-50/70 p-2.5 dark:border-blue-500/30 dark:bg-blue-500/5"
        >
          <label class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
            <input
              type="checkbox"
              :checked="allPrintChecked"
              aria-label="Select all QR codes"
              class="h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
              @change="toggleAllPrint($event.target.checked)"
            />
            Select all QR codes
          </label>
          <button
            type="button"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="selectedPrintItemIds.length === 0 || printingQr"
            @click="openPrintSelected"
          >
            <LucideIcon :icon="Printer" class="h-3.5 w-3.5" />
            {{ printingQr ? 'Preparing labels…' : `Print selected (${selectedPrintItemIds.length})` }}
          </button>
        </div>

        <template v-if="recordGroup.status === 'disposed'">
          <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
            Disposal history and identifiers for each disposed unit.
          </p>
          <div class="min-h-[6rem] space-y-2 overflow-y-auto">
            <article
              v-for="source in recordGroup.sourceItems ?? []"
              :key="source.item_id"
              class="rounded-md border border-gray-200 p-3 dark:border-gray-700"
            >
              <div class="flex flex-wrap items-start justify-between gap-2.5">
                <div class="flex items-start gap-2.5">
                  <input
                    v-if="source.qr_code"
                    v-model="selectedPrintItemIds"
                    :value="source.item_id"
                    type="checkbox"
                    :aria-label="`Select QR code for ${source.inventory_item_no ?? `item ${source.item_id}`}`"
                    class="mt-1 h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                  />
                  <div class="space-y-0.5">
                    <p class="font-mono text-xs font-bold text-gray-900 dark:text-white">
                      {{ source.inventory_item_no ?? `#${source.item_id}` }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                      Serial: <span class="font-mono">{{ source.serial_number || 'Not recorded' }}</span>
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                      Quantity: {{ format(source.quantity) }} {{ source.unit }}
                    </p>
                  </div>
                </div>
                <span class="inline-flex rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                  Disposed
                </span>
              </div>

              <dl class="mt-3 grid gap-2 border-t border-gray-100 pt-2.5 text-xs dark:border-gray-700 sm:grid-cols-2">
                <div>
                  <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Disposed on</dt>
                  <dd class="mt-0.5 text-gray-800 dark:text-gray-200">
                    {{ formatMovementDate(source.disposalMovement ?? source.latestDisposalMovement ?? source.disposal_movement ?? source.latest_disposal_movement) }}
                  </dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Reason / notes</dt>
                  <dd class="mt-0.5 whitespace-pre-wrap text-gray-800 dark:text-gray-200">
                    {{ (source.disposalMovement ?? source.latestDisposalMovement ?? source.disposal_movement ?? source.latest_disposal_movement)?.notes || 'No reason or notes recorded.' }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>
        </template>

        <template v-else>
          <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Storage: {{ recordGroup.building || 'Main Building' }}{{ recordGroup.room ? `, Room ${recordGroup.room}` : '' }}
            </p>
          </div>

          <div class="min-h-[6rem] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
            <div
              v-for="source in recordGroup.sourceItems ?? []"
              :key="source.item_id"
              class="flex flex-wrap items-center justify-between gap-2 p-2.5 text-xs"
            >
              <div class="flex flex-wrap items-center gap-2">
                <input
                  v-if="source.qr_code"
                  v-model="selectedPrintItemIds"
                  :value="source.item_id"
                  type="checkbox"
                  :aria-label="`Select QR code for ${source.inventory_item_no ?? `item ${source.item_id}`}`"
                  class="h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                />
                <span class="font-mono font-bold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span
                  v-if="source.serial_number"
                  class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-white/5 dark:text-gray-300"
                >
                  SN: {{ source.serial_number }}
                </span>
                <span v-else class="text-[11px] text-gray-400">No serial</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ format(sourceQuantity(source)) }} {{ source.unit }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                  {{ source.active_assignee_summary || source.assigned_to?.full_name || source.assignedTo?.full_name || source.assigned_to?.username || source.assignedTo?.username || 'Stockroom' }}
                </span>
                <span
                  v-if="(source.receive_return_assignments ?? []).length"
                  class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                >
                  {{ source.receive_return_assignments.length }} pending return{{ source.receive_return_assignments.length > 1 ? 's' : '' }}
                </span>
                <div
                  v-if="openIssueReport(source)"
                  class="flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-md border border-rose-200 bg-rose-50/70 px-2 py-1.5 dark:border-rose-500/30 dark:bg-rose-500/5"
                >
                  <span class="text-[11px] font-bold capitalize text-rose-800 dark:text-rose-200">Reported {{ openIssueReport(source).diagnosis }}</span>
                  <span class="text-[11px] text-rose-700 dark:text-rose-300">
                    by {{ openIssueReport(source).reporter }}<span v-if="openIssueReport(source).notes"> · {{ openIssueReport(source).notes }}</span>
                  </span>
                  <span class="ml-auto inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-md border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
                      :disabled="reportDecisionId !== null"
                      @click="declineIssueReport(source)"
                    >
                      {{ reportDecisionId === openIssueReport(source).id ? 'Declining…' : 'Decline' }}
                    </button>
                  </span>
                </div>
                <div
                  v-if="source.repairRecommendation"
                  class="flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-md border border-amber-200 bg-amber-50/70 px-2 py-1.5 dark:border-amber-500/30 dark:bg-amber-500/5"
                >
                  <span class="text-[11px] font-bold text-amber-800 dark:text-amber-200">Repair recommended</span>
                  <span class="text-[11px] text-amber-700 dark:text-amber-300">
                    by {{ source.repairRecommendation.inspected_by }}<span v-if="source.repairRecommendation.reason"> · {{ source.repairRecommendation.reason }}</span>
                  </span>
                  <span class="ml-auto inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-md bg-amber-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="repairDecisionId !== null"
                      @click="decideRepairRecommendation(source, 'approve')"
                    >
                      {{ repairDecisionId === source.repairRecommendation.inspection_id ? 'Approving…' : 'Approve' }}
                    </button>
                    <button
                      type="button"
                      class="rounded-md border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
                      :disabled="repairDecisionId !== null"
                      @click="decideRepairRecommendation(source, 'decline')"
                    >
                      Decline
                    </button>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </Modal>

    <Modal
      :open="maintenanceGroup !== null"
      :title="maintenanceGroup ? `Send ${maintenanceGroup.item_name} for repair` : 'Send items for repair'"
      subtitle="Select the available item records to send, then describe the issue."
      max-width="max-w-xl"
      fixed-body
      @close="closeMaintenanceModal"
    >
      <div v-if="maintenanceGroup" class="flex min-h-0 flex-1 flex-col space-y-3">
        <p
          v-if="maintenanceError"
          role="alert"
          class="shrink-0 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ maintenanceError }}
        </p>

        <div class="min-h-[6rem] max-h-[38vh] shrink divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in maintenanceEligibleItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="maintenanceItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="sendingToMaintenance"
                class="h-4 w-4 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span>{{ format(source.quantity) }} {{ source.unit }}</span>
              <span class="rounded-md bg-emerald-50 px-2 py-1 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                Available
              </span>
            </span>
          </label>
          <p v-if="!maintenanceEligibleItems.length" class="p-4 text-sm text-gray-500">
            This group has no eligible available item records.
          </p>
        </div>

        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
          {{ maintenanceItemIds.length }} item record(s) selected.
        </p>

        <div class="shrink-0 space-y-1.5">
          <label for="maintenance-issue" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Issue description <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="maintenance-issue"
            v-model="maintenanceIssue"
            rows="2"
            required
            maxlength="1000"
            :disabled="sendingToMaintenance"
            placeholder="Describe the problem or repair needed"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

        <div class="shrink-0 space-y-1.5">
          <label for="maintenance-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Additional notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="maintenance-notes"
            v-model="maintenanceNotes"
            rows="2"
            maxlength="500"
            :disabled="sendingToMaintenance"
            placeholder="Add any relevant details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="sendingToMaintenance"
            @click="closeMaintenanceModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="sendingToMaintenance || maintenanceItemIds.length === 0 || !maintenanceIssue.trim()"
            @click="sendSelectedToMaintenance"
          >
            {{ sendingToMaintenance ? 'Sending…' : `Send ${maintenanceItemIds.length} for Repair` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="inspectionGroup !== null"
      :title="inspectionGroup ? `Send ${inspectionGroup.item_name} for inspection` : 'Send items for inspection'"
      subtitle="Flag the selected records so the inspector can verify them, then mark each one inspected."
      max-width="max-w-xl"
      fixed-body
      @close="closeInspectionModal"
    >
      <div v-if="inspectionGroup" class="flex min-h-0 flex-1 flex-col space-y-3">
        <p
          v-if="inspectionError"
          role="alert"
          class="shrink-0 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ inspectionError }}
        </p>

        <div class="min-h-[6rem] max-h-[38vh] shrink divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in inspectableItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="inspectionItemIds"
                :value="source.item_id"
                type="checkbox"
                :disabled="sendingToInspection"
                class="h-4 w-4 rounded-md border-gray-300 text-sky-600 focus:ring-sky-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span>{{ format(sourceQuantity(source)) }} {{ source.unit }}</span>
              <span class="rounded-md bg-sky-50 px-2 py-1 font-medium capitalize text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                {{ (source.status ?? '').replace(/_/g, ' ') }}
              </span>
            </span>
          </label>
          <p v-if="!inspectableItems.length" class="p-4 text-sm text-gray-500">
            This group has no records that can be flagged for inspection.
          </p>
        </div>

        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
          {{ inspectionItemIds.length }} item record(s) selected.
        </p>

        <div class="shrink-0 space-y-1.5">
          <label for="inspection-reason" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Reason for inspection <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="inspection-reason"
            v-model="inspectionReason"
            rows="2"
            maxlength="500"
            :disabled="sendingToInspection"
            placeholder="e.g. Quarterly verification, reported defect, custody audit"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="sendingToInspection"
            @click="closeInspectionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="sendingToInspection || inspectionItemIds.length === 0"
            @click="sendSelectedToInspection"
          >
            {{ sendingToInspection ? 'Sending…' : `Flag ${inspectionItemIds.length} for Inspection` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="disposeGroup !== null"
      :title="disposeGroup ? `Dispose ${disposeGroup.item_name}` : 'Dispose inventory items'"
      subtitle="Review and select the ready-to-dispose records to permanently dispose."
      max-width="max-w-2xl"
      @close="closeDisposeModal"
    >
      <div v-if="disposeGroup" class="space-y-4">
        <p
          v-if="disposeError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ disposeError }}
        </p>

        <div class="max-h-[45vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in disposeEligibleItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="disposeItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="disposing"
                class="h-4 w-4 rounded-md border-gray-300 text-rose-600 focus:ring-rose-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span class="rounded-md bg-orange-50 px-2 py-1 font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-300">
                Ready to dispose
              </span>
            </span>
          </label>
          <p v-if="!disposeEligibleItems.length" class="p-4 text-sm text-gray-500">
            No records are currently ready for disposal in this group.
          </p>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ disposeItemIds.length }} item record(s) selected. This action cannot be undone.
        </p>

        <div class="space-y-1.5">
          <label for="dispose-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Disposal notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="dispose-notes"
            v-model="disposeNotes"
            rows="2"
            maxlength="500"
            :disabled="disposing"
            placeholder="Add disposal or approval reference details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="disposing"
            @click="closeDisposeModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disposing || disposeItemIds.length === 0"
            @click="confirmDispose"
          >
            {{ disposing ? 'Disposing…' : `Confirm Dispose ${disposeItemIds.length} Selected` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="maintenanceActionGroup !== null"
      :title="maintenanceActionTitle"
      :subtitle="maintenanceAction === 'repair' ? 'Select the item records to complete. Rows still with their holder return to the assignee.' : 'Select the item records to mark for disposal.'"
      max-width="max-w-2xl"
      @close="closeMaintenanceActionModal"
    >
      <div v-if="maintenanceActionGroup" class="space-y-4">
        <p
          v-if="maintenanceActionError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ maintenanceActionError }}
        </p>

        <div class="max-h-[45vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in actionableMaintenanceActionItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="maintenanceActionItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="maintenanceActionBusy"
                class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span v-if="Number(source.quantity ?? 0) > 0">{{ format(source.quantity) }} {{ source.unit }}</span>
              <span
                v-if="Number(source.quantity ?? 0) <= 0"
                class="rounded-md bg-indigo-50 px-2 py-1 font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                title="The units are still issued out — completing returns the row to the assignee"
              >
                With assignee
              </span>
              <span
                v-else
                class="rounded-md bg-amber-50 px-2 py-1 font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
              >
                Under repair
              </span>
            </span>
          </label>
          <p v-if="!actionableMaintenanceActionItems.length" class="p-4 text-sm text-gray-500">
            {{ heldMaintenanceActionCount > 0
              ? 'No actionable records here — the rest have neither stock nor holder.'
              : 'No item records are currently under repair in this group.' }}
          </p>
        </div>

        <p v-if="heldMaintenanceActionCount > 0" class="text-xs text-gray-500 dark:text-gray-400">
          {{ heldMaintenanceActionCount }} record{{ heldMaintenanceActionCount > 1 ? 's have' : ' has' }} neither stock nor holder and cannot be completed.
        </p>

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ maintenanceActionItemIds.length }} item record(s) selected.</p>

        <template v-if="maintenanceAction === 'repair'">
          <div class="space-y-1.5">
            <label for="maintenance-repair-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Repair notes <span class="font-normal text-gray-500">(optional)</span>
            </label>
            <textarea
              id="maintenance-repair-notes"
              v-model="maintenanceRepairNotes"
              rows="2"
              maxlength="1000"
              :disabled="maintenanceActionBusy"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            />
          </div>
          <div class="space-y-1.5">
            <label for="maintenance-cost" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Repair cost <span class="font-normal text-gray-500">(optional)</span>
            </label>
            <input
              id="maintenance-cost"
              v-model.number="maintenanceCost"
              type="number"
              min="0"
              step="0.01"
              :disabled="maintenanceActionBusy"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            />
          </div>
        </template>
        <div v-else class="space-y-1.5">
          <label for="maintenance-disposal-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Reason for disposal <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="maintenance-disposal-notes"
            v-model="maintenanceDisposalNotes"
            rows="3"
            required
            maxlength="500"
            :disabled="maintenanceActionBusy"
            placeholder="Explain why the item should be disposed"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="maintenanceActionBusy"
            @click="closeMaintenanceActionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
            :class="maintenanceAction === 'repair' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-orange-600 hover:bg-orange-700'"
            :disabled="maintenanceActionBusy || maintenanceActionItemIds.length === 0 || (maintenanceAction === 'dispose' && !maintenanceDisposalNotes.trim())"
            @click="confirmMaintenanceAction"
          >
            {{ maintenanceActionBusy ? 'Updating…' : maintenanceAction === 'repair' ? `Confirm ${maintenanceActionItemIds.length} Repaired` : `Confirm ${maintenanceActionItemIds.length} for Disposal` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="returnGroup !== null"
      :title="returnFromQr ? 'Confirm item return' : returnGroup ? `Return ${returnGroup.item_name}` : 'Return assigned item'"
      :subtitle="returnFromQr ? 'Confirm that you want to record this item as returned.' : 'Review the assignment details before recording the return.'"
      max-width="max-w-xl"
      @close="closeReturnModal"
    >
      <div v-if="returnGroup" class="space-y-5">
        <p
          v-if="returnFromQr && selectedReturnAssignment"
          class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
        >
          Are you sure you want to return {{ returnGroup.item_name }} ({{ format(selectedReturnAssignment.remaining_quantity) }} {{ selectedReturnAssignment.unit }}) assigned to {{ selectedReturnAssignment.recipient }}?
        </p>
        <p
          v-if="returnError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ returnError }}
        </p>

        <div v-if="returnAssignments.length > 1" class="space-y-1.5">
          <label for="return-assignment-select" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Assignment to return
          </label>
          <select
            id="return-assignment-select"
            v-model="returnTransactionId"
            :disabled="returning"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option
              v-for="assignment in returnAssignments"
              :key="assignment.transaction_id"
              :value="String(assignment.transaction_id)"
            >
              {{ assignment.recipient }} · {{ assignment.remaining_quantity }} {{ assignment.unit }} remaining
            </option>
          </select>
        </div>

        <dl v-if="selectedReturnAssignment" class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800/60 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Assigned to</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.recipient }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Assignment date</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.transaction_date || '—' }}</dd>
          </div>
          <div v-if="selectedReturnAssignment.expected_return_date">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Expected return date</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.expected_return_date }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Inventory record</dt>
            <dd class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-white">
              {{ selectedReturnAssignment.inventory_item_no ?? `#${selectedReturnAssignment.inventory_id}` }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Serial number</dt>
            <dd class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.serial_number || 'Not recorded' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Outstanding quantity</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ format(selectedReturnAssignment.remaining_quantity) }} {{ selectedReturnAssignment.unit }}
              <span class="text-xs font-normal text-gray-500">(full quantity will be returned)</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Current location</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ selectedReturnAssignment.building || 'Unspecified' }}{{ selectedReturnAssignment.room ? `, Room ${selectedReturnAssignment.room}` : '' }}
            </dd>
          </div>
        </dl>
        <div
          v-else
          role="status"
          class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
          No active returnable assignment transaction was found for this inventory group. Refresh the inventory and verify its assignment record before recording a return.
        </div>

        <div class="space-y-1.5">
          <label for="inventory-return-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Return notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="inventory-return-notes"
            v-model="returnNotes"
            rows="2"
            maxlength="1000"
            :disabled="returning"
            placeholder="Condition or other return details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="returning"
            @click="closeReturnModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="returning || !selectedReturnAssignment"
            @click="confirmReturn"
          >
            {{ returning ? 'Recording return…' : 'Confirm Return' }}
          </button>
        </div>
      </template>
    </Modal>

    <QrScannerModal
        :open="qrScannerOpen"
        title="Scan Inventory QR"
        subtitle="Point the camera at a DNHS inventory label, or enter its token."
        :resolved="Boolean(qrResult)"
        :lookup-error="qrError"
        :loading="qrLookupLoading"
        viewport-id="custodian-qr-camera-viewport"
        @close="closeQrScanner"
        @lookup="lookupQrToken"
      >
        <section class="rounded-md border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10" aria-live="polite">
          <div class="mb-4 flex items-center gap-2 text-emerald-800 dark:text-emerald-300">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <h3 class="text-sm font-semibold">Item found</h3>
          </div>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Item no.</dt><dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ qrResult?.inventory_item_no }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ qrResult?.item_name }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Category</dt><dd class="text-right text-gray-700 dark:text-gray-300">{{ qrResult?.category || 'Uncategorized' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="capitalize text-gray-700 dark:text-gray-300">{{ (qrResult?.status || '').replace(/_/g, ' ') }}</dd></div>
            <div v-if="qrResult?.serial_number" class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Serial no.</dt><dd class="font-mono text-gray-700 dark:text-gray-300">{{ qrResult.serial_number }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Quantity</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ format(qrResult?.quantity) }} {{ qrResult?.unit }}</dd></div>
            <div v-if="qrResult?.status === 'assigned' && qrResult?.assigned_to" class="flex justify-between gap-4">
              <dt class="text-gray-500 dark:text-gray-400">Assigned to</dt>
              <dd class="text-right font-medium text-gray-900 dark:text-white">{{ qrResult.assigned_to }}</dd>
            </div>
          </dl>
        </section>
        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <button
              v-if="qrResult?.status === 'assigned'"
              type="button"
              class="rounded-md border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
              :disabled="!qrResult?.return_assignments?.length"
              :title="qrResult?.return_assignments?.length ? '' : 'No active return assignment was found.'"
              @click="openQrReturnModal"
            >
              Return
            </button>
            <button type="button" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="scanAnotherQrCode">
              Scan another
            </button>
          </div>
        </template>
      </QrScannerModal>

    <!-- Print QR Codes modal: lists QR-coded items across the current workspace -->
    <Modal
      :open="qrPrintOpen"
      title="Print QR Codes"
      subtitle="Select item records that have a QR code, then print stickers."
      max-width="max-w-3xl"
      fixed-body
      @close="closeQrPrintModal"
    >
      <div class="flex min-h-0 flex-1 flex-col space-y-2">
        <!-- Search (always visible) -->
        <div class="relative w-full shrink-0">
          <svg
            class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="qrPrintSearch"
            type="search"
            placeholder="Search item no., name, category, serial…"
            aria-label="Search QR-coded items"
            :disabled="qrPrintLoading"
            class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 pl-7 pr-7 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
          />
        </div>

        <!-- Loading skeleton -->
        <div v-if="qrPrintLoading" class="min-h-[12rem] max-h-[60vh] shrink overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700" aria-hidden="true">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <th class="w-10 px-3 py-2.5">
                  <div class="h-4 w-4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                </th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-4 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5 text-right"><div class="h-3 w-8 animate-pulse rounded-md bg-gray-100 dark:bg-white/5 ml-auto" /></th>
                <th class="px-3 py-2.5 text-center" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="row in 4" :key="row">
                <td class="px-3 py-2.5"><div class="h-4 w-4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-24 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-4 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5 text-right"><div class="h-3 w-8 animate-pulse rounded-md bg-gray-100 dark:bg-white/5 ml-auto" /></td>
                <td class="px-3 py-2.5" />
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Error state -->
        <div v-else-if="qrPrintError" class="p-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400">
          {{ qrPrintError }}
          <button
            type="button"
            class="mt-2 text-xs font-semibold underline hover:text-rose-900 dark:hover:text-rose-200"
            @click="loadQrPrintItems"
          >
            Try again
          </button>
        </div>

        <!-- Real table + footer -->
        <template v-else>
          <div class="min-h-[12rem] max-h-[60vh] shrink divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700 dark:bg-gray-900/5">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  <th class="w-10 px-3 py-2.5">
                    <input
                      type="checkbox"
                      :checked="allQrPrintChecked"
                      :aria-label="filteredQrPrintItems.length ? 'Select all' : 'Select all (none available)'"
                      :disabled="!filteredQrPrintItems.length"
                      class="h-4 w-4 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                      @change="toggleAllQrPrint($event.target.checked)"
                    />
                  </th>
                  <th class="px-3 py-2.5 font-mono">Item No.</th>
                  <th class="px-3 py-2.5">Item Name</th>
                  <th class="px-3 py-2.5">Category</th>
                  <th class="px-3 py-2.5">Serial</th>
                  <th class="px-3 py-2.5 text-center">Status</th>
                  <th class="px-3 py-2.5 text-right">Qty</th>
                  <th class="px-3 py-2.5 text-center" />
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                <tr
                  v-for="item in filteredQrPrintItems"
                  :key="item.item_id"
                  class="hover:bg-gray-50/40 dark:hover:bg-white/5"
                >
                  <td class="px-3 py-2.5">
                    <input
                      v-model="qrPrintSelected"
                      :value="item.item_id"
                      type="checkbox"
                      :aria-label="`Select ${item.inventory_item_no || item.item_name}`"
                      class="h-4 w-4 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                    />
                  </td>
                  <td class="px-3 py-2.5 font-mono text-gray-900 dark:text-white truncate max-w-[8rem]" :title="item.inventory_item_no || `#${item.item_id}`">
                    {{ item.inventory_item_no || `#${item.item_id}` }}
                  </td>
                  <td class="px-3 py-2.5 text-gray-900 dark:text-white truncate">{{ item.item_name }}</td>
                  <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 truncate">{{ item.category.category_name }}</td>
                  <td class="px-3 py-2.5 font-mono text-gray-600 dark:text-gray-400">{{ item.serial_number || '—' }}</td>
                  <td class="px-3 py-2.5 text-center capitalize">{{ item.status?.replace(/_/g, ' ') ?? '—' }}</td>
                  <td class="px-3 py-2.5 text-right text-gray-900 dark:text-white">{{ format(item.quantity) }} {{ item.unit }}</td>
                  <td class="px-3 py-2.5 text-center" />
                </tr>
                <tr v-if="!filteredQrPrintItems.length">
                  <td :colspan="8" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    <p class="font-semibold text-gray-800 dark:text-white">No QR-coded items found</p>
                    <p class="mt-1 text-xs text-gray-400">{{ qrPrintSearch ? 'Try adjusting your search.' : 'Stock QR-eligible items first.' }}</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
      <template v-if="!qrPrintLoading && !qrPrintError" #footer>
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ qrPrintSelected.length }} of {{ filteredQrPrintItems.length }} selected
          </span>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-md border border-blue-200 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-500/30 dark:hover:bg-blue-700/80"
              :disabled="qrPrintSelected.length === 0 || printingQr || qrPrintLoading"
              @click="printQrAll"
            >
              {{ printingQr ? 'Printing…' : 'Print all' }}
            </button>
            <button
              type="button"
              class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
              @click="closeQrPrintModal"
            >
              Cancel
            </button>
          </div>
        </div>
      </template>
    </Modal>

    <Modal
      :open="exportPdfOpen"
      title="Export Inventory PDF"
      subtitle="Choose the inventory filters and ICS numbers to include."
      max-width="max-w-2xl"
      @close="closeExportPdfModal"
    >
      <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
            <span>Workspace</span>
            <select
              v-model="exportPdfWorkspace"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
              <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
            </select>
          </label>
          <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
            <span>Category</span>
            <select
              v-model="exportPdfCategory"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
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
              <button
                type="button"
                class="text-emerald-700 hover:text-emerald-800 dark:text-emerald-300"
                :disabled="!filteredExportPdfIcs.length"
                @click="toggleVisibleExportIcs(true)"
              >
                Select shown
              </button>
              <button
                type="button"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                :disabled="!exportPdfSelectedIcs.length"
                @click="exportPdfSelectedIcs = []"
              >
                Clear
              </button>
            </div>
          </div>

          <input
            v-model="exportPdfIcsSearch"
            type="search"
            placeholder="Find an exact ICS number"
            aria-label="Search ICS numbers"
            class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />

          <div
            v-if="exportPdfIcsLoading"
            class="max-h-48 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
            aria-label="Loading ICS numbers"
            aria-busy="true"
          >
            <div
              v-for="row in 4"
              :key="row"
              class="flex items-center gap-2.5 border-b border-gray-100 px-3 py-2 last:border-b-0 dark:border-gray-800"
            >
              <div class="h-4 w-4 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
              <div class="h-3 w-28 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
            </div>
          </div>
          <div v-else-if="exportPdfIcsError" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">
            {{ exportPdfIcsError }}
            <button type="button" class="ml-2 font-semibold underline" @click="loadExportPdfIcs">
              Try again
            </button>
          </div>
          <div v-else class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700">
            <label
              v-for="icsNumber in filteredExportPdfIcs"
              :key="icsNumber"
              class="flex cursor-pointer items-center gap-2.5 border-b border-gray-100 px-3 py-2 text-xs text-gray-700 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
            >
              <input
                v-model="exportPdfSelectedIcs"
                type="checkbox"
                :value="icsNumber"
                class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
              />
              <span class="font-mono">{{ icsNumber }}</span>
            </label>
            <p v-if="!filteredExportPdfIcs.length" class="px-3 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
              {{ exportPdfIcsOptions.length ? 'No ICS numbers match your search.' : 'No ICS numbers are available.' }}
            </p>
          </div>
        </section>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 pt-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="closeExportPdfModal"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="exportPdfIcsLoading || Boolean(exportPdfIcsError) || exportPdfSubmitting"
            class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            @click="exportInventoryPdf"
          >
            {{ exportPdfSubmitting ? 'Exporting…' : 'Export PDF' }}
          </button>
        </div>
      </template>
    </Modal>

    <IirupPrintModal
      :open="iirupPrintOpen"
      :categories="categories"
      :selected-ids="selection"
      :selected-count="selection.length"
      @close="iirupPrintOpen = false"
    />
    <RfqPrintModal
      :open="rfqPrintOpen"
      :selected-ids="selection"
      @close="rfqPrintOpen = false"
    />

    <!-- Modals -->
    <StockInModal :open="stockInOpen" :categories="categories" @close="stockInOpen = false" @saved="onStockInSaved" @categories-updated="categories = $event" />
    <EditInventoryModal :open="editItemId !== null" :item-id="editItemId" :categories="categories" @close="editItemId = null" @saved="onEditSaved" />

    <Modal
      :open="deleteGroup !== null"
      :title="deleteGroup ? `Delete ${deleteGroup.item_name} items` : 'Delete inventory items'"
      :subtitle="deleteGroup ? 'Select available records to delete. Items with request or assignment history must be disposed to preserve their records.' : ''"
      max-width="max-w-2xl"
      @close="closeDeleteModal"
    >
      <div v-if="deleteGroup" class="space-y-4">
        <p v-if="deleteError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
          {{ deleteError }}
        </p>

        <div class="max-h-[55vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in deleteGroup.sourceItems ?? []"
            :key="source.item_id"
            class="flex flex-wrap items-center justify-between gap-3 p-4"
            :class="source.status === 'available' ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5' : 'opacity-60'"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="deleteItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="source.status !== 'available' || deleting"
                class="h-4 w-4 rounded-md border-gray-300 text-rose-600 focus:ring-rose-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span>{{ format(source.quantity) }} {{ source.unit }}</span>
              <span class="rounded-md bg-gray-100 px-2 py-1 capitalize text-gray-600 dark:bg-white/10 dark:text-gray-300">
                {{ (source.status ?? 'unknown').replace(/_/g, ' ') }}
              </span>
            </span>
          </label>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ deleteItemIds.length }} record(s) selected. Only available inventory can be deleted.
        </p>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="deleting"
            @click="closeDeleteModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="deleting || deleteItemIds.length === 0"
            @click="doDelete"
          >
            {{ deleting ? 'Deleting…' : `Delete ${deleteItemIds.length} selected` }}
          </button>
        </div>
      </template>
    </Modal>

    <ConfirmDialog
      :open="confirmBulk"
      title="Delete selected items?"
      :message="`${selection.length} selected inventory record(s) will be deleted. Items with request or assignment history must be disposed to preserve their records.`"
      :busy="deleting"
      confirm-label="Delete"
      @cancel="confirmBulk = false"
      @confirm="doDelete"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { Armchair, BookOpen, Camera, Dumbbell, FileStack, FlaskConical, HardDrive, Monitor, Package, Printer, Projector, Wrench } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import InventoryTableSkeleton from '../../components/ui/skeletons/InventoryTableSkeleton.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';
import QrScannerModal from '../../components/ui/qr/QrScannerModal.vue';
import StockInModal from './StockInModal.vue';
import InventoryItemLogDrawer from './InventoryItemLogDrawer.vue';
import EditInventoryModal from './EditInventoryModal.vue';
import IirupPrintModal from './components/IirupPrintModal.vue';
import RfqPrintModal from './components/RfqPrintModal.vue';

const STATUSES = ['available', 'assigned', 'under_repair', 'under_inspection', 'ready_to_dispose', 'disposed'];
const ITEM_ICONS = [
  { terms: ['bond paper', 'coupon bond', 'copy paper', 'ream', 'paper', 'envelope', 'folder', 'notepad', 'notebook', 'index card', 'cartolina', 'manila paper', 'parchment', 'sticky note'], icon: FileStack },
  { terms: ['document camera', 'camera'], icon: Camera },
  { terms: ['printer', 'epson'], icon: Printer },
  { terms: ['external hard drive', 'hard drive', 'storage drive'], icon: HardDrive },
  { terms: ['projector'], icon: Projector },
  { terms: ['laptop', 'computer', 'monitor', 'desktop'], icon: Monitor },
  { terms: ['book', 'workbook'], icon: BookOpen },
  { terms: ['chair', 'desk', 'table'], icon: Armchair },
];
const CATEGORY_ICONS = [
  { terms: ['consumable', 'office suppl'], icon: FileStack },
  { terms: ['furniture'], icon: Armchair },
  { terms: ['ict', 'information technology'], icon: Monitor },
  { terms: ['office equipment'], icon: Printer },
  { terms: ['laboratory'], icon: FlaskConical },
  { terms: ['learning resource'], icon: BookOpen },
  { terms: ['sports'], icon: Dumbbell },
  { terms: ['tools', 'maintenance equipment'], icon: Wrench },
];
const STATUS_LABELS = {
  all: 'All Inventory',
  available: 'Available',
  assigned: 'Assigned',
  under_repair: 'Under Repair',
  under_inspection: 'Under Inspection',
  ready_to_dispose: 'Ready to Dispose',
  disposed: 'Disposed',
};

const workspace = ref('all');
const workspaceMenuOpen = ref(false);
const page = ref(1);
const search = ref('');
const categoryFilter = ref('');
const loading = ref(true);
const error = ref('');
const inventoryCachePage = 'custodian-inventory';

function inventoryCacheParams() {
  return { workspace: workspace.value, page: page.value };
}

function applyInventoryPayload(data) {
  metrics.value = data.inventoryMetrics ?? {};
  counts.value = data.inventoryStatusCounts ?? {};
  categories.value = data.categories ?? [];
  allPage.value = data.allInventoryPage ?? { data: [] };
  statusPages.value = data.inventoryPages ?? {};
}
const deleting = ref(false);
const stockInOpen = ref(false);
const logsOpen = ref(false);
const qrScannerOpen = ref(false);
const qrLookupLoading = ref(false);
const qrError = ref('');
const qrResult = ref(null);
const inspectionGroup = ref(null);
const inspectionItemIds = ref([]);
const inspectionReason = ref('');
const inspectionError = ref('');
const sendingToInspection = ref(false);
const editItemId = ref(null);
const confirmBulk = ref(false);
const selection = ref([]);
const recordGroup = ref(null);
const selectedPrintItemIds = ref([]);
// "Print QR Codes" header modal state: lists QR-coded items across the current workspace.
const qrPrintOpen = ref(false);
const exportPdfOpen = ref(false);
const exportPdfWorkspace = ref('all');
const exportPdfCategory = ref('');
const exportPdfIcsOptions = ref([]);
const exportPdfSelectedIcs = ref([]);
const exportPdfIcsSearch = ref('');
const exportPdfIcsLoading = ref(false);
const exportPdfIcsError = ref('');
const exportPdfSubmitting = ref(false);
const iirupPrintOpen = ref(false);
const rfqPrintOpen = ref(false);
const qrPrintItems = ref([]);
const qrPrintSelected = ref([]);
const qrPrintSearch = ref('');
const qrPrintLoading = ref(false);
const qrPrintError = ref('');
const repairDecisionId = ref(null);
const reportDecisionId = ref(null);
const maintenanceGroup = ref(null);
const maintenanceItemIds = ref([]);
const maintenanceIssue = ref('');
const maintenanceNotes = ref('');
const maintenanceError = ref('');
const sendingToMaintenance = ref(false);
const maintenanceActionGroup = ref(null);
const maintenanceAction = ref('repair');
const maintenanceActionItemIds = ref([]);
const maintenanceRepairNotes = ref('');
const maintenanceCost = ref(null);
const maintenanceDisposalNotes = ref('');
const maintenanceActionError = ref('');
const maintenanceActionBusy = ref(false);
const disposeGroup = ref(null);
const disposeItemIds = ref([]);
const disposeNotes = ref('');
const disposeError = ref('');
const disposing = ref(false);
const returnGroup = ref(null);
const returnFromQr = ref(false);
const returnTransactionId = ref('');
const returnNotes = ref('');
const returnError = ref('');
const returning = ref(false);
const deleteGroup = ref(null);
const deleteItemIds = ref([]);
const deleteError = ref('');

const metrics = ref({});
const counts = ref({});
const categories = ref([]);
const allPage = ref({ data: [] });
const statusPages = ref({});

const tabs = computed(() => [
  {
    key: 'all',
    label: STATUS_LABELS.all,
    // Assigned and Under Inspection intentionally overlap (a unit flagged while
    // still issued to a user is both), so summing the per-status badges would
    // double count. The server total is the de-duplicated figure.
    count: Number(metrics.value?.total ?? 0),
  },
  ...STATUSES.map((status) => ({ key: status, label: STATUS_LABELS[status], count: Number(counts.value?.[status] ?? 0) })),
]);

const activeWorkspaceTab = computed(() => tabs.value.find((tab) => tab.key === workspace.value) ?? tabs.value[0]);

const paginator = computed(() =>
  workspace.value === 'all' ? allPage.value ?? { data: [] } : statusPages.value?.[workspace.value] ?? { data: [] },
);

const rows = computed(() => paginator.value.data ?? []);
const maintenanceEligibleItems = computed(() => (maintenanceGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'available' && isMaintenanceEligible(source)));
// Mirrors InventoryOperationService::INSPECTABLE_STATUSES. Unlike maintenance this is
// not gated on the category, because inspection verifies rather than repairs.
const INSPECTABLE_STATUSES = ['available', 'assigned', 'under_repair', 'ready_to_dispose'];
const inspectableItems = computed(() => (inspectionGroup.value?.sourceItems ?? [])
  .filter((source) => INSPECTABLE_STATUSES.includes(source.status)));
const maintenanceActionItems = computed(() => (maintenanceActionGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'under_repair'));
// Zero-stock rows are still issued out, so they cannot be completed here.
// They stay hidden from the picker with a count note instead of a disabled
// row, which read as broken rather than informative.
const hasStockOrHolder = (source) => Number(source.quantity ?? 0) > 0 || Number(source.assigned_quantity ?? 0) > 0;
const actionableMaintenanceActionItems = computed(() =>
  maintenanceActionItems.value.filter(hasStockOrHolder));
const heldMaintenanceActionCount = computed(() =>
  maintenanceActionItems.value.filter((source) => !hasStockOrHolder(source)).length);
const disposeEligibleItems = computed(() => (disposeGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'ready_to_dispose'));
const maintenanceActionTitle = computed(() => maintenanceAction.value === 'repair'
  ? `Mark ${maintenanceActionGroup.value?.item_name ?? 'items'} as repaired`
  : `Mark ${maintenanceActionGroup.value?.item_name ?? 'items'} ready to dispose`);
const returnAssignments = computed(() => (returnGroup.value?.sourceItems ?? [])
  .flatMap((source) => source.receive_return_assignments ?? []));
const selectedReturnAssignment = computed(() =>
  returnAssignments.value.find((assignment) => String(assignment.transaction_id) === returnTransactionId.value) ?? null,
);

const filteredRows = computed(() => {
  const query = search.value.toLowerCase().trim();
  return rows.value.filter((row) => {
    if (categoryFilter.value && String(row.category_id) !== String(categoryFilter.value)) {
      return false;
    }
    if (!query) {
      return true;
    }
    return [row.item_name, row.category?.category_name, row.ics_no, row.description, rowAssignees(row)]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });
});

const filteredQrPrintItems = computed(() => {
  const query = qrPrintSearch.value.toLowerCase().trim();
  if (!query) {
    return qrPrintItems.value;
  }
  return qrPrintItems.value.filter((item) =>
    [item.inventory_item_no, item.item_name, item.category?.category_name, item.serial_number]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  );
});

const filteredExportPdfIcs = computed(() => {
  const query = exportPdfIcsSearch.value.trim().toLowerCase();
  if (!query) {
    return exportPdfIcsOptions.value;
  }
  return exportPdfIcsOptions.value.filter((icsNumber) => icsNumber.toLowerCase().includes(query));
});

const exportPdfModalHref = computed(() => {
  const params = new URLSearchParams({
    workspace: exportPdfWorkspace.value,
    category_id: exportPdfCategory.value,
  });
  exportPdfSelectedIcs.value.forEach((icsNumber) => params.append('ics_numbers[]', icsNumber));

  return `/property-custodian/inventory/export-pdf?${params.toString()}`;
});

const allChecked = computed({
  get: () => filteredRows.value.length > 0 && filteredRows.value.every((row) => isGroupSelected(row)),
  set: (checked) => {
    const ids = new Set(selection.value);
    filteredRows.value.forEach((row) => {
      groupSourceIds(row).forEach((id) => {
        if (checked) {
          ids.add(id);
        } else {
          ids.delete(id);
        }
      });
    });
    selection.value = [...ids];
  },
});

function tabBadgeClass(key, isActive) {
  if (isActive) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
  }
  switch (key) {
    case 'available':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    case 'assigned':
      return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300';
    case 'under_repair':
    case 'under_inspection':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
    case 'ready_to_dispose':
      return 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300';
    case 'disposed':
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
    default:
      return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
  }
}

// Must mirror the server-side group key (PropertyCustodianController::inventory).
// Groups collapse by (name, category, unit, status, ics_no): units sharing one
// ICS slip render as a single `N pieces` row, per-unit serials stay in sourceItems.
function groupKey(row) {
  return `${row.item_name}|${row.category_id}|${row.unit}|${row.status}|${row.ics_no ?? ''}`;
}

// A collapsed row stands for every source record in it, so list-level selection
// must carry all of its item_ids — otherwise bulk delete would only delete the
// MIN id and silently leave its slip-mates behind.
function groupSourceIds(row) {
  const sources = row?.sourceItems ?? [];
  if (sources.length) {
    return sources.map((source) => source.item_id).filter((id) => id !== undefined && id !== null);
  }
  return [row?.source_item_id].filter((id) => id !== undefined && id !== null);
}

function isGroupSelected(row) {
  const ids = groupSourceIds(row);
  return ids.length > 0 && ids.every((id) => selection.value.includes(id));
}

function toggleGroupSelection(row, checked) {
  const ids = new Set(selection.value);
  groupSourceIds(row).forEach((id) => {
    if (checked) {
      ids.add(id);
    } else {
      ids.delete(id);
    }
  });
  selection.value = [...ids];
}

// Structured identifiers for a collapsed row. Returning an object instead of a
// pre-joined string lets each chip be styled on its own; the template evaluates
// this once per row via a single-element v-for.
function groupMeta(row) {
  const sourceItems = row?.sourceItems ?? [];
  const source = sourceItems[0] ?? null;
  const count = Number(row?.source_count ?? sourceItems.length ?? 0);

  return {
    key: `${row?.item_name ?? ''}|${row?.ics_no ?? ''}|${row?.status ?? ''}|${count}`,
    count,
    // Only show a single unit's identifiers; a collapsed row spans many units and
    // the per-unit values live in the Records modal.
    invNo: count > 1 ? '' : (source?.inventory_item_no ?? row?.inventory_item_no ?? ''),
    serial: count > 1 ? '' : (source?.serial_number ?? ''),
  };
}

function groupSubtitle(row) {
  const { count, invNo, serial } = groupMeta(row);

  if (count > 1) {
    return `${count} unit records`;
  }

  if (invNo && serial) {
    return `${invNo} · SN: ${serial}`;
  }

  return invNo || (serial ? `SN: ${serial}` : '');
}

function hasQrCodes(row) {
  return (row.sourceItems ?? []).some((source) => Boolean(source.qr_code));
}

const qrSources = computed(() => (recordGroup.value?.sourceItems ?? [])
  .filter((source) => Boolean(source.qr_code)));

const allPrintChecked = computed(() =>
  qrSources.value.length > 0 && selectedPrintItemIds.value.length === qrSources.value.length,
);

function toggleAllPrint(checked) {
  selectedPrintItemIds.value = checked ? qrSources.value.map((source) => source.item_id) : [];
}

const allQrPrintChecked = computed(() =>
  filteredQrPrintItems.value.length > 0 && qrPrintSelected.value.length === filteredQrPrintItems.value.length,
);

function toggleAllQrPrint(checked) {
  qrPrintSelected.value = checked ? filteredQrPrintItems.value.map((item) => item.item_id) : [];
}

const printingQr = ref(false);

function escapeStickerHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

async function printStickers(itemsToPrint) {
  if (!itemsToPrint.length || printingQr.value) return;
  printingQr.value = true;
  let frame = null;
  try {
    let stickersHtml = '';
    for (const source of itemsToPrint) {
      let qrDataUrl = '';
      try {
        qrDataUrl = await QRCode.toDataURL(source.qr_code, {
          width: 320,
          margin: 0,
          color: { dark: '#1d4ed8', light: '#ffffff' },
        });
      } catch {}
      const itemName = escapeStickerHtml(source.item_name ?? recordGroup.value?.item_name ?? 'INVENTORY ITEM');
      const invNo = escapeStickerHtml(source.inventory_item_no ?? `#${source.item_id}`);
      const serialNo = escapeStickerHtml(source.serial_number || 'Not recorded');
      const category = escapeStickerHtml(source.category?.category_name ?? recordGroup.value?.category?.category_name ?? 'Uncategorized');
      const qrText = escapeStickerHtml(source.qr_code ?? '');
      const qrImgHtml = qrDataUrl
        ? `<img src="${qrDataUrl}" alt="QR Code" style="width:115px;height:115px;display:block;" />`
        : '<div style="width:115px;height:115px;display:flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;font-size:10px;color:#9ca3af;">NO QR</div>';

      stickersHtml += '<div style="display:block;width:9cm;margin:0;border:2px solid #1d4ed8;background:#fff;padding:14px;page-break-inside:avoid;break-inside:avoid;">'
        + '<div style="border-bottom:1.5px solid #dbeafe;padding-bottom:6px;text-align:center;">'
        + '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1d4ed8;">Dian-ay National High School (NIR)</div>'
        + '<div style="font-size:9px;font-weight:600;color:#6b7280;margin-top:1px;">Division of Negros Occidental</div>'
        + '</div>'
        + '<div style="display:flex;align-items:flex-start;gap:12px;margin-top:10px;">'
        + '<div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:120px;">'
        + qrImgHtml
        + `<div style="margin-top:4px;font-family:monospace;font-size:8px;line-height:1.2;color:#9ca3af;text-align:center;word-break:break-all;">${qrText}</div>`
        + '</div>'
        + '<div style="flex:1;min-width:0;">'
        + `<div style="font-size:13px;font-weight:800;text-transform:uppercase;color:#111827;line-height:1.25;margin-bottom:8px;word-break:break-word;">${itemName}</div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">ITEM NO.</span><span style="display:block;font-family:monospace;font-size:11px;font-weight:700;color:#2563eb;">${invNo}</span></div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">SERIAL NO.</span><span style="display:block;font-family:monospace;font-size:10px;font-weight:600;color:#1f2937;">${serialNo}</span></div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">CATEGORY</span><span style="display:block;font-size:10px;font-weight:600;color:#374151;">${category}</span></div>`
        + '</div>'
        + '</div>'
        + '<div style="margin-top:10px;border-top:1px dashed #dbeafe;padding-top:6px;text-align:center;font-size:8px;font-weight:500;color:#9ca3af;">Scan this QR code to view item details &bull; DNHS Property Management System</div>'
        + '</div>';
    }

    frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(frame);
    const doc = frame.contentDocument ?? frame.contentWindow?.document;
    if (!doc) {
      useToastStore().error('Print failed', 'Could not open the print view. Please try again.');
      frame.remove();
      frame = null;
      return;
    }
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print QR - DNHS</title>'
      + '<style>@page{size:auto;margin:10mm;}*{box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}html,body{margin:0;padding:0;background:#fff;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;color:#111827;}body{display:grid;grid-template-columns:repeat(auto-fill,9cm);grid-auto-flow:row;gap:6mm;padding:0;}</style>'
      + `</head><body>${stickersHtml}</body></html>`);
    doc.close();

    await new Promise((resolve) => { window.setTimeout(resolve, 350); });
    frame.contentWindow?.focus();
    frame.contentWindow?.print();
    await new Promise((resolve) => { window.setTimeout(resolve, 800); });
  } finally {
    if (frame) frame.remove();
    printingQr.value = false;
  }
}

function openPrintSelected() {
  const selected = qrSources.value.filter((source) => selectedPrintItemIds.value.includes(source.item_id));
  void printStickers(selected);
}

async function openQrPrintModal() {
  qrPrintSearch.value = '';
  qrPrintSelected.value = [];
  qrPrintError.value = '';
  qrPrintOpen.value = true;
  await loadQrPrintItems();
}

async function openExportPdfModal() {
  exportPdfWorkspace.value = workspace.value ?? 'all';
  exportPdfCategory.value = categoryFilter.value ? String(categoryFilter.value) : '';
  exportPdfSelectedIcs.value = [];
  exportPdfIcsSearch.value = '';
  exportPdfIcsError.value = '';
  exportPdfSubmitting.value = false;
  exportPdfOpen.value = true;
  await loadExportPdfIcs();
}

function closeExportPdfModal() {
  exportPdfOpen.value = false;
}

async function loadExportPdfIcs() {
  exportPdfIcsLoading.value = true;
  exportPdfIcsError.value = '';
  try {
    const { data } = await api.get('/custodian/inventory/ics-numbers');
    if (!Array.isArray(data.icsNumbers)) {
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

async function exportInventoryPdf() {
  if (exportPdfIcsLoading.value || exportPdfIcsError.value || exportPdfSubmitting.value) {
    return;
  }
  exportPdfSubmitting.value = true;
  try {
    const exportUrl = new URL(exportPdfModalHref.value, window.location.origin);
    const response = await api.get(`/custodian/inventory/export-pdf${exportUrl.search}`, {
      responseType: 'blob',
      skipToast: true,
    });
    const blobUrl = window.URL.createObjectURL(response.data);
    const downloadLink = document.createElement('a');
    const disposition = response.headers['content-disposition'] ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1]
      ?? `dnhs_inventory_${exportPdfWorkspace.value}.pdf`;

    downloadLink.href = blobUrl;
    downloadLink.download = filename;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);
    closeExportPdfModal();
  } catch (requestError) {
    console.error('Failed to export inventory PDF', requestError);
    useToastStore().error('Export failed', 'Could not generate the inventory PDF. Please try again.');
  } finally {
    exportPdfSubmitting.value = false;
  }
}

function toggleVisibleExportIcs(checked) {
  const visible = new Set(filteredExportPdfIcs.value);
  exportPdfSelectedIcs.value = checked
    ? [...new Set([...exportPdfSelectedIcs.value, ...visible])]
    : exportPdfSelectedIcs.value.filter((icsNumber) => !visible.has(icsNumber));
}

function closeQrPrintModal() {
  if (printingQr.value) return;
  qrPrintOpen.value = false;
  qrPrintItems.value = [];
  qrPrintSelected.value = [];
  qrPrintSearch.value = '';
  qrPrintError.value = '';
}

async function loadQrPrintItems() {
  qrPrintLoading.value = true;
  qrPrintError.value = '';
  try {
    const ws = workspace.value;
    const pageName = ws === 'all' ? 'all_page' : `${ws}_page`;
    const collected = [];
    let page = 1;
    let lastPage = 1;
    do {
      const { data } = await api.get('/custodian/inventory', {
        params: { workspace: ws, [pageName]: page },
      });
      const pageData = ws === 'all' ? data.allInventoryPage : (data.inventoryPages ?? {})[ws];
      const groups = pageData?.data ?? [];
      for (const group of groups) {
        for (const source of group.sourceItems ?? []) {
          if (!source.qr_code) continue;
          collected.push({
            item_id: source.item_id,
            inventory_item_no: source.inventory_item_no,
            item_name: source.item_name,
            category: { category_name: source.category?.category_name ?? 'Uncategorized' },
            serial_number: source.serial_number,
            quantity: sourceQuantity(source),
            unit: source.unit,
            status: source.status,
            qr_code: source.qr_code,
          });
        }
      }
      lastPage = Number(pageData?.last_page ?? 1);
      page++;
    } while (page <= lastPage);

    // De-duplicate by item_id in case a group appears under multiple paginators.
    const seen = new Set();
    qrPrintItems.value = collected.filter((item) => {
      if (seen.has(item.item_id)) return false;
      seen.add(item.item_id);
      return true;
    });
  } catch (requestError) {
    qrPrintError.value = requestError?.response?.data?.message ?? 'Could not load QR-coded items.';
    useToastStore().error('Could not load QR-coded items', qrPrintError.value);
  } finally {
    qrPrintLoading.value = false;
  }
}

function printQrAll() {
  const selected = qrPrintItems.value.filter((item) => qrPrintSelected.value.includes(item.item_id));
  void printStickers(selected);
}

function pageParam() {
  return workspace.value === 'all' ? 'all_page' : `${workspace.value}_page`;
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

// A fully issued record keeps 0 in inventory.quantity because the units live on the
// assignment transaction instead. Read that so the unit row agrees with the group total.
// This covers both `assigned` and `under_inspection` flagged while assigned: flagging
// only rewrites `status`, the transaction stays active, and the stored quantity stays 0.
function sourceQuantity(source) {
  const assigned = Number(source?.assigned_quantity ?? 0);
  if (assigned > 0 && ['assigned', 'under_inspection'].includes(source?.status)) {
    return assigned;
  }
  return Number(source?.quantity ?? 0);
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Lifespan helpers. Both fields already come from the grouped listing query
// (MAX(lifespan_years) / MAX(expected_end_date)), so this is display only — no
// backend change. The accessor-driven lifespan_status is not serialized to the
// SPA, so the tone here is derived from the end date using the same thresholds
// as Inventory::lifespanStatus (past = expired, within a year = approaching).
function lifespanYears(row) {
  const years = Number(row?.lifespan_years ?? 0);
  return Number.isFinite(years) && years > 0 ? years : 0;
}

function lifespanEndDate(row) {
  const value = row?.expected_end_date;
  if (!value) {
    return null;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function lifespanEndLabel(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return '';
  }

  return `Ends ${end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}`;
}

function lifespanLabel(row) {
  const years = lifespanYears(row);
  return years ? `${years} yr${years === 1 ? '' : 's'}` : '';
}

function lifespanTone(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'unknown';
  }
  if (end.getTime() < Date.now()) {
    return 'expired';
  }
  if (end.getTime() <= Date.now() + 365 * 24 * 60 * 60 * 1000) {
    return 'approaching';
  }

  return 'healthy';
}

function lifespanChip(row) {
  const years = lifespanLabel(row);
  const ends = lifespanEndLabel(row);
  if (!years && !ends) {
    return '';
  }

  return years && ends ? `${years} · ${ends}` : (years || ends);
}

function lifespanChipClass(row) {
  switch (lifespanTone(row)) {
    case 'expired':
      return 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300';
    case 'approaching':
      return 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300';
    case 'healthy':
      return 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300';
    default:
      return 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300';
  }
}

function lifespanEndClass(row) {
  return lifespanTone(row) === 'expired'
    ? 'text-rose-600 dark:text-rose-400'
    : '';
}

// Lifespan bar: fraction of life remaining, from the same two dates the chip
// used. Null when either date is missing or invalid, and the template falls
// back to the text chip rather than drawing a misleading bar.
function lifespanSpan(row) {
  const end = lifespanEndDate(row);
  const startValue = row?.date_acquired;
  if (!end || !startValue) {
    return null;
  }
  const start = new Date(startValue);
  if (Number.isNaN(start.getTime()) || end.getTime() <= start.getTime()) {
    return null;
  }
  return { start, end };
}

function lifespanRemainingFraction(row) {
  const span = lifespanSpan(row);
  if (!span) {
    return null;
  }
  const now = Date.now();
  if (now >= span.end.getTime()) {
    return 0;
  }
  if (now <= span.start.getTime()) {
    return 1;
  }
  return (span.end.getTime() - now) / (span.end.getTime() - span.start.getTime());
}

function lifespanRemainingLabel(row) {
  const span = lifespanSpan(row);
  if (!span) {
    return '';
  }
  const now = Date.now();
  if (now >= span.end.getTime()) {
    return 'Expired';
  }
  const months = Math.floor((span.end.getTime() - now) / (30.44 * 24 * 60 * 60 * 1000));
  if (months >= 24) {
    return `${(months / 12).toFixed(1)} yrs remaining`;
  }
  if (months >= 1) {
    return `${months} mo${months === 1 ? '' : 's'} remaining`;
  }
  const days = Math.max(1, Math.floor((span.end.getTime() - now) / (24 * 60 * 60 * 1000)));
  return `${days} day${days === 1 ? '' : 's'} remaining`;
}

function lifespanBarTitle(row) {
  const end = lifespanEndDate(row);
  const date = end ? end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
  const remaining = lifespanRemainingLabel(row);
  if (remaining === 'Expired') {
    return date ? `Expired · Expected expiry was ${date}` : 'Expired';
  }
  return date ? `${remaining} · Expected expiry ${date}` : remaining;
}

function lifespanBarWidth(row) {
  const fraction = lifespanRemainingFraction(row);
  if (fraction === null) {
    return '0%';
  }
  return `${(Math.max(0, Math.min(1, fraction)) * 100).toFixed(1)}%`;
}

function lifespanBarFillClass(row) {
  switch (lifespanTone(row)) {
    case 'expired':
      return 'bg-rose-500';
    case 'approaching':
      return 'bg-amber-500';
    case 'healthy':
      return 'bg-emerald-500';
    default:
      return 'bg-gray-400';
  }
}

function formatMovementDate(movement) {
  if (!movement?.created_at) {
    return 'Not recorded';
  }

  const date = new Date(movement.created_at);
  return Number.isNaN(date.getTime()) ? 'Not recorded' : date.toLocaleString();
}

function itemIconMarkup(row) {
  const itemName = String(row.item_name ?? '').toLowerCase();
  const categoryName = String(row.category?.category_name ?? '').toLowerCase();
  const matchedItem = ITEM_ICONS.find(({ terms }) => terms.some((term) => itemName.includes(term)));
  const matchedCategory = CATEGORY_ICONS.find(({ terms }) => terms.some((term) => categoryName.includes(term)));
  const icon = matchedItem?.icon ?? matchedCategory?.icon ?? Package;
  const children = icon.map(([tag, attributes]) => {
    const serializedAttributes = Object.entries(attributes)
      .map(([name, value]) => `${name}="${String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"`)
      .join(' ');
    return `<${tag}${serializedAttributes ? ` ${serializedAttributes}` : ''} />`;
  }).join('');

  return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${children}</svg>`;
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openQrScanner() {
  qrResult.value = null;
  qrError.value = '';
  qrScannerOpen.value = true;
}

function printIIRUP() {
  iirupPrintOpen.value = true;
}

function openRfqPreview() {
  rfqPrintOpen.value = true;
}

function closeQrScanner() {
  qrScannerOpen.value = false;
  qrResult.value = null;
  qrError.value = '';
}

async function lookupQrToken(token) {
  const normalizedToken = String(token ?? '').trim();
  if (!normalizedToken || qrLookupLoading.value) return;

  qrLookupLoading.value = true;
  qrError.value = '';

  try {
    const { data } = await api.get('/custodian/inventory/qr/lookup', {
      params: { token: normalizedToken },
    });

    if (!data.found) {
      qrError.value = data.message ?? 'No item found for this QR code.';
      return;
    }

    qrResult.value = data;
  } catch (requestError) {
    qrError.value = requestError?.response?.data?.message ?? 'Could not look up this QR code. Try again.';
  } finally {
    qrLookupLoading.value = false;
  }
}

function scanAnotherQrCode() {
  qrResult.value = null;
  qrError.value = '';
}

function hasRepairRecommendation(row) {
  return (row?.sourceItems ?? []).some((source) => Boolean(source?.repairRecommendation));
}

// Disposal audit attached per source row. The serializer keys relations by
// their camelCase method names; the snake_case fallbacks stay for any path
// that already provides them.
function disposalMovementOf(source) {
  return source?.disposalMovement
    ?? source?.latestDisposalMovement
    ?? source?.disposal_movement
    ?? source?.latest_disposal_movement
    ?? null;
}

function disposalReason(row) {
  const reasons = (row?.sourceItems ?? [])
    .map((source) => disposalMovementOf(source)?.notes)
    .filter((note) => typeof note === 'string' && note.trim() !== '');
  return [...new Set(reasons.map((note) => note.trim()))].join(' · ');
}

async function decideRepairRecommendation(source, decision) {
  const inspectionId = source?.repairRecommendation?.inspection_id;
  if (!inspectionId || repairDecisionId.value !== null) return;

  repairDecisionId.value = inspectionId;
  try {
    const { data } = await api.post(
      `/custodian/inspection/${inspectionId}/${decision === 'approve' ? 'approve-repair' : 'decline-repair'}`,
      {},
      { skipToast: true },
    );
    useToastStore().success(
      decision === 'approve' ? 'Repair approved' : 'Recommendation declined',
      data?.message ?? (decision === 'approve'
        ? 'The item is now under repair.'
        : 'The item was returned to its previous status.'),
    );
    recordGroup.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    useToastStore().error(
      'Could not decide',
      requestError?.response?.data?.message ?? 'Could not record this decision. Refresh the inventory and try again.',
    );
  } finally {
    repairDecisionId.value = null;
  }
}

function openRecordModal(row) {
  recordGroup.value = row;
  selectedPrintItemIds.value = [];
}

// Open end-user damage/loss report on a source row, if any. Only reports
// (records carrying a diagnosis from the report flow) count — custodian
// repair-pipeline records are handled by the repair actions.
function openIssueReport(source) {
  const record = source?.latestMaintenance ?? null;
  if (!record || !['reported', 'in_progress'].includes(record.status) || !record.diagnosis) {
    return null;
  }
  const reporter = record.reportedBy ?? record.reported_by ?? null;
  const name = reporter
    ? ([reporter.first_name, reporter.last_name].filter(Boolean).join(' ') || reporter.username || 'Unknown')
    : 'Unknown';
  return {
    id: record.id ?? record.maintenance_id,
    diagnosis: record.diagnosis,
    reporter: name,
    notes: record.issue_description ?? '',
  };
}

async function declineIssueReport(source) {
  const report = openIssueReport(source);
  if (!report?.id || reportDecisionId.value !== null) return;

  reportDecisionId.value = report.id;
  try {
    const { data } = await api.post(
      `/custodian/maintenance/${report.id}/decline`,
      {},
      { skipToast: true },
    );
    useToastStore().success(
      'Report declined',
      data?.message ?? 'The issue report was closed.',
    );
    recordGroup.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    useToastStore().error(
      'Could not decline',
      requestError?.response?.data?.message ?? 'Could not close this report. Refresh the inventory and try again.',
    );
  } finally {
    reportDecisionId.value = null;
  }
}

function isMaintenanceEligible(source, row = maintenanceGroup.value) {
  const eligibility = source.category?.is_maintenance_eligible ?? row?.category?.is_maintenance_eligible;
  return eligibility !== false && eligibility !== 0;
}

function hasMaintenanceEligibleItems(row) {
  return (row.sourceItems ?? []).some((source) =>
    source.status === 'available' && isMaintenanceEligible(source, row),
  );
}

function isInspectable(status) {
  return INSPECTABLE_STATUSES.includes(status);
}

// Change 1: partially issued stock reads in the Assigned tab. A row is shown
// as assigned when it is fully assigned, or when the Assigned workspace lists
// an `available` record that still has units issued out (its remainder stays
// under Available). Every branch below keys off this, never raw status alone.
function assignedOutQty(row) {
  return Number(row?.assigned_quantity ?? 0);
}

function rowAssignees(row) {
  const names = (row?.sourceItems ?? [])
    .flatMap((source) => String(source.active_assignee_summary ?? '').split(','))
    .map((part) => part.replace(/\s*\(\d+\)\s*$/, '').trim())
    .filter(Boolean);
  return [...new Set(names)].join(', ');
}

function hasIssuedOut(row) {
  // Under repair joins available here: repair can be approved while units are
  // still issued out, and those rows must show the holder breakdown too.
  return (row?.status === 'available' || row?.status === 'under_repair') && assignedOutQty(row) > 0;
}

function isPartialAssigned(row) {
  return workspace.value === 'assigned' && hasIssuedOut(row);
}

function displayStatus(row) {
  return isPartialAssigned(row) ? 'assigned' : row?.status;
}

function hasInspectableItems(row) {
  return (row?.sourceItems ?? []).some((source) => INSPECTABLE_STATUSES.includes(source.status));
}

function openInspectionModal(row) {
  inspectionGroup.value = row;
  inspectionItemIds.value = [];
  inspectionReason.value = '';
  inspectionError.value = '';
}

function closeInspectionModal() {
  if (sendingToInspection.value) return;
  inspectionGroup.value = null;
  inspectionItemIds.value = [];
  inspectionReason.value = '';
  inspectionError.value = '';
}

async function sendSelectedToInspection() {
  const selectedSources = inspectableItems.value.filter((source) =>
    inspectionItemIds.value.includes(source.item_id),
  );

  if (!selectedSources.length || sendingToInspection.value) {
    inspectionError.value = 'Select at least one eligible item record.';
    sendingToInspection.value = false;
    return;
  }

  sendingToInspection.value = true;
  inspectionError.value = '';

  try {
    await api.post(
      `/custodian/inventory/${selectedSources[0].item_id}/send-to-inspection`,
      {
        inventory_ids: selectedSources.map((source) => source.item_id),
        reason: inspectionReason.value.trim() || null,
      },
      // This handler raises its own confirmation below. Without skipToast the
      // interceptor would add a second, generic "Success" toast for the same action.
      { skipToast: true },
    );
    inspectionGroup.value = null;
    inspectionItemIds.value = [];
    inspectionReason.value = '';
    forgetPageCache();
    await load();
    useToastStore().success(
      'Sent for inspection',
      `${selectedSources.length} item record(s) are now awaiting inspection.`,
    );
  } catch (requestError) {
    inspectionError.value = requestError?.response?.data?.message
      ?? 'Could not flag the selected items for inspection. Refresh the inventory and try again.';
  } finally {
    sendingToInspection.value = false;
  }
}

function openMaintenanceModal(row) {
  maintenanceGroup.value = row;
  maintenanceItemIds.value = [];
  maintenanceIssue.value = '';
  maintenanceNotes.value = '';
  maintenanceError.value = '';
}

function closeMaintenanceModal() {
  if (sendingToMaintenance.value) return;
  maintenanceGroup.value = null;
  maintenanceItemIds.value = [];
  maintenanceIssue.value = '';
  maintenanceNotes.value = '';
  maintenanceError.value = '';
}

async function sendSelectedToMaintenance() {
  if (!maintenanceItemIds.value.length || !maintenanceIssue.value.trim() || sendingToMaintenance.value) return;

  sendingToMaintenance.value = true;
  maintenanceError.value = '';
  const selectedSources = maintenanceEligibleItems.value
    .filter((source) => maintenanceItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    maintenanceError.value = 'Select at least one eligible available item record.';
    sendingToMaintenance.value = false;
    return;
  }

  try {
    await api.post(
      `/custodian/inventory/${selectedSources[0].item_id}/send-to-maintenance`,
      {
        inventory_ids: selectedSources.map((source) => source.item_id),
        issue_description: maintenanceIssue.value.trim(),
        notes: maintenanceNotes.value.trim() || null,
      },
    );
    maintenanceGroup.value = null;
    maintenanceItemIds.value = [];
    maintenanceIssue.value = '';
    maintenanceNotes.value = '';
    maintenanceError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    maintenanceError.value = requestError?.response?.data?.message
      ?? 'Could not send the selected items for repair. Refresh the inventory and try again.';
  } finally {
    sendingToMaintenance.value = false;
  }
}

function openMaintenanceActionModal(row, action) {
  maintenanceActionGroup.value = row;
  maintenanceAction.value = action;
  maintenanceActionItemIds.value = [];
  maintenanceRepairNotes.value = '';
  maintenanceCost.value = null;
  maintenanceDisposalNotes.value = '';
  maintenanceActionError.value = '';
}

function closeMaintenanceActionModal() {
  if (maintenanceActionBusy.value) return;
  maintenanceActionGroup.value = null;
  maintenanceActionItemIds.value = [];
  maintenanceActionError.value = '';
}

function openDisposeModal(row) {
  disposeGroup.value = row;
  disposeItemIds.value = [];
  disposeNotes.value = '';
  disposeError.value = '';
}

function closeDisposeModal() {
  if (disposing.value) return;
  disposeGroup.value = null;
  disposeItemIds.value = [];
  disposeNotes.value = '';
  disposeError.value = '';
}

async function confirmDispose() {
  if (!disposeItemIds.value.length || disposing.value) return;

  disposing.value = true;
  disposeError.value = '';
  const selectedSources = disposeEligibleItems.value
    .filter((source) => disposeItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    disposeError.value = 'Select at least one record that is still ready to dispose.';
    disposing.value = false;
    return;
  }

  try {
    await api.post(`/custodian/inventory/${selectedSources[0].item_id}/dispose`, {
      inventory_ids: selectedSources.map((source) => source.item_id),
      notes: disposeNotes.value.trim() || null,
    });
    disposeGroup.value = null;
    disposeItemIds.value = [];
    disposeNotes.value = '';
    disposeError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    disposeError.value = requestError?.response?.data?.message
      ?? 'Could not dispose the selected items. Refresh the inventory and try again.';
  } finally {
    disposing.value = false;
  }
}

async function confirmMaintenanceAction() {
  if (!maintenanceActionItemIds.value.length || maintenanceActionBusy.value) return;
  if (maintenanceAction.value === 'dispose' && !maintenanceDisposalNotes.value.trim()) return;

  maintenanceActionBusy.value = true;
  maintenanceActionError.value = '';
  const selectedSources = maintenanceActionItems.value
    .filter((source) => maintenanceActionItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    maintenanceActionError.value = 'Select at least one item record that is still under repair.';
    maintenanceActionBusy.value = false;
    return;
  }

  const routeSuffix = maintenanceAction.value === 'repair' ? 'mark-repaired' : 'mark-ready-to-dispose';
  const payload = maintenanceAction.value === 'repair'
    ? {
        inventory_ids: selectedSources.map((source) => source.item_id),
        repair_notes: maintenanceRepairNotes.value.trim() || null,
        ...(maintenanceCost.value !== null && maintenanceCost.value !== '' ? { maintenance_cost: Number(maintenanceCost.value) } : {}),
      }
    : {
        inventory_ids: selectedSources.map((source) => source.item_id),
        notes: maintenanceDisposalNotes.value.trim(),
      };

  try {
    await api.post(`/custodian/inventory/${selectedSources[0].item_id}/${routeSuffix}`, payload);
    maintenanceActionGroup.value = null;
    maintenanceActionItemIds.value = [];
    maintenanceRepairNotes.value = '';
    maintenanceCost.value = null;
    maintenanceDisposalNotes.value = '';
    maintenanceActionError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    maintenanceActionError.value = requestError?.response?.data?.message
      ?? 'Could not update the selected maintenance items. Refresh and try again.';
  } finally {
    maintenanceActionBusy.value = false;
  }
}

function openReturnModal(row, fromQr = false) {
  returnGroup.value = row;
  returnFromQr.value = fromQr;
  const firstAssignment = (row.sourceItems ?? [])
    .flatMap((source) => source.receive_return_assignments ?? [])[0];
  returnTransactionId.value = firstAssignment ? String(firstAssignment.transaction_id) : '';
  returnNotes.value = '';
  returnError.value = '';
}

function openQrReturnModal() {
  const item = qrResult.value;
  if (!item?.return_assignments?.length) return;

  qrScannerOpen.value = false;
  openReturnModal({
    item_name: item.item_name,
    sourceItems: [{
      item_id: item.item_id,
      receive_return_assignments: item.return_assignments,
    }],
  }, true);
}

function closeReturnModal() {
  if (returning.value) return;
  const returnToQrScanner = returnFromQr.value;
  returnGroup.value = null;
  returnFromQr.value = false;
  returnTransactionId.value = '';
  returnNotes.value = '';
  returnError.value = '';

  if (returnToQrScanner) {
    qrResult.value = null;
    qrManualToken.value = '';
    qrError.value = '';
    qrScannerOpen.value = true;
  }
}

async function confirmReturn() {
  const assignment = selectedReturnAssignment.value;
  if (!assignment || returning.value) return;

  returning.value = true;
  returnError.value = '';
  try {
    const endpoint = assignment.is_manual_return
      ? `/custodian/transactions/${assignment.transaction_id}/manual-return`
      : '/custodian/inventory/receive-return';
    const payload = assignment.is_manual_return
      ? { notes: returnNotes.value.trim() || null }
      : {
          transaction_id: assignment.transaction_id,
          return_quantity: assignment.remaining_quantity,
          notes: returnNotes.value.trim() || null,
        };
    const { data } = await api.post(
      endpoint,
      payload,
      // A "Return recorded" toast follows below; skipToast stops the interceptor
      // from repeating the same backend message a second time.
      { skipToast: true },
    );
    returnGroup.value = null;
    returnFromQr.value = false;
    returnTransactionId.value = '';
    returnNotes.value = '';
    useToastStore().success('Return recorded', data.message ?? 'The assigned item return was recorded.');
    forgetPageCache();
    await load();
  } catch (requestError) {
    returnError.value = requestError?.response?.data?.message
      ?? 'Could not record this return. The assignment may have changed; refresh and try again.';
  } finally {
    returning.value = false;
  }
}

async function load(options = {}) {
  const params = inventoryCacheParams();
  await loadCachedPage({
    page: inventoryCachePage,
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/custodian/inventory', {
        params: { workspace: params.workspace, [pageParam()]: params.page },
      });
      return data;
    },
    applyData: applyInventoryPayload,
    isCurrent: () => {
      const now = inventoryCacheParams();
      return now.workspace === params.workspace && now.page === params.page;
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load inventory.';
    },
  });
}

function switchWorkspace(next) {
  animationsLive.value = true;
  if (workspace.value === next) {
    return;
  }
  workspace.value = next;
  page.value = 1;
  selection.value = [];
}

// Sliding active-tab pill: measured from the rendered button so it tracks
// label/badge widths, wrapping, and dark mode without hardcoded sizes.
const tabStripRef = ref(null);
const pillStyle = ref({ opacity: '0' });
const animationsLive = ref(false);

function updatePill() {
  const strip = tabStripRef.value;
  if (!strip) return;
  const active = strip.querySelector(`[data-workspace-tab="${workspace.value}"]`);
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

watch(workspace, () => nextTick(updatePill));

function selectWorkspace(next) {
  switchWorkspace(next);
  workspaceMenuOpen.value = false;
}

function goToPage(next) {
  page.value = next;
}

function askDelete(row) {
  deleteGroup.value = row;
  deleteError.value = '';
  deleteItemIds.value = [];
}

function closeDeleteModal() {
  if (deleting.value) return;
  deleteGroup.value = null;
  deleteItemIds.value = [];
  deleteError.value = '';
}

function askEdit(row) {
  editItemId.value = row.source_item_id ?? row.item_id;
}

async function doDelete() {
  const selectedIds = confirmBulk.value ? selection.value : deleteItemIds.value;
  const deleteContext = confirmBulk.value ? 'bulk' : 'group';
  if (!selectedIds.length || deleting.value) return;

  deleting.value = true;
  deleteError.value = '';

  try {
    await api.delete(`/custodian/inventory/${selectedIds[0]}`, {
      data: { selected_item_ids: selectedIds },
    });
    selection.value = [];
    deleteGroup.value = null;
    deleteItemIds.value = [];
    confirmBulk.value = false;
    forgetPageCache();
    await load();
  } catch (requestError) {
    const message = requestError?.response?.data?.message ?? 'Could not delete the selected inventory records.';
    if (deleteContext === 'group') {
      deleteError.value = message;
    } else {
      useToastStore().error('Error', message);
    }
  } finally {
    deleting.value = false;
  }
}

function onStockInSaved() {
  stockInOpen.value = false;
  forgetPageCache();
  const alreadyAtDefaultList = workspace.value === 'all' && page.value === 1;
  search.value = '';
  categoryFilter.value = '';
  selection.value = [];
  workspace.value = 'all';
  page.value = 1;

  if (alreadyAtDefaultList) {
    load();
  }
}

function onEditSaved() {
  editItemId.value = null;
  forgetPageCache();
  load();
}

watch([workspace, page], () => load());
onMounted(() => load());
</script>

<style scoped>
.workspace-enter-active {
  transition: opacity 0.18s ease-out, transform 0.18s ease-out;
}
.workspace-leave-active {
  transition: opacity 0.15s ease-in, transform 0.15s ease-in;
}
.workspace-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.workspace-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@keyframes workspace-badge-pop {
  0% { transform: scale(1); }
  40% { transform: scale(1.25); }
  100% { transform: scale(1); }
}
.tab-badge-pop {
  animation: workspace-badge-pop 0.15s ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .workspace-enter-active,
  .workspace-leave-active {
    transition: none;
  }
  .tab-badge-pop {
    animation: none;
  }
}
</style>
