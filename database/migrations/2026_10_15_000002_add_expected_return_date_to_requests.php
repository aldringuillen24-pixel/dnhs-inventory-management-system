<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('requests') && ! Schema::hasColumn('requests', 'expected_return_date')) {
            Schema::table('requests', function (Blueprint $table): void {
                $table->date('expected_return_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('requests') && Schema::hasColumn('requests', 'expected_return_date')) {
            Schema::table('requests', function (Blueprint $table): void {
                $table->dropColumn('expected_return_date');
            });
        }
    }
};
