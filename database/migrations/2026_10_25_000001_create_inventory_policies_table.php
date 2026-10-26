<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_policies', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedSmallInteger('low_stock_threshold')->default(5);
            $table->boolean('require_serial_number')->default(true);
            $table->unsignedTinyInteger('default_lifespan_years')->default(5);
            $table->unsignedSmallInteger('return_due_days')->default(14);
            $table->unsignedTinyInteger('auto_reminder_days')->default(3);
            $table->unsignedTinyInteger('overdue_escalation_days')->default(7);
            $table->unsignedTinyInteger('inventory_verification_interval_months')->default(1);
            $table->timestamps();
        });

        DB::table('inventory_policies')->insert([
            'id' => 1,
            'low_stock_threshold' => 5,
            'require_serial_number' => true,
            'default_lifespan_years' => 5,
            'return_due_days' => 14,
            'auto_reminder_days' => 3,
            'overdue_escalation_days' => 7,
            'inventory_verification_interval_months' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_policies');
    }
};
