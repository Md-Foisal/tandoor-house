<?php

namespace App\Notifications\Sms;

interface SmsSender
{
    /** Sends one text to an E.164 number; false when the provider refused it. */
    public function send(string $to, string $text): bool;
}
