<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;

uses(RefreshDatabase::class);

test('seeded administrator can sign in and is redirected to the admin dashboard', function () {
    // AdminUserSeeder reads the credentials from the environment and stores no
    // default password in source, so the bootstrap variables are supplied here.
    putenv('ADMIN_USERNAME=admin');
    putenv('ADMIN_PASSWORD=test-admin-password');
    $_ENV['ADMIN_USERNAME'] = 'admin';
    $_ENV['ADMIN_PASSWORD'] = 'test-admin-password';

    Artisan::call('db:seed');

    $this->assertDatabaseHas('roles', ['role_name' => 'Administrator']);
    $this->assertDatabaseHas('roles', ['role_name' => 'School Head']);
    $this->assertDatabaseHas('roles', ['role_name' => 'Property Custodian']);
    $this->assertDatabaseHas('roles', ['role_name' => 'Inspector']);
    $this->assertDatabaseHas('roles', ['role_name' => 'End User']);

    $response = $this->post('/signin', [
        'username' => 'admin',
        'password' => 'test-admin-password',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs(
        
        \App\Models\User::where('username', 'admin')->first()
    );

    putenv('ADMIN_USERNAME');
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_USERNAME'], $_ENV['ADMIN_PASSWORD']);
});

test('legacy sign-in sends temporary-password users to Vue onboarding', function () {
    $role = Role::create(['role_name' => 'School Head']);
    $user = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Temporary',
        'last_name' => 'User',
        'username' => 'temporary-school-head',
        'email' => 'temporary-school-head@example.com',
        'password' => 'temporary-password',
        'temporary_password' => 'temporary-password',
        'status' => 'active',
    ]);

    $this->post('/signin', [
        'username' => $user->username,
        'password' => 'temporary-password',
    ])->assertRedirect('/spa/onboarding');

    $this->get('/')->assertRedirect('/spa/onboarding');
    $this->actingAs($user)
        ->get(route('schoolHead.onboarding'))
        ->assertRedirect('/spa/onboarding');
});

test('temporary-password administrators are sent to Vue onboarding instead of the admin dashboard', function () {
    $role = Role::create(['role_name' => 'Administrator']);
    $user = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Temporary',
        'last_name' => 'Administrator',
        'username' => 'temporary-admin',
        'email' => 'temporary-admin@example.com',
        'password' => 'temporary-password',
        'temporary_password' => 'temporary-password',
        'status' => 'active',
    ]);

    $this->post('/signin', [
        'username' => $user->username,
        'password' => 'temporary-password',
    ])->assertRedirect('/spa/onboarding');

    $this->get('/')->assertRedirect('/spa/onboarding');
});
