@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Complete your account setup</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Please fill in your details and update your password before you can access the dashboard.</p>
            </div>

            <x-onboarding-form action="{{ route('propertyCustodian.onboarding.post') }}" />
        </div>
    </div>
@endsection
