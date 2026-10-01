<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('assignment_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('inventory_id')->constrained('inventory', 'item_id')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();

            $table->index(['inventory_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_returns');
    }
};