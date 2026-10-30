<?php

namespace App\Reports;

use App\Support\Csv;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportCsv
{
    public static function download(string $section, ReportRange $range): StreamedResponse
    {
        $data = ReportSections::build($section, $range);
        $filename = self::filename($section, $range);

        return Csv::download($filename, null, self::rows($data['tables'], $range), bom: true);
    }

    /**
     * The same file as download(), as a string for an email attachment.
     */
    public static function contents(string $section, ReportRange $range): string
    {
        $data = ReportSections::build($section, $range);
        $out = fopen('php://temp', 'w+') ?: throw new \RuntimeException('Cannot open a temporary stream.');
        fwrite($out, "\xEF\xBB\xBF");

        foreach (self::rows($data['tables'], $range) as $row) {
            fputcsv($out, array_map(Csv::safe(...), $row), ',', '"', '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    public static function filename(string $section, ReportRange $range): string
    {
        return 'tandoor-house-'.str_replace('_', '-', $section).'-'.$range->fileSlug().'.csv';
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @return \Generator<int, list<string|int|float>>
     */
    private static function rows(array $tables, ReportRange $range): \Generator
    {
        yield [(string) config('restaurant.name'), $range->label()];

        foreach ($tables as $table) {
            yield [];
            yield [$table['title']];
            yield array_values(array_map(self::heading(...), $table['columns']));

            foreach ($table['rows'] as $row) {
                yield self::line($table['columns'], $row);
            }

            if ($table['totals'] !== null) {
                yield self::line($table['columns'], $table['totals']);
            }
        }
    }

    /**
     * @param  array{key: string, label: string, type: string}  $column
     */
    private static function heading(array $column): string
    {
        return in_array($column['type'], ['percent', 'change'], true) ? $column['label'].' (%)' : $column['label'];
    }

    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  array<string, mixed>  $row
     * @return list<string|int|float>
     */
    private static function line(array $columns, array $row): array
    {
        return array_map(fn (array $c) => self::cell($row[$c['key']] ?? null, $c['type'], $row), $columns);
    }

    /**
     * Numbers go out as numbers so a negative figure is not mistaken for a formula
     * by the shared guard in Csv and still sums in a spreadsheet.
     *
     * @param  array<string, mixed>  $row
     */
    private static function cell(mixed $value, string $type, array $row): string|int|float
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($type === 'auto') {
            $type = ($row['_format'] ?? 'int') === 'money' ? 'money' : 'int';
        }

        return match ($type) {
            'money' => round((float) $value / 100, 2),
            'int' => (int) $value,
            'percent', 'change', 'minutes' => round((float) $value, 1),
            default => ReportFormat::cell($value, $type, $row, csv: true),
        };
    }
}
