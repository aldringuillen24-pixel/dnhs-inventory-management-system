<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\AiInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    uses(RefreshDatabase::class);
}

test('end users receive warehouse availability without another users assigned items', function () {
    if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $this->markTestSkipped('SQLite PDO driver is not installed.');
    }

    $role = Role::create(['role_name' => 'End User']);
    $user = User::create([
        'role_id' => $role->role_id,
        'first_name' => 'Current',
        'last_name' => 'User',
        'username' => 'current-user',
        'status' => 'active',
    ]);
    $otherUser = User::create([
        'first_name' => 'Other',
        'last_name' => 'User',
        'username' => 'other-user',
        'status' => 'active',
    ]);
    $category = Category::create(['category_name' => 'Equipment']);

    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'pcs',
        'user_id' => $user->id,
        'item_name' => 'Assigned Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-01-01',
        'status' => 'assigned',
        'assigned_to_user_id' => $user->id,
    ]);
    Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'pcs',
        'user_id' => $otherUser->id,
        'item_name' => 'Other Laptop',
        'quantity' => 1,
        'date_acquired' => '2026-01-01',
        'status' => 'assigned',
        'assigned_to_user_id' => $otherUser->id,
    ]);

    $context = app(AiInventoryService::class)->buildRoleScopedContext($user);

    expect($context['warehouse_item_availability'])->toHaveCount(2)
        ->and($context['warehouse_item_availability'])->toContain(['item_name' => 'Assigned Laptop', 'stock_status' => 'Out of Stock', 'units_available' => 0])
        ->and($context['assigned_items'])->toBe([]);
});