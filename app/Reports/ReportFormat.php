<?php

namespace App\Reports;

/**
 * Plain-text cells for the CSV and the PDF: pounds with two decimals, en-GB dates.
 */
final class ReportFormat
{
    public static function pounds(int|float $pence): string
    {
        return number_format($pence / 100, 2, '.', '');
    }

    public static function money(int|float $pence): string
    {
        $pounds = number_format(abs($pence) / 100, 2);

        return ($pence < 0 ? '-£' : '£').$pounds;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function cell(mixed $value, string $type, array $row = [], bool $csv = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($type === 'auto') {
            $type = ($row['_format'] ?? 'int') === 'money' ? 'money' : 'int';
        }

        return match ($type) {
            'money' => $csv ? self::pounds((float) $value) : self::money((float) $value),
            'int' => (string) (int) $value,
            'percent' => number_format((float) $value, 1).'%',
            'change' => ((float) $value > 0 ? '+' : '').number_format((float) $value, 1).'%',
            'minutes' => number_format((float) $value, 1),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1 ? date('d/m/Y', (int) strtotime((string) $value)) : (string) $value,
            'rate' => ! is_numeric($value) ? (string) $value : rtrim(rtrim(number_format((float) $value / 100, 2), '0'), '.').'%',
            default => (string) $value,
        };
    }
}
