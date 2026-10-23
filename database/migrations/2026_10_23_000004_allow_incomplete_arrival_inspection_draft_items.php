<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrival_inspection_items', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('unit')->nullable()->change();
            $table->unsignedInteger('quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('arrival_inspection_items')->whereNull('description')->update(['description' => '']);
        DB::table('arrival_inspection_items')->whereNull('unit')->update(['unit' => '']);
        DB::table('arrival_inspection_items')->whereNull('quantity')->update(['quantity' => 0]);

        Schema::table('arrival_inspection_items', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
            $table->string('unit')->nullable(false)->change();
            $table->unsignedInteger('quantity')->nullable(false)->change();
        });
    }
};
