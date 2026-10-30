<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Payable;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $order_id
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property string $gateway
 * @property string|null $reference
 * @property PaymentMethod $method
 * @property int $amount
 * @property int $refunded
 * @property PaymentStatus $status
 * @property array<string, mixed>|null $meta
 * @property-read Model|null $payable
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['order_id', 'payable_type', 'payable_id', 'gateway', 'reference', 'method', 'amount', 'refunded', 'status', 'meta'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * What this payment is for. Rows written before payments knew about
     * payables only carry order_id.
     */
    public function payableModel(): (Payable&Model)|null
    {
        $relation = $this->payable_type !== null ? 'payable' : 'order';
        $payable = $this->loadMissing($relation)->getRelation($relation);

        return $payable instanceof Payable && $payable instanceof Model ? $payable : null;
    }

    public function remaining(): int
    {
        return $this->amount - $this->refunded;
    }
}
