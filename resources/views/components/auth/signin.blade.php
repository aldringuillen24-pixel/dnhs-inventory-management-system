@extends('layouts.fullscreen-layout')

@section('content')
    <main class="min-h-screen bg-[#f6f7f2] px-5 py-8 text-slate-900 dark:bg-slate-950 dark:text-white sm:px-8">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] w-full max-w-4xl items-center justify-center">
            <section class="w-full max-w-xl overflow-hidden rounded-md border border-slate-200 bg-white p-4 shadow-[0_24px_80px_rgba(15,23,42,0.10)] dark:border-slate-800 dark:bg-slate-900 sm:p-12 lg:p-16">
                    <div class="mx-auto flex w-full max-w-md flex-col justify-center">
                        <div class="mb-9">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.1 rem] text-slate-400">Welcome back</p>
                            <h2 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white sm:text-4xl">Sign in to continue.</h2>
                            <p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">Use your assigned username to open your inventory workspace.</p>
                        </div>

                        <form method="POST" action="{{ route('signin.post') }}" class="space-y-5">
                            @csrf
                            <div>
                                <label for="username" class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-300">Username</label>
                                <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="Enter your username" autocomplete="username" required autofocus
                                    class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
                            </div>

                            <div x-data="{ showPassword: false }">
                                <div class="mb-2 flex items-center justify-between">
                                    <label for="password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Password</label>
                                    <a href="/forgot-password" class="text-xs font-semibold text-[#b17b28] transition hover:text-[#173f3b] dark:text-[#e7b86a] dark:hover:text-white">Forgot password?</a>
                                </div>
                                <div class="relative">
                                    <input id="password" name="password" :type="showPassword ? 'text' : 'password'" placeholder="Enter your password" autocomplete="current-password" required
                                        class="h-12 w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 pr-12 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#173f3b] focus:ring-4 focus:ring-[#173f3b]/10 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-[#e7b86a] dark:focus:ring-[#e7b86a]/10" />
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 transition hover:text-[#173f3b] dark:hover:text-[#e7b86a]" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                        <span x-text="showPassword ? 'Hide' : 'Show'" class="text-xs font-semibold"></span>
                                    </button>
                                </div>
                            </div>

                            <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-[#173f3b] focus:ring-[#173f3b] dark:border-slate-700 dark:bg-slate-950 dark:text-[#e7b86a]" />
                                Keep me logged in
                            </label>

                            <x-common.button-spinner
                                text="Sign in"
                                loadingText="Signing in..."
                                type="submit"
                                class="h-12 w-full rounded-xl bg-[#173f3b] px-5 text-sm font-semibold text-white hover:bg-[#0f302d] focus:ring-4 focus:ring-[#173f3b]/20 dark:bg-[#e7b86a] dark:text-[#173f3b] dark:hover:bg-[#f0c980] dark:focus:ring-[#e7b86a]/20"
                            />
                        </form>
                    </div>

                    <p class="mt-8 text-center text-xs leading-5 text-slate-400 dark:text-slate-500">Need access help? Contact your system administrator.</p>
            </section>
        </div>
    </main>
@endsection
