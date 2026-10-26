<template>
  <Teleport to="body">
    <div v-if="open || keepMounted" v-show="open" class="fixed inset-0 flex items-center justify-center p-4" :class="zClass">
      <div class="absolute inset-0 bg-gray-900/50" @click="closeOnBackdrop && $emit('close')" />
      <div
        role="dialog"
        aria-modal="true"
        class="relative flex max-h-[90vh] min-h-0 w-full flex-col overflow-hidden rounded-md border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900"
        :class="[
          maxWidth,
          compact ? 'p-3 min-[480px]:p-6' : 'p-6',
        ]"
      >
        <div class="mb-4 flex shrink-0 items-start justify-between gap-4" :class="[compact ? 'mb-2 gap-3 min-[480px]:mb-4 min-[480px]:gap-4' : '', headerClass]">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90" :class="compact ? 'text-base min-[480px]:text-lg' : ''">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400" :class="compact ? 'text-xs min-[480px]:text-sm' : ''">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
            aria-label="Close dialog"
            @click="$emit('close')"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <!-- fixedBody: the panel itself stays put and the body is capped to the remaining
             height, so nested scroll regions (e.g. a list card) scroll instead of the dialog. -->
        <div v-if="fixedBody" class="flex min-h-0 flex-1 flex-col overflow-hidden">
          <slot />
        </div>
        <div v-else class="min-h-0 flex-1" :class="scrollable ? 'overflow-y-auto' : 'overflow-visible'">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="shrink-0 border-t border-gray-100 pt-3 dark:border-gray-800" :class="footerClass">
          <slot name="footer" />
        </footer>
        <slot name="overlay" />
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { onUnmounted, watch } from 'vue';

let openModalCount = 0;
let lockedScrollY = 0;
let originalBodyStyles = null;
let originalDocumentOverflow = '';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
    closeOnBackdrop: { type: Boolean, default: true },
    keepMounted: { type: Boolean, default: false },
    scrollable: { type: Boolean, default: true },
    // Caps the body to the leftover height below the header so the dialog does not
    // scroll; inner regions own their own scrolling.
    fixedBody: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    headerClass: { type: String, default: '' },
    footerClass: { type: String, default: '' },
    // Lets a stacked dialog underneath stay open on Escape while a dialog on
    // top owns the keypress (e.g. Stock-In stays mounted behind the manager).
    closeOnEscape: { type: Boolean, default: true },
    // Stacking order for nested dialogs teleported to the same body target.
    zClass: { type: String, default: 'z-99999' },
});

const emit = defineEmits(['close']);

function onKeydown(event) {
    if (event.key === 'Escape' && props.closeOnEscape) {
        emit('close');
    }
}

function lockPageScroll() {
    if (openModalCount === 0) {
        lockedScrollY = window.scrollY;
        originalDocumentOverflow = document.documentElement.style.overflow;
        originalBodyStyles = {
            position: document.body.style.position,
            top: document.body.style.top,
            left: document.body.style.left,
            right: document.body.style.right,
            width: document.body.style.width,
            overflow: document.body.style.overflow,
        };
        document.documentElement.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${lockedScrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
        document.body.style.overflow = 'hidden';
    }

    openModalCount += 1;
}

function unlockPageScroll() {
    if (openModalCount === 0) return;

    openModalCount -= 1;
    if (openModalCount > 0 || !originalBodyStyles) return;

    document.documentElement.style.overflow = originalDocumentOverflow;
    Object.assign(document.body.style, originalBodyStyles);
    originalBodyStyles = null;
    window.scrollTo(0, lockedScrollY);
}

let modalIsOpen = false;
function updateOpenState(open) {
    if (open === modalIsOpen) return;
    modalIsOpen = open;

    if (open) {
        document.addEventListener('keydown', onKeydown);
        lockPageScroll();
    } else {
        document.removeEventListener('keydown', onKeydown);
        unlockPageScroll();
    }
}

watch(
    () => props.open,
    updateOpenState,
    { immediate: true },
);

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    if (modalIsOpen) {
        modalIsOpen = false;
        unlockPageScroll();
    }
});
</script>
