<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Notifications\Customer\ReviewRequest;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Asks a signed-in customer for a review. Guests are skipped because only an
 * account can leave one. Everything is checked again at send time, since an
 * hour is long enough for the order to be refunded or reviewed.
 */
class SendReviewRequest implements ShouldQueue
{
    use Queueable;

    public const EVENT = 'automation.review.requested';

    public function __construct(public readonly int $orderId) {}

    public function handle(Automation $automation): void
    {
        if (! $automation->enabled(Automation::REVIEW_REQUESTS)) {
            return;
        }

        $order = Order::query()->with('user')->find($this->orderId);

        if ($order === null || $order->user === null || $order->status !== OrderStatus::Completed) {
            return;
        }

        if (in_array($order->payment_status, [PaymentStatus::Refunded], true) || $order->review()->exists()) {
            return;
        }

        $already = ActivityLog::query()
            ->where('event', self::EVENT)
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->exists();

        // The cache lock covers two copies of the job running at the same moment.
        if ($already || ! Cache::add("review-request:{$order->id}", true, now()->addDay())) {
            return;
        }

        $order->user->notify(new ReviewRequest($order));

        $automation->log(self::EVENT, __('automations.customers.review_request.log', [
            'name' => $order->user->name,
            'number' => $order->number,
        ]), $order, ['user_id' => $order->user->id]);
    }
}
