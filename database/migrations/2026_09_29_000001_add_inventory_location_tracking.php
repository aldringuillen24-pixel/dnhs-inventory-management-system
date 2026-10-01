<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table): void {
            $table->string('building')->nullable();
            $table->string('room')->nullable();
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->string('building')->nullable();
            $table->string('room')->nullable();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('from_building')->nullable();
            $table->string('from_room')->nullable();
            $table->string('building')->nullable();
            $table->string('room')->nullable();
        });

        Schema::table('assignment_returns', function (Blueprint $table): void {
            $table->string('building')->nullable();
            $table->string('room')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('from_building')->nullable();
            $table->string('from_room')->nullable();
            $table->string('to_building')->nullable();
            $table->string('to_room')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['from_building', 'from_room', 'to_building', 'to_room']);
        });
        Schema::table('assignment_returns', function (Blueprint $table): void {
            $table->dropColumn(['building', 'room']);
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn(['from_building', 'from_room', 'building', 'room']);
        });
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn(['building', 'room']);
        });
        Schema::table('inventory', function (Blueprint $table): void {
            $table->dropColumn(['building', 'room']);
        });
    }
};