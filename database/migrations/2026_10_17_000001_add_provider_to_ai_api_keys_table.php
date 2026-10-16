<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_api_keys') || Schema::hasColumn('ai_api_keys', 'provider')) {
            return;
        }

        Schema::table('ai_api_keys', function (Blueprint $table): void {
            // System provider the key belongs to (gemini, forecast, …).
            // Existing rows predate providers and are all Gemini keys.
            $table->string('provider', 30)->default('gemini')->after('label')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_api_keys') && Schema::hasColumn('ai_api_keys', 'provider')) {
            Schema::table('ai_api_keys', function (Blueprint $table): void {
                $table->dropColumn('provider');
            });
        }
    }
};
