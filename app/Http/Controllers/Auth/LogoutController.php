<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/spa/signin?signed_out=1');
    }

    /**
     * JSON sign-out for the Vue SPA views that render outside the app shell
     * (e.g. auth/Onboarding.vue, which has no header sign-out control).
     * Session handling matches logout(); returns JSON instead of a redirect.
     */
    public function logoutJson(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }
}