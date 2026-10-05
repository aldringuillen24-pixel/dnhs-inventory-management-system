<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create(['role_name' => 'Administrator']);
    $this->custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $this->endUserRole = Role::create(['role_name' => 'End User']);

    $this->admin = User::create([
        'role_id' => $this->adminRole->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'admin123',
        'status' => 'active',
    ]);
});

test('administrator can retrieve user management data with metrics', function () {
    User::create([
        'role_id' => $this->custodianRole->role_id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'username' => 'janesmith',
        'email' => 'jane@example.com',
        'password' => 'password',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('api.admin.users.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'users' => ['data'],
            'roles',
            'metrics' => ['total', 'active', 'inactive', 'custodians', 'endUsers', 'admins'],
        ])
        ->assertJsonFragment(['first_name' => 'John', 'last_name' => 'Doe'])
        ->assertJsonFragment(['first_name' => 'Jane', 'last_name' => 'Smith']);
});

test('administrator can filter users by role and status', function () {
    $custodian = User::create([
        'role_id' => $this->custodianRole->role_id,
        'first_name' => 'Custodian',
        'last_name' => 'Person',
        'username' => 'custodianperson',
        'email' => 'cust@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $endUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Teacher',
        'last_name' => 'One',
        'username' => 'teacherone',
        'email' => 'teacher@example.com',
        'password' => 'password',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('api.admin.users.index', [
        'role_id' => $this->custodianRole->role_id,
    ]));

    $response->assertOk()
        ->assertJsonFragment(['first_name' => 'Custodian', 'last_name' => 'Person'])
        ->assertJsonMissing(['first_name' => 'Teacher', 'last_name' => 'One']);
});
