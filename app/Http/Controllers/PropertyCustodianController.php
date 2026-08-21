<?php

namespace App\Http\Controllers;

use App\Models\AssignmentRequest;
use App\Models\Category;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PropertyCustodianController extends Controller
{
    public function onboarding()
    {
        $user = auth()->user();

        if (!$user->temporary_password) {
            return redirect()->route('dashboard');
        }

        return view('pages.propertyCustodian.onboarding', ['title' => 'Complete Your Account Setup']);
    }

    public function onboardingPost(Request $request)
    {
        $user = auth()->user();

        if (!$user->temporary_password) {
            return redirect()->route('dashboard');
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

        return redirect()->route('propertyCustodian.dashboard')->with('success', 'Your account has been updated.');
    }

    public function dashboard()
    {
        return view('pages.propertyCustodian.dashboard', ['title' => 'Property Custodian Dashboard']);
    }

    public function inventory()
    {
        $categories = Category::orderBy('category_name')->get();
        $inventoryItems = Inventory::query()
            ->with('category:category_id,category_name')
            ->select('item_name', 'category_id')
            ->selectRaw('MAX(unit) as unit')
            ->selectRaw('MAX(ics_no) as ics_no')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(quantity * unit_cost) as total_cost')
            ->selectRaw('SUM(quantity * unit_cost) / NULLIF(SUM(quantity), 0) as unit_cost')
            ->selectRaw('MAX(date_acquired) as date_acquired')
            ->groupBy('item_name', 'category_id')
            ->orderBy('item_name')
            ->get();

        $sourceItemsByGroup = Inventory::query()
            ->with('assignedTo')
            ->orderBy('item_name')
            ->orderBy('item_id')
            ->get()
            ->groupBy(fn (Inventory $item) => $item->item_name . '|' . $item->category_id);

        $inventoryItems->each(function (Inventory $inventoryItem) use ($sourceItemsByGroup): void {
            $inventoryItem->sourceItems = $sourceItemsByGroup->get(
                $inventoryItem->item_name . '|' . $inventoryItem->category_id,
                collect(),
            );
        });

        return view('pages.propertyCustodian.inventory', compact('categories', 'inventoryItems'));
    }

    // Transactions method to display the transactions page with available inventory items and end users
    public function transactions()
    {
        $inventoryItems = Inventory::query()
            ->with(['category:category_id,category_name'])
            ->where('status', '!=', 'disposed')
            ->orderBy('item_name')
            ->get();

        $groupedInventory = $inventoryItems->groupBy(function (Inventory $item) {
            return $item->item_name . '|' . ($item->status === 'available' ? 'available' : 'other');
        })->map(function ($group) {
            $first = $group->first();
            return [
                'item_id' => $first->item_id,
                'item_name' => $first->item_name,
                'status' => $first->status,
                'category_name' => $first->category?->category_name,
                'quantity' => $group->sum('quantity'),
                'inventory_item_no' => $first->inventory_item_no,
            ];
        })->sortBy('item_name')->values();

        $endUsers = \App\Models\User::query()
            ->with('role:role_id,role_name')
            ->whereHas('role', fn ($q) => $q->where('role_name', 'End User'))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->full_name,
            ]);

        $transactions = Transaction::query()
            ->with([
                'user:id,first_name,last_name',
                'item:item_id,inventory_item_no,item_name,category_id',
            ])
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Include pending assignment requests in the transactions table view
        $assignmentRequests = AssignmentRequest::query()
            ->with(['item:item_id,inventory_item_no,item_name,category_id', 'targetUser:id,first_name,last_name'])
            ->where('status', 'waiting for approval')
            ->orderBy('requested_at', 'desc')
            ->get()
            ->map(function (AssignmentRequest $req) {
                // create a transient Transaction model so views/components that expect Eloquent methods work
                $t = new Transaction();
                $t->id = null; // not persisted
                $t->quantity = $req->quantity;
                $t->transaction_date = $req->requested_at;
                $t->return_date = null;
                $t->status = ucfirst($req->status);
                // set relations so view optional() calls work
                $t->setRelation('item', $req->item);
                $t->setRelation('user', $req->targetUser);
                // mark as request and attach request id
                $t->is_request = true;
                $t->request_id = $req->id;
                return $t;
            });

        // Merge and sort both collections by date desc
        $transactions = $transactions->merge($assignmentRequests)
            ->sortByDesc(function ($t) {
                if (isset($t->transaction_date) && $t->transaction_date instanceof \DateTimeInterface) {
                    return $t->transaction_date->getTimestamp();
                }

                return strtotime((string) ($t->transaction_date ?? now()));
            })->values();

        return view('pages.propertyCustodian.transactions', [
            'title' => 'Transactions',
            'availableInventoryItems' => $groupedInventory,
            'endUsers' => $endUsers,
            'transactions' => $transactions,
        ]);
    }

    public function assignItem(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory,item_id'],
            'user_id' => ['required', 'exists:users,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
        ]);

        $inventoryItem = Inventory::findOrFail($validated['item_id']);
        $endUser = User::findOrFail($validated['user_id']);

        if ($inventoryItem->status !== 'available') {
            return redirect()->back()->withErrors(['item_id' => 'The selected item is not available for assignment.']);
        }

        if ($validated['quantity'] > $inventoryItem->quantity) {
            return redirect()->back()->withErrors(['quantity' => 'The requested quantity exceeds the available stock.']);
        }

        DB::transaction(function () use ($inventoryItem, $endUser, $validated): void {
            AssignmentRequest::create([
                'item_id' => $inventoryItem->item_id,
                'user_id' => auth()->id(),
                'target_user_id' => $endUser->id,
                'quantity' => $validated['quantity'],
                'status' => 'waiting for approval',
                'requested_at' => $validated['transaction_date'] ? Carbon::parse($validated['transaction_date']) : now(),
            ]);
        });

        return redirect()->route('propertyCustodian.transactions')->with('success', 'Assignment request submitted for approval.');
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'] ?? '';
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('propertyCustodian.dashboard')->with('success', 'Profile updated successfully.');
    }
    
    public function stockIn(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,category_id'],
            'description' => ['nullable', 'string'],
            'ics_no' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:255'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'date_acquired' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $category = Category::findOrFail($validated['category_id']);
        $serialNumbers = [];

        if ($category->requires_serial_number) {
            $serialData = $request->validate([
                'serial_numbers' => ['required', 'array', 'size:' . $validated['quantity']],
                'serial_numbers.*' => ['required', 'string', 'max:255', 'distinct', 'unique:inventory,serial_number'],
            ]);

            $serialNumbers = $serialData['serial_numbers'];
        }

        DB::transaction(function () use ($validated, $serialNumbers, $category): void {
            $itemAttributes = [
                'category_id' => $validated['category_id'],
                'unit' => $validated['unit'],
                'user_id' => auth()->id(),
                'item_name' => $validated['item_name'],
                'description' => $validated['description'] ?? null,
                'ics_no' => $validated['ics_no'] ?? null,
                'unit_cost' => $validated['unit_cost'],
                'date_acquired' => $validated['date_acquired'],
            ];

            if ($category->requires_serial_number) {
                foreach ($serialNumbers as $serialNumber) {
                    $this->createInventoryItem([
                        ...$itemAttributes,
                        'quantity' => 1,
                        'serial_number' => $serialNumber,
                    ]);
                }

                return;
            }

            $this->createInventoryItem([
                ...$itemAttributes,
                'quantity' => $validated['quantity'],
                'serial_number' => null,
            ]);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item stocked in successfully.');
    }

    private function createInventoryItem(array $attributes): void
    {
        $inventoryItem = Inventory::create($attributes);

        $inventoryItem->update([
            'inventory_item_no' => sprintf('INV-%06d', $inventoryItem->item_id),
            'qr_code' => 'inventory-item:' . $inventoryItem->item_id,
        ]);
    }
}
