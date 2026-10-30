<?php

namespace App\Notifications\Channels;

use App\Notifications\PhoneNumber;
use App\Notifications\WhatsApp\WhatsAppSender;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppSender $sender) {}

    public function send(object $notifiable, Notification $notification): bool
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return false;
        }

        $to = PhoneNumber::routeFor($notifiable, 'whatsapp', $notification);

        if ($to === null) {
            return false;
        }

        return $this->sender->send($to, $notification->toWhatsApp($notifiable)->text);
    }
}
