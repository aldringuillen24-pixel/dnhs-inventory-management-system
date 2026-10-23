<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')
                ->unique()
                ->constrained('maintenance_record_events')
                ->restrictOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_notification_deliveries');
    }
};
