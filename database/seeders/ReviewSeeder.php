<?php

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
 * Forty-odd reviews, each tied to a completed order of the matching kind,
 * plus two from the demo customer.
 */
class ReviewSeeder extends Seeder
{
    private const D = OrderChannel::Delivery;

    private const P = OrderChannel::Pickup;

    private const E = OrderChannel::DineIn;

    /**
     * Channel, rating, body, manager reply.
     *
     * @var list<array{0: OrderChannel, 1: int, 2: string, 3: string|null}>
     */
    private const REVIEWS = [
        [self::D, 5, 'Rogan josh arrived hot, not tepid, which never happens on a Friday. Naan still blistered. Rider found our flat first time.', null],
        [self::D, 5, 'Black daal on a wet Tuesday. Smoky, properly cooked down. One portion fed the two of us with rice to spare.', null],
        [self::D, 4, 'Butter chicken was lovely and well balanced. Quoted 35 minutes, took 50, but the rider apologised and everything was hot.', null],
        [self::D, 5, 'Ordered for six at work. Spice levels marked on the lids, raita in its own pot so nothing went soggy. Our default now.', null],
        [self::D, 4, 'The Chettinad is genuinely hot, as promised. Wish the lime pickle came as standard, so I added it next time.', null],
        [self::D, 3, 'Biryani was excellent but the naan had gone leathery by the time it reached us. Might just be the ride.', 'Thanks for the honest note. We now pack naan in paper, not a sealed box, so it keeps its crisp. Tom, manager'],
        [self::D, 5, 'A vegan jackfruit biryani that does not taste like an afterthought. Pastry lid still crisp-ish. My partner ate half before I opened mine.', null],
        [self::D, 4, 'Chicken tikka was smoky and tender, thigh meat done right. A lot of plastic in the bag though.', null],
        [self::D, 5, 'Second order this month. Same hot food, same polite rider. The lamb seekh is the one.', null],
        [self::D, 2, 'Arrived twenty minutes late and the prawn curry was lukewarm. The prawns themselves were good. Refund offered quickly, which I appreciated.', 'That is not the standard we hold ourselves to, and I am sorry. Friday was our busiest night in weeks and we have changed how riders are staggered. Tom, manager'],
        [self::D, 5, 'Palak paneer that tastes of spinach rather than cream. Jeera rice properly fluffy. Under half an hour to Bethnal Green.', null],
        [self::D, 4, 'Good value for the cooking. Poppadoms came broken but the pickle tray made up for it.', null],
        [self::D, 5, 'Left a note about a nut allergy and they rang to double check before cooking. That is why we keep ordering.', 'That call is exactly what we want to be doing. Thank you for trusting us with it. Tom, manager'],
        [self::D, 4, 'Samosa chaat travelled surprisingly well, chutneys in separate pots. Gulab jamun a touch sweet for me, my daughter loved it.', null],
        [self::P, 5, 'Collected at 7.15 as promised, bag still warm and the daal still hissing. Friendly and quick at the counter.', null],
        [self::P, 4, 'Quick pick-up, though I waited five minutes past my slot. The lamb chops were worth it.', null],
        [self::P, 5, 'Collection after work: chicken tikka, garlic naan, a mango lassi for the walk home. Exactly right.', null],
        [self::P, 3, 'Food was good but a naan was missing from the bag. They sorted it with a free dessert next visit, which is fair.', null],
        [self::P, 5, 'Easiest pick-up in the area. Pay online, walk in, bag handed over in under a minute. Butter chicken as good as sitting in.', null],
        [self::P, 4, 'Well packed. The kulfi had softened on the cycle home along Hackney Road but still tasted great.', null],
        [self::E, 5, 'Counter seats by the tandoor are the best in the house. Watched the naan go on the wall, ate it thirty seconds later. Lamb chops outstanding.', null],
        [self::E, 5, 'Booked for my mum\'s 70th. They brought a candle with the kulfi without being asked and gave us the window table.', 'What a lovely evening to be part of. Happy birthday to your mum. Tom, manager'],
        [self::E, 5, 'The Hyderabadi biryani is opened at the table, steam and saffron everywhere. The dough seal is theatre, but it earns it.', null],
        [self::E, 4, 'Lively on a Saturday, a bit loud, but the food held up: tikka, black daal, rotis. Staff juggled it well.', null],
        [self::E, 5, 'Callum talked us out of a third curry and into the broccoli. He was right.', null],
        [self::E, 4, 'Prawn koliwada is the best thing I have eaten in Spitalfields this year. The chai at the end was a touch too sweet.', null],
        [self::E, 3, 'Good cooking, slow service. Forty minutes between starters and mains on a Thursday when half the room was empty.', 'Thank you, and sorry. We have looked at how tickets are fired on quieter nights so that gap does not happen again. Tom, manager'],
        [self::E, 5, 'Garden table, heaters on, black daal and a cold IPA. Winter dining sorted.', null],
        [self::E, 5, 'We ordered too much, as always. The pani puri is great fun, the kids loved pouring the water themselves.', null],
        [self::E, 4, 'Vegetarian friends well looked after: palak paneer, chana masala, tandoori broccoli, all properly seasoned.', null],
        [self::E, 5, 'The rogan josh tasted as if someone\'s grandmother had been standing over it. Not heavy. Bringing my dad next.', null],
        [self::E, 2, 'Waited twenty-five minutes for our table despite booking for seven. Food was good once it came but the evening started badly.', 'You should not have waited, and I am sorry. The table before yours ran over and we should have told you sooner. Please ask for me next time. Tom, manager'],
        [self::E, 5, 'Lovely room, not stuffy. Prices fair for the neighbourhood. The mango shrikhand is dangerous.', null],
        [self::E, 4, 'Ordered the Chettinad at a three out of three. Sweated happily. The fresh lime soda got me through.', null],
        [self::E, 5, 'Quiet Tuesday lunch. The manager sat us and talked us through half the menu. He genuinely knows the food.', null],
        [self::E, 4, 'Service charge was on the bill at 12.5%, clearly marked and easy to remove. Fair. Food excellent.', null],
        [self::E, 5, 'Chilli cheese toast. That is the review. Go.', null],
        [self::E, 5, 'Anniversary at the window table. Quiet enough to talk, dishes arrived well paced.', null],
        [self::E, 5, 'Black daal and butter naan at the counter on a Wednesday night. Simple and perfect.', null],
        [self::E, 4, 'Loved the bhel puri, wish the portion were bigger. Everything else spot on.', null],
        [self::E, 5, 'Group of eight on the long table, no fuss. They split the bill four ways without a sigh.', null],
    ];

    public function run(): void
    {
        $demo = User::where('email', 'customer@tandoorhouse.test')->firstOrFail();

        $orders = Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotNull('completed_at')
            ->whereDoesntHave('review')
            ->inRandomOrder()
            ->get();

        $demoOrders = $orders->where('user_id', $demo->id)->sortBy('placed_at')->values();
        $pool = $orders->where('user_id', '!=', $demo->id)->values();
        $rows = [];

        $rows[] = $this->row(
            $demoOrders->firstWhere('channel', OrderChannel::Delivery),
            5,
            'Friday night staple now. Butter chicken, garlic naan and a black daal, always hot and always on time to Cheshire Street.',
            null,
            true,
        );
        $rows[] = $this->row(
            $demoOrders->firstWhere('channel', OrderChannel::DineIn),
            4,
            'Went for a friend\'s birthday. Good pace, kind staff, and the lamb seekh is the dish I keep thinking about. Table was a little close to the door.',
            null,
            true,
        );

        foreach (self::REVIEWS as [$channel, $rating, $body, $reply]) {
            $order = $this->take($pool, $channel);

            if ($order !== null) {
                $rows[] = $this->row($order, $rating, $body, $reply, $rating >= 3 || Dice::chance(0.5));
            }
        }

        DB::table('reviews')->insert(array_values(array_filter($rows)));
    }

    /**
     * @param  Collection<int, Order>  $pool
     */
    private function take(Collection &$pool, OrderChannel $channel): ?Order
    {
        $order = $pool->first(fn (Order $candidate) => $candidate->channel === $channel);

        if ($order !== null) {
            $pool = $pool->reject(fn (Order $candidate) => $candidate->is($order))->values();
        }

        return $order;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(?Order $order, int $rating, string $body, ?string $reply, bool $published): ?array
    {
        if ($order === null) {
            return null;
        }

        $name = trim((string) ($order->contact['name'] ?? 'Guest'));
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $author = Str::before($parts[0], '-').' '.Str::upper(Str::substr((string) end($parts), 0, 1)).'.';
        $prefix = $order->postcode !== null ? Str::before($order->postcode, ' ') : null;
        $when = CarbonImmutable::parse($order->completed_at ?? $order->placed_at)->addHours(mt_rand(3, 40));
        $when = min($when, Trading::now()->subMinutes(10));

        return [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'author_name' => $author,
            'author_area' => EastLondon::area($prefix),
            'rating' => $rating,
            'body' => $body,
            'reply' => $reply,
            'is_published' => $published,
            'created_at' => Trading::stamp($when),
            'updated_at' => Trading::stamp($reply !== null ? $when->addHours(mt_rand(2, 20)) : $when),
        ];
    }
}
