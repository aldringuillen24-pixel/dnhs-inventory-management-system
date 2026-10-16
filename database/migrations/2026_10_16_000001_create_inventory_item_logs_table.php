<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_item_logs', function (Blueprint $table) {
            $table->id();
            // No foreign key: delete/dispose logs must survive the item row.
            $table->unsignedBigInteger('item_id')->index();
            $table->string('inventory_item_no')->nullable();
            $table->string('item_name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->string('status')->nullable();
            $table->string('action', 32)->index();
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_logs');
    }
};
