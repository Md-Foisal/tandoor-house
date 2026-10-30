<?php

namespace App\Notifications\Messages;

class SmsMessage
{
    /** Characters in one GSM-7 segment, and in each part of a longer message. */
    private const SINGLE = 160;

    private const MULTIPART = 153;

    public function __construct(public readonly string $text) {}

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function segments(): int
    {
        $length = mb_strlen($this->text);

        if ($length === 0) {
            return 0;
        }

        return $length <= self::SINGLE ? 1 : (int) ceil($length / self::MULTIPART);
    }

    /** The text cut to fit the most segments allowed. */
    public function fitted(int $maxSegments): string
    {
        $limit = $maxSegments <= 1 ? self::SINGLE : $maxSegments * self::MULTIPART;

        return mb_strlen($this->text) <= $limit ? $this->text : rtrim(mb_substr($this->text, 0, $limit - 1)).'…';
    }
}
