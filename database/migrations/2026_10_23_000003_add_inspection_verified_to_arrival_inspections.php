<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrival_inspections', function (Blueprint $table) {
            $table->boolean('inspection_verified')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('arrival_inspections', function (Blueprint $table) {
            $table->dropColumn('inspection_verified');
        });
    }
};
