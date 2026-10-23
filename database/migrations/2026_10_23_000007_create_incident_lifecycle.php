<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_records', 'incident_status')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->string('incident_status')->nullable();
            });
        }

        if (! Schema::hasIndex('maintenance_records', ['incident_status'])) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->index('incident_status');
            });
        }

        if (! Schema::hasColumn('maintenance_records', 'incident_outcome')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->string('incident_outcome')->nullable();
            });
        }

        if (! Schema::hasColumn('maintenance_records', 'assignment_transaction_id')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->foreignId('assignment_transaction_id')
                    ->nullable()
                    ->constrained('transactions')
                    ->nullOnDelete();
            });
        } elseif (! $this->hasForeignKeyOn('maintenance_records', 'assignment_transaction_id')) {
            Schema::table('maintenance_records', function (Blueprint $table): void {
                $table->foreign('assignment_transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('maintenance_record_events')) {
            Schema::create('maintenance_record_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('maintenance_record_id')
                    ->constrained('maintenance_records')
                    ->restrictOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_type');
                $table->string('from_status')->nullable();
                $table->string('to_status')->nullable();
                $table->text('details')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['maintenance_record_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('maintenance_record_evidence')) {
            Schema::create('maintenance_record_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('maintenance_record_id')
                    ->constrained('maintenance_records')
                    ->restrictOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('original_filename');
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('size');
                $table->timestamps();
                $table->index('maintenance_record_id');
            });
        }

        DB::table('maintenance_records')
            ->whereNotNull('diagnosis')
            ->orderBy('id')
            ->get(['id', 'reported_by', 'status', 'created_at', 'incident_status'])
            ->each(function (object $record): void {
                $mappedStatus = match ($record->status) {
                    'reported' => 'reported',
                    'in_progress' => 'under_review',
                    'cancelled' => 'declined',
                    'completed' => 'resolved',
                    default => null,
                };

                $incidentStatus = $record->incident_status ?: $mappedStatus;
                if ($incidentStatus === null) {
                    return;
                }

                if ($record->incident_status === null) {
                    DB::table('maintenance_records')
                    ->where('id', $record->id)
                        ->update(['incident_status' => $incidentStatus]);
                }

                $hasImportEvent = DB::table('maintenance_record_events')
                    ->where('maintenance_record_id', $record->id)
                    ->where('event_type', 'legacy_state_imported')
                    ->exists();
                if ($hasImportEvent) {
                    return;
                }

                DB::table('maintenance_record_events')->insert([
                    'maintenance_record_id' => $record->id,
                    'actor_id' => null,
                    'event_type' => 'legacy_state_imported',
                    'from_status' => null,
                    'to_status' => $incidentStatus,
                    'details' => 'Lifecycle state imported from the existing incident report.',
                    'metadata' => json_encode([
                        'legacy_status' => $record->status,
                        'historical_actor_known' => false,
                    ]),
                    'created_at' => $record->created_at ?? now(),
                ]);
            });
    }

    private function hasForeignKeyOn(string $tableName, string $column): bool
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_record_evidence');
        Schema::dropIfExists('maintenance_record_events');

        Schema::table('maintenance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assignment_transaction_id');
            $table->dropIndex(['incident_status']);
            $table->dropColumn(['incident_status', 'incident_outcome']);
        });
    }
};
