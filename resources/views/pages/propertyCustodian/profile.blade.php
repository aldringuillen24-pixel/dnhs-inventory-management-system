@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-5">
            <x-cards.base-card title="Profile Overview" subtitle="Your current account details">
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500 text-2xl font-semibold text-white">
                            {{ strtoupper(substr(auth()->user()?->first_name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ auth()->user()?->full_name ?? 'Property Custodian' }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()?->email ?? 'No email set' }}</p>
                        </div>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</p>
                            <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()?->role?->role_name ?? 'Property Custodian' }}</p>
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

        <div class="col-span-12 xl:col-span-7">
            <x-cards.base-card title="Edit Profile" subtitle="Update your profile information and password">
                <form method="POST" action="{{ route('propertyCustodian.profile.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name', auth()->user()?->first_name) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                            @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', auth()->user()?->last_name) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                            @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="username" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username', auth()->user()?->username) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                            @error('username')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-6 dark:border-gray-700">
                        <h4 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Change Password</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="current_password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                                <input id="current_password" name="current_password" type="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                                @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                                <input id="password" name="password" type="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Leave blank to keep current password" />
                                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Repeat new password" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed border-gray-200 bg-white p-5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <p class="font-semibold text-gray-900 dark:text-white">Keep your profile secure</p>
                        <p class="mt-2">Leave the password fields blank if you do not want to change your current password.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-common.button-spinner text="Save Changes" loadingText="Saving..." />
                    </div>
                </form>
            </x-cards.base-card>
        </div>
    </div>
@endsection