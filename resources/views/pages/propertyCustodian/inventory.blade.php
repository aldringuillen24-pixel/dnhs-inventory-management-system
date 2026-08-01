@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Inventory" />

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Total Assigned Items"
                value="84"
                subtitle="Items currently assigned"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M4 3a1 1 0 00-1 1v2a1 1 0 001 1h1v8a1 1 0 001 1h8a1 1 0 001-1V7h1a1 1 0 001-1V4a1 1 0 00-1-1H4z'/><path d='M5 7V5h10V7H5z'/></svg>"
            >
                <span class="text-sm text-gray-500 dark:text-gray-400">Updated just now.</span>
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Pending Inspections"
                value="12"
                subtitle="Awaiting review"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm1-11V5a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0V9h2a1 1 0 100-2h-2z' clip-rule='evenodd'/></svg>"
                tone="positive"
            >
                New inspection tasks were added today.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Overdue Returns"
                value="6"
                subtitle="Items past due date"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M10 2a8 8 0 100 16 8 8 0 000-16zm1 9H9V7a1 1 0 112 0v4z'/></svg>"
                tone="negative"
            >
                Follow up with custodians for quick returns.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Maintenance Due"
                value="18"
                subtitle="Items requiring service"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M9.049 2.927a1 1 0 011.902 0l.518 2.128a8.04 8.04 0 013.054 1.771l1.996-.39a1 1 0 011.149 1.254l-.901 2.93a8.072 8.072 0 010 2.356l.9 2.929a1 1 0 01-1.15 1.255l-1.995-.39a8.04 8.04 0 01-3.056 1.772l-.516 2.128a1 1 0 01-1.902 0l-.517-2.128a8.04 8.04 0 01-3.055-1.772l-1.996.39a1 1 0 01-1.15-1.255l.901-2.93a8.072 8.072 0 010-2.356l-.9-2.929a1 1 0 011.15-1.255l1.995.39a8.04 8.04 0 013.055-1.771l.517-2.128z'/></svg>"
                tone="neutral"
            >
                Keep the maintenance schedule up to date.
            </x-cards.metric-card>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-12">
            <x-cards.base-card title="Manage Inventory" subtitle="Search and manage assigned assets">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex-1">
                        <input type="search" placeholder="Search assets" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Category</button>
                        <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Status</button>
                        <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-brand-500 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">Location</button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Unit</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">ICS No.</th>
                                <th class="px-4 py-3">Inventory Item No.</th>
                                <th class="px-4 py-3">Unit Cost</th>
                                <th class="px-4 py-3">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            <tr>
                                <td class="px-4 py-4 font-medium text-gray-900 dark:text-white">Laptop - Model X</td>
                                <td class="px-4 py-4">Electronics</td>
                                <td class="px-4 py-4">Main Office</td>
                                <td class="px-4 py-4 text-emerald-600">Active</td>
                                <td class="px-4 py-4">Jun 12, 2026</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection