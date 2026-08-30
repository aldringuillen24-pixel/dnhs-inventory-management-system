<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::create([
        'role_name' => 'Property Custodian',
    ]);

    $this->propertyCustodian = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'property-custodian',
        'email' => 'custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
});

test('stock-in records an audit ledger entry with before and after quantities', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    $this->actingAs($this->propertyCustodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Workbook',
        'category_id' => $category->category_id,
        'description' => 'Mathematics workbook',
        'ics_no' => 'ICS-002',
        'unit' => 'copy',
        'unit_cost' => 120,
        'date_acquired' => '2026-08-10',
        'quantity' => 25,
    ]);

    $inventory = Inventory::where('item_name', 'Workbook')->firstOrFail();
    $movement = StockMovement::query()
        ->where('inventory_id', $inventory->item_id)
        ->where('movement_type', 'stock_in')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe(25)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_after)->toBe(25)
        ->and($movement->user_id)->toBe($this->propertyCustodian->id);
});

test('a serialized stock-in creates one inventory record per serial number', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);

    $response = $this->actingAs($this->propertyCustodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Laptop',
        'category_id' => $category->category_id,
        'description' => 'Student-use laptop',
        'ics_no' => 'ICS-001',
        'unit' => 'piece',
        'unit_cost' => 25000,
        'date_acquired' => '2026-08-10',
        'quantity' => 3,
        'serial_numbers' => ['SN-001', 'SN-002', 'SN-003'],
    ]);

    $response->assertRedirect(route('propertyCustodian.inventory'));

    expect(Inventory::where('item_name', 'Laptop')->count())->toBe(3);
    expect(Inventory::where('item_name', 'Laptop')->pluck('serial_number')->all())
        ->toEqualCanonicalizing(['SN-001', 'SN-002', 'SN-003']);
    expect(Inventory::where('item_name', 'Laptop')->pluck('quantity')->unique()->all())->toBe([1]);
});

test('a non-serialized stock-in creates one inventory record with the entered quantity', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    $response = $this->actingAs($this->propertyCustodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Workbook',
        'category_id' => $category->category_id,
        'description' => 'Mathematics workbook',
        'ics_no' => 'ICS-002',
        'unit' => 'copy',
        'unit_cost' => 120,
        'date_acquired' => '2026-08-10',
        'quantity' => 25,
    ]);

    $response->assertRedirect(route('propertyCustodian.inventory'));
    $this->assertDatabaseHas('inventory', [
        'item_name' => 'Workbook',
        'quantity' => 25,
        'serial_number' => null,
    ]);
});

test('the inventory table displays unit cost and calculated total cost', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'copy',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Workbook',
        'quantity' => 25,
        'unit_cost' => 120,
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('₱120.00')
        ->assertSee('₱3,000.00');
});

test('the inventory table groups matching item names and sums their quantity and total cost', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'copy',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Workbook',
        'quantity' => 10,
        'unit_cost' => 100,
        'date_acquired' => '2026-08-09',
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'copy',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Workbook',
        'quantity' => 15,
        'unit_cost' => 120,
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('₱2,800.00')
        ->assertSee('25')
        ->assertSee('Workbook', false)
        ->assertDontSee('₱1,000.00')
        ->assertDontSee('₱1,800.00');
});

test('the inventory listing excludes disposed items', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Disposed Laptop',
        'quantity' => 1,
        'status' => 'disposed',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertDontSee('Disposed Laptop', false);
});
