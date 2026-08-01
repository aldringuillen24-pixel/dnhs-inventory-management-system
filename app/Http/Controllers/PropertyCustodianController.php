<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PropertyCustodianController extends Controller
{
    public function onboarding()
    {
        $user = auth()->user();

        if (!$user->temporary_password) {
            return redirect()->route('dashboard');
        }

        return view('pages.propertyCustodian.onboarding', ['title' => 'Complete Your Account Setup']);
    }

    public function onboardingPost(Request $request)
    {
        $user = auth()->user();

        if (!$user->temporary_password) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
        $user->password = $validated['password'];
        $user->temporary_password = null;
        $user->save();

        return redirect()->route('propertyCustodian.dashboard')->with('success', 'Your account has been updated.');
    }

    public function dashboard()
    {
        return view('pages.propertyCustodian.dashboard', ['title' => 'Property Custodian Dashboard']);
    }

    public function inventory()
    {
        return view('pages.propertyCustodian.inventory', ['title' => 'Inventory']);
    }
}
