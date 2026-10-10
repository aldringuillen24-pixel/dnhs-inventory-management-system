<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_records', function (Blueprint $table): void {
            // What the inspector concluded: passed, or needs_repair. Null for
            // records completed before outcomes existed.
            $table->string('outcome')->nullable()->after('status');
            // What the custodian decided about a needs_repair recommendation:
            // approved or declined. Null until decided.
            $table->string('repair_decision')->nullable()->after('outcome');
            $table->foreignId('repair_decided_by')
                ->nullable()
                ->after('repair_decision')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('repair_decided_at')->nullable()->after('repair_decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repair_decided_by');
            $table->dropColumn(['outcome', 'repair_decision', 'repair_decided_at']);
        });
    }
};
