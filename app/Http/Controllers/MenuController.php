<?php

namespace App\Http\Controllers;

use App\Http\Resources\MenuItemResource;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public const CACHE_KEY = 'menu.v1';

    private const CACHE_SECONDS = 600;

    public function index(): Response
    {
        return Inertia::render('menu/index', [
            'categories' => Inertia::defer(fn () => $this->categories()),
        ]);
    }

    public function show(MenuItem $item): Response
    {
        $item->load(['category', 'variants', 'modifierGroups.modifiers']);

        $related = MenuItem::query()
            ->where('category_id', $item->category_id)
            ->whereKeyNot($item->id)
            ->orderByDesc('is_available')
            ->orderBy('position')
            ->limit(3)
            ->with(['variants'])
            ->get();

        return Inertia::render('menu/show', [
            'item' => (new MenuItemResource($item))->resolve(),
            'category' => ['name' => $item->category->name, 'slug' => $item->category->slug],
            'related' => MenuItemResource::collection($related)->resolve(),
        ]);
    }

    /**
     * Cached values must be plain arrays: the cache store refuses to rebuild
     * collections, so resource output is flattened through JSON.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, mixed>
     */
    private function plain(array $rows): array
    {
        return json_decode(json_encode($rows, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Every category with its dishes, sold-out ones included so they can be
     * shown disabled. Cached briefly; menu changes should forget CACHE_KEY.
     *
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function (): array {
            return Category::query()
                ->orderBy('position')
                ->with(['items' => fn ($query) => $query->with(['variants', 'modifierGroups.modifiers'])])
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    'items' => $this->plain(MenuItemResource::collection($category->items)->resolve()),
                ])
                ->all();
        });
    }
}
