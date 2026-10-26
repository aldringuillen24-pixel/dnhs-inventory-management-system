<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_policy_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('reminder_type', 40);
            $table->string('reference_key', 80);
            $table->timestamp('delivered_at');
            $table->timestamps();
            $table->unique(['recipient_id', 'reminder_type', 'reference_key'], 'inventory_policy_reminder_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_policy_reminder_deliveries');
    }
};
