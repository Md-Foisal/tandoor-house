<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property list<array{key: string, menu_item_id: int, item_variant_id: int|null, modifier_ids: list<int>, quantity: int, notes: string|null}> $lines
 * @property string|null $channel
 * @property int|null $dining_table_id
 * @property string|null $promo_code
 * @property CarbonImmutable|null $reminded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'session_id', 'lines', 'channel', 'dining_table_id', 'promo_code', 'reminded_at'])]
class Cart extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }
}
