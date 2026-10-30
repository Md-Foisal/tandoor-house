<?php

namespace App\Http\Controllers\Staff\Team;

use App\Actions\Team\RecordTipAllocations;
use App\Actions\Team\TipSplit;
use App\Actions\Team\WeekRange;
use App\Actions\Team\WeekReport;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Team\PayTipsRequest;
use App\Http\Requests\Staff\Team\RecordTipsRequest;
use App\Models\TipAllocation;
use App\Models\User;
use App\Support\ActivityRecorder;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TipsController extends Controller
{
    /**
     * The live split plus what has been recorded for the week.
     *
     * @return array<string, mixed>
     */
    public static function overview(User $actor, TipSplit $tips, WeekReport $report, WeekRange $week): array
    {
        $split = TeamController::split($tips, $report, $week);
        $recorded = self::recorded($week);
        $live = collect($split['shares'])->pluck('share', 'id');
        $paidAt = $recorded->first(fn (TipAllocation $row) => $row->paid_at !== null)?->paid_at;
        $over = $week->end()->isPast();

        $rows = $recorded->map(fn (TipAllocation $row) => [
            'id' => $row->user_id,
            'name' => $row->user->name,
            'role' => $row->user->role->value,
            'minutes' => $row->minutes_worked,
            'amount' => $row->amount,
            'live' => (int) ($live[$row->user_id] ?? 0),
        ])->values()->all();

        $differs = $recorded->isNotEmpty()
            && $recorded->pluck('amount', 'user_id')->sortKeys()->all() !== $live->filter()->sortKeys()->all();

        return [
            ...$split,
            'recording' => [
                'status' => match (true) {
                    $recorded->isEmpty() => 'none',
                    $paidAt !== null => 'paid',
                    default => 'recorded',
                },
                'recorded_at' => $recorded->max('updated_at')?->toIso8601String(),
                'recorded_by' => $recorded->first()?->recorder?->name,
                'paid_at' => $paidAt?->toIso8601String(),
                'rows' => $rows,
                'total' => (int) $recorded->sum('amount'),
                'differs' => $differs,
                'week_over' => $over,
                'available_on' => $week->end()->toDateString(),
                'can_record' => $over && $paidAt === null,
                'can_pay' => $actor->role === Role::Owner && $recorded->isNotEmpty() && $paidAt === null,
            ],
        ];
    }

    public function record(RecordTipsRequest $request, RecordTipAllocations $record): RedirectResponse
    {
        $rows = $record($request->user(), $request->week());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.tips_recorded', [
            'total' => Money::format((int) $rows->sum('amount')),
            'count' => $rows->count(),
        ])]);

        return back();
    }

    public function pay(PayTipsRequest $request, ActivityRecorder $activity): RedirectResponse
    {
        $week = $request->week();
        $period = $week->start->toDateString();

        $paid = DB::transaction(function () use ($period) {
            $rows = TipAllocation::query()->where('period', $period)->lockForUpdate()->get();

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['week' => __('staff_team.errors.tips_not_recorded')]);
            }

            $open = $rows->whereNull('paid_at');
            TipAllocation::query()->whereKey($open->modelKeys())->update(['paid_at' => now()]);

            return $open;
        });

        if ($paid->isEmpty()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('staff_team.toast.tips_already_paid')]);

            return back();
        }

        $activity->record('tips.paid', __('staff_team.log.tips_paid', [
            'user' => $request->user()->name,
            'week' => $week->start->format('j M Y'),
            'total' => Money::format((int) $paid->sum('amount')),
        ]), null, ['period' => $period, 'total' => (int) $paid->sum('amount')], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_team.toast.tips_paid')]);

        return back();
    }

    public function export(Request $request, TipSplit $tips, WeekReport $report): StreamedResponse
    {
        $week = WeekRange::from($request->query('week') === null ? null : (string) $request->query('week'));
        $recorded = self::recorded($week);

        $rows = $recorded->isNotEmpty()
            ? $recorded->map(fn (TipAllocation $row) => [$row->user->name, $row->user->role->value, $row->minutes_worked, $row->amount])->all()
            : array_map(fn (array $row) => [$row['name'], $row['role'], $row['minutes'], $row['share']], TeamController::split($tips, $report, $week)['shares']);

        return Csv::download(
            'tips-'.$week->start->toDateString().'.csv',
            array_map(fn (string $key) => __('staff_team.csv.tips.'.$key), ['name', 'role', 'hours', 'share']),
            array_map(fn (array $row) => [
                $row[0],
                __('roles.'.$row[1]),
                number_format($row[2] / 60, 2, '.', ''),
                number_format($row[3] / 100, 2, '.', ''),
            ], $rows),
        );
    }

    /**
     * @return Collection<int, TipAllocation>
     */
    private static function recorded(WeekRange $week): Collection
    {
        return TipAllocation::query()
            ->with(['user:id,name,role', 'recorder:id,name'])
            ->where('period', $week->start->toDateString())
            ->orderBy('user_id')
            ->get();
    }
}
