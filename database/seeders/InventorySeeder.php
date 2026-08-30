<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('category_name', 'Furniture and Fixtures')->first();
        $user = User::where('username', 'admin')->first();

        if (! $category || ! $user) {
            throw new RuntimeException('Required category or admin user is missing.');
        }

        $items = [
            ['item_name' => 'Teacher Desk', 'unit' => 'piece', 'quantity' => 12, 'unit_cost' => 3500.00, 'description' => 'Wooden teacher desk'],
            ['item_name' => 'Teacher Chair', 'unit' => 'piece', 'quantity' => 24, 'unit_cost' => 1200.00, 'description' => 'Wooden teacher chair'],
            ['item_name' => 'Student Armchair', 'unit' => 'piece', 'quantity' => 60, 'unit_cost' => 950.00, 'description' => 'Right-handed student armchair'],
            ['item_name' => 'Folding Table', 'unit' => 'piece', 'quantity' => 10, 'unit_cost' => 2800.00, 'description' => 'Folding activity table'],
            ['item_name' => 'Filing Cabinet', 'unit' => 'piece', 'quantity' => 6, 'unit_cost' => 6200.00, 'description' => 'Four-drawer filing cabinet'],
            ['item_name' => 'Bookshelf', 'unit' => 'piece', 'quantity' => 8, 'unit_cost' => 4100.00, 'description' => 'Open metal bookshelf'],
            ['item_name' => 'Conference Table', 'unit' => 'piece', 'quantity' => 2, 'unit_cost' => 12500.00, 'description' => 'Large conference table'],
            ['item_name' => 'Visitor Chair', 'unit' => 'piece', 'quantity' => 16, 'unit_cost' => 1350.00, 'description' => 'Padded visitor chair'],
            ['item_name' => 'Reception Counter', 'unit' => 'piece', 'quantity' => 1, 'unit_cost' => 18500.00, 'description' => 'Front office reception counter'],
            ['item_name' => 'Storage Cabinet', 'unit' => 'piece', 'quantity' => 5, 'unit_cost' => 7800.00, 'description' => 'Lockable storage cabinet'],
            ['item_name' => 'Whiteboard Stand', 'unit' => 'piece', 'quantity' => 7, 'unit_cost' => 2300.00, 'description' => 'Mobile whiteboard stand'],
            ['item_name' => 'Computer Table', 'unit' => 'piece', 'quantity' => 14, 'unit_cost' => 3200.00, 'description' => 'Student computer table'],
            ['item_name' => 'Printer Stand', 'unit' => 'piece', 'quantity' => 4, 'unit_cost' => 2500.00, 'description' => 'Printer stand with shelf'],
            ['item_name' => 'Steel Locker', 'unit' => 'piece', 'quantity' => 10, 'unit_cost' => 8900.00, 'description' => 'Six-compartment steel locker'],
            ['item_name' => 'Round Activity Table', 'unit' => 'piece', 'quantity' => 6, 'unit_cost' => 3600.00, 'description' => 'Round table for group activities'],
            ['item_name' => 'Staff Lounge Sofa', 'unit' => 'piece', 'quantity' => 2, 'unit_cost' => 14500.00, 'description' => 'Three-seat lounge sofa'],
            ['item_name' => 'Bulletin Board', 'unit' => 'piece', 'quantity' => 9, 'unit_cost' => 1800.00, 'description' => 'Fabric-covered bulletin board'],
            ['item_name' => 'Metal Bench', 'unit' => 'piece', 'quantity' => 6, 'unit_cost' => 2900.00, 'description' => 'Three-seat metal bench'],
            ['item_name' => 'Teacher Podium', 'unit' => 'piece', 'quantity' => 3, 'unit_cost' => 4500.00, 'description' => 'Wooden teacher podium'],
            ['item_name' => 'Library Study Table', 'unit' => 'piece', 'quantity' => 5, 'unit_cost' => 5400.00, 'description' => 'Library study table'],
        ];

        foreach ($items as $index => $item) {
            $inventoryNumber = sprintf('INV-DEMO-%03d', $index + 1);
            $icsNumber = sprintf('ICS-DEMO-%03d', $index + 1);

            Inventory::updateOrCreate(
                ['inventory_item_no' => $inventoryNumber],
                [
                    ...$item,
                    'category_id' => $category->category_id,
                    'user_id' => $user->id,
                    'date_acquired' => '2026-08-26',
                    'status' => 'available',
                    'qr_code' => 'inventory-item:' . $inventoryNumber,
                    'serial_number' => $category->requires_serial_number
                        ? sprintf('SERIAL-DEMO-%03d', $index + 1)
                        : null,
                    'assigned_to_user_id' => null,
                    'ics_no' => $icsNumber,
                ]
            );
        }
    }
}
