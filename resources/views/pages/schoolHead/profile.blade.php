@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    @if (session('success'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-700 dark:bg-green-900/20 dark:text-green-300">{{ session('success') }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <x-cards.base-card title="Profile Overview" subtitle="Your account details">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-500 text-2xl font-semibold text-white">{{ strtoupper(substr(auth()->user()?->first_name ?? 'S', 0, 1)) }}</div>
                    <div><p class="text-lg font-semibold text-gray-900 dark:text-white">{{ auth()->user()?->full_name ?? 'School Head' }}</p><p class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()?->email ?? 'No email set' }}</p></div>
                </div>
                <dl class="mt-6 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-gray-500">Role</dt><dd class="font-medium text-gray-900 dark:text-white">{{ auth()->user()?->role?->role_name ?? 'School Head' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-gray-500">Status</dt><dd class="font-medium text-gray-900 dark:text-white">{{ ucfirst(auth()->user()?->status ?? 'active') }}</dd></div></dl>
            </x-cards.base-card>
        </div>
        <div class="xl:col-span-8">
            <x-cards.base-card title="Edit Profile" subtitle="Update your profile information">
                <form method="POST" action="{{ route('schoolHead.profile.update') }}" class="space-y-5">@csrf @method('PATCH')
                    <div class="grid gap-4 md:grid-cols-2"><div><label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label><input id="first_name" name="first_name" value="{{ old('first_name', auth()->user()?->first_name) }}" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div><div><label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label><input id="last_name" name="last_name" value="{{ old('last_name', auth()->user()?->last_name) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div>
                    <div><label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label><input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                    <div class="border-t border-gray-200 pt-5 dark:border-gray-700"><h4 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Change Password</h4><div class="grid gap-4 md:grid-cols-2"><input name="password" type="password" placeholder="New password (optional)" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /><input name="password_confirmation" type="password" placeholder="Confirm new password" class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div></div>
                    <div class="flex justify-end"><button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Save Changes</button></div>
                </form>
            </x-cards.base-card>
        </div>
    </div>
@endsection