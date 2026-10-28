<?php

namespace Database\Seeders;

use App\Enums\ServicePeriod;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $cuisine = config('restaurant.cuisine');

        /** @var list<array<string, mixed>> $menu */
        $menu = require database_path("data/menus/{$cuisine}.php");

        $periods = [ServicePeriod::Lunch->value, ServicePeriod::Dinner->value];

        foreach ($menu as $categoryPosition => $categoryData) {
            $category = Category::create([
                'name' => $categoryData['name'],
                'slug' => Str::slug($categoryData['name']),
                'description' => $categoryData['description'],
                'service_periods' => $periods,
                'position' => $categoryPosition,
            ]);

            foreach ($categoryData['items'] as $position => $data) {
                $this->createItem($category, $data, $position);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createItem(Category $category, array $data, int $position): void
    {
        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'],
            'price' => $data['price'],
            'kcal' => $data['kcal'],
            'spice_level' => $data['spice'],
            'is_vegetarian' => $data['veg'] ?? false,
            'is_vegan' => $data['vegan'] ?? false,
            'allergens' => $data['allergens'],
            'station' => $data['station'],
            'prep_minutes' => $data['prep'],
            'is_hot' => $data['hot'] ?? true,
            'is_special' => $data['special'] ?? false,
            'is_alcoholic' => $data['alcohol'] ?? false,
            'position' => $position,
        ]);

        foreach ($data['variants'] ?? [] as $variantPosition => [$name, $price, $kcal]) {
            $item->variants()->create([
                'name' => $name,
                'price' => $price,
                'kcal' => $kcal,
                'position' => $variantPosition,
            ]);
        }

        foreach ($data['modifiers'] ?? [] as $groupPosition => [$name, $min, $max, $options]) {
            $group = $item->modifierGroups()->create([
                'name' => $name,
                'min_select' => $min,
                'max_select' => $max,
                'position' => $groupPosition,
            ]);

            foreach ($options as $optionPosition => [$optionName, $price, $allergens]) {
                $group->modifiers()->create([
                    'name' => $optionName,
                    'price' => $price,
                    'allergens' => $allergens,
                    'position' => $optionPosition,
                ]);
            }
        }
    }
}
