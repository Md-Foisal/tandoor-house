<?php

namespace App\Jobs;

use App\Actions\FindDeliveryZone;
use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\OpeningHours;
use App\Services\OrderStateMachine;
use App\Support\Automation;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a freshly placed order when every house rule is met; otherwise
 * leaves it for a person and records why.
 */
class AutoAcceptOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $orderId) {}

    public function handle(
        Automation $automation,
        OrderStateMachine $machine,
        OpeningHours $hours,
        FindDeliveryZone $zones,
        Settings $settings,
    ): void {
        if (! $automation->enabled(Automation::AUTO_ACCEPT)) {
            return;
        }

        $order = Order::query()->with(['lines.menuItem', 'deliveryZone'])->find($this->orderId);

        // A person may have got there first, or the order was cancelled.
        if ($order === null || $order->status !== OrderStatus::Placed) {
            return;
        }

        $reasons = $this->reasons($order, $hours, $zones, $settings);

        if ($reasons !== []) {
            $automation->log(
                'automation.order.left_for_staff',
                __('automations.orders.left_for_staff', [
                    'number' => $order->number,
                    'reasons' => implode(', ', array_map(fn (string $reason) => __("automations.orders.reasons.{$reason}"), $reasons)),
                ]),
                $order,
                ['reasons' => $reasons],
            );

            return;
        }

        try {
            $machine->transition($order, OrderStatus::Accepted);
        } catch (ValidationException) {
            // Someone accepted or cancelled it between our check and the lock.
            return;
        }

        $automation->log('automation.order.auto_accepted', __('automations.orders.accepted', ['number' => $order->number]), $order);
    }

    /**
     * @return list<string>
     */
    private function reasons(Order $order, OpeningHours $hours, FindDeliveryZone $zones, Settings $settings): array
    {
        $reasons = [];

        if (! $hours->isOpen()) {
            $reasons[] = 'closed';
        }

        if ($this->openLines() >= (int) $settings->get('orders.auto_accept_max_open', 25)) {
            $reasons[] = 'busy';
        }

        if ($order->channel === OrderChannel::Delivery && ! $this->insideZone($order, $zones)) {
            $reasons[] = 'outside_zone';
        }

        if ($this->needsAgeCheck($order)) {
            $reasons[] = 'age_check';
        }

        $online = in_array($order->payment_method, [PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::GooglePay], true);

        if ($online && $order->payment_status !== PaymentStatus::Paid) {
            $reasons[] = 'unpaid';
        }

        if ($order->total > (int) $settings->get('orders.auto_accept_max_total', 15000)) {
            $reasons[] = 'large_order';
        }

        return $reasons;
    }

    private function openLines(): int
    {
        return OrderLine::query()
            ->whereIn('status', [LineStatus::Queued, LineStatus::Cooking])
            ->whereHas('order', fn ($query) => $query->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking]))
            ->count();
    }

    private function insideZone(Order $order, FindDeliveryZone $zones): bool
    {
        $zone = $zones->handle((string) $order->postcode);

        return $zone !== null && $order->deliveryZone?->is_active === true;
    }

    // Alcohol at the door or the counter needs a face-to-face check; at the
    // table the server does it, once the guest has said they are 18.
    private function needsAgeCheck(Order $order): bool
    {
        $hasAlcohol = $order->lines->contains(fn ($line) => $line->menuItem?->is_alcoholic === true);

        if (! $hasAlcohol) {
            return false;
        }

        return $order->channel !== OrderChannel::DineIn || ! $order->age_confirmed;
    }
}
