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
use App\Support\ActivityRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrderEditController extends Controller
{
    public function __invoke(
        EditRequest $request,
        Order $order,
        Pricing $pricing,
        OrderBoardPolicy $policy,
        RefundOrder $refund,
        ActivityRecorder $activity,
    ): RedirectResponse {
        $user = $request->user();
        $note = trim((string) $request->input('note'));
        /** @var Collection<int, int> $quantities */
        $quantities = collect();

        foreach ((array) $request->input('lines') as $row) {
            $quantities[(int) $row['id']] = (int) $row['quantity'];
        }

        $refunded = 0;

        DB::transaction(function () use ($order, $quantities, $note, $user, $pricing, $policy, $refund, $activity, &$refunded) {
            // Editability, lines and totals are all read again under the lock:
            // the kitchen may have started cooking since the page loaded.
            $locked = Order::query()->lockForUpdate()->whereKey($order->getKey())->firstOrFail();

            if (! $policy->canEdit($locked)) {
                throw ValidationException::withMessages(['order' => __('staff_orders.edit.too_late')]);
            }

            $locked->load(['lines', 'deliveryZone', 'promoCode']);

            if ($quantities->keys()->diff($locked->lines->modelKeys())->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => __('staff_orders.edit.unknown_line')]);
            }

            $kept = $locked->lines->filter(fn (OrderLine $line) => ($quantities[$line->id] ?? $line->quantity) > 0);

            if ($kept->isEmpty()) {
                throw ValidationException::withMessages(['lines' => __('staff_orders.edit.keep_one')]);
            }

            $totals = $pricing->totals(
                $kept->map(fn (OrderLine $line) => [
                    'total' => $line->unit_price * ($quantities[$line->id] ?? $line->quantity),
                    'vat_rate' => $line->vat_rate,
                ])->values()->all(),
                $locked->channel,
                $locked->deliveryZone,
                $locked->promoCode,
                $locked->tip,
                $locked->channel !== OrderChannel::DineIn || $locked->service_charge > 0,
            );

            $paid = in_array($locked->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true);

            if ($paid && $totals['total'] > $locked->total) {
                throw ValidationException::withMessages(['lines' => __('staff_orders.edit.paid_only_down')]);
            }

            $before = $locked->total;

            foreach ($locked->lines as $line) {
                $quantity = $quantities[$line->id] ?? $line->quantity;

                if ($quantity === 0) {
                    $line->delete();
                } elseif ($quantity !== $line->quantity) {
                    $line->forceFill(['quantity' => $quantity])->save();
                }
            }

            $locked->forceFill($totals);

            if ($note !== '') {
                $locked->notes = trim(($locked->notes ? $locked->notes."\n" : '').__('staff_orders.edit.note_prefix', ['note' => $note]));
            }

            $locked->save();

            // A paid order that shrinks owes the guest the difference: it goes
            // through the refund path so it is recorded, limited and logged.
            if ($paid && $totals['total'] < $before) {
                try {
                    $refunded = $refund($locked, 'amount', $before - $totals['total'], [], __('staff_orders.edit.refund_reason', ['number' => $locked->number]), $user);
                } catch (AuthorizationException $e) {
                    throw ValidationException::withMessages(['lines' => $e->getMessage()]);
                }
            }

            $activity->record(
                'order.edited',
                __('staff_orders.log.edited', [
                    'user' => $user->name,
                    'number' => $locked->number,
                    'from' => '£'.number_format($before / 100, 2),
                    'to' => '£'.number_format($totals['total'] / 100, 2),
                ]),
                $locked,
                ['lines' => $quantities->all(), 'note' => $note, 'total_from' => $before, 'total_to' => $totals['total']],
                $user,
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $refunded > 0
                ? __('staff_orders.toast.edited_refund', ['amount' => '£'.number_format($refunded / 100, 2)])
                : __('staff_orders.toast.edited'),
        ]);

        return back();
    }
}
