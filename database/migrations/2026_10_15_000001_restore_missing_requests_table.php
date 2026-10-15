<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('requests')) {
            return;
        }

        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->nullable()->constrained('inventory', 'item_id')->restrictOnDelete();
            $table->string('requested_item_name')->nullable();
            $table->foreignId('requested_category_id')->nullable()->constrained('categories', 'category_id')->restrictOnDelete();
            $table->string('requested_unit')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->string('building')->nullable();
            $table->string('room')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('parent_request_id')->nullable()->constrained('requests')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('waiting for approval');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
    }
};
