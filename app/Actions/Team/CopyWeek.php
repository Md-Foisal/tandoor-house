<?php

namespace App\Actions\Team;

use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;

class CopyWeek
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /**
     * Copies the previous week's shifts into the chosen week, skipping any that
     * would overlap, that belong to someone no longer on the team, or that the
     * actor may not roster.
     *
     * @return array{copied: int, skipped: list<string>}
     */
    public function __invoke(User $actor, WeekRange $week): array
    {
        return DB::transaction(fn () => $this->copy($actor, $week));
    }

    /**
     * @return array{copied: int, skipped: list<string>}
     */
    private function copy(User $actor, WeekRange $week): array
    {
        $source = Shift::query()
            ->with('user:id,name,role')
            ->where('starts_at', '>=', $week->previous())
            ->where('starts_at', '<', $week->start)
            ->orderBy('starts_at')
            ->get();

        // One lock on every person being copied keeps concurrent edits from slipping an overlap in.
        User::query()->whereIn('id', $source->pluck('user_id')->unique())->orderBy('id')->lockForUpdate()->get(['id']);

        $copied = 0;
        $skipped = [];

        foreach ($source as $shift) {
            $start = $shift->starts_at->addWeek();
            $end = $shift->ends_at->addWeek();

            if (! $shift->user->role->isStaff() || ! TeamRules::canRoster($actor, $shift->user) || SaveShift::clash($shift->user_id, $start, $end) !== null) {
                $skipped[] = $shift->user->name.' '.$start->format('D H:i');

                continue;
            }

            Shift::create([
                'user_id' => $shift->user_id,
                'starts_at' => $start,
                'ends_at' => $end,
                'role' => $shift->role,
                'notes' => $shift->notes,
            ]);
            $copied++;
        }

        if ($copied > 0 || $skipped !== []) {
            $this->activity->record('shift.copied', __('staff_team.log.week_copied', [
                'user' => $actor->name,
                'week' => $week->label(),
                'count' => $copied,
                'skipped' => count($skipped),
            ]), $actor, ['week' => $week->start->toDateString(), 'copied' => $copied, 'skipped' => $skipped], $actor);
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}
