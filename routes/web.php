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
})->name('login');

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

    Route::get('/users-management', [UserController::class, 'index'])->name('users-management');

    Route::post('/users-management/export-slips', [UserController::class, 'exportSlips'])->name('users-management.export-slips');

    Route::post('/store-user', [UserController::class, 'store'])->name('users-management.store');
    Route::post('/users-management/generate-users', [UserController::class, 'generateUsers'])->name('users-management.generate-users');
    Route::patch('/users-management/{user}', [UserController::class, 'update'])->name('users-management.update');

    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');
});
