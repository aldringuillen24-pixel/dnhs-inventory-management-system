@extends('layouts.fullscreen-layout')

@section('content')
	<main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-white sm:px-8">
		<div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-5xl items-center justify-center">
			<section class="w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-7 shadow-[0_24px_80px_rgba(15,23,42,0.10)] dark:border-slate-800 dark:bg-slate-900 sm:p-12 lg:p-16">
					<a href="{{ route('signin') }}" class="mb-12 inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-[#173f3b] dark:text-slate-400 dark:hover:text-[#e7b86a]">
						<i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
						Back to sign in
					</a>

					<div class="mb-8 flex items-center gap-3" aria-label="Password recovery progress">
						<span class="h-1.5 w-12 rounded-full bg-[#173f3b] dark:bg-[#e7b86a]"></span>
						<span class="h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-700"></span>
						<span class="h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-700"></span>
						<span class="ml-1 text-xs font-medium text-slate-400">01 / 03</span>
					</div>

					<div class="max-w-md">
						<h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">Forgot your password?</h1>
						<p class="mt-4 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
							Enter the email connected to your account. We will send a six-digit code to continue.
						</p>

						@if ($errors->any())
							<div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300" role="alert">
								{{ $errors->first() }}
							</div>
						@endif

						<form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-6">
							@csrf
							<div>
								<label for="email" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Email address</label>
								<div class="relative">
									<i data-lucide="mail" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-slate-500"></i>
									<input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@dianayhs.edu.ph" autocomplete="email" required autofocus
										class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pl-11 pr-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
								</div>
							</div>

							<x-common.button-spinner
								text="Send recovery code"
								loadingText="Sending code..."
								type="submit"
								class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 dark:bg-[#e7b86a] dark:text-[#173f3b] dark:hover:bg-[#f0c980] dark:focus:ring-[#e7b86a]/20"
							/>
						</form>

						<p class="mt-8 text-xs leading-5 text-slate-400 dark:text-slate-500">
							The code expires after a short time. If you do not see it, check your spam folder or contact the system administrator.
						</p>
					</div>
				</div>
			</section>
		</div>
	</main>
@endsection