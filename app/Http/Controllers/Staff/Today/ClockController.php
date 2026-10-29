<?php

namespace App\Http\Controllers\Staff\Today;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClockController extends Controller
{
    public function in(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($this->open($user) !== null) {
            return back()->withErrors(['clock' => __('staff_today.clock.already_in')]);
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
        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_today.clock.in_done', ['time' => now()->format('H:i')])]);

        return back();
    }

    public function out(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $attendance = $this->open($user);

        if ($attendance === null) {
            return back()->withErrors(['clock' => __('staff_today.clock.not_in')]);
        }

        $attendance->forceFill(['clocked_out_at' => now()])->save();

        $this->log('clock_out', $attendance, $user, __('staff_today.clock.log_out', ['name' => $user->name]));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('staff_today.clock.out_done', ['time' => now()->format('H:i')])]);

        return back();
    }

    private function user(Request $request): User
    {
        return $request->user() ?? abort(403);
    }

    private function open(User $user): ?Attendance
    {
        return Attendance::query()->where('user_id', $user->id)->whereNull('clocked_out_at')->latest('clocked_in_at')->first();
    }

    private function log(string $event, Attendance $attendance, User $user, string $description): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $attendance->getMorphClass(),
            'subject_id' => $attendance->id,
            'user_id' => $user->id,
            'description' => $description,
            'properties' => ['shift_id' => $attendance->shift_id],
        ]);
    }
}
