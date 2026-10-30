<?php

namespace App\Notifications\Customer;

use App\Models\Reservation;
use App\Notifications\Messages\SmsMessage;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\NotificationChannels;
use App\Support\GuestLinks;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A nudge a couple of hours before the table is due.
 */
class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Reservation $reservation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for('booking');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notify.booking.reminder.subject', $this->replacements()))
            ->markdown('mail.booking-reminder', [
                'name' => MarkdownText::escape(explode(' ', trim($this->reservation->name))[0]),
                'headline' => __('notify.booking.reminder.headline', $this->replacements()),
                'body' => __('notify.booking.reminder.body', $this->replacements()),
                'reservation' => $this->reservation,
                'when' => $this->when(),
                'url' => GuestLinks::booking($this->reservation),
            ]);
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::make($this->text());
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::make($this->text());
    }

    public function text(): string
    {
        return __('notify.booking.reminder.sms', $this->replacements()).' '.GuestLinks::booking($this->reservation);
    }

    private function when(): string
    {
        return $this->reservation->starts_at->timezone('Europe/London')->translatedFormat('H:i');
    }

    /**
     * @return array<string, string|int>
     */
    private function replacements(): array
    {
        return [
            'restaurant' => config('restaurant.name'),
            'when' => $this->when(),
            'party' => $this->reservation->party_size,
            'reference' => $this->reservation->reference,
        ];
    }
}
