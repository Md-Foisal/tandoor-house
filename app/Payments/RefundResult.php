<?php

namespace App\Payments;

final readonly class RefundResult
{
    /**
     * @param  'gateway'|'cash'  $via  cash refunds are handed back at the till
     */
    public function __construct(
        public bool $succeeded,
        public ?string $reference = null,
        public string $via = 'gateway',
        public ?string $message = null,
    ) {}
}
