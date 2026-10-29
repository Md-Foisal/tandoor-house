<?php

namespace App\Http\Controllers\Staff\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reports\ReportRequest;
use App\Reports\ReportFormat;
use App\Reports\ReportSections;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReportPdfController extends Controller
{
    public function __invoke(ReportRequest $request): Response
    {
        $range = $request->range();

        $sections = collect(ReportSections::KEYS)->map(fn (string $key) => [
            'key' => $key,
            'title' => ReportSections::title($key),
            'tables' => ReportSections::build($key, $range)['tables'],
        ])->all();

        return Pdf::loadView('pdf.report', [
            'restaurant' => config('restaurant'),
            'period' => $range->label(),
            'channel' => $range->channel ? __("staff.channel.{$range->channel->value}") : __('staff_reports.all_channels'),
            'sections' => $sections,
            'format' => new ReportFormat,
            'generated' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4')->download('tandoor-house-report-'.$range->fileSlug().'.pdf');
    }
}
