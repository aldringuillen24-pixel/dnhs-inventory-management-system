import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import RoleLanding from '../views/RoleLanding.vue';
import SignIn from '../views/auth/SignIn.vue';
import Onboarding from '../views/auth/Onboarding.vue';
import ResetPassword from '../views/auth/ResetPassword.vue';
import WorkspacePlaceholder from '../views/WorkspacePlaceholder.vue';
import Forbidden from '../views/Forbidden.vue';
import NotFound from '../views/NotFound.vue';

// Frontend guards mirror server roles for UX only. The `auth` + `role`
// middleware on /api/* remain the access control source of truth.
const router = createRouter({
    history: createWebHistory('/spa'),
    routes: [
        { path: '/', name: 'landing', component: RoleLanding, meta: { title: 'Loading' } },
        { path: '/signin', name: 'signin', component: SignIn, meta: { guest: true, title: 'Sign In' } },
        { path: '/onboarding', name: 'onboarding', component: Onboarding, meta: { onboarding: true, title: 'Complete Your Account Setup' } },
        { path: '/forgot-password', name: 'forgot-password', component: ResetPassword, meta: { guest: true, title: 'Reset Password' } },
        {
            path: '/dashboard',
            name: 'custodian-dashboard',
            component: () => import('../views/custodian/Dashboard.vue'),
            meta: { roles: ['Property Custodian'], title: 'Dashboard' },
        },
        {
            path: '/inventory',
            name: 'custodian-inventory',
            component: () => import('../views/custodian/Inventory.vue'),
            meta: { roles: ['Property Custodian'], title: 'Inventory' },
        },
        {
            path: '/transactions',
            name: 'custodian-transactions',
            component: () => import('../views/custodian/Transactions.vue'),
            meta: { roles: ['Property Custodian'], title: 'Transactions' },
        },
        {
            path: '/reports',
            name: 'custodian-reports',
            component: () => import('../views/custodian/Reports.vue'),
            meta: { roles: ['Property Custodian'], title: 'Reports' },
        },
        {
            path: '/reports/forecast',
            name: 'forecast-recommendations',
            component: () => import('../views/custodian/ForecastRecommendations.vue'),
            meta: { roles: ['Property Custodian'], title: 'Demand Forecast' },
        },
        {
            path: '/end-user/dashboard',
            name: 'end-user-dashboard',
            component: () => import('../views/enduser/Dashboard.vue'),
            meta: { roles: ['End User'], title: 'My Dashboard' },
        },
        {
            path: '/end-user/requests',
            name: 'end-user-requests',
            component: () => import('../views/enduser/Requests.vue'),
            meta: { roles: ['End User'], title: 'Requests' },
        },
        {
            path: '/end-user/assigned',
            name: 'end-user-assigned',
            component: () => import('../views/enduser/Assigned.vue'),
            meta: { roles: ['End User'], title: 'My Assigned Items' },
        },
        {
            path: '/admin/dashboard',
            name: 'admin-dashboard',
            component: () => import('../views/admin/Dashboard.vue'),
            meta: { roles: ['Administrator'], title: 'Admin Dashboard' },
        },
        {
            path: '/admin/users',
            name: 'admin-users',
            component: () => import('../views/admin/Users.vue'),
            meta: { roles: ['Administrator'], title: 'User Management' },
        },
        {
            path: '/admin/reports',
            name: 'admin-reports',
            component: () => import('../views/admin/Reports.vue'),
            meta: { roles: ['Administrator'], title: 'System Reports' },
        },
        {
            path: '/admin/settings',
            name: 'admin-settings',
            component: () => import('../views/admin/Settings.vue'),
            meta: { roles: ['Administrator'], title: 'System Settings' },
        },
        {
            path: '/school-head/dashboard',
            name: 'school-head-dashboard',
            component: () => import('../views/schoolhead/Dashboard.vue'),
            meta: { roles: ['School Head'], title: 'School Head Dashboard' },
        },
        {
            path: '/school-head/inventory',
            name: 'school-head-inventory',
            component: () => import('../views/schoolhead/InventoryOverview.vue'),
            meta: { roles: ['School Head'], title: 'Inventory Overview' },
        },
        {
            path: '/school-head/reports',
            name: 'school-head-reports',
            component: () => import('../views/schoolhead/Reports.vue'),
            meta: { roles: ['School Head'], title: 'Reports' },
        },
        {
            path: '/school-head/audit-logs',
            name: 'school-head-audit-logs',
            component: () => import('../views/schoolhead/AuditLogs.vue'),
            meta: { roles: ['School Head'], title: 'Audit Logs' },
        },
        {
            path: '/inspector/inventory',
            name: 'inspector-inventory',
            component: () => import('../views/inspector/InventoryOverview.vue'),
            meta: { roles: ['Inspector'], title: 'Inventory Overview' },
        },
        {
            path: '/inspector/reports',
            name: 'inspector-reports',
            component: () => import('../views/inspector/Reports.vue'),
            meta: { roles: ['Inspector'], title: 'Reports' },
        },
        {
            path: '/profile',
            name: 'profile',
            component: () => import('../views/shared/Profile.vue'),
            meta: { roles: ['Administrator', 'Property Custodian', 'School Head', 'Inspector', 'End User'], title: 'Profile' },
        },
        {
            path: '/workspace',
            name: 'workspace',
            component: WorkspacePlaceholder,
            meta: { title: 'Workspace' },
        },
        { path: '/forbidden', name: 'forbidden', component: Forbidden, meta: { title: 'Forbidden' } },
        { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFound, meta: { title: 'Not found' } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (auth.status === 'idle') {
        await auth.resolve();
    }

    if (!auth.isAuthenticated) {
        if (to.meta.guest) {
            document.title = `${to.meta.title ?? 'App'} | Dian-ay Inventory`;
            return true;
        }
        window.location.href = '/spa/signin';
        return false;
    }

    if (auth.onboardingRequired) {
        if (to.meta.onboarding) {
            document.title = `${to.meta.title ?? 'App'} | Dian-ay Inventory`;
            return true;
        }
        return { name: 'onboarding' };
    }

    if (to.meta.guest || to.meta.onboarding) {
        return auth.homePath;
    }

    if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
        return { name: 'forbidden' };
    }

    if (to.name === 'landing') {
        return auth.homePath;
    }

    document.title = `${to.meta.title ?? 'App'} | Dian-ay Inventory`;

    return true;
});

export default router;
