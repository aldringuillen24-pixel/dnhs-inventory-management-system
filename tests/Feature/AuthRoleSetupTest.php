<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('seeded administrator can sign in and is redirected to the admin dashboard', function () {
    Artisan::call('db:seed');

    $this->assertDatabaseHas('roles', ['role_name' => 'Administrator']);
    $this->assertDatabaseHas('roles', ['role_name' => 'School Head']);
    $this->assertDatabaseHas('roles', ['role_name' => 'Property Custodian']);
    $this->assertDatabaseHas('roles', ['role_name' => 'Inspector']);
    $this->assertDatabaseHas('roles', ['role_name' => 'End User']);

    $response = $this->post('/signin', [
        'username' => 'admin',
        'password' => 'admin123',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs(
        
        \App\Models\User::where('username', 'admin')->first()
    );
});
