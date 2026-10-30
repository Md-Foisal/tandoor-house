<?php

namespace App\Support;

class MarkdownText
{
    /** Makes customer-supplied text render literally inside a Markdown mail. */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]<>()#+\-.!|~&:@])/', '\\\\$1', $text);
    }
}
