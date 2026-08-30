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
                                        @php($requestItemId = $row['item_id'] ?? optional($requestRecord)->item_id)
                                        @php($canTransfer = !empty($requestRecord) && in_array(strtolower((string) $row['status']), ['approved', 'accepted']))
                                        @php($isPendingReturn = str_contains(strtolower((string) $row['status']), 'waiting for return approval'))

                                        <div x-data="{ actionOpen: false, detailsOpen: false, transferOpen: false, menuStyle: '', toggleMenu(event) { if (!this.actionOpen) { const rect = event.currentTarget.getBoundingClientRect(); this.menuStyle = `top: ${rect.bottom + 4}px; left: ${rect.right - 144}px;`; } this.actionOpen = !this.actionOpen; } }" class="relative inline-block">
                                            <button type="button" @click="toggleMenu($event)" class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Actions for {{ $row['item_name'] }}">
                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M10 6a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm0 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                                                </svg>
                                            </button>

                                            <div x-show="actionOpen" x-cloak x-transition @click.outside="actionOpen = false" @keydown.escape.window="actionOpen = false" :style="menuStyle" class="fixed z-[1200] w-40 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                                <button type="button" @click="actionOpen = false; detailsOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                    Details
                                                </button>

                                                @if($canTransfer)
                                                    <button type="button" @click="actionOpen = false; transferOpen = true" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                        Transfer
                                                    </button>
                                                @endif
                                                @php($isAssigned = in_array(strtolower((string) $row['status']), ['approved', 'accepted', 'waiting for return approval']))
                                                @if($isAssigned && !$isPendingReturn && !empty($requestItemId))
                                                    <form method="POST" action="{{ route('endUser.inventory.request-return', $requestItemId) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                                                            Request Return
                                                        </button>
                                                    </form>
                                                @elseif($isPendingReturn && !empty($requestItemId))
                                                    <form method="POST" action="{{ route('endUser.inventory.cancel-return', $requestItemId) }}" class="block">
                                                        @csrf
                                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-700 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20">
                                                            Cancel Return Request
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>

                                            <!-- Details Modal -->
                                            <div x-show="detailsOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[1100] flex items-center justify-center bg-gray-900/60 px-4 shadow-lg backdrop-blur-sm dark:bg-gray-950/70" role="dialog" aria-modal="true" @click.outside="detailsOpen = false" @keydown.escape.window="detailsOpen = false">
                                                <div class="mt-8 w-full max-w-xl rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                    <div class="mb-4 flex items-start justify-between gap-3">
                                                        <div>
                                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $row['item_name'] }}</h3>
                                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Item details for this record.</p>
                                                        </div>
                                                        <button type="button" @click="detailsOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
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
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional(optional($requestRecord)->user)->full_name ?? 'Unknown user' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Target User</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional(optional($requestRecord)->targetUser)->full_name ?? 'Not assigned' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Requested On</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional($requestRecord)->requested_at ? optional($requestRecord)->requested_at->format('Y-m-d') : 'N/A' }}</p>
                                                            </div>
                                                            <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                                <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Responded On</h3>
                                                                <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ optional($requestRecord)->responded_at ? optional($requestRecord)->responded_at->format('Y-m-d') : 'Pending' }}</p>
                                                            </div>
                                                        </div>

                                                        <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                                            <h3 class="text-[8px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes</h3>
                                                            <p class="mt-2 leading-6 text-gray-700 dark:text-gray-200">{{ optional($requestRecord)->notes ?? 'No notes provided.' }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Transfer Modal -->
                                            @if($canTransfer)
                                                <div x-show="transferOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[1100] flex items-center justify-center bg-gray-900/60 px-4 shadow-lg backdrop-blur-sm dark:bg-gray-950/70" role="dialog" aria-modal="true" @click.outside="transferOpen = false" @keydown.escape.window="transferOpen = false">
                                                    <div class="mt-8 w-full max-w-lg rounded-md border border-gray-200 bg-white p-6 shadow-md dark:border-gray-700 dark:bg-gray-900">
                                                        <div class="mb-4 flex items-start justify-between gap-3">
                                                            <div>
                                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-start mb-2">Transfer Item</h3>
                                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transfer {{ $row['item_name'] }} to another end user.</p>
                                                            </div>
                                                            <button type="button" @click="transferOpen = false" class="rounded-md p-1 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                                                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <form action="{{ route('endUser.assigned-items.transfer') }}" method="POST" class="space-y-4" x-data="{ selectedRecipient: '', transferQuantity: 1, maxQuantity: {{ $row['quantity'] }} }" @submit="if (!selectedRecipient) { event.preventDefault(); alert('Please select a recipient before transferring.'); } else if (!transferQuantity || transferQuantity < 1 || transferQuantity > maxQuantity) { event.preventDefault(); alert('Please enter a valid quantity between 1 and ' + maxQuantity + '.'); }">
                                                            @csrf
                                                            <input type="hidden" name="request_id" value="{{ optional($requestRecord)->id ?? '' }}">
                                                            <input type="hidden" name="item_id" value="{{ optional($requestRecord)->item_id ?? $requestItemId ?? '' }}">

                                                            <div class="grid gap-3 sm:grid-cols-2">
                                                                <div class="rounded-md text-start border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/70">
                                                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</p>
                                                                    <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white">{{ $row['item_name'] }}</p>
                                                                </div>
                                                                <div>
                                                                    <label for="transfer_qty_{{ optional($requestRecord)->id ?? $loop->index }}" class="text-start mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                                                        Quantity <span class="text-red-500">*</span>
                                                                    </label>
                                                                    <div class="flex items-center gap-2">
                                                                        <input 
                                                                            type="number" 
                                                                            id="transfer_qty_{{ optional($requestRecord)->id ?? $loop->index }}" 
                                                                            name="quantity" 
                                                                            x-model.number="transferQuantity"
                                                                            min="1" 
                                                                            :max="maxQuantity"
                                                                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" 
                                                                            required>
                                                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap">of {{ $row['quantity'] }}</span>
                                                                    </div>
                                                                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400">Enter how many units to transfer (1–{{ $row['quantity'] }})</p>
                                                                </div>
                                                            </div>

                                                            <div>
                                                                <label for="transfer_user_{{ optional($requestRecord)->id ?? $loop->index }}" class="text-start mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                                                                    Transfer To <span class="text-red-500">*</span>
                                                                </label>
                                                                <select 
                                                                    id="transfer_user_{{ optional($requestRecord)->id ?? $loop->index }}" 
                                                                    name="transfer_user_id" 
                                                                    x-model="selectedRecipient"
                                                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" 
                                                                    required>
                                                                    <option value="" disabled selected>Select a recipient</option>
                                                                    @if ($endUsers->isEmpty())
                                                                        <option value="" disabled>No other end users available</option>
                                                                    @endif
                                                                    @foreach ($endUsers as $endUser)
                                                                        <option value="{{ $endUser->id }}">{{ $endUser->full_name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <div>
                                                                <label for="transfer_notes_{{ $row['request']->id ?? $loop->index }}" class="text-start mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Notes <span class="text-gray-400">(Optional)</span></label>
                                                                <textarea 
                                                                    id="transfer_notes_{{ $row['request']->id ?? $loop->index }}" 
                                                                    name="notes" 
                                                                    rows="3" 
                                                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" 
                                                                    placeholder="e.g., Transferred due to project reassignment"></textarea>
                                                            </div>

                                                            <div class="mt-6 flex justify-end gap-2">
                                                                <button type="button" @click="transferOpen = false" class="rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit" :class="{'opacity-50 cursor-not-allowed': !selectedRecipient, 'hover:bg-blue-700': selectedRecipient}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition disabled:hover:bg-blue-600">
                                                                    Request Transfer
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
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