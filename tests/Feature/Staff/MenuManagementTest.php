<?php

use App\Enums\Allergen;
use App\Enums\Role;
use App\Http\Controllers\MenuController;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\PriceChange;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

function menuStaff(Role $role): User
{
    return User::factory()->role($role)->create();
}

function itemPayload(Category $category, array $overrides = []): array
{
    return [
        'name' => 'Lamb Seekh Kebab',
        'description' => 'Minced lamb, mint and green chilli, cooked on skewers in the tandoor.',
        'category_id' => $category->id,
        'price' => 895,
        'vat_rate' => 2000,
        'kcal' => 420,
        'spice_level' => 2,
        'is_vegetarian' => false,
        'is_vegan' => false,
        'allergens' => ['milk', 'mustard'],
        'allergens_checked' => true,
        'station' => 'tandoor',
        'prep_minutes' => 14,
        'is_hot' => true,
        'is_alcoholic' => false,
        'is_special' => false,
        'is_available' => true,
        'service_periods' => [],
        'variants' => [],
        'modifier_groups' => [],
        ...$overrides,
    ];
}

it('lets managers and owners in and keeps everyone else out', function (Role $role, int $status) {
    $this->actingAs(menuStaff($role))->get('/staff/menu')->assertStatus($status);
})->with([
    [Role::Manager, 200], [Role::Owner, 200],
    [Role::Waiter, 403], [Role::Kitchen, 403], [Role::Cashier, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('keeps non-managers from every write', function (Role $role) {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create();
    $user = menuStaff($role);

    $this->actingAs($user)->post('/staff/menu/items', itemPayload($category))->assertForbidden();
    $this->actingAs($user)->put("/staff/menu/items/{$item->id}", itemPayload($category))->assertForbidden();
    $this->actingAs($user)->patch("/staff/menu/items/{$item->id}/availability", ['value' => false])->assertForbidden();
    $this->actingAs($user)->post('/staff/menu/categories', ['name' => 'Grills'])->assertForbidden();
    $this->actingAs($user)->delete("/staff/menu/categories/{$category->id}")->assertForbidden();

    expect($item->fresh()->is_available)->toBeTrue();
})->with([Role::Waiter, Role::Kitchen, Role::Cashier]);

it('lists dishes with filters', function () {
    $mains = Category::factory()->create(['position' => 1]);
    $breads = Category::factory()->create(['position' => 2]);
    MenuItem::factory()->for($mains)->create(['name' => 'Lamb Rogan', 'station' => 'curry']);
    MenuItem::factory()->for($breads)->vegan()->create(['name' => 'Tandoori Roti', 'station' => 'tandoor']);
    MenuItem::factory()->for($breads)->soldOut()->create(['name' => 'Garlic Naan', 'station' => 'tandoor']);

    $this->actingAs(menuStaff(Role::Manager))->get('/staff/menu')
        ->assertInertia(fn ($page) => $page
            ->component('staff/menu/index')
            ->has('items', 3)
            ->has('categories', 2)
            ->where('total', 3));

    $names = fn (string $query) => $this->actingAs(menuStaff(Role::Manager))->get("/staff/menu?$query")->viewData('page')['props']['items'];

    expect(collect($names('q=naan'))->pluck('name')->all())->toBe(['Garlic Naan']);
    expect(collect($names("category={$mains->id}"))->pluck('name')->all())->toBe(['Lamb Rogan']);
    expect(collect($names('availability=sold_out'))->pluck('name')->all())->toBe(['Garlic Naan']);
    expect(collect($names('diet=vegan'))->pluck('name')->all())->toBe(['Tandoori Roti']);
    expect(collect($names('station=curry'))->pluck('name')->all())->toBe(['Lamb Rogan']);
});

it('creates a dish with allergens, a generated slug and a log entry', function () {
    $category = Category::factory()->create();
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->post('/staff/menu/items', itemPayload($category))
        ->assertRedirect();

    $item = MenuItem::query()->where('name', 'Lamb Seekh Kebab')->firstOrFail();

    expect($item->slug)->toBe('lamb-seekh-kebab')
        ->and($item->price)->toBe(895)
        ->and($item->allergens->map->value->all())->toBe(['milk', 'mustard'])
        ->and($item->allergens->first())->toBe(Allergen::Milk);
    expect(ActivityLog::query()->where('event', 'menu.item.created')->where('user_id', $manager->id)->exists())->toBeTrue();
});

it('makes the slug unique', function () {
    $category = Category::factory()->create();
    MenuItem::factory()->for($category)->create(['slug' => 'lamb-seekh-kebab']);

    $this->actingAs(menuStaff(Role::Manager))->post('/staff/menu/items', itemPayload($category))->assertRedirect();
    expect(MenuItem::query()->where('slug', 'lamb-seekh-kebab-2')->exists())->toBeTrue();

    $this->actingAs(menuStaff(Role::Manager))->post('/staff/menu/items', itemPayload($category, ['name' => 'Other', 'slug' => 'lamb-seekh-kebab']))
        ->assertSessionHasErrors('slug');
});

it('makes a vegan dish vegetarian too', function () {
    $category = Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))
        ->post('/staff/menu/items', itemPayload($category, ['is_vegan' => true, 'is_vegetarian' => false, 'allergens' => []]))
        ->assertRedirect();

    $item = MenuItem::query()->firstOrFail();
    expect($item->is_vegan)->toBeTrue()->and($item->is_vegetarian)->toBeTrue();
});

it('updates a dish and keeps the allergen list', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['allergens' => [Allergen::Eggs], 'slug' => 'keep-me']);

    $this->actingAs(menuStaff(Role::Manager))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['allergens' => ['sesame', 'soya'], 'name' => 'Renamed']))
        ->assertRedirect("/staff/menu/items/{$item->id}/edit");

    $item->refresh();
    expect($item->name)->toBe('Renamed')
        ->and($item->slug)->toBe('keep-me')
        ->and($item->allergens->map->value->all())->toBe(['sesame', 'soya']);
});

it('requires the allergen acknowledgement', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create();
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->post('/staff/menu/items', itemPayload($category, ['allergens_checked' => false]))
        ->assertSessionHasErrors('allergens_checked');
    $this->actingAs($manager)->put("/staff/menu/items/{$item->id}", itemPayload($category, ['allergens_checked' => null]))
        ->assertSessionHasErrors('allergens_checked');

    expect(MenuItem::query()->count())->toBe(1);
});

it('records price changes for the dish and its variants', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['price' => 800]);
    $variant = $item->variants()->create(['name' => 'Large', 'price' => 1200, 'position' => 0]);
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->put("/staff/menu/items/{$item->id}", itemPayload($category, [
        'price' => 850,
        'variants' => [['id' => $variant->id, 'name' => 'Large', 'price' => 1300, 'kcal' => null]],
    ]))->assertRedirect();

    $changes = PriceChange::query()->orderBy('id')->get();
    expect($changes)->toHaveCount(2)
        ->and([$changes[0]->old_price, $changes[0]->new_price, $changes[0]->item_variant_id, $changes[0]->user_id])->toBe([800, 850, null, $manager->id])
        ->and([$changes[1]->old_price, $changes[1]->new_price, $changes[1]->item_variant_id])->toBe([1200, 1300, $variant->id]);
    expect(ActivityLog::query()->where('event', 'menu.price.changed')->count())->toBe(2);

    $this->actingAs($manager)->get("/staff/menu/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page->has('history', 2)->where('history.0.by', $manager->name));
});

it('writes no price change when the price is untouched', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['price' => 800]);

    $this->actingAs(menuStaff(Role::Manager))->put("/staff/menu/items/{$item->id}", itemPayload($category, ['price' => 800]))->assertRedirect();

    expect(PriceChange::query()->count())->toBe(0);
});

it('lets only the owner change the VAT rate', function () {
    $category = Category::factory()->create();
    $item = MenuItem::factory()->for($category)->create(['vat_rate' => 2000]);

    $this->actingAs(menuStaff(Role::Manager))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['vat_rate' => 0]))
        ->assertForbidden();
    expect($item->fresh()->vat_rate)->toBe(2000);

    $this->actingAs(menuStaff(Role::Manager))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['vat_rate' => 2000, 'name' => 'Same rate']))
        ->assertRedirect();

    $this->actingAs(menuStaff(Role::Owner))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['vat_rate' => 0]))
        ->assertRedirect();
    expect($item->fresh()->vat_rate)->toBe(0);

    $this->actingAs(menuStaff(Role::Owner))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['vat_rate' => 1500]))
        ->assertSessionHasErrors('vat_rate');
});

it('refuses a manager creating a dish at a non-default VAT rate', function () {
    $category = Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))->post('/staff/menu/items', itemPayload($category, ['vat_rate' => 0]))->assertForbidden();
});

it('saves variants and modifier groups in order and removes dropped ones', function () {
    $category = Category::factory()->create();
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->post('/staff/menu/items', itemPayload($category, [
        'variants' => [
            ['name' => 'Half', 'price' => 700, 'kcal' => 300],
            ['name' => 'Full', 'price' => 1200, 'kcal' => 600],
        ],
        'modifier_groups' => [[
            'name' => 'Heat',
            'min' => 1,
            'max' => 1,
            'modifiers' => [
                ['name' => 'Mild', 'price' => 0, 'allergens' => []],
                ['name' => 'Extra hot', 'price' => 50, 'allergens' => ['mustard']],
            ],
        ]],
    ]))->assertRedirect();

    $item = MenuItem::query()->with(['variants', 'modifierGroups.modifiers'])->firstOrFail();
    expect($item->variants->pluck('name')->all())->toBe(['Half', 'Full'])
        ->and($item->modifierGroups->first()->modifiers->pluck('name')->all())->toBe(['Mild', 'Extra hot'])
        ->and($item->modifierGroups->first()->modifiers->last()->allergens->first())->toBe(Allergen::Mustard);

    $full = $item->variants->last();
    $this->actingAs($manager)->put("/staff/menu/items/{$item->id}", itemPayload($category, [
        'variants' => [['id' => $full->id, 'name' => 'Full', 'price' => 1200, 'kcal' => 600]],
        'modifier_groups' => [],
    ]))->assertRedirect();

    $item->refresh()->load(['variants', 'modifierGroups']);
    expect($item->variants->pluck('name')->all())->toBe(['Full'])->and($item->modifierGroups)->toHaveCount(0);
});

it('validates variants and modifier groups', function (array $overrides, string $errorKey) {
    $category = Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))
        ->post('/staff/menu/items', itemPayload($category, $overrides))
        ->assertSessionHasErrors($errorKey);

    expect(MenuItem::query()->count())->toBe(0);
})->with([
    'max below min' => [['modifier_groups' => [['name' => 'Heat', 'min' => 3, 'max' => 2, 'modifiers' => [['name' => 'A', 'price' => 0]]]]], 'modifier_groups.0.max'],
    'max zero' => [['modifier_groups' => [['name' => 'Heat', 'min' => 0, 'max' => 0, 'modifiers' => [['name' => 'A', 'price' => 0]]]]], 'modifier_groups.0.max'],
    'no modifiers' => [['modifier_groups' => [['name' => 'Heat', 'min' => 0, 'max' => 1, 'modifiers' => []]]], 'modifier_groups.0.modifiers'],
    'unnamed modifier' => [['modifier_groups' => [['name' => 'Heat', 'min' => 0, 'max' => 1, 'modifiers' => [['name' => '', 'price' => 0]]]]], 'modifier_groups.0.modifiers.0.name'],
    'unnamed variant' => [['variants' => [['name' => '', 'price' => 500]]], 'variants.0.name'],
    'negative variant price' => [['variants' => [['name' => 'Big', 'price' => -5]]], 'variants.0.price'],
]);

it('toggles availability, logs it and clears the menu cache', function () {
    $item = MenuItem::factory()->for(Category::factory())->create(['is_available' => true]);
    $manager = menuStaff(Role::Manager);
    Cache::put(MenuController::CACHE_KEY, ['stale'], 600);

    $this->actingAs($manager)->patch("/staff/menu/items/{$item->id}/availability", ['value' => false])->assertRedirect();

    expect($item->fresh()->is_available)->toBeFalse()
        ->and(Cache::has(MenuController::CACHE_KEY))->toBeFalse();
    expect(ActivityLog::query()->where('event', 'menu.item.is_available')->where('user_id', $manager->id)->count())->toBe(1);

    $this->actingAs($manager)->patch("/staff/menu/items/{$item->id}/special", ['value' => true])->assertRedirect();
    expect($item->fresh()->is_special)->toBeTrue();
});

it('clears the menu cache on every write', function () {
    $category = Category::factory()->create();
    $manager = menuStaff(Role::Manager);

    Cache::put(MenuController::CACHE_KEY, ['stale'], 600);
    $this->actingAs($manager)->post('/staff/menu/items', itemPayload($category))->assertRedirect();
    expect(Cache::has(MenuController::CACHE_KEY))->toBeFalse();

    Cache::put(MenuController::CACHE_KEY, ['stale'], 600);
    $this->actingAs($manager)->post('/staff/menu/categories', ['name' => 'Grills'])->assertRedirect();
    expect(Cache::has(MenuController::CACHE_KEY))->toBeFalse();
});

it('accepts a valid photo and stores it on the public disk', function () {
    Storage::fake('public');
    $category = Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))
        ->post('/staff/menu/items', itemPayload($category, ['photo' => UploadedFile::fake()->image('dish.jpg', 1200, 900)]))
        ->assertRedirect();

    $item = MenuItem::query()->firstOrFail();
    expect($item->image)->toStartWith('menu/');
    Storage::disk('public')->assertExists($item->image);

    $old = $item->image;
    $this->actingAs(menuStaff(Role::Manager))
        ->put("/staff/menu/items/{$item->id}", itemPayload($category, ['photo' => UploadedFile::fake()->image('new.png', 1000, 800)]))
        ->assertRedirect();
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($item->fresh()->image);
});

it('rejects photos that are the wrong type, too small or too large', function (UploadedFile $file) {
    Storage::fake('public');
    $category = Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))
        ->post('/staff/menu/items', itemPayload($category, ['photo' => $file]))
        ->assertSessionHasErrors('photo');

    expect(MenuItem::query()->count())->toBe(0);
})->with([
    'gif' => fn () => UploadedFile::fake()->image('a.gif', 1200, 900),
    'pdf' => fn () => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
    'small' => fn () => UploadedFile::fake()->image('a.jpg', 400, 300),
    'huge' => fn () => UploadedFile::fake()->image('a.jpg', 1200, 900)->size(6000),
]);

it('adds, edits and reorders categories', function () {
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->post('/staff/menu/categories', ['name' => 'Starters', 'service_periods' => ['lunch', 'dinner']])->assertRedirect();
    $this->actingAs($manager)->post('/staff/menu/categories', ['name' => 'Mains'])->assertRedirect();

    [$starters, $mains] = [Category::where('slug', 'starters')->firstOrFail(), Category::where('slug', 'mains')->firstOrFail()];
    expect($starters->service_periods)->toBe(['lunch', 'dinner'])->and($mains->position)->toBeGreaterThan($starters->position);

    $this->actingAs($manager)->post("/staff/menu/categories/{$mains->id}/move", ['direction' => 'up'])->assertRedirect();
    expect(Category::orderBy('position')->pluck('slug')->all())->toBe(['mains', 'starters']);

    $this->actingAs($manager)->post("/staff/menu/categories/{$mains->id}/move", ['direction' => 'up'])->assertRedirect();
    expect(Category::orderBy('position')->pluck('slug')->all())->toBe(['mains', 'starters']);

    $this->actingAs($manager)->patch("/staff/menu/categories/{$starters->id}", ['name' => 'Small plates', 'service_periods' => []])->assertRedirect();
    expect($starters->fresh()->name)->toBe('Small plates')->and($starters->fresh()->service_periods)->toBeNull();

    $this->actingAs($manager)->post("/staff/menu/categories/{$mains->id}/move", ['direction' => 'sideways'])->assertSessionHasErrors('direction');
});

it('refuses to delete a category that still has dishes', function () {
    $category = Category::factory()->create();
    MenuItem::factory()->for($category)->create();
    $manager = menuStaff(Role::Manager);

    $this->actingAs($manager)->delete("/staff/menu/categories/{$category->id}")->assertSessionHasErrors('category');
    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();

    $empty = Category::factory()->create();
    $this->actingAs($manager)->delete("/staff/menu/categories/{$empty->id}")->assertRedirect();
    expect(Category::query()->whereKey($empty->id)->exists())->toBeFalse();
});

it('shows the editor with the default VAT and who may change it', function () {
    Category::factory()->create();

    $this->actingAs(menuStaff(Role::Manager))->get('/staff/menu/items/create')
        ->assertInertia(fn ($page) => $page->component('staff/menu/edit')->where('item', null)->where('default_vat', 2000)->where('can_change_vat', false));

    $this->actingAs(menuStaff(Role::Owner))->get('/staff/menu/items/create')
        ->assertInertia(fn ($page) => $page->where('can_change_vat', true));
});
