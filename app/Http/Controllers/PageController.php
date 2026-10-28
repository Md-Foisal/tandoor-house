<?php

namespace App\Http\Controllers;

use App\Enums\Allergen;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Services\OpeningHours;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    private const DAAL_HOURS = 18;

    public function story(Settings $settings): Response
    {
        return Inertia::render('story', [
            'numbers' => [
                'years' => max(1, now()->year - (int) $settings->get('opened_year', 2016)),
                'opened' => (int) $settings->get('opened_year', 2016),
                'dishes' => MenuItem::query()->count(),
                'daal_hours' => self::DAAL_HOURS,
            ],
        ]);
    }

    public function visit(OpeningHours $hours, Settings $settings): Response
    {
        return Inertia::render('visit', [
            'hours' => $hours->week(),
            'bankHolidays' => $this->upcomingBankHolidays($settings),
            'maps' => $this->mapLinks(),
            'maxParty' => (int) data_get($settings->get('reservation', []), 'max_party', 10),
        ]);
    }

    public function allergens(): Response
    {
        $categories = Category::query()
            ->with('items.modifierGroups.modifiers')
            ->orderBy('position')
            ->get();

        return Inertia::render('allergens', [
            'groups' => $categories
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'dishes' => $category->items
                        ->map(fn (MenuItem $item) => $this->matrixRow($item))
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $group) => $group['dishes'] !== [])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array{slug: string, name: string, allergens: array<int, string>, options: array<int, array{name: string, allergens: array<int, string>}>}
     */
    private function matrixRow(MenuItem $item): array
    {
        $own = $item->allergens->map(fn (Allergen $allergen) => $allergen->value);

        $options = $item->modifierGroups
            ->flatMap(fn (ModifierGroup $group) => $group->modifiers)
            ->map(fn (Modifier $modifier) => [
                'name' => $modifier->name,
                'allergens' => ($modifier->allergens ?? collect())
                    ->map(fn (Allergen $allergen) => $allergen->value)
                    ->reject(fn (string $code) => $own->contains($code))
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $option) => $option['allergens'] !== [])
            ->values()
            ->all();

        return [
            'slug' => $item->slug,
            'name' => $item->name,
            'allergens' => $own->values()->all(),
            'options' => $options,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function upcomingBankHolidays(Settings $settings): array
    {
        $today = now()->startOfDay();

        return collect((array) $settings->get('bank_holidays', []))
            ->map(fn ($date) => CarbonImmutable::parse((string) $date, config('app.timezone'))->startOfDay())
            ->filter(fn (CarbonImmutable $date) => $date->greaterThanOrEqualTo($today))
            ->sort()
            ->take(4)
            ->map(fn (CarbonImmutable $date) => $date->translatedFormat('l j F'))
            ->values()
            ->all();
    }

    /**
     * @return array{google: string, apple: string, address: string}
     */
    private function mapLinks(): array
    {
        $address = (array) config('restaurant.contact.address');
        $full = implode(', ', array_filter([
            config('restaurant.name'),
            $address['line1'] ?? null,
            $address['city'] ?? null,
            $address['postcode'] ?? null,
        ]));

        return [
            'address' => $full,
            'google' => 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => $full], '', '&', PHP_QUERY_RFC3986),
            'apple' => 'https://maps.apple.com/?'.http_build_query(['q' => (string) config('restaurant.name'), 'address' => $full], '', '&', PHP_QUERY_RFC3986),
        ];
    }
}
