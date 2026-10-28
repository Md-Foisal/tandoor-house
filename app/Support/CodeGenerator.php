<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Short codes that are safe to read over the phone: no 0/O or 1/I.
 */
class CodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const ATTEMPTS = 20;

    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $column, string $prefix, int $length): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $code = $prefix.self::random($length);

            if (! $model::query()->where($column, $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not find an unused code.');
    }

    private static function random(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
