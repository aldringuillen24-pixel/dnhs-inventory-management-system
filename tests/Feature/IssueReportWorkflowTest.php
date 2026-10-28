<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecordEvent;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Mail\IncidentReportUpdateMail;
use App\Services\IncidentReporterNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'issue-custodian',
        'email' => 'issue-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'issue-end-user',
        'email' => 'issue-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->category = Category::create([
        'category_name' => 'Issue Report Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
});

function makeIssuedItem($test, string $name, int $quantity = 1): Inventory
{
    $item = Inventory::create([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => 0,
        'unit_cost' => 1000,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
        'assigned_to_user_id' => $test->endUser->id,
        'serial_number' => 'SN-'.uniqid(),
    ]);

    Transaction::create([
        'user_id' => $test->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => $quantity,
        'issued_quantity' => $quantity,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);

    return $item;
}

test('end user can report a damaged assigned item without moving stock', function () {
    $item = makeIssuedItem($this, 'Reported Chair', 3);
    $movementsBefore = StockMovement::where('inventory_id', $item->item_id)->count();

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 2,
            'notes' => 'Leg cracked',
        ])
        ->assertRedirect(route('endUser.my-assigned-items'))
        ->assertSessionHas('success', 'Issue reported for custodian triage.');

    $this->assertDatabaseHas('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
        'status' => 'reported',
        'incident_status' => 'reported',
        'diagnosis' => 'damaged',
        'quantity' => 2,
        'repair_return_destination' => 'custodian_available',
    ]);
    $this->assertDatabaseHas('maintenance_record_events', [
        'event_type' => 'report_submitted',
        'actor_id' => $this->endUser->id,
        'to_status' => 'reported',
    ]);

    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and(StockMovement::where('inventory_id', $item->item_id)->count())->toBe($movementsBefore);
});

test('consumable items cannot be reported as damaged', function () {
    $this->category = Category::firstOrCreate(
        ['category_name' => 'Consumables'],
        [
            'requires_serial_number' => false,
            'is_maintenance_eligible' => false,
        ],
    );
    $item = makeIssuedItem($this, 'Consumable Pens', 2);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => Transaction::where('item_id', $item->item_id)->value('id'),
        'quantity' => 2,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonFragment([
            'item_id' => $item->item_id,
            'is_consumable' => true,
        ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'One pen is broken.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');

    $this->assertDatabaseMissing('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
    ]);
});

test('end user cannot submit a repair report for a category that is not maintenance eligible', function () {
    $item = makeIssuedItem($this, 'Consumable Supply', 3);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => Transaction::where('item_id', $item->item_id)->value('id'),
        'quantity' => 3,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonFragment([
            'item_id' => $item->item_id,
            'maintenance_eligible' => false,
        ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 1,
            'notes' => 'This supply needs repair.',
        ])
        ->assertSessionHasErrors('type');

    $this->assertDatabaseMissing('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
    ]);
});

test('end user can submit a repair report for a maintenance eligible category', function () {
    $category = Category::create([
        'category_name' => 'Repairable Equipment',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $this->category = $category;
    $item = makeIssuedItem($this, 'Repairable Chair', 1);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => Transaction::where('item_id', $item->item_id)->value('id'),
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonFragment([
            'item_id' => $item->item_id,
            'maintenance_eligible' => true,
        ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 1,
            'notes' => 'The chair has a broken leg.',
            'repair_return_destination' => 'assigned_user',
        ])
        ->assertRedirect(route('endUser.my-assigned-items'))
        ->assertSessionHas('success', 'Issue reported for custodian triage.');

    $this->assertDatabaseHas('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
        'diagnosis' => 'needs_repair',
        'repair_return_destination' => 'assigned_user',
    ]);
});

test('repaired incident units can be assigned back to the reporting user when requested', function () {
    Mail::fake();
    $this->category->update(['is_maintenance_eligible' => true]);
    $item = makeIssuedItem($this, 'Return After Repair Chair', 2);
    $transaction = Transaction::where('item_id', $item->item_id)->firstOrFail();
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => $transaction->id,
        'quantity' => 2,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 1,
            'notes' => 'One chair has a cracked support.',
            'repair_return_destination' => 'assigned_user',
        ])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'The support can be replaced.',
            'repairability' => 'repairable',
        ])
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received one chair for repair.',
        ])
        ->assertOk()
        ->assertJsonPath('incident_status', 'processing_repair');

    $handover = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'incident_handover_confirmed')
        ->firstOrFail();
    $processingInventoryId = (int) $handover->metadata['processing_inventory_id'];

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/inventory/{$processingInventoryId}/mark-repaired", [
            'repair_notes' => 'Replaced the cracked support and checked stability.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $repairedItem = Inventory::findOrFail($processingInventoryId);
    $returnTransaction = Transaction::query()
        ->where('item_id', $processingInventoryId)
        ->where('user_id', $this->endUser->id)
        ->where('status', 'assigned')
        ->firstOrFail();
    $this->assertDatabaseHas('requests', [
        'item_id' => $processingInventoryId,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => $returnTransaction->id,
        'quantity' => 1,
        'status' => 'approved',
    ]);

    expect($repairedItem->status)->toBe('assigned')
        ->and((int) $repairedItem->assigned_to_user_id)->toBe((int) $this->endUser->id)
        ->and((int) $repairedItem->quantity)->toBe(0)
        ->and((int) $returnTransaction->quantity)->toBe(1)
        ->and($report->fresh()->incident_status)->toBe('resolved')
        ->and($report->fresh()->incident_outcome)->toBe('repaired');

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonFragment([
            'item_id' => $processingInventoryId,
            'quantity' => 1,
        ])
        ->assertJsonFragment([
            'repair_return_destination' => 'assigned_user',
        ]);
});

test('incident updates email the reporter once per event with a link to their reports', function () {
    Mail::fake();
    $item = makeIssuedItem($this, 'Notification Timeline Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'stolen',
            'quantity' => 1,
            'notes' => 'One chair is missing from the assigned room.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $notificationService = app(IncidentReporterNotificationService::class);
    $events = collect([
        MaintenanceRecordEvent::where('maintenance_record_id', $report->id)->where('event_type', 'report_submitted')->firstOrFail(),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'reported',
            'to_status' => 'awaiting_reporter',
            'details' => 'Please confirm the last known location.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'awaiting_reporter',
            'to_status' => 'under_review',
            'details' => 'The report was accepted for review.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'reported',
            'to_status' => 'declined',
            'details' => 'The report could not be verified.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'under_review',
            'to_status' => 'under_investigation',
            'details' => 'The investigation has started.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'under_review',
            'to_status' => 'processing_repair',
            'details' => 'The affected unit is being repaired.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'under_review',
            'to_status' => 'processing_disposal',
            'details' => 'The affected unit is under disposal review.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'investigation_action_recorded',
            'details' => 'Checked the last known room.',
        ]),
        MaintenanceRecordEvent::create([
            'maintenance_record_id' => $report->id,
            'actor_id' => $this->custodian->id,
            'event_type' => 'status_changed',
            'from_status' => 'under_investigation',
            'to_status' => 'resolved',
            'details' => 'The item was recovered.',
        ]),
    ]);

    foreach ($events as $event) {
        $notificationService->notify($event);
    }
    $notificationService->notify($events->first());

    Mail::assertSent(IncidentReportUpdateMail::class, 9);
    Mail::assertSent(IncidentReportUpdateMail::class, fn (IncidentReportUpdateMail $mail): bool =>
        $mail->hasTo($this->endUser->email)
        && $mail->eventTitle === 'More information requested'
        && $mail->updateMessage === 'Please confirm the last known location.'
        && $mail->reportUrl === url('/spa/end-user/assigned')
    );
    Mail::assertSent(IncidentReportUpdateMail::class, fn (IncidentReportUpdateMail $mail): bool =>
        $mail->eventTitle === 'Investigation update'
        && $mail->updateMessage === 'Checked the last known room.'
    );
    foreach ([
        'Report accepted for review',
        'Report declined',
        'Investigation started',
        'Repair started',
        'Disposal review started',
        'Report resolved',
    ] as $title) {
        Mail::assertSent(IncidentReportUpdateMail::class, fn (IncidentReportUpdateMail $mail): bool => $mail->eventTitle === $title);
    }
    expect(\Illuminate\Support\Facades\DB::table('incident_notification_deliveries')
        ->where('reporter_id', $this->endUser->id)
        ->where('status', 'sent')
        ->count())->toBe(9);
});

test('issue report quantity above the assigned quantity is rejected', function () {
    $item = makeIssuedItem($this, 'Over Reported Chair', 2);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 5,
            'notes' => 'Two chairs are missing.',
        ])
        ->assertSessionHas('error', 'Report quantity must be between 1 and the assigned quantity.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(0);
});

test('duplicate open issue reports are rejected', function () {
    $item = makeIssuedItem($this, 'Twice Reported Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged', 'quantity' => 1, 'notes' => 'A leg is broken.'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'lost', 'quantity' => 1, 'notes' => 'The item is missing.'])
        ->assertSessionHas('error', 'You already have an open report for this item.');
});

test('failed reporter email delivery does not fail issue report submission', function () {
    $item = makeIssuedItem($this, 'Email Failure Chair', 1);
    Mail::shouldReceive('to')
        ->once()
        ->with($this->endUser->email)
        ->andThrow(new RuntimeException('SMTP unavailable'));

    $response = $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The chair frame is cracked.',
        ]);

    $response->assertOk()->assertJson([
        'status' => 'success',
        'message' => 'Issue reported for custodian triage.',
    ]);

    $event = MaintenanceRecordEvent::where('event_type', 'report_submitted')->firstOrFail();
    app(IncidentReporterNotificationService::class)->notify($event);

    $this->assertDatabaseHas('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->endUser->id,
        'incident_status' => 'reported',
    ]);
    expect(\Illuminate\Support\Facades\DB::table('incident_notification_deliveries')
        ->where('event_id', $event->id)
        ->where('status', 'failed')
        ->where('error', 'SMTP unavailable')
        ->exists())->toBeTrue();

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(1);
});

test('assigned items expose an open report and duplicate report API returns a clear validation response', function () {
    $item = makeIssuedItem($this, 'Open Report Chair', 2);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'A leg is broken.',
        ])
        ->assertRedirect(route('endUser.my-assigned-items'));

    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => Transaction::where('item_id', $item->item_id)->value('id'),
        'quantity' => 2,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('activityRows.0.has_open_report', true)
        ->assertJsonPath('activityRows.0.quantity', 1);

    expect((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(2)
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 1,
            'notes' => 'The item is missing.',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You already have an open report for this item.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(1);
});

test('non-holders cannot report issues on items', function () {
    $item = makeIssuedItem($this, 'Stranger Reported Chair', 1);

    $stranger = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Stranger',
        'last_name' => 'User',
        'username' => 'issue-stranger',
        'email' => 'issue-stranger@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->actingAs($stranger)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged', 'quantity' => 1, 'notes' => 'A leg is broken.'])
        ->assertSessionHas('error', 'You can only report issues on items assigned to you.');

    expect(\App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->count())->toBe(0);
});

test('custodian can decline an issue report', function () {
    $item = makeIssuedItem($this, 'Declined Report Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'lost', 'quantity' => 1, 'notes' => 'The item was taken.'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $record = \App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $this->actingAs($this->custodian)
        ->post(route('propertyCustodian.maintenance.decline', $record->id), ['reason' => 'Report details could not be verified.'])
        ->assertRedirect(route('propertyCustodian.inventory'))
        ->assertSessionHas('success', 'Issue report declined.');

    expect($record->fresh()->status)->toBe('cancelled')
        ->and($record->fresh()->incident_status)->toBe('declined');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $record->id,
        'event_type' => 'status_changed',
        'from_status' => 'reported',
        'to_status' => 'declined',
        'details' => 'Report details could not be verified.',
    ]);

    $this->actingAs($this->custodian)
        ->post(route('propertyCustodian.maintenance.decline', $record->id))
        ->assertSessionHas('error', 'This report has already been processed.');
});

test('incident transitions require reasons and a valid final outcome', function () {
    $item = makeIssuedItem($this, 'Lifecycle Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged', 'quantity' => 1, 'notes' => 'A leg is broken.'])
        ->assertRedirect(route('endUser.my-assigned-items'));
    $record = \App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->firstOrFail();
    $lifecycle = app(\App\Services\IncidentLifecycleService::class);

    expect(fn () => $lifecycle->transition($record, 'declined', $this->custodian->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $reviewing = $lifecycle->transition($record, 'under_review', $this->custodian->id);
    expect($reviewing->incident_status)->toBe('under_review')
        ->and($reviewing->status)->toBe('in_progress');

    expect(fn () => $lifecycle->transition($reviewing, 'resolved', $this->custodian->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $resolved = $lifecycle->transition(
        $reviewing,
        'resolved',
        $this->custodian->id,
        null,
        ['outcome' => 'repaired'],
    );

    expect($resolved->incident_status)->toBe('resolved')
        ->and($resolved->incident_outcome)->toBe('repaired')
        ->and($resolved->status)->toBe('completed');
});

test('incident quantity validation uses the reporter active assignment quantity', function () {
    $item = makeIssuedItem($this, 'Quantity Guard Chair', 2);
    $transaction = Transaction::where('item_id', $item->item_id)->firstOrFail();
    $lifecycle = app(\App\Services\IncidentLifecycleService::class);

    $lifecycle->assertAffectedQuantity($item->item_id, $this->endUser->id, 2, $transaction->id);

    expect(fn () => $lifecycle->assertAffectedQuantity($item->item_id, $this->endUser->id, 3, $transaction->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($lifecycle->currentAssignedQuantity($item->item_id, $this->custodian->id))->toBe(0);
});

test('dashboard condition data shows reported damage while assigned', function () {
    $item = makeIssuedItem($this, 'Condition Report Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged', 'quantity' => 1, 'notes' => 'A leg is broken.'])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $conditions = collect(
        $this->actingAs($this->endUser)
            ->getJson(route('api.end-user.dashboard'))
            ->assertOk()
            ->json('conditionData')
    );

    expect($conditions->firstWhere('label', 'Damaged')['value'] ?? 0)->toBe(1);
});

test('end user can submit each supported incident type with required details', function () {
    foreach (['damaged', 'needs_repair', 'lost', 'stolen'] as $type) {
        $this->category->update(['is_maintenance_eligible' => $type === 'needs_repair']);
        $item = makeIssuedItem($this, "Reported {$type}", 2);

        $this->actingAs($this->endUser)
            ->post(route('endUser.inventory.report', $item->item_id), [
                'type' => $type,
                'quantity' => 1,
                'notes' => "Incident details for {$type}.",
            ])
            ->assertRedirect(route('endUser.my-assigned-items'));

        $this->assertDatabaseHas('maintenance_records', [
            'inventory_id' => $item->item_id,
            'reported_by' => $this->endUser->id,
            'diagnosis' => $type,
            'quantity' => 1,
            'incident_status' => 'reported',
        ]);
    }
});

test('incident report details are required and evidence is stored privately with metadata', function () {
    $item = makeIssuedItem($this, 'Evidence Chair', 1);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The incident evidence is invalid.',
            'evidence' => UploadedFile::fake()->create('program.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors(['evidence']);
    expect(\App\Models\MaintenanceRecord::count())->toBe(0);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The evidence file is too large.',
            'evidence' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['evidence']);
    expect(\App\Models\MaintenanceRecord::count())->toBe(0);

    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), ['type' => 'damaged', 'quantity' => 1])
        ->assertSessionHasErrors(['notes']);
    expect(\App\Models\MaintenanceRecord::count())->toBe(0);

    Storage::fake('local');
    $this->category->update(['is_maintenance_eligible' => true]);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 1,
            'notes' => 'The motor makes a grinding noise.',
            'evidence' => UploadedFile::fake()->image('motor.png'),
        ])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $evidence = \App\Models\MaintenanceRecordEvidence::firstOrFail();
    expect($evidence->disk)->toBe('local')
        ->and($evidence->original_filename)->toBe('motor.png')
        ->and($evidence->mime_type)->toBe('image/png');
    Storage::disk('local')->assertExists($evidence->path);
});

test('reporter sees only their incident reports and current lifecycle status', function () {
    $item = makeIssuedItem($this, 'Reporter Visibility Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'stolen',
            'quantity' => 1,
            'notes' => 'The item is missing from the assigned room.',
        ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('incidentReports.0.type', 'stolen')
        ->assertJsonPath('incidentReports.0.status', 'reported')
        ->assertJsonPath('incidentReports.0.details', 'The item is missing from the assigned room.');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => \App\Models\MaintenanceRecord::firstOrFail()->id,
        'actor_id' => $this->endUser->id,
        'event_type' => 'report_submitted',
        'created_at' => \App\Models\MaintenanceRecordEvent::firstOrFail()->created_at,
    ]);

    $stranger = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Other',
        'last_name' => 'Reporter',
        'username' => 'issue-other-reporter',
        'email' => 'issue-other-reporter@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->actingAs($stranger)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonCount(0, 'incidentReports');
});

test('reporter can cancel a blotter report before custodian review', function () {
    $item = makeIssuedItem($this, 'Cancellable Blotter Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'A leg is cracked.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.incident-reports.cancel', $report->id))
        ->assertOk()
        ->assertJsonPath('incident_status', 'cancelled');

    $this->assertDatabaseHas('maintenance_records', [
        'id' => $report->id,
        'reported_by' => $this->endUser->id,
        'status' => 'cancelled',
        'incident_status' => 'cancelled',
    ]);
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->endUser->id,
        'event_type' => 'report_cancelled',
        'from_status' => 'reported',
        'to_status' => 'cancelled',
    ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('incidentReports.0.status', 'cancelled')
        ->assertJsonPath('incidentReports.0.timeline.1.event_type', 'report_cancelled');
});

test('reporter cannot cancel another user report or a report already under review', function () {
    $item = makeIssuedItem($this, 'Protected Blotter Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'A leg is cracked.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $otherUser = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Other',
        'last_name' => 'Reporter',
        'username' => 'issue-cancel-other',
        'email' => 'issue-cancel-other@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->actingAs($otherUser)
        ->postJson(route('api.end-user.incident-reports.cancel', $report->id))
        ->assertNotFound();

    $report->forceFill([
        'incident_status' => 'under_review',
        'status' => 'in_progress',
    ])->save();
    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.incident-reports.cancel', $report->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('report');

    expect($report->fresh()->incident_status)->toBe('under_review');
});

test('reporter and custodian can download evidence but other users cannot', function () {
    Storage::fake('local');
    $item = makeIssuedItem($this, 'Evidence Access Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The chair back is cracked.',
            'evidence' => UploadedFile::fake()->image('chair.png'),
        ])
        ->assertRedirect(route('endUser.my-assigned-items'));

    $evidence = \App\Models\MaintenanceRecordEvidence::firstOrFail();
    $evidenceUrl = route('api.incident-evidence.download', $evidence->id);

    $this->actingAs($this->endUser)
        ->get($evidenceUrl)
        ->assertDownload('chair.png');

    $this->actingAs($this->custodian)
        ->get($evidenceUrl)
        ->assertDownload('chair.png');

    $stranger = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Evidence',
        'last_name' => 'Stranger',
        'username' => 'issue-evidence-stranger',
        'email' => 'issue-evidence-stranger@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->actingAs($stranger)->get($evidenceUrl)->assertForbidden();

    auth()->logout();
    $this->get($evidenceUrl)->assertRedirect();
});

test('evidence response metadata does not expose private storage paths', function () {
    Storage::fake('local');
    $item = makeIssuedItem($this, 'Evidence Metadata Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The chair back is cracked.',
            'evidence' => UploadedFile::fake()->image('chair.png'),
        ]);

    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('incidentReports.0.evidence.0.filename', 'chair.png')
        ->assertJsonPath('incidentReports.0.evidence.0.download_url', route(
            'api.incident-evidence.download',
            \App\Models\MaintenanceRecordEvidence::firstOrFail()->id,
        ))
        ->assertJsonMissingPath('incidentReports.0.evidence.0.path')
        ->assertJsonMissingPath('incidentReports.0.evidence.0.disk');
});

test('custodian incident queue lists open reports and is role restricted', function () {
    $item = makeIssuedItem($this, 'Custodian Queue Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The back support is cracked.',
        ]);

    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonCount(1, 'reports')
        ->assertJsonPath('reports.0.id', $report->id)
        ->assertJsonPath('reports.0.item.name', 'Custodian Queue Chair')
        ->assertJsonPath('reports.0.reporter.name', 'End User')
        ->assertJsonPath('reports.0.quantity', 1)
        ->assertJsonPath('reports.0.status', 'reported')
        ->assertJsonPath('reports.0.details', 'The back support is cracked.')
        ->assertJsonPath('reports.0.timeline.0.event_type', 'report_submitted')
        ->assertJsonPath('reports.0.timeline.0.actor_id', $this->endUser->id)
        ->assertJsonPath('reports.0.timeline.0.metadata.inventory_id', $item->item_id)
        ->assertJsonPath('reports.0.timeline.0.metadata.assignment_transaction_id', Transaction::where('item_id', $item->item_id)->value('id'));

    $this->actingAs($this->endUser)
        ->getJson('/api/custodian/incident-reports')
        ->assertForbidden();
});

test('reporter can respond to an information request and see the append-only attributed timeline', function () {
    $item = makeIssuedItem($this, 'Information Response Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 1,
            'notes' => 'One chair is missing from the assigned room.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/request-information", [
            'reason' => 'Please confirm the last known location.',
        ])
        ->assertOk();

    $requestEvent = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('to_status', 'awaiting_reporter')
        ->firstOrFail();

    $stranger = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Other',
        'last_name' => 'Reporter',
        'username' => 'issue-response-stranger',
        'email' => 'issue-response-stranger@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $this->actingAs($stranger)
        ->postJson("/api/end-user/incident-reports/{$report->id}/information-response", [
            'details' => 'The chair was last seen in the library.',
        ])
        ->assertNotFound();

    $this->actingAs($this->endUser)
        ->postJson("/api/end-user/incident-reports/{$report->id}/information-response", [
            'details' => 'It was last seen in the library yesterday afternoon.',
        ])
        ->assertOk()
        ->assertJsonPath('incident_status', 'reported');

    expect($report->fresh()->incident_status)->toBe('reported')
        ->and($requestEvent->fresh()->details)->toBe('Please confirm the last known location.');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->endUser->id,
        'event_type' => 'reporter_response_received',
        'from_status' => 'awaiting_reporter',
        'to_status' => 'reported',
        'details' => 'It was last seen in the library yesterday afternoon.',
    ]);

    $timeline = $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('incidentReports.0.timeline.1.event_type', 'status_changed')
        ->assertJsonPath('incidentReports.0.timeline.1.actor_id', $this->custodian->id)
        ->assertJsonPath('incidentReports.0.timeline.2.event_type', 'reporter_response_received')
        ->assertJsonPath('incidentReports.0.timeline.2.actor', 'End User')
        ->assertJsonPath('incidentReports.0.timeline.2.details', 'It was last seen in the library yesterday afternoon.');

    expect($timeline->json('incidentReports.0.timeline.2.created_at'))->not->toBeNull()
        ->and(\App\Models\MaintenanceRecordEvent::where('maintenance_record_id', $report->id)->count())->toBe(3);

    $responseEvent = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'reporter_response_received')
        ->firstOrFail();
    expect(fn () => $responseEvent->update(['details' => 'Edited response']))
        ->toThrow(\LogicException::class);
});

test('custodian can accept a report without changing assignment or inventory quantities', function () {
    $this->category->update(['is_maintenance_eligible' => true]);
    $item = makeIssuedItem($this, 'Accepted Incident Chair', 3);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 2,
            'notes' => 'The chair mechanism is stuck.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $eventCount = MaintenanceRecordEvent::where('maintenance_record_id', $report->id)->count();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk()
        ->assertJsonPath('message', 'Incident is already accepted for custodian review.');

    expect($report->fresh()->incident_status)->toBe('under_review')
        ->and($report->fresh()->status)->toBe('in_progress')
        ->and(MaintenanceRecordEvent::where('maintenance_record_id', $report->id)->count())->toBe($eventCount)
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(3)
        ->and(Transaction::where('item_id', $item->item_id)->value('status'))->toBe('assigned');

    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'from_status' => 'reported',
        'to_status' => 'under_review',
    ]);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk()
        ->assertJsonPath('message', 'Incident is already accepted for custodian review.');
    expect(\App\Models\MaintenanceRecordEvent::where('maintenance_record_id', $report->id)->count())->toBe(2);
});

test('custodian must provide a reason when requesting information or declining', function () {
    $item = makeIssuedItem($this, 'Information Request Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'stolen',
            'quantity' => 1,
            'notes' => 'The chair is missing.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/request-information")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/request-information", [
            'reason' => 'Please confirm the last known location.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($report->fresh()->incident_status)->toBe('awaiting_reporter');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'to_status' => 'awaiting_reporter',
        'details' => 'Please confirm the last known location.',
    ]);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/maintenance/{$report->id}/decline")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/maintenance/{$report->id}/decline", [
            'reason' => 'The report could not be verified.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($report->fresh()->incident_status)->toBe('declined');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'to_status' => 'declined',
        'details' => 'The report could not be verified.',
    ]);
});

test('inspection is audited without changing inventory or assignment quantities', function () {
    $item = makeIssuedItem($this, 'Inspected Incident Chair', 3);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 2,
            'notes' => 'Two chairs have damaged supports.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received the two affected units.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['inspection']);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'The support braces need a specialist assessment.',
            'repairability' => 'pending',
        ])
        ->assertOk()
        ->assertJsonPath('inspection.repairability', 'pending');
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received the two affected units.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['inspection']);

    expect($report->fresh()->incident_status)->toBe('under_review')
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(3);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'Two affected chairs have cracked support braces.',
            'repairability' => 'repairable',
        ])
        ->assertOk()
        ->assertJsonPath('inspection.repairability', 'repairable');
    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.inspection.findings', 'Two affected chairs have cracked support braces.');

    expect($report->fresh()->incident_status)->toBe('under_review')
        ->and($report->fresh()->incident_outcome)->toBeNull()
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(3)
        ->and(Transaction::where('item_id', $item->item_id)->value('status'))->toBe('assigned');

    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'event_type' => 'physical_inspection_recorded',
        'details' => 'Two affected chairs have cracked support braces.',
    ]);

    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(3);
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'event_type' => 'incident_processing_blocked',
        'details' => 'Record a complete physical inspection before confirming handover.',
    ]);
});

test('partial handover isolates only affected units and repair completion resolves the report', function () {
    $this->category->update(['is_maintenance_eligible' => true]);
    $item = makeIssuedItem($this, 'Partially Affected Chair', 3);
    $transaction = Transaction::where('item_id', $item->item_id)->firstOrFail();
    $assignment = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => $transaction->id,
        'quantity' => 3,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 2,
            'notes' => 'Two chairs have loose supports.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'Two units need their support brackets replaced.',
            'repairability' => 'repairable',
        ])
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received two chairs from the assigned user.',
        ])
        ->assertOk()
        ->assertJsonPath('incident_status', 'processing_repair');

    $report->refresh();
    $handover = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'incident_handover_confirmed')
        ->firstOrFail();
    expect($handover->metadata['source_inventory_id'])->toBe((int) $item->item_id)
        ->and($handover->metadata['assignment_transaction_id'])->toBe((int) Transaction::where('item_id', $item->item_id)->value('id'))
        ->and($handover->metadata['assignment_request_id'])->toBe((int) $assignment->id)
        ->and($handover->metadata['assignment_return_id'])->toBeGreaterThan(0);
    $processingInventoryId = (int) $handover->metadata['processing_inventory_id'];
    $processingInventory = Inventory::findOrFail($processingInventoryId);
    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.handover.confirmed', true)
        ->assertJsonPath('reports.0.handover.processing_inventory_id', $processingInventoryId)
        ->assertJsonPath('reports.0.status', 'processing_repair');

    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->assigned_to_user_id)->toBe((int) $this->endUser->id)
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(1)
        ->and((int) $processingInventory->quantity)->toBe(2)
        ->and($processingInventory->status)->toBe('under_repair')
        ->and($report->fresh()->incident_status)->toBe('processing_repair');

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/maintenance/{$report->id}/decline", [
            'reason' => 'Attempted after physical handover.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect(Inventory::findOrFail($processingInventoryId)->status)->toBe('under_repair')
        ->and((int) Inventory::findOrFail($processingInventoryId)->quantity)->toBe(2)
        ->and($report->fresh()->incident_status)->toBe('processing_repair');
    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.status', 'processing_repair');

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/inventory/{$processingInventoryId}/mark-repaired", [
            'repair_notes' => 'Both support brackets were replaced and tested.',
            'maintenance_cost' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Item marked as repaired and returned to inventory.');

    expect($report->fresh()->incident_status)->toBe('resolved')
        ->and($report->fresh()->incident_outcome)->toBe('repaired')
        ->and(Inventory::findOrFail($processingInventoryId)->status)->toBe('available')
        ->and((int) Inventory::findOrFail($processingInventoryId)->quantity)->toBe(2)
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(1);

    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'event_type' => 'status_changed',
        'to_status' => 'resolved',
    ]);
});

test('confirmed unrepairable partial quantity enters existing disposal without disposing automatically', function () {
    $item = makeIssuedItem($this, 'Unrepairable Incident Chair', 3);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 2,
            'notes' => 'Two chairs are badly damaged.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'The frames are fractured and cannot be repaired.',
            'repairability' => 'not_repairable',
        ])
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Two affected chairs received from the user.',
        ])
        ->assertOk()
        ->assertJsonPath('incident_status', 'processing_disposal');

    $handover = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'incident_handover_confirmed')
        ->firstOrFail();
    $processingInventoryId = (int) $handover->metadata['processing_inventory_id'];
    $processingInventory = Inventory::findOrFail($processingInventoryId);

    expect($report->fresh()->incident_status)->toBe('processing_disposal')
        ->and($report->fresh()->incident_outcome)->toBeNull()
        ->and($processingInventory->status)->toBe('ready_to_dispose')
        ->and((int) $processingInventory->quantity)->toBe(2)
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(1);
    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.status', 'processing_disposal');

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/inventory/{$processingInventoryId}/dispose", [
            'notes' => 'Disposed through the standard custodian process.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($report->fresh()->incident_status)->toBe('resolved')
        ->and($report->fresh()->incident_outcome)->toBe('disposed')
        ->and(Inventory::findOrFail($processingInventoryId)->status)->toBe('disposed')
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->assigned_to_user_id)->toBe((int) $this->endUser->id)
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(1);

    $disposalMovement = StockMovement::where('inventory_id', $processingInventoryId)
        ->where('movement_type', 'disposed')
        ->firstOrFail();
    $this->actingAs($this->endUser)
        ->getJson(route('api.end-user.assigned-items'))
        ->assertOk()
        ->assertJsonPath('incidentReports.0.timeline.0.metadata.inventory_id', $item->item_id)
        ->assertJsonPath('incidentReports.0.timeline.5.to_status', 'resolved')
        ->assertJsonPath('incidentReports.0.timeline.5.metadata.processing_inventory_id', $processingInventoryId)
        ->assertJsonPath('incidentReports.0.timeline.5.metadata.disposal_movement_id', $disposalMovement->id)
        ->assertJsonPath('incidentReports.0.timeline.5.actor_id', $this->custodian->id);
});

test('handover is blocked when a repairable item category is not eligible for repair', function () {
    $item = makeIssuedItem($this, 'Repair Ineligible Chair', 1);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'damaged',
            'quantity' => 1,
            'notes' => 'The frame is broken.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'The frame needs repair.',
            'repairability' => 'repairable',
        ])
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received the affected chair.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['repair']);

    expect($report->fresh()->incident_status)->toBe('under_review');
    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'event_type' => 'incident_processing_blocked',
        'details' => 'Items in this category are not eligible for repair.',
    ]);
});

test('repair failure requires incident reassessment before affected units enter disposal', function () {
    $this->category->update(['is_maintenance_eligible' => true]);
    $item = makeIssuedItem($this, 'Failed Repair Incident Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'needs_repair',
            'quantity' => 1,
            'notes' => 'One chair has a damaged frame.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/inspection", [
            'findings' => 'Frame damage appears repairable.',
            'repairability' => 'repairable',
        ])
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/handover", [
            'details' => 'Received one affected chair.',
        ])
        ->assertOk();

    $handover = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'incident_handover_confirmed')
        ->firstOrFail();
    $processingInventoryId = (int) $handover->metadata['processing_inventory_id'];

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/inventory/{$processingInventoryId}/mark-ready-to-dispose", [
            'notes' => 'Repair is not viable.',
        ])
        ->assertUnprocessable();

    expect(Inventory::findOrFail($processingInventoryId)->status)->toBe('under_repair')
        ->and($report->fresh()->incident_status)->toBe('processing_repair');

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/repair-not-viable", [
            'findings' => 'Technician found the frame cannot be repaired safely.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Inventory::findOrFail($processingInventoryId)->status)->toBe('ready_to_dispose')
        ->and((int) Inventory::findOrFail($processingInventoryId)->quantity)->toBe(1)
        ->and($report->fresh()->incident_status)->toBe('processing_disposal');

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/inventory/{$processingInventoryId}/dispose", [
            'notes' => 'Final disposal confirmed.',
        ])
        ->assertOk();

    expect($report->fresh()->incident_status)->toBe('resolved')
        ->and($report->fresh()->incident_outcome)->toBe('disposed')
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(1);
});

test('lost and stolen reports move into a separate investigation queue without changing custody', function () {
    $item = makeIssuedItem($this, 'Investigation Queue Chair', 3);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'stolen',
            'quantity' => 2,
            'notes' => 'Two chairs are missing from the assigned room.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/start-investigation")
        ->assertUnprocessable();

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/start-investigation")
        ->assertOk()
        ->assertJsonPath('incident_status', 'under_investigation');

    expect($report->fresh()->incident_status)->toBe('under_investigation')
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe(3)
        ->and(Transaction::where('item_id', $item->item_id)->value('status'))->toBe('assigned');

    $this->assertDatabaseHas('maintenance_record_events', [
        'maintenance_record_id' => $report->id,
        'actor_id' => $this->custodian->id,
        'event_type' => 'status_changed',
        'to_status' => 'under_investigation',
    ]);
    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.status', 'under_investigation');
});

test('custodians can append investigation updates with private evidence', function () {
    Storage::fake('local');
    $item = makeIssuedItem($this, 'Investigation Evidence Chair', 2);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 1,
            'notes' => 'One chair is missing.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/start-investigation")
        ->assertOk();

    $this->actingAs($this->custodian)
        ->post("/api/custodian/incident-reports/{$report->id}/investigation-actions", [
            'details' => 'Checked the last known room and confirmed the removal date.',
            'evidence' => [UploadedFile::fake()->create('security-review.pdf', 12, 'application/pdf')],
        ], ['Accept' => 'application/json'])
        ->assertOk();
    $firstEvent = \App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'investigation_action_recorded')
        ->firstOrFail();
    $evidence = \App\Models\MaintenanceRecordEvidence::where('maintenance_record_id', $report->id)->firstOrFail();
    Storage::disk('local')->assertExists($evidence->path);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/investigation-actions", [
            'details' => 'Contacted the room custodian and requested a second location check.',
        ])
        ->assertOk();

    expect(\App\Models\MaintenanceRecordEvent::query()
        ->where('maintenance_record_id', $report->id)
        ->where('event_type', 'investigation_action_recorded')
        ->count())->toBe(2)
        ->and($firstEvent->metadata['evidence_ids'])->toBe([(int) $evidence->id]);

    $this->actingAs($this->custodian)
        ->getJson('/api/custodian/incident-reports')
        ->assertOk()
        ->assertJsonPath('reports.0.evidence.0.filename', 'security-review.pdf')
        ->assertJsonPath('reports.0.timeline.2.to_status', 'under_investigation')
        ->assertJsonPath('reports.0.timeline.3.event_type', 'investigation_action_recorded')
        ->assertJsonPath('reports.0.timeline.4.event_type', 'investigation_action_recorded');

    $this->actingAs($this->endUser)
        ->get(route('api.incident-evidence.download', $evidence->id))
        ->assertOk();
});

test('confirmed investigation outcomes change only the reported assignment quantity', function () {
    $cases = [
        ['outcome' => 'recovered', 'type' => 'lost', 'affected_quantity' => 2, 'expected_quantity' => 3, 'expected_status' => 'assigned', 'expected_assignment_status' => 'approved', 'movement_count' => 0],
        ['outcome' => 'unsubstantiated', 'type' => 'stolen', 'affected_quantity' => 2, 'expected_quantity' => 3, 'expected_status' => 'assigned', 'expected_assignment_status' => 'approved', 'movement_count' => 0],
        ['outcome' => 'confirmed_lost_stolen', 'type' => 'lost', 'affected_quantity' => 2, 'expected_quantity' => 1, 'expected_status' => 'assigned', 'expected_assignment_status' => 'approved', 'movement_count' => 1],
        ['outcome' => 'confirmed_lost_stolen', 'type' => 'stolen', 'affected_quantity' => 3, 'expected_quantity' => 0, 'expected_status' => 'available', 'expected_assignment_status' => 'lost', 'movement_count' => 1],
    ];

    foreach ($cases as $index => $case) {
        $item = makeIssuedItem($this, "Outcome Investigation Chair {$index}", 3);
        $transaction = Transaction::where('item_id', $item->item_id)->firstOrFail();
        $assignment = AssignmentRequest::create([
            'item_id' => $item->item_id,
            'user_id' => $this->custodian->id,
            'target_user_id' => $this->endUser->id,
            'transaction_id' => $transaction->id,
            'quantity' => 3,
            'status' => 'approved',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);
        $this->actingAs($this->endUser)
            ->post(route('endUser.inventory.report', $item->item_id), [
                'type' => $case['type'],
                'quantity' => $case['affected_quantity'],
                'notes' => 'Two assigned chairs are missing.',
            ]);
        $report = \App\Models\MaintenanceRecord::where('inventory_id', $item->item_id)->firstOrFail();
        $this->actingAs($this->custodian)
            ->postJson("/api/custodian/incident-reports/{$report->id}/review")
            ->assertOk();
        $this->actingAs($this->custodian)
            ->postJson("/api/custodian/incident-reports/{$report->id}/start-investigation")
            ->assertOk();
        $this->actingAs($this->custodian)
            ->postJson("/api/custodian/incident-reports/{$report->id}/resolve-investigation", [
                'outcome' => $case['outcome'],
            ])
            ->assertOk()
            ->assertJsonPath('incident_outcome', $case['outcome']);

        expect($report->fresh()->incident_status)->toBe('resolved')
            ->and($report->fresh()->incident_outcome)->toBe($case['outcome'])
            ->and($item->fresh()->status)->toBe($case['expected_status'])
            ->and((int) $item->fresh()->quantity)->toBe(0)
            ->and((int) Transaction::where('item_id', $item->item_id)->value('quantity'))->toBe($case['expected_quantity'])
            ->and(Transaction::where('item_id', $item->item_id)->value('status'))->toBe($case['expected_assignment_status'] === 'lost' ? 'lost' : 'assigned')
            ->and((int) $assignment->fresh()->quantity)->toBe($case['expected_quantity'])
            ->and($assignment->fresh()->status)->toBe($case['expected_assignment_status'])
            ->and(StockMovement::where('inventory_id', $item->item_id)->count())->toBe($case['movement_count']);
    }

    $lostItem = Inventory::where('item_name', 'Outcome Investigation Chair 2')->firstOrFail();
    $movement = StockMovement::where('inventory_id', $lostItem->item_id)->firstOrFail();
    expect($movement->movement_type)->toBe('incident_loss')
        ->and((int) $movement->quantity)->toBe(2)
        ->and((int) $movement->quantity_before)->toBe(3)
        ->and((int) $movement->quantity_after)->toBe(1);
});

test('lost or stolen outcome cannot exceed the reporter remaining assigned quantity', function () {
    $item = makeIssuedItem($this, 'Stale Investigation Assignment Chair', 3);
    $this->actingAs($this->endUser)
        ->post(route('endUser.inventory.report', $item->item_id), [
            'type' => 'lost',
            'quantity' => 2,
            'notes' => 'Two chairs are missing.',
        ]);
    $report = \App\Models\MaintenanceRecord::firstOrFail();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/review")
        ->assertOk();
    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/start-investigation")
        ->assertOk();
    Transaction::where('item_id', $item->item_id)->update(['quantity' => 1]);

    $this->actingAs($this->custodian)
        ->postJson("/api/custodian/incident-reports/{$report->id}/resolve-investigation", [
            'outcome' => 'confirmed_lost_stolen',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['outcome']);

    expect($report->fresh()->incident_status)->toBe('under_investigation')
        ->and($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(0)
        ->and(StockMovement::where('inventory_id', $item->item_id)->count())->toBe(0);
});
