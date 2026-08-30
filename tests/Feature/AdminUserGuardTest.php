<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::create(['role_name' => 'Administrator']);
    $this->propertyRole = Role::create(['role_name' => 'Property Custodian']);

    $this->admin = User::create([
        'role_id' => $this->adminRole->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'admin',
        'email' => 'admin@test.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->otherAdmin = User::create([
        'role_id' => $this->adminRole->role_id,
        'first_name' => 'Other',
        'last_name' => 'Admin',
        'username' => 'other-admin',
        'email' => 'other@test.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('an administrator cannot demote themselves', function () {
    $response = $this->actingAs($this->admin)->patch(route('admin.users-management.update', $this->admin), [
        'name' => 'Admin User',
        'role_id' => $this->propertyRole->role_id,
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'You cannot demote yourself.');

    $this->admin->refresh();
    expect($this->admin->role_id)->toBe($this->adminRole->role_id);
});

test('an administrator cannot deactivate themselves', function () {
    $response = $this->actingAs($this->admin)->patch(route('admin.users-management.update', $this->admin), [
        'name' => 'Admin User',
        'role_id' => $this->adminRole->role_id,
        'status' => 'inactive',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'You cannot deactivate yourself.');

    $this->admin->refresh();
    expect($this->admin->status)->toBe('active');
});

test('cannot deactivate the last active administrator', function () {
    $this->otherAdmin->update(['status' => 'inactive']);

    $response = $this->actingAs($this->admin)->patch(route('admin.users-management.update', $this->admin), [
        'name' => 'Admin User',
        'role_id' => $this->adminRole->role_id,
        'status' => 'inactive',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Cannot deactivate the last active Administrator. At least one Administrator must remain active.');

    $this->admin->refresh();
    expect($this->admin->status)->toBe('active');
});

test('can deactivate an administrator if other active administrators exist', function () {
    $response = $this->actingAs($this->admin)->patch(route('admin.users-management.update', $this->otherAdmin), [
        'name' => 'Other Admin',
        'role_id' => $this->adminRole->role_id,
        'status' => 'inactive',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User updated successfully.');

    $this->otherAdmin->refresh();
    expect($this->otherAdmin->status)->toBe('inactive');
});

test('can demote an administrator if other active administrators exist', function () {
    $response = $this->actingAs($this->admin)->patch(route('admin.users-management.update', $this->otherAdmin), [
        'name' => 'Other Admin',
        'role_id' => $this->propertyRole->role_id,
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User updated successfully.');

    $this->otherAdmin->refresh();
    expect($this->otherAdmin->role_id)->toBe($this->propertyRole->role_id);
});

// Administrator account creation policy tests
test('cannot create an administrator account via store endpoint', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users-management.store'), [
        'username' => 'new-admin',
        'password' => 'password123',
        'role_id' => $this->adminRole->role_id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Creating Administrator accounts is not allowed through this interface. Administrators must be created directly in the database.');

    expect(User::where('username', 'new-admin')->exists())->toBeFalse();
});

test('cannot bulk-generate administrator accounts via generateUsers endpoint', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.users-management.generate-users'), [
        'role_id' => $this->adminRole->role_id,
        'count' => 3,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Bulk-generating Administrator accounts is not allowed. Administrators must be created directly in the database.');

    expect(User::where('role_id', $this->adminRole->role_id)->count())->toBe(2); // Only the two seeded admins
});

test('can create non-administrator accounts via store endpoint', function () {
    $endUserRole = Role::create(['role_name' => 'End User']);

    $response = $this->actingAs($this->admin)->post(route('admin.users-management.store'), [
        'username' => 'new-end-user',
        'password' => 'password123',
        'role_id' => $endUserRole->role_id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User created successfully.');

    $user = User::where('username', 'new-end-user')->first();
    expect($user)->not->toBeNull()
        ->and($user->role_id)->toBe($endUserRole->role_id);
});

test('can bulk-generate non-administrator accounts via generateUsers endpoint', function () {
    $endUserRole = Role::create(['role_name' => 'End User']);

    $response = $this->actingAs($this->admin)->post(route('admin.users-management.generate-users'), [
        'role_id' => $endUserRole->role_id,
        'count' => 3,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', '3 accounts generated successfully.');

    expect(User::where('role_id', $endUserRole->role_id)->count())->toBe(3);
});
