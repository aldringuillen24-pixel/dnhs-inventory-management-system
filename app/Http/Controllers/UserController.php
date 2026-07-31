<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,role_id'],
        ]);

        $baseUsername = Str::slug($validatedData['name']);
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '-' . $counter;
            $counter++;
        }

        $user = User::create([
            'first_name' => $validatedData['name'],
            'last_name' => '',
            'username' => $username,
            'email' => null,
            'password' => Hash::make($validatedData['password']),
            'role_id' => $validatedData['role_id'],
            'status' => 'active',
        ]);

        return redirect()->route('admin.users-management')->with('success', 'User created successfully.');
    }
}
