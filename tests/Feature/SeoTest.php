<?php

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->seed(MenuSeeder::class);
});

/**
 * @return list<array<string, mixed>>
 */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $raw) => json_decode($raw, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

it('puts the head tags in the raw html of the home page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
        ->toContain('<meta property="og:title"')
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:locale" content="en_GB">')
        ->toContain('<meta property="og:site_name" content="Tandoor House">')
        ->toContain('<meta property="og:image" content="'.url('media/og/home.jpg').'">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="robots" content="index, follow">');
});

it('describes the restaurant with real hours in json-ld', function () {
    $restaurant = collect(jsonLd($this->get('/')->getContent()))->firstWhere('@type', 'Restaurant');

    expect($restaurant)
        ->name->toBe('Tandoor House')
        ->hasMenu->toBe(route('menu.index'))
        ->acceptsReservations->toBe(route('book.create'))
        ->and($restaurant['geo']['@type'])->toBe('GeoCoordinates')
        ->and($restaurant)->not->toHaveKey('sameAs');

    $tuesday = collect($restaurant['openingHoursSpecification'])->filter(fn ($s) => in_array('Tuesday', $s['dayOfWeek']));
    expect($tuesday->pluck('opens')->all())->toBe(['12:00', '17:30'])
        ->and(collect($restaurant['openingHoursSpecification'])->contains(fn ($s) => in_array('Monday', $s['dayOfWeek'])))->toBeFalse();
});

it('lists the menu with sections, GBP offers and diets', function () {
    $html = $this->get('/menu')->assertOk()->getContent();
    $menu = collect(jsonLd($html))->firstWhere('@type', 'Menu');

    expect($menu['hasMenuSection'])->not->toBeEmpty()
        ->and($menu['hasMenuSection'][0]['@type'])->toBe('MenuSection');

    $items = collect($menu['hasMenuSection'])->flatMap(fn ($s) => $s['hasMenuItem']);
    expect($items->first()['offers']['priceCurrency'])->toBe('GBP')
        ->and($items->contains(fn ($i) => in_array('https://schema.org/VegetarianDiet', $i['suitableForDiet'] ?? [])))->toBeTrue();
});

it('titles a dish page and adds breadcrumbs without js', function () {
    $item = MenuItem::query()->where('is_available', true)->firstOrFail();
    $html = $this->get(route('menu.show', $item))->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="'.e($item->name).' · Tandoor House">')
        ->toContain('<link rel="canonical" href="'.route('menu.show', $item).'">')
        ->toContain('<meta property="og:type" content="article">');

    $crumbs = collect(jsonLd($html))->firstWhere('@type', 'BreadcrumbList');
    expect($crumbs['itemListElement'])->toHaveCount(3)
        ->and($crumbs['itemListElement'][2]['name'])->toBe($item->name);
});

it('escapes script-closing text in json-ld', function () {
    $item = MenuItem::query()->firstOrFail();
    $item->update(['description' => 'Hot </script><script>alert(1)</script>']);

    $html = $this->get(route('menu.show', $item))->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
});

it('lists every dish in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk();
    $xml = $response->getContent();

    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and(simplexml_load_string($xml))->not->toBeFalse();

    foreach (MenuItem::all() as $item) {
        expect($xml)->toContain(route('menu.show', $item));
    }

    expect($xml)->toContain(route('story'))->toContain('<lastmod>');
});

it('serves robots.txt that hides private areas and points at the sitemap', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /staff', false)
        ->assertSee('Disallow: /account', false)
        ->assertSee('Disallow: /t/', false)
        ->assertSee('Disallow: /webhooks', false)
        ->assertSee('Sitemap: '.route('sitemap'), false);
});

it('marks private pages noindex', function (string $path) {
    expect($this->get($path)->getContent())->toContain('<meta name="robots" content="noindex, nofollow">');
})->with(['/basket', '/login', '/register']);
