<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        // Rename existing rows in place so current inventory keeps its category_id.
        $renames = [
            'Furniture and Fixtures' => 'Furniture',
            'ICT Equipment' => 'ICT / Computer Equipment',
            'Learning Resources' => 'Library Resources',
            'Tools and Maintenance Equipment' => 'Industrial / Workshop Equipment',
            'Other Equipment' => 'Consumables',
        ];

        foreach ($renames as $old => $new) {
            $oldRow = DB::table('categories')->where('category_name', $old)->first();
            if (! $oldRow) {
                continue;
            }

            $newRow = DB::table('categories')->where('category_name', $new)->first();

            if ($newRow) {
                // Both exist (e.g. seeder already ran): repoint inventory, drop the old row.
                if (Schema::hasTable('inventory')) {
                    DB::table('inventory')
                        ->where('category_id', $oldRow->category_id)
                        ->update(['category_id' => $newRow->category_id]);
                }
                DB::table('categories')->where('category_id', $oldRow->category_id)->delete();
            } else {
                DB::table('categories')
                    ->where('category_id', $oldRow->category_id)
                    ->update(['category_name' => $new, 'updated_at' => now()]);
            }
        }

        $definitions = [
            ['category_name' => 'Furniture', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Office Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'ICT / Computer Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Audio-Visual Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Electrical Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Classroom Equipment', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Laboratory Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Sports Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'Library Resources', 'default_lifespan_years' => 5, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'School Supplies', 'default_lifespan_years' => 3, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => false],
            ['category_name' => 'Cleaning & Sanitation', 'default_lifespan_years' => 3, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => false],
            ['category_name' => 'Safety & Emergency Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Medical / Health Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Home Economics Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Industrial / Workshop Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Agricultural Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Music Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Science Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'School Vehicles', 'default_lifespan_years' => 10, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Communication Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Security Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'IT/Network Infrastructure', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
            ['category_name' => 'Facilities / Fixtures', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => true],
            ['category_name' => 'Consumables', 'default_lifespan_years' => 1, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => false],
        ];

        foreach ($definitions as $definition) {
            $existing = DB::table('categories')->where('category_name', $definition['category_name'])->first();

            $payload = array_merge($definition, ['updated_at' => now()]);

            if ($existing) {
                DB::table('categories')->where('category_id', $existing->category_id)->update($payload);
            } else {
                DB::table('categories')->insert(array_merge($payload, ['created_at' => now()]));
            }
        }
    }

    public function down(): void
    {
        // One-way sync: keep the 24-category list on rollback.
    }
};
