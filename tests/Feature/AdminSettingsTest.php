<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Transaction;
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

test('administrator can retrieve system settings data', function () {
    $this->actingAs($this->admin)
        ->getJson(route('api.admin.settings'))
        ->assertOk()
        ->assertJsonStructure([
            'systemDiagnostics',
            'inventoryPolicy',
            'securityPolicy',
            'aiConfig',
        ])
        ->assertJsonPath('inventoryPolicy.overdue_escalation_days', 7)
        ->assertJsonPath('inventoryPolicy.inventory_verification_interval_months', 1);
});

test('non-administrator cannot access system settings', function () {
    $this->actingAs($this->endUser)
        ->getJson(route('api.admin.settings'))
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
            'overdue_escalation_days' => 9,
            'inventory_verification_interval_months' => 3,
        ])
        ->assertRedirect(route('admin.settings', ['tab' => 'inventory']))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_policies', [
        'id' => 1,
        'low_stock_threshold' => 10,
        'require_serial_number' => true,
        'default_lifespan_years' => 7,
        'return_due_days' => 30,
        'auto_reminder_days' => 5,
        'overdue_escalation_days' => 9,
        'inventory_verification_interval_months' => 3,
    ]);
});

test('monthly inventory reminders are sent once to active property custodians', function () {
    $custodianRole = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'custodian',
        'email' => 'custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    \Carbon\Carbon::setTestNow('2026-03-03 01:00:00');

    try {
        $this->artisan('inventory:dispatch-policy-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->artisan('inventory:dispatch-policy-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $custodian->id,
            'type' => \App\Notifications\InventoryPolicyReminder::class,
        ]);
        $notificationData = $custodian->notifications()->firstOrFail()->data;
        $this->assertSame('Inventory verification reminder', $notificationData['title']);
        $this->assertSame('/inventory', $notificationData['url']);
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

test('overdue return reminders go to the issuer once the configured age is reached', function () {
    $category = Category::create(['category_name' => 'Reminder Test Equipment']);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'pieces',
        'user_id' => $this->admin->id,
        'item_name' => 'Reminder Test Projector',
        'quantity' => 2,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ]);

    foreach (['2026-02-22', '2026-02-23'] as $dueDate) {
        Transaction::create([
            'user_id' => $this->endUser->id,
            'from_user_id' => $this->admin->id,
            'item_id' => $item->item_id,
            'quantity' => 1,
            'transaction_date' => '2026-02-01',
            'expected_return_date' => $dueDate,
            'status' => 'assigned',
        ]);
    }

    \Carbon\Carbon::setTestNow('2026-03-01 01:00:00');

    try {
        $this->artisan('inventory:dispatch-policy-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->admin->id,
            'type' => \App\Notifications\InventoryPolicyReminder::class,
        ]);
        $notificationData = $this->admin->notifications()->firstOrFail()->data;
        $this->assertSame('/transactions', $notificationData['url']);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $this->endUser->id,
        ]);

        $this->artisan('inventory:dispatch-policy-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);
    } finally {
        \Carbon\Carbon::setTestNow();
    }
});

test('notifications are scoped to the signed-in user', function () {
    $otherUser = User::create([
        'role_id' => $this->endUserRole->role_id,
        'first_name' => 'Other',
        'last_name' => 'User',
        'username' => 'otheruser',
        'email' => 'otheruser@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->endUser->notify(new \App\Notifications\InventoryPolicyReminder(
        'Assigned item overdue',
        'An assigned item is overdue.',
        '/transactions',
    ));
    $otherUser->notify(new \App\Notifications\InventoryPolicyReminder(
        'Private notification',
        'Only the other user can read this.',
        '/inventory',
    ));
    $ownNotificationId = $this->endUser->notifications()->firstOrFail()->id;
    $otherNotificationId = $otherUser->notifications()->firstOrFail()->id;

    $this->actingAs($this->endUser)
        ->getJson(route('api.notifications.index'))
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonMissing(['title' => 'Private notification']);

    $this->patchJson(route('api.notifications.read', $otherNotificationId))->assertNotFound();
    $this->patchJson(route('api.notifications.read', $ownNotificationId))->assertOk();
    $this->assertDatabaseHas('notifications', [
        'id' => $ownNotificationId,
        'notifiable_id' => $this->endUser->id,
    ]);
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
