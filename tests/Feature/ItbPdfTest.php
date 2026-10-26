<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function itbPdfCustodian(): User
{
    $role = Role::create(['role_name' => 'Property Custodian']);

    return User::create([
        'role_id' => $role->role_id,
        'first_name' => 'ITB',
        'last_name' => 'Custodian',
        'username' => 'itb-pdf-custodian',
        'email' => 'itb-pdf-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
        'building' => 'Main Building',
        'room' => '101',
    ]);
}

function itbPdfItem(User $custodian, string $status = 'ready_to_dispose'): Inventory
{
    $category = Category::create(['category_name' => 'Equipment']);

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => 'Unserviceable Projector',
        'description' => 'Disposal auction item',
        'quantity' => 2,
        'unit_cost' => 1250,
        'date_acquired' => '2020-01-01',
        'status' => $status,
    ]);
}

test('property custodian can generate an ITB PDF using selected eligible inventory and metadata', function () {
    $pdf = Mockery::mock(DomPdf::class);
    $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->with(Mockery::pattern('/^ITB-/'))->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.itb', Mockery::on(function (array $data): bool {
            return $data['metadata']['school_name'] === 'Test School'
                && $data['metadata']['bid_description'] === 'public bidding for one lot of selected items'
                && $data['metadata']['corporation_requirement'] === 'Updated corporation document requirement'
                && $data['metadata']['proprietorship_requirement'] === 'Updated sole proprietor document requirement'
                && $data['metadata']['business_permit_requirement'] === 'Updated business permit requirement'
                && $data['metadata']['additional_requirements'] === ['Additional supporting document']
                && $data['metadata']['submission_deadline'] === '2026-05-26'
                && $data['metadata']['bid_submission_copies'] === '(3) (original copy (1) and 2 photocopies)'
                && $data['metadata']['pullout_period'] === 'seven (7) calendar days'
                && $data['metadata']['inspection_day'] === '2026-10-26'
                && $data['metadata']['inspection_time'] === '10:00 AM'
                && $data['metadata']['auction_date'] === '2026-10-27'
                && $data['metadata']['auction_time'] === '10:01 AM'
                && $data['metadata']['secretariat_role'] === 'Administrative Assistant II'
                && $data['metadata']['secretariat_role_detail'] === 'Head, BAC Secretariat'
                && $data['metadata']['signatories'][0]['name'] === 'LILIBETH L. CASTRO'
                && $data['metadata']['signatories'][0]['title'] === 'Teacher III'
                && $data['metadata']['signatories'][2]['name'] === 'LEE-RISA M. PRIETOS'
                && $data['metadata']['signatories'][3]['name'] === 'MELISSA L. PRUDENTE'
                && $data['metadata']['signatories'][4]['name'] === 'ROBERTDAWN S. FUAMASING'
                && $data['items'][0]['item_name'] === 'Unserviceable Projector'
                && $data['items'][0]['quantity'] === 2.0
                && $data['items'][0]['unit_cost'] === 1250.0
                && $data['items'][0]['floor_price'] === 4321.5;
        }))
        ->andReturn($pdf);

    $custodian = itbPdfCustodian();
    $item = itbPdfItem($custodian);

    $response = $this->actingAs($custodian)->postJson('/api/custodian/inventory/itb-pdf', [
        'selected_ids' => [$item->item_id],
        'floor_prices' => [4321.5],
        'metadata' => [
            'bid_description' => 'public bidding for one lot of selected items',
            'corporation_requirement' => 'Updated corporation document requirement',
            'proprietorship_requirement' => 'Updated sole proprietor document requirement',
            'business_permit_requirement' => 'Updated business permit requirement',
            'additional_requirements' => ['Additional supporting document'],
            'school_name' => 'Test School',
            'submission_deadline' => '2026-05-26',
            'submission_time' => '10:00 AM',
            'bid_opening_date' => '2026-05-26',
            'bid_opening_time' => '10:00 AM',
            'bid_submission_copies' => '(3) (original copy (1) and 2 photocopies)',
            'pullout_period' => 'seven (7) calendar days',
            'inspection_day' => '2026-10-26',
            'inspection_time' => '10:00 AM',
            'auction_date' => '2026-10-27',
            'auction_time' => '10:01 AM',
            'signatories' => [
                ['name' => 'LILIBETH L. CASTRO', 'title' => 'Teacher III', 'role' => 'BAC Chairperson'],
                ['name' => 'RIZALINA P. MAXILOM', 'title' => 'Principal I', 'role' => 'Chairperson, Disposal Committee'],
                ['name' => 'LEE-RISA M. PRIETOS', 'title' => 'BAC SECRETARIAT', 'role' => ''],
                ['name' => 'MELISSA L. PRUDENTE', 'title' => 'BAC MEMBER', 'role' => ''],
                ['name' => 'ROBERTDAWN S. FUAMASING', 'title' => 'BAC Member', 'role' => ''],
                ['name' => 'CHARISSA MAE I. SAPA', 'title' => 'BAC Member', 'role' => ''],
            ],
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('ITB PDF generation requires at least one selected item', function () {
    $custodian = itbPdfCustodian();

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/itb-pdf', ['selected_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected_ids');
});

test('ITB PDF generation rejects items that are not disposal eligible', function () {
    $custodian = itbPdfCustodian();
    $item = itbPdfItem($custodian, 'available');

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/itb-pdf', ['selected_ids' => [$item->item_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected_ids');
});

test('ITB PDF generation validates supplied ITB metadata', function () {
    $custodian = itbPdfCustodian();
    $item = itbPdfItem($custodian);

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/itb-pdf', [
            'selected_ids' => [$item->item_id],
            'metadata' => [
                'submission_deadline' => 'not-a-date',
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('metadata.submission_deadline');
});

test('ITB generation renders the Blade template as a PDF', function () {
    $custodian = itbPdfCustodian();
    $item = itbPdfItem($custodian);

    $response = $this->actingAs($custodian)->post('/api/custodian/inventory/itb-pdf', [
        'selected_ids' => [$item->item_id],
        'metadata' => [
            'submission_deadline' => '2026-05-26',
            'submission_time' => '10:00 AM',
            'bid_opening_date' => '2026-05-26',
            'bid_opening_time' => '10:00 AM',
            'inspection_day' => '2026-05-25',
            'inspection_time' => '10:00 AM',
            'auction_date' => '2026-05-26',
            'auction_time' => '10:01 AM',
            'form_acquisition_from' => '2026-05-20',
            'form_acquisition_to' => '2026-05-25',
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
