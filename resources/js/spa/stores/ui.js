import { defineStore } from 'pinia';
import { ref } from 'vue';

// Mirrors the Alpine `sidebar` store in resources/views/layouts/app.blade.php.
export const useUiStore = defineStore('ui', () => {
    const isExpanded = ref(window.innerWidth >= 1280 && localStorage.getItem('sidebar-expanded') !== 'false');
    const isMobileOpen = ref(false);

    function toggleExpanded() {
        isExpanded.value = !isExpanded.value;
        localStorage.setItem('sidebar-expanded', isExpanded.value ? 'true' : 'false');
        isMobileOpen.value = false;
    }

    function toggleMobileOpen() {
        isMobileOpen.value = !isMobileOpen.value;
    }

    function setMobileOpen(value) {
        isMobileOpen.value = value;
    }

    const aiOpen = ref(false);

    function setAiOpen(value) {
        aiOpen.value = value;
    }

    function syncWithViewport() {
        if (window.innerWidth < 1280) {
            isMobileOpen.value = false;
            isExpanded.value = false;
        } else {
            isMobileOpen.value = false;
            isExpanded.value = localStorage.getItem('sidebar-expanded') !== 'false';
        }
    }

    return { isExpanded, isMobileOpen, toggleExpanded, toggleMobileOpen, setMobileOpen, syncWithViewport, aiOpen, setAiOpen };
});
