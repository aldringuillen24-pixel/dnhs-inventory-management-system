<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rfqCustodian(): User
{
    $role = Role::create(['role_name' => 'Property Custodian']);

    return User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'rfq-custodian',
        'email' => 'rfq-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
        'building' => 'Main Building',
        'room' => '101',
    ]);
}

function rfqEligibleItem(User $custodian, string $status = 'ready_to_dispose'): Inventory
{
    $category = Category::create(['category_name' => 'Equipment']);

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => 'Unserviceable Projector',
        'description' => 'Disposed asset for auction',
        'quantity' => 2,
        'unit_cost' => 1250,
        'date_acquired' => '2020-01-01',
        'status' => $status,
    ]);
}

test('property custodian can generate a dynamic RFQ PDF from selected eligible items', function () {
    $pdf = Mockery::mock(DomPdf::class);
    $canvas = Mockery::mock(\Dompdf\Canvas::class);
    $canvas->shouldReceive('page_script')->once()->with(Mockery::type(Closure::class));
    $dompdf = Mockery::mock(\Dompdf\Dompdf::class);
    $dompdf->shouldReceive('getCanvas')->once()->andReturn($canvas);
    $pdf->shouldReceive('getDomPDF')->once()->andReturn($dompdf);
    $pdf->shouldReceive('setPaper')->once()->with('letter', 'portrait')->andReturnSelf();
    $pdf->shouldReceive('render')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->with(Mockery::pattern('/^RFQ-/'))->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.rfq', Mockery::on(function (array $data): bool {
            return $data['metadata']['quotation_no'] === 'RFQ-TEST-01'
                && $data['items'][0]['description'] === 'Auction lot'
                && (float) $data['items'][0]['unit_price'] === 1250.0
                && $data['total'] === 2500.0
                && $data['floorPrice'] === 1800.0
                && $data['requirements'][0]['description'] === 'Valid business permit'
                && $data['terms']['price_validity'] === 'Edited validity period';
        }))
        ->andReturn($pdf);

    $custodian = rfqCustodian();
    $item = rfqEligibleItem($custodian);

    $response = $this->actingAs($custodian)->postJson('/api/custodian/inventory/rfq-pdf', [
        'selected_ids' => [$item->item_id],
        'quotation_no' => 'RFQ-TEST-01',
        'total' => 2500,
        'floor_price' => 1800,
        'overrides' => [
            $item->item_id => [
                'description' => 'Auction lot',
                'unit_price' => 1250,
                'quantity' => 2,
                'price_offered' => 900,
                'compliance' => 'Comply',
            ],
        ],
        'requirements' => [
            ['description' => 'Valid business permit', 'compliance' => 'Comply'],
        ],
        'terms' => ['price_validity' => 'Edited validity period'],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('RFQ generation requires at least one selected item', function () {
    $custodian = rfqCustodian();

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/rfq-pdf', ['selected_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected_ids');
});

test('RFQ generation renders the dynamic Blade view as a PDF', function () {
    $custodian = rfqCustodian();
    $item = rfqEligibleItem($custodian);

    $response = $this->actingAs($custodian)->post('/api/custodian/inventory/rfq-pdf', [
        'selected_ids' => [$item->item_id],
        'quotation_no' => 'RFQ-RENDER-01',
        'overrides' => [
            $item->item_id => ['description' => 'Render check item'],
        ],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('RFQ generation rejects items that are not disposal eligible', function () {
    $custodian = rfqCustodian();
    $item = rfqEligibleItem($custodian, 'available');

    $this->actingAs($custodian)
        ->postJson('/api/custodian/inventory/rfq-pdf', ['selected_ids' => [$item->item_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected_ids');
});
