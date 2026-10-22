<?php

use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('property custodian can preview the RFQ PDF', function () {
    $pdf = Mockery::mock(DomPdf::class);
    $canvas = Mockery::mock(\Dompdf\Canvas::class);
    $canvas->shouldReceive('page_script')->once()->with(Mockery::type(Closure::class));
    $dompdf = Mockery::mock(\Dompdf\Dompdf::class);
    $dompdf->shouldReceive('getCanvas')->once()->andReturn($canvas);
    $pdf->shouldReceive('getDomPDF')->once()->andReturn($dompdf);
    $pdf->shouldReceive('setPaper')->once()->with('letter', 'portrait')->andReturnSelf();
    $pdf->shouldReceive('render')->once()->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->with('RFQ-preview.pdf')->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')->once()->with('pdfs.rfq')->andReturn($pdf);

    $role = Role::create(['role_name' => 'Property Custodian']);

    $custodian = User::create([
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

    $response = $this->actingAs($custodian)->get('/property-custodian/inventory/rfq-pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});