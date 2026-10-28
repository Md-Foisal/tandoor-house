<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property OrderStatus|null $from
 * @property OrderStatus $to
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['from', 'to', 'user_id'])]
class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from' => OrderStatus::class,
            'to' => OrderStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
