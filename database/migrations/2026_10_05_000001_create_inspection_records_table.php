<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->constrained('inventory', 'item_id')
                ->restrictOnDelete();
            $table->foreignId('flagged_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('inspected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // The status the record held before it was flagged. "Mark as inspected"
            // restores it so inspection is a reversible flag, not a dead end.
            $table->string('status_before');
            $table->string('status')->default('flagged')->index();
            $table->text('finding_notes')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamps();

            $table->index('inventory_id');
            $table->index(['status', 'inventory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_records');
    }
};