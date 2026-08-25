<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PropertyCustodianController;
use App\Http\Controllers\EndUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\UserController;
use App\Models\User;

// Authentication Routes
Route::get('/signin', function () {
    return view('components.auth.signin', ['title' => 'Sign In'])->with('success', session('success'))->with('error', session('error'));
})->name('signin');

Route::get('/login', function () {
    return redirect()->route('signin');
})->name('login');

Route::post('/signin', [LoginController::class, 'login'])->name('signin.post');

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');


// Protected Routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', function () {
        $user = auth()->user();

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
    });
});


// Property Custodian Routes (require property custodian role)
Route::middleware(['auth', 'role:Property Custodian'])->prefix('property-custodian')->name('propertyCustodian.')->group(function () {
    Route::get('/onboarding', [PropertyCustodianController::class, 'onboarding'])->name('onboarding');

    Route::post('/onboarding', [PropertyCustodianController::class, 'onboardingPost'])->name('onboarding.post');

    Route::get('/dashboard', [PropertyCustodianController::class, 'dashboard'])->name('dashboard');

    Route::get('/inventory', [PropertyCustodianController::class, 'inventory'])->name('inventory');

    Route::post('/inventory/stock-in', [PropertyCustodianController::class, 'stockIn'])->name('inventory.stock-in');

    Route::get('/transactions', [PropertyCustodianController::class, 'transactions'])->name('transactions');

    Route::post('/transactions/assign', [PropertyCustodianController::class, 'assignItem'])->name('transactions.assignItem'); 

    Route::post('/requests/{id}/approve', [PropertyCustodianController::class, 'approveRequest'])->name('requests.approve');

    Route::post('/requests/{id}/decline', [PropertyCustodianController::class, 'declineRequest'])->name('requests.decline');

    Route::post('/transfers/{id}/approve', [PropertyCustodianController::class, 'approveTransfer'])->name('transfers.approve');

    Route::post('/transfers/{id}/decline', [PropertyCustodianController::class, 'declineTransfer'])->name('transfers.decline');

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

    Route::get('/dashboard', function () {
        return view('pages.endUser.dashboard', ['title' => 'End User Dashboard']);
    })->name('dashboard');

    Route::get('/requests', [EndUserController::class, 'requests'])->name('requests');
    Route::get('/requests/my-requests', fn () => redirect()->route('endUser.requests'))->name('my-requests');
    Route::post('/requests', [EndUserController::class, 'storeRequest'])->name('requests.store');
    Route::post('/requests/{id}/respond', [EndUserController::class, 'respondRequest'])->name('requests.respond');

    Route::get('/assigned-items', [EndUserController::class, 'myAssignedItems'])->name('my-assigned-items');
    Route::post('/transfer', [EndUserController::class, 'transferAssignedItem'])->name('assigned-items.transfer');

    Route::get('/profile', function () {
        return view('pages.endUser.profile', ['title' => 'Profile']);
    })->name('profile');

    Route::patch('/profile', [EndUserController::class, 'updateProfile'])->name('profile.update');
});


// Admin Routes (require admin role)
Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('pages.administrator.dashboard', ['title' => 'Admin Dashboard']);
    })->name('dashboard');

    Route::get('/users-management', [UserController::class, 'index'])->name('users-management');

    Route::post('/users-management/export-slips', [UserController::class, 'exportSlips'])->name('users-management.export-slips');

    Route::post('/store-user', [UserController::class, 'store'])->name('users-management.store');
   
    Route::post('/users-management/generate-users', [UserController::class, 'generateUsers'])->name('users-management.generate-users');
   
    Route::patch('/users-management/{user}', [UserController::class, 'update'])->name('users-management.update');

    Route::get('/profile', function () {
        return view('pages.administrator.profile', ['title' => 'Profile']);
    })->name('profile');

    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
});
