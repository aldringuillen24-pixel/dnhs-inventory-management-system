import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../lib/axios';
import { useAuthStore } from './auth';

let pollTimer = null;

// Pending-action count for the sidebar Transaction badge. Custodian-only:
// fetch() no-ops for every other role so no stray requests are made.
export const useNavBadgeStore = defineStore('navBadge', () => {
    const count = ref(0);

    async function fetch() {
        const auth = useAuthStore();
        if (!auth.isAuthenticated || auth.role !== 'Property Custodian') {
            return;
        }

        try {
            const { data } = await api.get('/custodian/dashboard');
            count.value = Math.max(0, Number(data?.pendingActionCount) || 0);
        } catch {
            // Keep the last known count on transient failures.
        }
    }

    function startPolling() {
        if (pollTimer !== null) {
            return;
        }
        pollTimer = setInterval(fetch, 60000);
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function clear() {
        count.value = 0;
        stopPolling();
    }

    return { count, fetch, startPolling, stopPolling, clear };
});
