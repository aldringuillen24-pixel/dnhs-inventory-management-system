<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories retired from the catalogue. Any live rows pointing at
     * them are repointed to Consumables first so the restrictOnDelete
     * foreign keys on inventory.category_id and
     * requests.requested_category_id are never violated and no
     * assignment history is orphaned.
     */
    private const RETIRED = [
        ['category_name' => 'School Supplies', 'default_lifespan_years' => 3, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => false],
        ['category_name' => 'Medical / Health Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Home Economics Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Industrial / Workshop Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Agricultural Equipment', 'default_lifespan_years' => 7, 'requires_serial_number' => false, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Music Equipment', 'default_lifespan_years' => 8, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'School Vehicles', 'default_lifespan_years' => 10, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Communication Equipment', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'IT/Network Infrastructure', 'default_lifespan_years' => 5, 'requires_serial_number' => true, 'requires_qr_code' => true, 'is_maintenance_eligible' => true],
        ['category_name' => 'Facilities / Fixtures', 'default_lifespan_years' => 10, 'requires_serial_number' => false, 'requires_qr_code' => false, 'is_maintenance_eligible' => true],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $fallback = DB::table('categories')->where('category_name', 'Consumables')->first();

        if (! $fallback) {
            $fallbackId = DB::table('categories')->insertGetId([
                'category_name' => 'Consumables',
                'default_lifespan_years' => 1,
                'requires_serial_number' => false,
                'requires_qr_code' => false,
                'is_maintenance_eligible' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $fallbackId = $fallback->category_id;
        }

        $retiredIds = DB::table('categories')
            ->whereIn('category_name', array_column(self::RETIRED, 'category_name'))
            ->where('category_id', '!=', $fallbackId)
            ->pluck('category_id')
            ->all();

        if (empty($retiredIds)) {
            return;
        }

        if (Schema::hasTable('inventory')) {
            DB::table('inventory')
                ->whereIn('category_id', $retiredIds)
                ->update(['category_id' => $fallbackId]);
        }

        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'requested_category_id')) {
            DB::table('requests')
                ->whereIn('requested_category_id', $retiredIds)
                ->update(['requested_category_id' => $fallbackId]);
        }

        DB::table('categories')->whereIn('category_id', $retiredIds)->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        foreach (self::RETIRED as $definition) {
            $existing = DB::table('categories')->where('category_name', $definition['category_name'])->first();

            $payload = array_merge($definition, ['updated_at' => now()]);

            if ($existing) {
                DB::table('categories')->where('category_id', $existing->category_id)->update($payload);
            } else {
                DB::table('categories')->insert(array_merge($payload, ['created_at' => now()]));
            }
        }
    }
};
