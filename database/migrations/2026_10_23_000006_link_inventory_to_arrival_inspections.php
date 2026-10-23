<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->foreignId('arrival_inspection_id')
                ->nullable()
                ->after('expected_end_date')
                ->constrained('arrival_inspections')
                ->restrictOnDelete();
            $table->foreignId('arrival_inspection_item_id')
                ->nullable()
                ->after('arrival_inspection_id')
                ->constrained('arrival_inspection_items')
                ->restrictOnDelete();
            $table->index(['arrival_inspection_id', 'arrival_inspection_item_id'], 'inventory_arrival_inspection_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropIndex('inventory_arrival_inspection_source_index');
            $table->dropConstrainedForeignId('arrival_inspection_item_id');
            $table->dropConstrainedForeignId('arrival_inspection_id');
        });
    }
};
