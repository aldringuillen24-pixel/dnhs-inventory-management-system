<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->foreignId('item_id')->nullable()->change();
            $table->string('requested_item_name')->nullable();
            $table->foreignId('requested_category_id')->nullable()->constrained('categories', 'category_id')->restrictOnDelete();
            $table->string('requested_unit')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('parent_request_id')->nullable()->constrained('requests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('requests')->whereNull('item_id')->orderBy('id')->get()->each(function (object $request): void {
            $itemId = DB::table('requests')->where('parent_request_id', $request->id)->value('item_id')
                ?? DB::table('inventory')
                    ->where('item_name', $request->requested_item_name)
                    ->where('category_id', $request->requested_category_id)
                    ->where('unit', $request->requested_unit)
                    ->value('item_id');

            if (! $itemId) {
                throw new RuntimeException('Cannot restore a required inventory item for request #' . $request->id . '.');
            }

            DB::table('requests')->where('id', $request->id)->update(['item_id' => $itemId]);
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_request_id');
            $table->dropConstrainedForeignId('requested_category_id');
            $table->dropColumn(['requested_item_name', 'requested_unit', 'cancellation_reason']);
            $table->foreignId('item_id')->nullable(false)->change();
        });
    }
};
