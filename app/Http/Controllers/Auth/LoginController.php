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

            // Redirect based on role
            if ($user->role && strtolower($user->role->role_name) === 'administrator') {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('dashboard'));
        }

        $request->session()->flash('error', 'The provided credentials do not match our records.');

        throw ValidationException::withMessages([
            'username' => 'The provided credentials do not match our records.',
        ]);
    }
}