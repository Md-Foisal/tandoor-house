<?php

namespace App\Http\Controllers\Staff\Reservations;

use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Services\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the props for the reservations calendar: one day as a timeline, or a
 * week of totals. Everything is loaded in a handful of queries.
 */
class BoardData
{
    /** Bookings that hold a table. */
    public const HOLDING = [ReservationStatus::PendingDeposit, ReservationStatus::Confirmed, ReservationStatus::Seated];

    /** Bookings that count towards covers. */
    public const COUNTED = [ReservationStatus::PendingDeposit, ReservationStatus::Confirmed, ReservationStatus::Seated, ReservationStatus::Completed];

    /** Lunch ends and dinner starts here. */
    private const SPLIT = '16:00';

    public function __construct(private readonly OpeningHours $hours) {}

    /**
     * @return Collection<int, DiningTable>
     */
    public function tables(): Collection
    {
        return DiningTable::query()->whereNull('merged_into_id')->orderBy('area')->orderBy('label')->get();
    }

    /**
     * @return list<array{id: int, label: string, seats: int, area: string}>
     */
    public function tableRows(): array
    {
        return array_values($this->tables()->map(fn (DiningTable $table) => [
            'id' => $table->id,
            'label' => $table->label,
            'seats' => $table->seats,
            'area' => $table->area,
        ])->all());
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    public function periods(CarbonImmutable $date): array
    {
        return array_map(fn (array $period) => [
            'start' => $period[0]->format('H:i'),
            'end' => $period[1]->format('H:i'),
        ], $this->hours->periodsOn($date));
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function reservationsOn(CarbonImmutable $date): Collection
    {
        $start = $date->startOfDay();

        return Reservation::query()
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<', $start->addDay())
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array<string, mixed>>
     */
    public function bookings(CarbonImmutable $date, Collection $reservations): array
    {
        $tables = $this->tables();
        $labels = $tables->pluck('label', 'id');
        $holding = $reservations->filter(fn (Reservation $r) => in_array($r->status, self::HOLDING, true));
        $visits = $this->previousVisits($date, $reservations);
        $logs = $this->logs($reservations);

        $rows = $reservations->map(function (Reservation $r) use ($tables, $labels, $holding, $visits, $logs) {
            $start = $this->local($r->starts_at);
            $end = $this->local($r->ends_at);
            $active = in_array($r->status, self::HOLDING, true);

            return [
                'id' => $r->id,
                'reference' => $r->reference,
                'name' => $r->name,
                'party_size' => $r->party_size,
                'phone' => $r->phone,
                'email' => $r->email === '' ? null : $r->email,
                'starts_at' => $start->toIso8601String(),
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'start_minute' => $start->hour * 60 + $start->minute,
                'end_minute' => $end->hour * 60 + $end->minute + ($end->isSameDay($start) ? 0 : 1440),
                'dining_table_id' => $r->dining_table_id,
                'table_label' => $r->dining_table_id === null ? null : $labels->get($r->dining_table_id),
                'status' => $r->status->value,
                'deposit' => $r->deposit,
                'deposit_status' => $r->deposit_status?->value,
                'occasion' => $r->occasion,
                'notes' => $r->notes,
                'seated_at' => $r->seated_at === null ? null : $this->local($r->seated_at)->format('H:i'),
                'previous_visits' => $r->email === '' ? 0 : ($visits[strtolower($r->email)] ?? 0),
                'free_table_ids' => $active ? $this->freeTables($r, $tables, $holding) : [],
                'log' => $logs->get($r->id, []),
            ];
        })->all();

        return array_values($rows);
    }

    /**
     * Covers and bookings per service, with how many tables are still free to
     * seat walk-ins during it.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array<string, mixed>>
     */
    public function services(CarbonImmutable $date, Collection $reservations, CarbonImmutable $now): array
    {
        $periods = $this->hours->periodsOn($date);
        $split = $date->setTimeFromTimeString(self::SPLIT);
        $tables = $this->tables();
        $counted = $reservations->filter(fn (Reservation $r) => in_array($r->status, self::COUNTED, true));
        $holding = $reservations->filter(fn (Reservation $r) => in_array($r->status, self::HOLDING, true));

        $lunch = array_values(array_filter($periods, fn (array $p) => $p[0]->lessThan($split)));
        $dinner = array_values(array_filter($periods, fn (array $p) => $p[1]->greaterThan($split)));

        $windows = [];
        if ($lunch !== []) {
            $windows['lunch'] = [$lunch[0][0], min($split, $lunch[array_key_last($lunch)][1])];
        }
        if ($dinner !== []) {
            $windows['dinner'] = [max($split, $dinner[0][0]), $dinner[array_key_last($dinner)][1]];
        }

        $rows = [];
        foreach ($windows as $key => [$from, $to]) {
            $inService = $counted->filter(fn (Reservation $r) => $this->local($r->starts_at)->lessThan($split) === ($key === 'lunch'));
            $walkIn = null;

            if ($to->greaterThan($now)) {
                $from = $from->max($now);
                $free = $tables->filter(fn (DiningTable $table) => ! $holding->contains(
                    fn (Reservation $r) => $r->dining_table_id === $table->id
                        && $r->starts_at->lessThan($to)
                        && $r->ends_at->greaterThan($from),
                ));
                $walkIn = ['tables' => $free->count(), 'seats' => (int) $free->sum('seats')];
            }

            $rows[] = [
                'key' => $key,
                'from' => $from->format('H:i'),
                'to' => $to->format('H:i'),
                'bookings' => $inService->count(),
                'covers' => (int) $inService->sum('party_size'),
                'walk_in' => $walkIn,
            ];
        }

        return $rows;
    }

    /**
     * The seven days (Monday to Sunday) around a date.
     *
     * @return list<array<string, mixed>>
     */
    public function week(CarbonImmutable $date, CarbonImmutable $now): array
    {
        $first = $date->startOfWeek();
        $rows = Reservation::query()
            ->where('starts_at', '>=', $first)
            ->where('starts_at', '<', $first->addDays(7))
            ->whereIn('status', self::COUNTED)
            ->get(['starts_at', 'party_size']);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $first->addDays($i);
            $onDay = $rows->filter(fn (Reservation $r) => $this->local($r->starts_at)->isSameDay($day));

            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->translatedFormat('l'),
                'day' => $day->day,
                'month' => $day->translatedFormat('M'),
                'closed' => $this->hours->periodsOn($day) === [],
                'is_today' => $day->isSameDay($now),
                'bookings' => $onDay->count(),
                'covers' => (int) $onDay->sum('party_size'),
            ];
        }

        return $days;
    }

    /**
     * @param  Collection<int, DiningTable>  $tables
     * @param  Collection<int, Reservation>  $holding
     * @return list<int>
     */
    private function freeTables(Reservation $booking, Collection $tables, Collection $holding): array
    {
        $free = $tables
            ->filter(fn (DiningTable $table) => $table->seats >= $booking->party_size
                && ! $holding->contains(fn (Reservation $other) => $other->id !== $booking->id
                    && $other->dining_table_id === $table->id
                    && $other->starts_at->lessThan($booking->ends_at)
                    && $other->ends_at->greaterThan($booking->starts_at)))
            ->pluck('id')
            ->all();

        return array_values($free);
    }

    /**
     * Completed visits before this day, by email.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return array<string, int>
     */
    private function previousVisits(CarbonImmutable $date, Collection $reservations): array
    {
        $emails = $reservations->pluck('email')->filter()->map(fn (string $e) => strtolower($e))->unique()->values()->all();

        if ($emails === []) {
            return [];
        }

        return Reservation::query()
            ->selectRaw('lower(email) as address, count(*) as visits')
            ->whereIn('email', $emails)
            ->where('status', ReservationStatus::Completed)
            ->where('starts_at', '<', $date->startOfDay())
            ->groupBy('address')
            ->pluck('visits', 'address')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return Collection<int|string, array<int, array{description: string, at: string, by: string|null}>>
     */
    private function logs(Collection $reservations): Collection
    {
        if ($reservations->isEmpty()) {
            return collect();
        }

        return ActivityLog::query()
            ->with('user:id,name')
            ->where('subject_type', (new Reservation)->getMorphClass())
            ->whereIn('subject_id', $reservations->pluck('id')->all())
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $entries) => $entries->map(fn (ActivityLog $entry) => [
                'description' => $entry->description,
                'at' => $this->local($entry->created_at)->format('H:i'),
                'by' => $entry->user?->name,
            ])->values()->all());
    }

    private function local(\DateTimeInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(config('app.timezone'));
    }
}
