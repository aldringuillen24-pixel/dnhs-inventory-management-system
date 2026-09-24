<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Shared setup ─────────────────────────────────────────────────────────────

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
    ]);
});

// ── Shared helpers ────────────────────────────────────────────────────────────

function qrCategory(): Category
{
    return Category::create([
        'category_name'          => 'ICT Equipment',
        'requires_serial_number' => true,
        'requires_qr_code'       => true,
    ]);
}

function noQrCategory(): Category
{
    return Category::create([
        'category_name'          => 'Learning Resources',
        'requires_serial_number' => false,
        'requires_qr_code'       => false,
    ]);
}

function seedInventoryItem(User $custodian, Category $category, ?string $qrCode = null, string $inventoryItemNo = 'INV-000001'): Inventory
{
    return Inventory::create([
        'category_id'       => $category->category_id,
        'user_id'           => $custodian->id,
        'item_name'         => 'Test Laptop',
        'unit'              => 'piece',
        'unit_cost'         => 25000,
        'ics_no'            => 'ICS-TEST-001',
        'quantity'          => 1,
        'status'            => 'available',
        'date_acquired'     => '2026-01-01',
        'inventory_item_no' => $inventoryItemNo,
        'qr_code'           => $qrCode,
    ]);
}

// ── Test 1: Stock-in for a QR-eligible category generates a qr_code token ────

test('stock-in for a qr-eligible category generates a unique qr_code token', function () {
    $category = qrCategory();

    $this->actingAs($this->custodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name'      => 'Laptop',
        'category_id'    => $category->category_id,
        'description'    => 'Test laptop',
        'unit'           => 'piece',
        'unit_cost'      => 25000,
        'date_acquired'  => '2026-09-01',
        'quantity'       => 1,
        'serial_numbers' => ['SN-LAPTOP-001'],
    ]);

    $item = Inventory::where('item_name', 'Laptop')->firstOrFail();

    expect($item->qr_code)->not->toBeNull()
        ->toStartWith('dnhs_qr_');
});

// ── Test 2: Stock-in for a non-QR category leaves qr_code null ───────────────

test('stock-in for a non-qr category leaves qr_code as null', function () {
    $category = noQrCategory();

    $this->actingAs($this->custodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name'     => 'Workbook',
        'category_id'   => $category->category_id,
        'description'   => 'Math workbook',
        'unit'          => 'copy',
        'unit_cost'     => 120,
        'date_acquired' => '2026-09-01',
        'quantity'      => 10,
    ]);

    $item = Inventory::where('item_name', 'Workbook')->firstOrFail();

    expect($item->qr_code)->toBeNull();
});

// ── Test 3: QR lookup returns item data for a valid token ─────────────────────

test('qr lookup returns item data for a valid token', function () {
    $category = qrCategory();
    $token    = 'dnhs_qr_testtoken1234567890ab';
    $item     = seedInventoryItem($this->custodian, $category, $token);

    $response = $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=' . $token);

    $response->assertOk()
        ->assertJsonFragment(['found' => true])
        ->assertJsonFragment(['inventory_item_no' => $item->inventory_item_no])
        ->assertJsonFragment(['item_name' => 'Test Laptop'])
        ->assertJsonFragment(['quantity' => 1])
        ->assertJsonFragment(['unit' => 'piece'])
        ->assertJsonFragment(['ics_no' => 'ICS-TEST-001']);
});

// ── Test 4: QR lookup returns 404 for an unknown token ───────────────────────

test('qr lookup returns 404 for an unknown token', function () {
    $response = $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=dnhs_qr_doesnotexist');

    $response->assertNotFound()
        ->assertJsonFragment(['found' => false]);
});

// ── Test 5: QR label route returns 200 for items with a qr_code ──────────────

test('qr label page is accessible for an item that has a qr_code', function () {
    $category = qrCategory();
    $token    = 'dnhs_qr_labeltest1234567890ab';
    $item     = seedInventoryItem($this->custodian, $category, $token);

    $response = $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/qr-label');

    $response->assertOk()
        ->assertSee($item->inventory_item_no)
        ->assertSee($item->item_name);
});

// ── Test 6: QR label route returns 404 for items without a qr_code ───────────

test('qr label page returns 404 for an item without a qr_code', function () {
    $category = noQrCategory();
    $item     = seedInventoryItem($this->custodian, $category, null);

    $response = $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/qr-label');

    $response->assertNotFound();
});

test('property custodian can retrieve print-label JSON data after scanning a valid QR code', function () {
    $category = qrCategory();
    $token    = 'dnhs_qr_printjson1234567890';
    $item     = seedInventoryItem($this->custodian, $category, $token);

    $lookupResponse = $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=' . $token);

    $lookupResponse->assertOk();

    $printResponse = $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/' . $item->item_id . '/print-qr');

    $printResponse->assertOk()
        ->assertJsonPath('activeItem.item_id', $item->item_id)
        ->assertJsonPath('activeItem.qr_code', $token)
        ->assertJsonPath('items.0.item_id', $item->item_id);
});

test('print-qr route is accessible and displays two-column workspace', function () {
    $category = qrCategory();
    $item = seedInventoryItem($this->custodian, $category, 'dnhs_qr_printqr1234567890');

    $response = $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/print-qr');

    $response->assertOk()
        ->assertSee('Print QR Code')
        ->assertSee($item->inventory_item_no)
        ->assertSee($item->item_name);
});
