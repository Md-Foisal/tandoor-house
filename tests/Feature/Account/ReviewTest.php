<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(MenuSeeder::class);
});

function completedOrderFor(User $user, array $state = []): Order
{
    return Order::factory()->withLines(2)->forCustomer($user)->status(OrderStatus::Completed)->create($state);
}

function reviewPayload(array $overrides = []): array
{
    return [
        'rating' => 5,
        'body' => 'The lamb chops were worth the wait, and the rider found us first time.',
        'author_area' => 'Bow',
        ...$overrides,
    ];
}

it('sends guests to log in', function () {
    $this->get('/account/reviews')->assertRedirect('/login');
});

it('lists orders waiting for a review and the reviews already written', function () {
    $user = User::factory()->create();
    $waiting = completedOrderFor($user);
    $reviewed = completedOrderFor($user);
    Review::factory()->for($user)->for($reviewed)->unpublished()->create(['body' => 'Lovely, thank you.']);
    completedOrderFor($user, ['placed_at' => now()->subDays(61)]);
    Order::factory()->forCustomer($user)->status(OrderStatus::Cooking)->create();
    completedOrderFor(User::factory()->create());

    $this->actingAs($user)->get('/account/reviews')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/reviews')
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->has('waiting', 1)
                ->where('waiting.0.number', $waiting->number)
                ->has('waiting.0.items', 2)
                ->has('reviews', 1)
                ->where('reviews.0.is_published', false)
                ->where('reviews.0.order_number', $reviewed->number)));
});

it('stores a review as unpublished with a first name and last initial', function () {
    $user = User::factory()->create(['name' => 'priya  sharma']);
    $order = completedOrderFor($user);

    $this->actingAs($user)->post("/account/orders/{$order->number}/review", reviewPayload())
        ->assertRedirect('/account/reviews');

    $review = $order->review()->sole();
    expect($review->author_name)->toBe('priya S.')
        ->and($review->author_area)->toBe('Bow')
        ->and($review->rating)->toBe(5)
        ->and($review->is_published)->toBeFalse()
        ->and($review->user_id)->toBe($user->id);
});

it('uses the first name alone when there is no surname', function () {
    $user = User::factory()->create(['name' => 'Madonna']);
    $order = completedOrderFor($user);

    $this->actingAs($user)->post("/account/orders/{$order->number}/review", reviewPayload(['author_area' => '']))->assertRedirect();

    expect($order->review()->sole()->author_name)->toBe('Madonna')
        ->and($order->review()->sole()->author_area)->toBeNull();
});

it('forbids reviewing someone else\'s order', function () {
    $order = completedOrderFor(User::factory()->create());

    $this->actingAs(User::factory()->create())->post("/account/orders/{$order->number}/review", reviewPayload())->assertForbidden();

    expect(Review::count())->toBe(0);
});

it('forbids reviewing an order that is not completed', function () {
    $user = User::factory()->create();
    $order = Order::factory()->forCustomer($user)->status(OrderStatus::Cooking)->create();

    $this->actingAs($user)->post("/account/orders/{$order->number}/review", reviewPayload())->assertForbidden();
});

it('forbids a second review of the same order', function () {
    $user = User::factory()->create();
    $order = completedOrderFor($user);
    Review::factory()->for($user)->for($order)->create();

    $this->actingAs($user)->post("/account/orders/{$order->number}/review", reviewPayload())->assertForbidden();

    expect(Review::count())->toBe(1);
});

it('validates the rating, body length and area', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $order = completedOrderFor($user);

    $this->actingAs($user)->post("/account/orders/{$order->number}/review", reviewPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Review::count())->toBe(0);
})->with([
    'no rating' => [['rating' => ''], 'rating'],
    'rating too high' => [['rating' => 6], 'rating'],
    'rating zero' => [['rating' => 0], 'rating'],
    'body too short' => [['body' => 'Too short'], 'body'],
    'body too long' => [['body' => str_repeat('a', 1001)], 'body'],
    'area too long' => [['author_area' => str_repeat('a', 61)], 'author_area'],
]);

it('redirects guests away from the review endpoint', function () {
    $order = completedOrderFor(User::factory()->create());

    $this->post("/account/orders/{$order->number}/review", reviewPayload())->assertRedirect('/login');
});
