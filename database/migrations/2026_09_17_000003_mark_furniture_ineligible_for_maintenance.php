<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'is_maintenance_eligible')) {
            return;
        }

        DB::table('categories')
            ->where('category_name', 'Furniture and Fixtures')
            ->update(['is_maintenance_eligible' => false]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'is_maintenance_eligible')) {
            return;
        }

        DB::table('categories')
            ->where('category_name', 'Furniture and Fixtures')
            ->update(['is_maintenance_eligible' => true]);
    }
};