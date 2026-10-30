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
use Illuminate\Support\Facades\Storage;

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
 * @property array{width: int, height: int, placeholder: string, sources: array{avif: list<array{int, string}>, webp: list<array{int, string}>}}|null $photo
 * @property bool $is_available
 * @property bool $is_special
 * @property bool $is_hot
 * @property bool $is_alcoholic
 * @property list<string>|null $service_periods
 * @property int $position
 */
#[Fillable([
    'category_id', 'name', 'slug', 'description', 'price', 'kcal', 'spice_level',
    'is_vegetarian', 'is_vegan', 'allergens', 'vat_rate', 'station', 'prep_minutes',
    'image', 'photo', 'is_available', 'is_special', 'is_hot', 'is_alcoholic', 'service_periods', 'position',
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
            'is_alcoholic' => 'boolean',
            'kcal' => 'integer',
            'vat_rate' => 'integer',
            'allergens' => AsEnumCollection::of(Allergen::class),
            'station' => Station::class,
            'service_periods' => 'array',
            'photo' => 'array',
        ];
    }

    /**
     * Disk paths of every derived file of the uploaded photo.
     *
     * @return list<string>
     */
    public function photoPaths(): array
    {
        $paths = [];

        foreach ($this->photo['sources'] ?? [] as $list) {
            foreach ($list as [, $path]) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * What the front end hands to <Picture>: the converted upload (urls, not
     * disk paths) when there is one, otherwise the static manifest key.
     *
     * @return string|array<string, mixed>
     */
    public function imagePayload(): string|array
    {
        if ($this->photo === null) {
            return 'dish/'.$this->slug;
        }

        $disk = Storage::disk('public');
        $sources = array_map(
            fn (array $list) => array_map(fn (array $source) => [$source[0], $disk->url($source[1])], $list),
            $this->photo['sources'],
        );

        return [
            'width' => $this->photo['width'],
            'height' => $this->photo['height'],
            'placeholder' => $this->photo['placeholder'],
            'sources' => $sources,
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

    /**
     * @return HasMany<RecipeLine, $this>
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }
}
