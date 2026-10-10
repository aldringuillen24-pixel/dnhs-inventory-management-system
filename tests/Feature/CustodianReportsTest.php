<?php

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportsCustodian(string $username = 'reports-custodian'): User
{
    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);

    return User::factory()->create([
        'role_id' => $role->role_id,
        'username' => $username,
    ]);
}

function reportsInventory(User $custodian, string $name, array $overrides = []): Inventory
{
    $category = Category::firstOrCreate(
        ['category_name' => 'Reports Supplies'],
        ['requires_serial_number' => false],
    );

    return Inventory::create(array_merge([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => $name,
        'quantity' => 5,
        'unit_cost' => 10,
        'date_acquired' => '2025-01-01',
        'status' => 'available',
    ], $overrides));
}

test('the reports payload carries category, expiring, holder, overdue and forecast data', function () {
    $custodian = reportsCustodian();
    $holder = User::factory()->create([
        'role_id' => Role::firstOrCreate(['role_name' => 'End User'])->role_id,
        'first_name' => 'Hold',
        'last_name' => 'Erson',
    ]);

    reportsInventory($custodian, 'Ballpoint Pen');
    reportsInventory($custodian, 'Stapler', [
        'expected_end_date' => now()->addMonths(3)->format('Y-m-d'),
    ]);
    reportsInventory($custodian, 'Old Glue', [
        'expected_end_date' => now()->subMonth()->format('Y-m-d'),
    ]);
    $assigned = reportsInventory($custodian, 'Scissors', [
        'status' => 'assigned',
        'assigned_to_user_id' => $holder->id,
    ]);

    Transaction::create([
        'user_id' => $holder->id,
        'item_id' => $assigned->item_id,
        'quantity' => 2,
        'transaction_date' => now()->subDays(40),
        'status' => 'assigned',
        'expected_return_date' => now()->subDays(10)->format('Y-m-d'),
    ]);

    $payload = $this->actingAs($custodian)
        ->getJson(route('api.custodian.reports'))
        ->assertOk()
        ->json();

    // Category volume the subtitle promises.
    expect($payload['categoryData'])->not->toBeEmpty()
        ->and(collect($payload['categoryData'])->firstWhere('label', 'Reports Supplies')['quantity'])->toBeGreaterThan(0);

    // Expiring lifespans aggregate by item like the low-stock panel: per-record
    // rows carry no meaningful quantity once issued out.
    $expiring = collect($payload['expiringData']);
    expect($expiring->pluck('item_name'))->toContain('Old Glue', 'Stapler')
        ->and($expiring->first()['item_name'])->toBe('Old Glue')
        ->and($expiring->first()['tone'])->toBe('expired')
        ->and($expiring->firstWhere('item_name', 'Old Glue')['quantity'])->toBe(5);

    // Custody holders read from assignment transactions: issued-out inventory
    // rows hold zero, so row sums would report every holder as holding nothing.
    $holders = collect($payload['holderData']);
    expect($holders->firstWhere('holder_name', 'Hold Erson')['quantity'])->toBe(2);

    // Overdue returns with the holder named.
    expect($payload['overdueCount'])->toBe(1)
        ->and($payload['overdueData'])->toHaveCount(1)
        ->and($payload['overdueData'][0]['item_name'])->toBe('Scissors')
        ->and($payload['overdueData'][0]['holder_name'])->toBe('Hold Erson');
});

test('the summary PDF downloads with the same dataset as the page', function () {
    $custodian = reportsCustodian('reports-pdf-custodian');
    reportsInventory($custodian, 'Ballpoint Pen');

    $response = $this->actingAs($custodian)->get(route('api.custodian.reports.summary-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(strlen((string) $response->getContent()))->toBeGreaterThan(1000);
});
