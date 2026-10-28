<?php

namespace App\Models;

use App\Enums\Allergen;
use App\Enums\Station;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property int $price
 * @property int $kcal
 * @property int $spice_level
 * @property bool $is_vegetarian
 * @property bool $is_vegan
 * @property Collection<int, Allergen> $allergens
 * @property int $vat_rate
 * @property Station $station
 * @property int $prep_minutes
 * @property string|null $image
 * @property bool $is_available
 * @property bool $is_special
 * @property bool $is_hot
 * @property list<string>|null $service_periods
 * @property int $position
 */
#[Fillable([
    'category_id', 'name', 'slug', 'description', 'price', 'kcal', 'spice_level',
    'is_vegetarian', 'is_vegan', 'allergens', 'vat_rate', 'station', 'prep_minutes',
    'image', 'is_available', 'is_special', 'is_hot', 'service_periods', 'position',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'is_available' => 'boolean',
            'is_special' => 'boolean',
            'is_hot' => 'boolean',
            'allergens' => AsEnumCollection::of(Allergen::class),
            'station' => Station::class,
            'service_periods' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('position');
    }

    /**
     * @return HasMany<ModifierGroup, $this>
     */
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)->orderBy('position');
    }

    /**
     * @return HasMany<PriceChange, $this>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class)->latest('created_at');
    }
}
