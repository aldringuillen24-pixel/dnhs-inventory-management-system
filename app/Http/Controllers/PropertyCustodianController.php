<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                    Inventory::create([
                        ...$itemAttributes,
                        'quantity' => 1,
                        'serial_number' => $serialNumber,
                    ]);
                }

                return;
            }

            Inventory::create([
                ...$itemAttributes,
                'quantity' => $validated['quantity'],
                'serial_number' => null,
            ]);
        });

        return redirect()->route('propertyCustodian.inventory')->with('success', 'Item stocked in successfully.');
    }
}
