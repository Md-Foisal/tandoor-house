<?php

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(MenuSeeder::class));

it('renders for a guest without recent orders', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('recentOrders', null)
            ->has('hours', 7)
            ->where('hours.0.isToday', true)
            ->where('directionsUrl', fn (string $url) => str_starts_with($url, 'https://www.google.com/maps/dir/')
                && str_contains($url, urlencode(config('restaurant.contact.address.postcode'))))
            ->has('translations.home.hero.title')
            ->has('openStatus'));
});

it('lists only special dishes and keeps their availability flag', function () {
    MenuItem::query()->update(['is_special' => false]);
    $available = MenuItem::query()->where('is_available', true)->first();
    $soldOut = MenuItem::query()->where('is_available', true)->skip(1)->first();
    $ordinary = MenuItem::query()->where('is_available', true)->skip(2)->first();
    $available->update(['is_special' => true]);
    $soldOut->update(['is_special' => true, 'is_available' => false]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('specials', 2)
        ->where('specials.0.slug', $available->slug)
        ->where('specials.0.is_available', true)
        ->where('specials.1.slug', $soldOut->slug)
        ->where('specials.1.is_available', false)
        ->where('specials', fn ($specials) => collect($specials)->every(fn ($item) => $item['is_special'] === true
            && $item['slug'] !== $ordinary->slug)));
});

it('shows a customer their last three completed orders only', function () {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $other = User::factory()->create(['role' => Role::Customer]);

    foreach (range(1, 4) as $day) {
        Order::factory()->forCustomer($customer)->withLines(2)->completed()->create(['placed_at' => now()->subDays($day)]);
    }
    Order::factory()->forCustomer($customer)->withLines(1)->create();
    Order::factory()->forCustomer($other)->withLines(1)->completed()->create();

    $this->actingAs($customer)->get('/')->assertInertia(fn (Assert $page) => $page
        ->has('recentOrders', 3)
        ->has('recentOrders.0', fn (Assert $order) => $order
            ->hasAll(['number', 'placed_at', 'total', 'lines'])
            ->has('lines', 2)));
});

it('gives a customer with no orders an empty list', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Customer]))
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('recentOrders', []));
});

it('hides quick reorder from staff', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Waiter]))
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('recentOrders', null));
});

it('defers reviews and numbers and resolves them', function () {
    Review::factory()->count(7)->create();
    Review::factory()->unpublished()->create(['body' => 'Hidden review']);
    Review::factory()->rating(1)->unpublished()->create();

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->missing('reviews')
        ->missing('numbers')
        ->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->has('reviews', 6)
            ->where('reviews', fn ($reviews) => collect($reviews)->doesntContain('body', 'Hidden review'))
            ->has('reviews.0', fn (Assert $review) => $review->hasAll(['id', 'author_name', 'author_area', 'rating', 'body']))
            ->where('numbers.reviews', 7)
            ->where('numbers.years', fn (int $years) => $years >= 1)));
});

it('averages published ratings and counts dishes served this year', function () {
    Review::factory()->rating(5)->create();
    Review::factory()->rating(4)->create();
    Review::factory()->rating(1)->unpublished()->create();

    $order = Order::factory()->completed()->create();
    OrderLine::factory()->for($order)->create(['quantity' => 3]);
    OrderLine::factory()->for($order)->create(['quantity' => 2]);
    $old = Order::factory()->completed()->create(['placed_at' => now()->subYears(2)]);
    OrderLine::factory()->for($old)->create(['quantity' => 9]);
    $live = Order::factory()->live()->create();
    OrderLine::factory()->for($live)->create(['quantity' => 4]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('numbers.rating', 4.5)
            ->where('numbers.dishes', 5)));
});

it('has no rating when nobody has reviewed', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $loaded) => $loaded
            ->where('numbers.rating', null)
            ->where('reviews', [])));
});

it('keeps the query count bounded however much data there is', function () {
    $customer = User::factory()->create(['role' => Role::Customer]);
    $load = function () use ($customer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($customer)->get('/')->assertOk();

        return count(DB::getQueryLog());
    };

    MenuItem::query()->limit(1)->update(['is_special' => true]);
    Order::factory()->forCustomer($customer)->withLines(1)->completed()->create();
    $small = $load();

    MenuItem::query()->limit(3)->update(['is_special' => true]);
    Review::factory()->count(10)->create();
    foreach (range(1, 5) as $day) {
        Order::factory()->forCustomer($customer)->withLines(4)->completed()->create(['placed_at' => now()->subDays($day)]);
    }
    $large = $load();

    expect($large)->toBe($small)->and($large)->toBeLessThanOrEqual(14);
});
