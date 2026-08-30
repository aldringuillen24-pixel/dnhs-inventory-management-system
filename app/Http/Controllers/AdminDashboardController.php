<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $categoryData = Category::query()
            ->with(['inventoryItems' => fn ($query) => $query->where('status', '!=', 'disposed')])
            ->get()
            ->map(fn ($category) => [
                'label' => $category->category_name,
                'value' => (int) $category->inventoryItems->sum('quantity'),
            ])
            ->filter(fn ($category) => $category['value'] > 0)
            ->values();

        $recentTransactions = Transaction::query()
            ->with(['item:item_id,item_name', 'user:id,first_name,last_name'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(5)
            ->get();

        return view('pages.administrator.dashboard', [
            'title' => 'Admin Dashboard',
            'metrics' => [
                'inventory' => Inventory::where('status', '!=', 'disposed')->sum('quantity'),
                'available' => Inventory::where('status', 'available')->sum('quantity'),
                'transactions' => Transaction::whereDate('transaction_date', today())->count(),
                'users' => User::where('status', 'active')->count(),
            ],
            'categoryData' => $categoryData,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
