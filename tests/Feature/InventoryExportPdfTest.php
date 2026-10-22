<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create(['role_name' => 'Property Custodian']);
    $this->custodian = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'inventory-export-custodian',
        'email' => 'inventory-export-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
        'building' => 'Main Building',
        'room' => '101',
    ]);
    $category = Category::create(['category_name' => 'Equipment']);
    $this->item = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->custodian->id,
        'item_name' => 'Preview Laptop',
        'unit' => 'piece',
        'unit_cost' => 25000,
        'ics_no' => 'ICS-PREVIEW-001',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-01-01',
        'lifespan_years' => 5,
        'inventory_item_no' => 'INV-ICS-PREVIEW-001',
    ]);
});

test('inventory export preview renders the filtered report as HTML', function () {
    $category = Category::create(['category_name' => 'Other Equipment']);

    Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->custodian->id,
        'item_name' => 'Excluded Monitor',
        'unit' => 'piece',
        'unit_cost' => 25000,
        'ics_no' => 'ICS-OTHER-002',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-01-01',
        'inventory_item_no' => 'INV-ICS-OTHER-002',
    ]);

    $query = http_build_query([
        'preview' => 1,
        'workspace' => 'all',
        'ics_numbers' => ['ICS-PREVIEW-001'],
        'received_from_name' => 'Staff Member',
        'received_from_position' => 'Teacher',
        'received_from_date' => 'October 22, 2026',
        'received_by_name' => 'Custodian Name',
        'received_by_role' => 'Property Custodian',
        'received_by_date' => 'October 23, 2026',
    ]);
    $response = $this->actingAs($this->custodian)->get('/api/custodian/inventory/export-pdf?' . $query);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/html')
        ->and($response->getContent())->toContain('Preview Laptop')
        ->and($response->getContent())->toContain('5 years')
        ->and($response->getContent())->toContain('Entity Name:</strong> <span class="field-line">Dian-ay National High School</span>')
        ->and($response->getContent())->toContain('Signature Over Printed Name')
        ->and($response->getContent())->toContain('Position/Office')
        ->and($response->getContent())->toContain('value="Staff Member"')
        ->and($response->getContent())->toContain('value="Teacher"')
        ->and($response->getContent())->toContain('value="October 22, 2026"')
        ->and($response->getContent())->toContain('value="Custodian Name"')
        ->and($response->getContent())->toContain('value="Property Custodian"')
        ->and($response->getContent())->toContain('value="October 23, 2026"')
        ->and($response->getContent())->toContain('Received by:')
        ->and($response->getContent())->toContain('Received by name')
        ->and($response->getContent())->toContain('value="Property Custodian"')
        ->not->toContain('Total quantity:')
        ->not->toContain('Excluded Monitor');
});

test('inventory PDF export uses edited signature fields', function () {
    $capturedViewData = [];
    $pdf = Mockery::mock(DomPdf::class);
    $pdf->shouldReceive('setOption')->once()->with('defaultMediaType', 'print')->andReturnSelf();
    $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
    $pdf->shouldReceive('download')->once()->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.inventory-export', Mockery::on(function (array $viewData) use (&$capturedViewData): bool {
            $capturedViewData = $viewData;

            return true;
        }))
        ->andReturn($pdf);

    $this->actingAs($this->custodian)->get('/api/custodian/inventory/export-pdf?' . http_build_query([
        'workspace' => 'all',
        'received_from_name' => 'Staff Member',
        'received_from_position' => 'Teacher',
        'received_from_date' => 'October 22, 2026',
        'received_by_name' => 'Custodian Name',
        'received_by_role' => 'Property Custodian',
        'received_by_date' => 'October 23, 2026',
    ]))->assertOk();

    expect($capturedViewData)->toMatchArray([
        'receivedFromName' => 'Staff Member',
        'receivedFromPosition' => 'Teacher',
        'receivedFromDate' => 'October 22, 2026',
        'preparedBy' => 'Custodian Name',
        'receivedByRole' => 'Property Custodian',
        'receivedByDate' => 'October 23, 2026',
        'isPreview' => false,
    ]);
});

test('inventory export still downloads a PDF report', function () {
    Inventory::create([
        'category_id' => $this->item->category_id,
        'user_id' => $this->custodian->id,
        'item_name' => 'Second ICS Laptop',
        'unit' => 'piece',
        'unit_cost' => 25000,
        'ics_no' => 'ICS-PREVIEW-002',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-01-01',
        'lifespan_years' => 5,
        'inventory_item_no' => 'INV-ICS-PREVIEW-002',
    ]);

    $response = $this->actingAs($this->custodian)->get('/api/custodian/inventory/export-pdf?workspace=all');

    $response->assertOk();
    $pdfContents = $response->getContent();
    preg_match_all('/\/Type\s*\/Page\b/', $pdfContents, $pageMatches);
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($pdfContents, 0, 4))->toBe('%PDF')
        ->and(count($pageMatches[0]))->toBe(2);
});
