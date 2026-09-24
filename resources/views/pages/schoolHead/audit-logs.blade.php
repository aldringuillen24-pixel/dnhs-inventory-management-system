@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Audit Logs" />

    <x-cards.base-card title="User Audit Logs" subtitle="Read-only record of user governance actions.">
        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">Performed By</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Target User</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->actor_name ?? ($log->actor?->first_name . ' ' . $log->actor?->last_name ?: $log->actor?->username ?? 'System') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ str_replace('_', ' ', $log->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->target_name ?? ($log->targetUser?->first_name . ' ' . $log->targetUser?->last_name ?: $log->targetUser?->username ?? 'Unknown') }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                @if (!empty($log->details))
                                    @foreach ($log->details as $key => $value)
                                        <div><span class="font-medium capitalize">{{ str_replace('_', ' ', $key) }}:</span> {{ is_array($value) ? json_encode($value) : $value }}</div>
                                    @endforeach
                                @else
                                    <span class="text-gray-400">No details</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                No audit logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </x-cards.base-card>
@endsection
