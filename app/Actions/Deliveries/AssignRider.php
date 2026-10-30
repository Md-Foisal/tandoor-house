<?php

namespace App\Actions\Deliveries;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Policies\DeliveryPolicy;
use App\Support\ActivityRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Puts a rider on a delivery, or takes them off. Managers and owners only. */
class AssignRider
{
    public function __construct(private readonly DeliveryPolicy $policy, private readonly ActivityRecorder $activity) {}

    public function __invoke(Order $order, ?User $rider, User $by): Order
    {
        if (! $this->policy->manages($by)) {
            throw new AuthorizationException;
        }

        if ($rider !== null && $rider->role !== Role::Rider) {
            throw ValidationException::withMessages(['rider_id' => __('staff_deliveries.errors.not_a_rider')]);
        }

        return DB::transaction(function () use ($order, $rider, $by) {
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            if ($locked->channel !== OrderChannel::Delivery) {
                throw new AuthorizationException;
            }

            if (! in_array($locked->status, [OrderStatus::Ready, OrderStatus::OutForDelivery], true)) {
                throw ValidationException::withMessages(['rider_id' => __('staff_deliveries.errors.cannot_assign')]);
            }

            $locked->forceFill(['rider_id' => $rider?->id])->save();

            $this->activity->record(
                'order.rider',
                $rider === null
                    ? __('staff_deliveries.log.rider_removed', ['user' => $by->name, 'number' => $locked->number])
                    : __('staff_deliveries.log.rider_set', ['user' => $by->name, 'number' => $locked->number, 'rider' => $rider->name]),
                $locked,
                by: $by,
            );

            return $locked;
        });
    }
}
