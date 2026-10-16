<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return;
        }

        Schema::table('ai_api_keys', function (Blueprint $table): void {
            // Last per-key probe outcome. Tokens come from the provider's
            // usageMetadata; null when the provider reports none.
            if (! Schema::hasColumn('ai_api_keys', 'last_tokens_sent')) {
                $table->unsignedInteger('last_tokens_sent')->nullable()->after('last_error');
            }
            if (! Schema::hasColumn('ai_api_keys', 'last_ping_ok')) {
                $table->boolean('last_ping_ok')->nullable()->after('last_tokens_sent');
            }
            if (! Schema::hasColumn('ai_api_keys', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('last_ping_ok');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_api_keys')) {
            return;
        }

        Schema::table('ai_api_keys', function (Blueprint $table): void {
            foreach (['last_checked_at', 'last_ping_ok', 'last_tokens_sent'] as $column) {
                if (Schema::hasColumn('ai_api_keys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
