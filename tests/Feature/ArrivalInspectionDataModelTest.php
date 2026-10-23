<?php

use App\Models\ArrivalInspection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('defines only the requested IAR fields in the arrival inspection tables', function () {
    expect(Schema::getColumnListing('arrival_inspection_items'))
        ->toContain('stock_property_number', 'description', 'unit', 'quantity')
        ->not->toContain('category_id', 'item_name', 'unit_cost', 'ics_no', 'accepted_quantity');

    expect(Schema::getColumnListing('arrival_inspections'))
        ->toContain(
            'entity_name',
            'fund_cluster',
            'supplier_name',
            'contract_title',
            'requesting_office',
            'responsibility_center_code',
            'iar_number',
            'iar_date',
            'invoice_number',
            'invoice_date'
        );
});

it('stores IAR header, acceptance, item columns, and printed signatories', function () {
    $creator = User::factory()->create();
    $inspection = ArrivalInspection::query()->create([
        'status' => 'inspected',
        'iar_number' => 'IAR-2026-0001',
        'iar_date' => '2026-10-23',
        'entity_name' => 'Division of Cebu Province',
        'fund_cluster' => 'Regular Agency Fund-01',
        'supplier_name' => 'Test Supplier',
        'contract_title' => 'Supply and delivery of laptops',
        'requesting_office' => 'Central Office',
        'responsibility_center_code' => '70010807002',
        'invoice_number' => 'INV-001',
        'invoice_date' => '2026-10-20',
        'inspection_date' => '2026-10-22',
        'receipt_date' => '2026-10-23',
        'acceptance_status' => 'partial',
        'acceptance_remarks' => 'One item accepted; one rejected due to damage.',
        'created_by' => $creator->id,
        'inspected_by' => $creator->id,
        'inspected_at' => now(),
        'committee_member_1_name' => 'Inspector One',
        'committee_member_1_designation' => 'Inspection Committee',
        'committee_member_2_name' => 'Inspector Two',
        'committee_member_2_designation' => 'Inspection Committee',
        'committee_member_3_name' => 'Inspector Three',
        'committee_member_3_designation' => 'Inspection Committee',
        'custodian_name' => 'Property Custodian',
        'custodian_designation' => 'School Property Custodian',
    ]);

    $item = $inspection->items()->create([
        'stock_property_number' => 'DIVLAP23-0001',
        'description' => 'Laptop IS-1021OU, including specifications',
        'unit' => 'UNIT',
        'quantity' => 1,
    ]);

    expect($inspection->fresh()->items)->toHaveCount(1)
        ->and($item->fresh()->arrivalInspection->iar_number)->toBe('IAR-2026-0001')
        ->and($item->fresh()->quantity)->toBe(1)
        ->and($inspection->fresh()->acceptance_status)->toBe('partial')
        ->and($inspection->fresh()->custodian_designation)->toBe('School Property Custodian');
});

it('prevents duplicate IAR numbers', function () {
    $creator = User::factory()->create();

    ArrivalInspection::query()->create([
        'iar_number' => 'IAR-2026-0001',
        'created_by' => $creator->id,
    ]);

    expect(fn () => ArrivalInspection::query()->create([
        'iar_number' => 'IAR-2026-0001',
        'created_by' => $creator->id,
    ]))->toThrow(QueryException::class);
});
