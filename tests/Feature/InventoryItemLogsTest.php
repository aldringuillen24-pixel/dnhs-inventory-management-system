<?php

use App\Models\Category;
use App\Models\InventoryItemLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('custodian item logs show only edits and deletions regardless of inventory filters', function () {
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $custodian = User::factory()->create(['role_id' => $role->role_id]);
    $category = Category::create([
        'category_name' => 'Item Log Test Category',
        'requires_serial_number' => false,
    ]);

    $created = InventoryItemLog::create([
        'item_id' => 607,
        'inventory_item_no' => 'INV-000607',
        'item_name' => 'Stocked Item',
        'category_id' => $category->category_id,
        'category_name' => $category->category_name,
        'status' => 'available',
        'action' => InventoryItemLog::ACTION_CREATED,
        'changes' => ['quantity' => 1],
        'user_id' => $custodian->id,
    ]);
    $updated = InventoryItemLog::create([
        'item_id' => 606,
        'inventory_item_no' => 'INV-000606',
        'item_name' => 'Edited Item',
        'category_id' => $category->category_id,
        'category_name' => $category->category_name,
        'status' => 'available',
        'action' => InventoryItemLog::ACTION_UPDATED,
        'changes' => ['unit_cost' => ['old' => 10000, 'new' => 15000]],
        'user_id' => $custodian->id,
    ]);
    $deleted = InventoryItemLog::create([
        'item_id' => 605,
        'inventory_item_no' => 'INV-000605',
        'item_name' => 'Deleted Item',
        'category_id' => $category->category_id,
        'category_name' => $category->category_name,
        'status' => 'assigned',
        'action' => InventoryItemLog::ACTION_DELETED,
        'changes' => ['quantity' => 1],
        'user_id' => $custodian->id,
    ]);
    InventoryItemLog::create([
        'item_id' => 603,
        'inventory_item_no' => 'INV-000603',
        'item_name' => 'Incident Item',
        'category_id' => $category->category_id,
        'category_name' => $category->category_name,
        'status' => 'available',
        'action' => InventoryItemLog::ACTION_UPDATED,
        'changes' => ['quantity' => ['old' => 1, 'new' => 0]],
        'reason' => 'Isolated incident #16: received',
        'user_id' => $custodian->id,
    ]);
    InventoryItemLog::create([
        'item_id' => 604,
        'inventory_item_no' => 'INV-000604',
        'item_name' => 'Disposed Item',
        'category_id' => $category->category_id,
        'category_name' => $category->category_name,
        'status' => 'disposed',
        'action' => InventoryItemLog::ACTION_DISPOSED,
        'changes' => ['quantity' => 0],
        'user_id' => $custodian->id,
    ]);

    $availableLogs = $this->actingAs($custodian)
        ->getJson(route('api.custodian.inventory.logs', [
            'workspace' => 'available',
            'search' => 'No matching item',
            'category_id' => $category->category_id,
        ]))
        ->assertOk()
        ->json('logs');

    $assignedLogs = $this->actingAs($custodian)
        ->getJson(route('api.custodian.inventory.logs', ['workspace' => 'assigned']))
        ->assertOk()
        ->json('logs');

    expect(collect($availableLogs)->pluck('id')->all())->toBe([$deleted->id, $updated->id])
        ->and(collect($assignedLogs)->pluck('id')->all())->toBe([$deleted->id, $updated->id])
        ->and(collect($availableLogs)->pluck('action')->all())->toBe([
            InventoryItemLog::ACTION_DELETED,
            InventoryItemLog::ACTION_UPDATED,
        ])
        ->and(collect($availableLogs)->pluck('id'))->not->toContain($created->id)
        ->and(collect($availableLogs)->first()['item_name'])->toBe('Deleted Item');
});
