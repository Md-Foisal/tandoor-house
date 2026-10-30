<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Payments\Payable;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $number
 * @property OrderChannel $channel
 * @property OrderStatus $status
 * @property int|null $user_id
 * @property int|null $dining_table_id
 * @property int|null $rider_id
 * @property int|null $delivery_zone_id
 * @property int|null $promo_code_id
 * @property array{name: string, email: string, phone: string} $contact
 * @property array<string, string|null>|null $address
 * @property string|null $postcode
 * @property CarbonImmutable|null $pickup_at
 * @property int $subtotal
 * @property int $discount
 * @property int $delivery_fee
 * @property int $service_charge
 * @property int $tip
 * @property int $vat_total
 * @property int $total
 * @property PaymentStatus $payment_status
 * @property PaymentMethod $payment_method
 * @property int|null $cash_collected
 * @property string|null $notes
 * @property string|null $rejection_reason
 * @property bool $age_confirmed
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $estimated_ready_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['number', 'channel', 'status', 'user_id', 'dining_table_id', 'rider_id', 'delivery_zone_id', 'promo_code_id', 'contact', 'address', 'postcode', 'pickup_at', 'subtotal', 'discount', 'delivery_fee', 'service_charge', 'tip', 'vat_total', 'total', 'payment_status', 'payment_method', 'cash_collected', 'notes', 'rejection_reason', 'age_confirmed', 'placed_at', 'accepted_at', 'ready_at', 'completed_at', 'estimated_ready_at'])]
class Order extends Model implements Payable
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'contact' => 'array',
            'address' => 'array',
            'age_confirmed' => 'boolean',
            'pickup_at' => 'datetime',
            'placed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_ready_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<OrderStatusEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function amountOwed(): int
    {
        return $this->total;
    }

    public function paymentDescription(): string
    {
        return __('payments.describe.order', ['number' => $this->number, 'restaurant' => config('restaurant.name')]);
    }

    public function paymentReference(): string
    {
        return $this->number;
    }

    public function paymentStatus(): PaymentStatus
    {
        return $this->payment_status;
    }

    public function markPaid(): void
    {
        $this->forceFill(['payment_status' => PaymentStatus::Paid])->save();
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
