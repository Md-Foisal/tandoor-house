<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Jobs\SendReviewRequest;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Schedules the review request for an hour after the order is completed, so
 * the email lands once the meal has been eaten.
 */
class RequestReview implements ShouldQueue
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Completed || ! $this->automation->enabled(Automation::REVIEW_REQUESTS)) {
            return;
        }

        SendReviewRequest::dispatch($event->orderId)->delay(now()->addHour());
    }
}
