@extends('layouts.app')

@section('content')

    <x-common.page-breadcrumb pageTitle="User Management" />

    <div x-data="{
        showSlipModal: false,
        showEditModal: false,
        showGenerateModal: false,
        showFilterPanel: false,
        openMenuId: null,
        selectedUsers: [],
        allUserIds: @js($users->pluck('id')->all()),
        editUserId: null,
        editName: '',
        editRoleId: '',
        editStatus: 'active',
        editPassword: '',
        generateRoleId: '{{ $roles->first()?->role_id ?? '' }}',
        generateCount: 5,
        isAllSelected() {
            return this.selectedUsers.length > 0 && this.selectedUsers.length === this.allUserIds.length;
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
            this.showEditModal = true;
            this.openMenuId = null;
        },
        closeEdit() {
            this.showEditModal = false;
            this.editUserId = null;
            this.editName = '';
            this.editRoleId = '';
            this.editStatus = 'active';
            this.editPassword = '';
        },
        openGenerate() {
            this.generateRoleId = '{{ $roles->first()?->role_id ?? '' }}';
            this.generateCount = 5;
            this.showGenerateModal = true;
        },
        closeGenerate() {
            this.showGenerateModal = false;
        },
        get editFormAction() {
            return this.editUserId ? '{{ url('/admin/users-management') }}/' + this.editUserId : '#';
        }
    }">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <x-cards.base-card title="Add New User" subtitle="Create a user account for the system">
                <form method="POST" action="{{ route('admin.users-management.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User Name</label>
                        <input type="text" name="username" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter full name" required />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" name="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter password (optional)" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select name="role_id" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full rounded-md bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Create User
                    </button>
                    <button type="button" @click="openGenerate()" class="mt-3 w-full rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-900">
                        Generate Multiple Users
                    </button>
                </form>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-8">
            <x-cards.base-card title="User Accounts" subtitle="Manage registered users and access roles">
                <form method="GET" action="{{ route('admin.users-management') }}" class="mb-4">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="w-full md:max-w-sm">
                            <input
                                type="search"
                                name="search"
                                value="{{ request('search') }}"
                                @input.debounce.500ms="$event.target.form.submit()"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                placeholder="Search users"
                                aria-label="Search users"
                            />
                        </div>
                        <div class="flex flex-wrap gap-2 items-center">
                            <button
                                type="button"
                                @click.prevent="showFilterPanel = !showFilterPanel"
                                class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200"
                            >
                                Filter
                            </button>
                            <a
                                href="{{ route('admin.users-management') }}"
                                class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200"
                            >
                                Reset
                            </a>
                            <button type="button" @click="showSlipModal = true" class="rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                                Export Slip
                            </button>
                        </div>
                    </div>

                    <div x-show="showFilterPanel" x-cloak class="rounded-md border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                                <select name="role_id" @change="$event.target.form.submit()" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">All roles</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->role_id }}" {{ request('role_id') == $role->role_id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select name="status" @change="$event.target.form.submit()" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <option value="">All statuses</option>
                                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="space-y-3">
                    @forelse($users as $user)
                        <div class="flex items-center justify-between rounded-md border border-gray-200 p-3 dark:border-gray-700">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $user->first_name }} {{ $user->last_name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->role?->role_name ?? 'No role assigned' }}</p>
                            </div>
                            <div class="relative flex items-center gap-2">
                                <span class="rounded-md px-3 py-1 text-xs font-medium {{ $user->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                    {{ ucfirst($user->status ?? 'inactive') }}
                                </span>
                                <div class="relative">
                                    <button type="button" @click="openMenuId = openMenuId === {{ $user->id }} ? null : {{ $user->id }}" class="rounded-full p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="More actions">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 5.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                        </svg>
                                    </button>
                                    <div x-show="openMenuId === {{ $user->id }}" x-cloak class="absolute right-0 top-7 z-10 w-32 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                        <button type="button" @click="openEdit(@js(['id' => $user->id, 'name' => trim($user->first_name . ' ' . $user->last_name), 'role_id' => $user->role_id ?? '', 'status' => $user->status ?? 'active']))" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-md border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            No account yet
                        </div>
                    @endforelse
                </div>

                <div class="mt-4 border-t border-gray-200 pt-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                    @if ($users->lastPage() > 1)
                        {{ $users->links() }}
                    @else
                        <nav class="flex items-center justify-center gap-2">
                            <button class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800" disabled>
                                1
                            </button>
                        </nav>
                    @endif
                </div>
            </x-cards.base-card>
        </div>
        </div>

        <div x-show="showSlipModal" x-transition class="fixed inset-0 z-[1100] flex items-center justify-center" style="display: none;" @click.self="showSlipModal = false">
            <div class="w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-2 flex items-start justify-end gap-3">
                    <button type="button" @click="showSlipModal = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.users-management.export-slips') }}" class="space-y-4">
                    @csrf
                    <template x-for="userId in selectedUsers" :key="userId">
                        <input type="hidden" name="selected_user_ids[]" :value="userId">
                    </template>

                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Generate Slip</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Preview the slip before printing.</p>
                        </div>
                        <button type="button" @click="toggleSelectAll()" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            <span x-text="isAllSelected() ? 'Clear All' : 'Select All'"></span>
                        </button>
                    </div>

                    <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        @forelse($users as $user)
                            <label class="flex items-center justify-between rounded-md border border-gray-100 px-3 py-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" :checked="selectedUsers.includes({{ $user->id }})" @change="toggleUser({{ $user->id }})" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->first_name }} {{ $user->last_name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->role?->role_name ?? 'No role assigned' }}</p>
                                    </div>
                                </div>
                                <span class="text-xs tracking-wide text-green-700 dark:text-gray-400">{{ ucfirst($user->status ?? 'inactive') }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No accounts available.</p>
                        @endforelse
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showSlipModal = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            Close
                        </button>
                        <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            Print PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showGenerateModal" x-transition class="fixed inset-0 z-[1200] flex items-center justify-center" style="display: none;" @click.self="closeGenerate()">
            <div class="w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Generate Users</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select role and quantity to create random user accounts.</p>
                    </div>
                    <button type="button" @click="closeGenerate()" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.users-management.generate-users') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select name="role_id" x-model="generateRoleId" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Count</label>
                        <input type="number" name="count" x-model="generateCount" min="1" max="20" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeGenerate()" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            Generate Users
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showEditModal" x-transition class="fixed inset-0 z-[1200] flex items-center justify-center" style="display: none;" @click.self="closeEdit()">
            <div class="w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Edit User</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the selected user account details.</p>
                    </div>
                    <button type="button" @click="closeEdit()" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <form :action="editFormAction" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" x-model="editName" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                    </div>

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

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" name="password" x-model="editPassword" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Leave blank to keep current password" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeEdit()" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
