@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Requests" />

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-12">
            <x-cards.base-card title="Requests" subtitle="Requests assigned to you">
                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        <thead class="bg-gray-50 text-xs uppecase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr class="text-center">
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Requested By</th>
                                <th class="px-4 py-3">Requested On</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @forelse($requests as $req)
                                <tr class="text-center">
                                    <td class="px-4 py-4">{{ $req->quantity }}</td>
                                    <td class="px-4 py-4">{{ optional($req->item)->item_name ?? 'Unknown item' }}</td>
                                    <td class="px-4 py-4">{{ optional($req->user)->full_name ?? 'Custodian' }}</td>
                                    <td class="px-4 py-4">{{ $req->requested_at->format('Y-m-d') }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ ucfirst($req->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if($req->status === 'waiting for acceptance')
                                            <form method="POST" action="{{ route('endUser.requests.respond', $req->id) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="action" value="accept" />
                                                <button type="submit" class="rounded-md bg-green-500 px-3 py-1 text-sm font-medium text-white hover:bg-green-600">Accept</button>
                                            </form>
                                            <form method="POST" action="{{ route('endUser.requests.respond', $req->id) }}" class="inline ml-2">
                                                @csrf
                                                <input type="hidden" name="action" value="decline" />
                                                <button type="submit" class="rounded-md bg-red-500 px-3 py-1 text-sm font-medium text-white hover:bg-red-600">Decline</button>
                                            </form>
                                        @else
                                            <span class="text-sm text-gray-500">--</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center">No requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-cards.base-card>
        </div>
    </div>
@endsection
