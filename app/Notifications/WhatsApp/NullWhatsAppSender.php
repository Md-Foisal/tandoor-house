<?php

namespace App\Notifications\WhatsApp;

class NullWhatsAppSender implements WhatsAppSender
{
    public function send(string $to, string $text): bool
    {
        return false;
    }
}
