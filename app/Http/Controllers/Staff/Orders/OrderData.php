<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Staff\OrderBoardPolicy;
use App\Services\OrderStateMachine;
use Illuminate\Support\Str;

/**
 * Shapes orders for the board and the detail page. Callers eager-load
 * lines.menuItem, diningTable and rider.
 */
class OrderData
{
    private const ALLERGY_WORDS = '/allerg|intoleran|coeliac|celiac|anaphyla|\bnuts?\b|peanut|gluten|dairy|lactose|shellfish|sesame|\begg|soy|mustard|sulphite|sulfite/i';

    public function __construct(
        private readonly OrderStateMachine $machine,
        private readonly OrderBoardPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Order $order, User $viewer): array
    {
        $next = array_values(array_filter(
            $this->machine->nextFor($order),
            fn (OrderStatus $to) => $this->policy->canMove($viewer, $order, $to),
        ));
        $primary = collect($next)->first(fn (OrderStatus $s) => ! in_array($s, [OrderStatus::Rejected, OrderStatus::Cancelled], true));
        $noteText = collect([$order->notes, ...$order->lines->pluck('notes')])->filter()->implode(' ');

        return [
            'number' => $order->number,
            'channel' => $order->channel->value,
            'status' => $order->status->value,
            'table' => $order->diningTable?->label,
            'postcode' => $order->postcode,
            'pickup_at' => $order->pickup_at?->toIso8601String(),
            'placed_at' => $order->placed_at->toIso8601String(),
            'first_name' => Str::before(trim($order->contact['name'] ?? ''), ' '),
            'items' => $order->lines->map(fn (OrderLine $line) => [
                'quantity' => $line->quantity,
                'name' => $line->name,
                'variant' => $line->variant_name,
                'modifiers' => array_column($line->modifiers, 'name'),
                'notes' => $line->notes,
            ])->values()->all(),
            'notes' => $order->notes,
            'has_allergy' => $noteText !== '' && preg_match(self::ALLERGY_WORDS, $noteText) === 1,
            'age_check' => $order->age_confirmed || $order->lines->contains(fn (OrderLine $line) => $line->menuItem?->is_alcoholic === true),
            'total' => $order->total,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'rider' => $order->rider?->name,
            'next' => $primary?->value,
            'can_reject' => in_array(OrderStatus::Rejected, $next, true),
            'can_cancel' => in_array(OrderStatus::Cancelled, $next, true),
        ];
    }

    /**
     * Pence taken, and pence already returned, across the order's payments.
     *
     * @return array{paid: int, refunded: int}
     */
    public static function paid(Order $order): array
    {
        if ($order->payments->isEmpty()) {
            $taken = in_array($order->payment_status->value, ['paid', 'partially_refunded', 'refunded'], true);

            return ['paid' => $taken ? $order->total : 0, 'refunded' => 0];
        }

        $payments = $order->payments->filter(fn (Payment $p) => in_array($p->status->value, ['paid', 'partially_refunded', 'refunded'], true));

        return ['paid' => (int) $payments->sum('amount'), 'refunded' => (int) $payments->sum('refunded')];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Order $order, User $viewer): array
    {
        ['paid' => $paid, 'refunded' => $refunded] = self::paid($order);
        $address = $order->address;

        return [
            ...$this->card($order, $viewer),
            'contact' => $order->contact,
            'address' => $address === null ? null : array_filter([
                'line1' => $address['line1'] ?? null,
                'line2' => $address['line2'] ?? null,
                'city' => $address['city'] ?? null,
                'postcode' => $address['postcode'] ?? $order->postcode,
            ]),
            'instructions' => $address['instructions'] ?? null,
            'maps_url' => $address === null ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(implode(', ', array_filter([$address['line1'] ?? null, $address['city'] ?? null, $address['postcode'] ?? $order->postcode]))),
            'rider_id' => $order->rider_id,
            'cash_collected' => $order->cash_collected,
            'rejection_reason' => $order->rejection_reason,
            'lines' => $order->lines->map(fn (OrderLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'variant' => $line->variant_name,
                'modifiers' => $line->modifiers,
                'notes' => $line->notes,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'total' => $line->unit_price * $line->quantity,
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
            'events' => $order->events->map(fn ($event) => [
                'from' => $event->from?->value,
                'to' => $event->to->value,
                'by' => $event->user?->name,
                'at' => $event->created_at->toIso8601String(),
            ])->values()->all(),
            'payments' => $order->payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'method' => $p->method->value,
                'status' => $p->status->value,
                'amount' => $p->amount,
                'refunded' => $p->refunded,
                'reference' => $p->reference,
                'at' => $p->created_at?->toIso8601String(),
            ])->values()->all(),
            'paid' => $paid,
            'refunded' => $refunded,
        ];
    }
}
