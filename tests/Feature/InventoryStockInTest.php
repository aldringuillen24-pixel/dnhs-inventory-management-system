<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\InventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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

test('inventory seeder creates 100 items across available categories and can be rerun', function () {
    $adminRole = Role::create(['role_name' => 'Administrator']);
    $admin = User::create([
        'role_id' => $adminRole->role_id,
        'first_name' => 'System',
        'last_name' => 'Admin',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    app(CategorySeeder::class)->run();
    app(InventorySeeder::class)->run();

    $demoItems = Inventory::query()->where('inventory_item_no', 'like', 'INV-DEMO-%');

    expect($demoItems->count())->toBe(100)
        ->and($demoItems->distinct('category_id')->count('category_id'))->toBe(Category::count())
        ->and(Inventory::query()->whereHas('category', fn ($query) => $query->where('requires_serial_number', true))
            ->whereNotNull('serial_number')->count())->toBeGreaterThan(0)
        ->and(Inventory::query()->whereHas('category', fn ($query) => $query->where('requires_serial_number', true))
            ->whereNull('serial_number')->count())->toBeGreaterThan(0)
        ->and(Inventory::query()->whereHas('category', fn ($query) => $query->where('requires_serial_number', false))
            ->whereNotNull('serial_number')->count())->toBe(0)
        ->and(Inventory::query()->where('item_name', 'Laptop')->whereNotNull('serial_number')->exists())->toBeTrue()
        ->and(Inventory::query()->where('item_name', 'Hot Plate')->whereNull('serial_number')->exists())->toBeTrue()
        ->and(Inventory::query()->where('inventory_item_no', 'like', 'INV-DEMO-%')->whereNull('lifespan_years')->count())->toBe(0)
        ->and(Inventory::query()->where('inventory_item_no', 'like', 'INV-DEMO-%')->whereNull('expected_end_date')->count())->toBe(0)
        ->and(Inventory::query()->where('inventory_item_no', 'INV-DEMO-001')->value('lifespan_years'))->toBe(10)
        ->and(Inventory::query()->where('inventory_item_no', 'INV-DEMO-001')->firstOrFail()->expected_end_date->format('Y-m-d'))
        ->toBe('2036-08-26');

    app(InventorySeeder::class)->run();

    expect(Inventory::query()->where('inventory_item_no', 'like', 'INV-DEMO-%')->count())->toBe(100)
        ->and(Inventory::query()->where('user_id', $admin->id)->count())->toBe(100);
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
        'building' => 'Main Building',
        'room' => 'Supply Room',
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
        ->and($movement->user_id)->toBe($this->propertyCustodian->id)
        ->and($inventory->building)->toBe('Main Building')
        ->and($inventory->room)->toBe('Supply Room')
        ->and($movement->from_building)->toBeNull()
        ->and($movement->to_building)->toBe('Main Building')
        ->and($movement->to_room)->toBe('Supply Room');

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['total'] === 25
            && $metrics['available'] === 25
            && $metrics['assigned'] === 0);
    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertViewHas('availableInventoryItems', fn ($items) => $items->first()['quantity'] === 25);
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

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('Serial Number', false)
        ->assertSee('SN-001', false)
        ->assertSee('SN-002', false)
        ->assertSee('+1 more', false);

    $this->get(route('propertyCustodian.inventory', ['workspace' => 'available']))
        ->assertOk()
        ->assertSee('Serial Number', false)
        ->assertSee('SN-001', false)
        ->assertSee('+1 more', false);
});

test('the inventory edit modal exposes every serial number in a serialized group', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
    ]);

    $this->actingAs($this->propertyCustodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Desktop Computer',
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'unit_cost' => 30000,
        'date_acquired' => '2026-08-10',
        'quantity' => 2,
        'serial_numbers' => ['SN-DESKTOP-001', 'SN-DESKTOP-002'],
    ]);

    $response = $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'));

    $response->assertOk()
        ->assertDontSee('id="edit-building-', false)
        ->assertDontSee('id="edit-room-', false)
        ->assertSee('Serial numbers', false)
        ->assertSee('SN-DESKTOP-001', false)
        ->assertSee('SN-DESKTOP-002', false);
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

test('stock-in copies the category lifespan default and calculates the expected end date', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => true,
        'default_lifespan_years' => 5,
    ]);

    $this->actingAs($this->propertyCustodian)->post(route('propertyCustodian.inventory.stock-in'), [
        'item_name' => 'Laptop',
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'unit_cost' => 25000,
        'date_acquired' => '2026-08-10',
        'quantity' => 1,
        'serial_numbers' => ['SN-LIFESPAN-001'],
    ]);

    $inventory = Inventory::where('item_name', 'Laptop')->firstOrFail();

    expect($inventory->lifespan_years)->toBe(5)
        ->and($inventory->expected_end_date->format('Y-m-d'))->toBe('2031-08-10');
});

test('inventory updates accept a lifespan override and recalculate the expected end date', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
        'default_lifespan_years' => 3,
    ]);

    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Printer',
        'quantity' => 1,
        'unit_cost' => 12000,
        'date_acquired' => '2026-01-15',
        'lifespan_years' => 3,
        'expected_end_date' => '2029-01-15',
        'building' => 'Main Building',
        'room' => 'Room 12',
        'status' => 'available',
    ]);

    $response = $this->actingAs($this->propertyCustodian)->patch(
        route('propertyCustodian.inventory.update', $inventory),
        [
            'item_name' => 'Printer',
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'unit_cost' => 12000,
            'date_acquired' => '2026-01-15',
            'lifespan_years' => 6,
            'quantity' => 1,
        ],
    );

    $response->assertRedirect(route('propertyCustodian.inventory'));
    $inventory->refresh();

    expect($inventory->lifespan_years)->toBe(6)
        ->and($inventory->expected_end_date->format('Y-m-d'))->toBe('2032-01-15')
        ->and($inventory->building)->toBe('Main Building')
        ->and($inventory->room)->toBe('Room 12');
});

test('inventory quantity edits update both pages and record the stock delta', function () {
    $category = Category::create([
        'category_name' => 'Office Equipment',
        'requires_serial_number' => false,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Printer',
        'quantity' => 5,
        'unit_cost' => 12000,
        'date_acquired' => '2026-01-15',
        'status' => 'available',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->patch(route('propertyCustodian.inventory.update', $inventory), [
            'item_name' => 'Printer',
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'unit_cost' => 12000,
            'date_acquired' => '2026-01-15',
            'quantity' => 3,
        ])
        ->assertRedirect(route('propertyCustodian.inventory'));

    $movement = StockMovement::where('inventory_id', $inventory->item_id)->sole();
    expect($inventory->fresh()->quantity)->toBe(3)
        ->and($movement->movement_type)->toBe('stock_out')
        ->and($movement->quantity)->toBe(2)
        ->and($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(3);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertViewHas('inventoryMetrics', fn ($metrics) => $metrics['available'] === 3)
        ->assertViewHas('allInventoryPage', fn ($page) => (int) $page->getCollection()->first()->quantity === 3);

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.transactions'))
        ->assertViewHas('availableInventoryItems', fn ($items) => $items->first()['quantity'] === 3);
});

test('lifespan status follows the documented date boundaries', function () {
    Carbon::setTestNow('2026-09-19');

    $category = Category::create([
        'category_name' => 'Furniture and Fixtures',
        'requires_serial_number' => false,
    ]);

    $attributes = [
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Desk',
        'quantity' => 1,
        'unit_cost' => 1000,
        'date_acquired' => '2026-01-01',
        'status' => 'available',
    ];

    $expired = Inventory::create([...$attributes, 'expected_end_date' => '2026-09-19']);
    $approaching = Inventory::create([...$attributes, 'item_name' => 'Chair', 'expected_end_date' => '2027-09-19']);
    $healthy = Inventory::create([...$attributes, 'item_name' => 'Cabinet', 'expected_end_date' => '2027-09-20']);
    $unset = Inventory::create([...$attributes, 'item_name' => 'Shelf']);

    expect($expired->lifespan_status)->toBe('end_of_useful_life')
        ->and($expired->lifespan_remaining_percentage)->toBe(0)
        ->and($approaching->lifespan_status)->toBe('approaching_end_of_life')
        ->and($approaching->lifespan_remaining_percentage)->toBeGreaterThan(0)
        ->and($approaching->lifespan_remaining_percentage)->toBeLessThan(100)
        ->and($healthy->lifespan_status)->toBe('healthy')
        ->and($healthy->lifespan_remaining_percentage)->toBeGreaterThan(0)
        ->and($healthy->lifespan_remaining_percentage)->toBeLessThan(101)
        ->and($unset->lifespan_status)->toBeNull()
        ->and($unset->lifespan_remaining_percentage)->toBeNull();

    Carbon::setTestNow();
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

test('the inventory listing provides disposed items in the disposed tab', function () {
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
        ->assertSee('Disposed Laptop', false)
        ->assertSee('Disposed', false)
        ->assertSee('ready_to_dispose', false);
});

test('all inventory includes disposed records and ignores removed filter query parameters', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    $items = [
        ['item_name' => 'Available Projector', 'status' => 'available'],
        ['item_name' => 'Assigned Projector', 'status' => 'assigned'],
        ['item_name' => 'Inspection Projector', 'status' => 'under_inspection'],
        ['item_name' => 'Maintenance Projector', 'status' => 'under_maintenance'],
        ['item_name' => 'Disposed Projector', 'status' => 'disposed'],
    ];

    foreach ($items as $item) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->propertyCustodian->id,
            'quantity' => 1,
            'date_acquired' => '2026-08-10',
            ...$item,
        ]);
    }

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertViewHas('allInventoryPage', fn ($page) => $page->getCollection()->contains('item_name', 'Disposed Projector'));

    $this->get(route('propertyCustodian.inventory', [
        'workspace' => 'all',
        'search' => 'does-not-match',
        'category' => $category->category_id,
        'status' => 'assigned',
        'condition' => 'fair',
    ]))
        ->assertOk()
        ->assertViewHas('activeWorkspace', 'all')
        ->assertViewHas('allInventoryPage', fn ($page) => $page->total() === 5)
        ->assertDontSee('Search inventory...')
        ->assertDontSee('All Categories')
        ->assertDontSee('All Statuses')
        ->assertDontSee('All Conditions')
        ->assertDontSee('applyFilters()');
});

test('all inventory only shows edit and delete actions for available records', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    foreach ([
        ['item_name' => 'Available Asset', 'status' => 'available'],
        ['item_name' => 'Assigned Asset', 'status' => 'assigned'],
    ] as $item) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->propertyCustodian->id,
            'quantity' => 1,
            'date_acquired' => '2026-08-10',
            ...$item,
        ]);
    }

    $content = $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->getContent();

    $availableStart = strpos($content, 'Available Asset');
    $assignedStart = strpos($content, 'Assigned Asset');
    $availableRow = substr($content, $availableStart, strpos($content, '</tr>', $availableStart) - $availableStart);
    $assignedRow = substr($content, $assignedStart, strpos($content, '</tr>', $assignedStart) - $assignedStart);

    expect($availableRow)->toContain('title="Edit Item"')
        ->and($availableRow)->toContain('title="Delete Item"')
        ->and($assignedRow)->not->toContain('title="Edit Item"')
        ->and($assignedRow)->not->toContain('title="Delete Item"');
});

test('the inventory listing paginates after 25 grouped items', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
    ]);

    foreach (range(1, 26) as $number) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->propertyCustodian->id,
            'item_name' => sprintf('Asset %02d', $number),
            'quantity' => 1,
            'status' => 'available',
            'date_acquired' => '2026-08-10',
        ]);
    }

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('all_page=2', false);
});

test('the maintenance modal includes available items beyond the first page', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);

    foreach (range(1, 26) as $number) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->propertyCustodian->id,
            'item_name' => sprintf('Maintenance Asset %02d', $number),
            'quantity' => 1,
            'status' => 'available',
            'date_acquired' => '2026-08-10',
        ]);
    }

    $this->actingAs($this->propertyCustodian)
        ->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('Maintenance Asset 26', false)
        ->assertSee('name="maintenance_item_choice"', false);
});

test('failed stock-in validation reopens the modal with the error summary visible', function () {
    $category = Category::create([
        'category_name' => 'Learning Resources',
        'requires_serial_number' => false,
    ]);

    $response = $this->actingAs($this->propertyCustodian)
        ->from(route('propertyCustodian.inventory'))
        ->post(route('propertyCustodian.inventory.stock-in'), [
            'item_name' => '',
            'category_id' => $category->category_id,
            'description' => 'Bad entry',
            'ics_no' => 'ICS-SET',
            'unit' => 'copy',
            'unit_cost' => 'invalid',
            'date_acquired' => '2026-08-10',
            'quantity' => 0,
        ]);

    $response->assertRedirect(route('propertyCustodian.inventory'));
    $response->assertSessionHasErrors(['item_name', 'unit_cost', 'quantity']);

    $this->get(route('propertyCustodian.inventory'))
        ->assertOk()
        ->assertSee('x-data="{ open: true, closeModal()', false)
        ->assertSee('Please correct the highlighted fields and try again.');
});

test('property custodian can delete specific selected items from a serialized group', function () {
    $category = Category::create([
        'category_name' => 'IT Equipment',
        'requires_serial_number' => true,
    ]);

    $item1 = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'SN-PROJ-001',
        'quantity' => 1,
        'unit' => 'unit',
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $item2 = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Projector',
        'serial_number' => 'SN-PROJ-002',
        'quantity' => 1,
        'unit' => 'unit',
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->propertyCustodian)
        ->delete(route('propertyCustodian.inventory.destroy', $item1->item_id), [
            'selected_item_ids' => [$item1->item_id],
        ]);

    $response->assertRedirect(route('propertyCustodian.inventory'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('inventory', ['item_id' => $item1->item_id]);
    $this->assertDatabaseHas('inventory', ['item_id' => $item2->item_id]);
});

test('delete rejects deletion when any selected item is assigned', function () {
    $category = Category::create([
        'category_name' => 'IT Equipment',
        'requires_serial_number' => true,
    ]);

    $assignedItem = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Assigned Laptop',
        'serial_number' => 'SN-LAP-001',
        'quantity' => 1,
        'unit' => 'unit',
        'status' => 'assigned',
        'date_acquired' => '2026-08-10',
    ]);

    $response = $this->actingAs($this->propertyCustodian)
        ->delete(route('propertyCustodian.inventory.destroy', $assignedItem->item_id), [
            'selected_item_ids' => [$assignedItem->item_id],
        ]);

    $response->assertRedirect(route('propertyCustodian.inventory'));
    $response->assertSessionHas('error', 'Assigned inventory cannot be deleted.');
    $this->assertDatabaseHas('inventory', ['item_id' => $assignedItem->item_id]);
});

test('editing and deletion reject inventory that is not available', function () {
    $category = Category::create([
        'category_name' => 'IT Equipment',
        'requires_serial_number' => false,
    ]);

    $item = Inventory::create([
        'category_id' => $category->category_id,
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Maintenance Laptop',
        'quantity' => 1,
        'unit' => 'unit',
        'status' => 'under_maintenance',
        'date_acquired' => '2026-08-10',
    ]);

    $this->actingAs($this->propertyCustodian)
        ->patch(route('propertyCustodian.inventory.update', $item), [])
        ->assertRedirect(route('propertyCustodian.inventory'))
        ->assertSessionHas('error', 'Only available inventory can be updated.');

    $this->delete(route('propertyCustodian.inventory.destroy', $item))
        ->assertRedirect(route('propertyCustodian.inventory'))
        ->assertSessionHas('error', 'Only available inventory can be deleted.');

    $this->assertDatabaseHas('inventory', ['item_id' => $item->item_id]);
});

test('maintenance and disposal retain and audit the current building and room', function () {
    $category = Category::create([
        'category_name' => 'ICT Equipment',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $inventory = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->propertyCustodian->id,
        'item_name' => 'Library Projector',
        'quantity' => 1,
        'building' => 'Library Building',
        'room' => 'Media Room',
        'status' => 'available',
        'date_acquired' => '2026-08-10',
    ]);
    $operations = app(\App\Services\InventoryOperationService::class);

    expect($operations->sendToMaintenance($inventory->item_id, $this->propertyCustodian->id, 'No display', null))->toBeNull()
        ->and($operations->markRepaired($inventory->item_id, $this->propertyCustodian->id, 'Repaired', null))->toBeNull()
        ->and($operations->sendToMaintenance($inventory->item_id, $this->propertyCustodian->id, 'Retired', null))->toBeNull()
        ->and($operations->markReadyToDispose($inventory->item_id, $this->propertyCustodian->id, 'Beyond repair'))->toBeNull()
        ->and($operations->dispose($inventory->item_id, $this->propertyCustodian->id, 'Disposed'))->toBeNull();

    $movements = StockMovement::where('inventory_id', $inventory->item_id)->get();

    expect($inventory->fresh()->status)->toBe('disposed')
        ->and($inventory->fresh()->building)->toBe('Library Building')
        ->and($inventory->fresh()->room)->toBe('Media Room')
        ->and($movements)->toHaveCount(5)
        ->and($movements->every(fn (StockMovement $movement): bool =>
            $movement->from_building === 'Library Building'
            && $movement->from_room === 'Media Room'
            && $movement->to_building === 'Library Building'
            && $movement->to_room === 'Media Room'
        ))->toBeTrue();
});
