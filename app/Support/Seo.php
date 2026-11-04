<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Head metadata for the current request. Shared as the `seo` Inertia prop and
 * rendered into the root Blade view, so crawlers get it without running JS.
 */
class Seo
{
    /** Path prefixes that must never be indexed. */
    private const PRIVATE_PATHS = [
        'staff', 'account', 'checkout', 'basket', 'orders', 't', 'invite', 'settings', 'styleguide',
        'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'confirm-password',
        'two-factor-challenge', 'email', 'user', 'dashboard',
    ];

    public const IMAGE_WIDTH = 1200;

    public const IMAGE_HEIGHT = 630;

    /**
     * @return array{title: string, description: string, canonical: string, image: string, type: string, robots: string, jsonld: list<array<string, mixed>>}
     */
    public static function for(Request $request): array
    {
        $name = (string) config('restaurant.name');
        $route = $request->route()?->getName();
        $title = $name;
        $description = __('seo.default_description', ['name' => $name]);
        $image = 'default';
        $type = 'website';
        $jsonld = [];

        switch ($route) {
            case 'home':
                $title = $name.' · '.__('home.meta.title');
                $description = __('home.meta.description');
                $image = 'home';
                $jsonld = [StructuredData::restaurant()];
                break;
            case 'menu.index':
                $title = __('menu.title').' · '.$name;
                $description = __('menu.meta_description');
                $image = 'menu';
                $jsonld = [StructuredData::menu()];
                break;
            case 'menu.show':
                $item = $request->route('item');

                if ($item instanceof MenuItem) {
                    $title = $item->name.' · '.$name;
                    $description = $item->description !== ''
                        ? $item->description
                        : __('seo.dish_fallback', ['dish' => $item->name, 'category' => $item->category->name, 'name' => $name]);
                    $image = 'dish-'.$item->slug;
                    $type = 'article';
                    $jsonld = [StructuredData::dishBreadcrumbs($item)];
                }
                break;
            case 'story':
                $title = __('pages.story.meta.title').' · '.$name;
                $description = __('pages.story.meta.description');
                $image = 'story';
                break;
            case 'visit':
                $title = __('pages.visit.meta.title').' · '.$name;
                $description = __('pages.visit.meta.description');
                $image = 'visit';
                $jsonld = [StructuredData::restaurant()];
                break;
            case 'allergens':
                $title = __('pages.allergens.meta.title').' · '.$name;
                $description = __('pages.allergens.meta.description');
                break;
            case 'book.create':
                $title = __('booking.title').' · '.$name;
                $description = __('booking.meta_description', ['name' => $name]);
                break;
            case 'privacy':
            case 'cookies':
                $title = __("seo.{$route}.title").' · '.$name;
                $description = __("seo.{$route}.description", ['name' => $name]);
                break;
        }

        return [
            'title' => $title,
            'description' => str($description)->squish()->limit(200)->toString(),
            'canonical' => self::canonical($request),
            'image' => self::imageUrl($image),
            'type' => $type,
            'robots' => self::isPrivate($request) ? 'noindex, nofollow' : 'index, follow',
            'jsonld' => $jsonld,
        ];
    }

    public static function isPrivate(Request $request): bool
    {
        $first = $request->segment(1);

        if ($first === 'book' && $request->segment(2) !== null && $request->segment(2) !== 'slots') {
            return true;
        }

        return $first !== null && in_array($first, self::PRIVATE_PATHS, true);
    }

    public static function imageUrl(string $name): string
    {
        $file = file_exists(public_path("media/og/{$name}.jpg")) ? $name : 'default';

        return url("media/og/{$file}.jpg");
    }

    /** Query strings (filters, tracking) never change the canonical page. */
    private static function canonical(Request $request): string
    {
        return rtrim($request->url(), '/') ?: url('/');
    }

    /**
     * @return list<array{loc: string, lastmod: string|null}>
     */
    public static function sitemapEntries(): array
    {
        $latest = MenuItem::query()->max('updated_at');
        $entries = [
            ['loc' => url('/'), 'lastmod' => $latest],
            ['loc' => route('menu.index'), 'lastmod' => $latest],
        ];

        foreach (MenuItem::query()->orderBy('position')->get(['slug', 'updated_at']) as $item) {
            $entries[] = ['loc' => route('menu.show', $item->slug), 'lastmod' => $item->updated_at?->toIso8601String()];
        }

        foreach (['book.create', 'story', 'visit', 'allergens', 'privacy', 'cookies'] as $name) {
            if (Route::has($name)) {
                $entries[] = ['loc' => route($name), 'lastmod' => null];
            }
        }

        return array_map(fn (array $entry) => [
            'loc' => $entry['loc'],
            'lastmod' => $entry['lastmod'] === null ? null : Carbon::parse($entry['lastmod'])->toIso8601String(),
        ], $entries);
    }
}
