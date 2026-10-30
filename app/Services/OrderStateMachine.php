<?php

namespace App\Services;

use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Events\KitchenLineUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place an order or a kitchen line changes status. Every change is
 * checked against the allowed paths for the order's channel and recorded as
 * an order_status_events row.
 */
class OrderStateMachine
{
    /**
     * @return list<OrderStatus>
     */
    public function nextFor(Order $order): array
    {
        return match ($order->status) {
            OrderStatus::Placed => [OrderStatus::Accepted, OrderStatus::Rejected, OrderStatus::Cancelled],
            OrderStatus::Accepted => [OrderStatus::Cooking, OrderStatus::Cancelled],
            OrderStatus::Cooking => [OrderStatus::Ready],
            OrderStatus::Ready => [match ($order->channel) {
                OrderChannel::Delivery => OrderStatus::OutForDelivery,
                OrderChannel::Pickup => OrderStatus::Collected,
                OrderChannel::DineIn => OrderStatus::Served,
            }],
            OrderStatus::OutForDelivery => [OrderStatus::Delivered],
            OrderStatus::Served, OrderStatus::Collected, OrderStatus::Delivered => [OrderStatus::Completed],
            OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled => [],
        };
    }

    public function can(Order $order, OrderStatus $to): bool
    {
        return in_array($to, $this->nextFor($order), true);
    }

    public function transition(Order $order, OrderStatus $to, ?User $by = null, ?string $reason = null): Order
    {
        if (in_array($to, [OrderStatus::Rejected, OrderStatus::Cancelled], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('staff.orders.reason_required')]);
        }

        return DB::transaction(function () use ($order, $to, $by, $reason) {
            // Re-read under a row lock so two people pressing buttons at once
            // cannot both move the order on.
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            if (! $this->can($locked, $to)) {
                throw ValidationException::withMessages([
                    'status' => __('staff.orders.invalid_transition', [
                        'from' => __('staff.status.'.$locked->status->value),
                        'to' => __('staff.status.'.$to->value),
                    ]),
                ]);
            }

            $from = $locked->status;
            $now = now();

            $locked->status = $to;
            $locked->accepted_at ??= $to === OrderStatus::Accepted ? $now : null;
            $locked->ready_at = $to === OrderStatus::Ready ? $now : $locked->ready_at;
            $locked->completed_at = $to === OrderStatus::Completed ? $now : $locked->completed_at;
            $locked->rejection_reason = $reason ?? $locked->rejection_reason;
            $locked->save();

            if ($to === OrderStatus::Cooking) {
                $locked->lines()->where('status', LineStatus::Queued)->update([
                    'status' => LineStatus::Cooking,
                    'started_at' => $now,
                ]);
            }

            if ($to === OrderStatus::Ready) {
                $locked->lines()->where('status', '!=', LineStatus::Ready)->update([
                    'status' => LineStatus::Ready,
                    'bumped_at' => $now,
                ]);
            }

            $locked->events()->create(['from' => $from, 'to' => $to, 'user_id' => $by?->id]);

            OrderStatusChanged::dispatch($locked, $from, $to);

            return $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * The kitchen bumps a line when it is plated. When the last line of an
     * order is bumped, the whole order is ready.
     */
    public function bump(OrderLine $line, ?User $by = null): OrderLine
    {
        $order = $line->loadMissing('order')->order;

        if (! in_array($order->status, [OrderStatus::Accepted, OrderStatus::Cooking], true)) {
            throw ValidationException::withMessages(['line' => __('staff.kitchen.not_on_the_pass')]);
        }

        return DB::transaction(function () use ($line, $order, $by) {
            if ($order->status === OrderStatus::Accepted) {
                $this->transition($order, OrderStatus::Cooking, $by);
                $line->refresh();
            }

            $line->forceFill(['status' => LineStatus::Ready, 'bumped_at' => now()])->save();

            KitchenLineUpdated::dispatch($line, $order);

            if (! $order->lines()->where('status', '!=', LineStatus::Ready)->exists()) {
                $this->transition($order->refresh(), OrderStatus::Ready, $by);
            }

            return $line;
        });
    }

    /**
     * Recall sends a bumped line back to the stove, and an order that was
     * ready back to cooking.
     */
    public function recall(OrderLine $line, ?User $by = null): OrderLine
    {
        $order = $line->loadMissing('order')->order;

        if ($line->status !== LineStatus::Ready || ! in_array($order->status, [OrderStatus::Cooking, OrderStatus::Ready], true)) {
            throw ValidationException::withMessages(['line' => __('staff.kitchen.cannot_recall')]);
        }

        return DB::transaction(function () use ($line, $order, $by) {
            $order = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();
            $line = OrderLine::query()->lockForUpdate()->whereKey($line->getKey())->firstOrFail();

            if ($line->status !== LineStatus::Ready || ! in_array($order->status, [OrderStatus::Cooking, OrderStatus::Ready], true)) {
                throw ValidationException::withMessages(['line' => __('staff.kitchen.cannot_recall')]);
            }

            $line->forceFill(['status' => LineStatus::Cooking, 'bumped_at' => null])->save();

            KitchenLineUpdated::dispatch($line, $order);

            if ($order->status === OrderStatus::Ready) {
                $order->forceFill(['status' => OrderStatus::Cooking, 'ready_at' => null])->save();
                $order->events()->create(['from' => OrderStatus::Ready, 'to' => OrderStatus::Cooking, 'user_id' => $by?->id]);

                OrderStatusChanged::dispatch($order, OrderStatus::Ready, OrderStatus::Cooking);
            }

            return $line;
        });
    }
}
