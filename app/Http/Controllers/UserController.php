<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserAuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function exportSlips(Request $request)
    {
        $selectedUserIds = array_filter(array_map('intval', (array) $request->input('selected_user_ids', [])));

        if (empty($selectedUserIds)) {
            return redirect()->back()->with('error', 'Please select at least one account.');
        }

        $users = User::whereIn('id', $selectedUserIds)
            ->with('role')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'No selected accounts were found.');
        }

        $pdf = Pdf::loadView('pdfs.user-slips', ['users' => $users]);

        return $pdf->download('user-account-slips.pdf');
    }

    public function index(Request $request)
    {
        $roles = Role::orderBy('role_name')->get();

        $query = User::with('role')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->paginate(10)->withQueryString();

        $metrics = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'custodians' => User::whereHas('role', fn ($q) => $q->where('role_name', 'Property Custodian'))->count(),
            'endUsers' => User::whereHas('role', fn ($q) => $q->where('role_name', 'End User'))->count(),
            'admins' => User::whereHas('role', fn ($q) => $q->where('role_name', 'Administrator'))->count(),
        ];

        return view('pages.administrator.users-management', compact('users', 'roles', 'metrics'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'role_id' => ['required', 'exists:roles,role_id'],
        ]);

        $role = Role::findOrFail($validatedData['role_id']);

        // Policy: Prevent creating Administrator accounts via this endpoint
        if (strtolower($role->role_name) === 'administrator') {
            return redirect()->back()->with('error', 'Creating Administrator accounts is not allowed through this interface. Administrators must be created directly in the database.');
        }

        $plainPassword = $validatedData['password'] ?? Str::random(10);

        $baseUsername = Str::slug($validatedData['username']);
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '-' . $counter;
            $counter++;
        }

        $user = User::create([
            'first_name' => '',
            'last_name' => '',
            'username' => $username,
            'email' => null,
            'password' => $plainPassword,
            'temporary_password' => $plainPassword,
            'role_id' => $validatedData['role_id'],
            'status' => 'active',
        ]);

        return redirect()->route('admin.users-management')->with('success', 'User created successfully.');
    }

    public function generateUsers(Request $request)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,role_id'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        // Policy: Prevent creating Administrator accounts via this endpoint
        if (strtolower($role->role_name) === 'administrator') {
            return redirect()->back()->with('error', 'Bulk-generating Administrator accounts is not allowed. Administrators must be created directly in the database.');
        }

        $created = 0;

        for ($i = 0; $i < $validated['count']; $i++) {
            $username = '';

            do {
                $username = Str::slug($role->role_name . '-' . Str::random(4));
            } while (User::where('username', $username)->exists());

            $plainPassword = Str::random(10);

            User::create([
                'first_name' => 'Generated User',
                'last_name' => '',
                'username' => $username,
                'email' => null,
                'password' => $plainPassword,
                'temporary_password' => $plainPassword,
                'role_id' => $validated['role_id'],
                'status' => 'active',
            ]);

            $created++;
        }

        return redirect()->route('admin.users-management')->with('success', "$created accounts generated successfully.");
    }

    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'exists:roles,role_id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $currentUser = auth()->user();
        $adminRole = Role::where('role_name', 'Administrator')->first();

        // Prevent self-demotion: an Administrator cannot change their own role
        if ($currentUser->id === $user->id && $currentUser->role_id !== $validatedData['role_id']) {
            return redirect()->back()->with('error', 'You cannot demote yourself.');
        }

        // Prevent deactivating the last active Administrator (this check takes precedence over self-deactivation)
        if ($validatedData['status'] === 'inactive' && $user->role_id === $adminRole?->role_id) {
            $activeAdminCount = User::where('role_id', $adminRole->role_id)
                ->where('status', 'active')
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeAdminCount === 0) {
                return redirect()->back()->with('error', 'Cannot deactivate the last active Administrator. At least one Administrator must remain active.');
            }
        }

        // Prevent self-deactivation: an Administrator cannot deactivate themselves
        if ($currentUser->id === $user->id && $validatedData['status'] === 'inactive') {
            return redirect()->back()->with('error', 'You cannot deactivate yourself.');
        }

        // Prevent updating name or password once onboarding has been completed
        $incomingName = trim($validatedData['name'] ?? '');
        $currentFullName = trim($user->first_name . ' ' . $user->last_name);
        $nameChanged = $incomingName !== '' && $incomingName !== $currentFullName;
        $passwordChanged = !empty($validatedData['password']);

        if (is_null($user->temporary_password) && ($nameChanged || $passwordChanged)) {
            return redirect()->back()->with('error', 'Users can update their own name and password after onboarding.');
        }

        $originalRoleId = $user->role_id;
        $originalStatus = $user->status;

        if (!empty($validatedData['name'])) {
            [$firstName, $lastName] = $this->splitFullName($validatedData['name']);
            $user->first_name = $firstName;
            $user->last_name = $lastName;
        }

        $user->role_id = $validatedData['role_id'];
        $user->status = $validatedData['status'];

        if (!empty($validatedData['password'])) {
            $user->password = $validatedData['password'];
            $user->temporary_password = $validatedData['password'];
        }

        $user->save();

        if ((int) $originalRoleId !== (int) $validatedData['role_id']) {
            UserAuditLog::create([
                'actor_user_id' => $currentUser?->id,
                'actor_name' => $currentUser?->first_name . ' ' . $currentUser?->last_name ?: $currentUser?->username,
                'target_user_id' => $user->id,
                'target_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
                'action' => 'role_changed',
                'details' => [
                    'old_role' => optional(Role::find($originalRoleId))->role_name,
                    'new_role' => optional(Role::find($validatedData['role_id']))->role_name,
                ],
            ]);
        }

        if ($originalStatus !== $validatedData['status']) {
            UserAuditLog::create([
                'actor_user_id' => $currentUser?->id,
                'actor_name' => $currentUser?->first_name . ' ' . $currentUser?->last_name ?: $currentUser?->username,
                'target_user_id' => $user->id,
                'target_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
                'action' => 'status_changed',
                'details' => [
                    'old_status' => $originalStatus,
                    'new_status' => $validatedData['status'],
                ],
            ]);
        }

        return redirect()->route('admin.users-management')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if (!$user->temporary_password) {
            return redirect()->back()->with('error', 'Accounts that completed onboarding cannot be deleted.');
        }

        $user->delete();

        return redirect()->route('admin.users-management')->with('success', 'Pending user account deleted successfully.');
    }

    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $firstName = array_shift($parts) ?: '';
        $lastName = $parts ? implode(' ', $parts) : '';

        return [$firstName, $lastName];
    }
}
