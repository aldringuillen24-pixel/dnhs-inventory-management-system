<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved AI-written recommendations for one trained forecast cycle.
 *
 * The Recommendations tab needs a handful of provider calls to write a brief
 * and a sentence per actionable item. Running those on every page view would be
 * slow and would repeat identical work for every custodian, so the result is
 * stored and reused until the model is retrained.
 *
 * The cache is keyed on the forecast's own `generated_at` stamp rather than on
 * `forecast_period`. A retrain inside the same month produces the same period
 * but different numbers, and serving the previous cycle's advice against fresh
 * figures is exactly the kind of quiet disagreement this codebase avoids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_ai_recommendations', function (Blueprint $table) {
            $table->id();

            // Mirrors forecast_payloads.source_type so live and demo forecasts
            // can never share a cache entry.
            $table->string('source_type', 16)->default('live');

            // The cycle key. Matches ForecastPayload::generated_at.
            $table->timestamp('forecast_generated_at')->nullable();

            // Display only, and never used for lookups.
            $table->string('forecast_period', 64)->nullable();

            // The cycle brief: provider prose over server-computed aggregates.
            $table->longText('brief')->nullable();

            // Per-queue items and their written action line, as JSON. The server
            // reassembles this from the stored forecast on read, so the stored
            // copy is only ever what the provider contributed.
            $table->longText('queues')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'forecast_generated_at'], 'forecast_ai_recs_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_ai_recommendations');
    }
};
