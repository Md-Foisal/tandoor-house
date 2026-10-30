<?php

namespace App\Actions\Team;

use App\Models\TipAllocation;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the week's tips split so it can be paid out. Recording again replaces
 * the week's rows, until any of them is marked paid.
 */
class RecordTipAllocations
{
    public function __construct(
        private readonly TipSplit $tips,
        private readonly WeekReport $report,
        private readonly ActivityRecorder $activity,
    ) {}

    /**
     * @return Collection<int, TipAllocation>
     */
    public function __invoke(User $actor, WeekRange $week): Collection
    {
        $period = $week->start->toDateString();

        $rows = DB::transaction(function () use ($actor, $week, $period) {
            $existing = TipAllocation::query()->where('period', $period)->lockForUpdate()->get();

            if ($existing->contains(fn (TipAllocation $row) => $row->paid_at !== null)) {
                throw ValidationException::withMessages(['week' => __('notify.tips.paid', ['week' => $week->label()])]);
            }

            $pot = $this->tips->pot($week);
            $minutes = collect($this->report->build($week))
                ->filter(fn (array $person) => $person['clocked_minutes'] > 0)
                ->pluck('clocked_minutes', 'id')
                ->all();
            $shares = TipSplit::allocate($pot['total'], $minutes);

            TipAllocation::query()->where('period', $period)->delete();

            return collect($shares)->map(fn (int $amount, int $userId) => TipAllocation::create([
                'period' => $period,
                'user_id' => $userId,
                'amount' => $amount,
                'minutes_worked' => $minutes[$userId],
                'recorded_by' => $actor->id,
            ]))->values();
        });

        $this->activity->record('tips.recorded', __('notify.tips.recorded', [
            'user' => $actor->name,
            'week' => $week->start->format('j M Y'),
            'total' => Money::format((int) $rows->sum('amount')),
            'people' => trans_choice('{1} :count person|[2,*] :count people', $rows->count()),
        ]), null, ['period' => $period, 'total' => (int) $rows->sum('amount')], $actor);

        return $rows;
    }
}
