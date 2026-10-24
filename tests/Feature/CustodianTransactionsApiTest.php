<?php

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\StockMovement;
use App\Models\TransferReleaseSlip;
use App\Models\User;
use App\Services\InventoryOperationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $custodianRole = Role::create(['role_name' => 'Property Custodian']);
    $endUserRole = Role::create(['role_name' => 'End User']);

    $this->custodian = User::create([
        'role_id' => $custodianRole->role_id,
        'first_name' => 'Property',
        'last_name' => 'Custodian',
        'username' => 'tx-custodian',
        'email' => 'tx-custodian@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->endUser = User::create([
        'role_id' => $endUserRole->role_id,
        'first_name' => 'End',
        'last_name' => 'User',
        'username' => 'tx-end-user',
        'email' => 'tx-end-user@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    $this->category = Category::create([
        'category_name' => 'General Supplies',
        'requires_serial_number' => false,
    ]);
});

function makeAvailableItem($test, string $name, int $quantity): Inventory
{
    return Inventory::create([
        'category_id' => $test->category->category_id,
        'unit' => 'piece',
        'user_id' => $test->custodian->id,
        'item_name' => $name,
        'quantity' => $quantity,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);
}

test('transactions api returns every workspace list as JSON', function () {
    $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonStructure([
            'availableInventoryItems',
            'endUsers',
            'incomingRequests',
            'assignmentRequests',
            'pendingTransfers',
            'transferDrafts',
            'pendingReturns',
            'transactions',
            'auditLedger',
            'totalAssignedCount',
            'pendingRequestsCount',
            'totalTransactionsCount',
            'overdueReturnsCount',
        ]);
});

test('approve specific item request through the api assigns stock', function () {
    $item = makeAvailableItem($this, 'Stapler', 5);

    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.approve', $request->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($request->fresh()->status)->toBe('approved')
        ->and($item->fresh()->quantity)->toBe(3);

    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'assigned',
    ]);
});

test('custodian drafts the transfer release slip before confirming allocation', function () {
    $recipient = User::create([
        'role_id' => Role::where('role_name', 'End User')->value('role_id'),
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'transfer-recipient',
        'email' => 'transfer-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
        'building' => 'Science Building',
        'room' => '203',
    ]);
    $item = Inventory::create([
        'category_id' => $this->category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'assigned_to_user_id' => $this->endUser->id,
        'building' => 'Main Building',
        'room' => '101',
        'item_name' => 'Transfer Laptop',
        'inventory_item_no' => 'INV-TRANSFER-001',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
    ]);
    $original = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->endUser)
        ->postJson(route('api.end-user.transfer'), [
            'request_id' => $original->id,
            'transfer_user_id' => $recipient->id,
            'item_id' => $item->item_id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $transfer = AssignmentRequest::query()
        ->where('user_id', $this->endUser->id)
        ->where('target_user_id', $recipient->id)
        ->firstOrFail();
    expect($transfer->status)->toBe('waiting for custodian approval')
        ->and($transfer->source_assignment_id)->toBe($original->id);
    $this->actingAs($recipient)
        ->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->assertJsonCount(0, 'incomingRequests');
    $this->assertDatabaseHas('inventory', [
        'item_id' => $item->item_id,
        'assigned_to_user_id' => $this->endUser->id,
        'building' => 'Main Building',
    ]);
    $this->assertDatabaseCount('transactions', 0);

    $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('stream')
        ->twice()
        ->withArgs(fn (string $filename): bool => str_starts_with($filename, 'PRS-'))
        ->andReturn(response('%PDF-preview', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PRS-preview.pdf"',
        ]));
    $pdf->shouldReceive('download')
        ->once()
        ->withArgs(fn (string $filename): bool => str_starts_with($filename, 'PRS-'))
        ->andReturn(response('%PDF-download', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="PRS-download.pdf"',
        ]));
    Pdf::shouldReceive('loadView')
        ->times(3)
        ->with('pdfs.property-release-slip', \Mockery::on(
            fn (array $data): bool => $data['transferFromName'] === 'End User'
                && $data['recipientName'] === 'Transfer Recipient'
                && $data['lines'][0]['item_no'] === 'INV-TRANSFER-001'
                && $data['lines'][0]['qty'] === 1
                && $data['lines'][0]['condition'] === 'Good'
                && $data['slipFields']['department_unit'] === 'Science'
        ))
        ->andReturn($pdf);

    $slipFields = [
        'recipient_name' => 'Transfer Recipient',
        'department_unit' => 'Science',
        'designation' => 'Teacher',
        'purpose' => 'Transfer / Relocation',
        'condition_upon_release' => 'Good',
        'released_by_name' => 'Property Custodian',
        'released_by_date' => '2026-10-24',
        'received_by_name' => 'Transfer Recipient',
    ];
    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transfers.release-slip-preview', $transfer->id), ['slip_fields' => $slipFields])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transfers.draft', $transfer->id), ['slip_fields' => $slipFields])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($transfer->fresh()->status)->toBe('transfer draft');
    $this->assertDatabaseHas('transfer_release_slips', [
        'request_id' => $transfer->id,
        'recipient_name' => 'Transfer Recipient',
        'purpose' => 'Transfer / Relocation',
        'status' => 'draft',
    ]);
    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonCount(1, 'transferDrafts');
    $this->assertDatabaseHas('inventory', [
        'item_id' => $item->item_id,
        'assigned_to_user_id' => $this->endUser->id,
        'building' => 'Main Building',
    ]);
    $this->assertDatabaseCount('transactions', 0);

    $this->actingAs($this->custodian)
        ->get(route('api.custodian.transfers.release-slip', $transfer->id).'?preview=1')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="PRS-preview.pdf"');

    $this->actingAs($this->custodian)
        ->get(route('api.custodian.transfers.release-slip', $transfer->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="PRS-download.pdf"');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transfers.allocate', $transfer->id))
        ->assertUnprocessable();
    expect($transfer->fresh()->status)->toBe('transfer draft');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transfers.allocate', $transfer->id), ['confirmed' => true])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($transfer->fresh()->status)->toBe('approved')
        ->and(TransferReleaseSlip::where('request_id', $transfer->id)->value('status'))->toBe('completed');
    $this->assertDatabaseHas('inventory', [
        'item_id' => $item->item_id,
        'assigned_to_user_id' => $recipient->id,
        'building' => 'Science Building',
        'room' => '203',
    ]);
    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'from_user_id' => $this->endUser->id,
        'user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'assigned',
    ]);
    expect(StockMovement::where('inventory_id', $item->item_id)->where('movement_type', 'transfer')->count())->toBe(1);
});

test('partial transfers stay linked to the correct original assignment until each is allocated', function () {
    $recipientOne = User::create([
        'role_id' => Role::where('role_name', 'End User')->value('role_id'),
        'first_name' => 'Transfer',
        'last_name' => 'One',
        'username' => 'transfer-one',
        'email' => 'transfer-one@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $recipientTwo = User::create([
        'role_id' => Role::where('role_name', 'End User')->value('role_id'),
        'first_name' => 'Transfer',
        'last_name' => 'Two',
        'username' => 'transfer-two',
        'email' => 'transfer-two@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $item = Inventory::create([
        'category_id' => $this->category->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'assigned_to_user_id' => $this->endUser->id,
        'item_name' => 'Shared Device',
        'quantity' => 0,
        'status' => 'assigned',
        'date_acquired' => '2026-01-10',
    ]);
    $originTransaction = Transaction::create([
        'item_id' => $item->item_id,
        'from_user_id' => $this->custodian->id,
        'user_id' => $this->endUser->id,
        'quantity' => 5,
        'transaction_date' => now(),
        'status' => 'assigned',
    ]);
    $original = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'transaction_id' => $originTransaction->id,
        'quantity' => 5,
        'status' => 'approved',
        'requested_at' => now(),
    ]);
    $operations = app(InventoryOperationService::class);

    expect($operations->requestTransfer($original->id, $item->item_id, $this->endUser->id, $recipientOne->id, 2, null))->toBe('created')
        ->and($operations->requestTransfer($original->id, $item->item_id, $this->endUser->id, $recipientTwo->id, 3, null))->toBe('created');

    $transfers = AssignmentRequest::query()
        ->where('source_assignment_id', $original->id)
        ->orderBy('id')
        ->get();
    expect($transfers)->toHaveCount(2)
        ->and($original->fresh()->quantity)->toBe(0)
        ->and($original->fresh()->status)->toBe('approved');

    foreach ($transfers as $transfer) {
        TransferReleaseSlip::create([
            'request_id' => $transfer->id,
            'custodian_id' => $this->custodian->id,
            'recipient_name' => 'Transfer Recipient',
            'purpose' => 'Transfer / Relocation',
            'status' => 'draft',
        ]);
        $transfer->update(['status' => 'transfer draft']);
    }

    expect($operations->approveTransfer($transfers[0]->id, $this->custodian->id))->toBeTrue()
        ->and($original->fresh()->status)->toBe('approved')
        ->and($originTransaction->fresh()->quantity)->toBe(3);
    expect($operations->approveTransfer($transfers[1]->id, $this->custodian->id))->toBeTrue()
        ->and($original->fresh()->status)->toBe('transferred')
        ->and($originTransaction->fresh()->quantity)->toBe(0)
        ->and(Transaction::where('status', 'assigned')->sum('quantity'))->toBe(5);
});

test('custodian can download a release slip for an approved request', function () {
    $item = makeAvailableItem($this, 'Stapler', 5);
    $item->update([
        'unit_cost' => 125.50,
        'inventory_item_no' => 'INV-SLIP-001',
        'serial_number' => 'SLIP-SERIAL-001',
    ]);
    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.requests.slip', $request->id))
        ->assertOk()
        ->assertJsonPath('lines.0.item_no', 'INV-SLIP-001');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.approve', $request->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $slip = $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.requests.slip', $request->id))
        ->assertOk()
        ->assertJsonPath('recipientName', 'End User')
        ->assertJsonPath('lines.0.item_no', 'INV-SLIP-001')
        ->assertJsonPath('lines.0.description', 'Stapler (SN: SLIP-SERIAL-001)')
        ->assertJsonPath('lines.0.qty', 2)
        ->assertJsonPath('lines.0.amount', 251);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.slip-pdf', $request->id), [
            'slip_fields' => [
                'purpose' => 'Official Use (Off-site)',
                'conditions' => [$item->item_id => 'Good condition'],
            ],
        ])
        ->assertOk()
        ->assertDownload($slip->json('docNo').'.pdf');

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.slip-pdf', $request->id).'?preview=1', [
            'slip_fields' => [
                'purpose' => 'Official Use (Off-site)',
                'conditions' => [$item->item_id => 'Good condition'],
            ],
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('custodian can prepare a draft release slip without approving the request', function () {
    $item = makeAvailableItem($this, 'Chairs', 3);
    $item->update([
        'unit_cost' => 250,
        'inventory_item_no' => 'INV-DRAFT-001',
    ]);
    $request = AssignmentRequest::create([
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'requested_item_name' => 'Chair',
        'requested_category_id' => $this->category->category_id,
        'requested_unit' => 'piece',
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.requests.slip', [
            $request->id,
            'inventory_ids' => [$item->item_id],
        ]))
        ->assertOk()
        ->assertJsonPath('recipientName', 'End User')
        ->assertJsonPath('lines.0.item_no', 'INV-DRAFT-001')
        ->assertJsonPath('lines.0.qty', 1)
        ->assertJsonPath('lines.0.amount', 250);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.slip-pdf', $request->id), [
            'inventory_ids' => [$item->item_id],
            'slip_fields' => ['purpose' => 'Official Use (Off-site)'],
        ])
        ->assertOk()
        ->assertDownload(sprintf('PRS-%s-%06d.pdf', now()->format('Y'), $request->id));

    expect($request->fresh()->status)->toBe('waiting for approval')
        ->and($item->fresh()->quantity)->toBe(3);
});

test('draft slip preview includes entered item conditions in the PDF data', function () {
    $item = makeAvailableItem($this, 'Bondpaper', 4);
    $item->update(['inventory_item_no' => 'INV-000588']);

    $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('stream')
        ->once()
        ->with('DRAFT-PREVIEW.pdf')
        ->andReturn(response('%PDF-preview', 200, ['Content-Type' => 'application/pdf']));

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.property-release-slip', \Mockery::on(
            fn (array $data): bool => ($data['lines'][0]['condition'] ?? null) === 'good'
                && $data['lines'][0]['qty'] === 1
                && $data['recipientName'] === 'Edited Recipient'
                && $data['documentDate'] === '2026-10-10'
                && $data['slipFields']['department_unit'] === 'Science'
                && $data['slipFields']['designation'] === 'Teacher'
                && $data['slipFields']['purpose'] === 'Official Use (Off-site)'
                && $data['expectedReturnDate'] === '2026-11-01'
                && $data['actualReturnDate'] === '2026-11-02'
                && $data['slipFields']['released_by_signature'] === 'Released Signature'
                && $data['slipFields']['released_by_name'] === 'Custodian Name'
                && $data['slipFields']['released_by_date'] === '2026-11-03'
                && $data['slipFields']['received_by_signature'] === 'Received Signature'
                && $data['slipFields']['received_by_name'] === 'Recipient Name'
                && $data['slipFields']['received_by_date'] === '2026-11-04'
                && $data['slipFields']['approved_by_signature'] === 'Approved Signature'
                && $data['slipFields']['approved_by_name'] === 'Supervisor Name'
                && $data['slipFields']['approved_by_date'] === '2026-11-05'
        ))
        ->andReturn($pdf);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.assignments.draft.preview'), [
            'inventory_ids' => [$item->item_id],
            'inventory_quantities' => [$item->item_id => 1],
            'document_date' => '2026-10-10',
            'slip_fields' => [
                'recipient_name' => 'Edited Recipient',
                'department_unit' => 'Science',
                'designation' => 'Teacher',
                'purpose' => 'Official Use (Off-site)',
                'conditions' => [$item->item_id => 'good'],
                'expected_return_date' => '2026-11-01',
                'actual_return_date' => '2026-11-02',
                'released_by_signature' => 'Released Signature',
                'released_by_name' => 'Custodian Name',
                'released_by_date' => '2026-11-03',
                'received_by_signature' => 'Received Signature',
                'received_by_name' => 'Recipient Name',
                'received_by_date' => '2026-11-04',
                'approved_by_signature' => 'Approved Signature',
                'approved_by_name' => 'Supervisor Name',
                'approved_by_date' => '2026-11-05',
            ],
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('draft slip preview renders a PDF response', function () {
    $item = makeAvailableItem($this, 'Bond Paper', 4);

    $response = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.assignments.draft.preview'), [
            'inventory_ids' => [$item->item_id],
            'inventory_quantities' => [$item->item_id => 1],
            'slip_fields' => ['recipient_name' => 'End User'],
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
});

test('saving a draft persists the expected return date', function () {
    $item = makeAvailableItem($this, 'Bond Paper', 4);

    $response = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.assignments.draft'), [
            'item_id' => $item->item_id,
            'user_id' => $this->endUser->id,
            'quantity' => 1,
            'transaction_date' => '2026-10-10',
            'expected_return_date' => '2026-11-01',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(AssignmentRequest::query()->where('id', $response->json('draft_id'))->value('expected_return_date'))
        ->toBe('2026-11-01')
        ->and(AssignmentRequest::query()->where('parent_request_id', $response->json('draft_id'))->value('expected_return_date'))
        ->toBe('2026-11-01');
});

test('request slip preview preserves edited recipient and signatory names', function () {
    $item = makeAvailableItem($this, 'Desk', 1);
    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('stream')
        ->once()
        ->with(sprintf('PRS-%s-%06d.pdf', now()->format('Y'), $request->id))
        ->andReturn(response('%PDF-preview', 200, ['Content-Type' => 'application/pdf']));

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('pdfs.property-release-slip', \Mockery::on(
            fn (array $data): bool => $data['recipientName'] === 'Edited Recipient'
                && $data['slipFields']['released_by_name'] === 'Edited Custodian'
                && $data['slipFields']['received_by_name'] === 'Edited Recipient Signatory'
        ))
        ->andReturn($pdf);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.slip-pdf', $request->id).'?preview=1', [
            'slip_fields' => [
                'recipient_name' => 'Edited Recipient',
                'released_by_name' => 'Edited Custodian',
                'received_by_name' => 'Edited Recipient Signatory',
            ],
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('registered assign through the api creates an approval request', function () {
    $item = makeAvailableItem($this, 'Notebook', 10);

    $this->actingAs($this->custodian)->postJson(route('api.custodian.transactions.assign'), [
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'quantity' => 3,
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('requests', [
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 3,
        'status' => 'waiting for approval',
    ]);
});

test('manual assign through the api records a transaction', function () {
    $item = makeAvailableItem($this, 'Extension Cord', 4);

    $this->actingAs($this->custodian)->postJson(route('api.custodian.transactions.assign'), [
        'assignment_type' => 'manual',
        'item_id' => $item->item_id,
        'category_id' => $this->category->category_id,
        'unit' => 'piece',
        'quantity' => 1,
        'manual_recipient_name' => 'Visiting Lecturer',
        'transaction_date' => '2026-03-01',
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('transactions', [
        'item_id' => $item->item_id,
        'manual_recipient_name' => 'Visiting Lecturer',
        'manual_department' => null,
        'quantity' => 1,
    ]);
});

test('manual return through the api restores inventory', function () {
    $item = makeAvailableItem($this, 'Projector Screen', 2);

    $transaction = Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-03-01',
        'expected_return_date' => '2026-04-01',
        'manual_recipient_name' => 'Event Crew',
        'status' => 'assigned',
    ]);

    expect($transaction->fresh()->manual_department)->toBeNull();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.transactions.manual-return', $transaction->id), ['notes' => 'Returned after event'])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($transaction->fresh()->status)->not->toBe('assigned');
});

test('assigned inventory exposes return details and records a full return through the api', function () {
    $item = makeAvailableItem($this, 'Assigned Return Laptop', 1);
    $item->update([
        'quantity' => 0,
        'status' => 'assigned',
        'assigned_to_user_id' => $this->endUser->id,
        'serial_number' => 'RETURN-LAPTOP-001',
        'inventory_item_no' => 'INV-RETURN-001',
    ]);
    $transaction = Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-24',
        'building' => 'Science Building',
        'room' => '12',
        'from_building' => 'Main Building',
        'from_room' => 'Property Office',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_id', $transaction->id)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.inventory_item_no', 'INV-RETURN-001')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.serial_number', 'RETURN-LAPTOP-001')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.remaining_quantity', 1)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_date', '2026-09-24')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.building', 'Science Building')
        // A fully issued record keeps 0 in inventory.quantity; the issued units live on the
        // assignment transaction. The unit list must read assigned_quantity so it agrees
        // with the group total instead of rendering "0 piece".
        ->assertJsonPath('inventoryPages.assigned.data.0.quantity', 1)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.quantity', 0)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.assigned_quantity', 1);

    $this->postJson(route('api.custodian.inventory.receive-return'), [
        'transaction_id' => $transaction->id,
        'return_quantity' => 1,
        'notes' => 'Returned in good condition',
    ])->assertOk()->assertJsonPath('status', 'success');

    $this->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonPath('auditLedger.0.display_details', 'Returned 1 piece from End User (End User) to stockroom. Received by Property Custodian (Property Custodian). Note: Returned in good condition');

    expect($transaction->fresh()->status)->toBe('returned')
        ->and($item->fresh()->status)->toBe('available')
        ->and($item->fresh()->quantity)->toBe(1);
});

test('assigned inventory exposes eligible manual issues as returnable records', function () {
    $item = makeAvailableItem($this, 'Manual Return Laptop', 1);
    $item->update(['quantity' => 0, 'status' => 'assigned']);
    $transaction = Transaction::create([
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-09-24',
        'expected_return_date' => '2026-10-10',
        'manual_recipient_name' => 'Visiting Lecturer',
        'from_building' => 'Main Building',
        'from_room' => 'Property Office',
        'status' => 'assigned',
    ]);

    $this->actingAs($this->custodian)
        ->getJson(route('api.custodian.inventory', ['workspace' => 'assigned']))
        ->assertOk()
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.transaction_id', $transaction->id)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.recipient', 'Visiting Lecturer')
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.is_manual_return', true)
        ->assertJsonPath('inventoryPages.assigned.data.0.sourceItems.0.receive_return_assignments.0.expected_return_date', '2026-10-10');

    expect($transaction->fresh()->manual_department)->toBeNull();
});

test('custodian can send selected available inventory records to maintenance together', function () {
    $this->category->update(['is_maintenance_eligible' => true]);
    $first = makeAvailableItem($this, 'Printer Group', 1);
    $second = makeAvailableItem($this, 'Printer Group', 1);
    $ineligibleCategory = Category::create([
        'category_name' => 'Ineligible Supplies',
        'requires_serial_number' => false,
        'is_maintenance_eligible' => false,
    ]);
    $ineligible = Inventory::create([
        'category_id' => $ineligibleCategory->category_id,
        'unit' => 'piece',
        'user_id' => $this->custodian->id,
        'item_name' => 'Ineligible Item',
        'quantity' => 1,
        'status' => 'available',
        'date_acquired' => '2026-01-10',
    ]);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.send-to-maintenance', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'issue_description' => 'Does not power on',
            'notes' => 'Observed during inspection',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('under_repair')
        ->and($second->fresh()->status)->toBe('under_repair')
        ->and($ineligible->fresh()->status)->toBe('available')
        ->and(\App\Models\MaintenanceRecord::whereIn('inventory_id', [$first->item_id, $second->item_id])->count())->toBe(2);

    $third = makeAvailableItem($this, 'Another Printer', 1);
    $this->postJson(route('api.custodian.inventory.send-to-maintenance', $third->item_id), [
        'inventory_ids' => [$third->item_id, $ineligible->item_id],
        'issue_description' => 'Needs repair',
    ])->assertUnprocessable();

    expect($third->fresh()->status)->toBe('available');
});

test('custodian can confirm repair or ready-to-dispose actions for selected maintenance records', function () {
    $first = makeAvailableItem($this, 'Maintenance Printer', 1);
    $second = makeAvailableItem($this, 'Maintenance Printer', 1);
    $operations = app(\App\Services\InventoryOperationService::class);
    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Paper jam', null))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Paper jam', null))->toBeNull();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.mark-repaired', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'repair_notes' => 'Feed rollers replaced',
            'maintenance_cost' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('available')
        ->and($second->fresh()->status)->toBe('available')
        ->and(\App\Models\MaintenanceRecord::whereIn('inventory_id', [$first->item_id, $second->item_id])
            ->where('status', 'completed')->count())->toBe(2);

    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull();

    $this->postJson(route('api.custodian.inventory.mark-ready-to-dispose', $first->item_id), [
        'inventory_ids' => [$first->item_id, $second->item_id],
        'notes' => 'Repair is not economical',
    ])->assertOk()->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('ready_to_dispose')
        ->and($second->fresh()->status)->toBe('ready_to_dispose');

    $this->postJson(route('api.custodian.inventory.mark-repaired', $first->item_id), [
        'inventory_ids' => [$first->item_id, $second->item_id],
    ])->assertUnprocessable();

    expect($first->fresh()->status)->toBe('ready_to_dispose')
        ->and($second->fresh()->status)->toBe('ready_to_dispose');
});

test('exportPdf requests tab returns a search-scoped pdf', function () {
    $stapler = makeAvailableItem($this, 'Stapler Export', 5);
    $hammer = makeAvailableItem($this, 'Hammer Export', 3);

    AssignmentRequest::create([
        'item_id' => $stapler->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);
    AssignmentRequest::create([
        'item_id' => $hammer->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $rows = app(\App\Support\TransactionsExportRows::class)
        ->forTab('requests', $this->custodian->id, 'stapler');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item'])->toBe('Stapler Export');

    $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'requests', 'search' => 'stapler']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $response = $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'requests', 'search' => 'stapler']));
    expect($response->headers->get('Content-Disposition'))->toContain('transactions_requests_');

    $html = view('pdfs.transactions-export', [
        'tab' => 'requests',
        'rows' => collect($rows),
        'filterSummary' => 'Tab: requests; Search: "stapler"',
        'generatedAt' => now()->format('F j, Y g:i A'),
        'preparedBy' => 'Property Custodian',
    ])->render();

    expect($html)->toContain('Stapler Export')->and($html)->not->toContain('Hammer Export');
});

test('exportPdf history tab respects the date range', function () {
    $item = makeAvailableItem($this, 'History Widget', 4);

    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 1,
        'issued_quantity' => 1,
        'transaction_date' => '2026-03-01',
        'status' => 'assigned',
    ]);
    Transaction::create([
        'user_id' => $this->endUser->id,
        'item_id' => $item->item_id,
        'quantity' => 2,
        'issued_quantity' => 2,
        'transaction_date' => '2026-06-15',
        'status' => 'assigned',
    ]);

    $rows = app(\App\Support\TransactionsExportRows::class)
        ->forTab('history', $this->custodian->id, '', '2026-05-01', '2026-07-01');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['qty'])->toBe(2);

    $this->actingAs($this->custodian)
        ->get(route('api.custodian.transactions.export-pdf', [
            'tab' => 'history',
            'date_from' => '2026-05-01',
            'date_to' => '2026-07-01',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('exportPdf ledger tab exports movements and renders the empty state', function () {
    $item = makeAvailableItem($this, 'Ledger Gadget', 5);

    \App\Models\StockMovement::create([
        'inventory_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'movement_type' => 'stock_in',
        'quantity' => 5,
        'quantity_before' => 0,
        'quantity_after' => 5,
        'notes' => 'Ledger export probe',
    ]);

    $rows = app(\App\Support\TransactionsExportRows::class)
        ->forTab('ledger', $this->custodian->id, 'gadget');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['item'])->toBe('Ledger Gadget');

    $empty = app(\App\Support\TransactionsExportRows::class)
        ->forTab('ledger', $this->custodian->id, 'no-such-thing');

    expect($empty)->toBeEmpty();

    $html = view('pdfs.transactions-export', [
        'tab' => 'ledger',
        'rows' => collect($empty),
        'filterSummary' => 'Tab: ledger; Search: "no-such-thing"',
        'generatedAt' => now()->format('F j, Y g:i A'),
        'preparedBy' => 'Property Custodian',
    ])->render();

    expect($html)->toContain('No records match the current filters.');

    $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'ledger']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('exportPdf transfers and returns tabs export only their rows', function () {
    $recipient = User::create([
        'role_id' => $this->endUser->role_id,
        'first_name' => 'Second',
        'last_name' => 'Recipient',
        'username' => 'tx-recipient',
        'email' => 'tx-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    $item = makeAvailableItem($this, 'Transfer Chair', 2);

    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 1,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);

    $transfers = app(\App\Support\TransactionsExportRows::class)
        ->forTab('transfers', $this->custodian->id, 'chair');
    $returns = app(\App\Support\TransactionsExportRows::class)
        ->forTab('returns', $this->custodian->id, 'chair');

    expect($transfers)->toHaveCount(1)
        ->and($returns)->toHaveCount(1)
        ->and($transfers[0]['flow'])->toContain('Second Recipient')
        ->and($returns[0]['user'])->toContain('End User');

    $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'transfers']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->custodian)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'returns']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('exportPdf is forbidden for non-custodians', function () {
    $this->actingAs($this->endUser)
        ->get(route('propertyCustodian.transactions.export-pdf', ['tab' => 'requests']))
        ->assertForbidden();

    $this->actingAs($this->endUser)
        ->getJson(route('api.custodian.transactions.export-pdf', ['tab' => 'ledger']))
        ->assertForbidden();
});

test('custodian can dispose selected ready-to-dispose records together', function () {
    $first = makeAvailableItem($this, 'Old Projector', 1);
    $second = makeAvailableItem($this, 'Old Projector', 1);
    $available = makeAvailableItem($this, 'Available Projector', 1);
    $operations = app(\App\Services\InventoryOperationService::class);
    expect($operations->sendToMaintenance($first->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->markReadyToDispose($first->item_id, $this->custodian->id, 'Beyond repair'))->toBeNull()
        ->and($operations->sendToMaintenance($second->item_id, $this->custodian->id, 'Beyond repair', null))->toBeNull()
        ->and($operations->markReadyToDispose($second->item_id, $this->custodian->id, 'Beyond repair'))->toBeNull();

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.inventory.dispose', $first->item_id), [
            'inventory_ids' => [$first->item_id, $second->item_id],
            'notes' => 'Approved for disposal',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($first->fresh()->status)->toBe('disposed')
        ->and($second->fresh()->status)->toBe('disposed')
        ->and($available->fresh()->status)->toBe('available');

    $third = makeAvailableItem($this, 'Another Old Projector', 1);
    $this->postJson(route('api.custodian.inventory.dispose', $third->item_id), [
        'inventory_ids' => [$third->item_id, $available->item_id],
    ])->assertUnprocessable();

    expect($third->fresh()->status)->toBe('available')
        ->and($available->fresh()->status)->toBe('available');
});

test('legacy transfer requests move into the custodian workflow instead of recipient acceptance', function () {
    $item = makeAvailableItem($this, 'Shared Laptop', 1);
    $recipient = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'tx-recipient',
        'email' => 'tx-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'waiting for transfer approval',
        'requested_at' => now(),
    ]);

    // Legacy requests still awaiting recipient acceptance are routed to the
    // custodian queue so the new slip workflow can take over.
    $this->actingAs($this->custodian)->getJson(route('api.custodian.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.pendingRequests', 0)
        ->assertJsonPath('metrics.pendingTransfers', 1)
        ->assertJsonPath('metrics.pendingReturns', 0);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonPath('pendingRequestsCount', 0)
        ->assertJsonPath('pendingTransfersCount', 1)
        ->assertJsonPath('pendingReturnsCount', 0);

    // Recipients no longer have an in-app accept/decline step.
    $this->actingAs($recipient)->getJson(route('api.end-user.requests'))
        ->assertOk()
        ->assertJsonCount(0, 'incomingRequests');
});

test('per-queue counts match the tabs that render them', function () {
    $item = makeAvailableItem($this, 'Bond Paper', 10);
    $recipient = User::create([
        'role_id' => Role::where('role_name', 'End User')->firstOrFail()->role_id,
        'first_name' => 'Transfer',
        'last_name' => 'Recipient',
        'username' => 'tx-recipient',
        'email' => 'tx-recipient@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    // Requests tab: one incoming plus one assignment awaiting acceptance.
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->custodian->id,
        'target_user_id' => $this->endUser->id,
        'quantity' => 1,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);
    // Transfers tab: one peer transfer awaiting the custodian.
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $recipient->id,
        'quantity' => 1,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);
    // Returns tab: one return awaiting the custodian.
    AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 1,
        'status' => 'waiting for custodian approval',
        'requested_at' => now(),
    ]);

    $response = $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))->assertOk();

    expect($response->json('incomingRequests'))->toHaveCount(1)
        ->and($response->json('assignmentRequests'))->toHaveCount(1)
        ->and($response->json('pendingTransfers'))->toHaveCount(1)
        ->and($response->json('pendingReturns'))->toHaveCount(1);

    $response->assertJsonPath('pendingRequestsCount', 2)
        ->assertJsonPath('pendingTransfersCount', 1)
        ->assertJsonPath('pendingReturnsCount', 1);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.pendingRequests', 2)
        ->assertJsonPath('metrics.pendingTransfers', 1)
        ->assertJsonPath('metrics.pendingReturns', 1);
});

test('procurement requests render in the requests queue', function () {
    AssignmentRequest::create([
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'requested_item_name' => 'Special Microscope',
        'requested_category_id' => $this->category->category_id,
        'requested_unit' => 'unit',
        'quantity' => 3,
        'status' => AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT,
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonPath('incomingRequests.0.status', AssignmentRequest::STATUS_WAITING_FOR_PROCUREMENT)
        ->assertJsonPath('pendingRequestsCount', 1);
});

test('deciding a request drops the counts without reload', function () {
    $item = makeAvailableItem($this, 'Stapler', 5);

    $request = AssignmentRequest::create([
        'item_id' => $item->item_id,
        'user_id' => $this->endUser->id,
        'target_user_id' => $this->custodian->id,
        'quantity' => 2,
        'status' => 'waiting for approval',
        'requested_at' => now(),
    ]);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.pendingRequests', 1);

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.requests.approve', $request->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    // Fresh reads reflect the decision immediately; the SPA additionally
    // clears its page cache on every mutation and revalidates in the
    // background, so no hard reload is needed to see the drop.
    $this->actingAs($this->custodian)->getJson(route('api.custodian.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.pendingRequests', 0);

    $this->actingAs($this->custodian)->getJson(route('api.custodian.transactions'))
        ->assertOk()
        ->assertJsonPath('pendingRequestsCount', 0);
});
