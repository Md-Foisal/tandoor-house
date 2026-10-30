<?php

namespace App\Http\Controllers\Staff\Activity;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class ActivityQuery
{
    public const AUTOMATION = 'automation';

    /**
     * @param  array{family: string|null, person: string|null, from: string|null, to: string|null, q: string|null}  $filters
     * @return Builder<ActivityLog>
     */
    public static function build(array $filters): Builder
    {
        $tz = config('app.timezone');

        return ActivityLog::query()
            ->when($filters['family'], fn (Builder $query, string $family) => $query->where(
                fn (Builder $inner) => $inner->where('event', $family)
                    ->orWhere(fn (Builder $range) => $range->where('event', '>=', $family.'.')->where('event', '<', $family.'/')),
            ))
            ->when($filters['person'], fn (Builder $query, string $person) => $person === self::AUTOMATION
                ? $query->whereNull('user_id')
                : $query->where('user_id', (int) $person))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from, $tz)->startOfDay()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to, $tz)->endOfDay()))
            ->when($filters['q'], fn (Builder $query, string $q) => $query->whereRaw(
                "description like ? escape '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%'],
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public static function family(string $event): string
    {
        return Str::before($event, '.');
    }

    public static function familyLabel(string $family): string
    {
        $key = "staff_activity.families.{$family}";

        return Lang::has($key) ? __($key) : Str::headline($family);
    }

    /**
     * Families present in the log, so new automation events appear without code changes.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function families(): array
    {
        return array_values(ActivityLog::query()->distinct()->pluck('event')
            ->map(fn (string $event) => self::family($event))
            ->unique()->sort()->values()
            ->map(fn (string $family) => ['value' => $family, 'label' => self::familyLabel($family)])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function people(): array
    {
        $people = User::query()
            ->whereIn('id', ActivityLog::query()->whereNotNull('user_id')->select('user_id'))
            ->orWhereIn('role', collect(Role::cases())->filter(fn (Role $role) => $role->isStaff())->map(fn (Role $role) => $role->value)->all())
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();

        return [['value' => self::AUTOMATION, 'label' => __('staff_activity.automation')], ...$people];
    }
}
