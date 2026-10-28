<?php

use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(MenuSeeder::class));

it('shows every component on the styleguide', function () {
    $this->get('/styleguide')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('styleguide/index')
            ->has('items', 4)
            ->has('items.0', fn (Assert $item) => $item
                ->hasAll(['id', 'slug', 'name', 'price', 'kcal', 'allergens', 'image', 'has_options'])
                ->etc()));
});

it('shows the motion patterns', function () {
    $this->get('/styleguide/motion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('styleguide/motion')->has('items'));
});

it('shares interface strings and restaurant details with every page', function () {
    $this->get('/styleguide')
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', config('restaurant.name'))
            ->where('translations.ui.sold_out', 'Sold out tonight')
            ->where('restaurant.phone', config('restaurant.contact.phone')));
});
