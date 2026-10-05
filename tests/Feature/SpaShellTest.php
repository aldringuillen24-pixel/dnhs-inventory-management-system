<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create(['role_name' => 'Property Custodian']);

    $this->custodian = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'shell-custodian',
        'email' => 'shell-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('guests are redirected from the spa shell to sign in', function () {
    $this->get(route('spa'))->assertRedirect(route('login'));
});

test('authenticated users receive the spa shell', function () {
    $this->actingAs($this->custodian)
        ->get(route('spa'))
        ->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertSee('/build/assets/', false);
});

test('spa history subpaths resolve to the shell for the client router', function () {
    $this->actingAs($this->custodian)
        ->get('/spa/inventory')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
});

test('inspector receives the read-only inventory and reports SPA pages', function () {
    $inspectorRole = Role::create(['role_name' => 'Inspector']);
    $inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'Inventory',
        'last_name' => 'Inspector',
        'username' => 'shell-inspector',
        'email' => 'shell-inspector@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($inspector)
        ->get('/spa/inspector/inventory')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);

    $this->actingAs($inspector)
        ->get('/spa/inspector/reports')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);

    $this->actingAs($inspector)
        ->get('/')
        ->assertRedirect('/spa/inspector/inventory');

    $this->actingAs($this->custodian)
        ->get('/spa/inspector/inventory')
        ->assertForbidden();
});

test('role-protected app pages redirect into the Vue shell', function () {
    $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.dashboard'))
        ->assertRedirect('/spa/dashboard');

    $schoolHeadRole = Role::create(['role_name' => 'School Head']);
    $schoolHead = User::create([
        'role_id' => $schoolHeadRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Head',
        'username' => 'shell-school-head',
        'email' => 'shell-school-head@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $endUserRole = Role::create(['role_name' => 'End User']);
    $endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'shell-end-user',
        'email' => 'shell-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $adminRole = Role::create(['role_name' => 'Administrator']);
    $admin = User::create([
        'role_id' => $adminRole->role_id,
        'first_name' => 'System',
        'last_name' => 'Admin',
        'username' => 'shell-admin',
        'email' => 'shell-admin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($schoolHead)
        ->get(route('schoolHead.dashboard'))
        ->assertRedirect('/spa/school-head/dashboard');
    $this->actingAs($endUser)
        ->get(route('endUser.dashboard'))
        ->assertRedirect('/spa/end-user/dashboard');
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect('/spa/admin/dashboard');
});

test('role middleware still blocks app pages before the Vue shell redirect', function () {
    $this->actingAs($this->custodian)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($this->custodian)
        ->get('/spa/admin/dashboard')
        ->assertForbidden();
});
