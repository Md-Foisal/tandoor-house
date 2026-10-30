<?php

namespace App\Notifications\Customer;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\Messages\SmsMessage;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation or cancellation of a table, depending on the status passed in.
 */
class BookingNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Reservation $reservation, public readonly ReservationStatus $stage) {}

    public static function shouldNotify(ReservationStatus $stage): bool
    {
        return in_array($stage, [ReservationStatus::Confirmed, ReservationStatus::Cancelled], true);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for('booking');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $confirmed = $this->confirmed();

        $mail = (new MailMessage)
            ->subject(__($this->key('subject'), $this->replacements()))
            ->markdown('mail.booking', [
                'name' => $this->firstName(),
                'headline' => __($this->key('headline'), $this->replacements()),
                'body' => __($this->key('body'), $this->replacements()),
                'reservation' => $this->reservation,
                'when' => $this->when(),
                'confirmed' => $confirmed,
                'url' => $this->url(),
            ]);

        if ($confirmed) {
            $mail->attachData(CalendarInvite::for($this->reservation), 'booking.ics', ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH']);
        }

        return $mail;
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
        return __($this->key('sms'), $this->replacements()).' '.$this->url();
    }

    private function confirmed(): bool
    {
        return $this->stage === ReservationStatus::Confirmed;
    }

    private function key(string $part): string
    {
        return 'notify.booking.'.($this->confirmed() ? 'confirmed' : 'cancelled').'.'.$part;
    }

    private function when(): string
    {
        return $this->reservation->starts_at->timezone('Europe/London')->translatedFormat('l j F \a\t H:i');
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

    private function url(): string
    {
        return route('book.show', $this->reservation);
    }

    private function firstName(): string
    {
        return explode(' ', trim($this->reservation->name))[0];
    }
}
