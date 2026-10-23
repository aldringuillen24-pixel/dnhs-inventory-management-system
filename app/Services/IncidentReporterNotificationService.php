<?php

namespace App\Services;

use App\Mail\IncidentReportUpdateMail;
use App\Models\MaintenanceRecordEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class IncidentReporterNotificationService
{
    private const NOTIFIABLE_EVENTS = [
        'report_submitted',
        'status_changed',
        'investigation_action_recorded',
    ];

    public function notify(MaintenanceRecordEvent $event): void
    {
        if (! in_array($event->event_type, self::NOTIFIABLE_EVENTS, true)) {
            return;
        }

        $record = $event->maintenanceRecord()->with(['reportedBy', 'inventory'])->firstOrFail();
        $reporter = $record->reportedBy;
        if (! $reporter || blank($reporter->email)) {
            Log::warning('Incident reporter notification skipped because the reporter has no email address.', [
                'incident_id' => $record->id,
                'event_id' => $event->id,
                'reporter_id' => $record->reported_by,
            ]);

            return;
        }

        try {
            DB::table('incident_notification_deliveries')->insert([
                'event_id' => $event->id,
                'reporter_id' => $reporter->id,
                'status' => 'sending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        [$title, $message] = $this->notificationContent($event);

        try {
            Mail::to($reporter->email)->send(new IncidentReportUpdateMail(
                reporterName: $reporter->full_name ?: $reporter->username,
                itemName: $record->inventory?->item_name ?? 'your reported item',
                eventTitle: $title,
                updateMessage: $message,
                reportUrl: url('/spa/end-user/assigned'),
            ));

            DB::table('incident_notification_deliveries')
                ->where('event_id', $event->id)
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            DB::table('incident_notification_deliveries')
                ->where('event_id', $event->id)
                ->update([
                    'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 1000),
                    'updated_at' => now(),
                ]);
            Log::error('Incident reporter notification could not be sent.', [
                'incident_id' => $record->id,
                'event_id' => $event->id,
                'reporter_id' => $reporter->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    private function notificationContent(MaintenanceRecordEvent $event): array
    {
        if ($event->event_type === 'report_submitted') {
            return [
                'Report received',
                'Your report was received and is waiting for custodian review.',
            ];
        }

        if ($event->event_type === 'investigation_action_recorded') {
            return ['Investigation update', $event->details ?: 'A custodian added an update to your investigation.'];
        }

        return match ($event->to_status) {
            'under_review' => ['Report accepted for review', 'A custodian has accepted your report for review.'],
            'awaiting_reporter' => ['More information requested', $event->details ?: 'A custodian needs more information about your report.'],
            'declined' => ['Report declined', $event->details ?: 'A custodian declined your report.'],
            'under_investigation' => ['Investigation started', 'A custodian started an investigation into your report.'],
            'processing_repair' => ['Repair started', 'The affected units have entered the repair process.'],
            'processing_disposal' => ['Disposal review started', 'The affected units have entered the disposal process.'],
            'resolved' => [
                'Report resolved',
                $event->details ?: 'A custodian resolved your report.',
            ],
            default => [
                'Report status updated',
                $event->details ?: 'A custodian updated the status of your report.',
            ],
        };
    }
}
