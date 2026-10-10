<?php

use App\Models\Category;
use App\Models\Inventory;
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

    monitorItem($custodian, 'Ballpoint Pen');
    monitorItem($custodian, 'Lent Laptop', [
        'status' => 'assigned',
        'quantity' => 0,
        'assigned_to_user_id' => $holder->id,
    ]);
    monitorItem($custodian, 'Faulty Projector', ['status' => 'under_maintenance']);

    $payload = $this->actingAs($head)
        ->getJson(route('api.school-head.inventory.overview'))
        ->assertOk()
        ->json();

    $rows = collect($payload['monitorRows']);
    expect($rows->pluck('status')->unique()->sort()->values()->all())
        ->toEqualCanonicalizing(['available', 'assigned', 'under_maintenance']);

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
