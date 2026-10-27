<?php

use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create(['role_name' => 'Property Custodian']);

    $this->custodian = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'rcpi-custodian',
        'email' => 'rcpi-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('RCPI-PPE PDF preview renders the sample rows and calculates variances', function () {
    $capturedViewData = [];
    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
    $pdf->shouldReceive('stream')->once()->with(Mockery::type('string'))->andReturn(
        response()->make('pdf', 200, ['Content-Type' => 'application/pdf']),
    );

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.rcpi-ppe', Mockery::on(function (array $viewData) use (&$capturedViewData) {
            $capturedViewData = $viewData;

            return true;
        }))
        ->andReturn($pdf);

    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/rcpi-ppe-pdf', [
            'inventory_type' => 'Sports Equipment',
            'report_date' => '2026-10-26',
            'fund_cluster' => '01',
            'accountable_person' => 'Property Custodian',
            'accountability_date' => '2026-01-01',
            'items' => [
                [
                    'article' => 'Basketball',
                    'description' => 'Rubber basketball',
                    'stock_number' => 'SAMPLE-001',
                    'unit' => 'Piece',
                    'unit_value' => 850,
                    'book_quantity' => 4,
                    'counted_quantity' => 3,
                    'condition' => 'Good',
                    'whereabouts' => 'Sports room',
                ],
                [
                    'article' => 'Volleyball',
                    'unit_value' => 720,
                    'book_quantity' => 2,
                    'counted_quantity' => 4,
                ],
            ],
        ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf')
        ->and($capturedViewData['items'][0])->toMatchArray([
            'variance_quantity' => 1,
            'variance_value' => 850,
            'variance_type' => 'Shortage',
        ])
        ->and($capturedViewData['items'][1])->toMatchArray([
            'variance_quantity' => 2,
            'variance_value' => 1440,
            'variance_type' => 'Overage',
        ]);
});

test('RCPI-PPE PDF preview rejects negative quantities and unit values', function () {
    $response = $this->actingAs($this->custodian)
        ->postJson('/property-custodian/inventory/rcpi-ppe-pdf', [
            'inventory_type' => 'Sports Equipment',
            'report_date' => '2026-10-26',
            'items' => [[
                'article' => 'Basketball',
                'unit_value' => -10,
                'book_quantity' => 2,
                'counted_quantity' => -1,
            ]],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'items.0.unit_value',
            'items.0.counted_quantity',
        ]);
});

test('RCPI-PPE API route returns a rendered PDF document', function () {
    $response = $this->actingAs($this->custodian)
        ->postJson('/api/custodian/inventory/rcpi-ppe-pdf', [
            'inventory_type' => 'Sports Equipment',
            'report_date' => '2026-10-26',
            'items' => [[
                'article' => 'Basketball',
                'description' => 'Rubber basketball',
                'unit' => 'Piece',
                'unit_value' => 850,
                'book_quantity' => 4,
                'counted_quantity' => 4,
            ]],
        ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});
