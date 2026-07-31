@extends('layouts.app')

@section('content')
    @php
        $roles = App\Models\Role::orderBy('role_name')->get();
        $users = App\Models\User::with('role')->orderBy('created_at', 'desc')->get();
    @endphp

    <x-common.page-breadcrumb pageTitle="User Management" />

    @if (session('success'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div x-data="{ showSlipModal: false, openMenuId: null }">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <x-cards.base-card title="Add New User" subtitle="Create a user account for the system">
                <form method="POST" action="{{ route('admin.users-management.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User Name</label>
                        <input type="text" name="name" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter full name" required />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input type="password" name="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter password" required />
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
                </form>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-8">
            <x-cards.base-card title="User Accounts" subtitle="Manage registered users and access roles">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="w-full md:max-w-sm">
                        <input type="text" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Search users" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            Filter
                        </button>
                        <button type="button" @click="showSlipModal = true" class="rounded-md bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            Print Slip
                        </button>
                    </div>
                </div>

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
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Edit</button>
                                        <button type="button" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Lock</button>
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
            </x-cards.base-card>
        </div>
        </div>

        <div x-show="showSlipModal" x-transition class="fixed inset-0 z-[1100] flex items-center justify-center" style="display: none;" @click.self="showSlipModal = false">
            <div class="w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Generate Slip</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Preview the slip before printing.</p>
                    </div>
                    <button type="button" @click="showSlipModal = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Slip Preview</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This is a placeholder slip content for the selected user account.</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="showSlipModal = false" class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                            Close
                        </button>
                        <button type="button" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">
                            Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
