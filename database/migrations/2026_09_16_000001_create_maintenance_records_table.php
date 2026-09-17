<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->constrained('inventory', 'item_id')
                ->restrictOnDelete();
            $table->foreignId('reported_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status')->default('reported')->index();
            $table->text('issue_description')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('repair_notes')->nullable();
            $table->decimal('maintenance_cost', 12, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('inventory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
