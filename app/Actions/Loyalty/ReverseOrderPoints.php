<?php

namespace App\Actions\Loyalty;

use App\Enums\PaymentStatus;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Support\Automation;
use Illuminate\Support\Facades\DB;

/**
 * Takes back what an order earned once it is refunded in full. A partial
 * refund keeps the points. The balance never goes below zero.
 */
class ReverseOrderPoints
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(Order $order): ?LoyaltyTransaction
    {
        if ($order->user_id === null || ! $this->automation->enabled(Automation::LOYALTY)) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $user = User::query()->lockForUpdate()->whereKey($order->user_id)->first();
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->first();

            if ($user === null || $locked === null || $locked->payment_status !== PaymentStatus::Refunded) {
                return null;
            }

            $earned = LoyaltyTransaction::query()
                ->where('order_id', $locked->id)
                ->where('reason', LoyaltyReason::earned($locked))
                ->sum('points');

            $reason = LoyaltyReason::reversed($locked);

            if ($earned < 1 || LoyaltyTransaction::query()->where('order_id', $locked->id)->where('reason', $reason)->exists()) {
                return null;
            }

            $taken = min((int) $earned, $user->loyalty_points);

            $transaction = LoyaltyTransaction::create(['user_id' => $user->id, 'order_id' => $locked->id, 'points' => -$taken, 'reason' => $reason]);
            $user->forceFill(['loyalty_points' => $user->loyalty_points - $taken])->save();

            $this->automation->log('automation.loyalty.reversed', __('automations.customers.loyalty.log_reversed', [
                'points' => $taken,
                'name' => $user->name,
                'number' => $locked->number,
            ]), $locked, ['user_id' => $user->id, 'points' => -$taken]);

            return $transaction;
        });
    }
}
