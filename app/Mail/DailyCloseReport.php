<?php

namespace App\Mail;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyCloseReport extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report  from App\Reports\Digest::build()
     */
    public function __construct(
        public readonly array $report,
        public readonly string $name,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('automations.reports.daily.subject', [
            'restaurant' => config('restaurant.name'),
            'date' => $this->report['label'],
            'sales' => Money::format($this->report['metrics']['sales']['value']),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.daily-close', with: [
            'report' => $this->report,
            'name' => $this->name,
            'url' => $this->url,
        ]);
    }
}
