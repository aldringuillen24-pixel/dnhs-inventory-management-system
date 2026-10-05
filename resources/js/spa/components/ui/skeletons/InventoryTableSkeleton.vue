<template>
  <div class="min-h-0 flex-1 overflow-auto custom-scrollbar" aria-hidden="true">
    <table class="w-full min-w-[64rem] text-left text-xs">
      <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-800">
        <tr class="border-b border-gray-200 dark:border-gray-800">
          <th class="w-10 px-4 py-3.5"><div class="mx-auto h-4 w-4 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5"><div class="h-3 w-20 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5"><div class="h-3 w-16 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5 text-right"><div class="ml-auto h-3 w-14 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5 text-right"><div class="ml-auto h-3 w-16 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5 text-right"><div class="ml-auto h-3 w-16 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="px-4 py-3.5"><div class="h-3 w-24 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
          <th class="w-16 px-4 py-3.5"><div class="ml-auto h-3 w-8 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" /></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        <tr v-for="row in 5" :key="row" class="h-16">
          <td class="px-4 py-3.5"><div class="mx-auto h-4 w-4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 shrink-0 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              <div class="min-w-0 flex-1 space-y-2">
                <div class="h-3 w-2/3 animate-pulse rounded-md bg-gray-200 dark:bg-white/10" />
                <div class="h-2.5 w-4/5 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
              </div>
            </div>
          </td>
          <td class="px-4 py-3.5"><div class="h-6 w-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5"><div class="ml-auto h-3 w-10 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5"><div class="ml-auto h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5"><div class="ml-auto h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5"><div class="h-6 w-24 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
          <td class="px-4 py-3.5"><div class="ml-auto h-7 w-7 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
