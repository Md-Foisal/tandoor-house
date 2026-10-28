<?php

use App\Enums\Allergen;
use App\Models\Category;
use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;

it('seeds a full menu for the configured cuisine', function () {
    $this->seed(MenuSeeder::class);

    expect(Category::count())->toBe(8)
        ->and(MenuItem::count())->toBe(40);
});

it('declares allergens using only the fourteen FSA allergens', function () {
    $this->seed(MenuSeeder::class);

    MenuItem::all()->each(function (MenuItem $item) {
        $item->allergens->each(fn ($allergen) => expect($allergen)->toBeInstanceOf(Allergen::class));
    });

    expect(Allergen::cases())->toHaveCount(14);
});

it('marks every vegan dish as vegetarian too', function () {
    $this->seed(MenuSeeder::class);

    expect(MenuItem::where('is_vegan', true)->where('is_vegetarian', false)->count())->toBe(0);
});
