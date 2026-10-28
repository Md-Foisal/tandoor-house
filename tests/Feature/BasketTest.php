<?php

use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    guestSession($this);
    $this->seed(MenuSeeder::class);
    $this->dish = MenuItem::whereDoesntHave('variants')->whereDoesntHave('modifierGroups')
        ->where('is_available', true)->where('is_alcoholic', false)->orderBy('id')->firstOrFail();
});

/**
 * The test client does not keep cookies between requests, so a guest's
 * session cookie is sent by hand to make one visitor across requests.
 */
function guestSession($test): void
{
    $test->withCookie(config('session.cookie'), Str::random(40));
}

function addToBasket($test, MenuItem $item, int $quantity = 1)
{
    return $test->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => $quantity]);
}

it('renders the empty state props for an empty basket', function () {
    $this->get('/basket')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('basket')
            ->where('basket.lines', [])
            ->where('basket.count', 0)
            ->where('basket.total', 0)
            ->has('channels', 3)
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->has('specials')));
});

it('suggests up to three available specials with no options when empty', function () {
    MenuItem::where('is_special', false)->limit(5)->update(['is_special' => true]);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $deferred) => $deferred
            ->has('specials', fn (Assert $specials) => $specials->etc())
            ->where('specials', fn ($specials) => collect($specials)->count() <= 3
                && collect($specials)->every(fn ($item) => $item['is_available'] && ! $item['has_options']))));
});

it('does not defer specials once there is something in the basket', function () {
    addToBasket($this, $this->dish);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('specials', [])
        ->has('basket.lines', 1));
});

it('lists a line with the ids needed to put it back', function () {
    addToBasket($this, $this->dish, 2);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.count', 2)
        ->where('basket.lines.0.menu_item_id', $this->dish->id)
        ->where('basket.lines.0.quantity', 2)
        ->where('basket.lines.0.total', $this->dish->price * 2)
        ->where('basket.lines.0.item_variant_id', null)
        ->where('basket.lines.0.modifier_ids', [])
        ->where('basket.total', $this->dish->price * 2));
});

it('keeps guest and customer baskets separate', function () {
    addToBasket($this, $this->dish);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page->where('basket.count', 1));

    $user = User::factory()->create();
    Cart::create(['user_id' => $user->id, 'lines' => [[
        'key' => 'abc123abc123',
        'menu_item_id' => $this->dish->id,
        'item_variant_id' => null,
        'modifier_ids' => [],
        'quantity' => 3,
        'notes' => null,
    ]]]);

    $this->actingAs($user)->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.count', 3)
        ->where('basket.lines.0.key', 'abc123abc123'));

    expect(Cart::whereNotNull('session_id')->firstOrFail()->lines[0]['quantity'])->toBe(1);
});

it('reflects quantity updates and removal in the page props', function () {
    addToBasket($this, $this->dish);
    $key = Cart::firstOrFail()->lines[0]['key'];

    $this->patch("/basket/lines/{$key}", ['quantity' => 4])->assertRedirect();
    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.lines.0.quantity', 4)
        ->where('basket.subtotal', $this->dish->price * 4));

    $this->delete("/basket/lines/{$key}")->assertRedirect();
    $this->get('/basket')->assertInertia(fn (Assert $page) => $page->where('basket.lines', []));
});

it('rejects a quantity above the limit', function () {
    addToBasket($this, $this->dish);
    $key = Cart::firstOrFail()->lines[0]['key'];

    $this->patch("/basket/lines/{$key}", ['quantity' => 99])->assertSessionHasErrors('quantity');
});

it('flags a line that has sold out since it was added', function () {
    addToBasket($this, $this->dish);
    $this->dish->update(['is_available' => false]);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.has_unavailable', true)
        ->where('basket.lines.0.is_available', false));
});

it('reads prices from the menu, not from the stored line', function () {
    addToBasket($this, $this->dish, 2);
    $this->dish->update(['price' => $this->dish->price + 100]);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.lines.0.unit_price', $this->dish->price)
        ->where('basket.total', $this->dish->price * 2));
});

it('prices a sized dish from the chosen variant and keeps its id for undo', function () {
    $variant = ItemVariant::with('menuItem')->firstOrFail();
    $this->post('/basket/lines', [
        'menu_item_id' => $variant->menu_item_id,
        'item_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertSessionHasNoErrors();

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.lines.0.variant', $variant->name)
        ->where('basket.lines.0.item_variant_id', $variant->id)
        ->where('basket.lines.0.unit_price', $variant->price));
});

it('shows the applied promo code and its discount', function () {
    PromoCode::factory()->create(['code' => 'SAVE10', 'percent_off' => 1000]);
    addToBasket($this, $this->dish, 2);
    app(CartService::class)->applyPromo('SAVE10');

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('basket.promo_code', 'SAVE10')
        ->where('basket.discount', intdiv($this->dish->price * 2 * 1000, 10000)));
});

it('offers dine-in only when the basket has a table', function () {
    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('channels', fn ($channels) => collect($channels)->firstWhere('value', 'dine_in')['available'] === false));
});

it('gives the lowest delivery fee and minimum from active zones', function () {
    DeliveryZone::factory()->create(['fee' => 349, 'minimum_order' => 2000]);
    DeliveryZone::factory()->create(['fee' => 249, 'minimum_order' => 2500]);
    DeliveryZone::factory()->inactive()->create(['fee' => 99, 'minimum_order' => 100]);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page
        ->where('delivery.from_fee', 249)
        ->where('delivery.minimum_order', 2000));
});

it('flags alcohol in the basket', function () {
    $wine = MenuItem::where('is_alcoholic', true)->where('is_available', true)->first();
    $wine ??= tap($this->dish)->update(['is_alcoholic' => true]);
    $variant = $wine->variants()->first();
    $this->post('/basket/lines', ['menu_item_id' => $wine->id, 'item_variant_id' => $variant?->id, 'quantity' => 1]);

    $this->get('/basket')->assertInertia(fn (Assert $page) => $page->where('basket.has_alcohol', true));
});
