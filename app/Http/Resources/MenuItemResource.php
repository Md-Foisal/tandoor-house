<?php

namespace App\Http\Resources;

use App\Enums\Allergen;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->relationLoaded('variants') ? $this->variants : null;
        $groups = $this->relationLoaded('modifierGroups') ? $this->modifierGroups : null;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $variants?->min('price') ?? $this->price,
            'kcal' => $this->kcal,
            'spice_level' => $this->spice_level,
            'is_vegetarian' => $this->is_vegetarian,
            'is_vegan' => $this->is_vegan,
            'is_available' => $this->is_available,
            'is_special' => $this->is_special,
            'is_hot' => $this->is_hot,
            'is_alcoholic' => $this->is_alcoholic,
            'allergens' => $this->allergens->map(fn (Allergen $allergen) => $allergen->value)->values(),
            'option_allergens' => $this->whenLoaded('modifierGroups', fn () => $this->modifierGroups
                ->flatMap(fn (ModifierGroup $group) => $group->modifiers)
                ->flatMap(fn (Modifier $modifier) => $modifier->allergens ?? [])
                ->map(fn (Allergen $allergen) => $allergen->value)
                ->diff($this->allergens->map(fn (Allergen $allergen) => $allergen->value))
                ->unique()
                ->values(), []),
            'image' => 'dish/'.$this->slug,
            'has_options' => ($variants?->isNotEmpty() ?? false) || ($groups?->isNotEmpty() ?? false),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn (ItemVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'price' => $variant->price,
                'kcal' => $variant->kcal,
            ])),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => $this->modifierGroups->map(fn (ModifierGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'min' => $group->min_select,
                'max' => $group->max_select,
                'modifiers' => $group->modifiers->map(fn (Modifier $modifier) => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'price' => $modifier->price,
                    'allergens' => $modifier->allergens?->map(fn (Allergen $allergen) => $allergen->value)->values() ?? [],
                ]),
            ])),
        ];
    }
}
