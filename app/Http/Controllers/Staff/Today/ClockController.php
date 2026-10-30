<?php

namespace App\Http\Controllers\Staff\Today;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ClockController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function in(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $attendance = $this->locked($user, function () use ($user) {
            if ($this->open($user) !== null) {
                return null;
            }

            $today = now()->startOfDay();
            $shift = Shift::query()
                ->where('user_id', $user->id)
                ->whereBetween('starts_at', [$today, $today->copy()->endOfDay()])
                ->orderBy('starts_at')
                ->first();

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'shift_id' => $shift?->id,
                'clocked_in_at' => now(),
            ]);

            $this->log('clock_in', $attendance, $user, __('staff_today.clock.log_in', ['name' => $user->name]));

            return $attendance;
        });

        if ($attendance === null) {
            return back()->withErrors(['clock' => __('staff_today.clock.already_in')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_today.clock.in_done', ['time' => now()->format('H:i')])]);

        return back();
    }

    public function out(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        $attendance = $this->locked($user, function () use ($user) {
            $attendance = $this->open($user);

            if ($attendance === null) {
                return null;
            }

            $attendance->forceFill(['clocked_out_at' => now()])->save();
            $this->log('clock_out', $attendance, $user, __('staff_today.clock.log_out', ['name' => $user->name]));

            return $attendance;
        });

        if ($attendance === null) {
            return back()->withErrors(['clock' => __('staff_today.clock.not_in')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_today.clock.out_done', ['time' => now()->format('H:i')])]);

        return back();
    }

    private function user(Request $request): User
    {
        return $request->user() ?? abort(403);
    }

    /**
     * There may be no open attendance row to lock yet, so the person's own row
     * is the lock: two taps from one phone run one after the other.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function locked(User $user, callable $work): mixed
    {
        return DB::transaction(function () use ($user, $work) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $work();
        });
    }

    private function open(User $user): ?Attendance
    {
        return Attendance::query()->where('user_id', $user->id)->whereNull('clocked_out_at')->latest('clocked_in_at')->lockForUpdate()->first();
    }

    private function log(string $event, Attendance $attendance, User $user, string $description): void
    {
        $this->activity->record($event, $description, $attendance, ['shift_id' => $attendance->shift_id], $user);
    }
}
