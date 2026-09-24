<?php

use App\Models\Role;
use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole     = Role::firstOrCreate(['role_name' => 'Administrator']);
    $this->custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);

    $this->admin = User::create([
        'role_id'    => $this->adminRole->role_id,
        'first_name' => 'Admin',
        'last_name'  => 'User',
        'username'   => 'admin',
        'email'      => 'admin@test.com',
        'password'   => 'password',
        'status'     => 'active',
    ]);

    $this->custodian = User::create([
        'role_id'    => $this->custodianRole->role_id,
        'first_name' => 'Custodian',
        'last_name'  => 'User',
        'username'   => 'custodian',
        'email'      => 'custodian@test.com',
        'password'   => 'password',
        'status'     => 'active',
    ]);
});

test('an administrator can view the system reports page', function () {
    UserAuditLog::create([
        'user_id'     => $this->admin->id,
        'action'      => 'user_created',
        'actor_name'  => 'Admin User',
        'target_name' => 'Custodian User',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.reports'));

    $response->assertOk();
    $response->assertViewIs('pages.administrator.reports');
    $response->assertSee('System Reports');
    $response->assertSee('Active Users');
    $response->assertSee('Pending Setup');
    $response->assertSee('Pending Requests');
    $response->assertSee('Active Maintenance');
    $response->assertSee('Download PDF Report');
    $response->assertSee('Recent System Audit Activity');
    $response->assertSee('Manage Users');
});

test('an administrator can download the system pdf report', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.download'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('administrator-system-report.pdf');
});

test('a non-administrator cannot access the admin reports page', function () {
    $response = $this->actingAs($this->custodian)->get(route('admin.reports'));

    $response->assertForbidden();
});
