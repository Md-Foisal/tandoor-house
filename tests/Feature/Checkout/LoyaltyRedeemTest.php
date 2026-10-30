<?php

use App\Actions\Loyalty\RedeemReward;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\DeliveryZone;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Services\Pricing;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->seed(MenuSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    DeliveryZone::factory()->covering(['E1'])->create(['minimum_order' => 0, 'fee' => 249]);
});

function rewardBasket(int $quantity = 4): void
{
    $item = MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->where('price', '>=', 500)->firstOrFail();

    test()->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
}

function rewardPayload(array $overrides = []): array
{
    return [
        'channel' => 'delivery',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
        'line1' => '5 Fournier Street',
        'city' => 'London',
        'postcode' => 'E1 6QR',
        'payment_method' => 'card',
        ...$overrides,
    ];
}

function rewardMember(int $points): User
{
    return User::factory()->create(['loyalty_points' => $points]);
}

it('offers the reward at checkout to a signed-in customer with enough points', function () {
    rewardBasket();

    $this->actingAs(rewardMember(200))->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('summary.loyalty.reward_points', 150)
            ->where('summary.loyalty.reward_value', 1000)
            ->where('summary.loyalty.discount', 1000));
});

it('offers nothing to a guest, to someone short of points, or when loyalty is off', function () {
    rewardBasket();

    $this->get(route('checkout.show'))->assertInertia(fn ($page) => $page->where('summary.loyalty', null));
    $this->actingAs(rewardMember(149))->get(route('checkout.show'))->assertInertia(fn ($page) => $page->where('summary.loyalty', null));

    app(Settings::class)->set('automations', ['loyalty' => false]);
    $this->actingAs(rewardMember(500))->get(route('checkout.show'))->assertInertia(fn ($page) => $page->where('summary.loyalty', null));
});

it('takes the reward off the order, lowers VAT and spends the points in one go', function () {
    $user = rewardMember(200);
    rewardBasket();

    $this->actingAs($user)->post(route('checkout.store'), rewardPayload(['use_loyalty' => true]))->assertSessionHasNoErrors();

    $order = Order::query()->latest('id')->firstOrFail();
    $full = app(Pricing::class)->vat(
        $order->lines->map(fn ($line) => ['total' => $line->unit_price * $line->quantity, 'vat_rate' => $line->vat_rate])->all(),
        $order->subtotal,
    );

    expect($order->discount)->toBe(1000)
        ->and($order->promo_code_id)->toBeNull()
        ->and($order->total)->toBe($order->subtotal - 1000 + $order->delivery_fee)
        ->and($order->vat_total)->toBeLessThan($full)
        ->and($user->fresh()->loyalty_points)->toBe(50);

    $row = LoyaltyTransaction::query()->where('order_id', $order->id)->sole();
    expect($row->points)->toBe(-150)->and($row->user_id)->toBe($user->id);
});

it('does not touch points when the customer does not ask for the reward', function () {
    $user = rewardMember(200);
    rewardBasket();

    $this->actingAs($user)->post(route('checkout.store'), rewardPayload())->assertSessionHasNoErrors();

    expect(Order::query()->latest('id')->firstOrFail()->discount)->toBe(0)
        ->and($user->fresh()->loyalty_points)->toBe(200);
});

it('refuses a reward together with a promo code', function () {
    PromoCode::factory()->create(['code' => 'WELCOME', 'amount_off' => 200, 'minimum_order' => 0]);
    $user = rewardMember(200);
    rewardBasket();
    $this->actingAs($user)->post(route('checkout.promo'), ['code' => 'WELCOME'])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('checkout.store'), rewardPayload(['use_loyalty' => true]))
        ->assertSessionHasErrors('use_loyalty');

    expect(Order::query()->count())->toBe(0)->and($user->fresh()->loyalty_points)->toBe(200);
});

it('refuses a reward without the points, or as a guest', function () {
    rewardBasket();

    $this->post(route('checkout.store'), rewardPayload(['use_loyalty' => true]))->assertSessionHasErrors('use_loyalty');

    $this->actingAs(rewardMember(100))->post(route('checkout.store'), rewardPayload(['use_loyalty' => true]))
        ->assertSessionHasErrors('use_loyalty');

    expect(Order::query()->count())->toBe(0);
});

it('never offers more than the food is worth', function () {
    $item = MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->where('price', '<', 1000)->firstOrFail();
    $this->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $user = rewardMember(300);

    $this->actingAs($user)->get(route('checkout.show'))->assertInertia(fn ($page) => $page->where('summary.loyalty.discount', 0));
    $this->actingAs($user)->post(route('checkout.store'), rewardPayload(['use_loyalty' => true, 'channel' => 'pickup', 'payment_method' => 'pay_at_venue']))
        ->assertSessionHasErrors('use_loyalty');

    expect(Order::query()->count())->toBe(0)->and($user->fresh()->loyalty_points)->toBe(300);
});

it('cannot spend the same points twice', function () {
    $user = rewardMember(200);
    $stale = User::find($user->id);

    // A second checkout that read 200 points before the first one spent them.
    $order = Order::factory()->pickup()->forCustomer($user)->create(['subtotal' => 3000]);
    app(RedeemReward::class)->handle($user, $order, 150, 1000);

    expect(fn () => app(RedeemReward::class)->handle($stale, $order, 150, 1000))->toThrow(ValidationException::class)
        ->and($user->fresh()->loyalty_points)->toBe(50)
        ->and(LoyaltyTransaction::count())->toBe(1);
});

it('gives the points back once if the order is cancelled, and again never', function () {
    $user = rewardMember(200);
    rewardBasket();
    $this->actingAs($user)->post(route('checkout.store'), rewardPayload(['use_loyalty' => true]))->assertSessionHasNoErrors();
    $order = Order::query()->latest('id')->firstOrFail();

    app(OrderStateMachine::class)->transition($order, OrderStatus::Cancelled, $user, 'Changed my mind');
    event(new OrderStatusChanged($order->fresh(), OrderStatus::Placed, OrderStatus::Cancelled));

    expect($user->fresh()->loyalty_points)->toBe(200)
        ->and(LoyaltyTransaction::where('order_id', $order->id)->count())->toBe(2);
});
