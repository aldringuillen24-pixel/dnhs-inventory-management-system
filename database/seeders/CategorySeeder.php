<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Furniture and Fixtures', 'requires_serial_number' => false, 'is_maintenance_eligible' => false],
            ['category_name' => 'ICT Equipment', 'requires_serial_number' => true],
            ['category_name' => 'Office Equipment', 'requires_serial_number' => true],
            ['category_name' => 'Laboratory Equipment', 'requires_serial_number' => true],
            ['category_name' => 'Learning Resources', 'requires_serial_number' => false],
            ['category_name' => 'Sports Equipment', 'requires_serial_number' => false],
            ['category_name' => 'Tools and Maintenance Equipment', 'requires_serial_number' => true],
            ['category_name' => 'Other Equipment', 'requires_serial_number' => false],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['category_name' => $category['category_name']],
                $category
            );
        }
    }
}
