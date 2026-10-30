<?php

namespace App\Notifications\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development sender. Numbers are masked and link query strings dropped because
 * signed order and booking links grant pay and cancel rights.
 */
class LogSmsSender implements SmsSender
{
    public function __construct(private readonly string $channel = 'sms') {}

    public function send(string $to, string $text): bool
    {
        Log::channel($this->channel)->info('sms', ['to' => $this->mask($to), 'text' => $this->redact($text)]);

        return true;
    }

    private function mask(string $number): string
    {
        $length = strlen($number);

        return $length <= 3 ? $number : str_repeat('*', $length - 3).substr($number, -3);
    }

    private function redact(string $text): string
    {
        return (string) preg_replace('/(https?:\/\/[^\s?#]+)[?#]\S*/i', '$1', $text);
    }
}
