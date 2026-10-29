<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Enums\OrderChannel;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Orders\EditRequest;
use App\Models\Order;
use App\Models\OrderLine;
use App\Policies\Staff\OrderBoardPolicy;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderEditController extends Controller
{
    public function __invoke(EditRequest $request, Order $order, Pricing $pricing): RedirectResponse
    {
        if (! (new OrderBoardPolicy)->canEdit($order)) {
            throw ValidationException::withMessages(['order' => __('staff_orders.edit.too_late')]);
        }

        $order->load(['lines', 'deliveryZone', 'promoCode']);
        /** @var Collection<int, int> $quantities */
        $quantities = collect();

        foreach ((array) $request->input('lines') as $row) {
            $quantities[(int) $row['id']] = (int) $row['quantity'];
        }

        if ($quantities->keys()->diff($order->lines->modelKeys())->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => __('staff_orders.edit.unknown_line')]);
        }

        $kept = $order->lines->filter(fn (OrderLine $line) => ($quantities[$line->id] ?? $line->quantity) > 0);

        if ($kept->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('staff_orders.edit.keep_one')]);
        }

        $priced = $kept->map(fn (OrderLine $line) => [
            'total' => $line->unit_price * ($quantities[$line->id] ?? $line->quantity),
            'vat_rate' => $line->vat_rate,
        ])->values()->all();

        $totals = $pricing->totals(
            $priced,
            $order->channel,
            $order->deliveryZone,
            $order->promoCode,
            $order->tip,
            $order->channel !== OrderChannel::DineIn || $order->service_charge > 0,
        );

        $paidOnline = in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);

        if ($paidOnline && $totals['total'] > $order->total) {
            throw ValidationException::withMessages(['lines' => __('staff_orders.edit.paid_only_down')]);
        }

        $before = $order->total;
        $note = trim((string) $request->input('note'));

        DB::transaction(function () use ($order, $quantities, $totals, $note, $request, $before) {
            foreach ($order->lines as $line) {
                $quantity = $quantities[$line->id] ?? $line->quantity;

                if ($quantity === 0) {
                    $line->delete();
                } elseif ($quantity !== $line->quantity) {
                    $line->forceFill(['quantity' => $quantity])->save();
                }
            }

            $order->forceFill($totals);

            if ($note !== '') {
                $order->notes = trim(($order->notes ? $order->notes."\n" : '').__('staff_orders.edit.note_prefix', ['note' => $note]));
            }

            $order->save();

            OrderActivity::record(
                'order.edited',
                $order,
                $request->user(),
                __('staff_orders.log.edited', [
                    'user' => $request->user()->name,
                    'number' => $order->number,
                    'from' => '£'.number_format($before / 100, 2),
                    'to' => '£'.number_format($totals['total'] / 100, 2),
                ]),
                ['lines' => $quantities->all(), 'note' => $note, 'total_from' => $before, 'total_to' => $totals['total']],
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $paidOnline && $totals['total'] < $before
                ? __('staff_orders.toast.edited_refund', ['amount' => '£'.number_format(($before - $totals['total']) / 100, 2)])
                : __('staff_orders.toast.edited'),
        ]);

        return back();
    }
}
