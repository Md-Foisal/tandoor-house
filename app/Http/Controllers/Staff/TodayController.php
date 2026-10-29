<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Today\TodayBoard;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\OpeningHours;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(Request $request, OpeningHours $hours, TodayBoard $board): Response
    {
        /** @var User $user */
        $user = $request->user();
        $now = now();

        return Inertia::render('staff/today', [
            'first_name' => explode(' ', trim($user->name))[0],
            'date' => $now->isoFormat('dddd D MMMM'),
            'server_time' => $now->toIso8601String(),
            'open' => $hours->status(),
            'periods' => array_map(
                fn (array $period) => $period[0]->format('H:i').' – '.$period[1]->format('H:i'),
                $hours->periodsOn($now),
            ),
            'view' => TodayBoard::viewFor($user->role),
            'shift' => $this->shift($user),
            'blocks' => Inertia::defer(fn () => $board->for($user)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shift(User $user): array
    {
        $today = now()->startOfDay();

        $shift = Shift::query()
            ->where('user_id', $user->id)
            ->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->first();

        $open = Attendance::query()->where('user_id', $user->id)->whereNull('clocked_out_at')->latest('clocked_in_at')->first();

        $last = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNotNull('clocked_out_at')
            ->where('clocked_out_at', '>=', $today)
            ->latest('clocked_out_at')
            ->first();

        return [
            'shift' => $shift ? [
                'starts_at' => $shift->starts_at->toIso8601String(),
                'ends_at' => $shift->ends_at->toIso8601String(),
                'role' => $shift->role->value,
                'notes' => $shift->notes,
            ] : null,
            'clocked_in_at' => $open?->clocked_in_at->toIso8601String(),
            'last_clocked_out_at' => $last?->clocked_out_at?->toIso8601String(),
        ];
    }
}
