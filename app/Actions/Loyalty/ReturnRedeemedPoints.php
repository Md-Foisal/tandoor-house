<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Support\Automation;
use Illuminate\Support\Facades\DB;

/**
 * Gives the points back when an order that used a reward is cancelled or
 * rejected. It ignores the loyalty switch: the points were spent in good
 * faith and are the customer's either way.
 */
class ReturnRedeemedPoints
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(Order $order): ?LoyaltyTransaction
    {
        if ($order->user_id === null) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $user = User::query()->lockForUpdate()->whereKey($order->user_id)->first();

            if ($user === null) {
                return null;
            }

            $spent = (int) -LoyaltyTransaction::query()
                ->where('order_id', $order->id)
                ->where('reason', LoyaltyReason::redeemed($order))
                ->sum('points');

            $reason = LoyaltyReason::returned($order);

            if ($spent < 1 || LoyaltyTransaction::query()->where('order_id', $order->id)->where('reason', $reason)->exists()) {
                return null;
            }

            $transaction = LoyaltyTransaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'points' => $spent, 'reason' => $reason]);
            $user->forceFill(['loyalty_points' => $user->loyalty_points + $spent])->save();

            $this->automation->log('automation.loyalty.returned', __('automations.customers.loyalty.log_returned', [
                'name' => $user->name,
                'points' => $spent,
                'number' => $order->number,
            ]), $order, ['user_id' => $user->id, 'points' => $spent]);

            return $transaction;
        });
    }
}
