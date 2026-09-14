@extends('layouts.fullscreen-layout')

@section('content')
	<main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-white sm:px-8">
		<div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-5xl items-center justify-center">
			<section class="w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-7 shadow-[0_24px_80px_rgba(15,23,42,0.10)] dark:border-slate-800 dark:bg-slate-900 sm:p-12 lg:p-16">
				<a href="{{ route('password.otp', ['email' => $email]) }}" class="mb-12 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-[#173f3b] dark:text-slate-400 dark:hover:text-[#e7b86a]">
					<span aria-hidden="true">&larr;</span>
					Back to code
				</a>

				<div class="mb-8 flex items-center gap-3" aria-label="Password recovery progress">
					<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
					<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
					<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
					<span class="ml-1 text-xs font-medium text-slate-400">03 / 03</span>
				</div>

				<div class="max-w-md">
					<p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-[#b17b28] dark:text-[#e7b86a]">New credentials</p>
					<h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">Set a new password.</h1>
					<p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">Choose a password you have not used before to secure your account.</p>

					@if ($errors->any())
						<div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300" role="alert">
							{{ $errors->first() }}
						</div>
					@endif

					<form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
						@csrf
						<div>
							<label for="password" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">New password</label>
							<input id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required
								class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
						</div>
						<div>
							<label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Confirm new password</label>
							<input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" placeholder="Repeat your new password" required
								class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
						</div>

						<x-common.button-spinner
							text="Update password"
							loadingText="Updating password..."
							type="submit"
							class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 dark:bg-[#e7b86a] dark:text-[#173f3b] dark:hover:bg-[#f0c980] dark:focus:ring-[#e7b86a]/20"
						/>
					</form>
				</div>
			</section>
		</div>
	</main>
@endsection