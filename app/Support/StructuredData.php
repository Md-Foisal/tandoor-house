<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Builds schema.org JSON-LD documents from real data. Output is plain arrays;
 * encode with self::encode() so it is safe inside a script tag.
 */
class StructuredData
{
    private const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public const MENU_CACHE_KEY = 'seo.menu.v1';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_LINE_TERMINATORS,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function restaurant(): array
    {
        $address = (array) config('restaurant.contact.address');
        $contact = (array) config('restaurant.contact');

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Restaurant',
            '@id' => url('/').'#restaurant',
            'name' => config('restaurant.name'),
            'url' => url('/'),
            'image' => Seo::imageUrl('default'),
            'telephone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'priceRange' => __('seo.price_range'),
            'servesCuisine' => Str::headline((string) config('restaurant.cuisine')),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['line1'] ?? null,
                'addressLocality' => $address['city'] ?? null,
                'postalCode' => $address['postcode'] ?? null,
                'addressCountry' => 'GB',
            ],
            'acceptsReservations' => route('book.create'),
            'hasMenu' => route('menu.index'),
            'openingHoursSpecification' => self::openingHours(),
        ];

        if (isset($contact['latitude'], $contact['longitude'])) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $contact['latitude'],
                'longitude' => (float) $contact['longitude'],
            ];
        }

        $sameAs = array_values(array_filter((array) config('restaurant.same_as', [])));

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return self::clean($data);
    }

    /**
     * One entry per service period; days sharing the same periods are grouped.
     *
     * @return list<array<string, mixed>>
     */
    public static function openingHours(): array
    {
        $week = (array) app(Settings::class)->get('opening_hours', []);
        $groups = [];

        foreach (self::DAYS as $number => $name) {
            foreach ((array) ($week[$number] ?? $week[(string) $number] ?? []) as $period) {
                $groups[$period[0].'-'.$period[1]]['period'] = $period;
                $groups[$period[0].'-'.$period[1]]['days'][] = $name;
            }
        }

        return array_values(array_map(fn (array $group) => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $group['days'],
            'opens' => $group['period'][0],
            'closes' => $group['period'][1],
        ], $groups));
    }

    /**
     * @return array<string, mixed>
     */
    public static function menu(): array
    {
        return Cache::remember(self::MENU_CACHE_KEY, 600, function (): array {
            $sections = Category::query()
                ->orderBy('position')
                ->with(['items' => fn ($query) => $query->with('variants')->orderBy('position')])
                ->get()
                ->filter(fn (Category $category) => $category->items->isNotEmpty())
                ->map(fn (Category $category) => array_filter([
                    '@type' => 'MenuSection',
                    'name' => $category->name,
                    'description' => $category->description,
                    'hasMenuItem' => $category->items->map(fn (MenuItem $item) => self::menuItem($item))->all(),
                ]))
                ->values()
                ->all();

            return [
                '@context' => 'https://schema.org',
                '@type' => 'Menu',
                'name' => __('menu.title').' · '.config('restaurant.name'),
                'url' => route('menu.index'),
                'inLanguage' => 'en-GB',
                'hasMenuSection' => $sections,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function menuItem(MenuItem $item): array
    {
        $diets = [];

        if ($item->is_vegan) {
            $diets[] = 'https://schema.org/VeganDiet';
        }

        if ($item->is_vegetarian || $item->is_vegan) {
            $diets[] = 'https://schema.org/VegetarianDiet';
        }

        $prices = $item->variants->isNotEmpty() ? $item->variants->pluck('price') : collect([$item->price]);

        return array_filter([
            '@type' => 'MenuItem',
            'name' => $item->name,
            'description' => $item->description,
            'url' => route('menu.show', $item),
            'suitableForDiet' => $diets ?: null,
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($prices->min() / 100, 2, '.', ''),
                'priceCurrency' => 'GBP',
                'availability' => $item->is_available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function dishBreadcrumbs(MenuItem $item): array
    {
        $trail = [
            [config('restaurant.name'), url('/')],
            [__('menu.title'), route('menu.index')],
            [$item->name, route('menu.show', $item)],
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ], $trail, array_keys($trail)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
