<?php

namespace App\Http\Controllers;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Http\Requests\Checkout\ConfirmPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\PaymentException;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentPageProps;
use App\Payments\Payments;
use App\Payments\StartedPayment;
use App\Support\GuestOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's payment step for an order placed with card. Only the person
 * who placed the order (signed in, or the same guest session) can pay it.
 */
class OrderPaymentController extends Controller
{
    private const OPEN = [PaymentStatus::Pending, PaymentStatus::Failed];

    public function __construct(private readonly Payments $payments, private readonly GuestOrders $guestOrders) {}

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        $this->authorizeCustomer($request->user(), $order);

        $returned = $request->query('payment_intent');

        if (is_string($returned) && $returned !== '') {
            $this->settleReturn($order, $returned);
            $order->refresh();
        }

        if ($this->isDone($order)) {
            return redirect()->route('orders.show', $order->number);
        }

        try {
            $started = $this->current($order);
        } catch (PaymentException $exception) {
            return redirect()->route('orders.show', $order->number)->with('error', $exception->getMessage());
        }

        $declined = $order->payment_status === PaymentStatus::Failed;
        $canVenue = $order->channel !== OrderChannel::Delivery;

        return Inertia::render('orders/pay', [
            'order' => [
                'number' => $order->number,
                'channel' => $order->channel->value,
                'total' => $order->total,
            ],
            'payment' => PaymentPageProps::for(
                $started,
                route('orders.pay.confirm', $order->number),
                route('orders.pay', $order->number),
            ),
            'declined' => $declined ? __('payments.declined') : null,
            'urls' => [
                'tracking' => route('orders.show', $order->number),
                'venue' => $canVenue ? route('orders.pay.venue', $order->number) : null,
            ],
        ]);
    }

    public function confirm(ConfirmPaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorizeCustomer($request->user(), $order);

        $payment = $order->payments()->where('reference', $request->string('reference')->toString())->firstOrFail();

        if ($payment->gateway === 'fake' && $request->string('token')->toString() === 'tok_decline'
            && $payment->status === PaymentStatus::Pending) {
            return $this->answer($order, 'failed', __('payments.fake.declined'));
        }

        $payment = $this->payments->confirm((string) $payment->reference);

        return $this->answer($order, $this->outcome($payment), $payment->meta['failure'] ?? null);
    }

    /**
     * Switch an unpaid card order to paying at the restaurant (not for delivery).
     */
    public function venue(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeCustomer($request->user(), $order);

        abort_if($order->channel === OrderChannel::Delivery, 422);

        $switched = DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($this->isDone($locked) || $locked->payment_status === PaymentStatus::Paid) {
                return false;
            }

            $locked->forceFill(['payment_method' => PaymentMethod::PayAtVenue, 'payment_status' => PaymentStatus::Pending])->save();

            return true;
        });

        if ($switched) {
            $this->payments->start($order->refresh(), PaymentMethod::PayAtVenue);
            OrderPlaced::dispatch($order);
        }

        return redirect()->route('orders.show', $order->number);
    }

    private function authorizeCustomer(?User $user, Order $order): void
    {
        $owns = ($user !== null && $order->user_id === $user->id) || $this->guestOrders->owns($order);

        abort_unless($owns, 403);
    }

    private function isDone(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::Paid
            || ! PaymentPageProps::isOnline($order->payment_method)
            || in_array($order->status, [OrderStatus::Rejected, OrderStatus::Cancelled], true)
            || ! in_array($order->payment_status, self::OPEN, true);
    }

    /**
     * Reuses the waiting payment so reloading the page does not open a new
     * charge; a declined or missing one starts a fresh attempt.
     */
    private function current(Order $order): StartedPayment
    {
        $pending = $order->payments()
            ->where('method', $order->payment_method)
            ->where('gateway', '!=', 'venue')
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            $secret = $pending->gateway === 'fake'
                ? $pending->reference.'_secret_test'
                : $this->payments->gateway($pending->gateway)->retrieve($pending)->clientSecret;

            return new StartedPayment($pending, new PaymentAttempt(AttemptStatus::RequiresAction, (string) $pending->reference, $secret));
        }

        return $this->payments->start($order, $order->payment_method, ['email' => $order->contact['email'] ?? null]);
    }

    private function settleReturn(Order $order, string $reference): void
    {
        $payment = $order->payments()->where('reference', $reference)->first();

        if ($payment !== null) {
            $this->payments->confirm((string) $payment->reference);
        }
    }

    private function outcome(Payment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded => 'paid',
            PaymentStatus::Failed => 'failed',
            default => 'pending',
        };
    }

    private function answer(Order $order, string $status, ?string $message): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $status === 'failed' ? ($message ?: __('payments.declined')) : null,
            'redirect' => $status === 'paid' ? route('orders.show', $order->number) : null,
        ]);
    }
}
