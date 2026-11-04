<?php

namespace Database\Seeders;

use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\TableRequestType;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Pricing;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Catalogue;
use Database\Seeders\Support\Dice;
use Database\Seeders\Support\EastLondon;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sixty days of trading, the demo customer's three months of orders, and
 * today's live queue. Rows are built in memory and bulk inserted so the
 * whole seed stays quick.
 *
 * @phpstan-import-type Line from Catalogue
 */
class OrderHistorySeeder extends Seeder
{
    private const ORDER_NOTES = [
        OrderChannel::Delivery->value => ['Ring the bell twice, it is the black door', 'Please leave with the concierge', 'Call when outside, our bell is broken', 'No cutlery please'],
        OrderChannel::Pickup->value => ['Running about ten minutes late, sorry', 'Please pack the raita separately'],
        OrderChannel::DineIn->value => ['One of us is vegetarian, so no shared spoons please', 'Celebrating a birthday, no fuss please'],
    ];

    private const REJECTIONS = [
        'Kitchen is at capacity, please try again in 30 minutes.',
        'Sorry, we ran out of that dish and could not substitute it.',
        'We could not reach your address with the rider available.',
    ];

    private CarbonImmutable $now;

    private Catalogue $catalogue;

    private Pricing $pricing;

    /** @var Collection<int, User> */
    private Collection $customers;

    private User $demo;

    /** @var array<string, array<int, int>> */
    private array $staff = [];

    /** @var Collection<int, DeliveryZone> */
    private Collection $zones;

    /** @var Collection<int, DiningTable> */
    private Collection $tables;

    /** @var Collection<int, PromoCode> */
    private Collection $promos;

    /** @var list<int> */
    private array $liveTables = [];

    /** @var array<string, int> */
    private array $tableByRole = [];

    /** @var list<array<string, mixed>> */
    private array $bundles = [];

    public function run(): void
    {
        $this->now = Trading::now();
        $this->catalogue = new Catalogue;
        $this->pricing = app(Pricing::class);
        $this->demo = User::with('addresses')->where('email', 'customer@tandoorhouse.test')->firstOrFail();
        $this->customers = User::with('addresses')->where('role', Role::Customer)->whereKeyNot($this->demo->id)->get();
        $this->zones = DeliveryZone::all();
        $this->tables = DiningTable::all();
        $this->promos = PromoCode::all();

        foreach ([Role::Kitchen, Role::Waiter, Role::Cashier, Role::Rider, Role::Manager] as $role) {
            $this->staff[$role->value] = User::where('role', $role)->orderBy('id')->get()->map(fn (User $user) => $user->id)->values()->all();
        }

        DB::transaction(function () {
            $this->planHistory();
            $this->planDemoCustomer();
            $this->planLive();
            $this->persist();
        });
    }

    private function planHistory(): void
    {
        $weekday = [2 => 1.0, 3 => 1.0, 4 => 1.1, 5 => 1.8, 6 => 2.2, 7 => 1.4];

        for ($daysAgo = 60; $daysAgo >= 0; $daysAgo--) {
            $day = $this->now->subDays($daysAgo)->startOfDay();

            if (Trading::periods($day) === []) {
                continue;
            }

            $count = max(1, (int) round(($weekday[$day->dayOfWeekIso] ?? 1.0) * 4.6 * (0.8 + mt_rand(0, 40) / 100)));

            for ($i = 0; $i < $count; $i++) {
                $placed = Trading::pickTime($day);

                if ($placed === null || $placed > $this->now->subMinutes(50)) {
                    continue;
                }

                [$final, $refund] = Dice::weighted([
                    [[OrderStatus::Completed, false], 93],
                    [[OrderStatus::Completed, true], 1.8],
                    [[OrderStatus::Rejected, false], 2.2],
                    [[OrderStatus::Cancelled, false], 3],
                ]);

                $this->compose($this->channel(), $placed, $this->customer(), $final, $refund);
            }
        }
    }

    private function planDemoCustomer(): void
    {
        $channels = [
            OrderChannel::Delivery, OrderChannel::Delivery, OrderChannel::DineIn, OrderChannel::Delivery,
            OrderChannel::Pickup, OrderChannel::DineIn, OrderChannel::Delivery, OrderChannel::Delivery,
            OrderChannel::DineIn, OrderChannel::Pickup, OrderChannel::Delivery, OrderChannel::DineIn,
        ];

        foreach ([86, 79, 71, 63, 55, 46, 38, 29, 21, 14, 8, 3] as $index => $daysAgo) {
            $day = $this->now->subDays($daysAgo)->startOfDay();

            for ($shift = 0; $shift < 7 && Trading::periods($day) === []; $shift++) {
                $day = $day->addDay();
            }

            $placed = Trading::pickTime($day);

            if ($placed === null || $placed > $this->now->subDay()) {
                continue;
            }

            $this->compose($channels[$index], $placed, $this->demo, $index === 4 ? OrderStatus::Cancelled : OrderStatus::Completed);
        }
    }

    private function planLive(): void
    {
        $queue = [
            [OrderStatus::Placed, OrderChannel::Delivery],
            [OrderStatus::Placed, OrderChannel::Pickup],
            [OrderStatus::Accepted, OrderChannel::DineIn],
            [OrderStatus::Accepted, OrderChannel::Delivery],
            [OrderStatus::Cooking, OrderChannel::DineIn],
            [OrderStatus::Cooking, OrderChannel::DineIn],
            [OrderStatus::Cooking, OrderChannel::Delivery],
            [OrderStatus::Cooking, OrderChannel::Pickup],
            [OrderStatus::Ready, OrderChannel::Pickup],
            [OrderStatus::Ready, OrderChannel::Delivery],
            [OrderStatus::OutForDelivery, OrderChannel::Delivery],
            [OrderStatus::OutForDelivery, OrderChannel::Delivery],
            [OrderStatus::Served, OrderChannel::DineIn],
            [OrderStatus::Served, OrderChannel::DineIn],
        ];

        $seen = [];

        foreach ($queue as $index => [$status, $channel]) {
            $user = $index === 11 ? $this->demo : $this->customer();
            $table = $this->compose($channel, null, $user, $status);
            $key = $status->value.($seen[$status->value] ?? 0);
            $seen[$status->value] = ($seen[$status->value] ?? 0) + 1;

            if ($table !== null) {
                $this->tableByRole[$key] = $table;
                $this->liveTables[] = $table;
            }
        }
    }

    /**
     * Builds one order with its lines, status events and payment, and queues
     * it for insertion. Live orders (no placed time) are timed backwards from
     * now so that they entered their current status a few minutes ago.
     *
     * @return int|null the dining table used, for dine-in orders
     */
    private function compose(OrderChannel $channel, ?CarbonImmutable $placed, ?User $user, OrderStatus $final, bool $refund = false): ?int
    {
        $live = $placed === null;
        $lines = $this->catalogue->lines($this->catalogue->lineCount($channel === OrderChannel::DineIn));
        $zone = null;
        $address = null;

        if ($channel === OrderChannel::Delivery) {
            $address = $this->deliveryAddress($user, $placed ?? $this->now);
            $zone = $this->zones->first(fn (DeliveryZone $candidate) => in_array(Str::before($address['postcode'], ' '), $candidate->postcode_prefixes, true));

            while ($this->subtotal($lines) < ($zone->minimum_order ?? 0) && count($lines) < 8) {
                $lines[] = $this->catalogue->line();
            }
        }

        $tip = match ($channel) {
            OrderChannel::Delivery => Dice::chance(0.25) ? Dice::pick([100, 150, 200, 300]) : 0,
            OrderChannel::Pickup => Dice::chance(0.06) ? 100 : 0,
            OrderChannel::DineIn => Dice::chance(0.05) ? 200 : 0,
        };

        $promo = $live ? null : $this->promoFor($this->subtotal($lines), $placed);
        $totals = $this->pricing->totals(
            array_map(fn (array $line) => ['total' => $line['unit_price'] * $line['quantity'], 'vat_rate' => $line['vat_rate']], $lines),
            $channel,
            $zone,
            $promo,
            $tip,
        );

        if ($totals['discount'] === 0) {
            $promo = null;
        }

        $steps = $this->timeline($channel, $final, max(1, ...array_column($lines, 'prep')), $live);
        $lastOffset = $steps[array_key_last($steps)][1];
        $placed ??= $this->now->subSeconds($lastOffset + $this->stay($final, $lastOffset));

        $at = [];

        foreach ($steps as [$step, $offset]) {
            $at[$step->value] = $placed->addSeconds($offset);
        }

        $riderId = $channel === OrderChannel::Delivery && in_array($final, [OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed], true)
            ? Dice::pick($this->staff[Role::Rider->value])
            : null;
        $customerId = $user?->id;
        $events = [];
        $previous = null;

        foreach ($steps as [$step, $offset]) {
            $events[] = [
                'from' => $previous?->value,
                'to' => $step->value,
                'user_id' => $this->eventUser($step, $riderId, $customerId),
                'created_at' => Trading::stamp($placed->addSeconds($offset)),
            ];
            $previous = $step;
        }

        $method = $this->paymentMethod($channel);
        $payment = $this->payment($method, $final, $refund, $totals['total'], $lines, $placed, $live);
        $tableId = null;

        if ($channel === OrderChannel::DineIn) {
            $tableId = $live
                ? $this->tables->reject(fn (DiningTable $table) => in_array($table->id, $this->liveTables, true))->random()->id
                : $this->tables->random()->id;
        }

        $accepted = $at[OrderStatus::Accepted->value] ?? null;
        $prep = max(1, ...array_column($lines, 'prep'));
        $contact = $user !== null
            ? ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]
            : ['name' => EastLondon::person(), 'email' => fake('en_GB')->safeEmail(), 'phone' => EastLondon::mobile()];
        $cashCollected = $method === PaymentMethod::Cash && $final === OrderStatus::Completed
            ? (int) (ceil($totals['total'] / 500) * 500)
            : null;
        $pickupAt = $channel === OrderChannel::Pickup ? $this->roundUp($placed->addMinutes(mt_rand(20, 35))) : null;
        $end = $at[$final->value] ?? $placed;

        $this->bundles[] = [
            'placed' => $placed,
            'user_id' => $customerId,
            'completed' => $final === OrderStatus::Completed,
            'order' => [
                'channel' => $channel->value,
                'status' => $final->value,
                'user_id' => $customerId,
                'dining_table_id' => $tableId,
                'rider_id' => $riderId,
                'delivery_zone_id' => $zone?->id,
                'promo_code_id' => $promo?->id,
                'contact' => json_encode($contact, JSON_THROW_ON_ERROR),
                'address' => $address !== null ? json_encode($address, JSON_THROW_ON_ERROR) : null,
                'postcode' => $address['postcode'] ?? null,
                'pickup_at' => $pickupAt !== null ? Trading::stamp($pickupAt) : null,
                ...$totals,
                'payment_status' => $payment['order_status']->value,
                'payment_method' => $method->value,
                'cash_collected' => $cashCollected,
                'notes' => Dice::chance(0.1) ? Dice::pick(self::ORDER_NOTES[$channel->value]) : null,
                'rejection_reason' => $final === OrderStatus::Rejected ? Dice::pick(self::REJECTIONS) : null,
                'age_confirmed' => $channel !== OrderChannel::DineIn && in_array(true, array_column($lines, 'alcohol'), true),
                'placed_at' => Trading::stamp($placed),
                'accepted_at' => $accepted !== null ? Trading::stamp($accepted) : null,
                'ready_at' => isset($at[OrderStatus::Ready->value]) ? Trading::stamp($at[OrderStatus::Ready->value]) : null,
                'completed_at' => $final === OrderStatus::Completed ? Trading::stamp($end) : null,
                'estimated_ready_at' => Trading::stamp(($accepted ?? $placed)->addMinutes($prep + 4)),
                'created_at' => Trading::stamp($placed),
                'updated_at' => Trading::stamp($end),
            ],
            'lines' => $this->lineRows($lines, $final, $at, $placed),
            'events' => $events,
            'payment' => $payment['row'],
            'points' => $final === OrderStatus::Completed && $payment['order_status'] === PaymentStatus::Paid
                ? intdiv($totals['subtotal'] - $totals['discount'], 100)
                : 0,
        ];

        return $tableId;
    }

    /**
     * @param  list<Line>  $lines
     * @param  array<string, CarbonImmutable>  $at
     * @return list<array<string, mixed>>
     */
    private function lineRows(array $lines, OrderStatus $final, array $at, CarbonImmutable $placed): array
    {
        $count = count($lines);
        $cookingAt = $at[OrderStatus::Cooking->value] ?? null;
        $readyAt = $at[OrderStatus::Ready->value] ?? null;
        $statuses = array_fill(0, $count, LineStatus::Queued);

        if ($final === OrderStatus::Cooking) {
            $ready = $count === 1 ? 0 : ($count === 2 ? 1 : intdiv($count, 3));
            $cooking = $count >= 5 ? 2 : 1;

            foreach ($statuses as $index => $_) {
                $statuses[$index] = $index < $ready ? LineStatus::Ready : ($index < $ready + $cooking ? LineStatus::Cooking : LineStatus::Queued);
            }
        } elseif ($readyAt !== null) {
            $statuses = array_fill(0, $count, LineStatus::Ready);
        }

        $rows = [];
        $latest = $this->now->subSeconds(5);

        foreach ($lines as $index => $line) {
            $started = null;
            $bumped = null;

            if ($statuses[$index] !== LineStatus::Queued && $cookingAt !== null) {
                if ($final === OrderStatus::Cooking) {
                    $started = min($cookingAt->addSeconds(mt_rand(0, 40)), $latest);
                    $bumped = $statuses[$index] === LineStatus::Ready ? min($started->addSeconds(mt_rand(90, 240)), $latest) : null;
                    $started = $statuses[$index] === LineStatus::Cooking ? min($cookingAt->addSeconds(mt_rand(30, 120)), $latest) : $started;
                } elseif ($readyAt !== null) {
                    $started = $cookingAt->addSeconds(mt_rand(0, 60));
                    $bumped = $readyAt->subSeconds(mt_rand(5, 60));
                }
            }

            $rows[] = [
                'menu_item_id' => $line['menu_item_id'],
                'item_variant_id' => $line['item_variant_id'],
                'name' => $line['name'],
                'variant_name' => $line['variant_name'],
                'modifiers' => json_encode($line['modifiers'], JSON_THROW_ON_ERROR),
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'vat_rate' => $line['vat_rate'],
                'station' => $line['station'],
                'status' => $statuses[$index]->value,
                'notes' => $line['notes'],
                'started_at' => $started !== null ? Trading::stamp($started) : null,
                'bumped_at' => $bumped !== null ? Trading::stamp($bumped) : null,
                'created_at' => Trading::stamp($placed),
                'updated_at' => Trading::stamp($bumped ?? $started ?? $placed),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<Line>  $lines
     * @return array{row: array<string, mixed>|null, order_status: PaymentStatus}
     */
    private function payment(PaymentMethod $method, OrderStatus $final, bool $refund, int $total, array $lines, CarbonImmutable $placed, bool $live): array
    {
        $online = in_array($method, [PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::GooglePay], true);
        $refunded = 0;

        if ($online) {
            $status = PaymentStatus::Paid;

            if (in_array($final, [OrderStatus::Rejected, OrderStatus::Cancelled], true)) {
                $status = PaymentStatus::Refunded;
                $refunded = $total;
            } elseif ($refund) {
                $whole = Dice::chance(0.3);
                $status = $whole ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
                $refunded = $whole ? $total : min($total, $lines[0]['unit_price'] * $lines[0]['quantity']);
            }
        } else {
            $status = $final === OrderStatus::Completed ? PaymentStatus::Paid : PaymentStatus::Pending;

            if (! $live && $status === PaymentStatus::Pending) {
                return ['row' => null, 'order_status' => PaymentStatus::Pending];
            }
        }

        $brand = Dice::pick(['visa', 'visa', 'mastercard', 'amex']);

        return [
            'order_status' => $status,
            'row' => [
                'gateway' => $online ? 'stripe' : 'venue',
                'reference' => $online ? 'pi_3'.Str::random(21) : null,
                'method' => $method->value,
                'amount' => $total,
                'refunded' => $refunded,
                'status' => $status->value,
                'meta' => $online ? json_encode($method === PaymentMethod::Card ? ['brand' => $brand, 'last4' => (string) mt_rand(1000, 9999)] : ['wallet' => $method->value], JSON_THROW_ON_ERROR) : null,
                'created_at' => Trading::stamp($placed),
                'updated_at' => Trading::stamp($placed->addMinutes(1)),
            ],
        ];
    }

    /**
     * Seconds from placing to each status, up to and including the final one.
     *
     * @return non-empty-list<array{0: OrderStatus, 1: int}>
     */
    private function timeline(OrderChannel $channel, OrderStatus $final, int $maxPrep, bool $live): array
    {
        if ($final === OrderStatus::Rejected) {
            return [[OrderStatus::Placed, 0], [OrderStatus::Rejected, mt_rand(120, 420)]];
        }

        $accepted = mt_rand(10, 120);

        if ($final === OrderStatus::Cancelled) {
            return [[OrderStatus::Placed, 0], [OrderStatus::Accepted, $accepted], [OrderStatus::Cancelled, $accepted + mt_rand(200, 600)]];
        }

        $cooking = $accepted + mt_rand(60, 180);
        // Estimates are prep time plus four minutes; most tickets beat that,
        // about one in five runs over, as in a well-run kitchen.
        $ready = $accepted + max(240, min($maxPrep, $live ? 12 : 60) * 60 + mt_rand(-150, 330));
        $steps = [[OrderStatus::Placed, 0], [OrderStatus::Accepted, $accepted], [OrderStatus::Cooking, $cooking], [OrderStatus::Ready, $ready]];

        if ($channel === OrderChannel::Delivery) {
            $out = $ready + mt_rand(120, 420);
            $delivered = $out + mt_rand(8, 22) * 60;
            $steps = [...$steps, [OrderStatus::OutForDelivery, $out], [OrderStatus::Delivered, $delivered], [OrderStatus::Completed, $delivered + 60]];
        } elseif ($channel === OrderChannel::Pickup) {
            $collected = $ready + mt_rand(0, 600);
            $steps = [...$steps, [OrderStatus::Collected, $collected], [OrderStatus::Completed, $collected + 30]];
        } else {
            $served = $ready + mt_rand(60, 240);
            $steps = [...$steps, [OrderStatus::Served, $served], [OrderStatus::Completed, $served + mt_rand(2400, 4200)]];
        }

        foreach ($steps as $index => [$step]) {
            if ($step === $final) {
                return array_slice($steps, 0, $index + 1);
            }
        }

        throw new \LogicException("{$final->value} is not a status of a {$channel->value} order.");
    }

    /**
     * How long a live order has sat in its current status, keeping the whole
     * order inside the last 44 minutes.
     */
    private function stay(OrderStatus $final, int $offset): int
    {
        $stay = $final === OrderStatus::Placed ? mt_rand(30, 150) : mt_rand(60, 300);

        return $offset + $stay > 2640 ? max(30, 2640 - $offset) : $stay;
    }

    private function roundUp(CarbonImmutable $time): CarbonImmutable
    {
        return $time->startOfHour()->addMinutes((int) (ceil($time->minute / 15) * 15));
    }

    private function eventUser(OrderStatus $step, ?int $riderId, ?int $customerId): ?int
    {
        return match ($step) {
            OrderStatus::Placed => $customerId,
            OrderStatus::Accepted => Dice::chance(0.85) ? null : Dice::pick($this->staff[Role::Cashier->value]),
            OrderStatus::Cooking, OrderStatus::Ready => Dice::pick($this->staff[Role::Kitchen->value]),
            OrderStatus::OutForDelivery, OrderStatus::Delivered => $riderId,
            OrderStatus::Served => Dice::pick($this->staff[Role::Waiter->value]),
            OrderStatus::Collected => Dice::pick($this->staff[Role::Cashier->value]),
            OrderStatus::Completed => Dice::chance(0.5) ? Dice::pick($this->staff[Role::Cashier->value]) : null,
            OrderStatus::Rejected => Dice::pick($this->staff[Role::Manager->value]),
            OrderStatus::Cancelled => $customerId ?? Dice::pick($this->staff[Role::Cashier->value]),
        };
    }

    private function paymentMethod(OrderChannel $channel): PaymentMethod
    {
        return match ($channel) {
            OrderChannel::Delivery => Dice::weighted([[PaymentMethod::Card, 55], [PaymentMethod::ApplePay, 20], [PaymentMethod::GooglePay, 17], [PaymentMethod::Cash, 8]]),
            OrderChannel::Pickup => Dice::weighted([[PaymentMethod::Card, 50], [PaymentMethod::ApplePay, 15], [PaymentMethod::GooglePay, 10], [PaymentMethod::PayAtVenue, 25]]),
            OrderChannel::DineIn => Dice::weighted([[PaymentMethod::PayAtVenue, 45], [PaymentMethod::Card, 25], [PaymentMethod::Cash, 12], [PaymentMethod::ApplePay, 10], [PaymentMethod::GooglePay, 8]]),
        };
    }

    private function channel(): OrderChannel
    {
        return Dice::weighted([[OrderChannel::Delivery, 45], [OrderChannel::Pickup, 25], [OrderChannel::DineIn, 30]]);
    }

    /**
     * Regulars order more often than the rest; a third of orders are guests.
     */
    private function customer(): ?User
    {
        if (! Dice::chance(0.65) || $this->customers->isEmpty()) {
            return null;
        }

        $index = (int) floor($this->customers->count() * (mt_rand() / mt_getrandmax()) ** 2);

        return $this->customers->values()->get(min($index, $this->customers->count() - 1));
    }

    /**
     * @return array{line1: string, line2: string|null, city: string, postcode: string, instructions: string|null}
     */
    private function deliveryAddress(?User $user, CarbonImmutable $when): array
    {
        if ($user !== null && $user->addresses->isNotEmpty()) {
            $saved = $user->addresses;
            $address = $user->is($this->demo) && $when->hour < 16
                ? ($saved->firstWhere('label', 'Work') ?? $saved->first())
                : ($saved->firstWhere('is_default', true) ?? $saved->first());

            return [
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'postcode' => $address->postcode,
                'instructions' => $address->instructions,
            ];
        }

        $prefix = Dice::weighted([['E1', 30], ['E2', 25], ['E3', 8], ['E8', 12], ['E14', 8], ['E5', 6], ['E9', 6], ['N1', 5]]);

        return [...EastLondon::address($prefix), 'instructions' => null];
    }

    private function promoFor(int $subtotal, CarbonImmutable $placed): ?PromoCode
    {
        foreach ($this->promos as $promo) {
            if (($promo->starts_at !== null && $placed < $promo->starts_at) || ($promo->ends_at !== null && $placed > $promo->ends_at)) {
                continue;
            }

            $chance = match ($promo->code) {
                'WELCOME10' => 0.07,
                'TANDOOR5' => $subtotal >= 3000 ? 0.15 : 0,
                default => 0.2,
            };

            if (Dice::chance($chance)) {
                return $promo;
            }
        }

        return null;
    }

    /**
     * @param  list<Line>  $lines
     */
    private function subtotal(array $lines): int
    {
        return array_sum(array_map(fn (array $line) => $line['unit_price'] * $line['quantity'], $lines));
    }

    private function persist(): void
    {
        usort($this->bundles, fn (array $a, array $b) => $a['placed']->getTimestamp() <=> $b['placed']->getTimestamp());

        $id = (int) DB::table('orders')->max('id');
        $number = 10240;
        $points = [];
        $promoUses = [];
        $loyalty = [];

        foreach (array_chunk($this->bundles, 50) as $chunk) {
            $orders = [];
            $lines = [];
            $events = [];
            $payments = [];

            foreach ($chunk as $bundle) {
                $id++;
                $number++;
                $orders[] = ['id' => $id, 'number' => "TH-{$number}", ...$bundle['order']];

                foreach ($bundle['lines'] as $line) {
                    $lines[] = ['order_id' => $id, ...$line];
                }

                foreach ($bundle['events'] as $event) {
                    $events[] = ['order_id' => $id, ...$event];
                }

                if ($bundle['payment'] !== null) {
                    $payments[] = ['order_id' => $id, ...$bundle['payment']];
                }

                if ($bundle['order']['promo_code_id'] !== null) {
                    $promoUses[$bundle['order']['promo_code_id']] = ($promoUses[$bundle['order']['promo_code_id']] ?? 0) + 1;
                }

                if ($bundle['points'] > 0 && $bundle['user_id'] !== null) {
                    $points[$bundle['user_id']] = ($points[$bundle['user_id']] ?? 0) + $bundle['points'];
                    $loyalty[] = [
                        'user_id' => $bundle['user_id'],
                        'order_id' => $id,
                        'points' => $bundle['points'],
                        'kind' => 'earned',
                        'reason' => "Order TH-{$number}",
                        'created_at' => $bundle['order']['completed_at'],
                    ];
                }
            }

            DB::table('orders')->insert($orders);
            DB::table('order_lines')->insert($lines);
            DB::table('order_status_events')->insert($events);
            DB::table('payments')->insert($payments);
        }

        foreach (array_chunk($loyalty, 100) as $chunk) {
            DB::table('loyalty_transactions')->insert($chunk);
        }

        foreach ($points as $userId => $total) {
            DB::table('users')->where('id', $userId)->update(['loyalty_points' => $total]);
        }

        foreach ($promoUses as $promoId => $uses) {
            DB::table('promo_codes')->where('id', $promoId)->update(['uses' => $uses]);
        }

        $this->tableRequests();
    }

    /**
     * One pending waiter call, one pending bill request, and a few handled earlier today.
     */
    private function tableRequests(): void
    {
        $waiters = $this->staff[Role::Waiter->value];
        $rows = [];

        $call = $this->tableByRole['cooking1'] ?? null;
        $bill = $this->tableByRole['served0'] ?? null;

        if ($call !== null) {
            $rows[] = ['dining_table_id' => $call, 'type' => TableRequestType::CallWaiter->value, 'handled_at' => null, 'handled_by' => null, 'created_at' => Trading::stamp($this->now->subMinutes(3))];
        }

        if ($bill !== null) {
            $rows[] = ['dining_table_id' => $bill, 'type' => TableRequestType::Bill->value, 'handled_at' => null, 'handled_by' => null, 'created_at' => Trading::stamp($this->now->subMinutes(2))];
        }

        foreach ($this->tables->random(5) as $table) {
            $created = $this->now->subMinutes(mt_rand(60, 600));
            $rows[] = [
                'dining_table_id' => $table->id,
                'type' => Dice::pick([TableRequestType::CallWaiter, TableRequestType::Bill])->value,
                'handled_at' => Trading::stamp($created->addMinutes(2)),
                'handled_by' => Dice::pick($waiters),
                'created_at' => Trading::stamp($created),
            ];
        }

        foreach ($rows as &$row) {
            $row['updated_at'] = $row['created_at'];
        }

        DB::table('table_requests')->insert($rows);
    }
}
