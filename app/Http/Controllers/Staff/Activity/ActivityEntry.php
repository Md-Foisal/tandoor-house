<?php

namespace App\Http\Controllers\Staff\Activity;

use App\Models\ActivityLog;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns log rows into the plain shapes the page and the CSV share.
 */
class ActivityEntry
{
    private const MONEY_KEYS = ['amount', 'total', 'refund'];

    /**
     * Looks up the staff page for every subject in one query per type.
     *
     * @param  iterable<ActivityLog>  $logs
     * @return Collection<string, array{label: string, href: string}>
     */
    public static function subjects(iterable $logs): Collection
    {
        $wanted = collect($logs)
            ->filter(fn (ActivityLog $log) => $log->subject_type !== null && $log->subject_id !== null)
            ->groupBy(fn (ActivityLog $log) => self::modelClass($log->subject_type))
            ->filter(fn ($group, string $class) => in_array($class, [Order::class, Reservation::class, MenuItem::class, Ingredient::class, Review::class], true));

        $links = collect();

        foreach ($wanted as $class => $group) {
            /** @var class-string<Model> $class */
            $class::query()->whereIn('id', $group->pluck('subject_id')->unique())->get()
                ->each(function (Model $model) use ($class, $links) {
                    $link = self::link($model);

                    if ($link !== null) {
                        $links->put($class.':'.$model->getKey(), $link);
                    }
                });
        }

        return $links;
    }

    /**
     * @return array{label: string, href: string}|null
     */
    public static function link(Model $model): ?array
    {
        return match (true) {
            $model instanceof Order => ['label' => __('staff_activity.subjects.order', ['number' => $model->number]), 'href' => '/staff/orders/'.$model->number],
            $model instanceof Reservation => ['label' => __('staff_activity.subjects.booking', ['reference' => $model->reference]), 'href' => '/staff/reservations?date='.$model->starts_at->format('Y-m-d')],
            $model instanceof MenuItem => ['label' => $model->name, 'href' => '/staff/menu/items/'.$model->id.'/edit'],
            $model instanceof Ingredient => ['label' => $model->name, 'href' => '/staff/inventory'],
            $model instanceof Review => ['label' => __('staff_activity.subjects.review'), 'href' => '/staff/reviews'],
            default => null,
        };
    }

    /**
     * @param  Collection<string, array{label: string, href: string}>  $subjects
     * @return array<string, mixed>
     */
    public static function row(ActivityLog $log, Collection $subjects): array
    {
        $subject = $log->subject_type !== null
            ? $subjects->get(self::modelClass($log->subject_type).':'.$log->subject_id)
            : null;

        return [
            'id' => $log->id,
            'event' => $log->event,
            'family' => ActivityQuery::family($log->event),
            'day' => $log->created_at->format('Y-m-d'),
            'time' => $log->created_at->format('H:i'),
            'actor' => $log->user === null ? null : ['id' => $log->user->id, 'name' => $log->user->name],
            'description' => $log->description,
            'subject' => $subject,
            'details' => self::details($log->properties ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return list<array{label: string, value: string}>
     */
    public static function details(array $properties): array
    {
        $rows = [];

        foreach ($properties as $key => $value) {
            $key = (string) $key;
            $money = str_ends_with($key, '_pence') || in_array($key, self::MONEY_KEYS, true);

            $rows[] = [
                'label' => Str::headline(Str::replaceEnd('_pence', '', $key)),
                'value' => $money && is_numeric($value) ? Money::format((int) $value) : self::scalar($value),
            ];
        }

        return $rows;
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => '–',
            is_bool($value) => $value ? __('staff_activity.yes') : __('staff_activity.no'),
            is_scalar($value) => (string) $value,
            is_array($value) && array_is_list($value) && collect($value)->every(fn ($item) => is_scalar($item)) => implode(', ', $value),
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }

    private static function modelClass(string $type): string
    {
        return Relation::getMorphedModel($type) ?? $type;
    }
}
