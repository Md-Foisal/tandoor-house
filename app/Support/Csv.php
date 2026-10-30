<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * @param  list<string>|null  $header  null when the rows carry their own headings
     * @param  iterable<list<string|int|float>>  $rows
     * @param  bool  $bom  prefix a UTF-8 byte order mark so Excel reads £ and accents correctly
     */
    public static function download(string $filename, ?array $header, iterable $rows, bool $bom = false): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $bom) {
            $out = fopen('php://output', 'w') ?: throw new \RuntimeException('Cannot open the output stream.');

            if ($bom) {
                fwrite($out, "\xEF\xBB\xBF");
            }

            if ($header !== null) {
                fputcsv($out, $header, ',', '"', '');
            }

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safe(...), $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops a name like "=HYPERLINK(...)" running as a formula in a spreadsheet. */
    public static function safe(string|int|float $cell): string|int|float
    {
        if (is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$cell;
        }

        return $cell;
    }
}
