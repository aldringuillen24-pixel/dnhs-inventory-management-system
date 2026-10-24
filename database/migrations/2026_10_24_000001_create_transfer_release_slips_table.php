<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_release_slips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->foreignId('custodian_id')->constrained('users')->restrictOnDelete();
            $table->string('recipient_name', 150);
            $table->string('department_unit', 150)->nullable();
            $table->string('designation', 150)->nullable();
            $table->date('expected_return_date')->nullable();
            $table->date('actual_return_date')->nullable();
            $table->string('purpose', 100);
            $table->text('condition_upon_release')->nullable();
            $table->string('released_by_name', 150)->nullable();
            $table->date('released_by_date')->nullable();
            $table->string('received_by_name', 150)->nullable();
            $table->date('received_by_date')->nullable();
            $table->string('approved_by_name', 150)->nullable();
            $table->date('approved_by_date')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_release_slips');
    }
};
