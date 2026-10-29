<?php

namespace App\Actions\Menu;

use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PriceChange;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SaveMenuItem
{
    /**
     * Creates or updates a dish with its variants and option groups in one
     * transaction, recording price changes and the activity log.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function handle(array $data, User $by, ?MenuItem $item = null, ?UploadedFile $photo = null): MenuItem
    {
        $creating = $item === null;

        return DB::transaction(function () use ($data, $by, $item, $photo, $creating) {
            $item ??= new MenuItem(['position' => (int) MenuItem::query()->where('category_id', $data['category_id'])->max('position') + 1]);
            $oldPrice = $item->exists ? $item->price : null;
            $oldImage = $item->image;

            $item->fill([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->slug($data, $item),
                'description' => $data['description'],
                'price' => $data['price'],
                'kcal' => $data['kcal'],
                'spice_level' => $data['spice_level'],
                'is_vegan' => (bool) $data['is_vegan'],
                'is_vegetarian' => (bool) $data['is_vegan'] || (bool) $data['is_vegetarian'],
                'allergens' => $data['allergens'] ?? [],
                'vat_rate' => $data['vat_rate'],
                'station' => $data['station'],
                'prep_minutes' => $data['prep_minutes'],
                'is_hot' => (bool) $data['is_hot'],
                'is_alcoholic' => (bool) $data['is_alcoholic'],
                'is_special' => (bool) $data['is_special'],
                'is_available' => (bool) $data['is_available'],
                'service_periods' => ($data['service_periods'] ?? []) === [] ? null : array_values($data['service_periods']),
            ]);

            if ($photo !== null) {
                $item->image = $photo->store('menu', 'public') ?: null;
            }

            $item->save();

            if ($photo !== null && $oldImage !== null) {
                Storage::disk('public')->delete($oldImage);
            }

            if ($oldPrice !== null && $oldPrice !== $item->price) {
                $this->priceChange($item, null, $oldPrice, $item->price, $by);
            }

            $this->syncVariants($item, $data['variants'] ?? [], $by);
            $this->syncGroups($item, $data['modifier_groups'] ?? []);

            RecordMenuChange::record(
                $creating ? 'menu.item.created' : 'menu.item.updated',
                $item,
                $by,
                __($creating ? 'staff_menu.log.item_created' : 'staff_menu.log.item_updated', ['name' => $item->name]),
            );

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function slug(array $data, MenuItem $item): string
    {
        $wanted = Str::slug((string) ($data['slug'] ?? ''));

        if ($wanted !== '') {
            return $wanted;
        }

        if ($item->exists) {
            return $item->slug;
        }

        $base = Str::slug($data['name']) ?: 'dish';
        $slug = $base;

        for ($i = 2; MenuItem::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    private function priceChange(MenuItem $item, ?ItemVariant $variant, int $old, int $new, User $by): void
    {
        PriceChange::create([
            'menu_item_id' => $item->id,
            'item_variant_id' => $variant?->id,
            'old_price' => $old,
            'new_price' => $new,
            'user_id' => $by->id,
        ]);

        RecordMenuChange::record(
            'menu.price.changed',
            $item,
            $by,
            __('staff_menu.log.price_changed', [
                'name' => $variant ? "{$item->name} ({$variant->name})" : $item->name,
                'old' => Money::format($old),
                'new' => Money::format($new),
            ]),
            ['old' => $old, 'new' => $new, 'variant' => $variant?->name],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncVariants(MenuItem $item, array $rows, User $by): void
    {
        $existing = $item->variants()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $position => $row) {
            $variant = isset($row['id']) ? $existing->get($row['id']) : null;
            $oldPrice = $variant?->price;
            $variant ??= new ItemVariant;
            $variant->fill([
                'name' => $row['name'],
                'price' => $row['price'],
                'kcal' => $row['kcal'] ?? null,
                'position' => $position,
            ]);
            $variant->menu_item_id = $item->id;
            $variant->save();
            $keep[] = $variant->id;

            if ($oldPrice !== null && $oldPrice !== $variant->price) {
                $this->priceChange($item, $variant, $oldPrice, $variant->price, $by);
            }
        }

        $item->variants()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncGroups(MenuItem $item, array $rows): void
    {
        $existing = $item->modifierGroups()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $position => $row) {
            $group = isset($row['id']) ? $existing->get($row['id']) : null;
            $group ??= new ModifierGroup;
            $group->fill([
                'name' => $row['name'],
                'min_select' => $row['min'],
                'max_select' => $row['max'],
                'position' => $position,
            ]);
            $group->menu_item_id = $item->id;
            $group->save();
            $keep[] = $group->id;

            $this->syncModifiers($group, $row['modifiers']);
        }

        $item->modifierGroups()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncModifiers(ModifierGroup $group, array $rows): void
    {
        $existing = $group->modifiers()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($rows) as $position => $row) {
            $modifier = isset($row['id']) ? $existing->get($row['id']) : null;
            $modifier ??= new Modifier;
            $modifier->fill([
                'name' => $row['name'],
                'price' => $row['price'],
                'allergens' => $row['allergens'] ?? [],
                'position' => $position,
            ]);
            $modifier->modifier_group_id = $group->id;
            $modifier->save();
            $keep[] = $modifier->id;
        }

        $group->modifiers()->whereNotIn('id', $keep)->delete();
    }
}
