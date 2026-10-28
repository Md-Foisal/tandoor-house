<?php

use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\MenuSeeder;

beforeEach(fn () => $this->seed(MenuSeeder::class));

function guestCart(string $token, int $itemId, int $quantity): Cart
{
    return Cart::create([
        'session_id' => $token,
        'lines' => [['key' => 'abc123', 'menu_item_id' => $itemId, 'item_variant_id' => null, 'modifier_ids' => [], 'quantity' => $quantity, 'notes' => null]],
    ]);
}

it('keeps the guest basket when the guest signs in', function () {
    $item = MenuItem::where('slug', 'onion-bhaji')->firstOrFail();
    $user = User::factory()->create();
    guestCart('guest-token', $item->id, 2);

    $this->actingAs($user)->withSession(['guest_cart' => 'guest-token'])->get('/basket')->assertOk();

    $cart = Cart::where('user_id', $user->id)->firstOrFail();

    expect($cart->lines)->toHaveCount(1)
        ->and($cart->lines[0]['quantity'])->toBe(2)
        ->and(Cart::where('session_id', 'guest-token')->exists())->toBeFalse();
});

it('adds the guest basket to a basket the customer already had', function () {
    $item = MenuItem::where('slug', 'onion-bhaji')->firstOrFail();
    $user = User::factory()->create();
    Cart::create([
        'user_id' => $user->id,
        'lines' => [['key' => 'abc123', 'menu_item_id' => $item->id, 'item_variant_id' => null, 'modifier_ids' => [], 'quantity' => 1, 'notes' => null]],
    ]);
    guestCart('guest-token', $item->id, 2);

    $this->actingAs($user)->withSession(['guest_cart' => 'guest-token'])->get('/basket')->assertOk();

    expect(Cart::where('user_id', $user->id)->firstOrFail()->lines[0]['quantity'])->toBe(3);
});

it('gives each guest session its own basket', function () {
    $item = MenuItem::where('slug', 'onion-bhaji')->firstOrFail();

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1])->assertRedirect();

    expect(Cart::whereNull('user_id')->count())->toBe(1)
        ->and(strlen((string) Cart::first()->session_id))->toBe(40);
});
