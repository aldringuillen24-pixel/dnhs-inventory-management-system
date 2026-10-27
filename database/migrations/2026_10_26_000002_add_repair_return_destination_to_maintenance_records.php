<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_records', 'repair_return_destination')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->string('repair_return_destination')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('maintenance_records', 'repair_return_destination')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->dropColumn('repair_return_destination');
            });
        }
    }
};
