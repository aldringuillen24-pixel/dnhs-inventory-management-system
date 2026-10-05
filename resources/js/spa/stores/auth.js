import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api from '../lib/axios';

// Session state for the SPA shell. The server (`auth` + `role` middleware)
// remains the access control authority; this store only reflects /api/me
// so the UI can route, label, and hide affordances by role.
export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const role = ref(null);
    const onboardingRequired = ref(false);
    const status = ref('idle');

    const isAuthenticated = computed(() => user.value !== null);
    const isResolved = computed(() => status.value === 'ready' || status.value === 'guest');
    const displayName = computed(() => {
        if (!user.value) return '';
        const full = `${user.value.first_name ?? ''} ${user.value.last_name ?? ''}`.trim();
        return full || user.value.username || '';
    });

    const homePaths = {
        'Property Custodian': '/dashboard',
        'End User': '/end-user/dashboard',
        'School Head': '/school-head/dashboard',
        Inspector: '/inspector/inventory',
        Administrator: '/admin/dashboard',
    };

    const homePath = computed(() => homePaths[role.value] ?? '/workspace');

    function setPayload(payload) {
        user.value = payload.user;
        role.value = payload.role;
        onboardingRequired.value = payload.onboarding_required === true;
        status.value = 'ready';
    }

    function clear() {
        user.value = null;
        role.value = null;
        onboardingRequired.value = false;
        status.value = 'guest';
    }

    async function resolve() {
        if (status.value !== 'idle') return;
        status.value = 'loading';

        try {
            const { data } = await api.get('/me');
            setPayload(data);
        } catch (error) {
            if (error?.response?.status === 401) {
                clear();
                return;
            }
            status.value = 'idle';
            throw error;
        }
    }

    async function refresh() {
        status.value = 'idle';
        await resolve();
    }

    return {
        user,
        role,
        onboardingRequired,
        status,
        isAuthenticated,
        isResolved,
        displayName,
        homePath,
        setPayload,
        clear,
        resolve,
        refresh,
    };
});
