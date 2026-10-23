<?php

use App\Models\ArrivalInspection;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inspectorUser(): User
{
    $role = Role::query()->firstOrCreate(['role_name' => 'Inspector']);

    return User::factory()->create(['role_id' => $role->role_id]);
}

function custodianUser(): User
{
    $role = Role::query()->firstOrCreate(['role_name' => 'Property Custodian']);

    return User::factory()->create(['role_id' => $role->role_id]);
}

function arrivalInspectionPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'status' => 'inspected',
        'inspection_verified' => true,
        'entity_name' => 'Division of Cebu Province',
        'fund_cluster' => 'Regular Agency Fund-01',
        'supplier_name' => 'Arrival Supplier',
        'contract_title' => 'Supply and delivery of laptops',
        'requesting_office' => 'Central Office',
        'responsibility_center_code' => '70010807002',
        'iar_number' => 'IAR-2026-0001',
        'iar_date' => '2026-10-23',
        'invoice_number' => 'INV-001',
        'invoice_date' => '2026-10-20',
        'inspection_date' => '2026-10-22',
        'receipt_date' => '2026-10-23',
        'acceptance_status' => 'partial',
        'acceptance_remarks' => 'One item rejected due to damage.',
        'committee_member_1_name' => 'Committee One',
        'committee_member_1_designation' => 'Inspection Committee',
        'committee_member_2_name' => 'Committee Two',
        'committee_member_2_designation' => 'Inspection Committee',
        'committee_member_3_name' => 'Committee Three',
        'committee_member_3_designation' => 'Inspection Committee',
        'custodian_name' => 'Custodian',
        'custodian_designation' => 'School Property Custodian',
        'items' => [[
            'stock_property_number' => 'DIVLAP23-0001',
            'description' => 'Laptop with specifications',
            'unit' => 'UNIT',
            'quantity' => 1,
        ]],
    ], $overrides);
}

it('lets an inspector record and review IAR fields and item lines', function () {
    $inspector = inspectorUser();
    $response = $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', arrivalInspectionPayload());

    $response->assertCreated()
        ->assertJsonPath('inspection.status', 'inspected')
        ->assertJsonPath('inspection.inspection_verified', true)
        ->assertJsonPath('inspection.entity_name', 'Division of Cebu Province')
        ->assertJsonPath('inspection.iar_number', 'IAR-2026-0001')
        ->assertJsonPath('inspection.items.0.stock_property_number', 'DIVLAP23-0001')
        ->assertJsonPath('inspection.items.0.description', 'Laptop with specifications')
        ->assertJsonPath('inspection.items.0.unit', 'UNIT')
        ->assertJsonPath('inspection.items.0.quantity', 1);

    $id = $response->json('inspection.id');
    $this->actingAs($inspector)
        ->getJson("/api/inspector/arrival-inspections/$id")
        ->assertOk()
        ->assertJsonPath('inspection.acceptance_status', 'partial');

    $this->assertDatabaseHas('arrival_inspections', [
        'id' => $id,
        'created_by' => $inspector->id,
        'inspected_by' => $inspector->id,
        'status' => 'inspected',
    ]);
    expect(ArrivalInspection::query()->findOrFail($id)->inspected_at)->not->toBeNull();
});

it('saves the inspection verification checkbox state', function () {
    $inspector = inspectorUser();
    $response = $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', arrivalInspectionPayload([
            'inspection_verified' => false,
        ]));

    $response->assertCreated()
        ->assertJsonPath('inspection.inspection_verified', false);

    $this->assertDatabaseHas('arrival_inspections', [
        'id' => $response->json('inspection.id'),
        'inspection_verified' => false,
    ]);
});

it('accepts a complete delivery and validates submitted item descriptions, units, and positive quantities', function () {
    $inspector = inspectorUser();
    $completePayload = arrivalInspectionPayload([
        'acceptance_status' => 'complete',
        'acceptance_remarks' => null,
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $completePayload)
        ->assertCreated()
        ->assertJsonPath('inspection.acceptance_status', 'complete');

    $invalidPayload = arrivalInspectionPayload([
        'iar_number' => 'IAR-INVALID-ITEMS',
        'items' => [[
            'stock_property_number' => null,
            'description' => null,
            'unit' => null,
            'quantity' => 0,
        ]],
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $invalidPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'items.0.description',
            'items.0.unit',
            'items.0.quantity',
        ]);
});

it('rejects duplicate IAR numbers through the Inspector API', function () {
    $inspector = inspectorUser();
    ArrivalInspection::query()->create([
        'iar_number' => 'IAR-2026-0001',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', arrivalInspectionPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('iar_number');
});

it('allows saving an IAR draft before inspection details are complete', function () {
    $inspector = inspectorUser();
    $payload = arrivalInspectionPayload([
        'status' => 'draft',
        'acceptance_status' => null,
        'inspection_date' => null,
        'committee_member_1_name' => null,
        'committee_member_1_designation' => null,
        'committee_member_2_name' => null,
        'committee_member_2_designation' => null,
        'committee_member_3_name' => null,
        'committee_member_3_designation' => null,
        'custodian_name' => null,
        'custodian_designation' => null,
        'items' => [[
            'stock_property_number' => null,
            'description' => null,
            'unit' => null,
            'quantity' => null,
        ]],
    ]);

    $response = $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $payload)
        ->assertCreated()
        ->assertJsonPath('inspection.status', 'draft')
        ->assertJsonPath('inspection.items.0.description', null)
        ->assertJsonPath('inspection.items.0.unit', null)
        ->assertJsonPath('inspection.items.0.quantity', null);

    $this->assertDatabaseHas('arrival_inspection_items', [
        'arrival_inspection_id' => $response->json('inspection.id'),
        'description' => null,
        'unit' => null,
        'quantity' => null,
    ]);
});

it('allows saving an IAR draft without any item rows', function () {
    $inspector = inspectorUser();
    $payload = arrivalInspectionPayload();
    $payload['status'] = 'draft';
    $payload['items'] = [];

    $response = $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $payload)
        ->assertCreated()
        ->assertJsonPath('inspection.status', 'draft')
        ->assertJsonCount(0, 'inspection.items');

    $this->assertDatabaseCount('arrival_inspection_items', 0);
});

it('does not assign an IAR number to a fully rejected delivery', function () {
    $inspector = inspectorUser();
    $payload = arrivalInspectionPayload([
        'iar_number' => null,
        'acceptance_status' => 'rejected',
        'acceptance_remarks' => 'All delivered items were damaged.',
    ]);

    $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $payload)
        ->assertCreated()
        ->assertJsonPath('inspection.iar_number', null)
        ->assertJsonPath('inspection.acceptance_status', 'rejected');
});

it('rejects an IAR number on a fully rejected delivery', function () {
    $inspector = inspectorUser();
    $payload = arrivalInspectionPayload(['acceptance_status' => 'rejected']);

    $this->actingAs($inspector)
        ->postJson('/api/inspector/arrival-inspections', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('iar_number');
});

it('limits arrival inspection endpoints to inspectors and records to their creator', function () {
    $inspector = inspectorUser();
    $otherInspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'draft',
        'supplier_name' => 'Private Supplier',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->getJson('/api/inspector/arrival-inspections')
        ->assertForbidden();

    $this->actingAs($otherInspector)
        ->getJson("/api/inspector/arrival-inspections/{$inspection->id}")
        ->assertNotFound();

    $this->actingAs($otherInspector)
        ->deleteJson("/api/inspector/arrival-inspections/{$inspection->id}")
        ->assertNotFound();
});

it('allows an inspector to delete their own draft with its item rows', function () {
    $inspector = inspectorUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'draft',
        'created_by' => $inspector->id,
    ]);
    $inspection->items()->create([
        'stock_property_number' => null,
        'description' => null,
        'unit' => null,
        'quantity' => null,
    ]);

    $this->actingAs($inspector)
        ->deleteJson("/api/inspector/arrival-inspections/{$inspection->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Inspection draft deleted.');

    $this->assertDatabaseMissing('arrival_inspections', ['id' => $inspection->id]);
    $this->assertDatabaseMissing('arrival_inspection_items', ['arrival_inspection_id' => $inspection->id]);
});

it('prevents deleting an inspected arrival inspection', function () {
    $inspector = inspectorUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($inspector)
        ->deleteJson("/api/inspector/arrival-inspections/{$inspection->id}")
        ->assertStatus(409);
});

it('prevents editing an IAR while it is awaiting custodian approval', function () {
    $inspector = inspectorUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'iar_number' => 'IAR-2026-0001',
        'supplier_name' => 'Recorded Supplier',
        'created_by' => $inspector->id,
    ]);
    $payload = arrivalInspectionPayload(['iar_number' => 'IAR-2026-0002']);

    $this->actingAs($inspector)
        ->patchJson("/api/inspector/arrival-inspections/{$inspection->id}", $payload)
        ->assertStatus(409);
});

it('allows correcting and resubmitting an IAR returned by the custodian', function () {
    $inspector = inspectorUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'returned',
        'iar_number' => 'IAR-2026-0001',
        'supplier_name' => 'Recorded Supplier',
        'custodian_return_reason' => 'Correct the invoice number.',
        'custodian_returned_at' => now(),
        'created_by' => $inspector->id,
    ]);
    $payload = arrivalInspectionPayload(['iar_number' => 'IAR-2026-0002']);

    $this->actingAs($inspector)
        ->patchJson("/api/inspector/arrival-inspections/{$inspection->id}", $payload)
        ->assertOk()
        ->assertJsonPath('inspection.status', 'inspected')
        ->assertJsonPath('inspection.iar_number', 'IAR-2026-0002')
        ->assertJsonPath('inspection.custodian_return_reason', null);
});

it('shows submitted non-rejected IARs in the custodian approval queue', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $submitted = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'supplier_name' => 'Submitted Supplier',
        'created_by' => $inspector->id,
        'inspected_by' => $inspector->id,
    ]);
    $submitted->items()->create([
        'description' => 'Desk',
        'unit' => 'UNIT',
        'quantity' => 2,
    ]);
    ArrivalInspection::query()->create([
        'status' => 'draft',
        'supplier_name' => 'Private Draft',
        'created_by' => $inspector->id,
    ]);
    ArrivalInspection::query()->create([
        'status' => 'inspected',
        'acceptance_status' => 'rejected',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->getJson('/api/custodian/arrival-inspections/pending-approval')
        ->assertOk()
        ->assertJsonCount(1, 'inspections')
        ->assertJsonPath('inspections.0.id', $submitted->id)
        ->assertJsonPath('inspections.0.items.0.quantity', 2)
        ->assertJsonPath('inspections.0.creator.id', $inspector->id);

    $this->actingAs($inspector)
        ->getJson('/api/custodian/arrival-inspections/pending-approval')
        ->assertForbidden();
});

it('offers approved unposted IARs as Stock In sources', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $approved = ArrivalInspection::query()->create([
        'status' => 'approved',
        'acceptance_status' => 'complete',
        'iar_number' => 'IAR-READY',
        'supplier_name' => 'Approved Supplier',
        'created_by' => $inspector->id,
    ]);
    $approved->items()->create(['description' => 'Desk', 'unit' => 'UNIT', 'quantity' => 2]);
    ArrivalInspection::query()->create([
        'status' => 'inspected',
        'iar_number' => 'IAR-PENDING',
        'created_by' => $inspector->id,
    ]);
    ArrivalInspection::query()->create([
        'status' => 'posted',
        'iar_number' => 'IAR-POSTED',
        'posted_at' => now(),
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->getJson('/api/custodian/inventory/stock-in/approved-iars')
        ->assertOk()
        ->assertJsonCount(1, 'inspections')
        ->assertJsonPath('inspections.0.id', $approved->id)
        ->assertJsonPath('inspections.0.items.0.quantity', 2);

    $this->actingAs($inspector)
        ->getJson('/api/custodian/inventory/stock-in/approved-iars')
        ->assertForbidden();
});

it('prevents Stock In from an IAR before custodian approval', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'acceptance_status' => 'complete',
        'iar_number' => 'IAR-NOT-APPROVED',
        'created_by' => $inspector->id,
    ]);
    $inspectionItem = $inspection->items()->create([
        'description' => 'Unapproved desk',
        'unit' => 'UNIT',
        'quantity' => 2,
    ]);
    $category = Category::query()->create([
        'category_name' => 'Unapproved IAR Furniture',
        'requires_serial_number' => false,
        'requires_qr_code' => false,
    ]);

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/stock-in/approved-iar', [
            'arrival_inspection_id' => $inspection->id,
            'rows' => [[
                'arrival_inspection_item_id' => $inspectionItem->id,
                'category_id' => $category->category_id,
                'unit_cost' => 100,
            ]],
        ])
        ->assertStatus(409);

    expect(Inventory::query()->where('arrival_inspection_id', $inspection->id)->exists())->toBeFalse()
        ->and($inspection->fresh()->status)->toBe('inspected');
});

it('stocks every accepted item from an approved IAR once and links inventory back to the IAR', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'approved',
        'acceptance_status' => 'partial',
        'iar_number' => 'IAR-STOCK-IN',
        'receipt_date' => '2026-10-20',
        'inspection_date' => '2026-10-19',
        'created_by' => $inspector->id,
        'completed_by' => $custodian->id,
        'completed_at' => now(),
    ]);
    $inspectionItem = $inspection->items()->create([
        'stock_property_number' => 'PROPERTY-001',
        'description' => 'Approved desk',
        'unit' => 'UNIT',
        'quantity' => 3,
    ]);
    $category = Category::query()->create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
        'requires_qr_code' => false,
        'default_lifespan_years' => 5,
    ]);
    $payload = [
        'arrival_inspection_id' => $inspection->id,
        'ics_no' => 'ICS-2026-001',
        'rows' => [[
            'arrival_inspection_item_id' => $inspectionItem->id,
            'category_id' => $category->category_id,
            'unit_cost' => 1500,
            'lifespan_years' => null,
            'serial_numbers' => [],
        ]],
    ];

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/stock-in/approved-iar', $payload)
        ->assertCreated()
        ->assertJsonPath('created_records', 1)
        ->assertJsonPath('message', 'Stock In completed from IAR IAR-STOCK-IN.');

    $inventory = Inventory::query()->where('arrival_inspection_id', $inspection->id)->firstOrFail();
    expect($inventory->item_name)->toBe('Approved desk')
        ->and($inventory->unit)->toBe('UNIT')
        ->and($inventory->quantity)->toBe(3)
        ->and((float) $inventory->unit_cost)->toBe(1500.0)
        ->and($inventory->ics_no)->toBe('ICS-2026-001')
        ->and($inventory->date_acquired->format('Y-m-d'))->toBe('2026-10-20')
        ->and($inventory->arrival_inspection_item_id)->toBe($inspectionItem->id)
        ->and($inspection->fresh()->status)->toBe('posted')
        ->and($inspection->fresh()->posted_at)->not->toBeNull();

    expect(StockMovement::query()
        ->where('inventory_id', $inventory->item_id)
        ->where('movement_type', 'stock_in')
        ->exists())->toBeTrue();

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/stock-in/approved-iar', $payload)
        ->assertStatus(409);

    expect(Inventory::query()->where('arrival_inspection_id', $inspection->id)->count())->toBe(1);
});

it('creates separately tracked stock records for serial-numbered IAR quantities', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'approved',
        'acceptance_status' => 'complete',
        'iar_number' => 'IAR-SERIAL-STOCK-IN',
        'receipt_date' => '2026-10-20',
        'created_by' => $inspector->id,
        'completed_by' => $custodian->id,
        'completed_at' => now(),
    ]);
    $inspectionItem = $inspection->items()->create([
        'description' => 'Laptop',
        'unit' => 'UNIT',
        'quantity' => 2,
    ]);
    $category = Category::query()->create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
        'requires_qr_code' => true,
    ]);

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/stock-in/approved-iar', [
            'arrival_inspection_id' => $inspection->id,
            'rows' => [[
                'arrival_inspection_item_id' => $inspectionItem->id,
                'category_id' => $category->category_id,
                'unit_cost' => 25000,
                'serial_numbers' => ['LAPTOP-001', 'LAPTOP-002'],
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('created_records', 2);

    $items = Inventory::query()->where('arrival_inspection_id', $inspection->id)->orderBy('item_id')->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('serial_number')->all())->toBe(['LAPTOP-001', 'LAPTOP-002'])
        ->and($items->pluck('quantity')->all())->toBe([1, 1])
        ->and($items->every(fn (Inventory $item) => $item->arrival_inspection_item_id === $inspectionItem->id
            && $item->qr_code !== null))->toBeTrue();
});

it('requires every approved IAR item to be mapped before Stock In', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'approved',
        'acceptance_status' => 'complete',
        'iar_number' => 'IAR-INCOMPLETE-MAPPING',
        'created_by' => $inspector->id,
    ]);
    $inspection->items()->create(['description' => 'Desk', 'unit' => 'UNIT', 'quantity' => 1]);
    $inspection->items()->create(['description' => 'Chair', 'unit' => 'UNIT', 'quantity' => 2]);
    $category = Category::query()->create([
        'category_name' => 'IAR Mapping Furniture',
        'requires_serial_number' => false,
        'requires_qr_code' => false,
    ]);

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/stock-in/approved-iar', [
            'arrival_inspection_id' => $inspection->id,
            'rows' => [[
                'arrival_inspection_item_id' => $inspection->items()->first()->id,
                'category_id' => $category->category_id,
                'unit_cost' => 100,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rows');

    expect(Inventory::query()->where('arrival_inspection_id', $inspection->id)->exists())->toBeFalse()
        ->and($inspection->fresh()->status)->toBe('approved');
});

it('allows a custodian to sign and approve an IAR awaiting approval', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'created_by' => $inspector->id,
        'inspected_by' => $inspector->id,
    ]);
    $signatureName = trim($custodian->first_name.' '.$custodian->last_name);

    $this->actingAs($custodian)
        ->postJson("/api/custodian/arrival-inspections/{$inspection->id}/approve", [
            'signature_name' => $signatureName,
            'confirmation' => true,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'IAR approved and signed.');

    $this->assertDatabaseHas('arrival_inspections', [
        'id' => $inspection->id,
        'status' => 'approved',
        'completed_by' => $custodian->id,
        'custodian_signature_name' => $signatureName,
    ]);
    expect($inspection->fresh()->completed_at)->not->toBeNull();
});

it('requires the authenticated custodian name to sign an IAR approval', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->postJson("/api/custodian/arrival-inspections/{$inspection->id}/approve", [
            'signature_name' => 'Someone Else',
            'confirmation' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('signature_name');

    $this->assertDatabaseHas('arrival_inspections', ['id' => $inspection->id, 'status' => 'inspected']);
});

it('prevents a custodian from approving a fully rejected delivery', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'acceptance_status' => 'rejected',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->postJson("/api/custodian/arrival-inspections/{$inspection->id}/approve", [
            'signature_name' => trim($custodian->first_name.' '.$custodian->last_name),
            'confirmation' => true,
        ])
        ->assertStatus(409);

    $this->assertDatabaseHas('arrival_inspections', ['id' => $inspection->id, 'status' => 'inspected']);
});

it('returns an IAR to the inspector with a required correction reason', function () {
    $inspector = inspectorUser();
    $custodian = custodianUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($custodian)
        ->postJson("/api/custodian/arrival-inspections/{$inspection->id}/return", ['reason' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this->actingAs($custodian)
        ->postJson("/api/custodian/arrival-inspections/{$inspection->id}/return", [
            'reason' => 'Please correct the invoice details.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'IAR returned to the Inspector for correction.');

    $this->assertDatabaseHas('arrival_inspections', [
        'id' => $inspection->id,
        'status' => 'returned',
        'custodian_return_reason' => 'Please correct the invoice details.',
        'completed_by' => null,
    ]);
});

it('locks an inspection after IAR finalization', function () {
    $inspector = inspectorUser();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'finalized',
        'iar_number' => 'IAR-2026-LOCKED',
        'created_by' => $inspector->id,
    ]);

    $this->actingAs($inspector)
        ->patchJson("/api/inspector/arrival-inspections/{$inspection->id}", [])
        ->assertStatus(409);
});
