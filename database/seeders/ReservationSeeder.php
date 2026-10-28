<?php

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Dice;
use Database\Seeders\Support\EastLondon;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * About 125 bookings from thirty days back to three weeks ahead, assigned
 * to tables without ever double-booking one, then the floor's table
 * statuses to match what is happening now.
 */
class ReservationSeeder extends Seeder
{
    private const OCCASIONS = [['Birthday', 55], ['Anniversary', 30], ['Business dinner', 10], ['Engagement', 5]];

    private const NOTES = [
        'High chair please', 'One guest is allergic to nuts', 'Window table if possible', 'Coming with a pram',
        'Wheelchair user, step-free access please', 'Would love a quiet corner', 'Two vegans in the group',
        'Running a few minutes late, will ring',
    ];

    private CarbonImmutable $now;

    private int $duration;

    /** @var Collection<int, DiningTable> */
    private Collection $tables;

    /** @var Collection<int, User> */
    private Collection $customers;

    /** @var array<int, list<array{0: int, 1: int}>> */
    private array $busy = [];

    /** @var array<string, true> */
    private array $references = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    public function run(): void
    {
        $this->now = Trading::now();
        $this->duration = (int) config('restaurant.defaults.reservation.duration_minutes');
        $this->tables = DiningTable::all();
        $this->customers = User::where('role', Role::Customer)->get();

        DB::transaction(function () {
            $this->seatedFromLiveOrders();
            $this->bookDays();

            foreach (array_chunk($this->rows, 60) as $chunk) {
                DB::table('reservations')->insert($chunk);
            }

            $this->syncTableStatuses();
        });
    }

    /**
     * Some of the parties eating right now booked; the rest walked in.
     */
    private function seatedFromLiveOrders(): void
    {
        $orders = Order::query()
            ->with('user')
            ->where('channel', OrderChannel::DineIn)
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled])
            ->whereNotNull('dining_table_id')
            ->orderBy('placed_at')
            ->get();

        foreach ($orders as $index => $order) {
            $start = CarbonImmutable::parse($order->placed_at)->subMinutes(mt_rand(8, 15));
            $this->hold($order->dining_table_id ?? 0, $start, $start->addMinutes($this->duration));

            if ($index >= 3) {
                continue;
            }

            $table = $this->tables->firstWhere('id', $order->dining_table_id);
            $contact = $order->contact;

            $this->rows[] = $this->row(
                $start,
                min($table->seats ?? 2, mt_rand(2, 4)),
                ReservationStatus::Seated,
                $order->dining_table_id,
                $order->user,
                $contact['name'],
                $contact['email'],
                $contact['phone'],
            );
        }
    }

    private function bookDays(): void
    {
        for ($offset = -30; $offset <= 21; $offset++) {
            $day = $this->now->addDays($offset)->startOfDay();

            if (Trading::periods($day) === []) {
                continue;
            }

            $base = $offset === 0 ? 9 : match ($day->dayOfWeekIso) {
                5, 6 => 4.2,
                7 => 3.0,
                default => 2.0,
            };
            $count = max(1, (int) round($base * (0.7 + mt_rand(0, 60) / 100)));

            for ($i = 0; $i < $count; $i++) {
                $this->book($day);
            }
        }
    }

    private function book(CarbonImmutable $day): void
    {
        $party = Dice::weighted([[2, 38], [3, 8], [4, 30], [5, 7], [6, 8], [7, 4], [8, 3]]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $start = $this->slot($day);

            if ($start === null) {
                return;
            }

            $end = $start->addMinutes($this->duration);
            $status = $this->statusFor($start, $end, $party);
            $table = $status === ReservationStatus::Cancelled ? null : $this->findTable($party, $start, $end);

            if ($table === null && $status !== ReservationStatus::Cancelled) {
                continue;
            }

            if ($table !== null) {
                $this->hold($table->id, $start, $end);
            }

            $customer = Dice::chance(0.35) && $this->customers->isNotEmpty() ? $this->customers->random() : null;
            $name = $customer->name ?? EastLondon::person();

            $this->rows[] = $this->row(
                $start,
                $party,
                $status,
                $table?->id,
                $customer,
                $name,
                $customer->email ?? Str::slug($name, '.').'@'.Dice::pick(['gmail.com', 'outlook.com', 'icloud.com', 'btinternet.com']),
                $customer->phone ?? '07'.fake('en_GB')->numerify('### ######'),
            );

            return;
        }
    }

    private function slot(CarbonImmutable $day): ?CarbonImmutable
    {
        $slots = [];

        foreach (Trading::periods($day) as [$open, $close]) {
            for ($t = $open; $t <= $close->subMinutes($this->duration); $t = $t->addMinutes(15)) {
                $slots[] = [$t, Trading::demand($t)];
            }
        }

        return $slots === [] ? null : Dice::weighted($slots);
    }

    private function statusFor(CarbonImmutable $start, CarbonImmutable $end, int $party): ReservationStatus
    {
        if ($end < $this->now) {
            return Dice::weighted([[ReservationStatus::Completed, 82], [ReservationStatus::NoShow, 7], [ReservationStatus::Cancelled, 11]]);
        }

        if ($start > $this->now) {
            if (Dice::chance(0.06)) {
                return ReservationStatus::Cancelled;
            }

            return $party >= 6 && Dice::chance(0.35) ? ReservationStatus::PendingDeposit : ReservationStatus::Confirmed;
        }

        if ($this->now < $start->addMinutes(20)) {
            return ReservationStatus::Confirmed;
        }

        return Dice::chance(0.9) ? ReservationStatus::Seated : ReservationStatus::NoShow;
    }

    private function findTable(int $party, CarbonImmutable $start, CarbonImmutable $end): ?DiningTable
    {
        $candidates = $this->tables
            ->filter(fn (DiningTable $table) => $table->seats >= $party
                && ($table->area !== 'Tandoor counter' || $party <= 2)
                && ! $this->clashes($table->id, $start, $end))
            ->shuffle()
            ->sortBy(fn (DiningTable $table) => $table->seats - $party + ($table->area === 'Garden' ? 3 : 0));

        return $candidates->first();
    }

    private function clashes(int $tableId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($this->busy[$tableId] ?? [] as [$from, $to]) {
            if ($start->getTimestamp() < $to && $end->getTimestamp() > $from) {
                return true;
            }
        }

        return false;
    }

    private function hold(int $tableId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $this->busy[$tableId][] = [$start->getTimestamp(), $end->getTimestamp()];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CarbonImmutable $start, int $party, ReservationStatus $status, ?int $tableId, ?User $user, string $name, string $email, string $phone): array
    {
        do {
            $reference = 'R'.Str::upper(Str::random(6));
        } while (isset($this->references[$reference]));

        $this->references[$reference] = true;

        $perHead = (int) config('restaurant.defaults.reservation.deposit_per_head');
        $needsDeposit = $party >= (int) config('restaurant.defaults.reservation.deposit_from_party');
        $depositStatus = match (true) {
            ! $needsDeposit => null,
            $status === ReservationStatus::PendingDeposit => PaymentStatus::Pending,
            $status === ReservationStatus::Cancelled => PaymentStatus::Refunded,
            default => PaymentStatus::Paid,
        };

        $created = $status === ReservationStatus::PendingDeposit
            ? $this->now->subHours(mt_rand(1, 40))
            : min($this->now->subMinutes(30), $start->subDays(mt_rand(1, 14))->subHours(mt_rand(0, 12)));
        $reminded = in_array($status, [ReservationStatus::Confirmed, ReservationStatus::Seated, ReservationStatus::Completed, ReservationStatus::NoShow], true)
            && $start->subDay() <= $this->now && $created < $start->subDay()
                ? $start->subDay()->addMinutes(mt_rand(0, 30))
                : null;
        $seated = in_array($status, [ReservationStatus::Seated, ReservationStatus::Completed], true)
            ? $start->addMinutes(mt_rand(-5, 10))
            : null;

        return [
            'reference' => $reference,
            'user_id' => $user?->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'party_size' => $party,
            'starts_at' => Trading::stamp($start),
            'ends_at' => Trading::stamp($start->addMinutes($this->duration)),
            'dining_table_id' => $tableId,
            'status' => $status->value,
            'deposit' => $needsDeposit ? $party * $perHead : 0,
            'deposit_status' => $depositStatus?->value,
            'occasion' => Dice::chance(0.18) ? Dice::weighted(self::OCCASIONS) : null,
            'notes' => Dice::chance(0.15) ? Dice::pick(self::NOTES) : null,
            'reminded_at' => $reminded !== null ? Trading::stamp($reminded) : null,
            'seated_at' => $seated !== null ? Trading::stamp($seated) : null,
            'created_at' => Trading::stamp($created),
            'updated_at' => Trading::stamp($seated ?? $created),
        ];
    }

    private function syncTableStatuses(): void
    {
        $status = [];
        $soon = $this->now->addMinutes(60);

        $liveOrderTables = Order::query()
            ->where('channel', OrderChannel::DineIn)
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled])
            ->pluck('dining_table_id');

        foreach ($liveOrderTables as $id) {
            $status[$id] = TableStatus::Seated;
        }

        foreach ($this->rows as $row) {
            $start = CarbonImmutable::parse($row['starts_at'], config('app.timezone'));

            if ($row['dining_table_id'] === null) {
                continue;
            }

            if ($row['status'] === ReservationStatus::Seated->value) {
                $status[$row['dining_table_id']] = TableStatus::Seated;
            } elseif (in_array($row['status'], [ReservationStatus::Confirmed->value, ReservationStatus::PendingDeposit->value], true)
                && $start > $this->now && $start <= $soon) {
                $status[$row['dining_table_id']] ??= TableStatus::Reserved;
            }
        }

        $bills = DB::table('table_requests')->where('type', TableRequestType::Bill->value)->whereNull('handled_at')->pluck('dining_table_id');

        foreach ($bills as $id) {
            $status[$id] = TableStatus::BillRequested;
        }

        $spare = $this->tables->pluck('id')->reject(fn (int $id) => isset($status[$id]))->shuffle();

        foreach ($spare->take(2) as $id) {
            $status[$id] = TableStatus::NeedsCleaning;
        }

        foreach ($status as $id => $tableStatus) {
            DB::table('dining_tables')->where('id', $id)->update(['status' => $tableStatus->value]);
        }
    }
}
