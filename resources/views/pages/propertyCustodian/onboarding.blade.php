@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Complete your account setup</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Please fill in your details and update your password before you can access the dashboard.</p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('propertyCustodian.onboarding.post') }}" method="POST" class="space-y-5">
                @csrf

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                        <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                        <input type="password" name="password" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
                        <input type="password" name="password_confirmation" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    <p><span class="font-semibold">Username:</span> {{ auth()->user()->username }}</p>
                    <p class="mt-2"><span class="font-semibold">Temporary password:</span> {{ auth()->user()->temporary_password }}</p>
                </div>

                <div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
                        Complete Setup
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
