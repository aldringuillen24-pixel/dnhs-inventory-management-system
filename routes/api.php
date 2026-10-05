<?php

use Illuminate\Support\Facades\Route;

// Phase 0 SPA API: public foundation endpoint only. Authenticated,
// role-scoped JSON resources arrive in Phase 1; server-side
// `auth` + `role` middleware remain the access control source of truth.
Route::get('/spa/status', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'spa-foundation',
    ]);
})->name('api.spa.status');
