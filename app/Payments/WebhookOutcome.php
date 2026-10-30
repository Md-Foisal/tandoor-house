<?php

namespace App\Payments;

/**
 * A verified gateway notification about one payment. `amount` is what the
 * gateway says it took, checked against our row before anything is marked.
 */
final readonly class WebhookOutcome
{
    public function __construct(
        public string $reference,
        public AttemptStatus $status,
        public ?int $amount = null,
        public ?string $eventId = null,
        public ?string $message = null,
    ) {}
}
