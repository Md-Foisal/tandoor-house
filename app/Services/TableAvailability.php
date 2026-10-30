<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Support\DepositHold;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which times can be booked, and which table a party would get.
 *
 * A party takes one table that seats it with at most two spare chairs,
 * so a couple never sits at a table for six.
 */
class TableAvailability
{
    public const LEAD_MINUTES = 30;

    public const HORIZON_DAYS = 60;

    private const SPARE_SEATS = 2;

    public function __construct(
        private readonly OpeningHours $hours,
        private readonly Settings $settings,
        private readonly DepositHold $hold,
    ) {}

    public function maxParty(): int
    {
        return (int) $this->settings->get('reservation.max_party', 10);
    }

    public function durationMinutes(): int
    {
        return (int) $this->settings->get('reservation.duration_minutes', 105);
    }

    public function depositFor(int $party): int
    {
        $from = (int) $this->settings->get('reservation.deposit_from_party', 6);

        return $party >= $from ? $party * (int) $this->settings->get('reservation.deposit_per_head', 1000) : 0;
    }

    public function parseDate(string $value): ?CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));

        return $date?->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * The next sixty days, each open, closed, or too late to book today.
     *
     * @return list<array{date: string, status: 'open'|'closed'|'late'}>
     */
    public function days(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $days = [];

        for ($offset = 0; $offset < self::HORIZON_DAYS; $offset++) {
            $date = $today->addDays($offset);
            $status = match (true) {
                $this->slotTimes($date, $now) !== [] => 'open',
                $this->hours->periodsOn($date) === [] => 'closed',
                default => 'late',
            };

            $days[] = ['date' => $date->toDateString(), 'status' => $status];
        }

        return $days;
    }

    /**
     * Every start time on a date: a slot each slot_minutes inside an opening
     * period, the last one early enough for the meal to end by closing.
     *
     * @return list<CarbonImmutable>
     */
    public function slotTimes(CarbonImmutable $date, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $step = max(5, (int) $this->settings->get('reservation.slot_minutes', 15));
        $duration = $this->durationMinutes();
        $earliest = $now->addMinutes(self::LEAD_MINUTES);
        $times = [];

        foreach ($this->hours->periodsOn($date) as [$opens, $closes]) {
            for ($time = $opens; $time->addMinutes($duration)->lessThanOrEqualTo($closes); $time = $time->addMinutes($step)) {
                if ($time->greaterThanOrEqualTo($earliest)) {
                    $times[] = $time;
                }
            }
        }

        return $times;
    }

    public function isBookableTime(CarbonImmutable $start): bool
    {
        $last = CarbonImmutable::now(config('app.timezone'))->startOfDay()->addDays(self::HORIZON_DAYS - 1);

        if ($start->startOfDay()->greaterThan($last)) {
            return false;
        }

        foreach ($this->slotTimes($start->startOfDay()) as $time) {
            if ($time->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{time: string, available: bool}>
     */
    public function slots(CarbonImmutable $date, int $party): array
    {
        $times = $this->slotTimes($date);

        if ($times === [] || $party < 1 || $party > $this->maxParty()) {
            return [];
        }

        $tables = $this->candidates($party);
        $duration = $this->durationMinutes();
        $reservations = $this->overlapping($tables, $date->startOfDay(), $date->endOfDay()->addMinutes($duration));

        return array_map(fn (CarbonImmutable $start) => [
            'time' => $start->format('H:i'),
            'available' => $this->pick($tables, $reservations, $start, $start->addMinutes($duration)) !== null,
        ], $times);
    }

    /**
     * The best-fitting free table for a party, smallest first. With $lock the
     * rows are locked so the caller can create the booking in the same
     * transaction without another request taking the table first.
     */
    public function findTable(CarbonImmutable $start, int $party, bool $lock = false): ?DiningTable
    {
        $end = $start->addMinutes($this->durationMinutes());
        $tables = $this->candidates($party, $lock);

        return $this->pick($tables, $this->overlapping($tables, $start, $end, $lock), $start, $end);
    }

    /**
     * @return Collection<int, DiningTable>
     */
    private function candidates(int $party, bool $lock = false): Collection
    {
        return DiningTable::query()
            ->whereNull('merged_into_id')
            ->whereBetween('seats', [$party, $party + self::SPARE_SEATS])
            ->orderBy('seats')
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * @param  Collection<int, DiningTable>  $tables
     * @return Collection<int, Reservation>
     */
    private function overlapping(Collection $tables, CarbonImmutable $from, CarbonImmutable $to, bool $lock = false): Collection
    {
        return Reservation::query()
            ->whereIn('dining_table_id', $tables->modelKeys())
            ->where(fn ($query) => $query
                ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated])
                // An unpaid deposit only holds the table while its hold lasts.
                ->orWhere(fn ($pending) => $pending
                    ->where('status', ReservationStatus::PendingDeposit)
                    ->where(fn ($held) => $held
                        ->where('created_at', '>=', $this->hold->cutoff())
                        ->orWhere('deposit_status', PaymentStatus::Paid))))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * @param  Collection<int, DiningTable>  $tables
     * @param  Collection<int, Reservation>  $reservations
     */
    private function pick(Collection $tables, Collection $reservations, CarbonImmutable $start, CarbonImmutable $end): ?DiningTable
    {
        return $tables->first(fn (DiningTable $table) => ! $reservations->contains(
            fn (Reservation $booked) => $booked->dining_table_id === $table->id
                && $booked->starts_at->lessThan($end)
                && $booked->ends_at->greaterThan($start),
        ));
    }
}
