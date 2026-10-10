<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The durable conversation memory for the AI assistant.
 *
 * A turn log cannot live in the session: session payloads are serialised into
 * every request, and the assistant needs older turns than any session window
 * would allow. Two tables are used rather than one so that the lifetime of a
 * conversation is owned by a single row that can be touched, aged out, or purged
 * without touching its turns.
 *
 * `json` is used rather than `jsonb` on purpose. The test suite runs on SQLite,
 * where an unknown type name such as `jsonb` receives NUMERIC affinity and stops
 * behaving like text. The application validates the shape of both columns on
 * read, so the database is not asked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id', 64)->unique();
            $table->timestamp('last_touch_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'last_touch_at']);
        });

        Schema::create('ai_conversation_turns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_id', 64);
            $table->unsignedInteger('turn_index');

            $table->text('question');
            $table->text('reply');

            // success | clarification | forbidden | not_found | unsupported
            $table->string('status', 32);

            // The resolved request: intent, capability, response_type, entity
            // references and filters. Stored and validated in application code.
            $table->json('resolved')->nullable();

            // The inventory ids offered to the user this turn, in the order they
            // were offered. This is the only thing that can resolve an ordinal
            // reference such as "the second one".
            $table->json('candidate_ids')->nullable();

            $table->timestamps();

            $table->unique(['session_id', 'turn_index']);
            $table->index(['user_id', 'created_at']);
            $table->index(['session_id', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_turns');
        Schema::dropIfExists('ai_conversation_sessions');
    }
};