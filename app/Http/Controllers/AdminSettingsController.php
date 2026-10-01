<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Services\GeminiApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSettingsController extends Controller
{
    public function index(): View
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
            'geminiKeySet'      => !empty(config('services.gemini.api_key')),
            'activeAiModel'     => config('services.gemini.model', 'gemini-3.1-flash-lite'),
            'totalAuditLogs'    => UserAuditLog::count(),
            'totalUsers'        => User::count(),
            'totalInventory'    => Inventory::count(),
        ];

        $inventoryPolicy = session('admin_settings_inventory', [
            'low_stock_threshold'    => 5,
            'require_serial_number'  => '1',
            'default_lifespan_years' => 5,
            'return_due_days'        => 14,
            'auto_reminder_days'     => 3,
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

        return view('pages.administrator.settings', compact(
            'systemDiagnostics',
            'inventoryPolicy',
            'securityPolicy',
            'aiConfig'
        ));
    }

    public function update(Request $request)
    {
        $section = $request->input('section', 'inventory');

        if ($section === 'inventory') {
            $validated = $request->validate([
                'low_stock_threshold'    => ['required', 'integer', 'min:1', 'max:1000'],
                'require_serial_number'  => ['nullable', 'in:0,1'],
                'default_lifespan_years' => ['required', 'integer', 'min:1', 'max:30'],
                'return_due_days'        => ['required', 'integer', 'min:1', 'max:365'],
                'auto_reminder_days'     => ['required', 'integer', 'min:1', 'max:30'],
            ]);

            $validated['require_serial_number'] = $request->has('require_serial_number') ? '1' : '0';

            session(['admin_settings_inventory' => $validated]);
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
}
