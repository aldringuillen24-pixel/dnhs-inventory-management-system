<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
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

test('stock-in creates a separate QR-coded inventory record for each QR-eligible unit', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
        'requires_qr_code' => true,
    ]);

    $response = $this->actingAs($this->custodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Air Printer',
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'unit_cost' => 15000,
        'date_acquired' => '2026-09-01',
        'quantity' => 4,
    ]);

    $items = Inventory::where('item_name', 'Air Printer')->orderBy('item_id')->get();

    $response->assertRedirect(route('propertyCustodian.inventory'));
    expect($items)->toHaveCount(4)
        ->and($items->pluck('quantity')->unique()->all())->toBe([1])
        ->and($items->pluck('qr_code')->unique())->toHaveCount(4)
        ->and($items->every(fn (Inventory $item) => str_starts_with($item->qr_code, 'dnhs_qr_')))->toBeTrue()
        ->and($items->pluck('inventory_item_no')->unique())->toHaveCount(4);

    $inventoryResponse = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory'))
        ->assertOk();
    $listedSourceItems = collect($inventoryResponse->json('inventoryItems'))
        ->flatMap(fn (array $group) => $group['sourceItems'] ?? [])
        ->where('item_name', 'Air Printer');

    expect($listedSourceItems)->toHaveCount(4);
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

test('qr lookup includes the assignee and return details for an assigned item', function () {
    $category = qrCategory();
    $token = 'dnhs_qr_assigned_testtoken';
    $item = seedInventoryItem($this->custodian, $category, $token);
    $item->update([
        'quantity' => 0,
        'status' => 'assigned',
    ]);
    Transaction::create([
        'user_id' => $this->custodian->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-01',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=' . $token)
        ->assertOk()
        ->assertJsonPath('quantity', 1)
        ->assertJsonPath('assigned_to', 'Property Custodian (1)')
        ->assertJsonPath('return_assignments.0.recipient', 'Property Custodian')
        ->assertJsonPath('return_assignments.0.remaining_quantity', 1)
        ->assertJsonPath('return_assignments.0.inventory_id', $item->item_id);
});

    test('Vue qr lookup api returns the scanned item data', function () {
        $category = qrCategory();
        $token = 'dnhs_qr_vue_testtoken123456';
        $item = seedInventoryItem($this->custodian, $category, $token);

        $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory.qr.lookup', ['token' => $token]))
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('item_id', $item->item_id)
        ->assertJsonPath('item_name', 'Test Laptop')
        ->assertJsonPath('quantity', 1)
        ->assertJsonPath('unit', 'piece');
    });

test('Vue inventory data includes QR tokens for eligible source items', function () {
        $category = qrCategory();
        $eligibleItem = seedInventoryItem($this->custodian, $category, 'dnhs_qr_inventory_token123');

        $response = $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory'))
            ->assertOk();

        $sourceItems = collect($response->json('inventoryItems'))
            ->flatMap(fn (array $group) => $group['sourceItems'] ?? []);

        expect($sourceItems->firstWhere('item_id', $eligibleItem->item_id)['qr_code'] ?? null)
            ->toBe('dnhs_qr_inventory_token123');
});

// ── Test 4: QR lookup returns 404 for an unknown token ───────────────────────

test('qr lookup returns 404 for an unknown token', function () {
    $response = $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=dnhs_qr_doesnotexist');

    $response->assertNotFound()
        ->assertJsonFragment(['found' => false]);
});

// ── Retired standalone label routes ─────────────────────────────────────────
// QR printing now happens client-side in the SPA record modal, so the
// standalone print-qr / qr-label routes are gone. These tests lock in
// the retirement (404) and prove the data the printer needs is still
// served by the lookup and inventory APIs.

test('qr label page is retired and returns 404', function () {
    $category = qrCategory();
    $token    = 'dnhs_qr_labeltest1234567890ab';
    $item     = seedInventoryItem($this->custodian, $category, $token);

    $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/qr-label')
        ->assertNotFound();

    // The lookup data the client-side printer flow needs is still available.
    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=' . $token)
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('inventory_item_no', $item->inventory_item_no);
});

// ── Test 6: QR label route returns 404 for items without a qr_code ───────────

test('qr label page returns 404 for an item without a qr_code', function () {
    $category = noQrCategory();
    $item     = seedInventoryItem($this->custodian, $category, null);

    $response = $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/qr-label');

    $response->assertNotFound();
});

test('print-qr json endpoint is retired in favor of client-side printing', function () {
    $category = qrCategory();
    $token    = 'dnhs_qr_printjson1234567890';
    $item     = seedInventoryItem($this->custodian, $category, $token);

    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/qr/lookup?token=' . $token)
        ->assertOk();

    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/' . $item->item_id . '/print-qr')
        ->assertNotFound();
});

test('print-qr group route is retired', function () {
    $category = qrCategory();
    $first = seedInventoryItem($this->custodian, $category, 'dnhs_qr_group_first123');
    seedInventoryItem($this->custodian, $category, 'dnhs_qr_group_second123', 'INV-000002');

    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/' . $first->item_id . '/print-qr?single=1')
        ->assertNotFound();

    $this->actingAs($this->custodian)
        ->getJson('/property-custodian/inventory/' . $first->item_id . '/print-qr')
        ->assertNotFound();
});

test('print-qr page route is retired', function () {
    $category = qrCategory();
    $item = seedInventoryItem($this->custodian, $category, 'dnhs_qr_printqr1234567890');

    $this->actingAs($this->custodian)
        ->get('/property-custodian/inventory/' . $item->item_id . '/print-qr')
        ->assertNotFound();
});
