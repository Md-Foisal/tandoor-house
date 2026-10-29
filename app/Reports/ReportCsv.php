<?php

namespace App\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportCsv
{
    public static function download(string $section, ReportRange $range): StreamedResponse
    {
        $data = ReportSections::build($section, $range);
        $filename = 'tandoor-house-'.str_replace('_', '-', $section).'-'.$range->fileSlug().'.csv';

        return response()->streamDownload(function () use ($data, $range) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // The byte order mark makes Excel open the file as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [config('restaurant.name'), $range->label()]);

            foreach ($data['tables'] as $table) {
                fputcsv($out, []);
                fputcsv($out, [self::safe($table['title'])]);
                fputcsv($out, array_map(fn (array $c) => self::safe($c['label']), $table['columns']));

                foreach ($table['rows'] as $row) {
                    fputcsv($out, self::line($table['columns'], $row));
                }

                if ($table['totals'] !== null) {
                    fputcsv($out, self::line($table['columns'], $table['totals']));
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function line(array $columns, array $row): array
    {
        return array_map(
            fn (array $c) => self::safe(ReportFormat::cell($row[$c['key']] ?? null, $c['type'], $row, csv: true)),
            $columns,
        );
    }

    /**
     * Stops a dish name that starts with = or + from running as a spreadsheet formula.
     */
    private static function safe(string $value): string
    {
        return preg_match('/^[=@\t\r]/', $value) === 1 || preg_match('/^[+-][^0-9.]/', $value) === 1 ? "'".$value : $value;
    }
}
