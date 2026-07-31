<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\UserController;
use App\Models\User;

// Authentication Routes
Route::get('/signin', function () {
    return view('components.auth.signin', ['title' => 'Sign In'])->with('success', session('success'))->with('error', session('error'));
})->name('signin');

Route::post('/signin', [LoginController::class, 'login'])->name('signin.post');

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

// Protected Routes (require authentication)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', function () {
        return view('components.auth.signin', ['title' => 'Sign In'])->with('success', session('success'))->with('error', session('error')   );
    })->name('dashboard');
});

// Admin Routes (require admin role)
Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('pages.administrator.dashboard', ['title' => 'Admin Dashboard']);
    })->name('dashboard');

    Route::get('/users-management', function () {
        return view('pages.administrator.users-management', ['title' => 'User Management']);
    })->name('users-management');

    Route::post('/store-user', [UserController::class, 'store'])->name('users-management.store');

    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
});