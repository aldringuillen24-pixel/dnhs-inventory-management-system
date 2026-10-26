<?php

namespace App\Http\Controllers;

use App\Models\AiApiKey;
use App\Models\Inventory;
use App\Models\InventoryPolicy;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Services\GeminiApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dbConnected = true;
        $dbError = null;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbConnected = false;
            $dbError = $e->getMessage();
        }

        $systemDiagnostics = [
            'phpVersion'        => PHP_VERSION,
            'laravelVersion'    => app()->version(),
            'appEnv'            => config('app.env'),
            'appDebug'          => config('app.debug'),
            'appUrl'            => config('app.url'),
            'dbDriver'          => config('database.default'),
            'dbConnected'       => $dbConnected,
            'dbError'           => $dbError,
            'cacheDriver'       => config('cache.default'),
            'sessionLifetime'   => config('session.lifetime'),
            'queueDriver'       => config('queue.default'),
            'geminiKeySet'      => $this->geminiKeySet(),
            'activeAiModel'     => config('services.gemini.model', 'gemini-3.1-flash-lite'),
            'totalAuditLogs'    => UserAuditLog::count(),
            'totalUsers'        => User::count(),
            'totalInventory'    => Inventory::count(),
        ];

        $inventoryPolicy = InventoryPolicy::current()->only([
            'low_stock_threshold',
            'require_serial_number',
            'default_lifespan_years',
            'return_due_days',
            'auto_reminder_days',
            'overdue_escalation_days',
            'inventory_verification_interval_months',
        ]);

        $securityPolicy = session('admin_settings_security', [
            'default_password_pattern' => 'temporary_random',
            'otp_expiry_minutes'       => 10,
            'session_timeout_minutes'  => (int) config('session.lifetime', 120),
            'audit_retention_days'     => 365,
            'enforce_strong_password'  => '1',
        ]);

        $aiConfig = session('admin_settings_ai', [
            'provider'             => 'Google AI Studio (Gemini, local fallback)',
            'primary_model'        => config('services.gemini.model', 'gemini-3.1-flash-lite'),
            'fallback_model'       => 'Grounded local response',
            'role_scoped_context'  => '1',
            'max_history_messages' => 6,
        ]);

        return response()->json(compact(
            'systemDiagnostics',
            'inventoryPolicy',
            'securityPolicy',
            'aiConfig',
        ) + ['aiKeys' => $this->maskedKeys(), 'envKey' => $this->envKeyInfo()]);
    }

    /**
     * Pool holds keys when migrated; env GEMINI_API_KEY seeds otherwise.
     * Only `gemini` provider rows count: other providers reserve pool
     * rows for future wiring and never drive AI failover.
     */
    private function geminiKeySet(): bool
    {
        if (! empty(config('services.gemini.api_key'))) {
            return true;
        }

        try {
            if (! Schema::hasTable('ai_api_keys')) {
                return false;
            }

            $query = AiApiKey::query()->where('is_active', true);
            if (Schema::hasColumn('ai_api_keys', 'provider')) {
                $query->where('provider', 'gemini');
            }

            return $query->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Environment fallback key for its own status card. Only a masked
     * tail is exposed — never the value. `in_use` mirrors the failover
     * rule: the env key serves traffic exactly when no active pooled
     * Gemini key exists.
     *
     * @return array{masked_key: string, in_use: bool}|null
     */
    private function envKeyInfo(): ?array
    {
        $env = config('services.gemini.api_key');
        if (! is_string($env) || trim($env) === '') {
            return null;
        }

        try {
            if (! Schema::hasTable('ai_api_keys')) {
                $inUse = true;
            } else {
                $query = AiApiKey::query()->where('is_active', true);
                if (Schema::hasColumn('ai_api_keys', 'provider')) {
                    $query->where('provider', 'gemini');
                }
                $inUse = ! $query->exists();
            }
        } catch (\Throwable) {
            // Unreadable pool behaves like an empty pool: failover falls
            // through to the env key (see GeminiApiService::activePool).
            $inUse = true;
        }

        return [
            'masked_key' => '…'.substr($env, -4),
            'in_use' => $inUse,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function maskedKeys(): array
    {
        try {
            if (! Schema::hasTable('ai_api_keys')) {
                return [];
            }

            $query = AiApiKey::query();
            if (Schema::hasColumn('ai_api_keys', 'provider')) {
                $query->orderBy('provider');
            }

            return $query->orderBy('priority')->orderBy('id')
                ->get()->map(fn (AiApiKey $key): array => $key->toMaskedArray())->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @throws ValidationException when the 5-active-key cap is reached.
     * The cap applies per provider, so a full Gemini pool never blocks
     * other providers (and vice versa).
     */
    private function ensureKeyCapacity(?int $ignoreId = null, string $provider = 'gemini'): void
    {
        $active = AiApiKey::query()->where('is_active', true);
        if ($ignoreId !== null) {
            $active->where('id', '!=', $ignoreId);
        }

        try {
            if (Schema::hasColumn('ai_api_keys', 'provider')) {
                $active->where('provider', $provider);
            }
        } catch (\Throwable) {
            // Pre-migration schema: fall through to the global count.
        }

        if ($active->count() >= AiApiKey::MAX_ACTIVE) {
            throw ValidationException::withMessages([
                'key' => ['At most '.AiApiKey::MAX_ACTIVE." active API keys are allowed for provider '{$provider}'. Deactivate or remove one first."],
            ]);
        }
    }

    public function update(Request $request)
    {
        $section = $request->input('section', 'inventory');

        if ($section === 'inventory') {
            $validated = $request->validate([
                'low_stock_threshold'    => ['required', 'integer', 'min:1', 'max:1000'],
                'require_serial_number'  => ['nullable', 'in:0,1'],
                'require_serial_number'  => ['nullable', 'in:0,1'],
                'default_lifespan_years' => ['required', 'integer', 'min:1', 'max:30'],
                'return_due_days'        => ['required', 'integer', 'min:1', 'max:365'],
                'auto_reminder_days'     => ['required', 'integer', 'min:1', 'max:30'],
                'overdue_escalation_days' => ['required', 'integer', 'min:1', 'max:90'],
                'inventory_verification_interval_months' => ['required', 'integer', Rule::in([1, 3, 6, 12])],
            ]);

            $validated['require_serial_number'] = $request->has('require_serial_number')
                && (string) $request->input('require_serial_number') === '1';
            InventoryPolicy::current()->update($validated);
            return redirect()->route('admin.settings', ['tab' => 'inventory'])->with('success', 'Inventory & lifecycle policies updated successfully.');
        }

        if ($section === 'security') {
            $validated = $request->validate([
                'default_password_pattern' => ['required', 'string', 'max:50'],
                'otp_expiry_minutes'       => ['required', 'integer', 'min:1', 'max:60'],
                'session_timeout_minutes'  => ['required', 'integer', 'min:15', 'max:1440'],
                'audit_retention_days'     => ['required', 'integer', 'min:30', 'max:1825'],
                'enforce_strong_password'  => ['nullable', 'in:0,1'],
            ]);

            $validated['enforce_strong_password'] = $request->has('enforce_strong_password') ? '1' : '0';

            session(['admin_settings_security' => $validated]);
            return redirect()->route('admin.settings', ['tab' => 'security'])->with('success', 'Security & account policies updated successfully.');
        }

        if ($section === 'ai') {
            $validated = $request->validate([
                'primary_model'        => ['required', 'string', 'max:100'],
                'fallback_model'       => ['required', 'string', 'max:100'],
                'role_scoped_context'  => ['nullable', 'in:0,1'],
                'max_history_messages' => ['required', 'integer', 'min:2', 'max:20'],
            ]);

            $validated['provider']            = 'Google AI Studio (Gemini, local fallback)';
            $validated['role_scoped_context'] = $request->has('role_scoped_context') ? '1' : '0';

            session(['admin_settings_ai' => $validated]);
            return redirect()->route('admin.settings', ['tab' => 'ai'])->with('success', 'AI Assistant configuration saved successfully.');
        }

        return redirect()->route('admin.settings', ['tab' => 'inventory'])->with('success', 'Settings updated.');
    }

    public function clearCache(Request $request)
    {
        $type = $request->input('cache_type', 'all');

        try {
            switch ($type) {
                case 'views':
                    Artisan::call('view:clear');
                    $message = 'Compiled views cache cleared successfully.';
                    break;
                case 'routes':
                    Artisan::call('route:clear');
                    $message = 'Route cache cleared successfully.';
                    break;
                case 'config':
                    Artisan::call('config:clear');
                    $message = 'Configuration cache cleared successfully.';
                    break;
                case 'optimize':
                case 'all':
                default:
                    Artisan::call('optimize:clear');
                    $message = 'All framework caches cleared successfully.';
                    break;
            }

            return redirect()->route('admin.settings', ['tab' => 'diagnostics'])->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings', ['tab' => 'diagnostics'])->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Ping the AI provider to verify connectivity.
     */
    public function pingAi(Request $request, GeminiApiService $geminiApi): JsonResponse
    {
        try {
            $response = $geminiApi->generate(
                'Return only the requested short text.',
                'Reply with exactly: ok',
                ['maxOutputTokens' => 8]
            );

            $ok = !empty($response);

            return response()->json([
                'ok'      => $ok,
                'message' => $ok ? 'Gemini API responded successfully.' : 'Gemini API returned no response. Check its key, quota, and model access.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Masked key-pool list. Full key material is never returned.
     */
    public function keys(): JsonResponse
    {
        return response()->json(['keys' => $this->maskedKeys(), 'envKey' => $this->envKeyInfo()]);
    }

    public function storeKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['sometimes', 'string', Rule::in(AiApiKey::PROVIDERS)],
            'label' => ['required', 'string', 'max:30'],
            'key' => ['required', 'string', 'min:20', 'max:500'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $provider = $validated['provider'] ?? 'gemini';
        $this->ensureKeyCapacity(null, $provider);

        $record = AiApiKey::create([
            'provider' => $provider,
            'label' => $validated['label'],
            'key_encrypted' => AiApiKey::encryptKey($validated['key']),
            'is_active' => true,
            'priority' => $validated['priority'] ?? 0,
        ]);

        return response()->json(['key' => $record->toMaskedArray()], 201);
    }

    public function updateKey(Request $request, AiApiKey $key): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['sometimes', 'string', Rule::in(AiApiKey::PROVIDERS)],
            'label' => ['sometimes', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

        $effectiveProvider = $validated['provider'] ?? ($key->provider ?? 'gemini');

        if (($validated['is_active'] ?? $key->is_active) && (! $key->is_active || $effectiveProvider !== ($key->provider ?? 'gemini'))) {
            $this->ensureKeyCapacity($key->id, $effectiveProvider);
        }

        $key->fill($validated);
        $key->save();

        return response()->json(['key' => $key->fresh()->toMaskedArray()]);
    }

    public function destroyKey(AiApiKey $key): JsonResponse
    {
        $key->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * 8-token live probe against one pooled Gemini key. Other providers
     * have no live probe yet: the key is stored (encrypted, masked) and
     * reported as such without ever touching the network.
     */
    public function pingKey(AiApiKey $key, GeminiApiService $geminiApi): JsonResponse
    {
        if (($key->provider ?? 'gemini') !== 'gemini') {
            return response()->json([
                'ok' => false,
                'message' => "Key '{$key->label}' is stored for provider '{$key->provider}'. No live probe exists for this provider yet.",
                'key' => $key->fresh()->toMaskedArray(),
            ]);
        }

        $ok = $geminiApi->probe($key);

        return response()->json([
            'ok' => $ok,
            'message' => $ok
                ? "Key '{$key->label}' responded successfully."
                : "Key '{$key->label}' did not respond. Check the key, quota, and model access.",
            'key' => $key->fresh()->toMaskedArray(),
        ]);
    }
}
