<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Ingredient;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Dice;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rotas for a fortnight either side of today, who clocked in when, and the
 * automation and staff events the activity feed shows.
 */
class PeopleSeeder extends Seeder
{
    private CarbonImmutable $now;

    /** @var array<string, array<int, int>> */
    private array $pools = [];

    public function run(): void
    {
        $this->now = Trading::now();

        foreach ([Role::Kitchen, Role::Waiter, Role::Cashier, Role::Rider, Role::Manager] as $role) {
            $this->pools[$role->value] = User::where('role', $role)->orderBy('id')->get()->map(fn (User $user) => $user->id)->values()->all();
        }

        DB::transaction(function () {
            $this->rota();
            $this->activity();
        });
    }

    private function rota(): void
    {
        $shifts = [];

        foreach (range(-14, 14) as $index => $offset) {
            $day = $this->now->addDays($offset)->startOfDay();
            $periods = Trading::periods($day);

            if ($periods === []) {
                continue;
            }

            $open = $periods[0][0];
            $close = $periods[array_key_last($periods)][1];
            $split = count($periods) > 1;
            $middle = $split ? $periods[0][1] : $open->addHours(5);
            $earlyEnd = $middle->addMinutes(30);
            $lateStart = $split ? $periods[1][0]->subMinutes(60) : $middle->subMinutes(30);
            $busy = in_array($day->dayOfWeekIso, [5, 6], true);

            $kitchenEarly = $this->rotate(Role::Kitchen, $index, 1);
            $waiterEarly = $this->rotate(Role::Waiter, $index, $busy ? 2 : 1);
            $riderEarly = $this->rotate(Role::Rider, $index, 1);
            $plan = [
                [Role::Kitchen, $kitchenEarly, $open->subMinutes(90), $earlyEnd],
                [Role::Kitchen, $this->rotate(Role::Kitchen, $index + 1, 2, $kitchenEarly), $lateStart->subMinutes(30), $close->addMinutes(30)],
                [Role::Waiter, $waiterEarly, $open->subMinutes(30), $earlyEnd],
                [Role::Waiter, $this->rotate(Role::Waiter, $index + 2, $busy ? 3 : 2, $waiterEarly), $lateStart, $close->addMinutes(30)],
                [Role::Rider, $riderEarly, $open, $earlyEnd],
                [Role::Rider, $this->rotate(Role::Rider, $index + 1, 2, $riderEarly), $lateStart->addMinutes(30), $close],
            ];

            if (in_array($day->dayOfWeekIso, [2, 3, 7], true)) {
                $plan[] = [Role::Cashier, $this->pools[Role::Cashier->value], $open->subMinutes(15), $earlyEnd];
            } else {
                $plan[] = [Role::Cashier, $this->pools[Role::Cashier->value], $lateStart, $close->addMinutes(15)];
            }

            $plan[] = in_array($day->dayOfWeekIso, [2, 3], true)
                ? [Role::Manager, $this->pools[Role::Manager->value], $open->subMinutes(60), $earlyEnd]
                : [Role::Manager, $this->pools[Role::Manager->value], $lateStart, $close->addMinutes(45)];

            foreach ($plan as [$role, $people, $from, $to]) {
                foreach ($people as $userId) {
                    $shifts[] = ['user_id' => $userId, 'starts_at' => $from, 'ends_at' => $to, 'role' => $role->value];
                }
            }
        }

        $id = (int) DB::table('shifts')->max('id');
        $shiftRows = [];
        $attendances = [];

        foreach ($shifts as $shift) {
            $id++;
            $shiftRows[] = [
                'id' => $id,
                'user_id' => $shift['user_id'],
                'starts_at' => Trading::stamp($shift['starts_at']),
                'ends_at' => Trading::stamp($shift['ends_at']),
                'role' => $shift['role'],
                'notes' => null,
                'created_at' => Trading::stamp($this->now->subDays(15)),
                'updated_at' => Trading::stamp($this->now->subDays(15)),
            ];

            if ($shift['starts_at'] > $this->now) {
                continue;
            }

            $late = Dice::chance(0.08);
            $in = $shift['starts_at']->addMinutes($late ? mt_rand(8, 28) : mt_rand(-12, 3));
            $out = $shift['ends_at']->addMinutes(mt_rand(0, 25));

            $attendances[] = [
                'user_id' => $shift['user_id'],
                'shift_id' => $id,
                'clocked_in_at' => Trading::stamp($in),
                'clocked_out_at' => $out < $this->now ? Trading::stamp($out) : null,
                'created_at' => Trading::stamp($in),
                'updated_at' => Trading::stamp($out < $this->now ? $out : $in),
                'late' => $late ? (int) $shift['starts_at']->diffInMinutes($in) : 0,
            ];
        }

        foreach (array_chunk($shiftRows, 100) as $chunk) {
            DB::table('shifts')->insert($chunk);
        }

        $lateIds = [];

        foreach ($attendances as &$attendance) {
            $late = $attendance['late'];
            unset($attendance['late']);

            if ($late > 0) {
                $lateIds[] = [$attendance['user_id'], $late, $attendance['clocked_in_at']];
            }
        }

        unset($attendance);

        foreach (array_chunk($attendances, 100) as $chunk) {
            DB::table('attendances')->insert($chunk);
        }

        $this->lateClockIns = $lateIds;
    }

    /** @var list<array{0: int, 1: int, 2: string}> */
    private array $lateClockIns = [];

    /**
     * @param  list<int>  $exclude
     * @return list<int>
     */
    private function rotate(Role $role, int $index, int $count, array $exclude = []): array
    {
        $pool = $this->pools[$role->value];
        $picked = [];

        for ($i = 0; $i < count($pool) && count($picked) < $count; $i++) {
            $id = $pool[($index + $i) % count($pool)];

            if (! in_array($id, $exclude, true)) {
                $picked[] = $id;
            }
        }

        return $picked;
    }

    private function activity(): void
    {
        $manager = $this->pools[Role::Manager->value][0];
        $log = [];
        $add = function (string $event, string $description, CarbonImmutable $at, ?string $subject = null, ?int $subjectId = null, ?int $userId = null) use (&$log) {
            if ($at > $this->now) {
                return;
            }

            $log[] = [
                'event' => $event,
                'subject_type' => $subject,
                'subject_id' => $subjectId,
                'user_id' => $userId,
                'description' => $description,
                'properties' => null,
                'created_at' => Trading::stamp($at),
                'at' => $at->getTimestamp(),
            ];
        };

        $money = fn (int $pence): string => '£'.number_format($pence / 100, 2);

        $closed = 0;

        for ($offset = 1; $offset <= 14 && $closed < 8; $offset++) {
            $day = $this->now->subDays($offset)->startOfDay();
            $periods = Trading::periods($day);

            if ($periods === []) {
                continue;
            }

            $closed++;
            $orders = Order::query()->where('status', OrderStatus::Completed)->whereBetween('placed_at', [$day, $day->endOfDay()]);
            $add('report.daily_close', sprintf('Daily close for %s: %d orders completed, %s taken', $day->format('D j M'), $orders->count(), $money((int) $orders->sum('total'))), $periods[array_key_last($periods)][1]->addMinutes(20));
        }

        foreach ([1, 2] as $weeksAgo) {
            $monday = $this->now->startOfWeek()->subWeeks($weeksAgo - 1)->setTime(7, 0);
            $add('report.weekly_sent', sprintf('Weekly report for %s to %s emailed to owner@tandoorhouse.test', $monday->subWeek()->format('j M'), $monday->subDay()->format('j M')), $monday);
        }

        foreach (Order::query()->where('status', OrderStatus::Completed)->whereNotNull('accepted_at')->latest('placed_at')->limit(12)->get() as $order) {
            $add('order.auto_accepted', "Order {$order->number} accepted automatically", CarbonImmutable::parse($order->accepted_at));
        }

        foreach (Ingredient::query()->whereColumn('stock', '<', 'low_threshold')->get() as $ingredient) {
            $add('stock.low', "{$ingredient->name} is down to {$ingredient->stock} {$ingredient->unit}, below its low threshold of {$ingredient->low_threshold} {$ingredient->unit}", $this->now->subMinutes(mt_rand(5, 300)), Ingredient::class, $ingredient->id);
        }

        foreach (Reservation::query()->whereNotNull('reminded_at')->latest('reminded_at')->limit(8)->get() as $reservation) {
            $startsAt = CarbonImmutable::parse($reservation->starts_at);
            $add('reservation.reminder_sent', "Reminder sent to {$reservation->name} for {$startsAt->format('D j M \a\t H:i')}, party of {$reservation->party_size}", CarbonImmutable::parse($reservation->reminded_at), Reservation::class, $reservation->id);
        }

        foreach (Reservation::query()->where('status', ReservationStatus::NoShow)->whereNotNull('dining_table_id')->with('diningTable')->latest('starts_at')->limit(3)->get() as $reservation) {
            $add('reservation.no_show_released', "Table {$reservation->diningTable?->label} released after 20 minutes: {$reservation->name} did not arrive", CarbonImmutable::parse($reservation->starts_at)->addMinutes(20), Reservation::class, $reservation->id);
        }

        foreach (Review::query()->whereNotNull('order_id')->with('order')->latest()->limit(8)->get() as $review) {
            $order = $review->order;

            if ($order !== null) {
                $add('review.request_sent', "Review request sent for order {$order->number}", CarbonImmutable::parse($order->completed_at ?? $order->placed_at)->addHours(2), Order::class, $order->id);
            }
        }

        foreach (LoyaltyTransaction::query()->with('user')->latest('created_at')->limit(6)->get() as $transaction) {
            $add('loyalty.points_awarded', "Awarded {$transaction->points} points to {$transaction->user?->name} ({$transaction->reason})", CarbonImmutable::parse($transaction->created_at), LoyaltyTransaction::class, $transaction->id);
        }

        foreach (User::query()->where('role', Role::Customer)->inRandomOrder()->limit(3)->get() as $customer) {
            $add('cart.reminder_sent', "Basket reminder emailed to {$customer->name}", $this->now->subHours(mt_rand(5, 120)), User::class, $customer->id);
        }

        foreach (Order::query()->whereIn('payment_status', [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded])->where('status', OrderStatus::Completed)->limit(4)->get() as $order) {
            $add('order.refunded', "Refund issued on order {$order->number}: item not as described", CarbonImmutable::parse($order->completed_at ?? $order->placed_at)->addHours(3), Order::class, $order->id, $manager);
        }

        foreach (Order::query()->where('status', OrderStatus::Rejected)->limit(3)->get() as $order) {
            $add('order.rejected', "Order {$order->number} rejected: {$order->rejection_reason}", CarbonImmutable::parse($order->placed_at)->addMinutes(4), Order::class, $order->id, $manager);
        }

        foreach (array_slice($this->lateClockIns, -4) as [$userId, $minutes, $when]) {
            $name = User::find($userId)?->name;
            $add('staff.late_clock_in', "{$name} clocked in {$minutes} minutes late", CarbonImmutable::parse($when, config('app.timezone')), User::class, $userId);
        }

        usort($log, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        $rows = array_map(function (array $entry) {
            unset($entry['at']);

            return $entry;
        }, $log);

        DB::table('activity_logs')->insert($rows);
    }
}
