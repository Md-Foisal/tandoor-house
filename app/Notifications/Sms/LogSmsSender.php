<?php

namespace App\Notifications\Sms;

use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function __construct(private readonly string $channel = 'sms') {}

    public function send(string $to, string $text): bool
    {
        Log::channel($this->channel)->info('sms', ['to' => $to, 'text' => $text]);

        return true;
    }
}
