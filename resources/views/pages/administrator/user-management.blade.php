@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Management" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-4">
            <x-cards.base-card title="Add New User" subtitle="Create a user account for the system">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                        <input type="text" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter full name" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                        <input type="email" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Enter email" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <option>Administrator</option>
                            <option>Inventory Manager</option>
                            <option>Staff</option>
                        </select>
                    </div>
                    <button type="button" class="w-full rounded-md bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Create User
                    </button>
                </div>
            </x-cards.base-card>
        </div>

        <div class="xl:col-span-8">
            <x-cards.base-card title="User Accounts" subtitle="Manage registered users and access roles">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="w-full md:max-w-sm">
                        <input type="text" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Search users" />
                    </div>
                    <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:text-gray-200">
                        Filter
                    </button>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Juan Dela Cruz</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Administrator</p>
                        </div>
                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">Active</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Maria Santos</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Inventory Manager</p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Active</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Rico Fernandez</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Staff</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">Pending</span>
                    </div>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
