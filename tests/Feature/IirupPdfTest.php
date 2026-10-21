<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create(['role_name' => 'Property Custodian']);

    $this->custodian = User::create([
        'role_id'    => $role->role_id,
        'first_name' => 'Property',
        'last_name'  => 'Custodian',
        'username'   => 'prop-custodian',
        'email'      => 'custodian@example.com',
        'password'   => 'password',
        'status'     => 'active',
        'building'   => 'Main Building',
        'room'       => '101',
    ]);

    $category = Category::create([
        'category_name'          => 'ICT Equipment',
        'requires_serial_number' => true,
        'requires_qr_code'       => true,
    ]);

    $this->item = Inventory::create([
        'category_id'       => $category->category_id,
        'user_id'           => $this->custodian->id,
        'item_name'         => 'Test Laptop',
        'unit'              => 'piece',
        'unit_cost'         => 25000,
        'ics_no'            => 'ICS-TEST-001',
        'quantity'          => 1,
        'status'            => 'ready_to_dispose',
        'date_acquired'     => '2026-01-01',
        'inventory_item_no' => 'INV-000001',
    ]);
});

test('IIRUP PDF generation returns 200', function () {
    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/iirup-pdf', [
            'report_date' => '2026-10-20',
            'entity_name' => 'Dian-ay National High School',
            'fund_cluster' => '',
            'accountable_officer_name' => 'Property Custodian',
            'accountable_officer_designation' => 'SCHOOL PROPERTY CUSTODIAN',
            'accountable_officer_station' => 'Main Building, Room 101',
            'approving_official_name' => 'Principal Test',
            'approving_official_designation' => 'PRINCIPAL I',
            'inspection_officer_name' => 'Inspector Test',
            'inspection_designation' => 'Name of Inspection Officer',
            'witness_name' => 'Witness Test',
            'selected_ids' => [$this->item->item_id],
        ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

test('IIRUP PDF generation fails without signatories', function () {
    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/iirup-pdf', [
            'report_date' => '2026-10-20',
            'entity_name' => 'Dian-ay National High School',
            'selected_ids' => [$this->item->item_id],
        ]);

    $response->assertUnprocessable();
});

test('IIRUP PDF generation uses item overrides without recalculating edited values', function () {
    $capturedViewData = [];
    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->once()->with('letter', 'landscape')->andReturnSelf();
    $pdf->shouldReceive('download')->once()->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.disposal-inspection-report', Mockery::on(function (array $viewData) use (&$capturedViewData) {
            $capturedViewData = $viewData;

            return true;
        }))
        ->andReturn($pdf);

    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/iirup-pdf', [
            'report_date' => '2026-10-20',
            'entity_name' => 'Edited School Name',
            'fund_cluster' => 'Edited Fund',
            'accountable_officer_name' => 'Property Custodian',
            'accountable_officer_designation' => 'SCHOOL PROPERTY CUSTODIAN',
            'accountable_officer_station' => 'Main Building, Room 101',
            'approving_official_name' => 'Principal Test',
            'approving_official_designation' => 'PRINCIPAL I',
            'inspection_officer_name' => 'Inspector Test',
            'inspection_designation' => 'Inspection Officer',
            'witness_name' => 'Witness Test',
            'selected_ids' => [$this->item->item_id],
            'overrides' => [
                $this->item->item_id => [
                    'item_name' => 'Edited Laptop',
                    'quantity' => 3,
                    'unit_cost' => 100,
                    'total_cost' => 123,
                    'carrying_amount' => 111,
                    'remarks' => 'Edited remarks',
                    'disposal_sale' => 7,
                    'disposal_total' => 8,
                ],
            ],
        ]);

    $response->assertOk();
    expect($capturedViewData['groups'][0]['items'][0])->toMatchArray([
        'item_name' => 'Edited Laptop',
        'quantity' => 3,
        'unit_cost' => 100,
        'total_cost' => 123,
        'carrying_amount' => 111,
        'remarks' => 'Edited remarks',
        'disposal_sale' => 7,
        'disposal_total' => 8,
    ]);
    expect($capturedViewData)->toMatchArray([
        'entity_name' => 'Edited School Name',
        'fund_cluster' => 'Edited Fund',
        'inspection_designation' => 'Inspection Officer',
    ]);
});

test('IIRUP PDF generation rejects negative numeric overrides', function () {
    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/iirup-pdf', [
            'approving_official_name' => 'Principal Test',
            'inspection_officer_name' => 'Inspector Test',
            'witness_name' => 'Witness Test',
            'selected_ids' => [$this->item->item_id],
            'overrides' => [
                $this->item->item_id => ['quantity' => -1],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors("overrides.{$this->item->item_id}.quantity");
});