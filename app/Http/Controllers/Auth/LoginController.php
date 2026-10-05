<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Attempt to login using username (not email)
        if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Check if user is active
            if ($user->status !== 'active') {
                Auth::logout();
                $request->session()->flash('error', 'Your account is inactive. Please contact administrator.');
                throw ValidationException::withMessages([
                    'username' => 'Your account is inactive. Please contact administrator.',
                ]);
            }

            $request->session()->flash('success', 'Signed in successfully.');

            if ($user->temporary_password && $user->role) {
                return redirect('/spa/onboarding');
            }

            // Redirect based on role
            if ($user->role) {
                $role = strtolower($user->role->role_name);

                if ($role === 'administrator') {
                    return redirect()->intended(route('admin.dashboard'));
                }

                if ($role === 'property custodian') {
                    return redirect()->intended(route('propertyCustodian.dashboard'));
                }

                if ($role === 'end user') {
                    return redirect()->intended(route('endUser.dashboard'));
                }

                if ($role === 'school head') {
                    return redirect()->intended(route('schoolHead.dashboard'));
                }
            }

            Auth::logout();
            throw ValidationException::withMessages([
                'username' => 'Your account does not have a valid role. Please contact an administrator.',
            ]);
        }

        $request->session()->flash('error', 'The provided credentials do not match our records.');

        throw ValidationException::withMessages([
            'username' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle a JSON authentication attempt for the Vue SPA sign-in view.
     * Mirrors login() rules (active account, valid role) but responds
     * with the session payload instead of Blade redirects.
     */
    public function loginJson(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user()->load('role');

        if ($user->status !== 'active') {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Your account is inactive. Please contact administrator.',
            ]);
        }

        if (! $user->role) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Your account does not have a valid role. Please contact an administrator.',
            ]);
        }

        return response()->json([
            'message' => 'Signed in successfully.',
            'user' => $user,
            'role' => $user->role?->role_name,
            'onboarding_required' => $user->temporary_password !== null,
            // The session regenerates on login, so hand the fresh token
            // to the SPA (its <meta> copy is now stale).
            'csrf_token' => csrf_token(),
        ]);
    }
}