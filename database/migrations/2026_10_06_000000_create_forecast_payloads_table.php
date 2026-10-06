<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the trained forecast out of the container's local disk and into the
 * database.
 *
 * Why: a Render Cron Job trains in a *different* container from the web
 * service, and each web service has an ephemeral filesystem. A file written by
 * one is invisible to the other and is destroyed by the next deploy, so
 * file-based storage made scheduled retraining impossible. One row per source
 * keeps the latest successful run in a place every instance can read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_payloads', function (Blueprint $table) {
            $table->id();
            // 'live' is the trained production forecast; 'demo' is the isolated
            // sample run. One row per source: retraining replaces the row.
            $table->string('source_type', 16)->unique();
            // Nullable because a failed or in-flight training run records its
            // state without producing a document. Making this NOT NULL meant a
            // failed run could not be recorded at all, which hid the failure.
            $table->longText('payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            // 'running' | 'success' | 'failed', mirroring the previous
            // training-status.json file. Kept here so a failed or in-flight run
            // is visible to the web service instead of being invisible.
            $table->string('training_status', 16)->default('success');
            $table->timestamp('training_status_updated_at')->nullable();
            $table->timestamps();

            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_payloads');
    }
};
