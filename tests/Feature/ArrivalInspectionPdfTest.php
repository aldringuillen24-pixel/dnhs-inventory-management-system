<?php

use App\Models\ArrivalInspection;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function iarPdfInspector(): User
{
    $role = Role::query()->firstOrCreate(['role_name' => 'Inspector']);

    return User::factory()->create(['role_id' => $role->role_id]);
}

function iarPdfInspection(User $inspector, array $attributes = []): ArrivalInspection
{
    $inspection = ArrivalInspection::query()->create(array_merge([
        'status' => 'inspected',
        'iar_number' => 'IAR-2026-0005',
        'iar_date' => '2026-10-23',
        'entity_name' => 'DIVISION OF CEBU PROVINCE',
        'fund_cluster' => 'Regular Agency Fund-01',
        'supplier_name' => 'ARIK GENERAL MERCHANDISING',
        'contract_title' => 'Supply and delivery of laptop',
        'requesting_office' => 'ALCANTARA CENTRAL ES',
        'responsibility_center_code' => '70010807002',
        'invoice_number' => 'INV-2026-001',
        'invoice_date' => '2026-10-22',
        'inspection_date' => '2026-10-22',
        'receipt_date' => '2026-10-23',
        'acceptance_status' => 'partial',
        'acceptance_remarks' => 'One unit accepted; one unit rejected due to damage.',
        'committee_member_1_name' => 'Inspector One',
        'committee_member_1_designation' => 'Inspection Officer/Inspection Committee',
        'committee_member_2_name' => 'Inspector Two',
        'committee_member_2_designation' => 'Inspection Officer/Inspection Committee',
        'committee_member_3_name' => 'Inspector Three',
        'committee_member_3_designation' => 'Inspection Officer/Inspection Committee',
        'custodian_name' => 'Custodian One',
        'custodian_designation' => 'School Property Custodian',
        'created_by' => $inspector->id,
        'inspected_by' => $inspector->id,
        'inspected_at' => now(),
    ], $attributes));

    $inspection->items()->create([
        'stock_property_number' => 'DIVLAP23-0001',
        'description' => "Laptop IS-1021OU\nSpecifications: 15.6 inch screen, 8GB RAM",
        'unit' => 'UNIT',
        'quantity' => 1,
    ]);

    return $inspection;
}

it('renders a letter-size IAR PDF with the inspection record', function () {
    $inspector = iarPdfInspector();
    $inspection = iarPdfInspection($inspector);

    $response = $this->actingAs($inspector)
        ->get("/api/inspector/arrival-inspections/{$inspection->id}/pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('IAR-2026-0005.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('does not generate an IAR PDF for drafts, rejected deliveries, or records without an IAR number', function () {
    $inspector = iarPdfInspector();
    $draft = iarPdfInspection($inspector, [
        'status' => 'draft',
        'iar_number' => 'IAR-DRAFT',
    ]);
    $rejected = iarPdfInspection($inspector, [
        'iar_number' => null,
        'acceptance_status' => 'rejected',
    ]);
    $missingNumber = iarPdfInspection($inspector, [
        'iar_number' => null,
        'acceptance_status' => 'complete',
    ]);

    $this->actingAs($inspector)
        ->getJson("/api/inspector/arrival-inspections/{$draft->id}/pdf")
        ->assertStatus(409);
    $this->actingAs($inspector)
        ->getJson("/api/inspector/arrival-inspections/{$rejected->id}/pdf")
        ->assertStatus(409);
    $this->actingAs($inspector)
        ->getJson("/api/inspector/arrival-inspections/{$missingNumber->id}/pdf")
        ->assertStatus(409);
});

it('only allows the owning inspector to generate an IAR PDF', function () {
    $inspector = iarPdfInspector();
    $otherInspector = iarPdfInspector();
    $inspection = iarPdfInspection($inspector);

    $this->actingAs($otherInspector)
        ->getJson("/api/inspector/arrival-inspections/{$inspection->id}/pdf")
        ->assertNotFound();

    $custodianRole = Role::query()->firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::factory()->create(['role_id' => $custodianRole->role_id]);
    $this->actingAs($custodian)
        ->getJson("/api/inspector/arrival-inspections/{$inspection->id}/pdf")
        ->assertForbidden();
});
