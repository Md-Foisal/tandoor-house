<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyKind;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Support\Automation;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Points for a completed order: points_per_pound for each whole pound of
 * food and drink after any discount. Tips, delivery and service charge earn
 * nothing. Safe to run twice: the ledger row for the order is the guard.
 */
class CreditOrderPoints
{
    public function __construct(private readonly Settings $settings, private readonly Automation $automation) {}

    public function handle(Order $order): ?LoyaltyTransaction
    {
        if ($order->user_id === null || ! $this->automation->enabled(Automation::LOYALTY)) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $user = User::query()->lockForUpdate()->whereKey($order->user_id)->first();
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->first();

            if ($user === null || $locked === null) {
                return null;
            }

            if ($locked->status !== OrderStatus::Completed || $locked->payment_status === PaymentStatus::Refunded) {
                return null;
            }

            if (LoyaltyTransaction::query()->where('order_id', $locked->id)->where('kind', LoyaltyKind::Earned)->exists()) {
                return null;
            }

            $points = intdiv(max(0, $locked->subtotal - $locked->discount), 100) * max(0, (int) $this->settings->get('loyalty.points_per_pound', 1));

            if ($points < 1) {
                return null;
            }

            try {
                $transaction = LoyaltyTransaction::create(['user_id' => $user->id, 'order_id' => $locked->id, 'points' => $points, 'kind' => LoyaltyKind::Earned, 'reason' => LoyaltyReason::earned($locked)]);
            } catch (UniqueConstraintViolationException) {
                return null;
            }

            $user->forceFill(['loyalty_points' => $user->loyalty_points + $points])->save();

            $this->automation->log('automation.loyalty.credited', __('automations.customers.loyalty.log_earned', [
                'points' => $points,
                'name' => $user->name,
                'number' => $locked->number,
            ]), $locked, ['user_id' => $user->id, 'points' => $points, 'spend' => Money::format($locked->subtotal - $locked->discount)]);

            return $transaction;
        });
    }
}
