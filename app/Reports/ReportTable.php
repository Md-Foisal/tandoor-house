<?php

namespace App\Reports;

/**
 * A titled table that the page, the CSV export and the PDF all render.
 * Column types: text, int, money (pence), percent, change (signed percent,
 * null for none), minutes, date (Y-m-d), rate (basis points), auto (a row's
 * own `_format`: money or int).
 */
final class ReportTable
{
    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?array $totals = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * @param  array<string, string>  $columns  key => "type|Label"
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    public static function make(string $key, string $title, array $columns, array $rows, ?array $totals = null, ?string $note = null): self
    {
        $built = [];

        foreach ($columns as $column => $spec) {
            [$type, $label] = explode('|', $spec, 2);
            $built[] = ['key' => $column, 'label' => $label, 'type' => $type];
        }

        return new self($key, $title, $built, $rows, $totals, $note);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'totals' => $this->totals,
            'note' => $this->note,
        ];
    }
}
