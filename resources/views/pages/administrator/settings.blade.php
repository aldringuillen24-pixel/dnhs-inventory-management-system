@extends('layouts.app', ['title' => 'System Settings'])

@section('content')
    <div x-data="{ activeTab: '{{ request('tab', 'inventory') }}' }">

        {{-- Breadcrumb & Title --}}
        <div class="mb-6 flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
            <div>
                <x-common.page-breadcrumb pageTitle="System Settings" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Configure inventory lifecycle rules, security thresholds, AI assistant settings, and system diagnostics.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                    <span x-text="{
                        inventory: 'Inventory Policies',
                        security: 'Security Defaults',
                        ai: 'AI Configuration',
                        diagnostics: 'System Diagnostics'
                    }[activeTab] ?? 'System Settings'"></span>
                </span>
            </div>
        </div>

        {{-- Tab Navigation Bar --}}
        <div class="mb-6 flex flex-wrap items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1.5 dark:border-gray-700 dark:bg-gray-800/60">
            {{-- Config Tabs --}}
            <button
                type="button"
                @click="activeTab = 'inventory'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-150"
                :class="activeTab === 'inventory'
                    ? 'bg-white text-brand-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-700 dark:text-brand-300 dark:ring-gray-600'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                <i data-lucide="package" class="h-4 w-4"></i>
                <span>Inventory Policies</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'security'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-150"
                :class="activeTab === 'security'
                    ? 'bg-white text-brand-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-700 dark:text-brand-300 dark:ring-gray-600'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
                <span>Security Defaults</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'ai'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-150"
                :class="activeTab === 'ai'
                    ? 'bg-white text-brand-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-700 dark:text-brand-300 dark:ring-gray-600'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                <i data-lucide="bot" class="h-4 w-4"></i>
                <span>AI Configuration</span>
            </button>

            {{-- Divider --}}
            <span class="mx-1 hidden h-5 w-px bg-gray-300 dark:bg-gray-600 sm:block"></span>

            {{-- Tools Tab --}}
            <button
                type="button"
                @click="activeTab = 'diagnostics'"
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-150"
                :class="activeTab === 'diagnostics'
                    ? 'bg-white text-brand-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-700 dark:text-brand-300 dark:ring-gray-600'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                <i data-lucide="wrench" class="h-4 w-4"></i>
                <span>Diagnostics</span>
            </button>
        </div>

        {{-- TAB 1: Inventory & Lifecycle Policies --}}
        <div x-show="activeTab === 'inventory'" x-cloak>
            <x-cards.base-card title="Inventory Thresholds & Lifecycle Rules" subtitle="Automated inventory warnings, equipment tracking policies, and return due intervals.">
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="section" value="inventory">

                    {{-- Section: Stock & Threshold Rules --}}
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Stock &amp; Threshold Rules</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            {{-- Low Stock Threshold — amber accent --}}
                            <div class="rounded-lg border border-l-4 border-amber-400 border-gray-200 bg-amber-50/40 p-4 dark:border-gray-700 dark:border-l-amber-500 dark:bg-amber-950/10">
                                <div class="mb-1 flex items-center gap-2">
                                    <i data-lucide="triangle-alert" class="h-4 w-4 text-amber-500"></i>
                                    <label class="text-sm font-semibold text-gray-900 dark:text-white">Low Stock Warning Threshold</label>
                                </div>
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Trigger low-stock indicators across custodian dashboards when units reach this minimum number.</p>
                                <div class="flex items-center gap-0">
                                    <input type="number" name="low_stock_threshold" min="1" max="1000"
                                        value="{{ old('low_stock_threshold', $inventoryPolicy['low_stock_threshold']) }}"
                                        class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        required />
                                    <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">units</span>
                                </div>
                            </div>

                            {{-- Default Asset Lifespan --}}
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Default Asset Lifespan</label>
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Default operational lifespan assigned to newly stocked items before depreciation inspection.</p>
                                <div class="flex items-center gap-0">
                                    <input type="number" name="default_lifespan_years" min="1" max="30"
                                        value="{{ old('default_lifespan_years', $inventoryPolicy['default_lifespan_years']) }}"
                                        class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        required />
                                    <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">years</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section: Loan & Return Cycle --}}
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Loan &amp; Return Cycle</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Return Due Period</label>
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Suggested maximum loan duration before items are flagged for scheduled return review.</p>
                                <div class="flex items-center gap-0">
                                    <input type="number" name="return_due_days" min="1" max="365"
                                        value="{{ old('return_due_days', $inventoryPolicy['return_due_days']) }}"
                                        class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        required />
                                    <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">days</span>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Custodian Auto-Reminder Alert</label>
                                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Days prior to return expiration when reminder notices become visible on dashboards.</p>
                                <div class="flex items-center gap-0">
                                    <input type="number" name="auto_reminder_days" min="1" max="30"
                                        value="{{ old('auto_reminder_days', $inventoryPolicy['auto_reminder_days']) }}"
                                        class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        required />
                                    <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">days in advance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Serial Number Enforcement Panel --}}
                    <div class="rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-800/40 dark:bg-rose-950/10">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="require_serial_number" value="1"
                                @checked(old('require_serial_number', $inventoryPolicy['require_serial_number']) === '1')
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="lock" class="h-4 w-4 text-rose-500"></i>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Strict Serial Number Enforcement</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Require individual serial numbers for electronics, IT devices, and high-value machinery categories during initial stock-in.</p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                        <x-common.button-spinner text="Save Inventory Policies" loadingText="Saving..." class="text-white" />
                    </div>
                </form>
            </x-cards.base-card>
        </div>

        {{-- TAB 2: Security & Account Defaults --}}
        <div x-show="activeTab === 'security'" x-cloak>
            <x-cards.base-card title="Account Security & Access Rules" subtitle="Session durations, one-time-password expiration, and automated audit retention.">
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6"
                    x-data="{
                        otpMin: {{ old('otp_expiry_minutes', $securityPolicy['otp_expiry_minutes']) }},
                        sessionMin: {{ old('session_timeout_minutes', $securityPolicy['session_timeout_minutes']) }},
                        get otpLabel() { return this.otpMin + ' min → ' + this.otpMin + ' minutes until expiry'; },
                        get sessionLabel() {
                            let h = Math.floor(this.sessionMin / 60);
                            let m = this.sessionMin % 60;
                            return h > 0 ? (h + 'h ' + (m > 0 ? m + 'min' : '')) : (this.sessionMin + ' min');
                        }
                    }">
                    @csrf
                    <input type="hidden" name="section" value="security">

                    {{-- Risk Warning Banner --}}
                    <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/40 dark:bg-amber-950/20">
                        <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500"></i>
                        <p class="text-sm text-amber-800 dark:text-amber-300">Changes to these settings affect <strong>all active user sessions</strong> and authentication flows across every role.</p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- Default Password Pattern --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Default Onboarding Password Pattern</label>
                            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Credential generation scheme for batch-created and manually registered user accounts.</p>
                            <select name="default_password_pattern"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                required>
                                <option value="temporary_random" @selected(old('default_password_pattern', $securityPolicy['default_password_pattern']) === 'temporary_random')>Random 10-Character Alphanumeric (Recommended)</option>
                                <option value="role_prefixed" @selected(old('default_password_pattern', $securityPolicy['default_password_pattern']) === 'role_prefixed')>Role Prefix + Random Digits (e.g. EndUser-4819)</option>
                                <option value="school_standard" @selected(old('default_password_pattern', $securityPolicy['default_password_pattern']) === 'school_standard')>Institutional Default (Requires instant onboarding reset)</option>
                            </select>
                        </div>

                        {{-- OTP Expiry --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">OTP Expiration Window</label>
                            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Validity lifespan for email password-reset OTP verification codes before expiration.</p>
                            <div class="flex items-center gap-0">
                                <input type="number" name="otp_expiry_minutes" min="1" max="60"
                                    x-model="otpMin"
                                    value="{{ old('otp_expiry_minutes', $securityPolicy['otp_expiry_minutes']) }}"
                                    class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    required />
                                <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">min</span>
                            </div>
                            <p class="mt-1.5 text-xs text-brand-600 dark:text-brand-400" x-text="'→ ' + otpMin + ' minutes until expiry'"></p>
                        </div>

                        {{-- Session Timeout --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Session Inactivity Timeout</label>
                            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Idle duration before active web sessions automatically expire and require re-authentication.</p>
                            <div class="flex items-center gap-0">
                                <input type="number" name="session_timeout_minutes" min="15" max="1440"
                                    x-model="sessionMin"
                                    value="{{ old('session_timeout_minutes', $securityPolicy['session_timeout_minutes']) }}"
                                    class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    required />
                                <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">min</span>
                            </div>
                            <p class="mt-1.5 text-xs text-brand-600 dark:text-brand-400" x-text="'→ ' + sessionLabel + ' idle before logout'"></p>
                        </div>

                        {{-- Audit Retention --}}
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <label class="mb-1 block text-sm font-semibold text-gray-900 dark:text-white">Audit Log Retention Policy</label>
                            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Timeframe to retain user activity logs and administrative changes for compliance audit.</p>
                            <div class="flex items-center gap-0">
                                <input type="number" name="audit_retention_days" min="30" max="1825"
                                    value="{{ old('audit_retention_days', $securityPolicy['audit_retention_days']) }}"
                                    class="w-28 rounded-l-md border border-r-0 border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    required />
                                <span class="inline-flex h-[38px] items-center rounded-r-md border border-gray-200 bg-gray-100 px-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">days</span>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-400">1–5 years maximum retention</p>
                        </div>
                    </div>

                    {{-- Enforce Strong Password Panel --}}
                    <div class="rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-800/40 dark:bg-rose-950/10">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="enforce_strong_password" value="1"
                                @checked(old('enforce_strong_password', $securityPolicy['enforce_strong_password']) === '1')
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="shield-alert" class="h-4 w-4 text-rose-500"></i>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Enforce Strong Passwords upon Onboarding</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Requires users to set a password with at least 8 characters containing letters and digits when finalizing initial onboarding.</p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                        <x-common.button-spinner text="Save Security Policies" loadingText="Saving..." class="text-white" />
                    </div>
                </form>
            </x-cards.base-card>
        </div>

        {{-- TAB 3: AI Assistant Configuration --}}
        <div x-show="activeTab === 'ai'" x-cloak>
            <x-cards.base-card title="AI Inventory Assistant Settings" subtitle="Configure AI provider integration, active model stack, and role-scoped context guardrails.">
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="section" value="ai">

                    {{-- AI Status Indicator + Test Button --}}
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800"
                        x-data="{
                            pinging: false,
                            pingResult: null,
                            async testConnection() {
                                this.pinging = true;
                                this.pingResult = null;
                                try {
                                    const res = await fetch('{{ route('admin.settings.ping-ai') }}', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            'Accept': 'application/json'
                                        }
                                    });
                                    this.pingResult = await res.json();
                                } catch(e) {
                                    this.pingResult = { ok: false, message: 'Request failed.' };
                                } finally {
                                    this.pinging = false;
                                }
                            }
                        }">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $systemDiagnostics['openRouterKeySet'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                <i data-lucide="bot" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Provider Status: {{ $systemDiagnostics['openRouterKeySet'] ? 'OpenRouter Connected' : 'Grounded Local Intelligence (Fallback Active)' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $systemDiagnostics['openRouterKeySet'] ? 'Live OpenRouter API key detected in environment.' : 'No API key set. Assistant gracefully responds using local database rules.' }}
                                </p>
                                {{-- Ping result --}}
                                <p x-show="pingResult !== null" class="mt-1 text-xs font-medium"
                                    :class="pingResult?.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                    x-text="pingResult?.message"></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $systemDiagnostics['openRouterKeySet'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $systemDiagnostics['openRouterKeySet'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                {{ $systemDiagnostics['openRouterKeySet'] ? 'Active Online' : 'Local Fallback' }}
                            </span>
                            <button type="button" @click="testConnection"
                                :disabled="pinging"
                                class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                                <i data-lucide="zap" class="h-3.5 w-3.5" x-show="!pinging"></i>
                                <i data-lucide="loader-circle" class="h-3.5 w-3.5 animate-spin" x-show="pinging" x-cloak></i>
                                <span x-text="pinging ? 'Testing...' : 'Test Connection'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- Primary Model --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary AI Model</label>
                            <div class="relative">
                                <input type="text" name="primary_model"
                                    value="{{ old('primary_model', $aiConfig['primary_model']) }}"
                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 pr-9 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    required />
                                <button type="button" title="Copy model name"
                                    onclick="navigator.clipboard.writeText(this.previousElementSibling.value)"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i data-lucide="copy" class="h-4 w-4"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Default: <code class="font-mono">meta-llama/llama-3.3-70b-instruct:free</code></p>
                        </div>

                        {{-- Fallback Model --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Fallback Model</label>
                            <div class="relative">
                                <input type="text" name="fallback_model"
                                    value="{{ old('fallback_model', $aiConfig['fallback_model']) }}"
                                    class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 pr-9 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    required />
                                <button type="button" title="Copy model name"
                                    onclick="navigator.clipboard.writeText(this.previousElementSibling.value)"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <i data-lucide="copy" class="h-4 w-4"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Backup model used when primary rate limits occur.</p>
                        </div>

                        {{-- Chat History Window --}}
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Chat History Message Window</label>
                            <input type="number" name="max_history_messages" min="2" max="20"
                                value="{{ old('max_history_messages', $aiConfig['max_history_messages']) }}"
                                class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                required />
                            <p class="mt-1 text-xs text-gray-500">Number of recent messages included as context. Higher values = more context but slower responses.</p>
                        </div>
                    </div>

                    {{-- Role-Scoped Context Guard Panel --}}
                    <div class="rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-800/40 dark:bg-rose-950/10">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="role_scoped_context" value="1"
                                @checked(old('role_scoped_context', $aiConfig['role_scoped_context']) === '1')
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="shield-alert" class="h-4 w-4 text-rose-500"></i>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Strict Role-Scoped Context Guard</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Prevents end-users from querying inventory they are not assigned to. <strong class="text-rose-600 dark:text-rose-400">Disabling this exposes all inventory data to all roles.</strong></p>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-700">
                        <x-common.button-spinner text="Save AI Configuration" loadingText="Saving..." class="text-white" />
                    </div>
                </form>
            </x-cards.base-card>
        </div>

        {{-- TAB 4: System Maintenance & Diagnostics --}}
        <div x-show="activeTab === 'diagnostics'" x-cloak>
            <div class="space-y-6">

                {{-- System Environment & Framework Grid --}}
                <x-cards.base-card title="System Runtime & Environment" subtitle="Framework versions, database status, and server drivers.">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Framework</p>
                            <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">Laravel {{ $systemDiagnostics['laravelVersion'] }}</p>
                            <span class="text-xs text-gray-400">PHP {{ $systemDiagnostics['phpVersion'] }}</span>
                        </div>

                        {{-- Environment — amber warning if debug ON --}}
                        <div class="rounded-lg border p-4 {{ $systemDiagnostics['appDebug'] ? 'border-amber-300 bg-amber-50 dark:border-amber-700/50 dark:bg-amber-950/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
                            <div class="flex items-center gap-1.5">
                                @if($systemDiagnostics['appDebug'])
                                    <i data-lucide="triangle-alert" class="h-3.5 w-3.5 text-amber-500"></i>
                                @endif
                                <p class="text-xs font-semibold uppercase tracking-wider {{ $systemDiagnostics['appDebug'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Environment</p>
                            </div>
                            <p class="mt-1 text-lg font-bold capitalize text-gray-900 dark:text-white">{{ $systemDiagnostics['appEnv'] }}</p>
                            <span class="text-xs {{ $systemDiagnostics['appDebug'] ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                                Debug: {{ $systemDiagnostics['appDebug'] ? 'Enabled ⚠' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Database</p>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $systemDiagnostics['dbConnected'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                <p class="text-lg font-bold capitalize text-gray-900 dark:text-white">{{ $systemDiagnostics['dbDriver'] }}</p>
                            </div>
                            <span class="text-xs {{ $systemDiagnostics['dbConnected'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $systemDiagnostics['dbConnected'] ? 'Connection Healthy' : 'Error: ' . $systemDiagnostics['dbError'] }}
                            </span>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cache Driver</p>
                            <p class="mt-1 text-lg font-bold uppercase text-gray-900 dark:text-white">{{ $systemDiagnostics['cacheDriver'] }}</p>
                            <span class="text-xs text-gray-400">Queue: {{ $systemDiagnostics['queueDriver'] }}</span>
                        </div>
                    </div>
                </x-cards.base-card>

                {{-- Cache Optimization Controls --}}
                <x-cards.base-card title="Cache Management & System Optimization" subtitle="Perform routine maintenance and clear application compilation caches.">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <form method="POST" action="{{ route('admin.settings.clear-cache') }}">
                            @csrf
                            <input type="hidden" name="cache_type" value="views">
                            <div class="flex h-full flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Compiled Views</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Recompiles Blade templates to apply latest UI modifications immediately.</p>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                        <i data-lucide="eye-off" class="h-3.5 w-3.5"></i>
                                        Clear View Cache
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.clear-cache') }}">
                            @csrf
                            <input type="hidden" name="cache_type" value="routes">
                            <div class="flex h-full flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Route Cache</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Refreshes application routing tables and registered role endpoints.</p>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                        <i data-lucide="map-off" class="h-3.5 w-3.5"></i>
                                        Clear Route Cache
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.clear-cache') }}">
                            @csrf
                            <input type="hidden" name="cache_type" value="config">
                            <div class="flex h-full flex-col justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Config Cache</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Reloads environment variables and global configuration settings.</p>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                        <i data-lucide="settings-2" class="h-3.5 w-3.5"></i>
                                        Clear Config Cache
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.settings.clear-cache') }}">
                            @csrf
                            <input type="hidden" name="cache_type" value="all">
                            <div class="flex h-full flex-col justify-between rounded-lg border border-rose-200 bg-rose-50/40 p-4 dark:border-rose-800/40 dark:bg-rose-950/20">
                                <div>
                                    <p class="text-sm font-semibold text-rose-900 dark:text-rose-300">Complete Optimization</p>
                                    <p class="mt-1 text-xs text-rose-700/80 dark:text-rose-400">Clears all framework caches (views, routes, config, events) simultaneously.</p>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-rose-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-rose-700">
                                        <i data-lucide="zap" class="h-3.5 w-3.5"></i>
                                        Optimize & Clear All
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </x-cards.base-card>

                {{-- System Health Summary --}}
                <x-cards.base-card title="System Health & Database Statistics" subtitle="Overview of live database records and audit footprints.">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                                <i data-lucide="users" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Total User Accounts</span>
                                <p class="mt-0.5 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($systemDiagnostics['totalUsers']) }}</p>
                                <span class="text-xs text-gray-400">Across all system roles</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <i data-lucide="package" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Inventory Items</span>
                                <p class="mt-0.5 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($systemDiagnostics['totalInventory']) }}</p>
                                <span class="text-xs text-gray-400">Tracked catalog records</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <i data-lucide="scroll-text" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <span class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Audit Log Entries</span>
                                <p class="mt-0.5 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($systemDiagnostics['totalAuditLogs']) }}</p>
                                <span class="text-xs text-gray-400">Immutable audit trails</span>
                            </div>
                        </div>
                    </div>
                </x-cards.base-card>
            </div>
        </div>
    </div>
@endsection
