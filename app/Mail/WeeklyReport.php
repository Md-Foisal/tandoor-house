<?php

namespace App\Mail;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyReport extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $report  from App\Reports\Digest::build()
     * @param  array<string, string>  $csv  file name => contents
     */
    public function __construct(
        public readonly array $report,
        public readonly string $name,
        public readonly string $url,
        public readonly array $csv,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('automations.reports.weekly.subject', [
            'restaurant' => config('restaurant.name'),
            'sales' => Money::format($this->report['metrics']['sales']['value']),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.weekly-report', with: [
            'report' => $this->report,
            'name' => $this->name,
            'url' => $this->url,
        ]);
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $contents, string $file) => Attachment::fromData(fn () => $contents, $file)->withMime('text/csv'),
            $this->csv,
            array_keys($this->csv),
        );
    }
}
