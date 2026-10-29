<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable();
        });

        DB::table('requests')
            ->whereIn('status', ['approved', 'accepted'])
            ->whereNotNull('transaction_id')
            ->whereNull('received_at')
            ->update(['received_at' => DB::raw('COALESCE(responded_at, requested_at)')]);
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn('received_at');
        });
    }
};