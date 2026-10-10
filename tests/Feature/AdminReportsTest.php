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

test('an administrator can retrieve system reports data', function () {
    UserAuditLog::create([
        'user_id'     => $this->admin->id,
        'action'      => 'user_created',
        'actor_name'  => 'Admin User',
        'target_name' => 'Custodian User',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('api.admin.reports'));

    $response->assertOk()
        ->assertJsonStructure([
            'title',
            'metrics' => ['activeUsers', 'pendingOnboarding', 'pendingRequests', 'openMaintenance'],
            'roleData',
            'requestStatusData',
            'maintenanceStatusData',
            'recentActivity',
            'onboardingQueue',
        ])
        ->assertJsonPath('title', 'System Reports');
});

test('the reports payload lists pending onboarding oldest first with stale flags', function () {
    $fresh = User::create([
        'role_id' => $this->custodianRole->role_id,
        'first_name' => 'Fresh',
        'last_name' => 'Hire',
        'username' => 'fresh-hire',
        'email' => 'fresh@test.com',
        'password' => 'password',
        'status' => 'active',
        'temporary_password' => 'temp-123',
    ]);
    $stale = User::create([
        'role_id' => $this->custodianRole->role_id,
        'first_name' => 'Stale',
        'last_name' => 'Hire',
        'username' => 'stale-hire',
        'email' => 'stale@test.com',
        'password' => 'password',
        'status' => 'active',
        'temporary_password' => 'temp-456',
    ]);
    $stale->forceFill(['created_at' => now()->subDays(20)])->save();

    $payload = $this->actingAs($this->admin)->getJson(route('api.admin.reports'))
        ->assertOk()
        ->json();

    $queue = collect($payload['onboardingQueue']);
    expect($queue->pluck('username')->all())->toBe(['stale-hire', 'fresh-hire'])
        ->and($queue->firstWhere('username', 'stale-hire'))->toMatchArray([
            'role_name' => 'Property Custodian',
            'stale' => true,
        ])
        ->and($queue->firstWhere('username', 'fresh-hire')['stale'])->toBeFalse()
        ->and($queue->firstWhere('username', 'fresh-hire')['waiting_days'])->toBe(0);
});

test('an administrator can download the system pdf report', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.download'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('administrator-system-report.pdf');
});

test('a non-administrator cannot access the admin reports page', function () {
    $response = $this->actingAs($this->custodian)->getJson(route('api.admin.reports'));

    $response->assertForbidden();
});
