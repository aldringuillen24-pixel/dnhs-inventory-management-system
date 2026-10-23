<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RESTORABLE_STATUSES = [
        'available',
        'assigned',
        'under_repair',
        'ready_to_dispose',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('inspection_records')
                ->whereIn('status', ['flagged', 'repair_recommended'])
                ->orderBy('id')
                ->get()
                ->each(function (object $record) use ($now): void {
                    $statusBefore = in_array($record->status_before, self::RESTORABLE_STATUSES, true)
                        ? $record->status_before
                        : 'available';

                    DB::table('inventory')
                        ->where('item_id', $record->inventory_id)
                        ->where('status', 'under_inspection')
                        ->update([
                            'status' => $statusBefore,
                            'updated_at' => $now,
                        ]);

                    $updates = [
                        'status' => 'inspected',
                        'updated_at' => $now,
                    ];

                    if ($record->status === 'flagged') {
                        $updates['outcome'] = 'cancelled';
                        $updates['inspected_at'] = $now;
                    } else {
                        $updates['repair_decision'] = 'declined';
                        $updates['repair_decided_at'] = $now;
                    }

                    DB::table('inspection_records')
                        ->where('id', $record->id)
                        ->update($updates);
                });
        });
    }

    public function down(): void
    {
        // Resolved records are not reopened automatically when rolling back.
    }
};
