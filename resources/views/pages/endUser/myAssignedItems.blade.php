@extends('layouts.app')

@section('content')

    <x-common.page-breadcrumb pageTitle="My Assigned Items" />

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-12">
            <x-cards.base-card title="My Assigned Items" subtitle="Assigned and requested items for your account">
                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr class="text-center">
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Qty</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @forelse($activityRows as $row)
                                <tr class="text-center">
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium {{ $row['type'] === 'Assigned' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                                            {{ $row['type'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">{{ $row['item_name'] }}</td>
                                    <td class="px-4 py-4">{{ $row['quantity'] }}</td>
                                    <td class="px-4 py-4">{{ $row['date'] instanceof \DateTimeInterface ? $row['date']->format('Y-m-d') : \Carbon\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ ucfirst($row['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @php($requestRecord = $row['request'])
                                        @php($canTransfer = $row['type'] === 'Assigned' && in_array(strtolower((string) $row['status']), ['approved', 'accepted']))

                                        <div x-data="{ actionOpen: false, detailsModalOpen: false, transferModalOpen: false, menuStyle: '', toggleMenu(event) { if (!this.actionOpen) { const rect = event.currentTarget.getBoundingClientRect(); this.menuStyle = `top: ${rect.bottom + 4}px; left: ${rect.right - 144}px;`; } this.actionOpen = !this.actionOpen; } }" class="relative inline-block">
                                            <button type="button" @click="toggleMenu($event)" class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Actions for {{ $row['item_name'] }}">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                                                </svg>
                                            </button>

                                            <div x-show="actionOpen" x-cloak x-transition @click.outside="actionOpen = false" @keydown.escape.window="actionOpen = false" :style="menuStyle" class="fixed z-[1200] w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                                <button type="button" @click="actionOpen = false; detailsModalOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Details
                                                </button>

                                                @if($canTransfer)
                                                    <button type="button" @click="actionOpen = false; transferModalOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                        Transfer
                                                    </button>
                                                @endif
                                            </div>
    
                                            <div x-show="detailsModalOpen" x-cloak x-transition @keydown.escape.window="detailsModalOpen = false" class="fixed inset-0 z-[1300] flex items-start justify-center overflow-y-auto px-4 pt-24" role="dialog" aria-modal="true">
                                                <div class="w-full max-w-xl rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                    <div class="mb-4 flex items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $row['item_name'] }}</h3>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Item details for this record.</p>
                                                        </div>
                                                        <button type="button" @click="detailsModalOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close details">
                                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </div>

                                                    <div class="space-y-5 text-sm text-gray-700 dark:text-gray-200">
                                                        <div class="rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/70">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div>
                                                                    <p class="text-xs text-start font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</p>
                                                                    <h4 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $row['item_name'] }}</h4>
                                                                </div>
                                                                <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                                    {{ ucfirst($row['status']) }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="grid gap-4 sm:grid-cols-2">
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $row['type'] }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quantity</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $row['quantity'] }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Requested By</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional($requestRecord->user)->full_name ?? 'Unknown user' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Target User</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional($requestRecord->targetUser)->full_name ?? 'Not assigned' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Requested On</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $requestRecord->requested_at ? $requestRecord->requested_at->format('Y-m-d') : 'N/A' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Responded On</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ $requestRecord->responded_at ? $requestRecord->responded_at->format('Y-m-d') : 'Pending' }}</p>
                                                            </div>
                                                        </div>

                                                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                            <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</h3>
                                                            <p class="mt-2 leading-6 text-gray-700 dark:text-gray-200">{{ $requestRecord->notes ?? 'No notes provided.' }}</p>
                                                        </div>

                                                        <div class="flex justify-end">
                                                            <button type="button" @click="detailsModalOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                Close
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Transfer Modal -->
                                            <div x-show="transferModalOpen" x-cloak x-transition @keydown.escape.window="transferModalOpen = false" class="fixed inset-0 z-[1300] flex items-start justify-center overflow-y-auto px-4 pt-30" role="dialog" aria-modal="true">
                                                <div class="w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                    <form action="{{ route('endUser.assigned-items.transfer') }}" method="POST" class="space-y-4">
                                                        @csrf
                                                        <input type="hidden" name="request_id" value="{{ $row['request']->id ?? '' }}">
                                                        <input type="hidden" name="item_id" value="{{ $row['request']->item_id ?? '' }}">

                                                        <div class="mb-4 flex items-start justify-between gap-3">
                                                            <div>
                                                                <h3 class="text-md text-start font-semibold text-gray-900 dark:text-white">Transfer Item</h3>
                                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transfer {{ $row['item_name'] }} to another end user.</p>
                                                            </div>
                                                            <button type="button" @click="transferModalOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close transfer modal">
                                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </div>

                                                        <div class="rounded-md text-start border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/70">
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</p>
                                                            <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">{{ $row['item_name'] }}</p>
                                                        </div>

                                                        <div>
                                                            <label for="transfer_user_{{ $row['request']->id ?? $loop->index }}" class="text-start mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Transfer To</label>
                                                            <select id="transfer_user_{{ $row['request']->id ?? $loop->index }}" name="transfer_user_id" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                                                                <option value="">Select an end user</option>
                                                                @foreach ($endUsers as $endUser)
                                                                    <option value="{{ $endUser->id }}">{{ $endUser->full_name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label for="transfer_notes_{{ $row['request']->id ?? $loop->index }}" class="text-start mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Notes</label>
                                                            <textarea id="transfer_notes_{{ $row['request']->id ?? $loop->index }}" name="notes" rows="4" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="Reason for transfer"></textarea>
                                                        </div>

                                                        <div class="mt-6 flex justify-end gap-2">
                                                            <button type="button" @click="transferModalOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                Cancel
                                                            </button>
                                                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                                                                Request Transfer
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="text-center">
                                    <td colspan="6" class="px-4 py-6 text-center">No assigned or requested items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-cards.base-card>
        </div>
    </div>

@endsection