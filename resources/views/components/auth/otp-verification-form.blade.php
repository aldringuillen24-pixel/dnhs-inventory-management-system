@extends('layouts.fullscreen-layout')

@section('content')
	<main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-white sm:px-8">
		<div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-5xl items-center justify-center">
			<section class="w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-7 shadow-[0_24px_80px_rgba(15,23,42,0.10)] dark:border-slate-800 dark:bg-slate-900 sm:p-12 lg:p-16">
				<a href="{{ route('password.forgot') }}" class="mb-12 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-[#173f3b] dark:text-slate-400 dark:hover:text-[#e7b86a]">
					<span aria-hidden="true">&larr;</span>
					Change email
				</a>

				<div class="mb-8 flex items-center gap-3" aria-label="Password recovery progress">
					<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
					<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
					<span class="h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-700"></span>
					<span class="ml-1 text-xs font-medium text-slate-400">02 / 03</span>
				</div>

				<div class="max-w-md">
					<p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-[#b17b28] dark:text-[#e7b86a]">Check your inbox</p>
					<h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">Enter your code.</h1>
					<p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">
						Your code expires in
						<span id="otp-countdown" class="font-semibold text-[#173f3b] dark:text-[#e7b86a]" data-expires-at="{{ $expiresAt?->toISOString() ?? '' }}">05:00</span>
					</p>

					@if ($errors->any())
						<div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300" role="alert">
							{{ $errors->first() }}
						</div>
					@endif

					<form method="POST" action="{{ route('password.otp.verify') }}" class="mt-8 space-y-6">
						@csrf
						<input type="hidden" name="email" value="{{ $email }}" />
						<div>
							<label for="otp" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">One-time code</label>
							<input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus
								class="h-14 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 text-center text-2xl font-semibold tracking-[0.45em] text-slate-900 outline-none transition placeholder:text-slate-300 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-700 dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
						</div>

						<x-common.button-spinner
							text="Verify code"
							loadingText="Verifying..."
							type="submit"
							class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 dark:bg-[#e7b86a] dark:text-[#173f3b] dark:hover:bg-[#f0c980] dark:focus:ring-[#e7b86a]/20"
						/>
					</form>

					<p class="mt-8 text-xs leading-5 text-slate-400 dark:text-slate-500">Did not receive it? Wait a moment, then request a new code from the previous step.</p>
				</div>
			</section>
		</div>
	</main>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const countdown = document.getElementById('otp-countdown');
			const expiresAt = countdown?.dataset.expiresAt;

			if (!countdown || !expiresAt) {
				return;
			}

			const updateCountdown = function () {
				const remainingMs = new Date(expiresAt).getTime() - Date.now();
				const totalSeconds = Math.max(0, Math.ceil(remainingMs / 1000));

				if (totalSeconds <= 0) {
					countdown.textContent = '00:00';
					return;
				}

				const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
				const seconds = String(totalSeconds % 60).padStart(2, '0');
				countdown.textContent = minutes + ':' + seconds;
				setTimeout(updateCountdown, 1000);
			};

			updateCountdown();
		});
	</script>@endsection