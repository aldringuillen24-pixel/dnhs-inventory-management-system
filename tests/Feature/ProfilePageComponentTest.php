<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

test('the reusable profile page component renders configurable profile content', function () {
    $user = User::factory()->make([
        'first_name' => 'Jamie',
        'last_name' => 'Rivera',
        'username' => 'jrivera',
        'email' => 'jamie@example.com',
    ]);

    $html = Blade::render(
        '<x-profile-page :user="$user" role-label="Inventory Manager" form-action="/profile/update" form-method="PATCH" />',
        compact('user'),
    );

    expect($html)
        ->toContain('Inventory Manager account')
        ->toContain('Jamie Rivera')
        ->toContain('jamie@example.com')
        ->toContain('action="/profile/update"')
        ->toContain('name="_method" value="PATCH"')
        ->toContain('Personal details')
        ->toContain('Security');
});

test('an authenticated property custodian can render and update the profile page', function () {
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
        ->assertOk()
        ->assertSee('Property Custodian account')
        ->assertSee('name="first_name"', false)
        ->assertSee('name="password_confirmation"', false);

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