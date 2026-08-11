@props(['action'])

<form action="{{ $action }}" method="POST" class="space-y-5">
    @csrf

    <div class="grid gap-5 md:grid-cols-3">
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
            <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>

        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
            <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
            <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div>
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
            <input type="text" name="username" value="{{ old('username', auth()->user()->username) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
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
