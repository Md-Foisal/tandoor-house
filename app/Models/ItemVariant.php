<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $menu_item_id
 * @property string $name
 * @property int $price
 * @property int|null $kcal
 * @property int $position
 */
#[Fillable(['name', 'price', 'kcal', 'position'])]
class ItemVariant extends Model
{
    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
