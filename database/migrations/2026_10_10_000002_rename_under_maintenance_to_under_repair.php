<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory')) {
            return;
        }

        DB::table('inventory')
            ->where('status', 'under_maintenance')
            ->update(['status' => 'under_repair']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory')) {
            return;
        }

        DB::table('inventory')
            ->where('status', 'under_repair')
            ->update(['status' => 'under_maintenance']);
    }
};
