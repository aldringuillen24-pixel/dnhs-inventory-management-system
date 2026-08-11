<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AssignmentRequest;
use App\Models\Transaction;
use App\Models\Inventory;
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

        if ($validated['quantity'] > $inventoryItem->quantity) {
            return redirect()->back()->withErrors(['quantity' => 'Requested quantity exceeds available stock.']);
        }

        AssignmentRequest::create([
            'item_id' => $inventoryItem->item_id,
            'user_id' => auth()->id(),
            'target_user_id' => auth()->id(),
            'quantity' => $validated['quantity'],
            'status' => 'waiting for approval',
            'requested_at' => now(),
        ]);

        return redirect()->route('endUser.my-requests')->with('success', 'Request submitted.');
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
