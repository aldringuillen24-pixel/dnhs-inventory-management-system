<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

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