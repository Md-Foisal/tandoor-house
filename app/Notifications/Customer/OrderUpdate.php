<?php

namespace App\Notifications\Customer;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\Messages\SmsMessage;
use App\Notifications\Messages\WhatsAppMessage;
use App\Notifications\NotificationChannels;
use App\Support\GuestLinks;
use App\Support\MarkdownText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /** Statuses a customer is told about; the rest are kitchen detail. */
    private const NOTIFY = [
        OrderStatus::Placed,
        OrderStatus::Accepted,
        OrderStatus::Ready,
        OrderStatus::OutForDelivery,
        OrderStatus::Delivered,
        OrderStatus::Rejected,
        OrderStatus::Cancelled,
    ];

    public function __construct(public readonly Order $order, public readonly OrderStatus $stage) {}

    public static function shouldNotify(Order $order, OrderStatus $stage): bool
    {
        if (! in_array($stage, self::NOTIFY, true)) {
            return false;
        }

        if ($order->channel === OrderChannel::DineIn && ! in_array($stage, [OrderStatus::Rejected, OrderStatus::Cancelled], true)) {
            return false;
        }

        // Ready only means something to a customer who has to come and fetch it.
        return $stage !== OrderStatus::Ready || $order->channel === OrderChannel::Pickup;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for('order_update');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->line('subject'))
            ->markdown('mail.order-update', [
                'name' => MarkdownText::escape($this->firstName()),
                'headline' => $this->line('headline', true),
                'body' => $this->line('body', true),
                'order' => $this->order,
                'url' => $this->url(),
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
        return $this->line('sms').' '.$this->url();
    }

    private function url(): string
    {
        return GuestLinks::order($this->order);
    }

    private function firstName(): string
    {
        return explode(' ', trim((string) ($this->order->contact['name'] ?? '')))[0];
    }

    private function line(string $part, bool $markdown = false): string
    {
        $time = ($this->order->estimated_ready_at ?? $this->order->pickup_at)?->timezone('Europe/London')->format('H:i') ?? '';

        $reason = $this->order->rejection_reason ?: __('notify.order.no_reason');
        $replace = [
            'number' => $this->order->number,
            'restaurant' => config('restaurant.name'),
            'time' => $time,
            'reason' => $markdown ? MarkdownText::escape($reason) : $reason,
        ];

        return __("notify.order.{$this->stage->value}.{$part}", $replace);
    }
}
