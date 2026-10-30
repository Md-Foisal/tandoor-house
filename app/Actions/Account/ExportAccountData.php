<?php

namespace App\Actions\Account;

use App\Models\Order;
use App\Models\User;
use Generator;

/**
 * The customer's own data for a UK GDPR access or portability request.
 * Yields one top-level section at a time so the response can be streamed.
 * Staff notes, rider and payment-gateway details, tokens and password hashes
 * are never included.
 */
class ExportAccountData
{
    /**
     * @return Generator<string, mixed>
     */
    public function sections(User $user): Generator
    {
        $user = $user->fresh() ?? $user;

        yield 'exported_at' => now()->toIso8601String();

        yield 'profile' => [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'marketing_opt_in' => $user->marketing_opt_in,
            'loyalty_points' => $user->loyalty_points,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        yield 'addresses' => $user->addresses()->orderBy('id')->get()->map(fn ($a) => [
            'label' => $a->label,
            'line1' => $a->line1,
            'line2' => $a->line2,
            'city' => $a->city,
            'postcode' => $a->postcode,
            'instructions' => $a->instructions,
            'is_default' => $a->is_default,
        ])->all();

        yield 'orders' => (function () use ($user) {
            foreach ($user->orders()->with('lines')->orderBy('id')->lazyById(100) as $order) {
                yield $this->order($order);
            }
        })();

        yield 'bookings' => $user->reservations()->orderBy('id')->get()->map(fn ($r) => [
            'reference' => $r->reference,
            'name' => $r->name,
            'email' => $r->email,
            'phone' => $r->phone,
            'party_size' => $r->party_size,
            'starts_at' => $r->starts_at->toIso8601String(),
            'status' => $r->status->value,
            'deposit_pence' => $r->deposit,
            'occasion' => $r->occasion,
        ])->all();

        yield 'reviews' => $user->reviews()->orderBy('id')->get()->map(fn ($r) => [
            'rating' => $r->rating,
            'body' => $r->body,
            'author_name' => $r->author_name,
            'published' => $r->is_published,
            'reply_from_restaurant' => $r->reply,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->all();

        yield 'loyalty_transactions' => $user->loyaltyTransactions()->orderBy('id')->get()->map(fn ($t) => [
            'points' => $t->points,
            'kind' => $t->kind->value,
            'reason' => $t->reason,
            'created_at' => $t->created_at->toIso8601String(),
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function order(Order $order): array
    {
        return [
            'number' => $order->number,
            'channel' => $order->channel->value,
            'status' => $order->status->value,
            'placed_at' => $order->placed_at->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'contact' => $order->contact,
            'address' => $order->address,
            'postcode' => $order->postcode,
            'notes' => $order->notes,
            'payment_method' => $order->payment_method->value,
            'payment_status' => $order->payment_status->value,
            'totals_pence' => [
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'delivery_fee' => $order->delivery_fee,
                'service_charge' => $order->service_charge,
                'tip' => $order->tip,
                'vat' => $order->vat_total,
                'total' => $order->total,
            ],
            'lines' => $order->lines->map(fn ($l) => [
                'name' => $l->name,
                'variant' => $l->variant_name,
                'modifiers' => $l->modifiers,
                'quantity' => $l->quantity,
                'unit_price_pence' => $l->unit_price,
                'notes' => $l->notes,
            ])->all(),
        ];
    }
}
