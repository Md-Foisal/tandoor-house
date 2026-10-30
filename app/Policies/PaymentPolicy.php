<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\Order;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use Illuminate\Auth\Access\Response;

/**
 * Taking and refunding payments at the till. The per-order rules (waiters on
 * dine-in only, the cashier refund limit) live in OrderBoardPolicy.
 */
class PaymentPolicy
{
    public function __construct(private readonly OrderBoardPolicy $board) {}

    public function create(User $user, Order $order): Response
    {
        if (! StaffArea::Orders->allows($user->role)) {
            return Response::deny();
        }

        return $this->board->takePayment($user, $order);
    }

    /** Whether this user may refund at all; the amount is checked against their limit when it is known. */
    public function refund(User $user, Order $order): bool
    {
        return StaffArea::Orders->allows($user->role) && $this->board->canRefund($user);
    }
}
