<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Events\TableRequestRaised;
use App\Events\TableStatusChanged;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\TableBillPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What a table still owes, and how card payments from the table settle it.
 *
 * A visit's orders stay as they are; each paid share is allocated to them
 * oldest first in `table_bill_payment_orders`, and an order turns paid when
 * its bill (total less any tip added by a share) is covered. Shares that are
 * still being paid hold their part of the bill back for a while so two
 * guests cannot both claim the same pounds.
 */
class TableBill
{
    /** How long a payment that was started but not finished keeps its part of the bill reserved. */
    public const HOLD_MINUTES = 15;

    /**
     * Orders for the table (and any tables merged into it) placed today that
     * are not yet paid or closed: the current visit.
     *
     * @return Collection<int, Order>
     */
    public function visit(DiningTable $lead, bool $lock = false): Collection
    {
        $ids = DiningTable::query()->where('merged_into_id', $lead->id)->pluck('id')->push($lead->id);

        $query = Order::query()
            ->whereIn('dining_table_id', $ids)
            ->where('placed_at', '>=', now('Europe/London')->startOfDay())
            ->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->with('lines')
            ->orderBy('placed_at')
            ->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     *                                          gross is the visit's bill, settled what finished payments cover, remaining the rest,
     *                                          due the remaining less what payments in progress hold, service the service charge inside due.
     * @return array{gross: int, settled: int, remaining: int, due: int, service: int, service_gross: int}
     */
    public function position(DiningTable $lead, Collection $orders): array
    {
        $allocated = $this->allocated($orders);
        $gross = $remaining = $service = $serviceGross = 0;

        foreach ($orders as $order) {
            $bill = $this->billOf($order);
            $left = max(0, $bill - ($allocated[$order->id] ?? 0));

            $gross += $bill;
            $remaining += $left;
            $serviceGross += $order->service_charge;
            $service += $bill > 0 ? intdiv($order->service_charge * $left, $bill) : 0;
        }

        $due = max(0, $remaining - $this->held($lead));

        return [
            'gross' => $gross,
            'settled' => $gross - $remaining,
            'remaining' => $remaining,
            'due' => $due,
            'service' => $remaining > 0 ? intdiv($service * $due, $remaining) : 0,
            'service_gross' => $serviceGross,
        ];
    }

    /**
     * The most a guest can pay now: everything due, less the service charge
     * if they are leaving it off.
     *
     * @param  array{due: int, service: int}  $position
     */
    public function payable(array $position, bool $withService): int
    {
        return $withService ? $position['due'] : $position['due'] - $position['service'];
    }

    /**
     * Turns a request into amounts, or refuses it. The browser says whole or
     * how much; the limit is always worked out here.
     *
     * @param  array{due: int, service: int}  $position
     * @return array{amount: int, waived: int}
     */
    public function quote(array $position, bool $withService, bool $whole, ?int $amount): array
    {
        $max = $this->payable($position, $withService);

        if ($max < 1) {
            throw ValidationException::withMessages(['amount' => __('table.pay.errors.nothing_left')]);
        }

        $amount = $whole ? $max : (int) $amount;

        if ($amount < 1 || $amount > $max) {
            throw ValidationException::withMessages([
                'amount' => __('table.pay.errors.too_much', ['max' => '£'.number_format($max / 100, 2)]),
            ]);
        }

        $waived = $withService ? 0 : ($amount === $max ? $position['service'] : intdiv($amount * $position['service'], $max));

        return ['amount' => $amount, 'waived' => $waived];
    }

    /**
     * Called when the payment lands, with the payment locked. Re-reads the
     * orders under lock, so shares that land together still add up.
     */
    public function allocate(TableBillPayment $payment): void
    {
        $table = DiningTable::query()->lockForUpdate()->findOrFail($payment->dining_table_id);
        $orders = $this->visit($table, lock: true);
        $allocated = $this->allocated($orders);
        $toSettle = $payment->amount + $payment->waived;
        $tipPlaced = $payment->tip === 0;

        foreach ($orders as $order) {
            $bill = $this->billOf($order);
            $room = max(0, $bill - ($allocated[$order->id] ?? 0));
            $take = min($toSettle, $room);

            if ($take > 0) {
                DB::table('table_bill_payment_orders')->insert([
                    'table_bill_payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'amount' => $take,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $toSettle -= $take;
                $allocated[$order->id] = ($allocated[$order->id] ?? 0) + $take;

                if (! $tipPlaced) {
                    $order->forceFill(['tip' => $order->tip + $payment->tip, 'total' => $order->total + $payment->tip]);
                    $tipPlaced = true;
                }
            }

            if (($allocated[$order->id] ?? 0) >= $bill) {
                $order->forceFill(['payment_status' => PaymentStatus::Paid, 'payment_method' => PaymentMethod::Card]);
            }

            if ($order->isDirty()) {
                $order->save();
            }
        }

        $this->tellTheFloor($table, $this->visit($table)->isEmpty());
    }

    /**
     * The part of a landed share the table no longer owed (others paid it
     * first); Payments refunds it.
     */
    public function unallocated(TableBillPayment $payment): int
    {
        $allocated = (int) DB::table('table_bill_payment_orders')
            ->where('table_bill_payment_id', $payment->id)
            ->sum('amount');

        return max(0, $payment->amount + $payment->waived - $allocated);
    }

    /**
     * Shares started but not finished within the hold: they no longer reserve
     * any of the bill, so their card payments must not be completed later.
     *
     * @return Collection<int, TableBillPayment>
     */
    public function lapsed(DiningTable $lead): Collection
    {
        return TableBillPayment::query()
            ->where('dining_table_id', $lead->id)
            ->where('payment_status', PaymentStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(self::HOLD_MINUTES))
            ->get();
    }

    private function billOf(Order $order): int
    {
        return $order->total - $order->tip;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, int>
     */
    private function allocated(Collection $orders): array
    {
        return DB::table('table_bill_payment_orders')
            ->whereIn('order_id', $orders->pluck('id'))
            ->groupBy('order_id')
            ->selectRaw('order_id, sum(amount) as total')
            ->pluck('total', 'order_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function held(DiningTable $lead): int
    {
        return (int) TableBillPayment::query()
            ->where('dining_table_id', $lead->id)
            ->where('payment_status', PaymentStatus::Pending)
            ->where('created_at', '>=', now()->subMinutes(self::HOLD_MINUTES))
            ->selectRaw('coalesce(sum(amount + waived), 0) as held')
            ->value('held');
    }

    private function tellTheFloor(DiningTable $table, bool $settled): void
    {
        if (! $settled) {
            TableStatusChanged::dispatch($table, $table->status, $table->status);

            return;
        }

        $from = $table->status;

        $bill = $table->requests()->whereNull('handled_at')->where('type', TableRequestType::Bill)->get();

        foreach ($bill as $request) {
            $request->forceFill(['handled_at' => now()])->save();
            TableRequestRaised::dispatch($request);
        }

        $this->markBillRequested($table);
        TableStatusChanged::dispatch($table, $from, $table->status);
    }

    private function markBillRequested(DiningTable $table): void
    {
        if ($table->status === TableStatus::Seated) {
            $table->forceFill(['status' => TableStatus::BillRequested])->save();
        }
    }
}
