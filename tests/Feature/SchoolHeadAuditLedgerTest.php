<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auditHead(): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'School Head'])->role_id,
        'username' => 'audit-head',
    ]);
}

function auditMovementItem(User $custodian, string $name): Inventory
{
    $category = Category::firstOrCreate(
        ['category_name' => 'Audit Supplies'],
        ['requires_serial_number' => false],
    );

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => $name,
        'quantity' => 10,
        'unit_cost' => 5,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ]);
}

test('the school-head audit view lists item movements like the custodian ledger', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'username' => 'audit-custodian',
    ]);
    $item = auditMovementItem($custodian, 'Audit Paper');

    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $custodian->id,
        'movement_type' => 'stock_in',
        'quantity' => 10,
        'quantity_before' => 0,
        'quantity_after' => 10,
        'notes' => 'Stock-in',
    ]);
    StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $custodian->id,
        'movement_type' => 'assignment',
        'quantity' => 1,
        'quantity_before' => 10,
        'quantity_after' => 9,
        'notes' => null,
    ]);

    $payload = $this->actingAs($head)->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json();

    expect($payload['logs'])->toBeArray();

    $movements = $payload['movements'];
    expect($movements)->toHaveCount(2)
        ->and($movements[0]['display_details'])->toBe('No additional details recorded.')
        ->and($movements[1]['display_details'])->toBe('Stock-in')
        ->and($movements[1]['inventory']['item_name'])->toBe('Audit Paper')
        ->and($movements[1]['user']['first_name'])->toBe('Juan');
});

test('the school-head movements ledger arrives whole so batches never split', function () {
    $head = auditHead();
    $custodian = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'Property Custodian'])->role_id,
        'username' => 'audit-custodian-2',
    ]);
    $item = auditMovementItem($custodian, 'Paged Paper');

    foreach (range(1, 15) as $index) {
        StockMovement::create([
            'inventory_id' => $item->item_id,
            'user_id' => $custodian->id,
            'movement_type' => 'stock_in',
            'quantity' => 1,
            'quantity_before' => $index - 1,
            'quantity_after' => $index,
            'notes' => 'Batch stock-in',
        ]);
    }

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.audit-logs'))
        ->assertOk()
        ->json();

    // One collection, newest first: the frontend groups then pages, so the
    // fifteen identical records stay one ×15 group on a single page.
    expect($payload['movements'])->toHaveCount(15)
        ->and($payload['movements'][0]['display_details'])->toBe('Batch stock-in')
        ->and($payload['movements'][0]['inventory']['item_name'])->toBe('Paged Paper');
});
