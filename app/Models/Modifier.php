<?php

namespace App\Models;

use App\Enums\Allergen;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $modifier_group_id
 * @property string $name
 * @property int $price
 * @property Collection<int, Allergen>|null $allergens
 * @property int $position
 */
#[Fillable(['name', 'price', 'allergens', 'position'])]
class Modifier extends Model
{
    protected function casts(): array
    {
        return [
            'allergens' => AsEnumCollection::of(Allergen::class),
        ];
    }

    /**
     * @return BelongsTo<ModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
