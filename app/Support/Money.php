<?php

namespace App\Support;

class Money
{
    /**
     * Pence as pounds for copy: "£12.50", or "£12" when the amount is whole.
     */
    public static function format(int $pence): string
    {
        return '£'.number_format($pence / 100, $pence % 100 === 0 ? 0 : 2);
    }
}
