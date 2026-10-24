<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\MaintenanceRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function monitorSchoolHead(): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'School Head'])->role_id,
        'username' => 'monitor-head',
    ]);
}

function monitorItem(User $custodian, string $name, array $overrides = []): Inventory
{
    $category = Category::firstOrCreate(
        ['category_name' => 'Monitor Supplies'],
        ['requires_serial_number' => false],
    );

    return Inventory::create(array_merge([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => $name,
        'quantity' => 4,
        'unit_cost' => 10,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ], $overrides));
}

test('the overview monitor lists every state with workbench-honest quantities', function () {
    $head = monitorSchoolHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'monitor-custodian',
    ]);
    $holder = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'End User'])->role_id,
        'first_name' => 'Hold',
        'last_name' => 'Erson',
    ]);

    $penItem = monitorItem($custodian, 'Ballpoint Pen');
    monitorItem($custodian, 'Lent Laptop', [
        'status' => 'assigned',
        'quantity' => 0,
        'assigned_to_user_id' => $holder->id,
    ]);
    monitorItem($custodian, 'Faulty Projector', ['status' => 'under_repair']);

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.inventory.overview'))
        ->assertOk()
        ->json();

    expect($payload['exportCategories'])->toContain([
        'category_id' => $penItem->category_id,
        'category_name' => 'Monitor Supplies',
    ]);

    $rows = collect($payload['monitorRows']);
    expect($rows->pluck('status')->unique()->sort()->values()->all())
        ->toEqualCanonicalizing(['available', 'assigned', 'under_repair']);

    $laptop = $rows->firstWhere('item_name', 'Lent Laptop');
    expect($laptop['holder_name'])->toBe('Hold Erson')
        ->and($laptop['status_label'])->toBe('Assigned');

    $pen = $rows->firstWhere('item_name', 'Ballpoint Pen');
    expect($pen['holder_name'])->toBeNull()
        ->and($pen['quantity'])->toBe(4);

    // Read-shaped only: no cost internals, no pivot data, no writable fields.
    expect(array_keys($laptop))->toEqualCanonicalizing([
        'item_id', 'item_name', 'inventory_item_no', 'category', 'unit',
        'status', 'status_label', 'quantity', 'holder_name', 'expected_end_date',
    ]);
});

test('the overview separates active incident reports from investigations', function () {
    $head = monitorSchoolHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'incident-custodian',
    ]);
    $reporter = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'End User'])->role_id,
        'first_name' => 'Report',
        'last_name' => 'Person',
    ]);
    $item = monitorItem($custodian, 'Incident Laptop');

    $reported = MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 2,
        'reported_by' => $reporter->id,
        'diagnosis' => 'damaged',
        'incident_status' => 'reported',
        'issue_description' => 'Screen is cracked',
    ]);
    $investigation = MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'lost',
        'incident_status' => 'under_investigation',
        'issue_description' => 'Unit cannot be located',
    ]);
    MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'stolen',
        'incident_status' => 'resolved',
        'issue_description' => 'Resolved case',
    ]);

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.inventory.overview'))
        ->assertOk()
        ->json();

    expect($payload['incidentReports'])->toHaveCount(1)
        ->and($payload['incidentReports'][0])->toMatchArray([
            'id' => $reported->id,
            'item_name' => 'Incident Laptop',
            'type' => 'damaged',
            'status' => 'reported',
            'quantity' => 2,
            'reporter' => 'Report Person',
            'details' => 'Screen is cracked',
        ])
        ->and($payload['investigations'])->toHaveCount(1)
        ->and($payload['investigations'][0])->toMatchArray([
            'id' => $investigation->id,
            'type' => 'lost',
            'status' => 'under_investigation',
            'quantity' => 1,
        ]);
});

test('school head can preview inventory slips filtered by incident workspace and ICS', function () {
    $head = monitorSchoolHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'export-custodian',
    ]);
    $reporter = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'End User'])->role_id,
        'username' => 'export-reporter',
    ]);
    $investigationItem = monitorItem($custodian, 'Investigation Laptop', [
        'ics_no' => 'ICS-EXPORT-1',
    ]);
    $reportedItem = monitorItem($custodian, 'Reported Projector', [
        'ics_no' => 'ICS-EXPORT-2',
    ]);
    $unrelatedItem = monitorItem($custodian, 'Unrelated Chair', [
        'ics_no' => 'ICS-EXPORT-1',
    ]);

    MaintenanceRecord::create([
        'inventory_id' => $investigationItem->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'lost',
        'incident_status' => 'under_investigation',
    ]);
    MaintenanceRecord::create([
        'inventory_id' => $reportedItem->item_id,
        'quantity' => 1,
        'reported_by' => $reporter->id,
        'diagnosis' => 'damaged',
        'incident_status' => 'reported',
    ]);

    $this->actingAs($head)
        ->getJson(route('api.school-head.inventory.ics-numbers'))
        ->assertOk()
        ->assertJsonFragment(['ICS-EXPORT-1'])
        ->assertJsonFragment(['ICS-EXPORT-2']);

    $this->get(route('api.school-head.inventory.export-pdf', [
        'workspace' => 'investigations',
        'ics_numbers' => ['ICS-EXPORT-1'],
        'preview' => '1',
    ]))
        ->assertOk()
        ->assertSee('Investigation Laptop')
        ->assertDontSee('Reported Projector')
        ->assertDontSee('Unrelated Chair');
});
