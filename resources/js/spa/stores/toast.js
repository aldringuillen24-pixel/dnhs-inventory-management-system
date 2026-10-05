import { defineStore } from 'pinia';
import { ref } from 'vue';

let sequence = 0;

export const useToastStore = defineStore('toast', () => {
    const toasts = ref([]);

    function dismiss(id) {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function push(type, title, message, timeout = 4000) {
        const id = ++sequence;
        toasts.value.push({ id, type, title, message });

        if (timeout > 0) {
            setTimeout(() => dismiss(id), timeout);
        }
    }

    function success(title, message) {
        push('success', title, message);
    }

    function error(title, message) {
        push('error', title, message, 6000);
    }

    return { toasts, push, dismiss, success, error };
});
