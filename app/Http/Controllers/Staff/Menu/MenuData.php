<?php

namespace App\Http\Controllers\Staff\Menu;

use App\Enums\Allergen;
use App\Models\Category;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PriceChange;
use Illuminate\Support\Facades\Storage;

class MenuData
{
    /**
     * @return array<string, mixed>
     */
    public static function category(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'service_periods' => $category->service_periods ?? [],
            'position' => $category->position,
            'items_count' => (int) ($category->items_count ?? 0),
        ];
    }

    /**
     * A row for the items list. Expects variants_count and variants_min_price.
     *
     * @return array<string, mixed>
     */
    public static function row(MenuItem $item): array
    {
        $variants = (int) ($item->variants_count ?? 0);

        return [
            'id' => $item->id,
            'category_id' => $item->category_id,
            'slug' => $item->slug,
            'name' => $item->name,
            'price' => $variants > 0 ? (int) $item->getAttribute('variants_min_price') : $item->price,
            'has_variants' => $variants > 0,
            'kcal' => $item->kcal,
            'is_vegetarian' => $item->is_vegetarian,
            'is_vegan' => $item->is_vegan,
            'allergen_count' => $item->allergens->count(),
            'station' => $item->station->value,
            'is_available' => $item->is_available,
            'is_special' => $item->is_special,
            'image' => 'dish/'.$item->slug,
            'photo_url' => self::photoUrl($item),
        ];
    }

    /**
     * Expects variants, modifierGroups.modifiers and priceChanges.user loaded.
     *
     * @return array<string, mixed>
     */
    public static function editor(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'category_id' => $item->category_id,
            'price' => $item->price,
            'vat_rate' => $item->vat_rate,
            'kcal' => $item->kcal,
            'spice_level' => $item->spice_level,
            'is_vegetarian' => $item->is_vegetarian,
            'is_vegan' => $item->is_vegan,
            'allergens' => $item->allergens->map(fn (Allergen $a) => $a->value)->values(),
            'station' => $item->station->value,
            'prep_minutes' => $item->prep_minutes,
            'is_hot' => $item->is_hot,
            'is_alcoholic' => $item->is_alcoholic,
            'is_special' => $item->is_special,
            'is_available' => $item->is_available,
            'service_periods' => $item->service_periods ?? [],
            'image' => 'dish/'.$item->slug,
            'photo_url' => self::photoUrl($item),
            'variants' => $item->variants->map(fn (ItemVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'price' => $v->price,
                'kcal' => $v->kcal,
            ])->values(),
            'modifier_groups' => $item->modifierGroups->map(fn (ModifierGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'min' => $g->min_select,
                'max' => $g->max_select,
                'modifiers' => $g->modifiers->map(fn (Modifier $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price' => $m->price,
                    'allergens' => $m->allergens?->map(fn (Allergen $a) => $a->value)->values() ?? [],
                ])->values(),
            ])->values(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function history(MenuItem $item): array
    {
        return $item->priceChanges
            ->map(fn (PriceChange $change) => [
                'id' => $change->id,
                'variant' => $change->item_variant_id === null
                    ? null
                    : data_get($item->variants->firstWhere('id', $change->item_variant_id), 'name', __('staff_menu.history.removed_variant')),
                'old' => $change->old_price,
                'new' => $change->new_price,
                'by' => $change->user?->name,
                'at' => $change->created_at->timezone(config('app.timezone'))->format('j M Y, H:i'),
            ])
            ->values()
            ->all();
    }

    public static function photoUrl(MenuItem $item): ?string
    {
        return $item->image === null ? null : Storage::disk('public')->url($item->image);
    }
}
