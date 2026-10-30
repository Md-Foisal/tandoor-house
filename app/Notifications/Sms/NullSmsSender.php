<?php

namespace App\Notifications\Sms;

class NullSmsSender implements SmsSender
{
    public function send(string $to, string $text): bool
    {
        return false;
    }
}
