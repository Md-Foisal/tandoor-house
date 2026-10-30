<?php

use App\Enums\Role;
use App\Jobs\ConvertMenuPhoto;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function photoItemPayload(Category $category): array
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
        'allergens' => ['milk'],
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
    ];
}

function uploadedItem(): MenuItem
{
    $item = MenuItem::factory()->for(Category::factory())->create();
    $item->image = UploadedFile::fake()->image('dish.jpg', 1200, 900)->store('menu', 'public');
    $item->save();

    return $item;
}

beforeEach(function () {
    Storage::fake('public');
    $this->manager = User::factory()->role(Role::Manager)->create();
});

it('queues the conversion when staff upload a photo', function () {
    Queue::fake();
    $category = Category::factory()->create();

    $this->actingAs($this->manager)
        ->post('/staff/menu/items', [...photoItemPayload($category), 'photo' => UploadedFile::fake()->image('dish.jpg', 1200, 900)])
        ->assertSessionHasNoErrors();

    $item = MenuItem::query()->firstOrFail();

    Queue::assertPushed(ConvertMenuPhoto::class, fn ($job) => $job->menuItemId === $item->id && $job->source === $item->image);
    expect($item->photo)->toBeNull();
});

it('does not queue a conversion when no photo is sent', function () {
    Queue::fake();

    $this->actingAs($this->manager)
        ->post('/staff/menu/items', photoItemPayload(Category::factory()->create()))
        ->assertSessionHasNoErrors();

    Queue::assertNothingPushed();
});

it('writes 4:5 crops, both formats and a placeholder for a real JPEG', function () {
    $item = uploadedItem();

    (new ConvertMenuPhoto($item->id, $item->image))->handle();

    $photo = $item->fresh()->photo;

    expect($photo['width'])->toBe(1600)
        ->and($photo['height'])->toBe(2000)
        ->and($photo['placeholder'])->toStartWith('data:image/webp;base64,')
        ->and(array_column($photo['sources']['webp'], 0))->toBe([480, 960, 1600]);

    foreach ($photo['sources']['webp'] as [$width, $path]) {
        expect($path)->toMatch("#^menu/{$item->id}/[a-f0-9]{12}-$width\.webp$#");
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        expect([$w, $h])->toBe([$width, (int) round($width * 1.25)]);
    }

    foreach ($photo['sources']['avif'] as [, $path]) {
        Storage::disk('public')->assertExists($path);
    }
});

it('cleans up the old derived files when a photo is replaced', function () {
    $item = uploadedItem();
    (new ConvertMenuPhoto($item->id, $item->image))->handle();
    $item->refresh();
    $oldFiles = [$item->image, ...$item->photoPaths()];

    $this->actingAs($this->manager)
        ->put("/staff/menu/items/{$item->id}", [...photoItemPayload($item->category), 'photo' => UploadedFile::fake()->image('new.jpg', 1000, 800)])
        ->assertSessionHasNoErrors();

    $item->refresh();

    foreach ($oldFiles as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    expect($item->image)->not->toBeNull()
        ->and($item->image)->not->toBe($oldFiles[0]);
    Storage::disk('public')->assertExists($item->image);
    expect(array_diff($oldFiles, $item->photoPaths()))->toEqual($oldFiles);
});

it('removes the original and every derived file when the photo is removed', function () {
    $item = uploadedItem();
    (new ConvertMenuPhoto($item->id, $item->image))->handle();
    $item->refresh();
    $files = [$item->image, ...$item->photoPaths()];

    $this->actingAs($this->manager)
        ->put("/staff/menu/items/{$item->id}", [...photoItemPayload($item->category), 'remove_photo' => true])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->image)->toBeNull()->and($item->photo)->toBeNull();

    foreach ($files as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

it('discards a conversion that finished after the photo was replaced', function () {
    $item = uploadedItem();
    $stale = $item->image;
    $item->forceFill(['image' => UploadedFile::fake()->image('newer.jpg', 1000, 800)->store('menu', 'public')])->save();

    (new ConvertMenuPhoto($item->id, $stale))->handle();

    expect($item->fresh()->photo)->toBeNull();
    expect(Storage::disk('public')->allFiles("menu/{$item->id}"))->toBe([]);
});

it('serves an uploaded photo ahead of the manifest key', function () {
    $item = uploadedItem();
    $item->forceFill(['is_available' => true])->save();
    (new ConvertMenuPhoto($item->id, $item->image))->handle();

    $this->get("/menu/{$item->slug}")
        ->assertInertia(fn ($page) => $page
            ->where('item.image.width', 1600)
            ->where('item.image.sources.webp.0.1', fn ($url) => str_contains($url, "menu/{$item->id}/"))
            ->has('item.image.placeholder'));
});

it('falls back to the manifest key without an upload', function () {
    $item = MenuItem::factory()->for(Category::factory())->create();

    $this->get("/menu/{$item->slug}")
        ->assertInertia(fn ($page) => $page->where('item.image', "dish/{$item->slug}"));
});
