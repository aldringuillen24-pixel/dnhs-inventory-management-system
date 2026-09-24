<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole   = Role::firstOrCreate(['role_name' => 'Administrator']);
    $this->endUserRole = Role::firstOrCreate(['role_name' => 'End User']);

    $this->admin = User::create([
        'role_id'    => $this->adminRole->role_id,
        'first_name' => 'Admin',
        'last_name'  => 'User',
        'username'   => 'admin',
        'email'      => 'admin@example.com',
        'password'   => 'password',
        'status'     => 'active',
    ]);

    $this->endUser = User::create([
        'role_id'    => $this->endUserRole->role_id,
        'first_name' => 'End',
        'last_name'  => 'User',
        'username'   => 'enduser',
        'email'      => 'enduser@example.com',
        'password'   => 'password',
        'status'     => 'active',
    ]);
});

test('administrator can view system settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('System Settings')
        ->assertSee('Inventory Policies')
        ->assertSee('Security Defaults')
        ->assertSee('AI Configuration')
        ->assertSee('Diagnostics');
});

test('system settings page does not contain school profile tab', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.settings'));
    $response->assertOk();
    $response->assertDontSee('General & School Profile', false);
    $response->assertDontSee('School & Institutional Information', false);
});

test('non-administrator cannot access system settings', function () {
    $this->actingAs($this->endUser)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

test('administrator can update inventory policy settings', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.update'), [
            'section'                => 'inventory',
            'low_stock_threshold'    => 10,
            'require_serial_number'  => '1',
            'default_lifespan_years' => 7,
            'return_due_days'        => 30,
            'auto_reminder_days'     => 5,
        ])
        ->assertRedirect(route('admin.settings', ['tab' => 'inventory']))
        ->assertSessionHas('success');
});

test('administrator can update security policy settings', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.update'), [
            'section'                  => 'security',
            'default_password_pattern' => 'temporary_random',
            'otp_expiry_minutes'       => 10,
            'session_timeout_minutes'  => 120,
            'audit_retention_days'     => 365,
        ])
        ->assertRedirect(route('admin.settings', ['tab' => 'security']))
        ->assertSessionHas('success');
});

test('administrator can trigger cache clear action', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.clear-cache'), [
            'cache_type' => 'views',
        ])
        ->assertRedirect(route('admin.settings', ['tab' => 'diagnostics']))
        ->assertSessionHas('success');
});

test('administrator can ping the ai connection endpoint', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.settings.ping-ai'));

    $response->assertOk()
        ->assertJsonStructure(['ok', 'message']);
});
