<?php

use App\Mail\IncidentReportUpdateMail;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\IncidentReporterNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

test('creating an incident event sends one reporter email after commit', function () {
    Schema::create('roles', function (Blueprint $table): void {
        $table->id('role_id');
        $table->string('role_name');
        $table->timestamps();
    });
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('role_id');
        $table->string('first_name');
        $table->string('last_name');
        $table->string('username');
        $table->string('email');
        $table->string('password');
        $table->string('status');
        $table->timestamps();
    });
    Schema::create('categories', function (Blueprint $table): void {
        $table->id('category_id');
        $table->string('category_name');
        $table->boolean('requires_serial_number')->default(false);
        $table->boolean('requires_qr_code')->default(false);
        $table->boolean('is_maintenance_eligible')->default(false);
        $table->timestamps();
    });
    Schema::create('stock_movements', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('inventory_id');
        $table->text('notes')->nullable();
    });
    Schema::create('inventory', function (Blueprint $table): void {
        $table->id('item_id');
        $table->unsignedBigInteger('category_id');
        $table->string('unit');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('assigned_to_user_id')->nullable();
        $table->string('item_name');
        $table->unsignedInteger('quantity');
        $table->decimal('unit_cost', 12, 2);
        $table->string('status');
        $table->date('date_acquired')->nullable();
        $table->timestamps();
    });
    Schema::create('maintenance_records', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('inventory_id');
        $table->unsignedInteger('quantity');
        $table->unsignedBigInteger('reported_by');
        $table->string('status');
        $table->string('diagnosis');
        $table->text('issue_description');
        $table->string('incident_status')->nullable();
        $table->string('incident_outcome')->nullable();
        $table->timestamps();
    });
    Schema::create('maintenance_record_events', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('maintenance_record_id');
        $table->unsignedBigInteger('actor_id')->nullable();
        $table->string('event_type');
        $table->string('from_status')->nullable();
        $table->string('to_status')->nullable();
        $table->text('details')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
    Schema::create('incident_notification_deliveries', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('event_id')->unique();
        $table->unsignedBigInteger('reporter_id');
        $table->string('status', 16);
        $table->timestamp('sent_at')->nullable();
        $table->text('error')->nullable();
        $table->timestamps();
    });

    Mail::fake();

    $role = Role::create(['role_name' => 'End User']);
    $reporter = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Timeline',
        'last_name' => 'Reporter',
        'username' => 'timeline-reporter',
        'email' => 'timeline-reporter@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $category = Category::create([
        'category_name' => 'Notification Test Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $reporter->id,
        'item_name' => 'Notification Test Chair',
        'quantity' => 1,
        'unit_cost' => 1000,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
    ]);
    $report = MaintenanceRecord::create([
        'inventory_id' => $inventory->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'status' => 'reported',
        'diagnosis' => 'damaged',
        'issue_description' => 'The chair leg is cracked.',
    ]);

    DB::transaction(function () use ($report, $reporter): void {
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $reporter->id,
            'event_type' => 'report_submitted',
            'to_status' => 'reported',
            'details' => 'Incident report submitted.',
        ]);

        Mail::assertNothingSent();
    });

    Mail::assertSent(IncidentReportUpdateMail::class, fn (IncidentReportUpdateMail $mail): bool =>
        $mail->hasTo($reporter->email)
        && $mail->eventTitle === 'Report received'
        && $mail->itemName === 'Notification Test Chair'
        && $mail->reportUrl === url('/spa/end-user/assigned')
    );

    $event = MaintenanceRecordEvent::query()->firstOrFail();
    app(IncidentReporterNotificationService::class)->notify($event);

    Mail::assertSent(IncidentReportUpdateMail::class, 1);
    expect(DB::table('incident_notification_deliveries')
        ->where('event_id', $event->id)
        ->where('status', 'sent')
        ->count())->toBe(1);
});
