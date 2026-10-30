<?php

namespace App\Notifications\WhatsApp;

interface WhatsAppSender
{
    /** Sends one message to an E.164 number; false when nothing was sent. */
    public function send(string $to, string $text): bool;
}
