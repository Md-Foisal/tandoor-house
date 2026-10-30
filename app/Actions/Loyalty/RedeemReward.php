<?php

namespace App\Actions\Loyalty;

use App\Enums\LoyaltyKind;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use App\Support\Automation;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Spends the points for a reward. Call it inside the order transaction: the
 * balance is read again under a row lock, so two checkouts at once cannot
 * spend the same points.
 */
class RedeemReward
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(User $user, Order $order, int $points, int $value): LoyaltyTransaction
    {
        $locked = User::query()->lockForUpdate()->findOrFail($user->id);

        if ($points < 1 || $locked->loyalty_points < $points) {
            throw ValidationException::withMessages(['use_loyalty' => __('checkout.errors.loyalty_points')]);
        }

        // The unique (order, kind) row goes first: a second redemption for the
        // same order fails here and rolls the whole checkout back.
        $transaction = LoyaltyTransaction::create([
            'user_id' => $locked->id,
            'order_id' => $order->id,
            'points' => -$points,
            'kind' => LoyaltyKind::Redeemed,
            'reason' => LoyaltyReason::redeemed($order),
        ]);

        $locked->forceFill(['loyalty_points' => $locked->loyalty_points - $points])->save();

        $this->automation->log('automation.loyalty.redeemed', __('automations.customers.loyalty.log_redeemed', [
            'name' => $locked->name,
            'points' => $points,
            'value' => Money::format($value),
            'number' => $order->number,
        ]), $order, ['user_id' => $locked->id, 'points' => -$points, 'value' => $value]);

        return $transaction;
    }
}
