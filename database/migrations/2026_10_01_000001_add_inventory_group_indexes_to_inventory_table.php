<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            // Serves the status workspace tabs (WHERE status + GROUP BY item) and
            // the single GROUP BY status aggregate used for tab badges and metrics.
            if (! Schema::hasIndex('inventory', 'inventory_status_group_index')) {
                $table->index(['status', 'item_name', 'category_id', 'unit'], 'inventory_status_group_index');
            }

            // Serves the "all inventory" workspace aggregate
            // (GROUP BY item_name, category_id, unit, status with no status filter).
            if (! Schema::hasIndex('inventory', 'inventory_item_group_index')) {
                $table->index(['item_name', 'category_id', 'unit', 'status'], 'inventory_item_group_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropIndex('inventory_status_group_index');
            $table->dropIndex('inventory_item_group_index');
        });
    }
};
