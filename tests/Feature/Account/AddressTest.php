<?php

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function addressPayload(array $overrides = []): array
{
    return [
        'label' => 'Home',
        'line1' => '12 Roman Road',
        'line2' => 'Flat 3',
        'city' => 'London',
        'postcode' => 'e2 0ps',
        'instructions' => 'Ring twice',
        ...$overrides,
    ];
}

it('sends guests to log in', function () {
    $this->get('/account/addresses')->assertRedirect('/login');
    $this->post('/account/addresses', addressPayload())->assertRedirect('/login');
});

it('lists the customer\'s addresses with the default first and a zone label', function () {
    DeliveryZone::factory()->covering(['E1', 'E2'])->create(['fee' => 249]);
    $user = User::factory()->create();
    Address::factory()->for($user)->inPostcode('E2')->create(['label' => 'Work']);
    Address::factory()->for($user)->default()->inPostcode('E14')->create(['label' => 'Home']);
    Address::factory()->create();

    $this->actingAs($user)->get('/account/addresses')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/addresses')
            ->where('max', 10)
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->has('addresses', 2)
                ->where('addresses.0.label', 'Home')
                ->where('addresses.0.zone', null)
                ->where('addresses.1.label', 'Work')
                ->where('addresses.1.zone.fee', 249)));
});

it('does not let E1 cover E14', function () {
    DeliveryZone::factory()->covering(['E1'])->create();
    $user = User::factory()->create();
    Address::factory()->for($user)->create(['postcode' => 'E14 9XY']);
    Address::factory()->for($user)->create(['postcode' => 'E1 6QR']);

    $this->actingAs($user)->get('/account/addresses')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->where('addresses.0.zone', null)
                ->where('addresses.1.zone.fee', 249)));
});

it('saves an address with the postcode normalised and makes the first one default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload())
        ->assertRedirect('/account/addresses');

    $address = $user->addresses()->sole();
    expect($address->postcode)->toBe('E2 0PS')
        ->and($address->is_default)->toBeTrue()
        ->and($address->instructions)->toBe('Ring twice');
});

it('keeps only one default address', function () {
    $user = User::factory()->create();
    $first = Address::factory()->for($user)->default()->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload(['label' => 'Work', 'is_default' => '1']))
        ->assertRedirect();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($user->addresses()->where('is_default', true)->count())->toBe(1)
        ->and($user->addresses()->where('label', 'Work')->sole()->is_default)->toBeTrue();
});

it('adds a non-default address without moving the default', function () {
    $user = User::factory()->create();
    $first = Address::factory()->for($user)->default()->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload(['label' => 'Work']))->assertRedirect();

    expect($first->fresh()->is_default)->toBeTrue()
        ->and($user->addresses()->where('is_default', true)->count())->toBe(1);
});

it('updates an address and can move the default to it', function () {
    $user = User::factory()->create();
    $old = Address::factory()->for($user)->default()->create();
    $other = Address::factory()->for($user)->create(['label' => 'Work']);

    $this->actingAs($user)->put("/account/addresses/{$other->id}", addressPayload(['label' => 'Office', 'is_default' => true]))
        ->assertRedirect('/account/addresses');

    expect($other->fresh()->label)->toBe('Office')
        ->and($other->fresh()->is_default)->toBeTrue()
        ->and($old->fresh()->is_default)->toBeFalse();
});

it('never leaves the account without a default when the default is edited', function () {
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->default()->create();

    $this->actingAs($user)->put("/account/addresses/{$address->id}", addressPayload(['is_default' => false]))->assertRedirect();

    expect($address->fresh()->is_default)->toBeTrue();
});

it('removes an address and promotes another when the default goes', function () {
    $user = User::factory()->create();
    $default = Address::factory()->for($user)->default()->create();
    $other = Address::factory()->for($user)->create();

    $this->actingAs($user)->delete("/account/addresses/{$default->id}")->assertRedirect('/account/addresses');

    expect(Address::find($default->id))->toBeNull()
        ->and($other->fresh()->is_default)->toBeTrue();
});

it('forbids changing or removing another customer\'s address', function () {
    $user = User::factory()->create();
    $theirs = Address::factory()->default()->create(['line1' => '8 Bow Lane']);

    $this->actingAs($user)->put("/account/addresses/{$theirs->id}", addressPayload())->assertForbidden();
    $this->actingAs($user)->delete("/account/addresses/{$theirs->id}")->assertForbidden();

    expect($theirs->fresh())->not->toBeNull()->and($theirs->fresh()->line1)->not->toBe('12 Roman Road');
});

it('rejects bad postcodes and missing fields', function (string $postcode) {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload(['postcode' => $postcode]))
        ->assertSessionHasErrors('postcode');

    expect($user->addresses()->count())->toBe(0);
})->with(['nonsense', '12345', 'E2', 'ZZZ ZZZ']);

it('requires label, first line and city', function () {
    $this->actingAs(User::factory()->create())
        ->post('/account/addresses', addressPayload(['label' => '', 'line1' => '', 'city' => '']))
        ->assertSessionHasErrors(['label', 'line1', 'city']);
});

it('accepts a postcode without a space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload(['postcode' => 'SW1A1AA']))->assertRedirect();

    expect($user->addresses()->sole()->postcode)->toBe('SW1A 1AA');
});

it('stops at ten addresses', function () {
    $user = User::factory()->create();
    Address::factory()->count(10)->for($user)->create();

    $this->actingAs($user)->post('/account/addresses', addressPayload())
        ->assertSessionHasErrors('label');

    expect($user->addresses()->count())->toBe(10);
});

it('moves the default on update so only one remains', function () {
    $user = User::factory()->create();
    $old = Address::factory()->for($user)->default()->create();
    $other = Address::factory()->for($user)->create();

    $this->actingAs($user)->put("/account/addresses/{$other->id}", addressPayload(['is_default' => '1']))->assertRedirect();

    expect($old->fresh()->is_default)->toBeFalse()
        ->and($user->addresses()->where('is_default', true)->pluck('id')->all())->toBe([$other->id]);
});
