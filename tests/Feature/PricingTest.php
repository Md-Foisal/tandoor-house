<?php

use App\Enums\OrderChannel;
use App\Models\DeliveryZone;
use App\Models\PromoCode;
use App\Services\Pricing;

function pricing(): Pricing
{
    return app(Pricing::class);
}

it('takes vat out of vat-inclusive prices', function () {
    $totals = pricing()->totals([['total' => 1200, 'vat_rate' => 2000]], OrderChannel::Pickup);

    expect($totals['vat_total'])->toBe(200)
        ->and($totals['total'])->toBe(1200);
});

it('adds the zone fee to delivery orders only', function () {
    $zone = new DeliveryZone(['fee' => 249]);

    expect(pricing()->totals([['total' => 2000, 'vat_rate' => 2000]], OrderChannel::Delivery, $zone)['total'])->toBe(2249)
        ->and(pricing()->totals([['total' => 2000, 'vat_rate' => 2000]], OrderChannel::Pickup, $zone)['total'])->toBe(2000);
});

it('adds the discretionary service charge to dine-in orders only', function () {
    $totals = pricing()->totals([['total' => 4000, 'vat_rate' => 2000]], OrderChannel::DineIn, tip: 300);

    expect($totals['service_charge'])->toBe(500)
        ->and($totals['total'])->toBe(4800);
});

it('applies a promo only above its minimum order', function () {
    $promo = new PromoCode(['amount_off' => 500, 'minimum_order' => 3000]);

    expect(pricing()->discount(2999, $promo))->toBe(0)
        ->and(pricing()->discount(3000, $promo))->toBe(500)
        ->and(pricing()->discount(4000, new PromoCode(['percent_off' => 1000, 'minimum_order' => 0])))->toBe(400);
});

it('lowers vat in proportion to a discount', function () {
    expect(pricing()->vat([['total' => 1200, 'vat_rate' => 2000]], 1200, 600))->toBe(100);
});
