<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Activity\ActivityEntry;
use App\Http\Controllers\Staff\Activity\ActivityQuery;
use App\Http\Requests\Staff\Activity\ActivityFilterRequest;
use App\Models\ActivityLog;
use App\Support\Csv;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ScrollMetadata;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    private const PER_PAGE = 50;

    public const RETENTION_MONTHS = 12;

    public function index(ActivityFilterRequest $request): Response
    {
        $filters = $request->filters();
        $meta = null;

        return Inertia::render('staff/activity/index', [
            'filters' => $filters,
            'today' => Date::now()->format('Y-m-d'),
            'retention_months' => self::RETENTION_MONTHS,
            'families' => fn () => ActivityQuery::families(),
            'people' => fn () => ActivityQuery::people(),
            'feed' => Inertia::scroll(function () use ($filters, &$meta) {
                $page = ActivityQuery::build($filters)->with('user:id,name')->cursorPaginate(self::PER_PAGE)->withQueryString();
                $subjects = ActivityEntry::subjects($page->items());
                // Cursors are read from the last model, so capture them before the rows become arrays.
                $meta = ScrollMetadata::fromPaginator($page);

                return ['data' => $page->getCollection()->map(fn (ActivityLog $log) => ActivityEntry::row($log, $subjects))->all()];
            }, 'data', function () use (&$meta) {
                return $meta;
            }),
        ]);
    }

    public function export(ActivityFilterRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $rows = function () use ($filters) {
            foreach (ActivityQuery::build($filters)->with('user:id,name')->lazy(500)->chunk(500) as $chunk) {
                $subjects = ActivityEntry::subjects($chunk);

                foreach ($chunk as $log) {
                    $row = ActivityEntry::row($log, $subjects);

                    yield [
                        $log->created_at->format('d/m/Y H:i'),
                        $row['actor']['name'] ?? __('staff_activity.automation'),
                        ActivityQuery::familyLabel($row['family']),
                        $log->event,
                        $log->description,
                        $row['subject']['label'] ?? '',
                        implode('; ', array_map(fn (array $d) => "{$d['label']}: {$d['value']}", $row['details'])),
                    ];
                }
            }
        };

        return Csv::download(
            'activity-log-'.Date::now()->format('Y-m-d').'.csv',
            [__('staff_activity.csv.time'), __('staff_activity.csv.who'), __('staff_activity.csv.family'), __('staff_activity.csv.event'), __('staff_activity.csv.description'), __('staff_activity.csv.subject'), __('staff_activity.csv.details')],
            $rows(),
        );
    }
}
