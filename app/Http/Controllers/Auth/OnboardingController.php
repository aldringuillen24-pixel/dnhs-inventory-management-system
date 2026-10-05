<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Complete temporary-password account setup for the authenticated SPA user.
 */
class OnboardingController extends Controller
{
    public function complete(Request $request)
    {
        $user = $request->user();

        if (! $user->temporary_password) {
            throw ValidationException::withMessages([
                'account' => 'Account setup has already been completed.',
            ]);
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        $isEndUser = $user->role && strtolower($user->role->role_name) === 'end user';

        if ($isEndUser) {
            $rules['building'] = ['required', 'string', 'max:255'];
            $rules['room'] = ['required', 'string', 'max:255'];
        }

        // Inspector has no writable profile route, so onboarding is the only
        // point where their sign-in username can be corrected. ignore($user->id)
        // lets them resubmit the username the admin already assigned.
        $isInspector = $user->role && strtolower($user->role->role_name) === 'inspector';

        if ($isInspector) {
            $rules['username'] = ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)];
        }

        $validated = $request->validate($rules);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];

        if ($isEndUser) {
            $user->building = $validated['building'];
            $user->room = $validated['room'];
        }

        if ($isInspector) {
            $user->username = $validated['username'];
        }

        $user->password = $validated['password'];
        $user->temporary_password = null;
        $user->save();
        $user->load('role');

        return response()->json([
            'message' => 'Your account has been updated.',
            'user' => $user,
            'role' => $user->role?->role_name,
            'onboarding_required' => false,
        ]);
    }
}
