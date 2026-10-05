import { defineStore } from 'pinia';
import { ref } from 'vue';

// Light mode only. The `.dark` class is never applied, so all `dark:`
// Tailwind variants stay dormant (dark is class-based in app.css).
// The `theme` ref stays so chart color computeds keep working.
export const useThemeStore = defineStore('theme', () => {
    const theme = ref('light');

    function init() {
        theme.value = 'light';
        try {
            localStorage.removeItem('theme');
        } catch {
            // Storage unavailable (private mode): nothing to clear.
        }
        document.documentElement.classList.remove('dark');
    }

    return { theme, init };
});
