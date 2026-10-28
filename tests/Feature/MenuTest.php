<?php

use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(MenuSeeder::class);
});

it('lists every category with all its dishes', function () {
    $this->get('/menu')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('menu/index')
            ->loadDeferredProps(fn (Assert $page) => $page
                ->has('categories', 8)
                ->where('categories', fn ($categories) => collect($categories)->sum(fn ($category) => count($category['items'])) === 40)));
});

it('defers the menu groups so the page can show a skeleton first', function () {
    $this->get('/menu')
        ->assertInertia(fn (Assert $page) => $page->missing('categories'));
});

it('keeps sold-out dishes on the menu, flagged', function () {
    $item = MenuItem::query()->first();
    $item->update(['is_available' => false]);

    $this->get('/menu')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $page) => $page
                ->where('categories', fn ($categories) => collect($categories)
                    ->flatMap(fn ($category) => $category['items'])
                    ->contains(fn ($row) => $row['slug'] === $item->slug && $row['is_available'] === false))));
});

it('caches the menu under its own key', function () {
    $this->get('/menu')->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page->has('categories')));

    expect(Cache::has('menu.v1'))->toBeTrue();
});

it('includes variants and modifier groups for each dish', function () {
    $this->get('/menu')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $page) => $page
                ->where('categories', fn ($categories) => collect($categories)
                    ->flatMap(fn ($category) => $category['items'])
                    ->contains(fn ($row) => $row['has_options'] && count($row['variants']) > 0))));
});

it('shows a dish with its variants, modifiers and allergens', function () {
    $item = MenuItem::query()->whereHas('variants')->firstOrFail();

    $this->get("/menu/{$item->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('menu/show')
            ->where('item.slug', $item->slug)
            ->has('item.variants', $item->variants()->count())
            ->has('item.allergens', $item->allergens->count())
            ->has('item.modifier_groups')
            ->where('category.name', $item->category->name)
            ->has('related', 3));
});

it('shows modifier groups with their limits', function () {
    $item = MenuItem::query()->whereHas('modifierGroups')->firstOrFail();

    $this->get("/menu/{$item->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('item.modifier_groups.0', fn (Assert $group) => $group
                ->hasAll(['id', 'name', 'min', 'max', 'modifiers'])
                ->etc()));
});

it('returns related dishes from the same category only', function () {
    $item = MenuItem::query()->firstOrFail();

    $this->get("/menu/{$item->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('related', fn ($related) => collect($related)->every(fn ($row) => $row['category_id'] === $item->category_id && $row['slug'] !== $item->slug)));
});

it('serves a sold-out dish page, flagged', function () {
    $item = MenuItem::query()->firstOrFail();
    $item->update(['is_available' => false]);

    $this->get("/menu/{$item->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('item.is_available', false));
});

it('returns 404 for an unknown dish', function () {
    $this->get('/menu/not-a-real-dish')->assertNotFound();
});

it('rejects adding a sized dish without a valid size', function () {
    $item = MenuItem::query()->whereHas('variants')->firstOrFail();

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1])
        ->assertSessionHasErrors('variant');

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'item_variant_id' => 999999, 'quantity' => 1])
        ->assertSessionHasErrors('variant');

    expect(app(CartService::class)->count())->toBe(0);
});

it('rejects a dish that has no sizes being given one', function () {
    $item = MenuItem::query()->whereDoesntHave('variants')->firstOrFail();
    $other = MenuItem::query()->whereHas('variants')->firstOrFail();

    $this->post('/basket/lines', [
        'menu_item_id' => $item->id,
        'item_variant_id' => $other->variants->first()->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('variant');
});

it('enforces the minimum and maximum of a modifier group', function () {
    $item = MenuItem::query()
        ->whereDoesntHave('variants')
        ->whereHas('modifierGroups', fn ($query) => $query->where('min_select', '>', 0))
        ->with('modifierGroups.modifiers')
        ->firstOrFail();
    $group = $item->modifierGroups->firstWhere('min_select', '>', 0);

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1, 'modifier_ids' => []])
        ->assertSessionHasErrors('modifiers');

    $tooMany = $group->modifiers->pluck('id')->take($group->max_select + 1)->all();

    if (count($tooMany) > $group->max_select) {
        $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1, 'modifier_ids' => $tooMany])
            ->assertSessionHasErrors('modifiers');
    }
});

it('rejects a modifier from another dish', function () {
    $item = MenuItem::query()->whereDoesntHave('modifierGroups')->whereDoesntHave('variants')->firstOrFail();
    $other = MenuItem::query()->whereHas('modifierGroups')->with('modifierGroups.modifiers')->firstOrFail();

    $this->post('/basket/lines', [
        'menu_item_id' => $item->id,
        'quantity' => 1,
        'modifier_ids' => [$other->modifierGroups->first()->modifiers->first()->id],
    ])->assertSessionHasErrors('modifiers');
});

it('rejects notes over 140 characters', function () {
    $item = MenuItem::query()->whereDoesntHave('variants')->whereDoesntHave('modifierGroups')->firstOrFail();

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1, 'notes' => str_repeat('a', 141)])
        ->assertSessionHasErrors('notes');
});

it('refuses to add a sold-out dish', function () {
    $item = MenuItem::query()->whereDoesntHave('variants')->whereDoesntHave('modifierGroups')->firstOrFail();
    $item->update(['is_available' => false]);

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1])
        ->assertSessionHasErrors('item');
});

it('adds a dish from its page and raises the basket count', function () {
    $item = MenuItem::query()->whereHas('variants')->with('variants')->firstOrFail();

    $this->actingAs(User::factory()->create())
        ->get("/menu/{$item->slug}")
        ->assertInertia(fn (Assert $page) => $page->where('basketCount', 0));

    $this->post('/basket/lines', [
        'menu_item_id' => $item->id,
        'item_variant_id' => $item->variants->first()->id,
        'quantity' => 2,
        'notes' => 'Extra lemon',
    ])->assertSessionHasNoErrors();

    $this->app->forgetInstance(CartService::class);

    $this->get("/menu/{$item->slug}")
        ->assertInertia(fn (Assert $page) => $page->where('basketCount', 2));
});

it('adds a dish with options from the menu quick add only when it has none', function () {
    $item = MenuItem::query()->whereDoesntHave('variants')->whereDoesntHave('modifierGroups')->firstOrFail();

    $this->post('/basket/lines', ['menu_item_id' => $item->id])
        ->assertSessionHasErrors('quantity');

    $this->post('/basket/lines', ['menu_item_id' => $item->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();
});

it('flags allergens that depend on the chosen option', function () {
    $chai = MenuItemResource::make(
        MenuItem::with(['variants', 'modifierGroups.modifiers'])->where('slug', 'masala-chai')->firstOrFail()
    )->resolve();

    expect($chai['allergens']->all())->toBe([])
        ->and($chai['option_allergens']->all())->toEqualCanonicalizing(['milk', 'gluten']);
});
