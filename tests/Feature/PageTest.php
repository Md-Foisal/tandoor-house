<?php

use App\Enums\Allergen;
use App\Models\MenuItem;
use App\Support\Settings;
use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(MenuSeeder::class));

it('renders the story with its numbers', function () {
    $this->get('/story')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('story')
            ->where('numbers.dishes', 40)
            ->where('numbers.opened', 2016)
            ->where('numbers.daal_hours', 18)
            ->where('numbers.years', fn (int $years) => $years >= 1)
            ->has('translations.pages.story.chapters.one.title'));
});

it('renders the visit page with today lit in the hours', function () {
    $this->get('/visit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('visit')
            ->has('hours', 7)
            ->where('hours.0.isToday', true)
            ->where('hours.0.day', now()->translatedFormat('l'))
            ->where('hours', fn ($hours) => collect($hours)->where('isToday', true)->count() === 1)
            ->has('openStatus')
            ->where('maps.google', fn (string $url) => str_starts_with($url, 'https://www.google.com/maps/search/')
                && str_contains($url, rawurlencode(config('restaurant.contact.address.postcode'))))
            ->where('maps.apple', fn (string $url) => str_starts_with($url, 'https://maps.apple.com/'))
            ->where('maxParty', (int) app(Settings::class)->get('reservation.max_party', 10)));
});

it('lists upcoming bank holidays only', function () {
    app(Settings::class)->set('bank_holidays', [
        now()->subDays(3)->toDateString(),
        now()->addDays(5)->toDateString(),
    ]);

    $this->get('/visit')->assertInertia(fn (Assert $page) => $page
        ->has('bankHolidays', 1)
        ->where('bankHolidays.0', now()->addDays(5)->translatedFormat('l j F')));
});

it('puts every dish and every allergen in the matrix', function () {
    $this->get('/allergens')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('allergens')
            ->where('groups', function ($groups) {
                $dishes = collect($groups)->flatMap(fn ($group) => $group['dishes']);

                return $dishes->count() === 40
                    && $dishes->pluck('slug')->unique()->count() === 40;
            })
            ->has('translations.allergens', count(Allergen::cases())));
});

it('matches each dish allergen flags to its data', function () {
    $item = MenuItem::query()->get()->first(fn (MenuItem $item) => $item->allergens->count() >= 2);

    $this->get('/allergens')->assertInertia(fn (Assert $page) => $page
        ->where('groups', function ($groups) use ($item) {
            $row = collect($groups)->flatMap(fn ($group) => $group['dishes'])->firstWhere('slug', $item->slug);

            return $row['name'] === $item->name
                && collect($row['allergens'])->sort()->values()->all()
                    === $item->allergens->map(fn (Allergen $a) => $a->value)->sort()->values()->all();
        }));
});

it('footnotes modifiers that add allergens a dish does not already have', function () {
    $item = MenuItem::query()->first();
    $item->update(['allergens' => []]);
    $group = $item->modifierGroups()->create(['name' => 'Extras', 'min_select' => 0, 'max_select' => 1, 'position' => 99]);
    $group->modifiers()->create(['name' => 'Cashew crumb', 'price' => 100, 'allergens' => [Allergen::TreeNuts], 'position' => 1]);

    $this->get('/allergens')->assertInertia(fn (Assert $page) => $page
        ->where('groups', function ($groups) use ($item) {
            $row = collect($groups)->flatMap(fn ($group) => $group['dishes'])->firstWhere('slug', $item->slug);

            return $row['allergens'] === []
                && collect($row['options'])->contains(['name' => 'Cashew crumb', 'allergens' => ['tree_nuts']]);
        }));
});
