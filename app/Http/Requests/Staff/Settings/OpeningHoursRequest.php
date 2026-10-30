<?php

namespace App\Http\Requests\Staff\Settings;

use Illuminate\Validation\Validator;

class OpeningHoursRequest extends SettingsRequest
{
    public const MAX_PERIODS = 3;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.closed' => ['required', 'boolean'],
            'days.*.periods' => ['present', 'array', 'max:'.self::MAX_PERIODS],
            'days.*.periods.*.open' => ['required', 'date_format:H:i'],
            'days.*.periods.*.close' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('days', []) as $i => $day) {
                if (! is_array($day) || ($day['closed'] ?? false)) {
                    continue;
                }

                $periods = (array) ($day['periods'] ?? []);

                if ($periods === []) {
                    $validator->errors()->add("days.{$i}.periods", __('staff_settings.hours.errors.need_period'));

                    continue;
                }

                $previousEnd = null;

                foreach ($this->sorted($periods) as $index => [$start, $end]) {
                    if ($end <= $start) {
                        $validator->errors()->add("days.{$i}.periods.{$index}.close", __('staff_settings.hours.errors.close_after_open'));
                    } elseif ($previousEnd !== null && $start <= $previousEnd) {
                        $validator->errors()->add("days.{$i}.periods.{$index}.open", __('staff_settings.hours.errors.overlap'));
                    }

                    $previousEnd = max($previousEnd ?? 0, $end);
                }
            }
        }];
    }

    /**
     * Saved shape: ISO weekday => list of ["HH:MM", "HH:MM"], close "24:00" for midnight.
     *
     * @return array<int, list<array{0: string, 1: string}>>
     */
    public function week(): array
    {
        $week = [];

        foreach ((array) $this->validated('days') as $day) {
            $periods = $day['closed'] ? [] : array_map(
                fn (array $p) => [$p['open'], $p['close'] === '00:00' ? '24:00' : $p['close']],
                $this->ordered((array) $day['periods']),
            );
            $week[(int) $day['day']] = $periods;
        }

        ksort($week);

        return $week;
    }

    /**
     * @param  array<int, array<string, string>>  $periods
     * @return array<int, array{0: int, 1: int}> minutes since midnight, keyed by original index, ordered by start
     */
    private function sorted(array $periods): array
    {
        $minutes = [];

        foreach ($periods as $index => $period) {
            $minutes[$index] = [self::minutes((string) ($period['open'] ?? '')), self::minutes((string) ($period['close'] ?? ''), true)];
        }

        uasort($minutes, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $minutes;
    }

    /**
     * @param  array<int, array<string, string>>  $periods
     * @return list<array<string, string>>
     */
    private function ordered(array $periods): array
    {
        $order = array_keys($this->sorted($periods));

        return array_map(fn (int $index) => $periods[$index], $order);
    }

    private static function minutes(string $time, bool $closing = false): int
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $time, $m) !== 1) {
            return 0;
        }

        $value = (int) $m[1] * 60 + (int) $m[2];

        return $closing && $value === 0 ? 24 * 60 : $value;
    }
}
