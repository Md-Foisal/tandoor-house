<?php

namespace App\Actions\Menu;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

class SaveCategory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $by, ?Category $category = null): Category
    {
        $creating = $category === null;
        $category ??= new Category(['position' => (int) Category::query()->max('position') + 1]);

        $category->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'service_periods' => empty($data['service_periods']) ? null : array_values($data['service_periods']),
        ]);

        if ($creating) {
            $category->slug = $this->uniqueSlug($data['name']);
        }

        $category->save();

        RecordMenuChange::record(
            $creating ? 'menu.category.created' : 'menu.category.updated',
            $category,
            $by,
            __($creating ? 'staff_menu.log.category_created' : 'staff_menu.log.category_updated', ['name' => $category->name]),
        );

        return $category;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($i = 2; Category::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }
}
