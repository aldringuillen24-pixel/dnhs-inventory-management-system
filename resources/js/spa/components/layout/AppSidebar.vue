<template>
  <aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-99999 flex flex-col border-r border-gray-200 bg-white px-5 text-gray-900 shadow-sm transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
    :class="{
      'w-[240px]': ui.isExpanded || ui.isMobileOpen,
      'w-[90px]': !ui.isExpanded && !ui.isMobileOpen,
      'translate-x-0': ui.isMobileOpen,
      '-translate-x-full xl:translate-x-0': !ui.isMobileOpen,
    }"
  >
    <div class="flex-1 overflow-y-auto py-6 no-scrollbar">
      <div class="mb-6 border-b border-gray-200 pb-4 dark:border-gray-800">
        <RouterLink
          to="/"
          class="flex items-center gap-3 transition-all duration-200 hover:opacity-90"
          :class="ui.isExpanded || ui.isMobileOpen ? 'justify-start' : 'justify-center'"
        >
          <img :src="logoUrl" alt="DNHS Logo" class="h-8 w-8 shrink-0 object-contain" />
          <span v-show="ui.isExpanded || ui.isMobileOpen" class="text-sm tracking-wide text-slate-800 dark:text-white">
            DNHS Inventory Management System
          </span>
        </RouterLink>
      </div>
      <nav aria-label="Main navigation">
        <div class="space-y-7">
          <section v-for="group in menuGroups" :key="group.title">
            <h2
              v-show="ui.isExpanded || ui.isMobileOpen"
              class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400"
            >
              {{ group.title }}
            </h2>
            <ul class="space-y-1">
              <li v-for="item in group.items" :key="item.name">
                <RouterLink
                  v-if="item.to"
                  :to="item.to"
                  :title="item.name"
                  :aria-label="item.name"
                  class="menu-item group"
                  :class="isActive(item.to) ? 'menu-item-active' : 'menu-item-inactive'"
                >
                  <span :class="isActive(item.to) ? 'menu-item-icon-active' : 'menu-item-icon-inactive'" v-html="icon(item.icon)" />
                  <span v-show="ui.isExpanded || ui.isMobileOpen" class="menu-item-text flex items-center gap-2">
                    {{ item.name }}
                  </span>
                </RouterLink>
                <a
                  v-else
                  :href="item.classic"
                  :title="`${item.name} (classic view)`"
                  :aria-label="item.name"
                  class="menu-item group menu-item-inactive"
                >
                  <span class="menu-item-icon-inactive" v-html="icon(item.icon)" />
                  <span v-show="ui.isExpanded || ui.isMobileOpen" class="menu-item-text flex items-center gap-2">
                    {{ item.name }}
                    <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-500 dark:bg-white/10 dark:text-gray-400">Classic</span>
                  </span>
                </a>
              </li>
            </ul>
          </section>
        </div>
      </nav>
    </div>
  </aside>

  <div
    v-show="ui.isMobileOpen"
    class="fixed inset-0 z-50 bg-gray-900/50 xl:hidden"
    @click="ui.setMobileOpen(false)"
  />
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useUiStore } from '../../stores/ui';
import { useAuthStore } from '../../stores/auth';
import { ICONS, menuForRole } from '../../config/menu';

const ui = useUiStore();
const auth = useAuthStore();
const route = useRoute();

const logoUrl = '/images/logo/dnhs_school_logo.svg';

const menuGroups = computed(() => menuForRole(auth.role));

watch(() => route.path, () => ui.setMobileOpen(false));

function icon(name) {
  return ICONS[name] ?? ICONS.dashboard;
}

/**
 * The single nav entry that should be highlighted for the current path.
 *
 * Matching is prefix-aware so a section stays active on its nested pages, but
 * the deepest match wins. Without that, "/reports" and "/reports/forecast" would
 * both match the forecast page and two items would light up at once.
 */
const activeNavPath = computed(() => {
  const items = menuGroups.value.flatMap((group) => group.items);

  return items
    .filter((item) => route.path === item.to || route.path.startsWith(`${item.to}/`))
    .sort((a, b) => b.to.length - a.to.length)[0]?.to ?? null;
});

function isActive(path) {
  return activeNavPath.value === path;
}
</script>
