<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Public writes throttle at the limit they are configured with: the request
 * after the limit is refused, the ones before it are not.
 */

/**
 * @param  Closure(int): TestResponse  $send  the nth request
 */
function assertThrottledAfter(int $limit, Closure $send): void
{
    $request = function (int $attempt) use ($send) {
        $response = $send($attempt);
        keepSession($response);

        return $response;
    };

    foreach (range(1, $limit) as $attempt) {
        expect($request($attempt)->getStatusCode())->not->toBe(429, "request {$attempt} of {$limit} was throttled early");
    }

    $request($limit + 1)->assertStatus(429);
}

/** Limits keyed by session only bite if the browser sends its session cookie back, as a real one does. */
function keepSession(TestResponse $response): void
{
    $cookie = $response->getCookie(config('session.cookie'), false);

    if ($cookie !== null) {
        test()->withCredentials()->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
    }
}

dataset('throttled writes', [
    'add to the basket' => [60, fn () => fn ($t) => $t->postJson('/basket/lines', [])],
    'apply a promo code' => [10, fn () => fn ($t) => $t->postJson('/checkout/promo', ['code' => 'NOPE'])],
    'check a delivery postcode' => [10, fn () => fn ($t) => $t->postJson('/checkout/postcode', ['postcode' => 'SW1A 1AA'])],
    'place an order' => [10, fn () => fn ($t) => $t->postJson('/checkout', [])],
    'confirm an order payment' => [10, function () {
        $order = Order::factory()->pickup()->live()->create();

        return fn ($t) => $t->postJson("/orders/{$order->number}/pay/confirm", []);
    }],
    'choose to pay at the venue' => [10, function () {
        $order = Order::factory()->pickup()->live()->create();

        return fn ($t) => $t->postJson("/orders/{$order->number}/pay/venue", []);
    }],
    'cancel an order' => [10, function () {
        $order = Order::factory()->pickup()->live()->create();

        return fn ($t) => $t->postJson("/orders/{$order->number}/cancel", []);
    }],
    'cancel a booking' => [10, function () {
        $booking = Reservation::factory()->create();

        return fn ($t) => $t->postJson("/book/{$booking->reference}/cancel", []);
    }],
    'confirm a booking deposit' => [10, function () {
        $booking = Reservation::factory()->pendingDeposit()->create();

        return fn ($t) => $t->postJson("/book/{$booking->reference}/pay/confirm", []);
    }],
    'ask for something at a table' => [12, function () {
        $table = DiningTable::factory()->create();

        return fn ($t) => $t->postJson("/t/{$table->qr_token}/requests", []);
    }],
    'order from a table' => [12, function () {
        $table = DiningTable::factory()->create();

        return fn ($t) => $t->postJson("/t/{$table->qr_token}/order", []);
    }],
    'start a table payment' => [12, function () {
        $table = DiningTable::factory()->create();

        return fn ($t) => $t->postJson("/t/{$table->qr_token}/pay", []);
    }],
    'accept a staff invite' => [6, fn () => fn ($t) => $t->postJson('/invite/not-a-real-token', [])],
    'send payment webhooks' => [120, fn () => fn ($t) => $t->postJson('/webhooks/payments/nowhere', [])],
]);

it('refuses the request after the configured limit', function (int $limit, Closure $arrange) {
    $send = $arrange();

    assertThrottledAfter($limit, fn () => $send($this));
})->with('throttled writes');

it('throttles signing in at five tries', function () {
    $user = User::factory()->create(['email' => 'guest@example.test']);

    assertThrottledAfter(5, fn () => $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password']));
});

it('keeps one email\'s lockout from spilling onto another', function () {
    foreach (range(1, 6) as $ignored) {
        $this->postJson('/login', ['email' => 'one@example.test', 'password' => 'wrong-password']);
    }

    expect($this->postJson('/login', ['email' => 'two@example.test', 'password' => 'wrong-password'])->getStatusCode())->not->toBe(429);
});

it('stops a booking spree with a message, not a bare error', function () {
    foreach (range(1, 20) as $ignored) {
        keepSession($this->from('/book')->post('/book', []));
    }

    $this->from('/book')->post('/book', [])
        ->assertRedirect('/book')
        ->assertSessionHasErrors('booking');
});

it('keeps table limits separate per table', function () {
    $one = DiningTable::factory()->create();
    $two = DiningTable::factory()->create();

    foreach (range(1, 13) as $ignored) {
        keepSession($this->postJson("/t/{$one->qr_token}/requests", []));
    }

    expect($this->postJson("/t/{$two->qr_token}/requests", [])->getStatusCode())->not->toBe(429);
});

/**
 * Public writes (no sign-in needed) that carry no throttle today. Adding a
 * throttle removes a row here; adding a public write without one fails.
 */
const UNTHROTTLED_PUBLIC_WRITES = [];

it('has a throttle on every public write route, bar the ones listed', function () {
    $unthrottled = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn ($method) => in_array($method, ['GET', 'HEAD', 'OPTIONS'], true))
            ->map(fn ($method) => [$method, $route]))
        ->reject(fn ($pair) => collect($pair[1]->gatherMiddleware())->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'auth') || str_starts_with($m, 'throttle:'))))
        ->map(fn ($pair) => $pair[0].' '.$pair[1]->uri())
        ->unique()
        ->sort()
        ->values()
        // QUERY/PUT/PATCH/DELETE on the settings redirect and broadcasting auth are not writes of ours.
        ->reject(fn ($row) => str_ends_with($row, ' settings') || str_ends_with($row, ' storage/{path}') || str_ends_with($row, ' broadcasting/auth'))
        ->all();

    expect($unthrottled)->toEqualCanonicalizing(UNTHROTTLED_PUBLIC_WRITES);
});
