<?php

namespace App\Actions;

use App\Enums\LineStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\CartService;
use App\Services\OpeningHours;
use App\Services\Pricing;
use App\Support\GuestOrders;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends the table's basket to the kitchen as a dine-in order, paid at the
 * end of the meal. No contact details are needed: the table is the contact.
 */
class PlaceTableOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly Pricing $pricing,
        private readonly GenerateOrderNumber $numbers,
        private readonly GuestOrders $guestOrders,
        private readonly OpeningHours $hours,
    ) {}

    public function handle(DiningTable $table, ?User $user, bool $ageConfirmed = false): Order
    {
        if (! $this->hours->isOpen()) {
            throw ValidationException::withMessages(['basket' => __('table.errors.closed')]);
        }

        $summary = $this->cart->summary();
        $rawLines = collect($this->cart->lines())->keyBy('key');
        $stations = MenuItem::query()
            ->whereIn('id', array_column($summary['lines'], 'menu_item_id'))
            ->pluck('station', 'id');

        $totals = $this->pricing->totals(
            array_map(fn (array $line) => ['total' => $line['total'], 'vat_rate' => $line['vat_rate']], $summary['lines']),
            OrderChannel::DineIn,
        );

        $order = DB::transaction(function () use ($table, $user, $ageConfirmed, $summary, $rawLines, $stations, $totals) {
            $order = Order::create([
                'number' => $this->numbers->handle(),
                'channel' => OrderChannel::DineIn,
                'status' => OrderStatus::Placed,
                'user_id' => $user?->id,
                'dining_table_id' => $table->id,
                'contact' => [
                    'name' => $user->name ?? $table->label,
                    'email' => $user->email ?? '',
                    'phone' => $user->phone ?? '',
                    'marketing_opt_in' => false,
                ],
                ...$totals,
                'payment_status' => PaymentStatus::Pending,
                'payment_method' => PaymentMethod::PayAtVenue,
                'age_confirmed' => $summary['has_alcohol'] && $ageConfirmed,
                'placed_at' => now(),
            ]);

            foreach ($summary['lines'] as $line) {
                $order->lines()->create([
                    'menu_item_id' => $line['menu_item_id'],
                    'item_variant_id' => $rawLines[$line['key']]['item_variant_id'] ?? null,
                    'name' => $line['name'],
                    'variant_name' => $line['variant'],
                    'modifiers' => $line['modifiers'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'vat_rate' => $line['vat_rate'],
                    'station' => $stations[$line['menu_item_id']],
                    'status' => LineStatus::Queued,
                    'notes' => $line['notes'],
                ]);
            }

            $order->events()->create(['from' => null, 'to' => OrderStatus::Placed, 'user_id' => $user?->id]);

            return $order;
        });

        $this->cart->clear();

        if ($user === null) {
            $this->guestOrders->remember($order);
        }

        return $order;
    }
}
