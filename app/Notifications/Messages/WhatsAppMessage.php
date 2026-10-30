<?php

namespace App\Notifications\Messages;

class WhatsAppMessage
{
    public function __construct(public readonly string $text) {}

    public static function make(string $text): self
    {
        return new self($text);
    }
}
