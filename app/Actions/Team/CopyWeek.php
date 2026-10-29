<?php

namespace App\Actions\Team;

use App\Models\Shift;
use App\Models\User;

class CopyWeek
{
    /**
     * Copies the previous week's shifts into the chosen week, skipping any that
     * would overlap or that belong to someone no longer on the team.
     *
     * @return array{copied: int, skipped: list<string>}
     */
    public function __invoke(User $actor, WeekRange $week): array
    {
        $source = Shift::query()
            ->with('user:id,name,role')
            ->where('starts_at', '>=', $week->previous())
            ->where('starts_at', '<', $week->start)
            ->orderBy('starts_at')
            ->get();

        $copied = 0;
        $skipped = [];

        foreach ($source as $shift) {
            $start = $shift->starts_at->addWeek();
            $end = $shift->ends_at->addWeek();

            if (! $shift->user->role->isStaff() || SaveShift::clash($shift->user_id, $start, $end) !== null) {
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
            TeamActivity::record('shift.copied', $actor, $actor, __('staff_team.log.week_copied', [
                'user' => $actor->name,
                'week' => $week->label(),
                'count' => $copied,
                'skipped' => count($skipped),
            ]), ['week' => $week->start->toDateString(), 'copied' => $copied, 'skipped' => $skipped]);
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}
