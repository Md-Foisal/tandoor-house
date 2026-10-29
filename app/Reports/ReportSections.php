<?php

namespace App\Reports;

use App\Reports\Sections\Breakdown;
use App\Reports\Sections\Items;
use App\Reports\Sections\Kitchen;
use App\Reports\Sections\Overview;
use App\Reports\Sections\Tips;
use App\Reports\Sections\Vat;

final class ReportSections
{
    public const KEYS = ['overview', 'breakdown', 'items', 'kitchen', 'vat', 'tips'];

    /**
     * @return array<string, mixed>
     */
    public static function build(string $section, ReportRange $range): array
    {
        return match ($section) {
            'overview' => (new Overview)->data($range),
            'breakdown' => (new Breakdown)->data($range),
            'items' => (new Items)->data($range),
            'kitchen' => (new Kitchen)->data($range),
            'vat' => (new Vat)->data($range),
            'tips' => (new Tips)->data($range),
            default => abort(404),
        };
    }

    public static function title(string $section): string
    {
        return __("staff_reports.sections.{$section}");
    }
}
