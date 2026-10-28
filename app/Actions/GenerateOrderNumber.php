<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Support\Str;

class GenerateOrderNumber
{
    // No 0/O or 1/I, so a number read over the phone is never ambiguous.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function handle(): string
    {
        do {
            $number = 'TH-'.collect(range(1, 5))
                ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->implode('');
        } while (Order::query()->where('number', $number)->exists());

        return Str::upper($number);
    }
}
