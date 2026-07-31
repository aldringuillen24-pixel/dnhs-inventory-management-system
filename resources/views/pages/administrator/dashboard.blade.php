@extends('layouts.app')

@section('content')
  <div class="grid grid-cols-12 gap-4 md:gap-6">
    <div class="col-span-12 space-y-6 xl:col-span-3">
      <x-cards.metric-card
        title="Inventory Overview"
        value="No Value Available"
        subtitle="Items currently tracked"
      />
    </div>
    <div class="col-span-12 xl:col-span-3">
      <x-cards.metric-card
        title="Recent Transactions"
        value="No Value Available"
        subtitle="Transactions today"
      />
    </div>

    <div class="col-span-3">
      <x-cards.metric-card
        title="Total User Accounts"
        value="No Value Available"
        subtitle="Active accounts in the system"
      />
    </div>

    <div class="col-span-12 xl:col-span-5">

    </div>

    <div class="col-span-12 xl:col-span-7">

    </div>
  </div>
@endsection
