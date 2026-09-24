<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->endUserRole = Role::firstOrCreate(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $this->custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'report-custodian',
        'email' => 'report-custodian@test.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'report-end-user',
        'email' => 'report-end-user@test.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('a property custodian can view the interactive inventory report', function () {
    $response = $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.reports', [
            'date_from' => now()->subDays(7)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertViewIs('pages.propertyCustodian.reports');
    $response->assertSee('Operational inventory report');
    $response->assertSee('custodianReportMovementChart');
    $response->assertSee('Low-stock watchlist');
    $response->assertSee('Attention queue');
});

test('an end user cannot view the property custodian inventory report', function () {
    $response = $this->actingAs($this->endUser)->get(route('propertyCustodian.reports'));

    $response->assertForbidden();
});
