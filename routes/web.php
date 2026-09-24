<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\SchoolHeadController;
use App\Http\Controllers\PropertyCustodianController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;

// Authentication Routes

Route::get('/signin', function () {
    return view('components.auth.signin', ['title' => 'Sign In'])->with('success', session('success'))->with('error', session('error'));
})->name('signin');

Route::get('/login', function () {
    return redirect()->route('signin');
})->name('login');

Route::post('/signin', [LoginController::class, 'login'])->name('signin.post')->middleware('throttle:5,1');

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])
    ->name('password.forgot');

Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])
    ->name('password.email')
    ->middleware('throttle:100,1'); //3,10

Route::get('/reset-password/verify', [PasswordResetController::class, 'showOtpForm'])
    ->name('password.otp');

Route::post('/reset-password/verify', [PasswordResetController::class, 'verifyOtp'])
    ->name('password.otp.verify')
    ->middleware('throttle:5,10');

Route::get('/reset-password/new', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset.form');

Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])
    ->name('password.update');

// Protected Routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', function (Request $request) {
        $user = $request->user();

        if ($user->role && strtolower($user->role->role_name) === 'administrator') {
            return redirect()->route('admin.dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'property custodian') {
            if ($user->temporary_password) {
                return redirect()->route('propertyCustodian.onboarding');
            }

            return redirect()->route('propertyCustodian.dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'end user') {
            if ($user->temporary_password) {
                return redirect()->route('endUser.onboarding');
            }

            return redirect()->route('endUser.dashboard');
        }

        if ($user->role && strtolower($user->role->role_name) === 'school head') {
            if ($user->temporary_password) {
                return redirect()->route('schoolHead.onboarding');
            }

            return redirect()->route('schoolHead.dashboard');
        }
    });
});


// Property Custodian Routes (require property custodian role)
Route::middleware(['auth', 'role:Property Custodian'])->prefix('property-custodian')->name('propertyCustodian.')->group(function () {
    Route::get('/onboarding', [PropertyCustodianController::class, 'onboarding'])->name('onboarding');

    Route::post('/onboarding', [PropertyCustodianController::class, 'onboardingPost'])->name('onboarding.post');

    Route::get('/dashboard', [PropertyCustodianController::class, 'dashboard'])->name('dashboard');

    Route::get('/inventory', [PropertyCustodianController::class, 'inventory'])->name('inventory');
    Route::get('/inventory/export', [PropertyCustodianController::class, 'exportInventory'])->name('inventory.export');

    Route::post('/inventory/stock-in', [PropertyCustodianController::class, 'stockIn'])->name('inventory.stock-in');

    Route::get('/inventory/{inventory}/edit', [PropertyCustodianController::class, 'editInventory'])->name('inventory.edit');
    Route::patch('/inventory/{inventory}', [PropertyCustodianController::class, 'updateInventory'])->name('inventory.update');
    Route::delete('/inventory/{inventory}', [PropertyCustodianController::class, 'destroyInventory'])->name('inventory.destroy');

    Route::get('/transactions', [PropertyCustodianController::class, 'transactions'])->name('transactions');

    Route::post('/transactions/assign', [PropertyCustodianController::class, 'assignItem'])->name('transactions.assignItem'); 

    Route::post('/requests/{id}/approve', [PropertyCustodianController::class, 'approveRequest'])->name('requests.approve');

    Route::post('/requests/{id}/decline', [PropertyCustodianController::class, 'declineRequest'])->name('requests.decline');

    Route::post('/transfers/{id}/approve', [PropertyCustodianController::class, 'approveTransfer'])->name('transfers.approve');

    Route::post('/transfers/{id}/decline', [PropertyCustodianController::class, 'declineTransfer'])->name('transfers.decline');

    Route::post('/returns/{id}/approve', [PropertyCustodianController::class, 'approveReturn'])->name('returns.approve');

    Route::post('/returns/{id}/decline', [PropertyCustodianController::class, 'declineReturn'])->name('returns.decline');

    Route::post('/inventory/{itemId}/mark-returned', [PropertyCustodianController::class, 'markReturned'])->name('inventory.mark-returned');

    Route::post('/inventory/{itemId}/send-to-maintenance', [PropertyCustodianController::class, 'sendToMaintenance'])->name('inventory.send-to-maintenance');

    Route::post('/inventory/{itemId}/mark-repaired', [PropertyCustodianController::class, 'markRepaired'])->name('inventory.mark-repaired');

    Route::post('/inventory/{itemId}/mark-ready-to-dispose', [PropertyCustodianController::class, 'markReadyToDispose'])->name('inventory.mark-ready-to-dispose');

    Route::post('/inventory/{itemId}/dispose', [PropertyCustodianController::class, 'disposeInventory'])->name('inventory.dispose');

    Route::get('/inventory/qr/lookup', [PropertyCustodianController::class, 'qrLookup'])->name('inventory.qr.lookup');

    Route::get('/inventory/{inventory}/print-qr', [PropertyCustodianController::class, 'printQr'])->name('inventory.print-qr');
    Route::get('/inventory/{inventory}/qr-label', [PropertyCustodianController::class, 'printQr'])->name('inventory.qr.label');
    Route::get('/inventory/{inventory}/qr-labels', [PropertyCustodianController::class, 'printQr'])->name('inventory.qr.labels');

    Route::get('/reports', [PropertyCustodianController::class, 'reports'])->name('reports');

    Route::get('/profile', function () {
        return view('pages.propertyCustodian.profile', ['title' => 'Profile']);
    })->name('profile');

    Route::patch('/profile', [PropertyCustodianController::class, 'updateProfile'])->name('profile.update');
});

// End User Routes (require end user role)
Route::middleware(['auth', 'role:End User'])->prefix('end-user')->name('endUser.')->group(function () {
    Route::get('/onboarding', [EndUserController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [EndUserController::class, 'onboardingPost'])->name('onboarding.post');

    Route::get('/dashboard', [EndUserController::class, 'dashboard'])->name('dashboard');

    Route::get('/requests', [EndUserController::class, 'requests'])->name('requests');
    Route::get('/requests/my-requests', [EndUserController::class, 'myRequests'])->name('my-requests');
    Route::post('/requests', [EndUserController::class, 'storeRequest'])->name('requests.store');
    Route::post('/requests/{id}/respond', [EndUserController::class, 'respondRequest'])->name('requests.respond');

    Route::get('/assigned-items', [EndUserController::class, 'myAssignedItems'])->name('my-assigned-items');
    Route::post('/transfer', [EndUserController::class, 'transferAssignedItem'])->name('assigned-items.transfer');
    Route::post('/inventory/{itemId}/request-return', [EndUserController::class, 'requestReturn'])->name('inventory.request-return');
    Route::post('/inventory/{itemId}/cancel-return', [EndUserController::class, 'cancelReturnRequest'])->name('inventory.cancel-return');

    Route::get('/profile', function () {
        return view('pages.endUser.profile', ['title' => 'Profile']);
    })->name('profile');

    Route::patch('/profile', [EndUserController::class, 'updateProfile'])->name('profile.update');
});

// School Head Routes (require school head role)
Route::middleware(['auth', 'role:School Head'])->prefix('school-head')->name('schoolHead.')->group(function () {
    Route::get('/onboarding', [SchoolHeadController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding', [SchoolHeadController::class, 'onboardingPost'])->name('onboarding.post');
    Route::get('/dashboard', [SchoolHeadController::class, 'index'])->name('dashboard');
    Route::get('/inventory/overview', [SchoolHeadController::class, 'inventoryOverview'])->name('inventory.overview');
    Route::get('/reports', [SchoolHeadController::class, 'reports'])->name('reports');
    Route::get('/audit-logs', [SchoolHeadController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/profile', [SchoolHeadController::class, 'profile'])->name('profile');
    Route::patch('/profile', [SchoolHeadController::class, 'updateProfile'])->name('profile.update');
});


// Admin Routes (require admin role)
Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/reports', [AdminDashboardController::class, 'reports'])->name('reports');

    Route::get('/reports/download', [AdminDashboardController::class, 'downloadReports'])->name('reports.download');

    Route::get('/users-management', [UserController::class, 'index'])->name('users-management');

    Route::post('/users-management/export-slips', [UserController::class, 'exportSlips'])->name('users-management.export-slips');

    Route::post('/store-user', [UserController::class, 'store'])->name('users-management.store');
   
    Route::post('/users-management/generate-users', [UserController::class, 'generateUsers'])->name('users-management.generate-users');
   
    Route::patch('/users-management/{user}', [UserController::class, 'update'])->name('users-management.update');

    Route::delete('/users-management/{user}', [UserController::class, 'destroy'])->name('users-management.destroy');

    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::post('/settings/ping-ai', [AdminSettingsController::class, 'pingAi'])->name('settings.ping-ai');

    Route::get('/profile', function () {
        return view('pages.administrator.profile', ['title' => 'Profile']);
    })->name('profile');

    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
});

// Authenticated AI Assistant endpoint (accessible by all roles with role-scoped responses)
Route::middleware('auth')->post('/api/ai/chat', [AiAssistantController::class, 'chat'])->name('ai.chat');
