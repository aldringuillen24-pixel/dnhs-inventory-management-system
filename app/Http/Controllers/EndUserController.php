<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use App\Services\InventoryOperationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EndUserController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $assignedRequests = AssignmentRequest::query()
            ->with('item.category:category_id,category_name')
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['approved', 'accepted'])
            ->where('quantity', '>', 0)
            ->get();

        $pendingRequests = AssignmentRequest::query()
            ->with('targetUser.role')
            ->where('user_id', $user->id)
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval'])
            ->get(['status', 'target_user_id']);
        $pendingReturns = AssignmentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->count();

        $pendingRequestData = collect([
            [
                'label' => 'Item Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for approval' && $request->targetUser?->role?->role_name === 'Property Custodian')->count(),
            ],
            [
                'label' => 'Transfer Requests',
                'value' => $pendingRequests->filter(fn ($request) => $request->status === 'waiting for transfer approval' || ($request->status === 'waiting for approval' && $request->targetUser?->role?->role_name !== 'Property Custodian'))->count(),
            ],
            ['label' => 'Return Requests', 'value' => $pendingReturns],
        ]);

        $today = now()->startOfDay();
        $dueSoon = Transaction::query()
            ->where('user_id', $user->id)
            ->where('status', 'assigned')
            ->whereNotNull('return_date')
            ->whereBetween('return_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
            ->sum('quantity');

        $assignedItems = (int) $assignedRequests->sum('quantity');
        $categoryData = $assignedRequests
            ->groupBy(fn ($request) => $request->item?->category?->category_name ?? 'Uncategorized')
            ->map(fn ($items, $label) => ['label' => $label, 'quantity' => (int) $items->sum('quantity')])
            ->sortByDesc('quantity')
            ->values();
        $statusData = $assignedRequests
            ->groupBy(fn ($request) => $request->item?->status ?? 'unknown')
            ->map(fn ($items, $status) => ['label' => ucfirst(str_replace('_', ' ', (string) $status)), 'quantity' => (int) $items->sum('quantity')])
            ->values();
        $conditionData = collect([
            ['label' => 'Damaged', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'damaged')->sum('quantity')],
            ['label' => 'Under Maintenance or Repair', 'value' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['under_maintenance', 'under_repair'], true))->sum('quantity')],
            ['label' => 'Lost', 'value' => (int) $assignedRequests->filter(fn ($request) => $request->item?->status === 'lost')->sum('quantity')],
            ['label' => 'Disposal Review', 'value' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['ready_to_dispose', 'disposal_review'], true))->sum('quantity')],
        ])->filter(fn ($condition) => $condition['value'] > 0)->values();

        $recentActivity = AssignmentRequest::query()
            ->with('item:item_id,item_name')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('target_user_id', $user->id);
            })
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('pages.endUser.dashboard', [
            'title' => 'End User Dashboard',
            'metrics' => [
                'assignedItems' => $assignedItems,
                'pendingRequests' => $pendingRequests->count(),
                'pendingReturns' => $pendingReturns,
                'dueSoon' => (int) $dueSoon,
                'attentionItems' => (int) $assignedRequests->filter(fn ($request) => in_array($request->item?->status, ['damaged', 'under_maintenance', 'under_repair', 'lost', 'ready_to_dispose', 'disposal_review'], true))->sum('quantity'),
            ],
            'categoryData' => $categoryData,
            'statusData' => $statusData,
            'pendingRequestData' => $pendingRequestData,
            'conditionData' => $conditionData,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function onboarding()
    {
        $user = User::findOrFail(Auth::id());

        if (!$user->temporary_password) {
            return redirect()->route('endUser.dashboard');
        }

        return view('pages.endUser.onboarding', ['title' => 'Complete Your Account Setup']);
    }

    public function onboardingPost(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        if (!$user->temporary_password) {
            return redirect()->route('endUser.dashboard');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'building' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
        $user->building = $validated['building'];
        $user->room = $validated['room'];
        $user->password = $validated['password'];
        $user->temporary_password = null;
        $user->save();

        return redirect()->route('endUser.dashboard')->with('success', 'Your account has been updated.');
    }

    public function updateProfile(Request $request)
    {
        $user = User::findOrFail(Auth::id());

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->username = $validated['username'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('endUser.profile')->with('success', 'Profile updated successfully.');
    }

    public function requests()
    {
        $user = Auth::user();

        // 1. My Requests (requests created by this end user to custodian/others)
        $myRequests = AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'requestedCategory:category_id,category_name',
                'targetUser:id,first_name,last_name,role_id',
                'targetUser.role:role_id,role_name',
                'transaction',
                'fulfillmentRequests.item:item_id,item_name,inventory_item_no',
                'fulfillmentRequests.transaction',
            ])
            ->where('user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        // 2. Incoming Requests — custodian assignments AND transfer requests directed to this user
        $incomingRequests = AssignmentRequest::query()
            ->with([
                'item:item_id,item_name,inventory_item_no',
                'user:id,first_name,last_name,email,role_id',
                'user.role:role_id,role_name',
            ])
            ->where('target_user_id', $user->id)
            ->whereNull('parent_request_id')
            ->orderBy('requested_at', 'desc')
            ->get();

        // Show available totals by requestable item type, not by physical stock row.
        $inventoryItems = Inventory::query()
            ->where('status', 'available')
            ->where('quantity', '>', 0)
            ->select('item_name', 'category_id', 'unit')
            ->selectRaw('SUM(quantity) as available_quantity')
            ->with('category:category_id,category_name')
            ->groupBy('item_name', 'category_id', 'unit')
            ->orderBy('item_name')
            ->orderBy('category_id')
            ->orderBy('unit')
            ->get()
            ->map(function (Inventory $item) {
                return [
                    'item_name' => $item->item_name,
                    'category_id' => $item->category_id,
                    'category_name' => $item->category?->category_name,
                    'unit' => $item->unit,
                    'quantity' => (int) $item->available_quantity,
                ];
            })->values();

        $pendingIncomingCount = $incomingRequests
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval'])
            ->count();
        $myPendingCount = $myRequests->where('status', 'waiting for approval')->count();

        return view('pages.endUser.requests', [
            'title'                => 'Requests',
            'myRequests'           => $myRequests,
            'incomingRequests'     => $incomingRequests,
            'availableItems'       => $inventoryItems,
            'pendingIncomingCount' => $pendingIncomingCount,
            'myPendingCount'       => $myPendingCount,
        ]);
    }

    public function requestHistory()
    {
        $user = Auth::user();

        $requests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name'])
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['approved', 'declined', 'returned'])
            ->orderBy('responded_at', 'desc')
            ->get();

        return view('pages.endUser.requestHistory', [
            'title' => 'Request History',
            'requests' => $requests,
        ]);
    }

    public function myRequests()
    {
        return $this->requests();
    }

    public function storeRequest(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,category_id'],
            'unit' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $availableQuantity = Inventory::query()
            ->where('item_name', $validated['item_name'])
            ->where('category_id', $validated['category_id'])
            ->where('unit', $validated['unit'])
            ->where('status', 'available')
            ->sum('quantity');

        if ($validated['quantity'] > $availableQuantity) {
            return redirect()->back()->with(['error' => 'Requested quantity exceeds available stock.']);
        }

        $custodian = User::whereHas('role', function ($query) {
            $query->where('role_name', 'Property Custodian');
        })->first();

        if (!$custodian) {
            return redirect()->back()->with(['error' => 'No property custodian is available to receive this request.']);
        }

        $assignmentRequest = AssignmentRequest::create([
            'requested_item_name' => $validated['item_name'],
            'requested_category_id' => $validated['category_id'],
            'requested_unit' => $validated['unit'],
            'user_id' => Auth::id(),
            'target_user_id' => $custodian->id,
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'notes' => $validated['notes'] ?? null,
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_name' => 'Failed to create request. Please try again.']);
        } else {
            return redirect()->route('endUser.my-requests')->with('success', 'Request submitted to property custodian.');
        }
    }

    public function myAssignedItems()
    {
        $user = Auth::user();

        $inventoryAssignments = Inventory::query()
            ->where('status', 'assigned')
            ->where('assigned_to_user_id', $user->id)
            ->where('quantity', '>', 0)
            ->get()
            ->map(function (Inventory $inventory) use ($user) {
                $pendingReturnRequest = AssignmentRequest::where('item_id', $inventory->item_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'waiting for custodian approval')
                    ->first();

                return [
                    'type'      => 'Assigned',
                    'item_id'   => $inventory->item_id,
                    'item_name' => $inventory->item_name,
                    'quantity'  => $inventory->quantity,
                    'date'      => now(),
                    'status'    => $pendingReturnRequest ? 'Waiting for Return Approval' : 'Approved',
                    'request'   => $pendingReturnRequest,
                ];
            })->toBase();

        $assignmentRequests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'transaction'])
            ->where('target_user_id', $user->id)
            ->where('quantity', '>', 0)
            // Hide transferred items — user no longer possesses them
            ->whereNotIn('status', ['declined', 'transferred'])
            ->get()
            ->map(function ($request) use ($user) {
                $returnRequestStatus = AssignmentRequest::where('item_id', $request->item_id)
                    ->where('user_id', $user->id)
                    ->where('status', 'waiting for custodian approval')
                    ->first();

                return [
                    'type'      => 'Assigned',
                    'item_id'   => $request->item_id,
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
                    'quantity'  => $request->quantity,
                    'date'      => $request->responded_at ?? $request->requested_at ?? now(),
                    'status'    => $returnRequestStatus ? 'Waiting for Return Approval' : $request->status,
                    'request'   => $request,
                ];
            })->toBase();

        $assignedItems = $inventoryAssignments->merge($assignmentRequests);

        $requestedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser.role'])
            ->where('user_id', $user->id)
            // Only items requisitioned from the Property Custodian (not outgoing transfers sent to colleagues)
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            // Hide live/processed return requests from this table; they belong to the request list instead.
            // Cancelled requests are also excluded from the assigned-items activity list.
            ->whereNotIn('status', ['waiting for custodian approval', 'declined', 'cancelled'])
            // Exclude transferred items (no longer in possession)
            ->where('status', '!=', 'transferred')
            // Fulfilled item-type requests are represented by their exact inventory fulfillment rows.
            ->where(function ($query) {
                $query->whereNotNull('item_id')
                    ->orWhere('status', 'waiting for approval');
            })
            ->get()
            ->map(function ($request) {
                return [
                    'type'      => 'Requested',
                    'item_id'   => $request->item_id,
                    'item_name' => $request->requested_item_name ?? optional($request->item)->item_name ?? 'Unknown item',
                    'quantity'  => $request->quantity,
                    'date'      => $request->requested_at ?? now(),
                    'status'    => $request->status,
                    'request'   => $request,
                ];
            })->toBase();

        $endUsers = User::whereHas('role', function ($query) {
            $query->where('role_name', 'End User');
        })
        ->whereKeyNot(Auth::id())
        ->get();

        $activityRows = $assignedItems
            ->merge($requestedItems)
            ->groupBy(fn ($row) => $row['type'] . '|' . ($row['item_id'] ?? $row['item_name']))
            ->map(function ($group) {
                return $group->sortByDesc(fn ($row) => $row['date'])->first();
            })
            ->values()
            ->sortByDesc(fn ($row) => $row['date'])
            ->values();

        return view('pages.endUser.myAssignedItems', [
            'title' => 'My Assigned Items',
            'activityRows' => $activityRows,
            'endUsers' => $endUsers,
        ]);
    }

    public function requestTransfer(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'target_user_id' => ['required', 'exists:users,id'],
        ]);

        $inventoryItem = Inventory::findOrFail($validated['item_id']);

        if ($inventoryItem->status !== 'available' || $validated['quantity'] > $inventoryItem->quantity) {
            return redirect()->back()->with(['error' => 'Requested quantity exceeds available stock.']);
        }

        $recipient = User::findOrFail($validated['target_user_id']);
        $assignmentRequest = AssignmentRequest::create([
            'item_id' => $inventoryItem->item_id,
            'user_id' => Auth::id(),
            'target_user_id' => $validated['target_user_id'],
            'building' => $recipient->building,
            'room' => $recipient->room,
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_id' => 'Failed to create request. Please try again.']);
        } else {
            return redirect()->route('endUser.my-requests')->with('success', 'Transfer request submitted to the selected end user.');
        }
    }

    public function transferAssignedItem(Request $request, InventoryOperationService $inventoryOperations)
    {
        $validated = $request->validate([
            'request_id'       => ['required', 'exists:requests,id'],
            'transfer_user_id' => ['required', 'exists:users,id'],
            'item_id'          => ['required', 'exists:inventory,item_id'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Find the original assignment belonging to the sender (approved/accepted)
        $original = AssignmentRequest::where('id', $validated['request_id'])
            ->where(function ($q) {
                $q->where('target_user_id', Auth::id())
                    ->orWhere(function ($q) {
                        $q->where('user_id', Auth::id())
                            ->whereHas('targetUser.role', fn ($role) => $role->where('role_name', 'Property Custodian'));
                    });
            })
            ->whereIn('status', ['approved', 'accepted'])
            ->firstOrFail();

        if ((int) $validated['item_id'] !== (int) $original->item_id) {
            return redirect()->back()
                ->withErrors(['item_id' => 'The selected item does not match the original assignment.'])
                ->withInput();
        }

        // Validate quantity does not exceed available quantity
        if ($validated['quantity'] > $original->quantity) {
            return redirect()->back()
                ->withErrors(['quantity' => "You cannot transfer more than {$original->quantity} unit(s)."])
                ->withInput();
        }

        $recipient = User::with('role')->findOrFail($validated['transfer_user_id']);

        if (
            $recipient->id === Auth::id()
            || $recipient->status !== 'active'
            || $recipient->role?->role_name !== 'End User'
        ) {
            return redirect()->back()
                ->withErrors(['transfer_user_id' => 'Transfers are only allowed to another active End User.'])
                ->withInput();
        }

        $transferResult = $inventoryOperations->requestTransfer(
            $original->id,
            (int) $validated['item_id'],
            (int) Auth::id(),
            (int) $validated['transfer_user_id'],
            (int) $validated['quantity'],
            $validated['notes'] ?? null,
        );

        if ($transferResult === 'insufficient') {
            return redirect()->back()
                ->withErrors(['quantity' => "You cannot transfer more than {$original->quantity} unit(s)."])
                ->withInput();
        }
        if ($transferResult !== 'created') {
            return redirect()->back()
                ->withErrors(['request_id' => 'The assignment is no longer available for transfer.'])
                ->withInput();
        }

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request sent. Waiting for the recipient to accept.');
    }

    public function respondRequest(Request $request, $id, InventoryOperationService $inventoryOperations)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $assignment = AssignmentRequest::findOrFail($id);

        // Only target user may respond
        if ($assignment->target_user_id !== Auth::id()) {
            abort(403);
        }

        // ── TRANSFER REQUEST (step 2 of 3: recipient accepts/declines) ──────────
        if ($assignment->status === 'waiting for transfer approval') {

            if ($validated['action'] === 'decline') {
                if (! $inventoryOperations->declineTransferRequest($assignment->id, $assignment->notes)) {
                    return redirect()->route('endUser.requests')->with('error', 'Transfer request has already been processed.');
                }

                return redirect()->route('endUser.requests')->with('success', 'Transfer request declined.');
            }

            // Accept: advance to custodian approval (step 3 of 3) with race condition protection
            if (! $inventoryOperations->acceptTransferRequest($assignment->id)) {
                return redirect()->route('endUser.requests')->with('error', 'Transfer request is no longer awaiting approval.');
            }

            return redirect()->route('endUser.requests')->with('success', 'Transfer accepted. Waiting for Property Custodian approval.');
        }

        // ── NORMAL ASSIGNMENT REQUEST (from custodian) ───────────────────────────
        if ($assignment->status !== 'waiting for approval') {
            return redirect()->route('endUser.requests')->with('error', 'This request is no longer awaiting a response.');
        }

        if ($validated['action'] === 'decline') {
            $assignment->status = 'declined';
            $assignment->responded_at = now();
            $assignment->save();

            return redirect()->route('endUser.requests')->with('success', 'Request declined.');
        }

        // Accept: create transaction, decrement inventory, mark request approved
        $acceptanceResult = $inventoryOperations->acceptAssignment(
            $assignment->id,
            (int) Auth::id(),
        );

        if ($acceptanceResult === 'insufficient') {
            return redirect()->route('endUser.requests')->with('error', 'Insufficient stock to fulfill request.');
        }

        if ($acceptanceResult !== 'accepted') {
            return redirect()->route('endUser.requests')->with('error', 'This request is no longer awaiting a response.');
        }

        return redirect()->route('endUser.requests')->with('success', 'Request accepted and item assigned.');
    }

    public function requestReturn(Request $request, $itemId)
    {
        $inventory = Inventory::findOrFail($itemId);
        $user = Auth::user();

        $approvedAssignment = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('target_user_id', $user->id)
            ->where('status', 'approved')
            ->where('quantity', '>', 0)
            ->orderByDesc('responded_at')
            ->first();

        $hasApprovedAssignment = (bool) $approvedAssignment;
        $isAssignedToUser = (int) ($inventory->assigned_to_user_id ?? 0) === (int) $user->id;

        // Verify the item is assigned to the current user from either the inventory record
        // or the approved assignment record, which may lag behind on older data.
        if (!$isAssignedToUser && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'You can only request return of items assigned to you.');
        }

        if ($inventory->status !== 'assigned' && !$hasApprovedAssignment) {
            return redirect()->back()->with('error', 'Only assigned items can be returned.');
        }

        $custodian = User::whereHas('role', function ($query) {
            $query->where('role_name', 'Property Custodian');
        })->first();

        if (!$custodian) {
            return redirect()->back()->with('error', 'No property custodian available to receive this return request.');
        }

        $assignmentTransaction = $approvedAssignment?->transaction_id
            ? Transaction::whereKey($approvedAssignment->transaction_id)->where('status', 'assigned')->first()
            : Transaction::query()
                ->where('item_id', $inventory->item_id)
                ->where('user_id', $user->id)
                ->where('status', 'assigned')
                ->where('quantity', '>', 0)
                ->latest('transaction_date')
                ->first();

        // Prevent duplicate return requests for the same assignment transaction.
        $existingRequestQuery = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval');
        if ($assignmentTransaction) {
            $existingRequestQuery->where('transaction_id', $assignmentTransaction->id);
        }
        $existingRequest = $existingRequestQuery->first();

        if ($existingRequest) {
            // Return request already exists, no duplicate needed
            return redirect()->route('endUser.my-requests')->with('success', 'Return request submitted to property custodian.');
        }

        $returnQuantity = $approvedAssignment?->quantity ?? $assignmentTransaction?->quantity ?? $inventory->quantity;

        if ($approvedAssignment && $approvedAssignment->quantity < 1) {
            return redirect()->back()->with('error', 'The approved assignment quantity is invalid.');
        }

        if (!$approvedAssignment && !$isAssignedToUser) {
            return redirect()->back()->with('error', 'Only assigned items can be returned.');
        }

        // Create new return request using the approved assignment quantity as the source of truth,
        // with the assigned inventory row only as a fallback when the user is clearly assigned.
        AssignmentRequest::create([
            'item_id' => $inventory->item_id,
            'user_id' => $user->id,
            'target_user_id' => $custodian->id,
            'transaction_id' => $assignmentTransaction?->id,
            'quantity' => $returnQuantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        return redirect()->route('endUser.my-requests')->with('success', 'Return request submitted to property custodian.');
    }

    public function cancelReturnRequest(Request $request, $itemId)
    {
        $inventory = Inventory::findOrFail($itemId);
        $user = Auth::user();

        $returnRequest = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->first();

        if (!$returnRequest) {
            return redirect()->back()->with('error', 'No pending return request was found for this item.');
        }

        $returnRequest->update([
            'status' => 'cancelled',
            'responded_at' => now(),
        ]);

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Return request cancelled successfully.');
    }
}
