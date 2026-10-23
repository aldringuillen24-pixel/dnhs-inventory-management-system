<?php

use App\Models\Category;
use App\Models\InspectionRecord;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('open inspection records are resolved idempotently without changing resolved or unrelated records', function () {
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

    $alreadyResolvedItem = $makeItem('Already Resolved');
    $alreadyResolvedItem->update(['status' => 'assigned']);
    $alreadyResolved = InspectionRecord::create([
        'inventory_id' => $alreadyResolvedItem->item_id,
        'flagged_by' => $custodian->id,
        'inspected_by' => $inspector->id,
        'status_before' => 'available',
        'status' => 'inspected',
        'outcome' => 'passed',
        'finding_notes' => 'Previously completed inspection',
        'flagged_at' => now()->subDay(),
        'inspected_at' => now()->subDay(),
    ]);
    $resolvedSnapshot = $alreadyResolved->only([
        'status',
        'outcome',
        'repair_decision',
        'repair_decided_by',
        'repair_decided_at',
        'finding_notes',
        'inspected_at',
        'updated_at',
    ]);

    $unrelatedItem = $makeItem('Unrelated Repair');
    $unrelatedItem->update(['status' => 'under_repair']);

    $staleItem = $makeItem('Already Changed Item');
    $staleItem->update(['status' => 'ready_to_dispose']);
    $staleOpenRecord = InspectionRecord::create([
        'inventory_id' => $staleItem->item_id,
        'flagged_by' => $custodian->id,
        'status_before' => 'available',
        'status' => 'flagged',
        'finding_notes' => 'Inventory status changed independently',
        'flagged_at' => now(),
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
        ->and($recommended->fresh()->repair_decided_at)->not->toBeNull()
        ->and($alreadyResolved->fresh()->only(array_keys($resolvedSnapshot)))->toEqual($resolvedSnapshot)
        ->and($alreadyResolvedItem->fresh()->status)->toBe('assigned')
        ->and($unrelatedItem->fresh()->status)->toBe('under_repair')
        ->and($staleItem->fresh()->status)->toBe('ready_to_dispose')
        ->and($staleOpenRecord->fresh()->status)->toBe('inspected')
        ->and($staleOpenRecord->fresh()->outcome)->toBe('cancelled');
});

test('cleanup refuses an invalid saved status instead of guessing and changing inventory', function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $category = Category::create([
        'category_name' => 'Invalid Legacy Inspection Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'invalid-legacy-custodian',
        'email' => 'invalid-legacy-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $validItem = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => 'Valid Prior Status',
        'quantity' => 1,
        'unit_cost' => 1000,
        'status' => 'under_inspection',
        'date_acquired' => '2026-01-10',
    ]);
    $validRecord = InspectionRecord::create([
        'inventory_id' => $validItem->item_id,
        'flagged_by' => $custodian->id,
        'status_before' => 'available',
        'status' => 'flagged',
        'finding_notes' => 'Would be restored before the invalid row',
        'flagged_at' => now(),
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $custodian->id,
        'item_name' => 'Unknown Prior Status',
        'quantity' => 1,
        'unit_cost' => 1000,
        'status' => 'under_inspection',
        'date_acquired' => '2026-01-10',
    ]);
    $record = InspectionRecord::create([
        'inventory_id' => $item->item_id,
        'flagged_by' => $custodian->id,
        'status_before' => 'unexpected_status',
        'status' => 'flagged',
        'finding_notes' => 'Must not be guessed',
        'flagged_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_10_23_000001_resolve_open_inspection_records.php');

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'Cannot safely restore inspection record');

    expect($validItem->fresh()->status)->toBe('under_inspection')
        ->and($validRecord->fresh()->status)->toBe('flagged')
        ->and($item->fresh()->status)->toBe('under_inspection')
        ->and($record->fresh()->status)->toBe('flagged')
        ->and($record->fresh()->outcome)->toBeNull();
});
