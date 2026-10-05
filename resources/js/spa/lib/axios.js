import axios from 'axios';
import { useToastStore } from '../stores/toast';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
    api.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

function isMutation(method) {
    return ['post', 'patch', 'put', 'delete'].includes((method ?? '').toLowerCase());
}

// Views can set `skipToast: true` on a request to opt out of the automatic
// success/error toasts. Sign-in uses this: it either redirects into onboarding
// (where a "signed in" toast is noise) or navigates away, so the message is
// redundant either way.
function wantsToast(config) {
    return isMutation(config?.method) && config?.skipToast !== true;
}

api.interceptors.response.use(
    (response) => {
        const data = response.data;

        if (wantsToast(response.config) && data && typeof data === 'object' && data.message) {
            const toast = useToastStore();

            if (data.status === 'error') {
                toast.error('Error', data.message);
            } else {
                toast.success('Success', data.message);
            }
        }

        return response;
    },
    (error) => {
        const status = error?.response?.status;
        const url = error?.config?.url ?? '';

        // Session expired mid-use: fall back to the SPA sign-in page.
        // The /me bootstrap call is handled by the auth store instead.
        if (status === 401 && !url.includes('/me') && !url.includes('/signin')) {
            window.location.href = '/spa/signin';
            return new Promise(() => {});
        }

        // 422s carry field errors for the active form; mutation failures other
        // than validation get a toast. GET failures are rendered inline by
        // the active view, so they stay quiet here.
        if (status && status !== 422 && status !== 401 && wantsToast(error?.config)) {
            try {
                useToastStore().error('Error', error?.response?.data?.message ?? 'Something went wrong.');
            } catch {
                // Store unavailable (e.g. outside setup): surface via rejection only.
            }
        }

        return Promise.reject(error);
    },
);

export default api;
