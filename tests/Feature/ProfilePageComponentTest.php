<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('an authenticated property custodian is directed to the SPA profile and can update it', function () {
    $role = Role::create(['role_name' => 'Property Custodian']);
    $user = User::factory()->create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'property-custodian',
        'email' => 'custodian@example.com',
    ]);

    $this->actingAs($user);

    $this->get(route('propertyCustodian.profile'))
        ->assertRedirect('/spa/profile');

    $this->patch(route('propertyCustodian.profile.update'), [
        'first_name' => 'Updated',
        'last_name' => 'Custodian',
        'username' => 'updated-custodian',
        'email' => 'updated@example.com',
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('propertyCustodian.profile'))
        ->assertSessionHas('success', 'Profile updated successfully.');

    expect($user->fresh())
        ->first_name->toBe('Updated')
        ->username->toBe('updated-custodian')
        ->email->toBe('updated@example.com');
});

test('an authenticated inspector is directed to the SPA profile and can update it', function () {
    $role = Role::create(['role_name' => 'Inspector']);
    $user = User::factory()->create([
        'role_id' => $role->role_id,
        'first_name' => 'Inventory',
        'last_name' => 'Inspector',
        'username' => 'inspector',
        'email' => 'inspector@example.com',
    ]);

    $this->actingAs($user);

    $this->get(route('inspector.profile'))
        ->assertRedirect('/spa/profile');

    $this->patch(route('inspector.profile.update'), [
        'first_name' => 'Updated',
        'last_name' => 'Inspector',
        'username' => 'updated-inspector',
        'email' => 'updated-inspector@example.com',
        'current_password' => '',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('inspector.profile'))
        ->assertSessionHas('success', 'Profile updated successfully.');

    expect($user->fresh())
        ->first_name->toBe('Updated')
        ->username->toBe('updated-inspector')
        ->email->toBe('updated-inspector@example.com');
});

test('changing password does not require or modify profile information', function () {
    $role = Role::create(['role_name' => 'End User']);
    $user = User::factory()->create([
        'role_id' => $role->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'password-only-user',
        'email' => 'password-only@example.com',
        'building' => 'Main Building',
        'room' => 'Room 12',
        'password' => 'original-password',
    ]);

    $this->actingAs($user)
        ->patchJson(route('api.profile.password'), [
            'password' => 'updated-password-123',
            'password_confirmation' => 'updated-password-123',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $updatedUser = $user->fresh();
    expect($updatedUser)
        ->first_name->toBe('End')
        ->last_name->toBe('User')
        ->username->toBe('password-only-user')
        ->email->toBe('password-only@example.com')
        ->building->toBe('Main Building')
        ->room->toBe('Room 12')
        ->and(Hash::check('updated-password-123', $updatedUser->password))->toBeTrue();
});