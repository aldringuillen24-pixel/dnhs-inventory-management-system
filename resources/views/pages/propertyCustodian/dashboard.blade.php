@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Property Custodian Dashboard" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-cards.metric-card title="Inventory Units" value="{{ number_format($metrics['inventory']) }}" subtitle="Tracked, non-disposed units" />
        <x-cards.metric-card title="Available Units" value="{{ number_format($metrics['available']) }}" subtitle="Ready for assignment" />
        <x-cards.metric-card title="Low-Stock Items" value="{{ number_format($metrics['lowStock']) }}" subtitle="Three units or fewer" />
        <x-cards.metric-card title="Pending Requests" value="{{ number_format($metrics['pendingRequests']) }}" subtitle="Awaiting review" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-cards.base-card title="Inventory by Category" subtitle="Available and assigned units by category">
            @if ($categoryData->isEmpty())
                <div class="flex min-h-[280px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">No inventory data available.</div>
            @else
                <div id="custodianCategoryChart" class="min-h-[280px]" data-labels='@json($categoryData->pluck("label")->values())' data-values='@json($categoryData->pluck("value")->values())'></div>
            @endif
        </x-cards.base-card>

        <x-cards.base-card title="Request Status" subtitle="Current request workflow">
            @if ($requestStatusData->isEmpty())
                <div class="flex min-h-[280px] items-center justify-center text-sm text-gray-500 dark:text-gray-400">No request data available.</div>
            @else
                <div id="custodianRequestChart" class="min-h-[280px]" data-labels='@json($requestStatusData->pluck("label")->values())' data-values='@json($requestStatusData->pluck("value")->values())'></div>
            @endif
        </x-cards.base-card>
    </div>
@endsection
