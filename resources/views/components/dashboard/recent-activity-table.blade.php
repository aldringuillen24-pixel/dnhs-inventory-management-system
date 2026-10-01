@props([
    'title' => 'Recent Activity',
    'subtitle' => null,
    'activities' => [],
    'columns' => [
        ['key' => 'date', 'label' => 'Date & Time'],
        ['key' => 'type', 'label' => 'Type'],
        ['key' => 'item', 'label' => 'Item'],
        ['key' => 'quantity', 'label' => 'Quantity', 'align' => 'right'],
        ['key' => 'user', 'label' => 'User'],
        ['key' => 'remarks', 'label' => 'Remarks'],
    ],
    'emptyMessage' => 'No recent activity.',
    'dateFormat' => 'M d, Y h:i A',
])

@php
    $activities = collect($activities);
@endphp

<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div>
            @if ($title)
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            @endif
            @if ($subtitle)
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>
        @if (isset($actions))
            {{ $actions }}
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-xs">
            <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                <tr>
                    @foreach ($columns as $column)
                        <th class="px-4 py-2.5 font-semibold {{ data_get($column, 'align') === 'right' ? 'text-right' : '' }}">{{ data_get($column, 'label') }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($activities as $activity)
                    <tr class="whitespace-nowrap text-gray-600 dark:text-gray-300">
                        @foreach ($columns as $column)
                            @php
                                $value = data_get($activity, data_get($column, 'key'));
                                if (data_get($column, 'key') === 'date' && $value instanceof \DateTimeInterface) {
                                    $value = $value->format($dateFormat);
                                }
                            @endphp
                            <td class="px-4 py-3 {{ data_get($column, 'align') === 'right' ? 'text-right' : '' }}">{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
