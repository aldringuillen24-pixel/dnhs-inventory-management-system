<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class EndUserController extends Controller
{
    public function onboarding()
    {
        $user = auth()->user();

        if (!$user->temporary_password) {
            return redirect()->route('endUser.dashboard');
        }

        return view('pages.endUser.onboarding', ['title' => 'Complete Your Account Setup']);
    }

    public function onboardingPost(Request $request)
    {
        $user = auth()->user();

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
        $user = auth()->user();

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
        $user = auth()->user();

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
            ->where('status', '!=', 'disposed')
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
        $user = auth()->user();

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
        return redirect()->route('endUser.requests');
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
            ->where('status', '!=', 'disposed')
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
            'user_id' => auth()->id(),
            'target_user_id' => $custodian->id,
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'requested_at' => now(),
        ]);

        if (!$assignmentRequest) {
            return redirect()->back()->withErrors(['item_id' => 'Failed to create request. Please try again.']);
        } else {
            return redirect()->route('endUser.requests')->with('success', 'Request submitted to property custodian.');
        }
    }

    public function myAssignedItems()
    {
        $user = auth()->user();

        $assignedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'transaction'])
            ->where('target_user_id', $user->id)
            // Hide transferred items — user no longer possesses them
            ->where('status', '!=', 'transferred')
            ->get()
            ->map(function ($request) {
                return [
                    'type'      => 'Assigned',
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
                    'quantity'  => $request->quantity,
                    'date'      => $request->responded_at ?? $request->requested_at ?? now(),
                    'status'    => $request->status,
                    'request'   => $request,
                ];
            })->toBase();

        $requestedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser.role'])
            ->where('user_id', $user->id)
            // Only items requisitioned from the Property Custodian (not outgoing transfers sent to colleagues)
            ->whereHas('targetUser.role', fn ($q) => $q->where('role_name', 'Property Custodian'))
            // Exclude transferred items (no longer in possession)
            ->where('status', '!=', 'transferred')
            ->get()
            ->map(function ($request) {
                return [
                    'type'      => 'Requested',
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
        ->whereKeyNot(auth()->id())
        ->get();

        $activityRows = $assignedItems
            ->merge($requestedItems)
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
            ->where('status', '!=', 'disposed')
            ->sum('quantity');

        if ($validated['quantity'] > $availableQuantity) {
            return redirect()->back()->with(['error' => 'Requested quantity exceeds available stock.']);
        }

        $assignmentRequest = AssignmentRequest::create([
            'item_id' => $inventoryItem->item_id,
            'user_id' => auth()->id(),
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
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Find the original assignment belonging to the sender (approved/accepted)
        $original = AssignmentRequest::where('id', $validated['request_id'])
            ->where(function ($q) {
                $q->where('target_user_id', auth()->id())
                  ->orWhere('user_id', auth()->id());
            })
            ->whereIn('status', ['approved', 'accepted'])
            ->firstOrFail();

        DB::transaction(function () use ($original, $validated) {
            // Lock the original assignment while transfer is pending
            $original->status = 'transfer pending';
            $original->save();

            // Create a new pending transfer request (Step 1 of 3)
            AssignmentRequest::create([
                'item_id'        => $validated['item_id'],
                'user_id'        => auth()->id(),               // sender
                'target_user_id' => $validated['transfer_user_id'], // recipient
                'quantity'       => $original->quantity,
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
        if ($assignment->target_user_id !== auth()->id()) {
            abort(403);
        }

        // ── TRANSFER REQUEST (step 2 of 3: recipient accepts/declines) ──────────
        if ($assignment->status === 'waiting for transfer approval') {

            if ($validated['action'] === 'decline') {
                DB::transaction(function () use ($assignment) {
                    // Restore the sender's original assignment back to 'approved'
                    AssignmentRequest::where('user_id', $assignment->user_id)
                        ->where('item_id', $assignment->item_id)
                        ->where('status', 'transfer pending')
                        ->update(['status' => 'approved']);

                    $assignment->status = 'declined';
                    $assignment->responded_at = now();
                    $assignment->save();
                });

                return redirect()->route('endUser.requests')->with('success', 'Transfer request declined.');
            }

            // Accept: advance to custodian approval (step 3 of 3)
            $assignment->status = 'waiting for custodian approval';
            $assignment->responded_at = now();
            $assignment->save();

            return redirect()->route('endUser.requests')->with('success', 'Transfer accepted. Waiting for Property Custodian approval.');
        }

        // ── NORMAL ASSIGNMENT REQUEST (from custodian) ───────────────────────────
        if ($validated['action'] === 'decline') {
            $assignment->status = 'declined';
            $assignment->responded_at = now();
            $assignment->save();

            return redirect()->route('endUser.requests')->with('success', 'Request declined.');
        }

        // Accept: create transaction, decrement inventory, mark request approved
        DB::transaction(function () use ($assignment) {
            $inventory = Inventory::findOrFail($assignment->item_id);

            if ($assignment->quantity > $inventory->quantity) {
                throw new \Exception('Insufficient stock to fulfill request.');
            }

            $transaction = Transaction::create([
                'item_id'          => $assignment->item_id,
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
}
