<?php

use App\Models\Category;
use App\Models\InspectionRecord;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('open inspection records are resolved and item statuses are restored', function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $inspectorRole = Role::create(['role_name' => 'Inspector']);
    $category = Category::create([
        'category_name' => 'Legacy Inspection Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'legacy-custodian',
        'email' => 'legacy-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Inspector',
        'username' => 'legacy-inspector',
        'email' => 'legacy-inspector@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $makeItem = fn (string $name): Inventory => Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => $name,
        'quantity' => 1,
        'unit_cost' => 1000,
        'status' => 'under_inspection',
        'date_acquired' => '2026-01-10',
    ]);

    $flaggedItem = $makeItem('Previously Available');
    $flagged = InspectionRecord::create([
        'inventory_id' => $flaggedItem->item_id,
        'flagged_by' => $custodian->id,
        'status_before' => 'available',
        'status' => 'flagged',
        'finding_notes' => 'Quarterly review',
        'flagged_at' => now(),
    ]);

    $recommendedItem = $makeItem('Previously Assigned');
    $recommended = InspectionRecord::create([
        'inventory_id' => $recommendedItem->item_id,
        'flagged_by' => $custodian->id,
        'inspected_by' => $inspector->id,
        'status_before' => 'assigned',
        'status' => 'repair_recommended',
        'outcome' => 'needs_repair',
        'finding_notes' => 'Damaged casing',
        'flagged_at' => now(),
        'inspected_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_23_000001_resolve_open_inspection_records.php');
    $migration->up();
    $migration->up();

    expect($flaggedItem->fresh()->status)->toBe('available')
        ->and($recommendedItem->fresh()->status)->toBe('assigned')
        ->and($flagged->fresh()->status)->toBe('inspected')
        ->and($flagged->fresh()->outcome)->toBe('cancelled')
        ->and($flagged->fresh()->inspected_at)->not->toBeNull()
        ->and($recommended->fresh()->status)->toBe('inspected')
        ->and($recommended->fresh()->repair_decision)->toBe('declined')
        ->and($recommended->fresh()->repair_decided_by)->toBeNull()
        ->and($recommended->fresh()->repair_decided_at)->not->toBeNull();
});
