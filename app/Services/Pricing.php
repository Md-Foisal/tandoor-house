<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Models\DeliveryZone;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Automation;
use App\Support\Settings;

/**
 * Turns priced lines into order totals. Menu prices include VAT, as UK
 * consumer prices must, so VAT is the inclusive portion of each line.
 */
class Pricing
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Automation $automation,
    ) {}

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
        bool $serviceCharge = true,
        int $loyaltyDiscount = 0,
    ): array {
        $subtotal = array_sum(array_column($lines, 'total'));
        // A points reward stands in for a promo: one discount per order.
        $discount = $loyaltyDiscount > 0 ? min($loyaltyDiscount, $subtotal) : $this->discount($subtotal, $promo);
        $deliveryFee = $channel === OrderChannel::Delivery ? ($zone->fee ?? 0) : 0;
        $service = $serviceCharge && $channel === OrderChannel::DineIn
            ? intdiv(($subtotal - $discount) * (int) $this->settings->get('service_charge_rate'), 10000)
            : 0;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'service_charge' => $service,
            'tip' => $tip,
            'vat_total' => $this->vat($lines, $subtotal, $discount),
            'total' => $subtotal - $discount + $deliveryFee + $service + $tip,
        ];
    }

    /**
     * The reward a signed-in customer can spend on this basket, or null when
     * they have not got one: loyalty off, a guest, or too few points. A
     * reward only applies to a basket worth at least its value, so no points
     * are spent on money that is not there to take off.
     *
     * @return array{points: int, reward_points: int, reward_value: int, discount: int}|null
     */
    public function loyaltyReward(?User $user, int $subtotal): ?array
    {
        if ($user === null || ! $this->automation->enabled(Automation::LOYALTY)) {
            return null;
        }

        $rewardPoints = max(1, (int) $this->settings->get('loyalty.reward_points', 150));
        $rewardValue = max(0, (int) $this->settings->get('loyalty.reward_value', 1000));

        // Read fresh: the signed-in model may predate the last order.
        $points = (int) User::query()->whereKey($user->id)->value('loyalty_points');

        if ($rewardValue === 0 || $points < $rewardPoints) {
            return null;
        }

        return [
            'points' => $points,
            'reward_points' => $rewardPoints,
            'reward_value' => $rewardValue,
            'discount' => $subtotal >= $rewardValue ? $rewardValue : 0,
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
