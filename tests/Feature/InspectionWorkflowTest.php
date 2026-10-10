<?php

use App\Models\Category;
use App\Models\InspectionRecord;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $inspectorRole = Role::create(['role_name' => 'Inspector']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'insp-custodian',
        'email' => 'insp-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->inspector = User::create([
        'role_id' => $inspectorRole->role_id,
        'first_name' => 'School',
        'last_name' => 'Inspector',
        'username' => 'insp-inspector',
        'email' => 'insp-inspector@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'insp-end-user',
        'email' => 'insp-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    // Furniture is deliberately maintenance-ineligible: inspection must not be gated
    // on that flag.
    $this->category = Category::create([
        'category_name' => 'Inspection Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
});

function makeInspectableItem($test, string $name, int $quantity = 1, string $status = 'available'): Inventory
{
    return Inventory::create([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'unit_cost' => 1000,
        'status' => $status,
        'date_acquired' => '2026-01-10',
    ]);
}

function makeIssuedItem($test, string $name, int $quantity = 1): Inventory
{
    $item = makeInspectableItem($test, $name, 0, 'assigned');
    $item->update([
        'assigned_to_user_id' => $test->endUser->id,
        'serial_number' => 'SN-'.$item->item_id,
        'inventory_item_no' => sprintf('INV-%06d', $item->item_id),
        'qr_code' => 'inventory-item:'.$item->item_id,
    ]);

    Transaction::create([
        'user_id' => $test->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => $quantity,
        'issued_quantity' => $quantity,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);

    return $item;
}

test('custodian can flag items for inspection from every allowed status', function () {
    $available = makeInspectableItem($test ?? $this, 'Flag From Available');
    $maintenance = makeInspectableItem($this, 'Flag From Maintenance', 1, 'under_repair');
    $dispose = makeInspectableItem($this, 'Flag From Dispose', 1, 'ready_to_dispose');
    $assigned = makeIssuedItem($this, 'Flag From Assigned');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $available->item_id), [
            'inventory_ids' => [$available->item_id, $maintenance->item_id, $dispose->item_id, $assigned->item_id],
            'reason' => 'Scheduled quarterly check',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    foreach ([$available, $maintenance, $dispose, $assigned] as $item) {
        expect($item->fresh()->status)->toBe('under_inspection');
    }

    $records = InspectionRecord::orderBy('id')->get();
    expect($records)->toHaveCount(4)
        ->and($records->pluck('status_before')->all())->toBe(
            ['available', 'under_repair', 'ready_to_dispose', 'assigned']
        )
        ->and($records->pluck('status')->unique()->all())->toBe(['flagged'])
        ->and($records->pluck('flagged_by')->unique()->all())->toBe([$this->custodian->id])
        ->and($records->pluck('finding_notes')->unique()->all())->toBe(['Scheduled quarterly check']);

    // The assigned record stores 0 in inventory.quantity, so the movement must carry
    // the transaction quantity rather than the stored column.
    $movement = StockMovement::where('inventory_id', $assigned->item_id)
        ->where('movement_type', 'inspection')
        ->first();
    expect($movement)->not->toBeNull()
        ->and((int) $movement->quantity)->toBe(1)
        ->and((int) $movement->quantity_before)->toBe(1)
        ->and((int) $movement->quantity_after)->toBe(1);
});

test('inspection is not blocked by maintenance-ineligible categories', function () {
    $item = makeInspectableItem($this, 'Ineligible But Inspectable');

    expect($this->category->is_maintenance_eligible)->toBeFalse();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $item->item_id), [])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($item->fresh()->status)->toBe('under_inspection');
});

test('flagging for inspection rejects statuses outside the allowed set', function () {
    $operations = app(\App\Services\InventoryOperationService::class);

    $disposed = makeInspectableItem($this, 'Already Disposed', 1, 'disposed');
    expect($operations->sendToInspection($disposed->item_id, $this->custodian->id, null))
        ->toBe('Only available, assigned, under repair, or ready to dispose items can be sent to inspection.');

    $alreadyFlagged = makeInspectableItem($this, 'Already Flagged', 1, 'under_inspection');
    expect($operations->sendToInspection($alreadyFlagged->item_id, $this->custodian->id, null))
        ->toBe('Only available, assigned, under repair, or ready to dispose items can be sent to inspection.');

    expect(InspectionRecord::count())->toBe(0);
});

test('a partially invalid inspection batch rolls back entirely', function () {
    $valid = makeInspectableItem($this, 'Valid Item');
    $blocked = makeInspectableItem($this, 'Blocked Item', 1, 'disposed');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $valid->item_id), [
            'inventory_ids' => [$valid->item_id, $blocked->item_id],
        ])
        ->assertSessionHas('error');

    expect($valid->fresh()->status)->toBe('available')
        ->and(InspectionRecord::count())->toBe(0)
        ->and(StockMovement::where('movement_type', 'inspection')->count())->toBe(0);
});

test('inspector queue lists only flagged items awaiting inspection', function () {
    $operations = app(\App\Services\InventoryOperationService::class);
    $flagged = makeIssuedItem($this, 'Awaiting Inspection');
    $operations->sendToInspection($flagged->item_id, $this->custodian->id, 'Needs a check');

    $untouched = makeInspectableItem($this, 'Not Flagged');
    $available = makeInspectableItem($this, 'Still Available');

    $response = $this->actingAs($this->inspector)
        ->getJson(route('api.inspector.inspection.queue'))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('total', 1);

    $record = $response->json('records.0');
    expect($record['inventory_id'])->toBe($flagged->item_id)
        ->and($record['item_name'])->toBe('Awaiting Inspection')
        ->and($record['status_before'])->toBe('assigned')
        ->and((int) $record['quantity'])->toBe(1)
        ->and($record['reason'])->toBe('Needs a check')
        ->and($record['flagged_by'])->toBe('Property Custodian');

    expect($record['inventory_id'])->not->toBe($untouched->item_id)
        ->and($record['inventory_id'])->not->toBe($available->item_id);
});

test('inspector can resolve a qr token only for items awaiting inspection', function () {
    $operations = app(\App\Services\InventoryOperationService::class);
    $flagged = makeIssuedItem($this, 'Scan Me');
    $operations->sendToInspection($flagged->item_id, $this->custodian->id, 'Verify serial');
    $token = $flagged->fresh()->qr_code;

    $this->actingAs($this->inspector)
        ->getJson(route('api.inspector.inspection.qr-lookup', ['token' => $token]))
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('inventory_id', $flagged->item_id)
        ->assertJsonPath('item_name', 'Scan Me')
        ->assertJsonPath('status_before', 'assigned')
        ->assertJsonPath('reason', 'Verify serial');

    $notFlagged = makeIssuedItem($this, 'Not Flagged');
    $this->actingAs($this->inspector)
        ->getJson(route('api.inspector.inspection.qr-lookup', ['token' => $notFlagged->fresh()->qr_code]))
        ->assertNotFound()
        ->assertJsonPath('found', false)
        ->assertJsonPath('message', 'That item is not awaiting inspection.');

    $this->actingAs($this->inspector)
        ->getJson(route('api.inspector.inspection.qr-lookup', ['token' => 'inventory-item:999999']))
        ->assertNotFound()
        ->assertJsonPath('message', 'No item found for this QR code.');
});

test('inspector marks an item inspected and the original status is restored', function () {
    $operations = app(\App\Services\InventoryOperationService::class);

    $fromAvailable = makeInspectableItem($this, 'Back To Available');
    $fromMaintenance = makeInspectableItem($this, 'Back To Maintenance', 1, 'under_repair');
    $fromDispose = makeInspectableItem($this, 'Back To Dispose', 1, 'ready_to_dispose');
    $fromAssigned = makeIssuedItem($this, 'Back To Assigned');

    foreach ([$fromAvailable, $fromMaintenance, $fromDispose, $fromAssigned] as $item) {
        expect($operations->sendToInspection($item->item_id, $this->custodian->id, 'Check me'))->toBeNull();
    }

    $inspection = InspectionRecord::where('inventory_id', $fromAvailable->item_id)->firstOrFail();

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [
            'finding_notes' => 'Serial verified, no defects',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($fromAvailable->fresh()->status)->toBe('available');

    $record = $inspection->fresh();
    expect($record->status)->toBe('inspected')
        ->and($record->inspected_by)->toBe($this->inspector->id)
        ->and($record->finding_notes)->toBe('Serial verified, no defects')
        ->and($record->inspected_at)->not->toBeNull();

    // Each remaining source status is restored by its own inspection record.
    foreach ([
        [$fromMaintenance, 'under_repair'],
        [$fromDispose, 'ready_to_dispose'],
        [$fromAssigned, 'assigned'],
    ] as [$item, $expectedStatus]) {
        $pending = InspectionRecord::where('inventory_id', $item->item_id)
            ->where('status', 'flagged')
            ->firstOrFail();

        $this->actingAs($this->inspector)
            ->postJson(route('api.inspector.inspection.mark-inspected', $pending->id), [])
            ->assertOk()
            ->assertJsonPath('inventory_status', $expectedStatus);

        expect($item->fresh()->status)->toBe($expectedStatus);
    }

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This inspection record has already been completed.');
});

test('under inspection counts and group quantities are not reported as zero', function () {
    $operations = app(\App\Services\InventoryOperationService::class);
    $assigned = makeIssuedItem($this, 'Counted Projector');
    $plainAvailable = makeInspectableItem($this, 'Plain Available', 4);

    $response = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'all']))
        ->assertOk();

    expect((int) $response->json('inventoryMetrics.assigned'))->toBe(1);

    // Flagging moves the record out of assigned, so the two must not double count.
    $operations->sendToInspection($assigned->item_id, $this->custodian->id, 'Look at it');

    $flagged = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'under_inspection']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.under_inspection.total', 1)
        ->assertJsonPath('inventoryStatusCounts.under_inspection', 1);

    $group = collect($flagged->json('inventoryPages.under_inspection.data'))
        ->firstWhere('item_name', 'Counted Projector');

    expect($group)->not->toBeNull()
        ->and((int) $group['quantity'])->toBe(1)
        ->and((int) $group['total_cost'])->toBe(1000);

    $after = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'all']))
        ->assertOk();

    // Flagging an issued item does not release it: it stays assigned (the holder
    // still has it) *and* counts as needing attention, so the two figures now
    // overlap by design. The overall total must still be the de-duplicated sum of
    // statuses: 4 available + 1 flagged = 5.
    expect((int) $after->json('inventoryMetrics.assigned'))->toBe(1)
        ->and((int) $after->json('inventoryMetrics.attention'))->toBe(1)
        ->and((int) $after->json('inventoryMetrics.available'))->toBe(4)
        ->and((int) $after->json('inventoryMetrics.total'))->toBe(5)
        ->and((int) $plainAvailable->fresh()->quantity)->toBe(4);

    // The custody quantity stays visible in both workspaces.
    $stillAssigned = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk();

    expect(collect($stillAssigned->json('inventoryPages.assigned.data'))->pluck('item_name')->all())
        ->toContain('Counted Projector');
});

test('inspection routes are restricted to their roles', function () {
    $item = makeInspectableItem($this, 'Guarded Item');
    $inspection = InspectionRecord::create([
        'inventory_id' => $item->item_id,
        'flagged_by' => $this->custodian->id,
        'status_before' => 'available',
        'status' => 'flagged',
        'flagged_at' => now(),
    ]);
    $item->update(['status' => 'under_inspection']);

    // An end user must not reach the inspector surface.
    $this->actingAs($this->endUser)
        ->getJson(route('api.inspector.inspection.queue'))
        ->assertForbidden();

    // A custodian must not mark inspections, and an inspector must not flag items.
    $this->actingAs($this->custodian)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [])
        ->assertForbidden();

    $this->actingAs($this->inspector)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $item->item_id), [])
        ->assertForbidden();

    expect($item->fresh()->status)->toBe('under_inspection');
});

test('an item flagged for inspection while assigned stays in the assigned workspace', function () {
    $assigned = makeIssuedItem($this, 'Still Mine', 4);
    $custodyIntact = (int) $assigned->assigned_to_user_id;
    expect($custodyIntact)->toBe((int) $this->endUser->id);

    $assignedWorkspace = fn () => collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
            ->assertOk()
            ->json('inventoryPages.assigned.data')
    );

    expect($assignedWorkspace()->pluck('item_name')->all())->toContain('Still Mine');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $assigned->item_id), ['reason' => 'Damaged strap'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    // Flagging rewrites `status` but must not release custody.
    $assigned->refresh();
    expect($assigned->status)->toBe('under_inspection')
        ->and((int) $assigned->assigned_to_user_id)->toBe($custodyIntact)
        ->and(in_array($assigned->status, Inventory::CUSTODY_STATUSES, true))->toBeTrue();

    // The reported bug: the holder's item used to vanish from the Assigned
    // workspace because the filter compared `status` to a single value.
    expect($assignedWorkspace()->pluck('item_name')->all())->toContain('Still Mine');

    // It must remain in the inspector queue too, and the quantity has to come
    // from the assignment transaction because inventory.quantity is 0.
    $queue = collect(
        $this->actingAs($this->inspector)
            ->getJson(route('api.inspector.inspection.queue'))
            ->assertOk()
            ->json('records')
    );
    expect($queue->pluck('inventory_item_no')->all())->toContain($assigned->inventory_item_no);
    expect((int) $assignedWorkspace()->firstWhere('item_name', 'Still Mine')['quantity'])->toBe(4);
});

test('the assigned workspace badge and metric count custody-flagged units once', function () {
    $custodyFlagged = makeIssuedItem($this, 'Flagged In Custody', 3);
    makeIssuedItem($this, 'Plain Custody', 2);
    $looseFlagged = makeInspectableItem($this, 'Flagged No Custody', 5);

    app(\App\Services\InventoryOperationService::class)->sendToInspection(
        $custodyFlagged->item_id,
        $this->custodian->id,
        'check'
    );
    app(\App\Services\InventoryOperationService::class)->sendToInspection(
        $looseFlagged->item_id,
        $this->custodian->id,
        'check'
    );

    $payload = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->json();

    // Assigned holds both custody-flagged and plain assigned units.
    expect(collect($payload['inventoryPages']['assigned']['data'])->pluck('item_name')->all())
        ->toContain('Flagged In Custody', 'Plain Custody')
        ->not->toContain('Flagged No Custody');

    // The badge has to agree with the tab it labels: 3 + 2.
    expect($payload['inventoryStatusCounts']['assigned'])->toBe(5)
        ->and($payload['inventoryMetrics']['assigned'])->toBe(5);

    // Under Inspection still shows every flagged record, custody or not.
    $flagged = collect($this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'under_inspection']))
        ->json('inventoryPages.under_inspection.data'))
        ->pluck('item_name');
    expect($flagged->all())->toContain('Flagged In Custody', 'Flagged No Custody');

    // `total` stays the de-duplicated sum of statuses: 3 + 2 + 5 = 10, so the
    // overlapping Assigned/Under Inspection figures must not inflate it.
    expect($payload['inventoryMetrics']['total'])->toBe(10);
});

test('marking the inspection restores the assigned workspace without duplication', function () {
    $item = makeIssuedItem($this, 'Restore Me', 2);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-inspection', $item->item_id), [])
        ->assertOk();

    $inspection = InspectionRecord::where('inventory_id', $item->item_id)
        ->where('status', 'flagged')
        ->firstOrFail();

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [])
        ->assertOk();

    expect($item->fresh()->status)->toBe('assigned');

    // Back to a single, non-overlapping bucket.
    $payload = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->json();

    expect(collect($payload['inventoryPages']['assigned']['data'])->where('item_name', 'Restore Me'))
        ->toHaveCount(1);
    expect($payload['inventoryStatusCounts']['under_inspection'])->toBe(0)
        ->and($payload['inventoryStatusCounts']['assigned'])->toBe(2)
        ->and($payload['inventoryMetrics']['total'])->toBe(2);
});

test('flagged assets with different ics numbers are not collapsed into one row', function () {
    // Two projectors of the same name flagged for inspection. They carry distinct
    // ICS slips (and distinct serials). Listing groups by
    // (name, category, unit, status, ics_no), so they stay two rows.
    $first = makeIssuedItem($this, 'Projector', 1);
    $second = makeIssuedItem($this, 'Projector', 1);

    $first->update(['ics_no' => 'ICS-2026-1']);
    $second->update(['ics_no' => 'ICS-2026-2']);

    foreach ([$first, $second] as $item) {
        app(\App\Services\InventoryOperationService::class)
            ->sendToInspection($item->item_id, $this->custodian->id, 'Lamp out');
    }

    $rows = collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'under_inspection']))
            ->assertOk()
            ->assertJsonPath('inventoryPages.under_inspection.total', 2)
            ->json('inventoryPages.under_inspection.data')
    );

    // One row per ICS slip, and each row keeps its own ICS number.
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('ics_no')->sort()->values()->all())->toBe(['ICS-2026-1', 'ICS-2026-2'])
        ->and($rows->flatMap(fn (array $row) => collect($row['sourceItems'] ?? [])->pluck('serial_number'))->filter()->unique())->toHaveCount(2);

    // Each row exposes only its own source record, not its same-named sibling.
    foreach ($rows as $row) {
        expect($row['sourceItems'])->toHaveCount(1)
            ->and($row['sourceItems'][0]['ics_no'] ?? null)->toBe($row['ics_no'])
            ->and($row['sourceItems'][0]['serial_number'] ?? null)->not->toBeEmpty();
    }
});

test('assets sharing one ics number collapse into a single row with combined quantity', function () {
    // Same name/category/unit/status/ICS but distinct serials: one slip, one row.
    // Per-unit identity (serial, INV, QR) stays in sourceItems for per-asset actions.
    $category = Category::create([
        'category_name' => 'Shared Slip ICT',
        'requires_serial_number' => true,
    ]);

    foreach (['SN-SHARED-001', 'SN-SHARED-002'] as $serial) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->custodian->id,
            'item_name' => 'Shared Slip Epson',
            'ics_no' => 'ICS-SHARED-1',
            'quantity' => 1,
            'unit_cost' => 1500,
            'status' => 'available',
            'date_acquired' => '2026-01-10',
            'serial_number' => $serial,
        ]);
    }

    $rows = collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'available']))
            ->assertOk()
            ->json('inventoryPages.available.data')
    )->where('item_name', 'Shared Slip Epson')->values();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['quantity'])->toBe(2)
        ->and($rows[0]['ics_no'])->toBe('ICS-SHARED-1')
        ->and($rows[0]['sourceItems'] ?? [])->toHaveCount(2)
        ->and(collect($rows[0]['sourceItems'])->pluck('serial_number')->sort()->values()->all())
        ->toBe(['SN-SHARED-001', 'SN-SHARED-002']);
});

test('bulk records that share no serial number still group into one row', function () {
    // Grouping must survive for interchangeable stock: identical rows with no ICS
    // slip (NULL) share one group key, and SQL groups NULLs together.
    $category = Category::create([
        'category_name' => 'Bulk Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);

    foreach (['Bulk Chair', 'Bulk Chair', 'Bulk Chair'] as $index => $name) {
        Inventory::create([
            'category_id' => $category->category_id,
            'unit' => 'piece',
            'user_id' => $this->custodian->id,
            'item_name' => $name,
            'quantity' => 4,
            'unit_cost' => 500,
            'status' => 'available',
            'date_acquired' => '2026-01-10',
            'serial_number' => null,
        ]);
    }

    $rows = collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'available']))
            ->assertOk()
            ->json('inventoryPages.available.data')
    )->where('item_name', 'Bulk Chair')->values();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['quantity'])->toBe(12)
        ->and((int) $rows[0]['total_cost'])->toBe(6000);
});

function makeRepairableItem($test, string $name, int $quantity = 1): Inventory
{
    $category = Category::create([
        'category_name' => 'Repairable '.preg_replace('/[^A-Za-z0-9]+/', ' ', $name),
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);

    return Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'unit_cost' => 1000,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);
}

function recommendRepair($test, Inventory $item, string $notes = 'Needs repair'): InspectionRecord
{
    expect(app(\App\Services\InventoryOperationService::class)
        ->sendToInspection($item->item_id, $test->custodian->id, 'Check me'))->toBeNull();

    $inspection = InspectionRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $test->actingAs($test->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [
            'finding_notes' => $notes,
            'outcome' => 'needs_repair',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('outcome', 'needs_repair')
        ->assertJsonPath('inventory_status', 'under_inspection');

    return $inspection->fresh();
}

test('inspector can recommend repair and the item stays under inspection', function () {
    $item = makeRepairableItem($this, 'Recommend Repair Fan');
    $record = recommendRepair($this, $item, 'Fan belt frayed');

    expect($item->fresh()->status)->toBe('under_inspection')
        ->and($record->status)->toBe('repair_recommended')
        ->and($record->outcome)->toBe('needs_repair')
        ->and($record->finding_notes)->toBe('Fan belt frayed')
        ->and($record->inspected_by)->toBe($this->inspector->id);

    // Recommended items leave the inspector's flagged queue.
    $queue = $this->actingAs($this->inspector)
        ->getJson(route('api.inspector.inspection.queue'))
        ->assertOk()
        ->json('records');

    expect(collect($queue)->pluck('inspection_id')->all())->not->toContain($record->id);
});

test('custodian approval moves a recommended item straight to under repair', function () {
    $item = makeRepairableItem($this, 'Approve Repair Drill');
    $record = recommendRepair($this, $item, 'Motor sparks');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.approve-repair', $record->id), [])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($item->fresh()->status)->toBe('under_repair');

    $this->assertDatabaseHas('maintenance_records', [
        'inventory_id' => $item->item_id,
        'reported_by' => $this->inspector->id,
        'status' => 'reported',
    ]);

    $decided = $record->fresh();
    expect($decided->status)->toBe('inspected')
        ->and($decided->repair_decision)->toBe('approved')
        ->and($decided->repair_decided_by)->toBe($this->custodian->id)
        ->and($decided->repair_decided_at)->not->toBeNull();

    // Deciding twice is refused.
    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.approve-repair', $record->id), [])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This repair recommendation has already been decided.');
});

test('custodian decline returns a recommended item to its previous status', function () {
    $item = makeRepairableItem($this, 'Decline Repair Lamp');
    $record = recommendRepair($this, $item, 'Just dusty');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.decline-repair', $record->id), [])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($item->fresh()->status)->toBe('available');

    $this->assertDatabaseMissing('maintenance_records', [
        'inventory_id' => $item->item_id,
    ]);

    $decided = $record->fresh();
    expect($decided->status)->toBe('inspected')
        ->and($decided->repair_decision)->toBe('declined')
        ->and($decided->repair_decided_by)->toBe($this->custodian->id);
});

test('repair approval is refused for maintenance-ineligible categories', function () {
    // The default test category is deliberately maintenance-ineligible.
    $item = makeInspectableItem($this, 'Ineligible Repair Item');
    $record = recommendRepair($this, $item);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.approve-repair', $record->id), [])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Items in this category are not eligible for repair.');

    expect($item->fresh()->status)->toBe('under_inspection')
        ->and($record->fresh()->status)->toBe('repair_recommended');
});

test('inspectors cannot decide repair recommendations', function () {
    $item = makeRepairableItem($this, 'Guarded Repair Item');
    $record = recommendRepair($this, $item);

    $this->actingAs($this->inspector)
        ->postJson(route('api.custodian.inspection.approve-repair', $record->id), [])
        ->assertForbidden();

    $this->actingAs($this->inspector)
        ->postJson(route('api.custodian.inspection.decline-repair', $record->id), [])
        ->assertForbidden();

    expect($record->fresh()->status)->toBe('repair_recommended');
});

test('unknown inspection outcomes are rejected', function () {
    $item = makeRepairableItem($this, 'Unknown Outcome Item');
    expect(app(\App\Services\InventoryOperationService::class)
        ->sendToInspection($item->item_id, $this->custodian->id, 'Check me'))->toBeNull();

    $inspection = InspectionRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [
            'outcome' => 'exploded',
        ])
        ->assertUnprocessable();

    expect($inspection->fresh()->status)->toBe('flagged');
});

test('repair completion restores assigned status when units are still issued out', function () {
    $category = Category::create([
        'category_name' => 'Partially Issued Gear',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Partial Repair Chair',
        'quantity' => 3,
        'unit_cost' => 1000,
        'status' => 'under_repair',
        'date_acquired' => '2026-01-10',
    ]);
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 2,
        'issued_quantity' => 2,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);
    \App\Models\MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'reported_by' => $this->custodian->id,
        'status' => 'reported',
        'issue_description' => 'Wobbly leg',
        'started_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.mark-repaired', $item->item_id), [
            'inventory_ids' => [$item->item_id],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    // The stockroom remainder stays countable, but the row reads as assigned
    // because the holder still has units — not as available stock.
    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) $item->fresh()->quantity)->toBe(3);
});

test('approved assigned-origin rows show holder quantity and cost under repair', function () {
    $category = Category::create([
        'category_name' => 'Holder Visible Gear',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Holder Epson',
        'quantity' => 0,
        'unit_cost' => 2500,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);

    expect(app(\App\Services\InventoryOperationService::class)
        ->sendToInspection($item->item_id, $this->custodian->id, 'Check me'))->toBeNull();

    $inspection = InspectionRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [
            'outcome' => 'needs_repair',
        ])
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.approve-repair', $inspection->id), [])
        ->assertOk();

    $rows = collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'under_repair']))
            ->assertOk()
            ->json('inventoryPages.under_repair.data')
    )->where('item_name', 'Holder Epson')->values();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['quantity'])->toBe(1)
        ->and((float) $rows[0]['unit_cost'])->toBe(2500.0)
        ->and((float) $rows[0]['total_cost'])->toBe(2500.0);
});

test('fully assigned repair rows complete back to the assignee', function () {
    // Flagged while assigned: the stockroom row holds 0, the unit is with the user.
    // Needs an eligible category (the default test one is ineligible on purpose).
    $category = Category::create([
        'category_name' => 'Issued Repairable Gear',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Issued Repair Chair',
        'quantity' => 0,
        'unit_cost' => 1000,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
        'assigned_to_user_id' => $this->endUser->id,
    ]);
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-02-01',
        'status' => 'assigned',
    ]);
    expect(app(\App\Services\InventoryOperationService::class)
        ->sendToInspection($item->item_id, $this->custodian->id, 'Wobbly leg'))->toBeNull();

    $inspection = InspectionRecord::where('inventory_id', $item->item_id)->firstOrFail();

    $this->actingAs($this->inspector)
        ->postJson(route('api.inspector.inspection.mark-inspected', $inspection->id), [
            'outcome' => 'needs_repair',
        ])
        ->assertOk();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inspection.approve-repair', $inspection->id), [])
        ->assertOk();

    expect($item->fresh()->status)->toBe('under_repair');

    // The holder never gave the unit up, so completing returns the row to them.
    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.mark-repaired', $item->item_id), [
            'inventory_ids' => [$item->item_id],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($item->fresh()->status)->toBe('assigned')
        ->and((int) Transaction::where('item_id', $item->item_id)->where('status', 'assigned')->sum('quantity'))->toBe(1);
});

test('orphan zero-stock rows with no holder still cannot be marked repaired', function () {
    $category = Category::create([
        'category_name' => 'Orphan Repairable Gear',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => true,
    ]);
    $item = Inventory::create([
        'category_id' => $category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Orphan Repair Chair',
        'quantity' => 0,
        'unit_cost' => 1000,
        'status' => 'under_repair',
        'date_acquired' => '2026-01-10',
    ]);
    \App\Models\MaintenanceRecord::create([
        'inventory_id' => $item->item_id,
        'reported_by' => $this->custodian->id,
        'status' => 'reported',
        'issue_description' => 'Wobbly leg',
        'started_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.mark-repaired', $item->item_id), [
            'inventory_ids' => [$item->item_id],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only item records with remaining stock can be marked as repaired. The units are still issued out.');

    expect($item->fresh()->status)->toBe('under_repair');
});

test('custodian inventory exposes pending repair recommendations on source rows', function () {
    $item = makeRepairableItem($this, 'Recommended Row Item');
    $record = recommendRepair($this, $item, 'Bearing worn');

    $rows = collect(
        $this->actingAs($this->custodian)
            ->getJson(route('api.custodian.inventory', ['workspace' => 'under_inspection']))
            ->assertOk()
            ->json('inventoryPages.under_inspection.data')
    )->where('item_name', 'Recommended Row Item')->values();

    expect($rows)->toHaveCount(1);

    $sources = collect($rows[0]['sourceItems'] ?? [])->where('item_id', $item->item_id)->values();
    expect($sources)->toHaveCount(1)
        ->and($sources[0]['repairRecommendation']['inspection_id'])->toBe($record->id)
        ->and($sources[0]['repairRecommendation']['reason'])->toBe('Bearing worn')
        ->and($sources[0]['repairRecommendation']['inspected_by'])->toBe('School Inspector');
});