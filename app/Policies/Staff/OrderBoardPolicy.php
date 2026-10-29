<?php

namespace App\Policies\Staff;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Per-order rules for the order board, on top of the "staff.orders" gate.
 * Not auto-discovered: the board's controllers call it directly.
 */
class OrderBoardPolicy
{
    /** What a cashier may refund on one order, in pence. */
    public const CASHIER_REFUND_LIMIT = 5000;

    /**
     * The most this user may refund on one order in total, or null for no limit.
     */
    public function refundLimit(User $user): ?int
    {
        return match ($user->role) {
            Role::Manager, Role::Owner => null,
            Role::Cashier => self::CASHIER_REFUND_LIMIT,
            default => 0,
        };
    }

    /**
     * @param  int  $alreadyRefunded  pence already refunded on the order
     */
    public function refund(User $user, Order $order, int $amount, int $alreadyRefunded = 0): Response
    {
        $limit = $this->refundLimit($user);

        if ($limit === 0) {
            return Response::deny(__('staff_orders.refund.not_allowed'));
        }

        if ($limit !== null && $alreadyRefunded + $amount > $limit) {
            return Response::deny(__('staff_orders.refund.over_limit', ['limit' => '£'.number_format($limit / 100, 2)]));
        }

        return Response::allow();
    }

    public function canRefund(User $user): bool
    {
        return $this->refundLimit($user) !== 0;
    }

    public function canAssignRider(User $user, Order $order): bool
    {
        return $user->role !== Role::Waiter
            && $order->channel === OrderChannel::Delivery
            && ! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled], true);
    }

    public function canEdit(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Placed, OrderStatus::Accepted], true);
    }

    public function isRefundable(Order $order): bool
    {
        return in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);
    }
}
