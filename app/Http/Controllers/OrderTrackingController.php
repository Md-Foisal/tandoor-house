<?php

namespace App\Http\Controllers;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderLine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderTrackingController extends Controller
{
    private const FINISHED = [
        OrderStatus::Delivered,
        OrderStatus::Collected,
        OrderStatus::Served,
        OrderStatus::Completed,
    ];

    private const TERMINAL = [OrderStatus::Rejected, OrderStatus::Cancelled];

    private const FALLBACK_PREP_MINUTES = 15;

    /**
     * Signed-in people who may not see the order get a plain 403. A guest
     * may simply be in another browser, so they get a page that says how to
     * get back to their order (same view, 403 status).
     */
    public function show(Request $request, Order $order): Response|InertiaResponse
    {
        if (! Gate::allows('view', $order)) {
            abort_if($request->user() !== null, 403);

            return Inertia::render('orders/show', [
                'denied' => true,
                'number' => $order->number,
            ])->toResponse($request)->setStatusCode(403);
        }

        $order->load(['lines.menuItem', 'diningTable', 'rider']);

        $user = $request->user();
        $owner = $user !== null && $order->user_id === $user->id;

        return Inertia::render('orders/show', [
            'denied' => false,
            'number' => $order->number,
            'order' => fn () => $this->summary($order),
            'details' => Inertia::defer(fn () => $this->details($order)),
            'can' => [
                'reorder' => $owner && Gate::allows('reorder', $order),
                'review' => $owner && Gate::allows('review', $order),
                'cancel' => $order->status === OrderStatus::Placed && Gate::allows('cancel', $order),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        $channel = $order->channel;
        $terminal = in_array($order->status, self::TERMINAL, true);
        $steps = $this->steps($channel);
        $finished = in_array($order->status, self::FINISHED, true);
        $position = array_search($order->status, $steps, true);
        $estimate = $this->estimate($order);

        return [
            'status' => $order->status->value,
            'channel' => $channel->value,
            'steps' => array_map(fn (OrderStatus $step) => $step->value, $steps),
            'current' => $terminal ? null : ($finished ? count($steps) : ($position === false ? 0 : $position)),
            'active' => ! $terminal && ! $finished,
            'terminal' => $terminal,
            'rejection_reason' => $terminal ? $order->rejection_reason : null,
            'refund' => $terminal ? $this->refund($order) : null,
            'estimated_at' => $estimate->toIso8601String(),
            'estimated_label' => $estimate->timezone('Europe/London')->format('H:i'),
            'rider_name' => $order->status === OrderStatus::OutForDelivery && $order->rider !== null
                ? explode(' ', trim($order->rider->name))[0]
                : null,
            'placed_label' => $order->placed_at->timezone('Europe/London')->format('H:i'),
        ];
    }

    /**
     * @return list<OrderStatus>
     */
    private function steps(OrderChannel $channel): array
    {
        $end = match ($channel) {
            OrderChannel::Delivery => [OrderStatus::OutForDelivery, OrderStatus::Delivered],
            OrderChannel::Pickup => [OrderStatus::Collected],
            OrderChannel::DineIn => [OrderStatus::Served],
        };

        return [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready, ...$end];
    }

    /**
     * The kitchen's estimate, or placed time plus the slowest dish when
     * nobody has set one yet.
     */
    private function estimate(Order $order): CarbonImmutable
    {
        if ($order->estimated_ready_at !== null) {
            return $order->estimated_ready_at;
        }

        $prep = $order->lines
            ->map(fn (OrderLine $line) => $line->menuItem->prep_minutes ?? self::FALLBACK_PREP_MINUTES)
            ->max() ?? self::FALLBACK_PREP_MINUTES;

        return $order->placed_at->addMinutes($prep);
    }

    private function refund(Order $order): ?string
    {
        return match ($order->payment_status) {
            PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => 'refunded',
            PaymentStatus::Paid, PaymentStatus::Authorised => 'pending',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Order $order): array
    {
        return [
            'lines' => $order->lines->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'variant_name' => $line->variant_name,
                'modifiers' => $line->modifiers,
                'notes' => $line->notes,
                'quantity' => $line->quantity,
                'line_total' => $line->unit_price * $line->quantity,
            ])->values()->all(),
            'totals' => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'delivery_fee' => $order->delivery_fee,
                'service_charge' => $order->service_charge,
                'tip' => $order->tip,
                'vat_total' => $order->vat_total,
                'total' => $order->total,
            ],
            'payment' => [
                'method' => $order->payment_method->value,
                'status' => $order->payment_status->value,
            ],
            'address' => $order->channel === OrderChannel::Delivery ? $order->address : null,
            'postcode' => $order->postcode,
            'pickup_label' => $order->channel === OrderChannel::Pickup
                ? $order->pickup_at?->timezone('Europe/London')->format('H:i')
                : null,
            'table' => $order->diningTable?->label,
            'notes' => $order->notes,
        ];
    }
}
