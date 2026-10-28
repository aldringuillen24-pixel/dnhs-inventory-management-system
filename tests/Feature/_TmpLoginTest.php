<?php
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
test('admin login', function () {
    $role = Role::create(['role_name' => 'Administrator']);
    $user = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->postJson('/api/signin', ['username' => 'admin', 'password' => 'password'])
        ->dump()
        ->assertOk();
});
