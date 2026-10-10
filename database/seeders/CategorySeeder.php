<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Furniture', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Office Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'ICT / Computer Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Audio-Visual Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Electrical Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Classroom Equipment', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Laboratory Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Sports Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'Library Resources', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'Cleaning & Sanitation', 'default_lifespan_years' => 3, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'Safety & Emergency Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Science Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Security Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Consumables', 'default_lifespan_years' => 1, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => false],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['category_name' => $category['category_name']],
                $category
            );
        }
    }
}
