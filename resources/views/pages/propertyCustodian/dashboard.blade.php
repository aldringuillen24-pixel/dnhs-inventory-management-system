@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Property Custodian Dashboard" />

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="col-span-12 md:col-span-3 xl:col-span-3">
            <x-cards.metric-card
                label="Total Assigned Assets"
                value="1,248"
                subtitle="Assets currently under your care"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M4 3a1 1 0 00-1 1v2a1 1 0 001 1h1v8a1 1 0 001 1h8a1 1 0 001-1V7h1a1 1 0 001-1V4a1 1 0 00-1-1H4z'/><path d='M5 7V5h10v2H5z'/></svg>"
            />
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Pending Inspections"
                value="14"
                subtitle="Inspections needing attention"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M10 18a8 8 0 100-16 8 8 0 000 16zm1-11V5a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0V9h2a1 1 0 100-2h-2z' clip-rule='evenodd'/></svg>"
                tone="positive"
            >
                4 new inspections were added this week.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Maintenance Due"
                value="7"
                subtitle="Items scheduled for maintenance"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M11 2a1 1 0 00-1 1v1H8a1 1 0 000 2h2v1a1 1 0 102 0V6h2a1 1 0 100-2h-2V3a1 1 0 00-1-1z'/><path d='M4 11a6 6 0 1110.99 1.712l.676.676a1 1 0 01-1.414 1.414l-.677-.676A6 6 0 014 11z'/></svg>"
                tone="neutral"
            >
                Schedule maintenance before the end of the month.
            </x-cards.metric-card>
        </div>
        <div class="col-span-12 md:col-span-6 xl:col-span-3">
            <x-cards.metric-card
                label="Pending Transfer Requests"
                value="5"
                subtitle="Awaiting approval"
                icon="<svg xmlns='http://www.w3.org/2000/svg' class='h-5 w-5' viewBox='0 0 20 20' fill='currentColor'><path d='M3 3.5A1.5 1.5 0 014.5 2h11A1.5 1.5 0 0117 3.5v13a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13zM5 4v3h10V4H5zm10 5H5v7.5a.5.5 0 00.5.5h9a.5.5 0 00.5-.5V9z'/></svg>"
                tone="negative"
            >
                Approve or decline requests to keep asset records up to date.
            </x-cards.metric-card>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-12 mt-6">
        <div class="col-span-12 xl:col-span-5">
            <x-cards.base-card title="Assets by Category" subtitle="Current distribution for assigned assets">
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <div id="chartOne" class="min-w-[1000px]"></div>
                </div>
            </x-cards.base-card>
        </div>

        <div class="col-span-6 xl:col-span-7">
            <x-cards.base-card title="Request Status" subtitle="Open versus closed requests">
                <div class="custom-scrollbar max-w-full overflow-x-auto">
                    <div id="chartEight" class="min-w-[600px] h-[260px]"></div>
                </div>
            </x-cards.base-card>
        </div>
    </div>

@endsection