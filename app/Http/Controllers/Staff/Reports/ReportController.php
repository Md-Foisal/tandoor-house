<?php

namespace App\Http\Controllers\Staff\Reports;

use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Reports\ReportRequest;
use App\Reports\ReportRange;
use App\Reports\ReportSections;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(ReportRequest $request): Response
    {
        $range = $request->range();
        $query = $range->query();

        $links = [
            'pdf' => route('staff.reports.pdf', $query),
            'export' => collect(ReportSections::KEYS)
                ->mapWithKeys(fn (string $key) => [$key => route('staff.reports.export', ['section' => $key, ...$query])])
                ->all(),
        ];

        $props = [
            'filters' => [
                'period' => $range->period,
                'from' => $range->start->format('Y-m-d'),
                'to' => $range->lastDay()->format('Y-m-d'),
                'channel' => $range->channel?->value,
            ],
            'range' => [
                'label' => $range->label(),
                'days' => $range->days(),
                'previous_label' => $range->previousLabel(),
                'max_days' => ReportRange::MAX_DAYS,
            ],
            'periods' => ReportRange::PERIODS,
            'channels' => array_map(fn (OrderChannel $c) => $c->value, OrderChannel::cases()),
            'links' => $links,
        ];

        foreach (ReportSections::KEYS as $key) {
            $props[$key] = Inertia::defer(fn () => ReportSections::build($key, $range), $key);
        }

        return Inertia::render('staff/reports/index', $props);
    }
}
