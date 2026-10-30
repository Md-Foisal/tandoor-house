<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

enum AttemptStatus: string
{
    case Succeeded = 'succeeded';
    case RequiresAction = 'requires_action';
    case Pending = 'pending';
    case Failed = 'failed';

    public function paymentStatus(): PaymentStatus
    {
        return match ($this) {
            self::Succeeded => PaymentStatus::Paid,
            self::Failed => PaymentStatus::Failed,
            self::RequiresAction, self::Pending => PaymentStatus::Pending,
        };
    }
}
