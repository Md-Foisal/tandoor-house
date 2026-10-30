<?php

namespace App\Actions;

use App\Actions\Loyalty\RedeemReward;
use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Payments\PaymentPageProps;
use App\Services\CartService;
use App\Services\Pricing;
use App\Support\GuestOrders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns the basket into a placed order. Input is already validated by
 * PlaceOrderRequest; prices are read fresh from the menu again here.
 */
class PlaceOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly Pricing $pricing,
        private readonly FindDeliveryZone $zones,
        private readonly GenerateOrderNumber $numbers,
        private readonly GuestOrders $guestOrders,
        private readonly RedeemReward $redeem,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $user): Order
    {
        $channel = OrderChannel::from($data['channel']);
        $summary = $this->cart->summary();
        $table = $channel === OrderChannel::DineIn ? DiningTable::query()->whereKey($summary['table'])->first() : null;
        $zone = $channel === OrderChannel::Delivery ? $this->zones->handle($data['postcode']) : null;
        $promo = $summary['promo_code'] !== null ? PromoCode::where('code', $summary['promo_code'])->first() : null;
        $reward = $this->reward($data, $user, $summary['subtotal'], $promo);
        $promo = $reward !== null ? null : $promo;

        $totals = $this->pricing->totals(
            array_map(fn (array $line) => ['total' => $line['total'], 'vat_rate' => $line['vat_rate']], $summary['lines']),
            $channel,
            $zone,
            $promo,
            (int) ($data['tip'] ?? 0),
            ! ($channel === OrderChannel::DineIn && ($data['remove_service_charge'] ?? false)),
            loyaltyDiscount: $reward['discount'] ?? 0,
        );

        $rawLines = collect($this->cart->lines())->keyBy('key');
        $stations = MenuItem::query()
            ->whereIn('id', array_column($summary['lines'], 'menu_item_id'))
            ->pluck('station', 'id');

        $order = DB::transaction(function () use ($data, $user, $channel, $summary, $table, $zone, $promo, $reward, $totals, $rawLines, $stations) {
            if ($totals['discount'] > 0 && $promo !== null) {
                $claimed = PromoCode::query()
                    ->whereKey($promo->id)
                    ->where(fn ($query) => $query->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'))
                    ->increment('uses');

                if ($claimed === 0) {
                    throw ValidationException::withMessages(['code' => __('checkout.errors.promo_used_up')]);
                }
            }

            $order = Order::create([
                'number' => $this->numbers->handle(),
                'channel' => $channel,
                'status' => OrderStatus::Placed,
                'user_id' => $user?->id,
                'dining_table_id' => $table?->id,
                'delivery_zone_id' => $zone?->id,
                'promo_code_id' => $totals['discount'] > 0 ? $promo?->id : null,
                'contact' => [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
                ],
                'address' => $channel === OrderChannel::Delivery ? [
                    'line1' => $data['line1'],
                    'line2' => $data['line2'] ?? null,
                    'city' => $data['city'],
                    'postcode' => $data['postcode'],
                    'instructions' => $data['instructions'] ?? null,
                ] : null,
                'postcode' => $channel === OrderChannel::Delivery ? $data['postcode'] : null,
                'pickup_at' => $channel === OrderChannel::Pickup && ! empty($data['pickup_at'])
                    ? CarbonImmutable::parse($data['pickup_at'])
                    : null,
                ...$totals,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'age_confirmed' => (bool) ($data['age_confirmed'] ?? false),
                'placed_at' => now(),
            ]);

            if ($reward !== null && $user !== null) {
                $this->redeem->handle($user, $order, $reward['reward_points'], $reward['discount']);
            }

            foreach ($summary['lines'] as $line) {
                $order->lines()->create([
                    'menu_item_id' => $line['menu_item_id'],
                    'item_variant_id' => $rawLines[$line['key']]['item_variant_id'] ?? null,
                    'name' => $line['name'],
                    'variant_name' => $line['variant'],
                    'modifiers' => $line['modifiers'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'vat_rate' => $line['vat_rate'],
                    'station' => $stations[$line['menu_item_id']],
                    'status' => LineStatus::Queued,
                    'notes' => $line['notes'],
                ]);
            }

            $order->events()->create(['from' => null, 'to' => OrderStatus::Placed, 'user_id' => $user?->id]);

            // Card orders reach the kitchen once paid (AnnounceOrderPaid).
            if (! PaymentPageProps::isOnline($order->payment_method)) {
                OrderPlaced::dispatch($order);
            }

            return $order;
        });

        $this->cart->clear();

        if ($user === null) {
            $this->guestOrders->remember($order);
        }

        return $order;
    }

    /**
     * The reward being spent, if the customer asked for one. A reward replaces
     * a promo code; the form request refuses both together, and this is the
     * same check again for callers that skip it.
     *
     * @param  array<string, mixed>  $data
     * @return array{points: int, reward_points: int, reward_value: int, discount: int}|null
     */
    private function reward(array $data, ?User $user, int $subtotal, ?PromoCode $promo): ?array
    {
        if (empty($data['use_loyalty'])) {
            return null;
        }

        $reward = $this->pricing->loyaltyReward($user, $subtotal);

        if ($reward === null || $reward['discount'] < 1 || $promo !== null) {
            throw ValidationException::withMessages(['use_loyalty' => __($promo !== null ? 'checkout.errors.loyalty_with_promo' : 'checkout.errors.loyalty_unavailable')]);
        }

        return $reward;
    }
}
