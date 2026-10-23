<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncidentReportUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reporterName,
        public string $itemName,
        public string $eventTitle,
        public string $updateMessage,
        public string $reportUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Incident report update: {$this->eventTitle}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incident-report-update',
            with: [
                'reporterName' => $this->reporterName,
                'itemName' => $this->itemName,
                'eventTitle' => $this->eventTitle,
                'updateMessage' => $this->updateMessage,
                'reportUrl' => $this->reportUrl,
            ],
        );
    }
}
