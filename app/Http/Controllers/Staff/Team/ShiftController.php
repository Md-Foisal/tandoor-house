<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\CopyWeek;
use App\Actions\Team\SaveShift;
use App\Actions\Team\TeamRules;
use App\Actions\Team\WeekRange;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Team\CopyWeekRequest;
use App\Http\Requests\Staff\Team\SaveShiftRequest;
use App\Models\Shift;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function store(SaveShiftRequest $request, SaveShift $save): RedirectResponse
    {
        return $this->saved($save($request->user(), $request->validated()));
    }

    public function update(SaveShiftRequest $request, Shift $shift, SaveShift $save): RedirectResponse
    {
        return $this->saved($save($request->user(), $request->validated(), $shift));
    }

    public function destroy(Request $request, Shift $shift, ActivityRecorder $activity): RedirectResponse
    {
        Gate::authorize('delete', $shift);
        $actor = $request->user() ?? abort(403);

        DB::transaction(function () use ($actor, $shift, $activity) {
            $shift = Shift::query()->whereKey($shift->id)->lockForUpdate()->first();

            if ($shift === null) {
                return;
            }

            $person = $shift->user()->firstOrFail();
            abort_unless(TeamRules::canRoster($actor, $person), 403);

            $activity->record('shift.deleted', __('staff_team.log.shift_deleted', [
                'user' => $actor->name,
                'name' => $person->name,
                'when' => $shift->starts_at->format('D j M H:i').'–'.$shift->ends_at->format('H:i'),
            ]), $shift, by: $actor);
            $shift->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.shift_deleted')]);

        return back();
    }

    public function copy(CopyWeekRequest $request, CopyWeek $copy): RedirectResponse
    {
        $result = $copy($request->user(), WeekRange::from($request->string('week')->toString()));
        $skipped = $result['skipped'];

        Inertia::flash('toast', [
            'type' => $skipped === [] ? 'success' : 'warning',
            'message' => $result['copied'] === 0 && $skipped === []
                ? __('staff_team.toast.copy_nothing')
                : __('staff_team.toast.copied', ['count' => $result['copied']]).($skipped === [] ? '' : ' '.__('staff_team.toast.copy_skipped', [
                    'count' => count($skipped),
                    'list' => implode(', ', array_slice($skipped, 0, 6)).(count($skipped) > 6 ? '…' : ''),
                ])),
        ]);

        return back();
    }

    /**
     * @param  array{shift: Shift, outside_hours: bool}  $result
     */
    private function saved(array $result): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => $result['outside_hours'] ? 'warning' : 'success',
            'message' => __('staff_team.toast.shift_saved').($result['outside_hours'] ? ' '.__('staff_team.toast.outside_hours') : ''),
        ]);

        return back();
    }
}
