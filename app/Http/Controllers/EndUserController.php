<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EndUserController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $assignedItems = AssignmentRequest::query()
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['approved', 'accepted'])
            ->sum('quantity');

        $pendingRequests = AssignmentRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval', 'waiting for custodian approval'])
            ->count();

        $incomingRequests = AssignmentRequest::query()
            ->where('target_user_id', $user->id)
            ->whereIn('status', ['waiting for approval', 'waiting for transfer approval'])
            ->count();

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
            'metrics' => compact('assignedItems', 'pendingRequests', 'incomingRequests'),
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];
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
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser:id,first_name,last_name', 'transaction'])
            ->where('user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        // 2. Incoming Requests — custodian assignments AND transfer requests directed to this user
        $incomingRequests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name,email'])
            ->where('target_user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        // Available items for creating a request (grouped by item_name)
        $inventoryItems = Inventory::query()
            ->where('status', 'available')
            ->orderBy('item_name')
            ->get()
            ->groupBy(fn (Inventory $item) => $item->item_name)
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'item_id'   => $first->item_id,
                    'item_name' => $first->item_name,
                    'quantity'  => $group->sum('quantity'),
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
            'item_id' => ['required', 'exists:inventory,item_id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $inventoryItem = Inventory::findOrFail($validated['item_id']);

        $availableQuantity = Inventory::query()
            ->where('item_name', $inventoryItem->item_name)
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
            'item_id' => $inventoryItem->item_id,
            'user_id' => Auth::id(),
            'target_user_id' => $custodian->id,
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_id' => 'Failed to create request. Please try again.']);
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
            ->get()
            ->map(function ($request) {
                return [
                    'type'      => 'Requested',
                    'item_id'   => $request->item_id,
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
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
            ->groupBy(fn ($row) => $row['item_name'] . '|' . $row['type'])
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

        $availableQuantity = Inventory::query()
            ->where('item_name', $inventoryItem->item_name)
            ->where('status', 'available')
            ->sum('quantity');

        if ($validated['quantity'] > $availableQuantity) {
            return redirect()->back()->with(['error' => 'Requested quantity exceeds available stock.']);
        }

        $assignmentRequest = AssignmentRequest::create([
            'item_id' => $inventoryItem->item_id,
            'user_id' => Auth::id(),
            'target_user_id' => $validated['target_user_id'],
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

    public function transferAssignedItem(Request $request)
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

        DB::transaction(function () use ($original, $validated) {
            // If transferring full quantity, mark original as transfer pending
            // If partial transfer, reduce the quantity in original assignment
            if ($validated['quantity'] === $original->quantity) {
                $original->status = 'transfer pending';
                $original->save();
            } else {
                // For partial transfers, reduce the quantity of the original
                $original->quantity -= $validated['quantity'];
                $original->save();
            }

            // Create a new pending transfer request with the specified quantity
            AssignmentRequest::create([
                'item_id'        => $validated['item_id'],
                'user_id'        => Auth::id(),               // sender
                'target_user_id' => $validated['transfer_user_id'], // recipient
                'quantity'       => $validated['quantity'],
                'status'         => 'waiting for transfer approval',
                'notes'          => $validated['notes'] ?? null,
                'requested_at'   => now(),
            ]);
        });

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request sent. Waiting for the recipient to accept.');
    }

    public function respondRequest(Request $request, $id)
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
                DB::transaction(function () use ($assignment) {
                    // Restore the assignment from which the sender initiated the transfer.
                    $originalAssignment = AssignmentRequest::query()
                        ->where('item_id', $assignment->item_id)
                        ->where(function ($query) use ($assignment) {
                            $query->where('target_user_id', $assignment->user_id)
                                ->orWhere(function ($query) use ($assignment) {
                                    $query->where('user_id', $assignment->user_id)
                                        ->whereHas('targetUser.role', fn ($role) => $role->where('role_name', 'Property Custodian'));
                                });
                        })
                        ->whereIn('status', ['approved', 'accepted', 'transfer pending'])
                        ->lockForUpdate()
                        ->first();

                    if ($originalAssignment?->status === 'transfer pending') {
                        $originalAssignment->update(['status' => 'approved']);
                    } elseif ($originalAssignment) {
                        // A partial transfer reduced this assignment before the request was sent.
                        $originalAssignment->increment('quantity', $assignment->quantity);
                    }

                    $assignment->status = 'declined';
                    $assignment->responded_at = now();
                    $assignment->save();
                });

                return redirect()->route('endUser.requests')->with('success', 'Transfer request declined.');
            }

            // Accept: advance to custodian approval (step 3 of 3) with race condition protection
            DB::transaction(function () use ($assignment) {
                // Lock the row and re-check status to prevent concurrent acceptance
                $lockedAssignment = AssignmentRequest::whereKey($assignment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedAssignment->status !== 'waiting for transfer approval') {
                    throw new \Exception('Transfer request is no longer awaiting approval.');
                }

                $lockedAssignment->status = 'waiting for custodian approval';
                $lockedAssignment->responded_at = now();
                $lockedAssignment->save();
            });

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
        DB::transaction(function () use ($assignment) {
            $inventory = Inventory::whereKey($assignment->item_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->quantity > $inventory->quantity) {
                throw new \Exception('Insufficient stock to fulfill request.');
            }

            $transaction = Transaction::create([
                'item_id'          => $assignment->item_id,
                'from_user_id'     => $assignment->user_id,
                'user_id'          => $assignment->target_user_id,
                'quantity'         => $assignment->quantity,
                'transaction_date' => $assignment->requested_at ?? now(),
                'status'           => 'assigned',
            ]);

            $assignment->transaction_id = $transaction->id;
            $assignment->status = 'approved';
            $assignment->responded_at = now();
            $assignment->save();

            // Update inventory
            $inventory->decrement('quantity', $assignment->quantity);
            if ($inventory->quantity === 0) {
                $inventory->status = 'assigned';
                $inventory->save();
            }
        });

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

        // Check if a return request already exists for this item
        $existingRequest = AssignmentRequest::where('item_id', $inventory->item_id)
            ->where('user_id', $user->id)
            ->where('status', 'waiting for custodian approval')
            ->first();

        if ($existingRequest) {
            // Return request already exists, no duplicate needed
            return redirect()->route('endUser.my-assigned-items')->with('success', 'Return request submitted to property custodian.');
        }

        $returnQuantity = $approvedAssignment?->quantity ?? $inventory->quantity;

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
            'quantity' => $returnQuantity,
            'status' => 'waiting for custodian approval',
            'requested_at' => now(),
        ]);

        return redirect()->route('endUser.my-assigned-items')->with('success', 'Return request submitted to property custodian.');
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
