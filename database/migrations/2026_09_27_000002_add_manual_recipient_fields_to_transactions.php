<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('manual_recipient_name')->nullable();
            $table->string('manual_department')->nullable();
            $table->string('manual_recipient_type')->nullable();
            $table->string('manual_contact')->nullable();
            $table->text('manual_notes')->nullable();
            $table->date('expected_return_date')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('transactions')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Cannot roll back manual recipient fields while manual issue transactions exist.');
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn([
                'manual_recipient_name',
                'manual_department',
                'manual_recipient_type',
                'manual_contact',
                'manual_notes',
                'expected_return_date',
            ]);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
