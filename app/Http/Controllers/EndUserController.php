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

        $requests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'user:id,first_name,last_name'])
            ->where('target_user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        return view('pages.endUser.requests', [
            'title' => 'My Requests',
            'requests' => $requests,
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
        $user = auth()->user();

        $requests = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser:id,first_name,last_name', 'transaction'])
            ->where('user_id', $user->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        // available items for creating a request (grouped by item_name)
        $inventoryItems = Inventory::query()
            ->where('status', '!=', 'disposed')
            ->orderBy('item_name')
            ->get()
            ->groupBy(fn (Inventory $item) => $item->item_name)
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'item_id' => $first->item_id,
                    'item_name' => $first->item_name,
                    'quantity' => $group->sum('quantity'),
                ];
            })->values();

        return view('pages.endUser.myRequest', [
            'title' => 'My Requests',
            'requests' => $requests,
            'availableItems' => $inventoryItems,
        ]);
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
        }else{
            return redirect()->route('endUser.my-requests')->with('success', 'Request submitted to property custodian.');
        }
    }

    public function myAssignedItems()
    {
        $user = auth()->user();

        $assignedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'transaction'])
            ->where('target_user_id', $user->id)
            ->get()
            ->map(function ($request) {
                return [
                    'type' => 'Assigned',
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
                    'quantity' => $request->quantity,
                    'date' => $request->responded_at ?? $request->requested_at ?? now(),
                    'status' => $request->status,
                    'request' => $request,
                ];
            });

        $requestedItems = AssignmentRequest::query()
            ->with(['item:item_id,item_name,inventory_item_no', 'targetUser'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($request) {
                return [
                    'type' => 'Requested',
                    'item_name' => optional($request->item)->item_name ?? 'Unknown item',
                    'quantity' => $request->quantity,
                    'date' => $request->requested_at ?? now(),
                    'status' => $request->status,
                    'request' => $request,
                ];
            });

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
            'transfer_user_id' => ['required', 'exists:users,id'],
            'item_id' => ['required', 'exists:inventory,item_id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $requestId = $request->input('request_id');
        $assignment = $requestId ? AssignmentRequest::find($requestId) : null;

        $transferRequest = AssignmentRequest::create([
            'item_id' => $validated['item_id'],
            'user_id' => auth()->id(),
            'target_user_id' => $validated['transfer_user_id'],
            'quantity' => $assignment ? $assignment->quantity : 1,
            'status' => 'waiting for approval',
            'requested_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($assignment && $assignment->target_user_id !== auth()->id()) {
            abort(403);
        }

        if (!$transferRequest) {
            return redirect()->back()->withErrors(['item_id' => 'Failed to create transfer request. Please try again.']);
        }

            return redirect()->route('endUser.my-assigned-items')->with('success', 'Transfer request submitted.');
    }

    public function respondRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $assignment = AssignmentRequest::findOrFail($id);

        // only target user may respond
        if ($assignment->target_user_id !== auth()->id()) {
            abort(403);
        }

        if ($validated['action'] === 'decline') {
            $assignment->status = 'declined';
            $assignment->responded_at = now();
            $assignment->save();

            return redirect()->route('endUser.requests')->with('success', 'Request declined.');
        }

        // accept: create transaction, decrement inventory, mark request approved
        DB::transaction(function () use ($assignment) {
            $inventory = Inventory::findOrFail($assignment->item_id);

            if ($assignment->quantity > $inventory->quantity) {
                throw new \Exception('Insufficient stock to fulfill request.');
            }

            $transaction = Transaction::create([
                'item_id' => $assignment->item_id,
                'user_id' => $assignment->target_user_id,
                'quantity' => $assignment->quantity,
                'transaction_type' => 'assignment',
                'transaction_date' => $assignment->requested_at ?? now(),
                'status' => 'assigned',
            ]);

            $assignment->transaction_id = $transaction->id;
            $assignment->status = 'approved';
            $assignment->responded_at = now();
            $assignment->save();

            // update inventory
            $inventory->decrement('quantity', $assignment->quantity);
            if ($inventory->quantity === 0) {
                $inventory->status = 'assigned';
                $inventory->save();
            }
        });

        return redirect()->route('endUser.requests')->with('success', 'Request accepted and item assigned.');
    }
}
