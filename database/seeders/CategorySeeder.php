<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Furniture and Fixtures', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'ICT Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true],
            ['category_name' => 'Office Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true],
            ['category_name' => 'Laboratory Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true],
            ['category_name' => 'Learning Resources', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => false],
            ['category_name' => 'Sports Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => false],
            ['category_name' => 'Tools and Maintenance Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true],
            ['category_name' => 'Other Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => false],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['category_name' => $category['category_name']],
                $category
            );
        }
    }
}
