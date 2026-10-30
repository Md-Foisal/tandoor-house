<?php

namespace App\Http\Controllers;

use App\Actions\TableBill;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\Checkout\ConfirmPaymentRequest;
use App\Http\Requests\Table\PayRequest;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\TableBillPayment;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentPageProps;
use App\Payments\Payments;
use App\Payments\StartedPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paying at the table by card: the whole bill or a share of it, plus an
 * optional tip. Anyone holding the table's code can pay, so a payment is
 * only ever reachable through the table it was made for.
 */
class TablePaymentController extends Controller
{
    public function __construct(private readonly Payments $payments, private readonly TableBill $bill) {}

    public function store(PayRequest $request, DiningTable $table): RedirectResponse
    {
        $lead = $this->lead($table);

        $charge = DB::transaction(function () use ($request, $lead) {
            $locked = DiningTable::query()->lockForUpdate()->findOrFail($lead->id);
            $orders = $this->bill->visit($locked, lock: true);
            $service = $request->boolean('service', true);
            $quote = $this->bill->quote(
                $this->bill->position($locked, $orders),
                $service,
                $request->boolean('whole'),
                $request->integer('amount') ?: null,
            );

            return TableBillPayment::query()->create([
                'dining_table_id' => $locked->id,
                'amount' => $quote['amount'],
                'waived' => $quote['waived'],
                'tip' => $request->integer('tip'),
                'service_included' => $service,
                'payment_status' => PaymentStatus::Pending,
            ]);
        });

        return redirect()->route('table.pay.show', [$lead->qr_token, $charge]);
    }

    public function show(DiningTable $table, TableBillPayment $payment): Response|RedirectResponse
    {
        $lead = $this->lead($table);
        $this->authorizeFor($lead, $payment);

        if ($payment->payment_status === PaymentStatus::Paid) {
            return $this->done($lead);
        }

        $declined = $payment->payment_status === PaymentStatus::Failed;

        try {
            $started = $this->current($payment);
        } catch (PaymentException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return redirect()->route('table.bill', $lead->qr_token);
        }

        return Inertia::render('table/pay', [
            'table' => ['token' => $lead->qr_token, 'label' => $lead->label, 'area' => $lead->area],
            'charge' => [
                'amount' => $payment->amount,
                'tip' => $payment->tip,
                'total' => $payment->amountOwed(),
                'service_included' => $payment->service_included,
            ],
            'payment' => PaymentPageProps::for(
                $started,
                route('table.pay.confirm', [$lead->qr_token, $payment]),
                route('table.pay.show', [$lead->qr_token, $payment]),
            ),
            'declined' => $declined ? __('payments.declined') : null,
            'urls' => ['bill' => route('table.bill', $lead->qr_token)],
        ]);
    }

    public function confirm(ConfirmPaymentRequest $request, DiningTable $table, TableBillPayment $payment): JsonResponse
    {
        $lead = $this->lead($table);
        $this->authorizeFor($lead, $payment);

        $row = $payment->payments()->where('reference', $request->string('reference')->toString())->firstOrFail();

        if ($row->gateway === 'fake' && $request->string('token')->toString() === 'tok_decline'
            && $row->status === PaymentStatus::Pending) {
            return $this->answer($lead, 'failed', __('payments.fake.declined'));
        }

        $row = $this->payments->confirm((string) $row->reference);

        return $this->answer($lead, $this->outcome($row), $row->meta['failure'] ?? null);
    }

    private function authorizeFor(DiningTable $lead, TableBillPayment $payment): void
    {
        abort_unless($payment->dining_table_id === $lead->id, 403);
    }

    private function lead(DiningTable $table): DiningTable
    {
        for ($hops = 0; $table->merged_into_id !== null && $hops < 5; $hops++) {
            $table = DiningTable::query()->findOrFail($table->merged_into_id);
        }

        return $table;
    }

    private function done(DiningTable $lead): RedirectResponse
    {
        return redirect()->route('table.bill', $lead->qr_token);
    }

    /**
     * Reuses the waiting payment so reloading does not open a new charge; a
     * declined one starts a fresh attempt and holds its part of the bill again.
     */
    private function current(TableBillPayment $payment): StartedPayment
    {
        $pending = $payment->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();

        if ($pending !== null) {
            $secret = $pending->gateway === 'fake'
                ? $pending->reference.'_secret_test'
                : $this->payments->gateway($pending->gateway)->retrieve($pending)->clientSecret;

            return new StartedPayment($pending, new PaymentAttempt(AttemptStatus::RequiresAction, (string) $pending->reference, $secret));
        }

        if ($payment->payment_status === PaymentStatus::Failed) {
            DB::transaction(function () use ($payment) {
                $locked = TableBillPayment::query()->lockForUpdate()->findOrFail($payment->id);
                $table = DiningTable::query()->lockForUpdate()->findOrFail($locked->dining_table_id);
                $position = $this->bill->position($table, $this->bill->visit($table, lock: true));

                if ($this->bill->payable($position, $locked->service_included) < $locked->amount) {
                    throw new PaymentException(__('table.pay.errors.too_much', ['max' => '£'.number_format($this->bill->payable($position, $locked->service_included) / 100, 2)]));
                }

                $locked->forceFill(['payment_status' => PaymentStatus::Pending, 'created_at' => now()])->save();
            });
            $payment->refresh();
        }

        return $this->payments->start($payment, PaymentMethod::Card);
    }

    private function outcome(Payment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded => 'paid',
            PaymentStatus::Failed => 'failed',
            default => 'pending',
        };
    }

    private function answer(DiningTable $lead, string $status, ?string $message): JsonResponse
    {
        if ($status === 'paid') {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('table.pay.paid_toast')]);
        }

        return response()->json([
            'status' => $status,
            'message' => $status === 'failed' ? ($message ?: __('payments.declined')) : null,
            'redirect' => $status === 'paid' ? route('table.bill', $lead->qr_token) : null,
        ]);
    }
}
