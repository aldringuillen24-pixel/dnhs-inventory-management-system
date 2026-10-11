<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table): void {
            // Units covered by an end-user damage/loss report. Null for
            // custodian-initiated repair pipelines, which act per record.
            $table->unsignedInteger('quantity')->nullable()->after('inventory_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
