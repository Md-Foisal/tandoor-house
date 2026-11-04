<?php

namespace Database\Seeders;

use App\Actions\Team\RecordTipAllocations;
use App\Actions\Team\WeekRange;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\StaffInvite;
use App\Models\TipAllocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Database\Seeders\Support\Trading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the later phases add to the demo: the demo customer's points, booking
 * and unpaid card order, a pending staff invite, recorded tip splits and a
 * few more automated log entries from the past days.
 */
class FeatureDemoSeeder extends Seeder
{
    public const UNPAID_ORDER = 'TH-90001';

    public const BOOKING = 'RDEMO26';

    public function run(): void
    {
        $demo = User::where('email', 'customer@tandoorhouse.test')->firstOrFail();

        $this->loyalty($demo);
        $this->unpaidOrder($demo);
        $this->booking($demo);
        $this->invite();
        $this->tips();
        $this->activity();
    }

    private function loyalty(User $demo): void
    {
        $demo->forceFill(['marketing_opt_in' => true])->save();

        $reward = (int) config('restaurant.defaults.loyalty.reward_points');
        $earnedOrder = Order::where('user_id', $demo->id)->where('status', OrderStatus::Completed)->latest('completed_at')->first();

        if ($earnedOrder === null) {
            return;
        }

        // The balance must stay above the reward so the redeem button shows.
        $earned = (int) $demo->loyaltyTransactions()->where('kind', 'earned')->sum('points');
        $shortfall = ($reward + 220) - $earned;

        if ($shortfall > 0) {
            Order::where('user_id', $demo->id)->where('status', OrderStatus::Completed)->oldest('completed_at')->limit(3)->get()
                ->each(function (Order $order) use (&$shortfall) {
                    $extra = (int) ceil($shortfall / 3);
                    DB::table('loyalty_transactions')->where('order_id', $order->id)->where('kind', 'earned')->increment('points', $extra);
                });
        }

        LoyaltyTransaction::forceCreate([
            'user_id' => $demo->id,
            'order_id' => $earnedOrder->id,
            'points' => -$reward,
            'kind' => 'redeemed',
            'reason' => 'Reward: £'.number_format((int) config('restaurant.defaults.loyalty.reward_value') / 100).' off',
            'created_at' => Trading::stamp(CarbonImmutable::parse($earnedOrder->placed_at)->subDay()),
        ]);

        $demo->forceFill(['loyalty_points' => (int) $demo->loyaltyTransactions()->sum('points')])->save();
    }

    private function unpaidOrder(User $demo): void
    {
        $order = Order::factory()->forCustomer($demo)->pickup()->state([
            'number' => self::UNPAID_ORDER,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'placed_at' => Trading::now()->subMinutes(6),
            'pickup_at' => Trading::now()->addMinutes(50),
        ])->status(OrderStatus::Placed)->create();

        foreach ([['Butter Chicken', 1], ['Garlic and Coriander Naan', 2]] as [$name, $quantity]) {
            $item = MenuItem::where('name', $name)->firstOrFail();
            OrderLine::factory()->for($order)->forItem($item)->create(['quantity' => $quantity]);
        }

        $order->refresh();
        OrderFactory::recalculate($order);
        $order->refresh();

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'fake',
            'reference' => 'fake_'.substr(hash('sha256', self::UNPAID_ORDER), 0, 24),
            'method' => PaymentMethod::Card,
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);
    }

    private function booking(User $demo): void
    {
        $day = Trading::now()->addDays(4)->startOfDay();

        while (Trading::periods($day) === []) {
            $day = $day->addDay();
        }

        $periods = Trading::periods($day);
        $starts = $periods[array_key_last($periods)][0]->addMinutes(60);
        $ends = $starts->addMinutes((int) config('restaurant.defaults.reservation.duration_minutes'));
        $party = 6;

        $table = DiningTable::where('seats', '>=', $party)->orderBy('seats')->orderBy('id')->get()
            ->first(fn (DiningTable $candidate) => ! Reservation::where('dining_table_id', $candidate->id)
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->where('starts_at', '<', Trading::stamp($ends))
                ->where('ends_at', '>', Trading::stamp($starts))
                ->exists());

        $reservation = Reservation::create([
            'reference' => self::BOOKING,
            'user_id' => $demo->id,
            'name' => $demo->name,
            'email' => $demo->email,
            'phone' => $demo->phone ?? '07700 900123',
            'party_size' => $party,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'dining_table_id' => $table?->id,
            'status' => ReservationStatus::Confirmed,
            'deposit' => $party * (int) config('restaurant.defaults.reservation.deposit_per_head'),
            'deposit_status' => PaymentStatus::Paid,
            'occasion' => 'birthday',
            'notes' => 'A small cake, if we may bring one.',
        ]);

        Payment::create([
            'order_id' => null,
            'payable_type' => $reservation->getMorphClass(),
            'payable_id' => $reservation->id,
            'gateway' => 'fake',
            'reference' => 'fake_'.substr(hash('sha256', self::BOOKING), 0, 24),
            'method' => PaymentMethod::Card,
            'amount' => $reservation->deposit,
            'status' => PaymentStatus::Paid,
            'meta' => ['brand' => 'visa', 'last4' => '4242'],
        ]);
    }

    private function invite(): void
    {
        $manager = User::where('email', 'manager@tandoorhouse.test')->firstOrFail();
        $nadia = User::factory()->role(Role::Waiter)->create([
            'name' => 'Nadia Hussain',
            'email' => 'nadia.hussain@tandoorhouse.test',
        ]);
        $sent = Trading::now()->subDays(2);

        StaffInvite::create([
            'user_id' => $nadia->id,
            'token_hash' => StaffInvite::hashToken(Str::random(64)),
            'invited_by' => $manager->id,
            'sent_at' => $sent,
            'expires_at' => $sent->addDays(7),
        ]);

        DB::table('activity_logs')->insert([
            'event' => 'automation.team.invite_sent',
            'subject_type' => $nadia->getMorphClass(),
            'subject_id' => $nadia->id,
            'user_id' => null,
            'description' => "Invite emailed to Nadia Hussain on behalf of {$manager->name}",
            'properties' => json_encode(['invited_by' => $manager->id, 'valid_days' => 7], JSON_THROW_ON_ERROR),
            'created_at' => Trading::stamp($sent),
        ]);

    }

    private function tips(): void
    {
        $manager = User::where('email', 'manager@tandoorhouse.test')->firstOrFail();
        $record = app(RecordTipAllocations::class);
        $current = WeekRange::from(null);

        $record($manager, new WeekRange($current->start->subWeek()));
        $older = new WeekRange($current->start->subWeeks(2));
        $record($manager, $older);

        TipAllocation::where('period', $older->start->toDateString())->update(['paid_at' => Trading::stamp($current->start->subWeek()->addDays(2)->setTime(10, 0))]);
    }

    /**
     * Back-dated entries so the feed shows each automation across the last few days.
     */
    private function activity(): void
    {
        $now = Trading::now();
        $rows = [];

        foreach (Ingredient::whereColumn('stock', '<', 'low_threshold')->limit(2)->get() as $index => $ingredient) {
            $rows[] = ['stock.low', "{$ingredient->name} is down to {$ingredient->stock} {$ingredient->unit}, below its low threshold of {$ingredient->low_threshold} {$ingredient->unit}", Ingredient::class, $ingredient->id, $now->subDays($index + 1)->setTime(17, 40)];
        }

        $accepted = Order::where('status', OrderStatus::Completed)->whereNotNull('accepted_at')->where('placed_at', '<', Trading::stamp($now->subDay()))->latest('placed_at')->first();

        if ($accepted !== null) {
            $rows[] = ['order.auto_accepted', "Order {$accepted->number} accepted automatically", Order::class, $accepted->id, CarbonImmutable::parse($accepted->accepted_at)];
        }

        $award = LoyaltyTransaction::with('user')->where('kind', 'earned')->latest('created_at')->skip(6)->first();

        if ($award !== null) {
            $rows[] = ['loyalty.points_awarded', "Awarded {$award->points} points to {$award->user?->name} ({$award->reason})", LoyaltyTransaction::class, $award->id, CarbonImmutable::parse($award->created_at)];
        }

        $reminded = Reservation::whereNotNull('reminded_at')->where('reminded_at', '<', Trading::stamp($now->subDay()))->latest('reminded_at')->first();

        if ($reminded !== null) {
            $rows[] = ['reservation.reminder_sent', "Reminder sent to {$reminded->name} for ".CarbonImmutable::parse($reminded->starts_at)->format('D j M \\a\\t H:i').", party of {$reminded->party_size}", Reservation::class, $reminded->id, CarbonImmutable::parse($reminded->reminded_at)];
        }

        foreach ($rows as [$event, $description, $subject, $subjectId, $at]) {
            DB::table('activity_logs')->insert([
                'event' => $event,
                'subject_type' => $subject,
                'subject_id' => $subjectId,
                'user_id' => null,
                'description' => $description,
                'properties' => null,
                'created_at' => Trading::stamp($at),
            ]);
        }
    }
}
