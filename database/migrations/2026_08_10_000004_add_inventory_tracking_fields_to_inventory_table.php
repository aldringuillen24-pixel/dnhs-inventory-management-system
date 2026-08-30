<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory', 'inventory_item_no')) {
                $table->string('inventory_item_no')->nullable();
            }

            if (! Schema::hasColumn('inventory', 'assigned_to_user_id')) {
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory', 'status')) {
                $table->string('status')->default('available');
            }

            if (! Schema::hasColumn('inventory', 'qr_code')) {
                $table->string('qr_code')->nullable();
            }
        });

        DB::table('inventory')->orderBy('item_id')->eachById(function (object $item): void {
            DB::table('inventory')->where('item_id', $item->item_id)->update([
                'inventory_item_no' => sprintf('INV-%06d', $item->item_id),
                'qr_code' => 'inventory-item:' . $item->item_id,
            ]);
        }, 100, 'item_id');

        Schema::table('inventory', function (Blueprint $table) {
            $hasInventoryNoIndex = Schema::hasIndex('inventory', ['inventory_item_no']);
            $hasQrCodeIndex = Schema::hasIndex('inventory', ['qr_code']);

            if (! $hasInventoryNoIndex) {
                $table->unique('inventory_item_no');
            }

            if (! $hasQrCodeIndex) {
                $table->unique('qr_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropUnique(['inventory_item_no']);
            $table->dropUnique(['qr_code']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn([
                'inventory_item_no',
                'assigned_to_user_id',
                'status',
                'qr_code',
            ]);
        });
    }
};
