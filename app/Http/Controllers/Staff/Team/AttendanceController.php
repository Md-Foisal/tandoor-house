<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\Csv;
use App\Actions\Team\FixClockOut;
use App\Actions\Team\WeekRange;
use App\Actions\Team\WeekReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Team\FixClockOutRequest;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function fix(FixClockOutRequest $request, Attendance $attendance, FixClockOut $fix): RedirectResponse
    {
        $attendance->loadMissing('user');
        $fix($request->user(), $attendance, $request->string('clocked_out_at')->toString(), $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.clock_out_fixed', ['name' => $attendance->user->name])]);

        return back();
    }

    public function export(Request $request, WeekReport $report): StreamedResponse
    {
        $week = WeekRange::from($request->query('week') === null ? null : (string) $request->query('week'));
        $people = $report->build($week);

        $rows = (function () use ($people) {
            foreach ($people as $person) {
                foreach ($person['entries'] as $entry) {
                    $in = CarbonImmutable::parse($entry['in']);

                    yield [
                        $person['name'],
                        $person['email'],
                        __('roles.'.$person['role']),
                        $in->format('Y-m-d'),
                        $in->format('H:i'),
                        $entry['out'] === null ? '' : CarbonImmutable::parse($entry['out'])->format('H:i'),
                        number_format($entry['minutes'] / 60, 2, '.', ''),
                        $entry['shift'] ?? '',
                        $entry['late_minutes'],
                        match (true) {
                            $entry['missed'] => __('staff_team.csv.missed'),
                            $entry['fixed'] => __('staff_team.csv.fixed'),
                            default => '',
                        },
                    ];
                }
            }
        })();

        return Csv::download(
            'attendance-'.$week->start->toDateString().'.csv',
            array_map(fn (string $key) => __('staff_team.csv.attendance.'.$key), ['name', 'email', 'role', 'date', 'in', 'out', 'hours', 'shift', 'late', 'note']),
            $rows,
        );
    }
}
