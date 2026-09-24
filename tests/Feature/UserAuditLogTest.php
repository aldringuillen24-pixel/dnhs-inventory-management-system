<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create(['role_name' => 'Administrator']);
    $this->schoolHeadRole = Role::create(['role_name' => 'School Head']);
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

test('administrator user changes are logged', function () {
    $target = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'username' => 'testuser',
        'email' => 'target@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.users-management.update', $target), [
            'role_id' => $this->custodianRole->role_id,
            'status' => 'inactive',
        ])
        ->assertRedirect(route('admin.users-management'));

    $this->assertDatabaseHas('user_audit_logs', [
        'actor_user_id' => $this->admin->id,
        'target_user_id' => $target->id,
        'action' => 'role_changed',
    ]);

    $this->assertDatabaseHas('user_audit_logs', [
        'actor_user_id' => $this->admin->id,
        'target_user_id' => $target->id,
        'action' => 'status_changed',
    ]);
});

test('school head can view audit logs page', function () {
    $schoolHead = User::create([
        'role_id' => $this->schoolHeadRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Head',
        'username' => 'schoolhead',
        'email' => 'head@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $response = $this->actingAs($schoolHead)->get(route('schoolHead.audit-logs'));

    $response->assertOk();
    $response->assertSee('Audit Logs');
});

test('administrator can change a users name or password while onboarding is still active', function () {
    $target = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'username' => 'testuser',
        'email' => 'target@example.com',
        'password' => 'temporary-password',
        'temporary_password' => 'temporary-password',
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->admin)
        ->patch(route('admin.users-management.update', $target), [
            'name' => 'Updated Name',
            'role_id' => $this->custodianRole->role_id,
            'status' => 'active',
            'password' => 'new-password-123',
        ]);

    $response->assertRedirect(route('admin.users-management'));

    $target->refresh();
    $this->assertSame('Updated', $target->first_name);
    $this->assertSame('Name', $target->last_name);
    $this->assertTrue(Hash::check('new-password-123', $target->password));
});

test('administrator cannot change a users name or password after onboarding is complete', function () {
    $target = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'username' => 'testuser',
        'email' => 'target@example.com',
        'password' => 'password',
        'temporary_password' => null,
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->admin)
        ->patch(route('admin.users-management.update', $target), [
            'name' => 'Abuse Name',
            'role_id' => $this->custodianRole->role_id,
            'status' => 'active',
            'password' => 'new-password-123',
        ]);

    $response->assertSessionHas('error', 'Users can update their own name and password after onboarding.');

    $target->refresh();
    $this->assertSame('Test', $target->first_name);
    $this->assertSame('User', $target->last_name);
    $this->assertTrue(Hash::check('password', $target->password));
});
