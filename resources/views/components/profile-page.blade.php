@props([
    'user' => auth()->user(),
    'roleLabel' => null,
    'formAction' => null,
    'formMethod' => 'PATCH',
    'accentClasses' => [
        'label' => 'text-teal-200',
        'icon' => 'text-teal-700 dark:text-teal-300',
        'iconBackground' => 'bg-teal-50 dark:bg-teal-500/10',
        'focus' => 'focus:border-teal-500 focus:ring-teal-500/20',
        'button' => 'bg-teal-600 text-white hover:bg-teal-700',
    ],
])

@php
    $roleLabel ??= $user?->role?->role_name ?? 'Property Custodian';
    $formAction ??= route('propertyCustodian.profile.update');
    $httpMethod = strtoupper($formMethod);
    $initials = strtoupper(substr($user?->first_name ?? 'U', 0, 1)) . strtoupper(substr($user?->last_name ?? '', 0, 1));
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

<div {{ $attributes->merge(['class' => 'mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    <div class="relative bg-slate-900 px-5 py-6 sm:px-7">
        <div class="absolute inset-0 opacity-20" style="background-image: linear-gradient(135deg, #0f766e 0, transparent 42%), linear-gradient(315deg, #2563eb 0, transparent 45%);"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 border-white/20 bg-white text-2xl font-bold text-slate-900">
                    {{ $initials }}
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] {{ $accentClasses['label'] }}">{{ $roleLabel }} account</p>
                    <h1 class="mt-1 text-xl font-semibold text-white">{{ $user?->full_name ?? $roleLabel }}</h1>
                    <p class="mt-1 text-sm text-slate-300">{{ $user?->email ?? 'No email set' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-5 p-5 sm:p-6 xl:grid-cols-[220px_minmax(0,1fr)]">
        <aside class="space-y-2">
            @if (blank($sidebar ?? null))
                <div class="border-b border-gray-200 pb-3 dark:border-gray-800">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Account summary</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Your identity and access details.</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3"><i data-lucide="badge-check" class="h-5 w-5 text-teal-600 dark:text-teal-400"></i><div><p class="text-xs text-gray-500 dark:text-gray-400">Role</p><p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $roleLabel }}</p></div></div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center gap-3"><i data-lucide="calendar-clock" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i><div><p class="text-xs text-gray-500 dark:text-gray-400">Last updated</p><p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ $user?->updated_at?->format('M d, Y') ?? 'N/A' }}</p></div></div>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <div class="flex items-start gap-3"><i data-lucide="shield-check" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"></i><div><p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Keep access private</p><p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-300">Never share your password or inventory access with another user.</p></div></div>
                </div>
            @else
                {{ $sidebar }}
            @endif
        </aside>

        <form method="POST" action="{{ $formAction }}" class="min-w-0 space-y-6">
            @csrf
            @if (!in_array($httpMethod, ['GET', 'POST']))
                @method($httpMethod)
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    <p class="font-semibold">Please review the highlighted fields.</p>
                    <ul class="mt-1 list-disc pl-5"><li>{{ $errors->first() }}</li></ul>
                </div>
            @endif
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300" role="status">{{ session('success') }}</div>
            @endif

            <section>
                <div class="flex items-center gap-2.5 border-b border-gray-200 pb-2.5 dark:border-gray-800"><div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $accentClasses['iconBackground'] }} {{ $accentClasses['icon'] }}"><i data-lucide="user-round" class="h-4 w-4"></i></div><div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Personal details</h2><p class="text-sm text-gray-500 dark:text-gray-400">Use the details other staff will recognize.</p></div></div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div><label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">First name</label><input id="first_name" name="first_name" type="text" value="{{ old('first_name', $user?->first_name) }}" required class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 {{ $accentClasses['focus'] }} focus:outline-none focus:ring-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('first_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Last name</label><input id="last_name" name="last_name" type="text" value="{{ old('last_name', $user?->last_name) }}" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 {{ $accentClasses['focus'] }} focus:outline-none focus:ring-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('last_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="username" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label><input id="username" name="username" type="text" value="{{ old('username', $user?->username) }}" required autocomplete="username" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 {{ $accentClasses['focus'] }} focus:outline-none focus:ring-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('username')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" required autocomplete="email" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 {{ $accentClasses['focus'] }} focus:outline-none focus:ring-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                </div>
            </section>

            <section>
                <div class="flex items-center gap-2.5 border-b border-gray-200 pb-2.5 dark:border-gray-800"><div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"><i data-lucide="key-round" class="h-4 w-4"></i></div><div><h2 class="text-base font-semibold text-gray-900 dark:text-white">Security</h2><p class="text-sm text-gray-500 dark:text-gray-400">Leave these fields blank to keep your current password.</p></div></div>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div><label for="current_password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" />@error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" class="w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" /></div>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"><p class="text-xs text-gray-500 dark:text-gray-400"><i data-lucide="lock-keyhole" class="mr-1 inline h-3.5 w-3.5"></i>Password changes require your current password.</p><x-common.button-spinner text="Save profile" loadingText="Saving..." class="{{ $accentClasses['button'] }}" /></div>
        </form>
    </div>
</div>