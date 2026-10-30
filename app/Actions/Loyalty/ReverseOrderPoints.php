<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyKind;
use App\Enums\PaymentStatus;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Support\Automation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Takes back what an order earned once it is refunded in full, and hands
 * back any reward it spent. A partial refund keeps the points. The balance
 * never goes below zero. It ignores the loyalty switch: a refund is a
 * refund whatever the owner has since switched off.
 */
class ReverseOrderPoints
{
    public function __construct(private readonly Automation $automation, private readonly ReturnRedeemedPoints $return) {}

    public function handle(Order $order): ?LoyaltyTransaction
    {
        if ($order->user_id === null) {
            return null;
        }

        $reversal = DB::transaction(function () use ($order) {
            $user = User::query()->lockForUpdate()->whereKey($order->user_id)->first();
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->first();

            if ($user === null || $locked === null || $locked->payment_status !== PaymentStatus::Refunded) {
                return null;
            }

            $earned = LoyaltyTransaction::query()
                ->where('order_id', $locked->id)
                ->where('kind', LoyaltyKind::Earned)
                ->sum('points');

            if ($earned < 1 || LoyaltyTransaction::query()->where('order_id', $locked->id)->where('kind', LoyaltyKind::Reversed)->exists()) {
                return null;
            }

            $taken = min((int) $earned, $user->loyalty_points);

            try {
                $transaction = LoyaltyTransaction::create(['user_id' => $user->id, 'order_id' => $locked->id, 'points' => -$taken, 'kind' => LoyaltyKind::Reversed, 'reason' => LoyaltyReason::reversed($locked)]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            $user->forceFill(['loyalty_points' => $user->loyalty_points - $taken])->save();

            $this->automation->log('automation.loyalty.reversed', __('automations.customers.loyalty.log_reversed', [
                'points' => $taken,
                'name' => $user->name,
                'number' => $locked->number,
            ]), $locked, ['user_id' => $user->id, 'points' => -$taken]);

            return $transaction;
        });

        if ($order->fresh()?->payment_status === PaymentStatus::Refunded) {
            $this->return->handle($order);
        }

        return $reversal;
    }
}
