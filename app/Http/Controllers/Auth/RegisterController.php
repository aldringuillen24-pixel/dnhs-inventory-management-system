<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * Handle a JSON registration attempt for the Vue SPA register view.
     *
     * Self-registration always assigns the End User role and never an
     * Administrator or Property Custodian role. Registration can be
     * disabled globally via the PUBLIC_REGISTRATION_ENABLED env var.
     */
    public function registerJson(Request $request)
    {
        if (! env('PUBLIC_REGISTRATION_ENABLED', false)) {
            throw ValidationException::withMessages([
                'registration' => 'Public registration is currently disabled.',
            ]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $endUserRole = Role::where('role_name', 'End User')->first();

        if (! $endUserRole) {
            throw ValidationException::withMessages([
                'registration' => 'End User role is not configured. Please contact an administrator.',
            ]);
        }

        $user = User::create([
            'role_id' => $endUserRole->role_id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'pending',
        ])->load('role');

        return response()->json([
            'message' => 'Account created successfully. It is pending admin approval before you can sign in.',
            'user' => $user,
            'role' => $user->role?->role_name,
        ]);
    }
}
