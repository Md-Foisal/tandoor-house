<?php

namespace App\Models;

use App\Actions\TableBill;
use App\Enums\PaymentStatus;
use App\Payments\Payable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One guest's card payment towards a table's bill: a whole bill or a share.
 * `amount` is what it takes off the bill, `waived` the service charge the
 * guest chose not to pay on that part, `tip` goes to staff in full. The bill
 * is allocated to the orders only once the payment lands.
 *
 * @property int $id
 * @property int $dining_table_id
 * @property int $amount
 * @property int $waived
 * @property int $tip
 * @property bool $service_included
 * @property PaymentStatus $payment_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['dining_table_id', 'amount', 'waived', 'tip', 'service_included', 'payment_status'])]
class TableBillPayment extends Model implements Payable
{
    protected function casts(): array
    {
        return [
            'service_included' => 'boolean',
            'payment_status' => PaymentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function amountOwed(): int
    {
        return $this->amount + $this->tip;
    }

    public function paymentDescription(): string
    {
        return __('table.pay.describe', ['table' => $this->diningTable->label, 'restaurant' => config('restaurant.name')]);
    }

    public function paymentReference(): string
    {
        return 'TB-'.$this->id;
    }

    public function paymentStatus(): PaymentStatus
    {
        return $this->payment_status;
    }

    public function markPaid(): void
    {
        $this->forceFill(['payment_status' => PaymentStatus::Paid])->save();
        app(TableBill::class)->allocate($this);
    }

    public function markPaymentFailed(): void
    {
        $this->forceFill(['payment_status' => PaymentStatus::Failed])->save();
    }

    public function markRefunded(bool $fully): void
    {
        $this->forceFill(['payment_status' => $fully ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded])->save();
    }
}
