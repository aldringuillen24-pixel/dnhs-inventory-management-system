@extends('layouts.app', ['title' => 'System Reports'])

@section('content')
    {{-- Print Style Helper --}}
    <style>
        @media print {
            .no-print, aside, header, nav {
                display: none !important;
            }
            main {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
        }
    </style>

    {{-- Breadcrumb + Action Toolbar --}}
    <div class="mb-4 flex flex-col gap-4 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
        <x-common.page-breadcrumb pageTitle="System Reports" />

        <div class="no-print flex items-center gap-3">
            <button 
                type="button" 
                onclick="window.print()" 
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect width="12" height="8" x="6" y="14"/>
                </svg>
                <span>Print</span>
            </button>

            <a 
                href="{{ route('admin.reports.download') }}" 
                class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span>Download PDF Report</span>
            </a>
        </div>
    </div>

    {{-- Unified 4-Card System Metrics Strip --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card 
            title="Active Users" 
            value="{{ number_format($metrics['activeUsers']) }}" 
            subtitle="Currently enabled accounts" 
            class="border-l-4 border-l-indigo-500"
            iconClass="bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </x-slot:icon>
        </x-cards.metric-card>

        <x-cards.metric-card 
            title="Pending Setup" 
            value="{{ number_format($metrics['pendingOnboarding']) }}" 
            subtitle="Awaiting onboarding setup" 
            class="border-l-4 border-l-amber-500"
            iconClass="bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </x-slot:icon>
        </x-cards.metric-card>

        <x-cards.metric-card 
            title="Pending Requests" 
            value="{{ number_format($metrics['pendingRequests']) }}" 
            subtitle="Awaiting custodian review" 
            class="border-l-4 border-l-sky-500"
            iconClass="bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-400"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/>
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                    <path d="M12 11h4"/>
                    <path d="M12 16h4"/>
                    <path d="M8 11h.01"/>
                    <path d="M8 16h.01"/>
                </svg>
            </x-slot:icon>
        </x-cards.metric-card>

        <x-cards.metric-card 
            title="Active Maintenance" 
            value="{{ number_format($metrics['openMaintenance']) }}" 
            subtitle="Units needing repair or inspection" 
            class="border-l-4 border-l-rose-500"
            iconClass="bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
        >
            <x-slot:icon>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
            </x-slot:icon>
        </x-cards.metric-card>
    </div>

    {{-- Roles and workflow analytics --}}
    <div class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                Roles & Maintenance Analytics
            </h3>
            <span class="text-xs text-gray-400">Account roles, request flows, and maintenance tracking</span>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Account Roles Chart --}}
            <x-cards.base-card title="Account Roles" subtitle="Distribution of registered user roles">
                <x-slot:actions>
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300">
                        {{ $roleData->sum('value') }} Accounts
                    </span>
                </x-slot:actions>

                @if ($roleData->isEmpty() || $roleData->sum('value') === 0)
                    <div class="flex min-h-[280px] flex-col items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs">No account role data available.</p>
                    </div>
                @else
                    <div id="adminSystemRoleChart" class="min-h-[280px]" data-labels='@json($roleData->pluck("label")->values())' data-values='@json($roleData->pluck("value")->values())'></div>
                @endif
            </x-cards.base-card>

            {{-- Assignment Requests Chart --}}
            <x-cards.base-card title="Assignment Requests" subtitle="Overview of request workflow statuses">
                <x-slot:actions>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300">
                        {{ $requestStatusData->sum('value') }} Requests
                    </span>
                </x-slot:actions>

                @if ($requestStatusData->isEmpty() || $requestStatusData->sum('value') === 0)
                    <div class="flex min-h-[280px] flex-col items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs">No request workflow records available.</p>
                    </div>
                @else
                    <div id="adminSystemRequestChart" class="min-h-[280px]" data-labels='@json($requestStatusData->pluck("label")->values())' data-values='@json($requestStatusData->pluck("value")->values())'></div>
                @endif
            </x-cards.base-card>

            {{-- Maintenance Records Chart --}}
            <x-cards.base-card title="Maintenance Records" subtitle="Overview of equipment maintenance statuses">
                <x-slot:actions>
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-300">
                        {{ $maintenanceStatusData->sum('value') }} Records
                    </span>
                </x-slot:actions>

                @if ($maintenanceStatusData->isEmpty() || $maintenanceStatusData->sum('value') === 0)
                    <div class="flex min-h-[280px] flex-col items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs">No maintenance records recorded.</p>
                    </div>
                @else
                    <div id="adminSystemMaintenanceChart" class="min-h-[280px]" data-labels='@json($maintenanceStatusData->pluck("label")->values())' data-values='@json($maintenanceStatusData->pluck("value")->values())'></div>
                @endif
            </x-cards.base-card>
        </div>
    </div>

    {{-- Recent system activity --}}
    <div class="mt-8">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
               Recent Activity & Audit Ledger
            </h3>
            <span class="text-xs text-gray-400">Audit trail of transactions and administrative events</span>
        </div>

        <div>
            {{-- Recent System Audit Activity with Client-Side Filter/Search --}}
            <x-cards.base-card title="Recent System Audit Activity" subtitle="Latest account updates, role modifications, and audit logs">
                <x-slot:actions>
                    <a 
                        href="{{ route('admin.users-management') }}" 
                        class="no-print inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                    >
                        <span>Manage Users</span>
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </x-slot:actions>

                <div 
                    x-data="{
                        search: '',
                        actionFilter: 'all',
                        matches(action, actor, target) {
                            const q = this.search.toLowerCase().trim();
                            const matchesQuery = !q || action.toLowerCase().includes(q) || actor.toLowerCase().includes(q) || target.toLowerCase().includes(q);
                            const matchesFilter = this.actionFilter === 'all' || action.toLowerCase().includes(this.actionFilter);
                            return matchesQuery && matchesFilter;
                        }
                    }"
                >
                    {{-- Filter & Search Toolbar --}}
                    <div class="no-print mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative max-w-sm flex-1">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.3-4.3"/>
                                </svg>
                            </div>
                            <input 
                                x-model="search" 
                                type="text" 
                                placeholder="Search by actor, target, or action..." 
                                class="w-full rounded-md border border-gray-300 bg-white py-1.5 pl-9 pr-3 text-xs text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <svg class="h-3.5 w-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                            </svg>
                            <label class="text-xs text-gray-500 dark:text-gray-400">Action:</label>
                            <select 
                                x-model="actionFilter" 
                                class="rounded-md border border-gray-300 bg-white py-1.5 pl-2 pr-7 text-xs text-gray-900 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="all">All Actions</option>
                                <option value="create">Created / Generated</option>
                                <option value="role">Role Updates</option>
                                <option value="status">Status Changes</option>
                                <option value="password">Password / Auth</option>
                            </select>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Action</th>
                                    <th class="px-4 py-3">Actor</th>
                                    <th class="px-4 py-3">Target</th>
                                    <th class="px-4 py-3 text-right">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($recentActivity as $activity)
                                    @php
                                        $actionLower = strtolower($activity->action);
                                    @endphp
                                    <tr 
                                        x-show="matches('{{ addslashes($activity->action) }}', '{{ addslashes($activity->actor_name ?: 'System') }}', '{{ addslashes($activity->target_name ?: '') }}')"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    >
                                        <td class="px-4 py-3">
                                            @if(str_contains($actionLower, 'create') || str_contains($actionLower, 'generate'))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                    {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                                </span>
                                            @elseif(str_contains($actionLower, 'role'))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-900/30 dark:text-purple-300">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                                                    {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                                </span>
                                            @elseif(str_contains($actionLower, 'status') || str_contains($actionLower, 'deactivate'))
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                    {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-800 dark:text-gray-300">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                                    {{ ucwords(str_replace('_', ' ', $activity->action)) }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            {{ $activity->actor_name ?: 'System' }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                            {{ $activity->target_name ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs text-gray-500 dark:text-gray-400">
                                            <span title="{{ $activity->created_at?->format('M d, Y h:i A') ?? 'N/A' }}">
                                                {{ $activity->created_at?->format('M d, Y H:i') ?? 'N/A' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <p class="text-xs font-medium">No system activity recorded yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
