<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Models\DeliveryZone;
use App\Models\PromoCode;
use App\Support\Settings;

/**
 * Turns priced lines into order totals. Menu prices include VAT, as UK
 * consumer prices must, so VAT is the inclusive portion of each line.
 */
class Pricing
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  array<int, array{total: int, vat_rate: int}>  $lines  line totals in pence (unit × quantity)
     * @return array{subtotal: int, discount: int, delivery_fee: int, service_charge: int, tip: int, vat_total: int, total: int}
     */
    public function totals(
        array $lines,
        OrderChannel $channel,
        ?DeliveryZone $zone = null,
        ?PromoCode $promo = null,
        int $tip = 0,
    ): array {
        $subtotal = array_sum(array_column($lines, 'total'));
        $discount = $this->discount($subtotal, $promo);
        $deliveryFee = $channel === OrderChannel::Delivery ? ($zone->fee ?? 0) : 0;
        $serviceCharge = $channel === OrderChannel::DineIn
            ? intdiv(($subtotal - $discount) * (int) $this->settings->get('service_charge_rate'), 10000)
            : 0;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'service_charge' => $serviceCharge,
            'tip' => $tip,
            'vat_total' => $this->vat($lines, $subtotal, $discount),
            'total' => $subtotal - $discount + $deliveryFee + $serviceCharge + $tip,
        ];
    }

    public function discount(int $subtotal, ?PromoCode $promo): int
    {
        if ($promo === null || $subtotal < $promo->minimum_order) {
            return 0;
        }

        $discount = $promo->percent_off !== null
            ? intdiv($subtotal * $promo->percent_off, 10000)
            : (int) $promo->amount_off;

        return min($discount, $subtotal);
    }

    /**
     * VAT inside VAT-inclusive prices: gross × rate / (1 + rate). A discount
     * reduces each line in proportion, so VAT falls with it.
     *
     * @param  array<int, array{total: int, vat_rate: int}>  $lines
     */
    public function vat(array $lines, int $subtotal, int $discount = 0): int
    {
        if ($subtotal === 0) {
            return 0;
        }

        $vat = 0.0;

        foreach ($lines as $line) {
            $gross = $line['total'] * ($subtotal - $discount) / $subtotal;
            $vat += $gross * $line['vat_rate'] / (10000 + $line['vat_rate']);
        }

        return (int) round($vat);
    }
}
