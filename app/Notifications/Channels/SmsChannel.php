<?php

namespace App\Notifications\Channels;

use App\Notifications\PhoneNumber;
use App\Notifications\Sms\SmsSender;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    /** True when a text was handed to the sender. */
    public function send(object $notifiable, Notification $notification): bool
    {
        if (! method_exists($notification, 'toSms')) {
            return false;
        }

        $to = PhoneNumber::routeFor($notifiable, 'sms', $notification);

        if ($to === null) {
            return false;
        }

        $message = $notification->toSms($notifiable);

        if ($message->segments() === 0) {
            return false;
        }

        return $this->sender->send($to, $message->fitted((int) config('notify.sms.max_segments', 3)));
    }
}
