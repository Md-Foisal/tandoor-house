<?php

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(MenuSeeder::class);
    $this->customer = User::factory()->create(['role' => Role::Customer]);
});

it('sends guests to sign in', function () {
    $this->get('/account/orders')->assertRedirect('/login');
    $this->post('/account/orders/TH-1/reorder')->assertRedirect('/login');
});

it('lists only the signed-in customer\'s orders, ten to a page', function () {
    Order::factory()->count(12)->forCustomer($this->customer)->withLines(1)->completed()->create();
    Order::factory()->withLines(1)->completed()->create();

    $this->actingAs($this->customer)->get('/account/orders')->assertInertia(fn (Assert $page) => $page
        ->component('account/orders')
        ->where('filter', 'all')
        ->has('orders', 10)
        ->where('pagination.total', 12)
        ->where('pagination.last_page', 2)
        ->where('pagination.prev_url', null)
        ->where('pagination.next_url', fn (string $url) => str_contains($url, 'page=2'))
        ->has('orders.0', fn (Assert $order) => $order->hasAll(['number', 'status', 'channel', 'placed_at', 'total', 'lines'])));

    $this->actingAs($this->customer)->get('/account/orders?page=2')->assertInertia(fn (Assert $page) => $page
        ->has('orders', 2)
        ->where('pagination.from', 11)
        ->where('pagination.next_url', null));
});

it('filters by status and keeps the filter in page links', function () {
    Order::factory()->count(2)->forCustomer($this->customer)->withLines(1)->completed()->create();
    Order::factory()->forCustomer($this->customer)->withLines(1)->live()->create();
    Order::factory()->forCustomer($this->customer)->withLines(1)->cancelled()->create();
    Order::factory()->forCustomer($this->customer)->withLines(1)->rejected()->create();

    $count = fn (string $status) => $this->actingAs($this->customer)
        ->get("/account/orders?status={$status}")
        ->viewData('page')['props']['orders'];

    expect($count('all'))->toHaveCount(5)
        ->and($count('active'))->toHaveCount(1)
        ->and($count('completed'))->toHaveCount(2)
        ->and($count('cancelled'))->toHaveCount(2)
        ->and($count('nonsense'))->toHaveCount(5);

    Order::factory()->count(11)->forCustomer($this->customer)->withLines(1)->completed()->create();

    $this->actingAs($this->customer)->get('/account/orders?status=completed')->assertInertia(fn (Assert $page) => $page
        ->where('pagination.next_url', fn (string $url) => str_contains($url, 'status=completed')));
});

it('stops another customer reordering', function () {
    $order = Order::factory()->forCustomer(User::factory()->create(['role' => Role::Customer]))->withLines(1)->completed()->create();

    $this->actingAs($this->customer)->post("/account/orders/{$order->number}/reorder")->assertForbidden();
});

it('adds available lines to the basket and skips sold-out ones', function () {
    [$curry, $naan, $lassi] = MenuItem::query()->where('is_available', true)->doesntHave('variants')->doesntHave('modifierGroups')->limit(3)->get()->all();
    $lassi->update(['is_available' => false]);

    $order = Order::factory()->forCustomer($this->customer)->completed()->create();
    OrderLine::factory()->for($order)->forItem($curry)->create(['quantity' => 1]);
    OrderLine::factory()->for($order)->forItem($naan)->create(['quantity' => 2]);
    OrderLine::factory()->for($order)->forItem($lassi)->create(['quantity' => 1]);

    $this->actingAs($this->customer)->post("/account/orders/{$order->number}/reorder")
        ->assertRedirect('/basket')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => "Added 3 dishes to your basket. {$lassi->name} is sold out tonight.",
        ]);

    $summary = $this->actingAs($this->customer)->get('/basket')->viewData('page')['props'];
    $cart = app(CartService::class);

    expect(collect($cart->lines())->pluck('menu_item_id')->all())->toEqualCanonicalizing([$curry->id, $naan->id])
        ->and(collect($cart->lines())->sum('quantity'))->toBe(3);
});

it('keeps variants and modifiers that still exist, matching by name', function () {
    $item = MenuItem::query()->where('is_available', true)->has('variants')->first();
    $variant = $item->variants->first();
    $order = Order::factory()->forCustomer($this->customer)->completed()->create();
    OrderLine::factory()->for($order)->forItem($item, $variant)->create(['item_variant_id' => null, 'variant_name' => $variant->name, 'quantity' => 1]);

    $this->actingAs($this->customer)->post("/account/orders/{$order->number}/reorder")->assertRedirect('/basket');

    expect(app(CartService::class)->lines()[0]['item_variant_id'])->toBe($variant->id);

    $withMods = MenuItem::query()->where('is_available', true)->doesntHave('variants')->has('modifierGroups')->first();
    $mod = Modifier::query()->whereIn('modifier_group_id', $withMods->modifierGroups->pluck('id'))->first();
    $second = Order::factory()->forCustomer($this->customer)->completed()->create();
    OrderLine::factory()->for($second)->forItem($withMods)->create([
        'modifiers' => [['name' => $mod->name, 'price' => $mod->price], ['name' => 'Gone modifier', 'price' => 100]],
        'quantity' => 1,
    ]);

    $this->post("/account/orders/{$second->number}/reorder");

    $ids = collect(app(CartService::class)->lines())->firstWhere('menu_item_id', $withMods->id)['modifier_ids'] ?? [];
    expect($ids)->toContain($mod->id);
});

it('goes back with a note when nothing can be added', function () {
    $item = MenuItem::query()->first();
    $item->update(['is_available' => false]);
    $order = Order::factory()->forCustomer($this->customer)->completed()->create();
    OrderLine::factory()->for($order)->forItem($item)->create();

    $this->actingAs($this->customer)->from('/account/orders')->post("/account/orders/{$order->number}/reorder")
        ->assertRedirect('/account/orders')
        ->assertInertiaFlash('toast', ['type' => 'info', 'message' => "{$item->name} is sold out tonight."]);

    expect(app(CartService::class)->lines())->toBe([]);
});
