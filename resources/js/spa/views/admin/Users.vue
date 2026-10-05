<template>
  <div>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2.5">
      <div>
        <h1 class="text-lg font-semibold text-gray-800 dark:text-white/90">User Management</h1>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Create, review, and maintain role-scoped accounts.</p>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/5" @click="slipsOpen = true">Export Slips</button>
        <button type="button" class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-brand-500 px-3 py-2 text-xs font-medium text-white hover:bg-brand-600" @click="openCreateAccount">Create Account</button>
      </div>
    </div>

    <div v-if="error" class="rounded-md border border-error-200 bg-error-50 p-4 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
      {{ error }}
      <button type="button" class="ml-2 font-semibold underline" @click="load">Retry</button>
    </div>

    <template v-else>
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <MetricCard compact dense micro :loading="firstLoading" title="Total Users" :value="format(metrics.total)" subtitle="All accounts" />
        <MetricCard compact dense micro :loading="firstLoading" title="Active" :value="format(metrics.active)" subtitle="Active accounts" />
        <MetricCard compact dense micro :loading="firstLoading" title="Inactive" :value="format(metrics.inactive)" subtitle="Inactive accounts" />
      </div>

      <div class="mt-4 rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
          <input v-model="search" type="search" placeholder="Search name or username…" class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:max-w-xs" />
          <select v-model="roleFilter" class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:max-w-xs">
            <option value="">All roles</option>
            <option v-for="role in roles" :key="role.role_id" :value="role.role_id">{{ role.role_name }}</option>
          </select>
          <select v-model="statusFilter" class="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:max-w-xs">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full min-w-[52rem] text-left text-xs">
            <thead>
              <tr class="border-y border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <th class="px-4 py-3.5">User</th>
                <th class="px-4 py-3.5">Role</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-4 py-3.5">Onboarding</th>
                <th class="px-4 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-if="firstLoading">
                <tr v-for="row in 5" :key="`loading-${row}`" aria-hidden="true">
                  <td v-for="column in 5" :key="column" class="px-4 py-3.5">
                    <div class="h-3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" :class="column === 1 ? 'w-3/4' : 'w-1/2'" />
                  </td>
                </tr>
              </template>
              <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-4 py-3.5">
                  <p class="font-medium text-gray-800 dark:text-white/90">{{ displayName(user) }}</p>
                  <p class="text-xs text-gray-500">@{{ user.username }}</p>
                </td>
                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ user.role?.role_name ?? '—' }}</td>
                <td class="px-4 py-3.5"><StatusBadge :status="user.status" /></td>
                <td class="px-4 py-3.5 text-xs text-gray-500">{{ user.onboarding_pending ? 'Pending' : 'Complete' }}</td>
                <td class="px-4 py-3.5">
                  <div class="flex justify-end gap-1">
                    <button type="button" class="rounded-md p-1 text-gray-500 transition hover:bg-brand-50 hover:text-brand-600 dark:text-gray-400 dark:hover:bg-brand-500/10 dark:hover:text-brand-400" title="Edit user" :aria-label="`Edit ${displayName(user)}`" @click="openEdit(user)">
                      <LucideIcon :icon="Pencil" class="h-3.5 w-3.5" />
                    </button>
                    <button type="button" class="rounded-md p-1 text-gray-500 transition hover:bg-error-50 hover:text-error-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-500 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 dark:disabled:hover:bg-transparent dark:disabled:hover:text-gray-400" :title="user.onboarding_pending ? 'Delete pending account' : 'Completed accounts cannot be deleted'" :aria-label="`Delete ${displayName(user)}`" :disabled="!user.onboarding_pending" @click="askDelete(user)">
                      <LucideIcon :icon="Trash2" class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!firstLoading && !users.length">
                <td colspan="5" class="px-4 py-8 text-center text-xs text-gray-500">No users match these filters.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-end border-t border-gray-200 p-3 dark:border-gray-800">
          <Pagination :current-page="paginator.current_page ?? 1" :last-page="paginator.last_page ?? 1" :from="paginator.from" :to="paginator.to" :total="paginator.total ?? 0" @page="goToPage" />
        </div>
      </div>
    </template>

    <Modal :open="createAccountOpen" :title="createAccountTitle" :subtitle="createAccountSubtitle" @close="closeCreateAccount">
      <div v-if="!createAccountMode" class="grid gap-2.5 sm:grid-cols-2">
        <button type="button" class="rounded-md border border-gray-200 p-3 text-left transition hover:border-brand-400 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500 dark:hover:bg-brand-500/5" @click="selectCreateAccountMode('single')">
          <span class="block text-xs font-semibold text-gray-800 dark:text-white/90">New User</span>
          <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">Create one account with a temporary password.</span>
        </button>
        <button type="button" class="rounded-md border border-gray-200 p-3 text-left transition hover:border-brand-400 hover:bg-brand-50/50 dark:border-gray-700 dark:hover:border-brand-500 dark:hover:bg-brand-500/5" @click="selectCreateAccountMode('bulk')">
          <span class="block text-xs font-semibold text-gray-800 dark:text-white/90">Generate Accounts</span>
          <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">Create 1–20 accounts for one role.</span>
        </button>
      </div>

      <form v-else-if="createAccountMode === 'single'" class="space-y-2.5" @submit.prevent="submitCreate">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="new-username">Username</label>
          <input id="new-username" v-model="createForm.username" required maxlength="255" class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <FieldError :errors="createErrors" field="username" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="new-role">Role</label>
          <select id="new-role" v-model="createForm.role_id" required class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <option value="">Select a role</option>
            <option v-for="role in creatableRoles" :key="role.role_id" :value="role.role_id">{{ role.role_name }}</option>
          </select>
          <FieldError :errors="createErrors" field="role_id" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="new-password">Password (optional)</label>
          <input id="new-password" v-model="createForm.password" type="password" minlength="6" class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <FieldError :errors="createErrors" field="password" />
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:text-gray-300" @click="createAccountMode = ''">Back</button>
          <button type="submit" class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600" :disabled="working">{{ working ? 'Saving…' : 'Create' }}</button>
        </div>
      </form>

      <form v-else class="space-y-2.5" @submit.prevent="submitGenerate">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="gen-role">Role</label>
          <select id="gen-role" v-model="generateForm.role_id" required class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <option value="">Select a role</option>
            <option v-for="role in creatableRoles" :key="role.role_id" :value="role.role_id">{{ role.role_name }}</option>
          </select>
          <FieldError :errors="generateErrors" field="role_id" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="gen-count">Count (1–20)</label>
          <input id="gen-count" v-model.number="generateForm.count" type="number" required min="1" max="20" class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <FieldError :errors="generateErrors" field="count" />
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:text-gray-300" @click="createAccountMode = ''">Back</button>
          <button type="submit" class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600" :disabled="working">{{ working ? 'Saving…' : 'Generate' }}</button>
        </div>
      </form>
    </Modal>

    <Modal :open="slipsOpen" title="Export Account Slips" subtitle="Download printable PDF slips with account credentials." @close="slipsOpen = false">
      <form class="space-y-3" @submit.prevent="exportSlips">
        <div class="flex items-center justify-between">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
            Select Accounts ({{ selectedSlipUsers.length }} selected)
          </p>
          <button type="button" class="text-[11px] font-medium text-brand-600 hover:underline dark:text-brand-400" @click="toggleAllSlipUsers">
            {{ allSlipUsersSelected ? 'Clear All' : 'Select All' }}
          </button>
        </div>
        <div class="max-h-56 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-2 dark:border-gray-700 dark:bg-gray-800/40">
          <label v-for="user in users" :key="user.id" class="flex cursor-pointer items-center justify-between rounded-md px-2 py-1 text-[11px] transition hover:bg-gray-50 dark:hover:bg-gray-700/60">
            <span class="flex items-center gap-2">
              <input v-model="selectedSlipUsers" type="checkbox" :value="user.id" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700" />
              <span>
                <span class="block text-xs font-medium text-gray-900 dark:text-white">{{ displayName(user) }} <span class="text-gray-400">(@{{ user.username }})</span></span>
                <span class="block text-[10px] text-gray-500 dark:text-gray-400">{{ user.role?.role_name ?? 'No role' }}</span>
              </span>
            </span>
            <StatusBadge :status="user.status" />
          </label>
          <p v-if="!users.length" class="p-2 text-center text-[11px] text-gray-500 dark:text-gray-400">No accounts found.</p>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:text-gray-300" @click="slipsOpen = false">Cancel</button>
          <button type="submit" class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedSlipUsers.length || exportingSlips">
            {{ exportingSlips ? 'Preparing PDF…' : 'Download PDF Slips' }}
          </button>
        </div>
      </form>
    </Modal>

    <Modal :open="editUser !== null" title="Edit User" :subtitle="editUser?.onboarding_pending ? 'Update account name, password, role, or status.' : 'Only role and status can be changed after onboarding.'" @close="editUser = null">
      <form v-if="editUser" class="space-y-2.5" @submit.prevent="submitEdit">
        <div v-if="editUser.onboarding_pending">
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="edit-name">Display name</label>
          <input id="edit-name" v-model="editForm.name" maxlength="255" class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <FieldError :errors="editErrors" field="name" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="edit-role">Role</label>
          <select id="edit-role" v-model="editForm.role_id" required class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <option v-for="role in roles" :key="role.role_id" :value="role.role_id">{{ role.role_name }}</option>
          </select>
          <FieldError :errors="editErrors" field="role_id" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="edit-status">Status</label>
          <select id="edit-status" v-model="editForm.status" required class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <FieldError :errors="editErrors" field="status" />
        </div>
        <div v-if="editUser.onboarding_pending">
          <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300" for="edit-password">Reset password (optional)</label>
          <input id="edit-password" v-model="editForm.password" type="password" minlength="6" class="w-full rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
          <FieldError :errors="editErrors" field="password" />
        </div>
        <div v-else class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
          Personal name and password updates are handled by the user after onboarding.
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs dark:border-gray-700 dark:text-gray-300" @click="editUser = null">Cancel</button>
          <button type="submit" class="rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600" :disabled="working">{{ working ? 'Saving…' : 'Save' }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog :open="deleteUser !== null" title="Delete pending account?" :message="`Delete @${deleteUser?.username ?? ''}? Only accounts pending onboarding can be deleted.`" confirm-label="Delete" :busy="working" @cancel="deleteUser = null" @confirm="doDelete" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Pencil, Trash2 } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import MetricCard from '../../components/ui/data-display/MetricCard.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';
import FieldError from '../../components/ui/feedback/FieldError.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';

const toast = useToastStore();
const loading = ref(true);
const firstLoading = ref(true);
const error = ref('');
const working = ref(false);
const search = ref('');
const roleFilter = ref('');
const statusFilter = ref('');
const page = ref(1);
const users = ref([]);
const roles = ref([]);
const metrics = ref({});
const paginator = ref({ data: [] });

const createAccountOpen = ref(false);
const createAccountMode = ref('');
const slipsOpen = ref(false);
const selectedSlipUsers = ref([]);
const exportingSlips = ref(false);
const editUser = ref(null);
const deleteUser = ref(null);
const createForm = reactive({ username: '', role_id: '', password: '' });
const generateForm = reactive({ role_id: '', count: 5 });
const editForm = reactive({ name: '', role_id: '', status: 'active', password: '' });
const createErrors = ref({});
const generateErrors = ref({});
const editErrors = ref({});

let searchTimer = null;
const creatableRoles = computed(() => roles.value.filter((role) => String(role.role_name).toLowerCase() !== 'administrator'));
const allSlipUsersSelected = computed(() => users.value.length > 0 && users.value.every((user) => selectedSlipUsers.value.includes(user.id)));
const createAccountTitle = computed(() => {
  if (createAccountMode.value === 'single') return 'New User';
  if (createAccountMode.value === 'bulk') return 'Generate Accounts';
  return 'Create Account';
});
const createAccountSubtitle = computed(() => {
  if (createAccountMode.value === 'single') return 'Creates an account with a temporary password.';
  if (createAccountMode.value === 'bulk') return 'Bulk-create up to 20 accounts for one role.';
  return 'Choose how you want to create user accounts.';
});

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

function displayName(user) {
  const full = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return full || user.username || 'Unknown';
}

async function load(options = {}) {
  const params = {
    search: search.value || '',
    role_id: roleFilter.value || '',
    status: statusFilter.value || '',
    page: page.value,
  };
  await loadCachedPage({
    page: 'admin-users',
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/admin/users', {
        params: {
          search: params.search || undefined,
          role_id: params.role_id || undefined,
          status: params.status || undefined,
          page: params.page,
        },
      });
      return data;
    },
    applyData: (data) => {
      const userPage = data.users ?? {};
      users.value = userPage.data ?? [];
      paginator.value = userPage;
      roles.value = data.roles ?? [];
      metrics.value = data.metrics ?? {};
    },
    isCurrent: () => search.value === params.search
      && roleFilter.value === params.role_id
      && statusFilter.value === params.status
      && page.value === params.page,
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
      firstLoading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load users.';
    },
  });
}

function goToPage(next) {
  page.value = next;
  load();
}

function toggleAllSlipUsers() {
  selectedSlipUsers.value = allSlipUsersSelected.value ? [] : users.value.map((user) => user.id);
}

watch([search], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 100);
});
watch([roleFilter, statusFilter], () => { page.value = 1; load(); });

async function submitCreate() {
  working.value = true;
  createErrors.value = {};
  try {
    await api.post('/admin/users', {
      username: createForm.username,
      role_id: Number(createForm.role_id),
      ...(createForm.password ? { password: createForm.password } : {}),
    });
    closeCreateAccount();
    createForm.username = '';
    createForm.role_id = '';
    createForm.password = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      createErrors.value = requestError?.response?.data?.errors ?? {};
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not create the user.');
    }
  } finally {
    working.value = false;
  }
}

async function submitGenerate() {
  working.value = true;
  generateErrors.value = {};
  try {
    await api.post('/admin/users/generate', { role_id: Number(generateForm.role_id), count: Number(generateForm.count) });
    closeCreateAccount();
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      generateErrors.value = requestError?.response?.data?.errors ?? {};
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not generate accounts.');
    }
  } finally {
    working.value = false;
  }
}

function openCreateAccount() {
  createAccountMode.value = '';
  createErrors.value = {};
  generateErrors.value = {};
  createAccountOpen.value = true;
}

function selectCreateAccountMode(mode) {
  createAccountMode.value = mode;
  createErrors.value = {};
  generateErrors.value = {};
}

function closeCreateAccount() {
  createAccountOpen.value = false;
  createAccountMode.value = '';
}

async function exportSlips() {
  if (!selectedSlipUsers.value.length) return;

  exportingSlips.value = true;
  try {
    const response = await api.post('/admin/users/export-slips', {
      selected_user_ids: selectedSlipUsers.value,
    }, { responseType: 'blob' });
    const downloadUrl = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = 'user-account-slips.pdf';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
    slipsOpen.value = false;
    selectedSlipUsers.value = [];
  } catch (requestError) {
    let message = 'Unable to generate the PDF. Please try again.';
    if (requestError?.response?.data instanceof Blob) {
      try {
        const errorData = JSON.parse(await requestError.response.data.text());
        message = errorData.message ?? message;
      } catch {
        message = 'Unable to generate the PDF. Please try again.';
      }
    }
    toast.error('Error', message);
  } finally {
    exportingSlips.value = false;
  }
}

function openEdit(user) {
  editUser.value = user;
  editErrors.value = {};
  editForm.name = displayName(user);
  editForm.role_id = user.role_id;
  editForm.status = user.status;
  editForm.password = '';
}

async function submitEdit() {
  if (!editUser.value) return;
  working.value = true;
  editErrors.value = {};
  try {
    const payload = {
      role_id: Number(editForm.role_id),
      status: editForm.status,
    };
    if (editUser.value.onboarding_pending) {
      payload.name = editForm.name || null;
      if (editForm.password) payload.password = editForm.password;
    }
    await api.patch(`/admin/users/${editUser.value.id}`, payload);
    editUser.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      editErrors.value = requestError?.response?.data?.errors ?? {};
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not update the user.');
    }
  } finally {
    working.value = false;
  }
}

function askDelete(user) {
  deleteUser.value = user;
}

async function doDelete() {
  if (!deleteUser.value) return;
  working.value = true;
  try {
    await api.delete(`/admin/users/${deleteUser.value.id}`);
    deleteUser.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    if (requestError?.response?.status === 422) {
      toast.error('Error', requestError?.response?.data?.message ?? 'Could not delete the user.');
    }
  } finally {
    working.value = false;
  }
}

onMounted(() => load());
</script>
