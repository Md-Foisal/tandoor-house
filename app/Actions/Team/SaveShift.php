<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveShift
{
    public const MAX_HOURS = 16;

    public function __construct(
        private readonly ShiftWarnings $warnings,
        private readonly ActivityRecorder $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{shift: Shift, outside_hours: bool}
     */
    public function __invoke(User $actor, array $data, ?Shift $shift = null): array
    {
        return DB::transaction(function () use ($actor, $data, $shift): array {
            // Locking the person serialises their rota edits, so two requests cannot both pass the clash check.
            $person = User::query()->whereKey((int) $data['user_id'])->lockForUpdate()->firstOrFail();

            if ($shift !== null) {
                $shift = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
                abort_unless(TeamRules::canRoster($actor, $shift->user()->firstOrFail()), 403);
            }

            return $this->save($actor, $person, $data, $shift);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{shift: Shift, outside_hours: bool}
     */
    private function save(User $actor, User $person, array $data, ?Shift $shift): array
    {
        if (! $person->role->isStaff()) {
            throw ValidationException::withMessages(['user_id' => __('staff_team.errors.not_staff')]);
        }

        abort_unless(TeamRules::canRoster($actor, $person), 403);

        [$start, $end] = self::window((string) $data['date'], (string) $data['start'], (string) $data['end']);

        if ($end->diffInMinutes($start, true) > self::MAX_HOURS * 60) {
            throw ValidationException::withMessages(['end' => __('staff_team.errors.too_long', ['hours' => self::MAX_HOURS])]);
        }

        $clash = self::clash($person->id, $start, $end, $shift?->id);

        if ($clash !== null) {
            throw ValidationException::withMessages(['start' => __('staff_team.errors.overlap', [
                'name' => $person->name,
                'from' => $clash->starts_at->format('H:i'),
                'to' => $clash->ends_at->format('H:i'),
                'day' => $clash->starts_at->format('D j M'),
            ])]);
        }

        $shift ??= new Shift;
        $isNew = ! $shift->exists;
        $shift->forceFill([
            'user_id' => $person->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'role' => Role::from((string) $data['role']),
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
        ])->save();

        $this->activity->record($isNew ? 'shift.created' : 'shift.updated', __($isNew ? 'staff_team.log.shift_created' : 'staff_team.log.shift_updated', [
            'user' => $actor->name,
            'name' => $person->name,
            'when' => $start->format('D j M H:i').'–'.$end->format('H:i'),
        ]), $shift, by: $actor);

        return ['shift' => $shift, 'outside_hours' => $this->warnings->outsideHours($start, $end)];
    }

    /**
     * An end at or before the start means the shift runs past midnight.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(string $date, string $start, string $end): array
    {
        $zone = config('app.timezone');
        $from = CarbonImmutable::parse($date.' '.$start, $zone);
        $to = CarbonImmutable::parse($date.' '.$end, $zone);

        return [$from, $to->lessThanOrEqualTo($from) ? $to->addDay() : $to];
    }

    /** Back-to-back shifts are fine; any real overlap is a clash. */
    public static function clash(int $userId, CarbonImmutable $start, CarbonImmutable $end, ?int $ignore = null): ?Shift
    {
        return Shift::query()
            ->where('user_id', $userId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore))
            ->orderBy('starts_at')
            ->first();
    }
}
