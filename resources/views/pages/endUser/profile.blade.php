@extends('layouts.app')

@section('content')
	<x-common.page-breadcrumb pageTitle="Profile" />

	<div class="grid gap-6 xl:grid-cols-12 mt-6">
		<div class="col-span-12 xl:col-span-5">
			<x-cards.base-card title="Profile Overview" subtitle="Your current account details">
				<div class="space-y-4">
					<div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
						<p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</p>
						<p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ auth()->user()?->first_name }} {{ auth()->user()?->last_name }}</p>
					</div>

					<div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
						<p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Username</p>
						<p class="mt-2 text-base font-medium text-gray-900 dark:text-white">{{ auth()->user()?->username ?? 'Not set' }}</p>
					</div>

					<div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
						<p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</p>
						<p class="mt-2 text-base font-medium text-gray-900 dark:text-white">{{ auth()->user()?->email ?? 'Not provided' }}</p>
					</div>
				</div>
			</x-cards.base-card>
		</div>

		<div class="col-span-12 xl:col-span-7">
			<x-cards.base-card title="Edit Profile" subtitle="Update your email, username, or password">
				<form method="POST" action="{{ route('endUser.profile.update') }}" class="space-y-6">
					@csrf
					@method('PATCH')

					<div>
						<label for="username" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
						<input id="username" name="username" type="text" value="{{ old('username', auth()->user()?->username) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
						@error('username')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
					</div>

					<div>
						<label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
						<input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" required />
						@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
					</div>

					<div>
						<label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
						<input id="password" name="password" type="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Leave blank to keep current password" />
						@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
					</div>

					<div>
						<label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
						<input id="password_confirmation" name="password_confirmation" type="password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" placeholder="Repeat new password" />
					</div>

					<div class="grid gap-4 sm:grid-cols-2">
						<div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
							<p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Role</p>
							<p class="mt-2 text-base font-medium text-gray-900 dark:text-white">{{ auth()->user()?->role?->role_name ?? 'End User' }}</p>
						</div>

						<div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
							<p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</p>
							<p class="mt-2 text-base font-medium text-gray-900 dark:text-white">{{ ucfirst(auth()->user()?->status ?? 'active') }}</p>
						</div>
					</div>

					<div class="mt-6 rounded-xl border border-dashed border-gray-200 bg-white p-5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
						<p class="font-semibold text-gray-900 dark:text-white">Keep your profile secure</p>
						<p class="mt-2">Leave the password fields blank if you do not want to change your current password.</p>
					</div>

					<div class="flex justify-end gap-3 pt-2">
						<button type="submit" class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600">Save Changes</button>
					</div>
				</form>
			</x-cards.base-card>
		</div>
	</div>
@endsection
