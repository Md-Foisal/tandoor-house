<?php

use App\Enums\LoyaltyKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Events\PaymentUpdated;
use App\Jobs\SendReviewRequest;
use App\Listeners\RequestReview;
use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Notifications\Customer\AbandonedBasket;
use App\Notifications\Customer\ReviewRequest;
use App\Services\OrderStateMachine;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function custSwitchOff(string $key): void
{
    app(Settings::class)->set('automations', [$key => false]);
}

function custOrder(?User $user, array $attributes = []): Order
{
    return Order::factory()->pickup()->when($user, fn ($factory) => $factory->forCustomer($user))
        ->status(OrderStatus::Completed)
        ->create($attributes);
}

function custComplete(Order $order): void
{
    event(new OrderStatusChanged($order, OrderStatus::Collected, OrderStatus::Completed));
}

describe('review requests', function () {
    it('schedules one request an hour after an order is completed', function () {
        Queue::fake();
        $order = custOrder(User::factory()->create());

        app(RequestReview::class)->handle(new OrderStatusChanged($order, OrderStatus::Collected, OrderStatus::Completed));

        Queue::assertPushed(SendReviewRequest::class, fn (SendReviewRequest $job) => $job->orderId === $order->id
            && abs(now()->addHour()->diffInSeconds($job->delay)) < 5);
    });

    it('emails a signed-in customer a link to the reviews page, once', function () {
        Notification::fake();
        $user = User::factory()->create();
        $order = custOrder($user);

        dispatch_sync(new SendReviewRequest($order->id));
        dispatch_sync(new SendReviewRequest($order->id));
        Cache::flush();
        dispatch_sync(new SendReviewRequest($order->id));

        Notification::assertSentToTimes($user, ReviewRequest::class, 1);
        expect(ActivityLog::where('event', 'automation.review.requested')->count())->toBe(1);
    });

    it('renders the mail with the reviews link', function () {
        $user = User::factory()->create();
        $mail = (new ReviewRequest(custOrder($user)))->toMail($user);

        expect($mail->subject)->toContain('How was order')
            ->and($mail->viewData['url'])->toBe(route('account.reviews.index'));
    });

    it('skips guests, reviewed, cancelled and refunded orders', function () {
        Notification::fake();
        $user = User::factory()->create();
        $guest = custOrder(null);
        $reviewed = custOrder($user);
        Review::factory()->create(['order_id' => $reviewed->id, 'user_id' => $user->id]);
        $cancelled = Order::factory()->pickup()->forCustomer($user)->status(OrderStatus::Cancelled)->create();
        $refunded = custOrder($user, ['payment_status' => PaymentStatus::Refunded]);

        foreach ([$guest, $reviewed, $cancelled, $refunded] as $order) {
            dispatch_sync(new SendReviewRequest($order->id));
        }

        Notification::assertNothingSent();
    });

    it('does nothing when the switch is off', function () {
        Queue::fake();
        custSwitchOff('review_requests');
        $order = custOrder(User::factory()->create());

        app(RequestReview::class)->handle(new OrderStatusChanged($order, OrderStatus::Collected, OrderStatus::Completed));
        Queue::assertNotPushed(SendReviewRequest::class);

        Notification::fake();
        dispatch_sync(new SendReviewRequest($order->id));
        Notification::assertNothingSent();
    });
});

describe('loyalty credit', function () {
    it('credits a point per whole pound of food after discount, leaving out tip and delivery', function () {
        $user = User::factory()->create(['loyalty_points' => 10]);
        $order = custOrder($user, ['subtotal' => 3250, 'discount' => 300, 'delivery_fee' => 249, 'tip' => 500, 'total' => 3699]);

        custComplete($order);

        expect($user->fresh()->loyalty_points)->toBe(10 + 29);
        $row = LoyaltyTransaction::where('order_id', $order->id)->sole();
        expect($row->points)->toBe(29);
        expect(ActivityLog::where('event', 'automation.loyalty.credited')->count())->toBe(1);
    });

    it('is idempotent when the event repeats', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = custOrder($user, ['subtotal' => 2000, 'total' => 2000]);

        custComplete($order);
        custComplete($order);

        expect($user->fresh()->loyalty_points)->toBe(20)->and(LoyaltyTransaction::count())->toBe(1);
    });

    it('credits through a real status change', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = Order::factory()->pickup()->forCustomer($user)->status(OrderStatus::Collected)->create(['subtotal' => 1500, 'total' => 1500]);

        app(OrderStateMachine::class)->transition($order, OrderStatus::Completed);

        expect($user->fresh()->loyalty_points)->toBe(15);
    });

    it('credits nobody for a guest order, a refunded one, or when the switch is off', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        custComplete(custOrder(null, ['subtotal' => 2000]));
        custComplete(custOrder($user, ['subtotal' => 2000, 'payment_status' => PaymentStatus::Refunded]));
        custSwitchOff('loyalty');
        custComplete(custOrder($user, ['subtotal' => 2000]));

        expect($user->fresh()->loyalty_points)->toBe(0)->and(LoyaltyTransaction::count())->toBe(0);
    });

    it('takes the points back after a full refund but not before', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = custOrder($user, ['subtotal' => 2000, 'total' => 2000]);
        custComplete($order);

        $payment = $order->payments()->create(['gateway' => 'venue', 'method' => $order->payment_method, 'amount' => 2000, 'refunded' => 500, 'status' => PaymentStatus::PartiallyRefunded]);
        $order->forceFill(['payment_status' => PaymentStatus::PartiallyRefunded])->save();
        event(new PaymentUpdated($payment, PaymentStatus::Paid));
        expect($user->fresh()->loyalty_points)->toBe(20);

        $payment->forceFill(['refunded' => 2000, 'status' => PaymentStatus::Refunded])->save();
        $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
        event(new PaymentUpdated($payment, PaymentStatus::PartiallyRefunded));
        event(new PaymentUpdated($payment, PaymentStatus::PartiallyRefunded));

        expect($user->fresh()->loyalty_points)->toBe(0)->and(LoyaltyTransaction::where('order_id', $order->id)->count())->toBe(2);
    });

    it('stays idempotent when the ledger wording changes', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = custOrder($user, ['subtotal' => 2000, 'total' => 2000]);
        custComplete($order);

        LoyaltyTransaction::query()->update(['reason' => 'Reworded entirely']);
        custComplete($order);

        expect($user->fresh()->loyalty_points)->toBe(20)->and(LoyaltyTransaction::count())->toBe(1);
    });

    it('claws back on a full refund even with the loyalty switch off, and returns a spent reward once', function () {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $order = custOrder($user, ['subtotal' => 2000, 'total' => 2000]);
        custComplete($order);
        LoyaltyTransaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'points' => -150, 'kind' => LoyaltyKind::Redeemed, 'reason' => 'Reward']);
        $user->forceFill(['loyalty_points' => 20 - 150 + 200])->save();

        custSwitchOff('loyalty');
        $order->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
        $payment = $order->payments()->create(['gateway' => 'venue', 'method' => $order->payment_method, 'amount' => 2000, 'refunded' => 2000, 'status' => PaymentStatus::Refunded]);
        event(new PaymentUpdated($payment, PaymentStatus::Paid));
        event(new PaymentUpdated($payment, PaymentStatus::Paid));

        expect($user->fresh()->loyalty_points)->toBe(70 - 20 + 150)
            ->and(LoyaltyTransaction::where('order_id', $order->id)->count())->toBe(4);
    });
});

describe('abandoned basket', function () {
    function custBasket(User $user, int $hoursAgo = 3, bool $optedIn = true): Cart
    {
        $user->forceFill(['marketing_opt_in' => $optedIn])->save();
        $cart = Cart::create(['user_id' => $user->id, 'lines' => [['key' => 'a', 'menu_item_id' => 1, 'item_variant_id' => null, 'modifier_ids' => [], 'quantity' => 2, 'notes' => null]]]);
        $cart->forceFill(['updated_at' => now()->subHours($hoursAgo)->toDateTimeString()])->save();

        return $cart;
    }

    it('emails a signed-in customer once about a basket untouched for two hours', function () {
        Notification::fake();
        $user = User::factory()->create();
        $cart = custBasket($user);

        $this->artisan('baskets:remind')->assertSuccessful();
        $this->artisan('baskets:remind')->assertSuccessful();

        Notification::assertSentToTimes($user, AbandonedBasket::class, 1);
        expect($cart->fresh()->reminded_at)->not->toBeNull()
            ->and(ActivityLog::where('event', 'automation.basket.reminded')->count())->toBe(1);
    });

    it('leaves recent, empty, guest, stale and already-ordered baskets alone', function () {
        Notification::fake();
        custBasket(User::factory()->create(), 1);
        custBasket(User::factory()->create(), 72);
        Cart::create(['session_id' => 'guest-token', 'lines' => [['quantity' => 1]]])->forceFill(['updated_at' => now()->subHours(3)->toDateTimeString()])->save();
        $empty = User::factory()->create(['marketing_opt_in' => true]);
        Cart::create(['user_id' => $empty->id, 'lines' => []])->forceFill(['updated_at' => now()->subHours(3)->toDateTimeString()])->save();
        $ordered = User::factory()->create();
        custBasket($ordered);
        Order::factory()->pickup()->forCustomer($ordered)->create(['placed_at' => now()->subHour()]);

        $this->artisan('baskets:remind')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('only emails customers who opted in to marketing', function () {
        Notification::fake();
        $optedOut = User::factory()->create();
        $cart = custBasket($optedOut, 3, false);

        $this->artisan('baskets:remind')->assertSuccessful();

        Notification::assertNothingSent();
        expect($cart->fresh()->reminded_at)->toBeNull();
    });

    it('does nothing when the switch is off', function () {
        Notification::fake();
        custSwitchOff('abandoned_cart');
        $cart = custBasket(User::factory()->create());

        $this->artisan('baskets:remind')->assertSuccessful();

        Notification::assertNothingSent();
        expect($cart->fresh()->reminded_at)->toBeNull();
    });

    it('starts the clock again whenever the basket changes', function () {
        $user = User::factory()->create();
        $item = MenuItem::factory()->create(['is_available' => true]);

        $this->actingAs($user)->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => 1]);

        expect(Cart::where('user_id', $user->id)->value('updated_at'))->not->toBeNull();
    });
});
