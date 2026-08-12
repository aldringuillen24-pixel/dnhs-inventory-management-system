<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignment_requests') && ! Schema::hasTable('requests')) {
            Schema::rename('assignment_requests', 'requests');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('requests') && ! Schema::hasTable('assignment_requests')) {
            Schema::rename('requests', 'assignment_requests');
        }
    }
};
