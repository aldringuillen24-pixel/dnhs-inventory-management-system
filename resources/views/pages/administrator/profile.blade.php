@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <x-cards.base-card title="Administrator Profile" subtitle="Your account details">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500 text-2xl font-semibold text-white">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ auth()->user()?->name ?? 'Administrator' }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()?->email ?? 'No email set' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()?->role?->role_name ?? 'Administrator' }}</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ ucfirst(auth()->user()?->status ?? 'active') }}</p>
                        </div>
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Last updated</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()?->updated_at?->format('M d, Y') ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-8">
            <x-cards.base-card title="Account Settings" subtitle="Review your profile information">
                <div class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                            <p class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ auth()->user()?->name ?? 'Not provided' }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                            <p class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ auth()->user()?->email ?? 'Not provided' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                            <p class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ auth()->user()?->username ?? 'Not provided' }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Temporary Password</label>
                            <p class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ auth()->user()?->temporary_password ? 'Set' : 'Not available' }}</p>
                        </div>
                    </div>

                    <div class="rounded-md border border-dashed border-gray-300 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-sm text-gray-700 dark:text-gray-200">If you need to update your profile details or password, use the administrator settings on your account management page or contact support.</p>
                    </div>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
