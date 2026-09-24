@extends('layouts.app', ['title' => 'User Management'])

@section('content')

    <x-common.page-breadcrumb pageTitle="User Management" />

    {{-- Top Metrics Grid --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card 
            title="Total Accounts" 
            value="{{ number_format($metrics['total']) }}" 
            subtitle="Registered system users"
        />
        <x-cards.metric-card 
            title="Active Users" 
            value="{{ number_format($metrics['active']) }}" 
            subtitle="Currently enabled access" 
            class="border-l-4 border-l-green-500"
        />
        <x-cards.metric-card 
            title="Inactive Users" 
            value="{{ number_format($metrics['inactive']) }}" 
            subtitle="Deactivated or suspended" 
            class="border-l-4 border-l-gray-400"
        />
        <x-cards.metric-card 
            title="Roles Breakdown" 
            value="{{ number_format($metrics['custodians'] + $metrics['endUsers'] + $metrics['admins']) }}" 
            subtitle="{{ $metrics['custodians'] }} Custodian · {{ $metrics['endUsers'] }} End User · {{ $metrics['admins'] }} Admin"
        />
    </div>

    <div x-data="{
        selectedUsers: [],
        allUserIds: @js($users->pluck('id')->all()),
        editUserId: null,
        editName: '',
        editRoleId: '',
        editStatus: 'active',
        editPassword: '',
        canEditIdentity: false,
        generateRoleId: '{{ $roles->first()?->role_id ?? '' }}',
        generateCount: 5,
        isAllSelected() {
            return this.allUserIds.length > 0 && this.selectedUsers.length === this.allUserIds.length;
        },
        toggleUser(userId) {
            if (this.selectedUsers.includes(userId)) {
                this.selectedUsers = this.selectedUsers.filter((id) => id !== userId);
            } else {
                this.selectedUsers.push(userId);
            }
        },
        toggleSelectAll() {
            this.selectedUsers = this.isAllSelected() ? [] : [...this.allUserIds];
        },
        openEdit(user) {
            this.editUserId = user.id;
            this.editName = user.name;
            this.editRoleId = user.role_id;
            this.editStatus = user.status || 'active';
            this.editPassword = '';
            this.canEditIdentity = Boolean(user.temporary_password !== null && user.temporary_password !== '');
            this.$dispatch('open-modal', 'edit-user-modal');
        },
        get editFormAction() {
            return this.editUserId ? '{{ url('/admin/users-management') }}/' + this.editUserId : '#';
        }
    }">
        {{-- Main Table Card --}}
        <x-cards.base-card title="System User Accounts" subtitle="Manage registered user credentials, roles, and status.">
            {{-- Toolbar: Filters on left, Action buttons on right --}}
            <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="GET" action="{{ route('admin.users-management') }}" class="flex flex-1 flex-wrap items-center gap-3">
                    <div class="w-full sm:w-64">
                        <input
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by name, username..."
                            x-on:input.debounce.300ms="$el.form.submit()"
                            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        />
                    </div>

                    <div class="w-full sm:w-44">
                        <select name="role_id" onchange="this.form.submit()" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">All Roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}" @selected(request('role_id') == $role->role_id)>{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-36">
                        <select name="status" onchange="this.form.submit()" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <option value="">All Statuses</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                    </div>

                    @if(request()->hasAny(['search', 'role_id', 'status']))
                        <a href="{{ route('admin.users-management') }}" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            Reset
                        </a>
                    @endif
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Add User Modal Trigger --}}
                    <x-modals.base-modal modalId="add-user-modal" title="Add New User" subtitle="Create a single user account for the system." maxWidth="max-w-lg">
                        <x-slot:trigger>
                            <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add User
                            </button>
                        </x-slot:trigger>

                        <form method="POST" action="{{ route('admin.users-management.store') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User Full Name / Identifier</label>
                                <input type="text" name="username" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="e.g. Maria Santos" required />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">A unique username slug will be generated automatically.</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                                <select name="role_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    @foreach($roles as $role)
                                        @if(strtolower($role->role_name) !== 'administrator')
                                            <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password (Optional)</label>
                                <input type="password" name="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Leave blank to auto-generate temporary password" />
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                    Cancel
                                </button>
                                <x-common.button-spinner text="Create User" loadingText="Creating..." class="text-white" />
                            </div>
                        </form>
                    </x-modals.base-modal>

                    {{-- Generate Multiple Users Modal Trigger --}}
                    <x-modals.base-modal modalId="generate-users-modal" title="Generate Multiple Accounts" subtitle="Batch-create temporary random user accounts for quick assignment." maxWidth="max-w-lg">
                        <x-slot:trigger>
                            <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Generate Multiple
                            </button>
                        </x-slot:trigger>

                        <form method="POST" action="{{ route('admin.users-management.generate-users') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Target Role</label>
                                <select name="role_id" x-model="generateRoleId" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                                    @foreach($roles as $role)
                                        @if(strtolower($role->role_name) !== 'administrator')
                                            <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Number of Accounts</label>
                                <input type="number" name="count" x-model="generateCount" min="1" max="20" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Generates between 1 to 20 accounts with random credentials.</p>
                            </div>
                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                    Cancel
                                </button>
                                <x-common.button-spinner text="Generate Users" loadingText="Generating..." class="text-white" />
                            </div>
                        </form>
                    </x-modals.base-modal>

                    {{-- Export Slips Modal Trigger --}}
                    <x-modals.base-modal modalId="export-slips-modal" title="Export Account Slips" subtitle="Download printable PDF slips with account credentials." maxWidth="max-w-lg">
                        <x-slot:trigger>
                            <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-brand-500 hover:text-brand-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Export Slips
                            </button>
                        </x-slot:trigger>

                        <form method="POST" action="{{ route('admin.users-management.export-slips') }}" class="space-y-4" x-data="{ async exportSlips(event) { const response = await fetch(event.currentTarget.action, { method: 'POST', body: new FormData(event.currentTarget), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/pdf' } }); if (!response.ok || !response.headers.get('content-type')?.includes('application/pdf')) { throw new Error('Unable to generate the PDF.'); } const blob = await response.blob(); const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'user-account-slips.pdf'; link.click(); URL.revokeObjectURL(link.href); window.dispatchEvent(new CustomEvent('pdf-download-finished')); window.dispatchEvent(new CustomEvent('export-slips-success')); window.dispatchEvent(new CustomEvent('close-modal', { detail: 'export-slips-modal' })); } }" @submit.prevent="exportSlips($event).catch(() => { window.dispatchEvent(new CustomEvent('pdf-download-finished')); alert('Unable to generate the PDF. Please try again.'); })">
                            @csrf
                            <template x-for="userId in selectedUsers" :key="userId">
                                <input type="hidden" name="selected_user_ids[]" :value="userId">
                            </template>

                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                    Select Accounts (<span x-text="selectedUsers.length"></span> selected)
                                </p>
                                <button type="button" @click="toggleSelectAll()" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                                    <span x-text="isAllSelected() ? 'Clear All' : 'Select All'"></span>
                                </button>
                            </div>

                            <div class="max-h-64 space-y-1.5 overflow-y-auto rounded-lg border border-gray-200 p-2.5 dark:border-gray-700 dark:bg-gray-800/40">
                                @forelse($users as $user)
                                    <label class="flex items-center justify-between rounded-md px-2.5 py-1.5 text-xs transition hover:bg-gray-50 dark:hover:bg-gray-700/60">
                                        <div class="flex items-center gap-2.5">
                                            <input type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700" :checked="selectedUsers.includes({{ $user->id }})" @change="toggleUser({{ $user->id }})" />
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ $user->first_name }} {{ $user->last_name }} <span class="text-gray-400">({{ '@' . $user->username }})</span></p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $user->role?->role_name ?? 'No role' }}</p>
                                            </div>
                                        </div>
                                        <span class="text-[11px] capitalize text-gray-500">{{ $user->status }}</span>
                                    </label>
                                @empty
                                    <p class="p-3 text-center text-xs text-gray-500 dark:text-gray-400">No accounts found.</p>
                                @endforelse
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                                    Cancel
                                </button>
                                <x-common.button-spinner text="Print PDF Slips" loadingText="Preparing PDF..." resetEvent="pdf-download-finished" :disabled="'selectedUsers.length === 0'" class="text-white" />
                            </div>
                        </form>
                    </x-modals.base-modal>
                </div>
            </div>

            {{-- Full-Width User Accounts Data Table matching All Inventory styling --}}
            <div class="max-h-[32rem] overflow-y-auto overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @php
                    $roleGroups = [
                        'End User' => 'End Users',
                        'Administrator' => 'Administrators',
                        'Property Custodian' => 'Property Custodians',
                        'Inspector' => 'Inspectors',
                        'School Head' => 'School Heads',
                    ];
                    $usersByRole = $users->getCollection()->groupBy(fn ($user) => $user->role?->role_name ?? 'No role assigned');
                @endphp
                <table class="min-w-full border-separate border-spacing-0 divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                    <thead class="sticky top-0 z-20 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="sticky top-0 z-20 bg-gray-50 px-4 py-3 dark:bg-gray-800">User</th>
                            <th class="sticky top-0 z-20 bg-gray-50 px-4 py-3 dark:bg-gray-800">Role</th>
                            <th class="sticky top-0 z-20 bg-gray-50 px-4 py-3 dark:bg-gray-800">Status</th>
                            <th class="sticky top-0 z-20 bg-gray-50 px-4 py-3 dark:bg-gray-800">Date Created</th>
                            <th class="sticky top-0 z-20 bg-gray-50 px-4 py-3 text-right dark:bg-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @if($users->isEmpty())
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No user accounts found.
                                </td>
                            </tr>
                        @else
                        @foreach($roleGroups as $roleName => $roleLabel)
                            @if($usersByRole->has($roleName))
                                <tr class="bg-gray-600">
                                    <th colspan="5" class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-white dark:text-gray-400">
                                        {{ $roleLabel }}
                                        <span class="ml-1 font-normal normal-case tracking-normal text-white dark:text-gray-500">({{ $usersByRole->get($roleName)->count() }})</span>
                                    </th>
                                </tr>
                            @endif

                            @foreach($usersByRole->get($roleName, collect()) as $user)
                                @php
                                    $fullName = trim($user->first_name . ' ' . $user->last_name) ?: $user->username;
                                    $roleName = $user->role?->role_name ?? 'No role assigned';
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60" :class="selectedUsers.includes({{ $user->id }}) ? 'bg-brand-50/40 dark:bg-brand-950/20' : ''">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $fullName }}
                                    </div>
                                    <span class="text-xs text-gray-400">
                                        {{ '@' . $user->username }}@if($user->email) · {{ $user->email }}@endif
                                    </span>
                                    @if($user->temporary_password)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Pending onboarding</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $roleName }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($user->status === 'active')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium capitalize text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ optional($user->created_at)->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button 
                                        type="button" 
                                        @click="openEdit(@js(['id' => $user->id, 'name' => $fullName, 'role_id' => $user->role_id ?? '', 'status' => $user->status ?? 'active', 'temporary_password' => $user->temporary_password]))" 
                                        class="rounded-md border px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </button>
                                    @if($user->temporary_password)
                                        <form method="POST" action="{{ route('admin.users-management.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this account before onboarding is completed?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-1 rounded-md border border-red-200 px-2.5 py-1.5 text-xs text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                </tr>
                            @endforeach
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Pagination footer --}}
            @if ($users->hasPages())
                <div class="mt-4 flex flex-col items-center justify-between gap-3 border-t border-gray-200 pt-4 sm:flex-row dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} accounts
                    </p>
                    <div>
                        {{ $users->links() }}
                    </div>
                </div>
            @endif
        </x-cards.base-card>

        {{-- Edit User Modal using base-modal component --}}
        <x-modals.base-modal modalId="edit-user-modal" title="Edit User Account" subtitle="Update account credentials, assigned role, or status." maxWidth="max-w-lg">
            <form :action="editFormAction" method="POST" class="space-y-4">
                @csrf
                @method('PATCH')

                <template x-if="canEditIdentity">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                        <input type="text" name="name" x-model="editName" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                    </div>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select name="role_id" x-model="editRoleId" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" x-model="editStatus" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <template x-if="canEditIdentity">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reset Password</label>
                        <input type="password" name="password" x-model="editPassword" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Leave blank to keep current password" />
                    </div>
                </template>

                <template x-if="!canEditIdentity">
                    <div class="rounded-md border border-amber-900 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-200">
                        Personal name and password updates are handled by the user after onboarding.
                    </div>
                </template>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="open = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">
                        Cancel
                    </button>
                    <x-common.button-spinner text="Save Changes" loadingText="Saving..." class="text-white" />
                </div>
            </form>
        </x-modals.base-modal>
    </div>
@endsection

