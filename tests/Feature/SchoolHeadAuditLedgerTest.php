<?php

use App\Models\Category;
use App\Models\ArrivalInspection;
use App\Models\ArrivalInspectionItem;
use App\Models\AssignmentRequest;
use App\Models\Inventory;
use App\Models\InventoryItemLog;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordEvent;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auditHead(): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'School Head'])->role_id,
        'username' => 'audit-head',
    ]);
}

function auditMovementItem(User $custodian, string $name): Inventory
{
    $category = Category::firstOrCreate(
        ['category_name' => 'Audit Supplies'],
        ['requires_serial_number' => false],
    );

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => $name,
        'quantity' => 10,
        'unit_cost' => 5,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ]);
}

test('the school-head audit view lists item movements like the custodian ledger', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'username' => 'audit-custodian',
    ]);
    $item = auditMovementItem($custodian, 'Audit Paper');

    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $custodian->id,
        'movement_type' => 'stock_in',
        'quantity' => 10,
        'quantity_before' => 0,
        'quantity_after' => 10,
        'notes' => 'Stock-in',
    ]);
    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $custodian->id,
        'movement_type' => 'assignment',
        'quantity' => 1,
        'quantity_before' => 10,
        'quantity_after' => 9,
        'notes' => null,
    ]);

    $payload = $this->actingAs($head)->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json();

    expect($payload['logs'])->toBeArray();

    $movements = $payload['movements'];
    expect($movements)->toHaveCount(2)
        ->and($movements[0]['display_details'])->toBe('No additional details recorded.')
        ->and($movements[1]['display_details'])->toBe('Stock-in')
        ->and($movements[1]['inventory']['item_name'])->toBe('Audit Paper')
        ->and($movements[1]['user']['first_name'])->toBe('Juan');
});

test('school head logs preview includes only selected streams', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'first_name' => 'Log',
        'last_name' => 'Actor',
        'username' => 'preview-actor',
    ]);
    $item = auditMovementItem($custodian, 'Preview Paper');
    $movement = StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $custodian->id,
        'movement_type' => 'stock_in',
        'quantity' => 5,
        'quantity_before' => 0,
        'quantity_after' => 5,
        'notes' => 'Received stock',
    ]);
    $movement->timestamps = false;
    $movement->created_at = '2026-10-23 05:15:05';
    $movement->save();
    UserAuditLog::create([
        'actor_user_id' => $custodian->id,
        'actor_name' => 'Log Actor',
        'target_user_id' => $head->id,
        'target_name' => 'Audit Head',
        'action' => 'user_updated',
        'details' => ['field' => 'email'],
    ]);

    $this->actingAs($head)
        ->get(route('api.school-head.audit-logs.export-preview', [
            'log_types' => ['movements'],
        ]))
        ->assertOk()
        ->assertSee('Item Movements')
        ->assertSee('Preview Paper')
        ->assertSee('Received stock')
        ->assertSee('Oct 23, 2026 1:15 PM')
        ->assertDontSee('Account Activity')
        ->assertDontSee('user_updated');

    $pdfResponse = $this->get(route('api.school-head.audit-logs.export-pdf', [
        'log_types' => ['movements'],
    ]));

    $pdfResponse->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition');
    expect(substr($pdfResponse->getContent(), 0, 4))->toBe('%PDF');
});

test('the school-head movements ledger arrives whole so batches never split', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'audit-custodian-2',
    ]);
    $item = auditMovementItem($custodian, 'Paged Paper');

    foreach (range(1, 15) as $index) {
        StockMovement::create([
            'inventory_id' => $item->item_id,
            'user_id' => $custodian->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
            'quantity_before' => $index - 1,
            'quantity_after' => $index,
            'notes' => 'Batch stock-in',
        ]);
    }

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json();

    // One collection, newest first: the frontend groups then pages, so the
    // fifteen identical records stay one ×15 group on a single page.
    expect($payload['movements'])->toHaveCount(15)
        ->and($payload['movements'][0]['display_details'])->toBe('Batch stock-in')
        ->and($payload['movements'][0]['inventory']['item_name'])->toBe('Paged Paper');
});

test('the school-head audit logs include item edits and deletions but not stock-in or incident isolation', function () {
    $head = auditHead();
    $category = Category::firstOrCreate(
        ['category_name' => 'Audit Item Log Supplies'],
        ['requires_serial_number' => false],
    );

    foreach ([
        ['item_id' => 11, 'action' => InventoryItemLog::ACTION_CREATED, 'reason' => null],
        ['item_id' => 12, 'action' => InventoryItemLog::ACTION_UPDATED, 'reason' => null],
        ['item_id' => 13, 'action' => InventoryItemLog::ACTION_DELETED, 'reason' => null],
        ['item_id' => 14, 'action' => InventoryItemLog::ACTION_UPDATED, 'reason' => 'Isolated incident #16: received'],
        ['item_id' => 15, 'action' => InventoryItemLog::ACTION_DISPOSED, 'reason' => null],
    ] as $index => $entry) {
        InventoryItemLog::create([
            'item_id' => $entry['item_id'],
            'inventory_item_no' => sprintf('INV-%06d', $entry['item_id']),
            'item_name' => "Audit Item {$entry['item_id']}",
            'category_id' => $category->category_id,
            'category_name' => $category->category_name,
            'status' => 'available',
            'action' => $entry['action'],
            'changes' => ['quantity' => ['old' => 1, 'new' => 0]],
            'reason' => $entry['reason'],
        ]);
    }

    $itemLogs = $this->actingAs($head)
        ->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json('itemLogs');

    expect(collect($itemLogs)->pluck('action')->all())->toBe([
        InventoryItemLog::ACTION_DELETED,
        InventoryItemLog::ACTION_UPDATED,
    ])
        ->and(collect($itemLogs)->pluck('item_name')->all())->toBe(['Audit Item 13', 'Audit Item 12']);
});

test('the school-head audit response includes incident, request decision, and arrival inspection logs', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'workflow-log-custodian',
    ]);
    $requester = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'End User'])->role_id,
        'username' => 'workflow-log-requester',
    ]);
    $item = auditMovementItem($custodian, 'Workflow Test Laptop');

    $incident = MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $requester->id,
        'status' => 'reported',
        'diagnosis' => 'damaged',
        'issue_description' => 'Screen is damaged.',
        'incident_status' => 'under_review',
    ]);
    MaintenanceRecordEvent::create([
        'maintenance_record_id' => $incident->id,
        'actor_id' => $custodian->id,
        'event_type' => 'legacy_state_imported',
        'to_status' => 'under_review',
        'details' => 'Incident entered review.',
    ]);

    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $requester->id,
        'target_user_id' => $custodian->id,
        'quantity' => 2,
        'status' => 'declined',
        'requested_at' => now()->subHour(),
        'responded_at' => now()->subMinutes(30),
        'notes' => 'Insufficient stock for this request.',
    ]);

    $inspection = ArrivalInspection::create([
        'status' => 'approved',
        'iar_number' => 'IAR-AUDIT-001',
        'supplier_name' => 'Test Supplier',
        'created_by' => $custodian->id,
        'inspected_by' => $requester->id,
        'inspected_at' => now()->subHours(2),
        'completed_by' => $head->id,
        'completed_at' => now()->subHour(),
    ]);
    ArrivalInspectionItem::create([
        'arrival_inspection_id' => $inspection->id,
        'description' => 'Inspection Test Laptop',
        'unit' => 'piece',
        'quantity' => 1,
    ]);

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json();

    expect($payload['incidentLogs'][0]['action'])->toBe('legacy_state_imported')
        ->and($payload['incidentLogs'][0]['item_name'])->toBe('Workflow Test Laptop')
        ->and($payload['incidentLogs'][0]['actor']['id'])->toBe($custodian->id)
        ->and($payload['requestLogs'][0]['action'])->toBe('declined')
        ->and($payload['requestLogs'][0]['quantity'])->toBe(2)
        ->and($payload['arrivalLogs'])->toHaveCount(3)
        ->and(collect($payload['arrivalLogs'])->pluck('action')->all())
        ->toContain('submitted', 'inspected', 'approved')
        ->and(collect($payload['arrivalLogs'])->firstWhere('action', 'approved')['actor']['id'])
        ->toBe($head->id);
});
