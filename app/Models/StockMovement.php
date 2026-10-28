<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ingredient_id
 * @property StockMovementType $type
 * @property int $quantity
 * @property string|null $reason
 * @property int|null $order_id
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['ingredient_id', 'type', 'quantity', 'reason', 'order_id', 'user_id'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
        ];
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
