<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\InspectorController;
use App\Http\Controllers\SchoolHeadController;
use App\Http\Controllers\PropertyCustodianController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Auth\PasswordResetController;

// Authentication Routes

Route::get('/signin', fn () => redirect('/spa/signin'))->name('signin');

Route::get('/login', function () {
    return redirect()->route('signin');
})->name('login');

Route::post('/signin', [LoginController::class, 'login'])->name('signin.post')->middleware('throttle:5,1');

// JSON sign-in for the Vue SPA view (auth/SignIn.vue). Session + CSRF,
// same as Blade; throttle mirrors the Blade endpoint.
Route::post('/api/signin', [LoginController::class, 'loginJson'])->middleware('throttle:5,1');

// JSON account setup for the Vue SPA onboarding view. Authenticated
// users with a temporary password complete setup for their own role.
Route::middleware('auth')->post('/api/onboarding', [OnboardingController::class, 'complete'])->name('api.onboarding');

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

// JSON sign-out for SPA views rendered without the app shell (onboarding).
Route::middleware('auth')->post('/api/logout', [LogoutController::class, 'logoutJson'])->name('api.logout');

Route::get('/forgot-password', fn () => redirect('/spa/forgot-password'))
    ->name('password.forgot');

Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])
    ->name('password.email')
    ->middleware('throttle:100,1'); //3,10

Route::get('/reset-password/verify', fn () => redirect('/spa/forgot-password'))
    ->name('password.otp');

Route::post('/reset-password/verify', [PasswordResetController::class, 'verifyOtp'])
    ->name('password.otp.verify')
    ->middleware('throttle:5,10');

Route::get('/reset-password/new', fn () => redirect('/spa/forgot-password'))
    ->name('password.reset.form');

Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])
    ->name('password.update');

// JSON password reset for the Vue SPA view. Throttles mirror Blade.
Route::post('/api/password/email', [PasswordResetController::class, 'sendOtpJson'])->middleware('throttle:100,1');
Route::post('/api/password/verify', [PasswordResetController::class, 'verifyOtpJson'])->middleware('throttle:5,10');
Route::post('/api/password/reset', [PasswordResetController::class, 'updatePasswordJson']);

// Protected Routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', function (Request $request) {
        $user = $request->user();

        if ($user->temporary_password && $user->role) {
            return redirect('/spa/onboarding');
        }

        if ($user->role && strtolower($user->role->role_name) === 'administrator') {
            return redirect('/spa/admin/dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'property custodian') {
            return redirect('/spa/dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'end user') {
            return redirect('/spa/end-user/dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'school head') {
            return redirect('/spa/school-head/dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'inspector') {
            return redirect('/spa/inspector/inventory');
        }
    });
});


// Property Custodian Routes (require property custodian role)
Route::middleware(['auth', 'role:Property Custodian'])->prefix('property-custodian')->name('propertyCustodian.')->group(function () {
    Route::get('/onboarding', fn () => redirect('/spa/onboarding'))->name('onboarding');

    Route::get('/dashboard', fn () => redirect('/spa/dashboard'))->name('dashboard');

    Route::get('/inventory', fn () => redirect('/spa/inventory'))->name('inventory');
    Route::get('/inventory/export', [PropertyCustodianController::class, 'exportInventory'])->name('inventory.export');

    Route::post('/inventory/stock-in', [PropertyCustodianController::class, 'stockIn'])->name('inventory.stock-in');

    Route::get('/inventory/{inventory}/edit', fn () => redirect('/spa/inventory'))->name('inventory.edit');
    Route::patch('/inventory/{inventory}', [PropertyCustodianController::class, 'updateInventory'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [PropertyCustodianController::class, 'destroyInventory'])->name('inventory.destroy');

    Route::get('/transactions', fn () => redirect('/spa/transactions'))->name('transactions');

    Route::post('/transactions/assign', [PropertyCustodianController::class, 'assignItem'])->name('transactions.assignItem'); 
    Route::post('/transactions/{transactionId}/manual-return', [PropertyCustodianController::class, 'returnManualIssue'])->name('transactions.manual-return');

    Route::post('/requests/{id}/approve', [PropertyCustodianController::class, 'approveRequest'])->name('requests.approve');

    Route::post('/requests/{id}/cancel', [PropertyCustodianController::class, 'cancelItemRequest'])->name('requests.cancel');

    Route::post('/requests/{id}/decline', [PropertyCustodianController::class, 'declineRequest'])->name('requests.decline');

    Route::post('/transfers/{id}/approve', [PropertyCustodianController::class, 'approveTransfer'])->name('transfers.approve');

    Route::post('/transfers/{id}/decline', [PropertyCustodianController::class, 'declineTransfer'])->name('transfers.decline');

    Route::post('/returns/{id}/approve', [PropertyCustodianController::class, 'approveReturn'])->name('returns.approve');

    Route::post('/returns/{id}/decline', [PropertyCustodianController::class, 'declineReturn'])->name('returns.decline');

    Route::post('/inventory/receive-return', [PropertyCustodianController::class, 'receiveReturn'])->name('inventory.receive-return');

    Route::post('/inventory/{itemId}/send-to-maintenance', [PropertyCustodianController::class, 'sendToMaintenance'])->name('inventory.send-to-maintenance');

    Route::post('/inventory/{itemId}/send-to-inspection', [PropertyCustodianController::class, 'sendToInspection'])->name('inventory.send-to-inspection');

    Route::post('/inventory/{itemId}/mark-repaired', [PropertyCustodianController::class, 'markRepaired'])->name('inventory.mark-repaired');

    Route::post('/inventory/{itemId}/mark-ready-to-dispose', [PropertyCustodianController::class, 'markReadyToDispose'])->name('inventory.mark-ready-to-dispose');

    Route::post('/inventory/{itemId}/dispose', [PropertyCustodianController::class, 'disposeInventory'])->name('inventory.dispose');

    Route::get('/inventory/qr/lookup', [PropertyCustodianController::class, 'qrLookup'])->name('inventory.qr.lookup');

    Route::get('/reports', fn () => redirect('/spa/reports'))->name('reports');
    Route::get('/reports/forecast/recommendations', fn () => redirect('/spa/reports/forecast'))->name('reports.forecast.recommendations');
    Route::post('/reports/forecast', [PropertyCustodianController::class, 'runForecast'])->name('reports.forecast');
    Route::post('/reports/forecast/explanation', [PropertyCustodianController::class, 'explainForecast'])->name('reports.forecast.explanation');

    Route::get('/profile', fn () => redirect('/spa/profile'))->name('profile');

    Route::patch('/profile', [PropertyCustodianController::class, 'updateProfile'])->name('profile.update');
});

// End User Routes (require end user role)
Route::middleware(['auth', 'role:End User'])->prefix('end-user')->name('endUser.')->group(function () {
    Route::get('/onboarding', fn () => redirect('/spa/onboarding'))->name('onboarding');

    Route::get('/dashboard', fn () => redirect('/spa/end-user/dashboard'))->name('dashboard');

    Route::get('/requests', fn () => redirect('/spa/end-user/requests'))->name('requests');
    Route::get('/requests/my-requests', fn () => redirect('/spa/end-user/requests'))->name('my-requests');
    Route::post('/requests', [EndUserController::class, 'storeRequest'])->name('requests.store');
    Route::post('/requests/{id}/respond', [EndUserController::class, 'respondRequest'])->name('requests.respond');

    Route::get('/assigned-items', fn () => redirect('/spa/end-user/assigned'))->name('my-assigned-items');
    Route::post('/transfer', [EndUserController::class, 'transferAssignedItem'])->name('assigned-items.transfer');
    Route::post('/inventory/{itemId}/request-return', [EndUserController::class, 'requestReturn'])->name('inventory.request-return');
    Route::post('/inventory/{itemId}/cancel-return', [EndUserController::class, 'cancelReturnRequest'])->name('inventory.cancel-return');

    Route::get('/profile', fn () => redirect('/spa/profile'))->name('profile');

    Route::patch('/profile', [EndUserController::class, 'updateProfile'])->name('profile.update');
});

// School Head Routes (require school head role)
Route::middleware(['auth', 'role:School Head'])->prefix('school-head')->name('schoolHead.')->group(function () {
    Route::get('/onboarding', fn () => redirect('/spa/onboarding'))->name('onboarding');
    Route::get('/dashboard', fn () => redirect('/spa/school-head/dashboard'))->name('dashboard');
    Route::get('/inventory/overview', fn () => redirect('/spa/school-head/inventory'))->name('inventory.overview');
    Route::get('/reports', fn () => redirect('/spa/school-head/reports'))->name('reports');
    Route::get('/audit-logs', fn () => redirect('/spa/school-head/audit-logs'))->name('audit-logs');
    Route::get('/profile', fn () => redirect('/spa/profile'))->name('profile');
    Route::patch('/profile', [SchoolHeadController::class, 'updateProfile'])->name('profile.update');
});


// Admin Routes (require admin role)
Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', fn () => redirect('/spa/admin/dashboard'))->name('dashboard');

    Route::get('/reports', fn () => redirect('/spa/admin/reports'))->name('reports');

    Route::get('/reports/download', [AdminDashboardController::class, 'downloadReports'])->name('reports.download');

    Route::get('/users-management', fn () => redirect('/spa/admin/users'))->name('users-management');

    Route::post('/users-management/export-slips', [UserController::class, 'exportSlips'])->name('users-management.export-slips');

    Route::post('/store-user', [UserController::class, 'store'])->name('users-management.store');
   
    Route::post('/users-management/generate-users', [UserController::class, 'generateUsers'])->name('users-management.generate-users');
   
    Route::patch('/users-management/{user}', [UserController::class, 'update'])->name('users-management.update');

    Route::delete('/users-management/{user}', [UserController::class, 'destroy'])->name('users-management.destroy');

    Route::get('/settings', fn () => redirect('/spa/admin/settings'))->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::post('/settings/ping-ai', [AdminSettingsController::class, 'pingAi'])->name('settings.ping-ai');

    Route::get('/profile', fn () => redirect('/spa/profile'))->name('profile');
});

// Authenticated AI Assistant endpoint (accessible by all roles with role-scoped responses)
Route::middleware('auth')->post('/api/ai/chat', [AiAssistantController::class, 'chat'])->name('ai.chat');
Route::middleware('auth')->post('/api/ai/chat/reset', [AiAssistantController::class, 'reset'])->name('ai.chat.reset');
Route::middleware(['auth', 'role:Property Custodian'])
    ->post('/api/ai/comparison', [AiAssistantController::class, 'compare'])
    ->name('ai.comparison');

// JSON API for the Vue SPA. Session auth + CSRF, same as Blade. The
// server-side `auth` + `role` middleware remain the access control
// authority; `api.redirect` converts Blade redirects into JSON so the
// existing web controllers serve both clients without duplication.
Route::middleware(['auth', 'api.redirect'])->prefix('/api')->name('api.')->group(function () {
    Route::get('/me', function (Request $request) {
        $user = $request->user()->load('role');

        return response()->json([
            'user' => $user,
            'role' => $user->role?->role_name,
            'onboarding_required' => $user->temporary_password !== null,
        ]);
    })->name('me');

    Route::middleware('role:Property Custodian')->prefix('custodian')->name('custodian.')->group(function () {
        Route::get('/dashboard', [PropertyCustodianController::class, 'dashboard'])->name('dashboard');
        Route::get('/inventory', [PropertyCustodianController::class, 'inventory'])->name('inventory');
        Route::get('/inventory/export', [PropertyCustodianController::class, 'exportInventory'])->name('inventory.export');
        Route::post('/inventory/stock-in', [PropertyCustodianController::class, 'stockIn'])->name('inventory.stock-in');
        Route::get('/inventory/qr/lookup', [PropertyCustodianController::class, 'qrLookup'])->name('inventory.qr.lookup');
        Route::post('/inventory/receive-return', [PropertyCustodianController::class, 'receiveReturn'])->name('inventory.receive-return');
        Route::get('/inventory/{inventory}', [PropertyCustodianController::class, 'editInventory'])->name('inventory.edit');
        Route::patch('/inventory/{inventory}', [PropertyCustodianController::class, 'updateInventory'])->name('inventory.update');
        Route::delete('/inventory/{inventory}', [PropertyCustodianController::class, 'destroyInventory'])->name('inventory.destroy');
        Route::post('/inventory/{itemId}/send-to-maintenance', [PropertyCustodianController::class, 'sendToMaintenance'])->name('inventory.send-to-maintenance');
        Route::post('/inventory/{itemId}/send-to-inspection', [PropertyCustodianController::class, 'sendToInspection'])->name('inventory.send-to-inspection');
        Route::post('/inventory/{itemId}/mark-repaired', [PropertyCustodianController::class, 'markRepaired'])->name('inventory.mark-repaired');
        Route::post('/inventory/{itemId}/mark-ready-to-dispose', [PropertyCustodianController::class, 'markReadyToDispose'])->name('inventory.mark-ready-to-dispose');
        Route::post('/inventory/{itemId}/dispose', [PropertyCustodianController::class, 'disposeInventory'])->name('inventory.dispose');
        Route::get('/transactions', [PropertyCustodianController::class, 'transactions'])->name('transactions');
        Route::post('/transactions/assign', [PropertyCustodianController::class, 'assignItem'])->name('transactions.assign');
        Route::post('/transactions/{transactionId}/manual-return', [PropertyCustodianController::class, 'returnManualIssue'])->name('transactions.manual-return');
        Route::post('/requests/{id}/approve', [PropertyCustodianController::class, 'approveRequest'])->name('requests.approve');
        Route::post('/requests/{id}/cancel', [PropertyCustodianController::class, 'cancelItemRequest'])->name('requests.cancel');
        Route::post('/requests/{id}/decline', [PropertyCustodianController::class, 'declineRequest'])->name('requests.decline');
        Route::post('/transfers/{id}/approve', [PropertyCustodianController::class, 'approveTransfer'])->name('transfers.approve');
        Route::post('/transfers/{id}/decline', [PropertyCustodianController::class, 'declineTransfer'])->name('transfers.decline');
        Route::post('/returns/{id}/approve', [PropertyCustodianController::class, 'approveReturn'])->name('returns.approve');
        Route::post('/returns/{id}/decline', [PropertyCustodianController::class, 'declineReturn'])->name('returns.decline');
        Route::get('/reports', [PropertyCustodianController::class, 'reports'])->name('reports');
        Route::get('/reports/forecast/recommendations', [PropertyCustodianController::class, 'forecastRecommendations'])->name('reports.forecast.recommendations');
        Route::post('/reports/forecast', [PropertyCustodianController::class, 'runForecast'])->name('reports.forecast');
        Route::post('/reports/forecast/explanation', [PropertyCustodianController::class, 'explainForecast'])->name('reports.forecast.explanation');
        Route::patch('/profile', [PropertyCustodianController::class, 'updateProfile'])->name('profile.update');
    });

    Route::middleware('role:End User')->prefix('end-user')->name('end-user.')->group(function () {
        Route::get('/dashboard', [EndUserController::class, 'dashboard'])->name('dashboard');
        Route::get('/requests', [EndUserController::class, 'requests'])->name('requests');
        Route::get('/requests/my-requests', [EndUserController::class, 'myRequests'])->name('my-requests');
        Route::post('/requests', [EndUserController::class, 'storeRequest'])->name('requests.store');
        Route::post('/requests/{id}/respond', [EndUserController::class, 'respondRequest'])->name('requests.respond');
        Route::get('/assigned-items', [EndUserController::class, 'myAssignedItems'])->name('assigned-items');
        Route::post('/transfer', [EndUserController::class, 'transferAssignedItem'])->name('transfer');
        Route::post('/inventory/{itemId}/request-return', [EndUserController::class, 'requestReturn'])->name('inventory.request-return');
        Route::post('/inventory/{itemId}/cancel-return', [EndUserController::class, 'cancelReturnRequest'])->name('inventory.cancel-return');
        Route::patch('/profile', [EndUserController::class, 'updateProfile'])->name('profile.update');
    });

    Route::middleware('role:School Head')->prefix('school-head')->name('school-head.')->group(function () {
        Route::get('/dashboard', [SchoolHeadController::class, 'index'])->name('dashboard');
        Route::get('/inventory/overview', [SchoolHeadController::class, 'inventoryOverview'])->name('inventory.overview');
        Route::get('/reports', [SchoolHeadController::class, 'reports'])->name('reports');
        Route::get('/audit-logs', [SchoolHeadController::class, 'auditLogs'])->name('audit-logs');
        Route::patch('/profile', [SchoolHeadController::class, 'updateProfile'])->name('profile.update');
    });

    Route::middleware('role:Inspector')->prefix('inspector')->name('inspector.')->group(function () {
        Route::get('/inventory/overview', [SchoolHeadController::class, 'inventoryOverview'])->name('inventory.overview');
        Route::get('/reports', [SchoolHeadController::class, 'reports'])->name('reports');
        Route::get('/inspection/queue', [InspectorController::class, 'inspectionQueue'])->name('inspection.queue');
        Route::get('/inspection/qr-lookup', [InspectorController::class, 'qrLookup'])->name('inspection.qr-lookup');
        Route::post('/inspection/{inspectionId}/mark-inspected', [InspectorController::class, 'markInspected'])->name('inspection.mark-inspected');
    });

    Route::middleware('role:Administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports', [AdminDashboardController::class, 'reports'])->name('reports');
        Route::get('/reports/download', [AdminDashboardController::class, 'downloadReports'])->name('reports.download');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/export-slips', [UserController::class, 'exportSlips'])->name('users.export-slips');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/generate', [UserController::class, 'generateUsers'])->name('users.generate');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/profile', [UserController::class, 'updateOwnProfile'])->name('profile.update');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
        Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('settings.clear-cache');
        Route::post('/settings/ping-ai', [AdminSettingsController::class, 'pingAi'])->name('settings.ping-ai');
    });
});

// Vue shell entry points retain server-side role protection. The fallback
// serves client-side 404s only after authentication and role validation.
$spaShell = fn () => view('spa');

// Guest entry point for the Vue SPA sign-in view. Registered before the
// authenticated wildcards below so guests can reach it.
Route::get('/spa/signin', $spaShell)->name('spa.signin');

// Guest entry point for the Vue SPA password-reset view.
Route::get('/spa/forgot-password', $spaShell)->name('spa.forgot-password');

// Authenticated entry point for the Vue SPA onboarding view. Users with
// a temporary password land here; the client guard keeps them here
// until setup is complete.
Route::middleware('auth')->get('/spa/onboarding', $spaShell)->name('spa.onboarding');

Route::middleware(['auth', 'role:Administrator'])->get('/spa/admin/{any?}', $spaShell)->where('any', '.*');
Route::middleware(['auth', 'role:School Head'])->get('/spa/school-head/{any?}', $spaShell)->where('any', '.*');
Route::middleware(['auth', 'role:Inspector'])->get('/spa/inspector/{any?}', $spaShell)->where('any', '.*');
Route::middleware(['auth', 'role:End User'])->get('/spa/end-user/{any?}', $spaShell)->where('any', '.*');

Route::middleware(['auth', 'role:Property Custodian'])->prefix('spa')->group(function () use ($spaShell) {
    Route::get('/dashboard', $spaShell);
    Route::get('/inventory', $spaShell);
    Route::get('/transactions', $spaShell);
    Route::get('/reports', $spaShell);
});

Route::middleware(['auth', 'role:Administrator,Property Custodian,School Head,Inspector,End User'])
    ->get('/spa/profile', $spaShell);
Route::middleware(['auth', 'role:Administrator,Property Custodian,School Head,Inspector,End User'])
    ->get('/spa', $spaShell)
    ->name('spa');
Route::middleware(['auth', 'role:Administrator,Property Custodian,School Head,Inspector,End User'])
    ->get('/spa/{any?}', $spaShell)
    ->where('any', '.*');
