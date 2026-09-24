<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->unsignedSmallInteger('lifespan_years')->nullable()->after('date_acquired');
            $table->date('expected_end_date')->nullable()->after('lifespan_years');
            $table->index('expected_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropIndex(['expected_end_date']);
            $table->dropColumn([
                'lifespan_years',
                'expected_end_date',
            ]);
        });
    }
};
