<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $menu_item_id
 * @property int|null $item_variant_id
 * @property int $old_price
 * @property int $new_price
 * @property int|null $user_id
 */
#[Fillable(['menu_item_id', 'item_variant_id', 'old_price', 'new_price', 'user_id'])]
class PriceChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
