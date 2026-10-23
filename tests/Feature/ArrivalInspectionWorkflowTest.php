<?php

use App\Models\ArrivalInspection;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inspectorUser(): User
{
    $role = Role::query()->firstOrCreate(['role_name' => 'Inspector']);

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
    $custodianRole = Role::query()->firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::factory()->create(['role_id' => $custodianRole->role_id]);
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

it('allows correcting inspected IAR details before finalization', function () {
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
        ->assertOk()
        ->assertJsonPath('inspection.iar_number', 'IAR-2026-0002');
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
